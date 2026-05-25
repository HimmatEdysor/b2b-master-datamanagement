<?php

namespace App\Services;

use App\Support\TenantDbAdmin;
use PDO;
use PDOException;

/**
 * Verify TENANT_DB_* admin can connect and has privileges to provision tenants on RDS.
 */
class TenantDbAdminCapabilityService
{
    /** Bump when provision-check / grant parsing changes (grep on server to confirm deploy). */
    public const CHECK_BUILD = '2026-05-26-grant-then-use';
    /**
     * @return array{
     *     ok: bool,
     *     summary: string,
     *     checks: list<array{name: string, ok: bool, detail: string}>,
     *     setup_sql: string,
     *     config: array{host: string, port: int, user: string, template: string, password_source: string}
     * }
     */
    public function audit(): array
    {
        $template = (string) config('master.template_database');
        $config = [
            'host' => TenantDbAdmin::host(),
            'port' => TenantDbAdmin::port(),
            'user' => TenantDbAdmin::username(),
            'template' => $template,
            'password_source' => app(MasterSettingsService::class)->tenantDbPasswordSource(),
        ];

        $checks = [];
        $checks[] = $this->checkConfig();

        try {
            $pdo = TenantDbAdmin::adminPdo();
        } catch (PDOException $e) {
            $checks[] = [
                'name' => 'mysql_connect',
                'ok' => false,
                'detail' => TenantDbAdmin::connectionErrorMessage($e),
            ];

            return $this->result(false, $checks, $config);
        }

        $checks[] = [
            'name' => 'mysql_connect',
            'ok' => true,
            'detail' => 'Connected as `'.TenantDbAdmin::username().'` to `'.TenantDbAdmin::host().'`.',
        ];

        $checks = array_merge($checks, $this->checkGrants($pdo));
        $checks[] = $this->checkTemplateDatabase($pdo, $template);
        $checks[] = $this->checkCreateDropDatabase($pdo);

        $ok = collect($checks)->every(fn (array $c) => $c['ok']);

        return $this->result($ok, $checks, $config);
    }

    /**
     * @throws \RuntimeException
     */
    public function assertReadyForProvisioning(): void
    {
        $audit = $this->audit();

        if ($audit['ok']) {
            return;
        }

        $failed = collect($audit['checks'])->first(fn (array $c) => ! $c['ok']);
        $detail = is_array($failed) ? ($failed['detail'] ?? $audit['summary']) : $audit['summary'];

        throw new \RuntimeException(
            'Tenant database admin is not ready: '.$detail
        );
    }

