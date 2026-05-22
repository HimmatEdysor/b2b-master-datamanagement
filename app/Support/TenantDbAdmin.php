<?php

namespace App\Support;

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
        return (string) config('master.tenant_db_host', '127.0.0.1');
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
     * @throws PDOException
     */
    public static function adminPdo(): PDO
    {
        self::assertCanProvision();

        return new PDO(
            self::dsn(),
            self::username(),
            self::password(),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
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
            $hint = self::password() === ''
                ? ' TENANT_DB_PASSWORD is empty.'
                : ' Check the password matches MySQL (Admin → Settings → Tenant database overrides .env).';

            return "Cannot connect to tenant MySQL as `{$user}` at `{$host}`.{$hint}"
                ." User must exist as `{$user}`@'%' (or allow your app server IP in MySQL). "
                .'Fix: run `php artisan tenant:db-admin-check` on the server. '
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
        return (bool) config('master.tenant_db_grant_admin_on_create', true);
    }

    /**
     * Step 2 of provisioning: ensure TENANT_DB_USERNAME can use the new tenant database.
     */
    public static function grantAdminOnTenantDatabase(PDO $pdo, string $databaseName): void
    {
        if (! self::shouldGrantAdminOnCreate()) {
            return;
        }

        $user = str_replace("'", "''", self::username());
        $quotedDb = self::quoteIdentifier($databaseName);

        foreach (self::adminGrantHosts() as $host) {
            $escapedHost = str_replace("'", "''", $host);
            $pdo->exec(
                "GRANT ALL PRIVILEGES ON {$quotedDb}.* TO '{$user}'@'{$escapedHost}'"
            );
        }

        self::flushPrivilegesIfAllowed($pdo);
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
