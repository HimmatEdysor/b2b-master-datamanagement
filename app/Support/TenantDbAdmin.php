<?php

namespace App\Support;

use Illuminate\Support\Str;
use PDO;
use PDOException;

/**
 * MySQL admin credentials for provisioning (CREATE DATABASE, mysqldump, CREATE USER).
 * Values come from config/master.php (TENANT_DB_* with DB_* fallback).
 *
 * Per-tenant CRM credentials are stored on tenants.database_* after approval (password encrypted).
 */
class TenantDbAdmin
{
    /** Bump when mysqldump RDS behaviour changes (grep on server to confirm deploy). */
    public const MYSQLDUMP_RDS_BUILD = '2026-05-22-rds-safe';

    public static function host(): string
    {
        return mysql_connect_host((string) config('master.tenant_db_host', '127.0.0.1'));
    }

    public static function port(): int
    {
        return (int) config('master.tenant_db_port', 3306);
    }

    public static function username(): string
    {
        return (string) config('master.tenant_db_username', 'root');
    }

    public static function password(): string
    {
        return (string) config('master.tenant_db_password', '');
    }

    /**
     * Prefer MYSQL_PWD in {@see mysqlCliEnv()} for mysqldump/mysql CLI (avoids password warning on stderr).
     *
     * @return list<string>
     */
    public static function mysqlPasswordArgs(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function mysqlCliEnv(): array
    {
        $pass = self::password();

        return $pass !== '' ? ['MYSQL_PWD' => $pass] : [];
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertCanProvision(): void
    {
        $host = self::host();
        $user = self::username();
        $pass = self::password();

        if ($pass === '' && ! self::isLoopbackHost($host)) {
            throw new \RuntimeException(
                'Remote MySQL ('.$host.') requires a password. Set TENANT_DB_PASSWORD in .env '
                .'(or DB_PASSWORD if TENANT_DB_PASSWORD is empty). User: '.$user
            );
        }
    }

    public static function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host));

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    public static function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    public static function cloneMethod(): string
    {
        $method = strtolower(trim((string) config('master.tenant_db_clone_method', 'pdo')));

        return in_array($method, ['pdo', 'mysqldump'], true) ? $method : 'pdo';
    }

    /**
     * Global *.* privileges for TENANT_DB_USERNAME (RDS master setup — not ALL PRIVILEGES).
     *
     * @return list<string>
     */
    public static function globalProvisionerPrivileges(): array
    {
        return self::normalizePrivilegeList(config('master.tenant_db_global_privileges', []));
    }

    /**
     * Per-tenant database privileges for provisioning admin + schema clone/seed.
     *
     * @return list<string>
     */
    public static function databaseProvisionerPrivileges(): array
    {
        return self::normalizePrivilegeList(config('master.tenant_db_database_privileges', []));
    }

    /**
     * Read-only on template DB.
     *
     * @return list<string>
     */
    public static function templateReadPrivileges(): array
    {
        return self::normalizePrivilegeList(config('master.tenant_db_template_privileges', []));
    }

    /**
     * Dedicated CRM MySQL user per company.
     *
     * @return list<string>
     */
    public static function tenantAppPrivileges(): array
    {
        return self::normalizePrivilegeList(config('master.tenant_db_tenant_user_privileges', []));
    }

    /**
     * @param  list<string>  $privileges
     */
    public static function privilegesSql(array $privileges): string
    {
        return implode(', ', self::normalizePrivilegeList($privileges));
    }

    /**
     * @param  list<string>  $privileges
     */
    public static function buildGrantOnDatabaseSql(
        string $databaseName,
        string $username,
        string $host,
        array $privileges
    ): string {
        $privSql = self::privilegesSql($privileges);
        $quotedDb = self::quoteIdentifier($databaseName);
        $user = str_replace("'", "''", $username);
        $host = str_replace("'", "''", $host);

        return "GRANT {$privSql} ON {$quotedDb}.* TO '{$user}'@'{$host}'";
    }

    /**
     * @return list<string>
     */
    public static function grantStatementsForAdminOnDatabase(string $databaseName): array
    {
        $user = self::username();
        $privileges = self::databaseProvisionerPrivileges();
        $statements = [];

        foreach (self::adminGrantHosts() as $host) {
            $statements[] = self::buildGrantOnDatabaseSql($databaseName, $user, $host, $privileges);
        }

        return $statements;
    }