    /**
     * @param  list<array{name: string, ok: bool, detail: string}>  $checks
     * @return array{ok: bool, summary: string, checks: list<array{name: string, ok: bool, detail: string}>, setup_sql: string, config: array{host: string, port: int, user: string, template: string, password_source: string}}
     */
    protected function result(bool $ok, array $checks, array $config): array
    {
        $user = TenantDbAdmin::username();
        $template = $config['template'];

        $shared = TenantDbAdmin::usesSharedTenantCredentials();
        $readySummary = $shared
            ? "Ready: `{$user}` can provision tenant DBs (shared credentials mode; template `{$template}`)."
            : "Ready: `{$user}` can provision tenant databases from template `{$template}`.";

        return [
            'ok' => $ok,
            'summary' => $ok
                ? $readySummary
                : 'Not ready: fix failed checks below, then run `php artisan tenant:db-admin-check` again.',
            'checks' => $checks,
            'setup_sql' => $this->rdsSetupSql($user, $template),
            'config' => $config,
        ];
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    protected function checkConfig(): array
    {
        $user = TenantDbAdmin::username();
        $pass = TenantDbAdmin::password();
        $host = TenantDbAdmin::host();

        if ($user === '') {
            return ['name' => 'config', 'ok' => false, 'detail' => 'TENANT_DB_USERNAME is empty.'];
        }

        if ($pass === '' && ! TenantDbAdmin::isLoopbackHost($host)) {
            return ['name' => 'config', 'ok' => false, 'detail' => 'TENANT_DB_PASSWORD is empty for remote host '.$host.'.'];
        }

        $source = app(MasterSettingsService::class)->tenantDbPasswordSource();
        $sourceLabel = match ($source) {
            'master_settings' => 'password from Admin → Web settings (overrides .env)',
            'env_tenant' => 'password from TENANT_DB_PASSWORD in .env',
            'env_db_fallback' => 'password from DB_PASSWORD (.env fallback — TENANT_DB_PASSWORD empty)',
            default => 'no password configured',
        };

        return [
            'name' => 'config',
            'ok' => true,
            'detail' => "Using admin user `{$user}` @ `{$host}` ({$sourceLabel}).",
        ];
    }

    public function passwordSourceHint(string $source): string
    {
        return match ($source) {
            'master_settings' => 'Update MySQL admin password in Web settings below, or clear the password field and save to use .env again.',
            'env_tenant' => 'Set TENANT_DB_PASSWORD in .env on the app server, then run `php artisan config:clear`.',
            'env_db_fallback' => 'Set TENANT_DB_PASSWORD in .env (recommended) or ensure DB_PASSWORD matches the MySQL user `'.TenantDbAdmin::username().'`.',
            default => 'Set TENANT_DB_PASSWORD in .env or Admin → Web settings.',
        };
    }

    /**
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    protected function checkGrants(PDO $pdo): array
    {
        $required = ['CREATE', 'DROP', 'CREATE USER'];

        $checks = [];
        foreach ($required as $priv) {
            $has = $this->hasGlobalPrivilege($pdo, $priv);
            $checks[] = [
                'name' => 'privilege_'.strtolower(str_replace(' ', '_', $priv)),
                'ok' => $has,
                'detail' => $has
                    ? "Has {$priv} on *.* (from SHOW GRANTS)."
                    : "Missing {$priv} on *.* — not enough to have it only on `".TenantDbAdmin::tenantDatabaseGrantPattern().'`. '
                    .'RDS master must run: GRANT CREATE, DROP, CREATE USER ON *.* TO `'.TenantDbAdmin::username()."`@'%';",
            ];
        }

        return $checks;
    }

    protected function hasGlobalPrivilege(PDO $pdo, string $privilege): bool
    {
        foreach ($this->globalGrantLines($pdo) as $line) {
            if ($this->globalGrantLineHasPrivilege($line, $privilege)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function globalGrantLines(PDO $pdo): array
    {
        return array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $this->showGrantsText($pdo)) ?: [],
            fn (string $line) => stripos($line, 'ON *.*') !== false
        ));
    }

    protected function globalGrantLineHasPrivilege(string $line, string $privilege): bool
    {
        $upper = strtoupper($line);

        if (str_contains($upper, 'ALL PRIVILEGES')) {
            return true;
        }

        return match ($privilege) {
            'CREATE' => (bool) preg_match(
                '/\bCREATE\b(?!\s+TEMPORARY)(?!\s+VIEW)(?!\s+USER)/',
                $upper
            ),
            'DROP' => (bool) preg_match('/\bDROP\b/', $upper),
            'CREATE USER' => str_contains($upper, 'CREATE USER'),
            default => str_contains($upper, strtoupper($privilege)),
        };
    }

    protected function showGrantsText(PDO $pdo): string
    {
        $rows = $pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_NUM);
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = (string) ($row[0] ?? '');
        }

        return implode("\n", $parts);
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    protected function checkTemplateDatabase(PDO $pdo, string $template): array
    {
        if ($template === '') {
            return ['name' => 'template_db', 'ok' => false, 'detail' => 'TENANT_TEMPLATE_DATABASE is not set.'];
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1'
        );
        $stmt->execute([$template]);
        if (! $stmt->fetchColumn()) {
            return [
                'name' => 'template_db',
                'ok' => false,
                'detail' => "Template database [{$template}] not found or not visible.",
            ];
        }

        $stmt = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' LIMIT 1"
        );
        $stmt->execute([$template]);
        $table = $stmt->fetchColumn();
        if (! $table) {
            return [
                'name' => 'template_db',
                'ok' => false,
                'detail' => "Template [{$template}] has no tables.",
            ];
        }

        $qualified = TenantDbAdmin::quoteIdentifier($template).'.'.TenantDbAdmin::quoteIdentifier((string) $table);
        try {
            $pdo->query("SHOW CREATE TABLE {$qualified}")->fetch(PDO::FETCH_ASSOC);

            return [
                'name' => 'template_db',
                'ok' => true,
                'detail' => "Can read schema from template `{$template}` (tested table `{$table}`).",
            ];
        } catch (PDOException $e) {
            return [
                'name' => 'template_db',
                'ok' => false,
                'detail' => "Cannot SHOW CREATE TABLE on {$template}.{$table}: ".$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    protected function checkCreateDropDatabase(PDO $pdo): array
    {
        $testDb = TenantDbAdmin::provisionCheckDatabaseName();
        $quoted = TenantDbAdmin::quoteIdentifier($testDb);
        $wildcard = TenantDbAdmin::tenantDatabaseGrantPattern();

        try {
            $this->dropProvisionCheckDatabaseIfExists($pdo, $testDb);
            $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $grantDetail = '';
            if (TenantDbAdmin::shouldGrantAdminOnCreate()) {
                $grantDetail = $this->tryGrantAdminOnProvisionCheckDatabase($pdo, $testDb);
            }

            $pdo->exec("DROP DATABASE {$quoted}");

            return [
                'name' => 'create_drop_database',
                'ok' => true,
                'detail' => 'Created and dropped test database `'.$testDb.'`.'
                    .($grantDetail !== '' ? ' '.$grantDetail : ''),
            ];
        } catch (PDOException $e) {
            $this->dropProvisionCheckDatabaseIfExists($pdo, $testDb);

            return [
                'name' => 'create_drop_database',
                'ok' => false,
                'detail' => $this->createDropDatabaseFailureDetail($e, $testDb, $wildcard),
            ];
        }
    }

    /**
     * RDS often blocks GRANT without GRANT OPTION — OK when `b2b_tenant_%`.* is pre-granted.
     */
    protected function tryGrantAdminOnProvisionCheckDatabase(PDO $pdo, string $testDb): string
    {
        $granted = TenantDbAdmin::grantAndVerifyAdminAccessToTenantDatabase($pdo, $testDb);

        return $granted
            ? 'Per-database GRANT succeeded and USE verified.'
            : 'Access verified via pre-granted `'.TenantDbAdmin::tenantDatabaseGrantPattern().'` (per-DB GRANT skipped or not allowed on RDS).';
    }

    protected function dropProvisionCheckDatabaseIfExists(PDO $pdo, string $databaseName): void
    {
        $quoted = TenantDbAdmin::quoteIdentifier($databaseName);

        try {
            $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
        } catch (PDOException) {
        }
    }

    protected function createDropDatabaseFailureDetail(
        PDOException $e,
        string $testDb,
        string $wildcard
    ): string {
        $msg = $e->getMessage();
        $hint = 'Run as RDS master: GRANT CREATE, DROP, CREATE USER ON *.* TO `'.TenantDbAdmin::username()."`@'%'; "
            ."GRANT … ON `{$wildcard}`.* TO `".TenantDbAdmin::username()."`@'%'; "
            .'Test databases use prefix `'.TenantDbAdmin::tenantDatabasePrefix().'`.';

        if (str_contains($msg, '1044')) {
            $hint .= ' Error 1044 usually means missing CREATE on *.* or grants only on `'.$wildcard.'` without global CREATE.';
        }

        return 'CREATE/GRANT/DROP test on `'.$testDb.'` failed: '.$msg.' '.$hint;
    }

    public function rdsSetupSql(string $username, string $template): string
    {
        $user = str_replace("'", "''", $username);
        $tpl = str_replace('`', '``', $template);
        $prefix = str_replace('`', '``', (string) config('master.tenant_database_prefix', 'b2b_tenant_'));
        $global = TenantDbAdmin::privilegesSql(TenantDbAdmin::globalProvisionerPrivileges());
        $templatePriv = TenantDbAdmin::privilegesSql(TenantDbAdmin::templateReadPrivileges());
        $dbPriv = TenantDbAdmin::privilegesSql(TenantDbAdmin::databaseProvisionerPrivileges());

        $sharedEnv = 'TENANT_DB_SHARED_CREDENTIALS=true';

        return <<<SQL
-- Run on RDS as the instance MASTER user (mysql client / Workbench).
-- AWS RDS does NOT allow: GRANT ALL PRIVILEGES ON *.*  or  WITH GRANT OPTION
-- Replace YOUR_STRONG_PASSWORD, then set .env TENANT_DB_USERNAME / TENANT_DB_PASSWORD

CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';

GRANT {$global} ON *.* TO '{$user}'@'%';
GRANT {$templatePriv} ON `{$tpl}`.* TO '{$user}'@'%';
GRANT {$dbPriv} ON `{$prefix}%`.* TO '{$user}'@'%';

FLUSH PRIVILEGES;

-- App server .env (RDS — no per-tenant MySQL users):
-- TENANT_DB_GRANT_ADMIN_ON_CREATE=false
-- {$sharedEnv}
-- TENANT_TEMPLATE_DATABASE={$tpl}

-- Verify from app server:
-- php artisan config:clear && php artisan tenant:db-admin-check
SQL;
    }
}
