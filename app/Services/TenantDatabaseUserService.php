<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantDbAdmin;
use Illuminate\Support\Str;

class TenantDatabaseUserService
{
    public function __construct(
        protected MasterActivityLogService $activityLog,
    ) {}

    /** MySQL username length limit (user@host). */
    public const USERNAME_MAX_LENGTH = 32;

    /**
     * Create a dedicated MySQL user for the tenant database and store credentials on the tenant.
     *
     * @return array{username: string, password: string}
     */
    public function provisionForTenant(Tenant $tenant): array
    {
        $databaseName = $tenant->database_name;

        if ($databaseName === null || $databaseName === '') {
            throw new \InvalidArgumentException('Tenant has no database name.');
        }

        if (TenantDbAdmin::usesSharedTenantCredentials()) {
            return $this->assignSharedAdminCredentials($tenant, $databaseName);
        }

        $username = $this->deriveUsername($databaseName);
        $password = Str::password(24, letters: true, numbers: true, symbols: false);

        $this->runAdminSql($this->buildProvisionSql($username, $password, $databaseName));

        $tenant->update([
            'database_username' => $username,
            'database_password' => $password,
        ]);

        $this->activityLog->database(
            'provision_db_user',
            'ok',
            "Dedicated MySQL user created for database {$databaseName}",
            $tenant,
            null,
            ['username' => $username, 'database' => $databaseName]
        );

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * @return array{username: string, password: string}
     */
    protected function assignSharedAdminCredentials(Tenant $tenant, string $databaseName): array
    {
        $username = TenantDbAdmin::username();
        $password = TenantDbAdmin::password();

        // Local XAMPP: root may have empty password; allow only on loopback/socket.
        $passwordRequired = ! (TenantDbAdmin::socket() !== '' || TenantDbAdmin::isLoopbackHost(TenantDbAdmin::host()));

        if ($username === '' || ($passwordRequired && $password === '')) {
            throw new \RuntimeException(
                'TENANT_DB_SHARED_CREDENTIALS is enabled but TENANT_DB_USERNAME or TENANT_DB_PASSWORD is empty.'
            );
        }

        $tenant->update([
            'database_username' => $username,
            'database_password' => $password,
        ]);

        $this->activityLog->database(
            'provision_db_user',
            'ok',
            "Using shared MySQL user `{$username}` for database {$databaseName} (RDS wildcard `"
                .TenantDbAdmin::tenantDatabaseGrantPattern().'`, no per-tenant GRANT)',
            $tenant,
            null,
            ['username' => $username, 'database' => $databaseName, 'shared' => true]
        );

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Change password for an existing dedicated MySQL user (ALTER USER) and store on tenant.
     *
     * @return array{username: string, password: string}
     */
    public function updatePasswordForTenant(Tenant $tenant, string $password): array
    {
        $databaseName = $tenant->database_name;

        if ($databaseName === null || $databaseName === '') {
            throw new \InvalidArgumentException('Tenant has no database name.');
        }

        $username = $tenant->database_username ?: $this->deriveUsername($databaseName);

        if (TenantDbAdmin::usesSharedTenantCredentials()) {
            $username = TenantDbAdmin::username();
            $password = TenantDbAdmin::password() !== '' ? TenantDbAdmin::password() : $password;
        } else {
            $this->runAdminSql($this->buildPasswordUpdateSql($username, $password));
        }

        $tenant->update([
            'database_username' => $username,
            'database_password' => $password,
        ]);

        $this->activityLog->database(
            'update_db_password',
            'ok',
            "MySQL password updated for user {$username}",
            $tenant,
            null,
            ['username' => $username, 'database' => $databaseName]
        );

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * @return list<string>
     */
    public function buildPasswordUpdateSql(string $username, string $password): array
    {
        $escapedUser = $this->escapeSqlString($username);
        $escapedPass = $this->escapeSqlString($password);
        $statements = [];

        foreach ($this->userHosts() as $host) {
            $escapedHost = $this->escapeSqlString($host);
            $statements[] = "ALTER USER '{$escapedUser}'@'{$escapedHost}' IDENTIFIED BY '{$escapedPass}'";
        }

        $statements[] = 'FLUSH PRIVILEGES';

        return $statements;
    }

    public function deriveUsername(string $databaseName): string
    {
        return Str::limit($databaseName, self::USERNAME_MAX_LENGTH, '');
    }

    /**
     * @return list<string>
     */
    public function buildProvisionSql(string $username, string $password, string $databaseName): array
    {
        $escapedUser = $this->escapeSqlString($username);
        $escapedPass = $this->escapeSqlString($password);
        $statements = [];

        foreach ($this->userHosts() as $host) {
            $escapedHost = $this->escapeSqlString($host);
            // Drop + create so re-provision after a failed run resets password (MariaDB/MySQL).
            $statements[] = "DROP USER IF EXISTS '{$escapedUser}'@'{$escapedHost}'";
            $statements[] = "CREATE USER '{$escapedUser}'@'{$escapedHost}' IDENTIFIED BY '{$escapedPass}'";
            $statements[] = TenantDbAdmin::buildGrantOnDatabaseSql(
                $databaseName,
                $username,
                $host,
                TenantDbAdmin::tenantAppPrivileges()
            );
        }

        $statements[] = 'FLUSH PRIVILEGES';

        return $statements;
    }

    /**
     * @param  list<string>  $statements
     */
    public function statementsToSqlBatch(array $statements): string
    {
        return implode(";\n", $statements).';';
    }

    /**
     * @return list<string>
     */
    protected function userHosts(): array
    {
        $hosts = config('master.tenant_db_user_hosts', ['%']);

        return array_values(array_unique(array_filter(
            is_array($hosts) ? $hosts : [$hosts],
            fn ($h) => is_string($h) && $h !== ''
        )));
    }

    /**
     * Run DDL via PDO (one statement at a time). Avoids mysql CLI batching / MariaDB syntax issues.
     *
     * @param  list<string>  $statements
     */
    protected function runAdminSql(array $statements): void
    {
        $pdo = TenantDbAdmin::adminPdo();

        foreach ($statements as $sql) {
            $sql = rtrim(trim($sql), ';').';';

            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                throw new \RuntimeException("MySQL failed on:\n{$sql}\n\n".$e->getMessage(), 0, $e);
            }
        }
    }

    protected function escapeSqlString(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "''"], $value);
    }

    protected function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
