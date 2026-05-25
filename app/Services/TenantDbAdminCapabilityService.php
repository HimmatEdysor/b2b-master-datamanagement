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
    /**
     * @return array{
     *     ok: bool,
     *     summary: string,
     *     checks: list<array{name: string, ok: bool, detail: string}>,
     *     setup_sql: string,
     *     config: array{host: string, port: int, user: string, template: string}
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

        if (TenantDbAdmin::shouldGrantAdminOnCreate()) {
            $checks[] = $this->checkGrantOnNewDatabase($pdo);
        }

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
     * @return array{ok: bool, summary: string, checks: list<array{name: string, ok: bool, detail: string}>, setup_sql: string, config: array{host: string, port: int, user: string, template: string}}
     */
    protected function result(bool $ok, array $checks, array $config): array
    {
        $user = TenantDbAdmin::username();
        $template = $config['template'];

        return [
            'ok' => $ok,
            'summary' => $ok
                ? "Ready: `{$user}` can provision tenant databases from template `{$template}`."
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

        return [
            'name' => 'config',
            'ok' => true,
            'detail' => "Using admin user `{$user}` @ `{$host}` (from .env or Admin → Settings).",
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    protected function checkGrants(PDO $pdo): array
    {
        $required = ['CREATE', 'DROP', 'CREATE USER'];

        $grantText = $this->showGrantsText($pdo);
        $global = strtoupper($grantText);

        $checks = [];
        foreach ($required as $priv) {
            $has = str_contains($global, $priv)
                || (str_contains($global, 'ALL PRIVILEGES') && str_contains($global, 'ON *.*'));
            $checks[] = [
                'name' => 'privilege_'.strtolower(str_replace(' ', '_', $priv)),
                'ok' => $has,
                'detail' => $has
                    ? "Has {$priv} (from SHOW GRANTS)."
                    : "Missing {$priv} on *.* — required to create/drop databases and tenant users.",
            ];
        }

        return $checks;
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
        $testDb = '__master_provision_check_'.bin2hex(random_bytes(4));
        $quoted = TenantDbAdmin::quoteIdentifier($testDb);

        try {
            $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            if (TenantDbAdmin::shouldGrantAdminOnCreate()) {
                TenantDbAdmin::grantAdminOnTenantDatabase($pdo, $testDb);
            }

            $pdo->exec("DROP DATABASE {$quoted}");

            return [
                'name' => 'create_drop_database',
                'ok' => true,
                'detail' => TenantDbAdmin::shouldGrantAdminOnCreate()
                    ? 'Can CREATE DATABASE, GRANT admin on it, and DROP.'
                    : 'Can CREATE and DROP a test database.',
            ];
        } catch (PDOException $e) {
            return [
                'name' => 'create_drop_database',
                'ok' => false,
                'detail' => 'CREATE/GRANT/DROP DATABASE test failed: '.$e->getMessage()
                    .' RDS master must grant CREATE,DROP,CREATE USER on *.* and database privileges on b2b_tenant_% (see tenant:db-admin-check).',
            ];
        }
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    protected function checkGrantOnNewDatabase(PDO $pdo): array
    {
        $testDb = '__master_grant_check_'.bin2hex(random_bytes(4));
        $quoted = TenantDbAdmin::quoteIdentifier($testDb);

        try {
            $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            TenantDbAdmin::grantAdminOnTenantDatabase($pdo, $testDb);
            $pdo->exec("DROP DATABASE {$quoted}");

            return [
                'name' => 'grant_admin_on_tenant_db',
                'ok' => true,
                'detail' => 'Can GRANT `'.TenantDbAdmin::username().'` on a new tenant database (or already has global access).',
            ];
        } catch (\PDOException $e) {
            try {
                $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
            } catch (\PDOException) {
            }

            return [
                'name' => 'grant_admin_on_tenant_db',
                'ok' => false,
                'detail' => 'GRANT on new tenant DB failed: '.$e->getMessage()
                    .' Pre-grant `b2b_tenant_%`.* with specific privileges, or set TENANT_DB_GRANT_ADMIN_ON_CREATE=false.',
            ];
        }
    }

    public function rdsSetupSql(string $username, string $template): string
    {
        $user = str_replace("'", "''", $username);
        $tpl = str_replace('`', '``', $template);
        $prefix = str_replace('`', '``', (string) config('master.tenant_database_prefix', 'b2b_tenant_'));
        $global = TenantDbAdmin::privilegesSql(TenantDbAdmin::globalProvisionerPrivileges());
        $templatePriv = TenantDbAdmin::privilegesSql(TenantDbAdmin::templateReadPrivileges());
        $dbPriv = TenantDbAdmin::privilegesSql(TenantDbAdmin::databaseProvisionerPrivileges());

        return <<<SQL
-- Run on RDS as the instance MASTER user (mysql client / Workbench).
-- AWS RDS does NOT allow: GRANT ALL PRIVILEGES ON *.*  or  WITH GRANT OPTION
-- Replace YOUR_STRONG_PASSWORD, then set .env TENANT_DB_USERNAME / TENANT_DB_PASSWORD

CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';

GRANT {$global} ON *.* TO '{$user}'@'%';
GRANT {$templatePriv} ON `{$tpl}`.* TO '{$user}'@'%';
GRANT {$dbPriv} ON `{$prefix}%`.* TO '{$user}'@'%';

FLUSH PRIVILEGES;

-- Verify from app server:
-- mysql -h YOUR_RDS_HOST -u {$username} -p -e "SHOW GRANTS FOR CURRENT_USER()"
-- php artisan tenant:db-admin-check
SQL;
    }
}
