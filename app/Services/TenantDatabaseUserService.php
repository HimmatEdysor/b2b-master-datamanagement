<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class TenantDatabaseUserService
{
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

        $username = $this->deriveUsername($databaseName);
        $password = Str::password(24, letters: true, numbers: true, symbols: false);

        $this->runAdminSql($this->buildProvisionSql($username, $password, $databaseName));

        $tenant->update([
            'database_username' => $username,
            'database_password' => $password,
        ]);

        return [
            'username' => $username,
            'password' => $password,
        ];
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
        $quotedDb = $this->quoteIdentifier($databaseName);
        $statements = [];

        foreach ($this->userHosts() as $host) {
            $escapedHost = $this->escapeSqlString($host);
            $statements[] = "CREATE USER IF NOT EXISTS '{$escapedUser}'@'{$escapedHost}' IDENTIFIED BY '{$escapedPass}'";
            $statements[] = "ALTER USER '{$escapedUser}'@'{$escapedHost}' IDENTIFIED BY '{$escapedPass}'";
            $statements[] = "GRANT ALL PRIVILEGES ON {$quotedDb}.* TO '{$escapedUser}'@'{$escapedHost}'";
        }

        $statements[] = 'FLUSH PRIVILEGES';

        return $statements;
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
     * @param  list<string>  $statements
     */
    protected function runAdminSql(array $statements): void
    {
        $host = config('master.tenant_db_host');
        $port = config('master.tenant_db_port');
        $user = config('master.tenant_db_username');
        $pass = config('master.tenant_db_password');
        $passArgs = ($pass !== '' && $pass !== null) ? ['-p'.$pass] : [];

        $result = Process::run([
            'mysql',
            '-h', $host,
            '-P', (string) $port,
            '-u', $user,
            ...$passArgs,
            '-e', implode("\n", $statements),
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException(trim($result->errorOutput() ?: $result->output()));
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
