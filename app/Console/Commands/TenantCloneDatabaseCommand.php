<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TenantCloneDatabaseCommand extends Command
{
    protected $signature = 'tenant:clone-database
        {slug : Tenant slug}
        {--from= : Source database (default: TENANT_TEMPLATE_DATABASE)}
        {--data : Copy data, not schema only}';

    protected $description = 'Clone MySQL schema (and optionally data) from template DB into tenant database.';

    public function handle(): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $from = $this->option('from') ?: config('master.template_database');
        $to = $tenant->database_name;
        $host = config('master.tenant_db_host');
        $port = config('master.tenant_db_port');
        $user = config('master.tenant_db_username');
        $pass = config('master.tenant_db_password');

        $withData = $this->option('data');
        if (! $withData && $this->input->isInteractive()) {
            $withData = $this->confirm(
                "Copy ALL data from [{$from}] into [{$to}]? (No = structure only, then seed reference data separately)",
                false
            );
        }

        $mode = $withData ? 'schema + all data' : 'schema only (no rows)';
        $this->info("Cloning [{$from}] → [{$to}] ({$mode}) …");

        $create = Process::run([
            'mysql',
            '-h', $host,
            '-P', (string) $port,
            '-u', $user,
            ...($pass !== '' && $pass !== null ? ['-p'.$pass] : []),
            '-e', "CREATE DATABASE IF NOT EXISTS `{$to}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);

        if (! $create->successful()) {
            $this->error($create->errorOutput());

            return self::FAILURE;
        }

        $dumpFlags = $withData ? [] : ['--no-data'];
        $dump = Process::run([
            'mysqldump',
            '-h', $host,
            '-P', (string) $port,
            '-u', $user,
            ...($pass !== '' && $pass !== null ? ['-p'.$pass] : []),
            ...$dumpFlags,
            '--skip-routines', '--skip-triggers', '--single-transaction',
            $from,
        ]);

        if (! $dump->successful()) {
            $this->error($dump->errorOutput());

            return self::FAILURE;
        }

        $import = Process::input($dump->output())->run([
            'mysql',
            '-h', $host,
            '-P', (string) $port,
            '-u', $user,
            ...($pass !== '' && $pass !== null ? ['-p'.$pass] : []),
            $to,
        ]);

        if (! $import->successful()) {
            $this->error($import->errorOutput());

            return self::FAILURE;
        }

        $tenant->update(['status' => 'active', 'provision_error' => null]);
        $this->info("Done. Database [{$to}] is ready ({$mode}).");

        return self::SUCCESS;
    }
}
