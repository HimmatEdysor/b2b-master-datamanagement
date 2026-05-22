<?php

namespace App\Support;

/**
 * MySQL admin credentials for provisioning (CREATE DATABASE, mysqldump, CREATE USER).
 * Values come from config/master.php (TENANT_DB_* with DB_* fallback).
 *
 * Per-tenant CRM credentials are stored on tenants.database_* after approval (password encrypted).
 */
class TenantDbAdmin
{
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
     * @return list<string>
     */
    public static function mysqlPasswordArgs(): array
    {
        $pass = self::password();

        return $pass !== '' ? ['-p'.$pass] : [];
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

    /**
     * mysqldump flags safe for AWS RDS (no FLUSH TABLES / RELOAD privileges).
     *
     * @return list<string>
     */
    public static function mysqldumpFlags(bool $schemaOnly = true): array
    {
        $flags = [
            '--single-transaction',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--skip-routines',
            '--skip-triggers',
        ];

        if ($schemaOnly) {
            $flags[] = '--no-data';
        }

        return $flags;
    }

    public static function normalizeMysqldumpError(string $error): string
    {
        if (str_contains($error, 'FLUSH TABLES')
            || str_contains($error, 'FLUSH_TABLES')
            || str_contains($error, 'RELOAD')
            || str_contains($error, '1227')) {
            return 'Schema clone failed: RDS does not allow FLUSH TABLES. '
                .'The app now uses --skip-lock-tables; restart Horizon (php artisan horizon:terminate) and retry provisioning.';
        }

        return $error;
    }
}