    /**
     * @param  list<string>  $privileges
     * @return list<string>
     */
    protected static function normalizePrivilegeList(array $privileges): array
    {
        return array_values(array_filter(
            array_map('trim', $privileges),
            fn (string $p) => $p !== ''
        ));
    }

    /**
     * @throws PDOException
     */
    /**
     * @return array<int, mixed>
     */
    public static function pdoOptions(): array
    {
        $timeout = max(2, (int) config('master.tenant_db_connect_timeout', 5));

        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => $timeout,
        ];
    }

    public static function adminPdo(): PDO
    {
        self::assertCanProvision();

        return new PDO(
            self::dsn(),
            self::username(),
            self::password(),
            self::pdoOptions()
        );
    }

    public static function tryAdminPdo(): ?PDO
    {
        try {
            return self::adminPdo();
        } catch (PDOException) {
            return null;
        }
    }

    public static function dsn(?string $database = null): string
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            self::host(),
            self::port()
        );

        if ($database !== null && $database !== '') {
            $dsn .= ';dbname='.str_replace([';', ' '], '', $database);
        }

        return $dsn;
    }

    public static function connectionErrorMessage(PDOException $e): string
    {
        if (str_starts_with($e->getMessage(), 'Cannot connect to tenant MySQL')) {
            return preg_replace('/\s+Original:.*$/s', '', $e->getMessage()) ?: $e->getMessage();
        }

        $user = self::username();
        $host = self::host();
        $raw = $e->getMessage();

        if ((int) $e->getCode() === 1045 || str_contains($raw, '1045')) {
            $source = app(\App\Services\MasterSettingsService::class)->tenantDbPasswordSource();
            $hint = match ($source) {
                'master_settings' => ' Password is from Admin → Web settings (overrides .env). Update it there or run the RDS setup SQL with the same password.',
                'env_tenant' => ' Password is from TENANT_DB_PASSWORD in .env — must match MySQL.',
                'env_db_fallback' => ' Password is from DB_PASSWORD (TENANT_DB_PASSWORD empty) — must match MySQL user `'.$user.'`.',
                default => ' TENANT_DB_PASSWORD is empty.',
            };

            return "Cannot connect to tenant MySQL as `{$user}` at `{$host}`.{$hint}"
                ." User must exist as `{$user}`@'%' (MySQL matches `{$user}`@'<app-server-ip>' to `{$user}`@'%'). "
                .'Fix: Admin → Web settings → Test connection, or `php artisan tenant:db-admin-check` on the server. '
                ."MySQL said: {$raw}";
        }

        return "Tenant MySQL connection failed ({$host}): {$raw}";
    }

    /**
     * @return list<string>
     */
    public static function adminGrantHosts(): array
    {
        $hosts = config('master.tenant_db_admin_grant_hosts', ['%']);

        $hosts = array_values(array_unique(array_filter(
            is_array($hosts) ? $hosts : [$hosts],
            fn ($h) => is_string($h) && $h !== ''
        )));

        return $hosts !== [] ? $hosts : ['%'];
    }

    public static function shouldGrantAdminOnCreate(): bool
    {
        if (self::usesSharedTenantCredentials()) {
            return false;
        }

        return (bool) config('master.tenant_db_grant_admin_on_create', false);
    }

    /**
     * RDS: b2b_master + b2b_tenant_% wildcard — no per-tenant MySQL users (no GRANT OPTION).
     */
    public static function usesSharedTenantCredentials(): bool
    {
        return (bool) config('master.tenant_db_shared_credentials', false);
    }

    /**
     * CRM / resolve API: b2b_master + company database_name (wildcard b2b_tenant_% on RDS).
     *
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    public static function tenantConnectionConfig(\App\Models\Tenant $tenant): array
    {
        $database = (string) $tenant->database_name;
        if ($database === '') {
            throw new \RuntimeException("Company [{$tenant->slug}] has no database_name.");
        }

        self::assertCanProvision();

        return [
            'host' => (string) ($tenant->database_host ?: self::host()),
            'port' => (int) ($tenant->database_port ?: self::port()),
            'database' => $database,
            'username' => self::username(),
            'password' => self::password(),
        ];
    }

    public static function tenantDatabasePrefix(): string
    {
        return (string) config('master.tenant_database_prefix', 'b2b_tenant_');
    }

    /** Wildcard used in RDS GRANT ON `prefix%`.* */
    public static function tenantDatabaseGrantPattern(): string
    {
        return self::tenantDatabasePrefix().'%';
    }

    /**
     * Same naming as real companies: prefix + slug (see TenantProvisionerService::reserveDatabaseName).
     */
    public static function tenantDatabaseNameFromSlug(string $slug): string
    {
        return self::tenantDatabasePrefix().Str::slug($slug, '');
    }

    /**
     * Reserved slug for RDS self-check only — do not register a company with this slug.
     */
    public static function provisionCheckSlug(): string
    {
        return (string) config('master.tenant_provision_check_slug', 'provisioncheck');
    }

    /**
     * Temporary DB for tenant:db-admin-check (same pattern as b2b_tenant_{company_slug}).
     */
    public static function provisionCheckDatabaseName(): string
    {
        return self::tenantDatabaseNameFromSlug(self::provisionCheckSlug());
    }

    /**
     * Step 2 of provisioning: ensure TENANT_DB_USERNAME can use the new tenant database.
     */
    public static function grantAdminOnTenantDatabase(PDO $pdo, string $databaseName): void
    {
        if (! self::shouldGrantAdminOnCreate()) {
            return;
        }

        foreach (self::grantStatementsForAdminOnDatabase($databaseName) as $sql) {
            $pdo->exec($sql);
        }

        self::flushPrivilegesIfAllowed($pdo);
    }

    /**
     * After CREATE DATABASE: GRANT on this DB (if enabled), flush privileges, verify USE works.
     * RDS often allows CREATE on *.* but not USE until `b2b_tenant_%`.* (or per-DB GRANT) exists.
     *
     * @return bool true when per-database GRANT ran; false when skipped or pre-granted wildcard only
     *
     * @throws \RuntimeException when the admin user still cannot access the database (1044)
     */
    public static function grantAndVerifyAdminAccessToTenantDatabase(PDO $pdo, string $databaseName): bool
    {
        $granted = false;

        if (self::shouldGrantAdminOnCreate()) {
            try {
                self::grantAdminOnTenantDatabase($pdo, $databaseName);
                $granted = true;
            } catch (PDOException $e) {
                if (! self::adminHasWildcardTenantDatabaseGrant($pdo)) {
                    throw new \RuntimeException(
                        self::databaseAccessDeniedMessage($databaseName, $e->getMessage()),
                        0,
                        $e
                    );
                }
            }
        }

        self::flushPrivilegesIfAllowed($pdo);

        if (! self::adminCanUseDatabase($pdo, $databaseName)) {
            throw new \RuntimeException(self::databaseAccessDeniedMessage($databaseName));
        }

        return $granted;
    }

    public static function adminHasWildcardTenantDatabaseGrant(PDO $pdo): bool
    {
        $needle = 'ON `'.self::tenantDatabaseGrantPattern().'`';
        $rows = $pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_NUM);

        foreach ($rows as $row) {
            if (str_contains((string) ($row[0] ?? ''), $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function adminCanUseDatabase(PDO $pdo, string $databaseName): bool
    {
        try {
            $pdo->exec('USE '.self::quoteIdentifier($databaseName));

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public static function databaseAccessDeniedMessage(string $databaseName, ?string $mysqlError = null): string
    {
        $user = self::username();
        $pattern = self::tenantDatabaseGrantPattern();
        $message = "Database `{$databaseName}` was created but `{$user}` cannot access it yet.";
        if ($mysqlError !== null && $mysqlError !== '') {
            $message .= ' MySQL: '.$mysqlError;
        }

        return $message.' On RDS (as master user), grant tenant DB privileges, then retry provisioning: '
            ."GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, "
            .'CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER, REFERENCES '
            ."ON `{$pattern}`.* TO `{$user}`@'%'; FLUSH PRIVILEGES;";
    }

    public static function createTenantDatabase(PDO $pdo, string $databaseName): void
    {
        $quoted = self::quoteIdentifier($databaseName);
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    protected static function flushPrivilegesIfAllowed(PDO $pdo): void
    {
        try {
            $pdo->exec('FLUSH PRIVILEGES');
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '1227') || str_contains($e->getMessage(), 'RELOAD')) {
                return;
            }

            throw $e;
        }
    }

    /**
     * mysqldump flags safe for AWS RDS (no FLUSH TABLES / RELOAD privileges).
     *
     * @return list<string>
     */
    public static function mysqldumpFlags(bool $schemaOnly = true): array
    {
        $flags = [
            '--skip-lock-tables',
            '--single-transaction',
            '--no-tablespaces',
            '--set-gtid-purged=OFF',
            '--skip-routines',
            '--skip-triggers',
        ];

        if ($schemaOnly) {
            $flags[] = '--no-data';
        }

        return $flags;
    }

    /**
     * @return list<string>
     */
    public static function mysqldumpCommand(string $database, bool $schemaOnly = true): array
    {
        $command = [
            'mysqldump',
            '-h', self::host(),
            '-P', (string) self::port(),
            '-u', self::username(),
            ...self::mysqldumpFlags($schemaOnly),
            $database,
        ];

        self::assertMysqldumpCommandIsRdsSafe($command);

        return $command;
    }

    /**
     * AWS RDS cannot grant RELOAD / FLUSH_TABLES — mysqldump must use --skip-lock-tables.
     *
     * @param  list<string>  $command
     */
    public static function assertMysqldumpCommandIsRdsSafe(array $command): void
    {
        if (! in_array('--skip-lock-tables', $command, true)) {
            throw new \RuntimeException(
                'mysqldump command is missing --skip-lock-tables (required on AWS RDS). '
                .'Deploy code build '.self::MYSQLDUMP_RDS_BUILD.' and restart Horizon.'
            );
        }
    }

    /**
     * Command string safe to log (no password).
     */
    public static function mysqldumpCommandForLog(string $database, bool $schemaOnly = true): string
    {
        return implode(' ', self::mysqldumpCommand($database, $schemaOnly));
    }

    public static function mysqlCommand(string ...$args): array
    {
        return array_merge(
            [
                'mysql',
                '-h', self::host(),
                '-P', (string) self::port(),
                '-u', self::username(),
            ],
            $args
        );
    }

    /**
     * Remove stderr noise that is not a failure (mysqldump still exits non-zero for real errors).
     */
    public static function stripMysqlCliNoise(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/password on the command line interface can be insecure/i', $line)) {
                continue;
            }
            if (preg_match('/^\[Warning\]/i', $line)) {
                continue;
            }
            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    /**
     * Build a failure message from a failed mysql/mysqldump process (exit code is authoritative).
     */
    public static function cliFailureMessage(
        \Illuminate\Contracts\Process\ProcessResult $result,
        string $tool = 'mysqldump'
    ): string {
        $raw = trim($result->errorOutput()."\n".$result->output());
        $message = self::stripMysqlCliNoise($raw);

        if ($message === '') {
            $message = "{$tool} failed with no output (exit code ".$result->exitCode().'). '
                .'Check TENANT_DB_* credentials, that mysqldump is installed, and Horizon is running the latest code.';
        } else {
            $message = "{$tool} failed (exit code ".$result->exitCode()."): ".$message;
        }

        return self::normalizeMysqldumpError($message);
    }

    public static function normalizeMysqldumpError(string $error): string
    {
        if (str_contains($error, 'FLUSH TABLES')
            || str_contains($error, 'FLUSH_TABLES')
            || str_contains($error, 'RELOAD')
            || str_contains($error, '1227')) {
            return 'Schema clone failed (AWS RDS): mysqldump ran FLUSH TABLES (needs RELOAD — not allowed on RDS). '
                .'Fix: deploy master code build '.self::MYSQLDUMP_RDS_BUILD.' (must include --skip-lock-tables), '
                .'then `php artisan horizon:terminate` and `php artisan tenant:verify-db-clone`. '
                .'If line 537 in TenantProvisionerService still throws the raw mysqldump text, Horizon is on OLD code.';
        }

        return $error;
    }
}
