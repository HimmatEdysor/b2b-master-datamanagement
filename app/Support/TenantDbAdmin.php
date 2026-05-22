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
        return [
            'mysqldump',
            '-h', self::host(),
            '-P', (string) self::port(),
            '-u', self::username(),
            ...self::mysqldumpFlags($schemaOnly),
            $database,
        ];
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
            return 'Schema clone failed (RDS): mysqldump tried FLUSH TABLES, which is not allowed. '
                .'Deploy the latest master code (uses --skip-lock-tables --no-tablespaces), run '
                .'`php artisan horizon:terminate`, then retry. Cannot be fixed with GRANT RELOAD on RDS.';
        }

        return $error;
    }
}
