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

        $failed = collect($audit['checks'])
            ->filter(fn (array $c) => ! $c['ok'])
            ->pluck('detail')
            ->implode(' ');

        throw new \RuntimeException(
            'Tenant database admin is not ready: '.$failed
            .' Run `php artisan tenant:db-admin-check` on the server for details and setup SQL.'
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
            $pdo->exec("DROP DATABASE {$quoted}");

            return [
                'name' => 'create_drop_database',
                'ok' => true,
                'detail' => 'Can CREATE and DROP a test database.',
            ];
        } catch (PDOException $e) {
            return [
                'name' => 'create_drop_database',
                'ok' => false,
                'detail' => 'CREATE/DROP DATABASE test failed: '.$e->getMessage(),
            ];
        }
    }

    public function rdsSetupSql(string $username, string $template): string
    {
        $user = str_replace("'", "''", $username);
        $tpl = str_replace('`', '``', $template);
        $prefix = str_replace('`', '``', (string) config('master.tenant_database_prefix', 'b2b_tenant_'));

        return <<<SQL
-- Run on RDS as the instance MASTER user (mysql client / Workbench).
-- Replace YOUR_STRONG_PASSWORD, then set the same values in .env:
--   TENANT_DB_USERNAME={$username}
--   TENANT_DB_PASSWORD=YOUR_STRONG_PASSWORD
-- User must connect from the app server (e.g. EC2 172.31.x.x) — use '%' host.

CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';

GRANT CREATE, DROP, ALTER, INDEX, CREATE USER, PROCESS ON *.* TO '{$user}'@'%';
GRANT SELECT, SHOW VIEW ON `{$tpl}`.* TO '{$user}'@'%';
GRANT ALL PRIVILEGES ON `{$prefix}%`.* TO '{$user}'@'%';

FLUSH PRIVILEGES;

-- Verify (from app server):
-- mysql -h YOUR_RDS_HOST -u {$username} -p -e "SHOW GRANTS FOR CURRENT_USER()"
SQL;
    }
}
