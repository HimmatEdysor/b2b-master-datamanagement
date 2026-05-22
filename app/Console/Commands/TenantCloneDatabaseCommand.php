<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\TenantDbAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TenantCloneDatabaseCommand extends Command
{
    protected $signature = 'tenant:clone-database
        {slug : Tenant slug}
        {--from= : Source database (default: TENANT_TEMPLATE_DATABASE)}';

    protected $description = 'Clone MySQL schema only from template DB into tenant database (no row data). Run tenants:seed-reference-data to copy config tables.';

    public function handle(): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        TenantDbAdmin::assertCanProvision();

        $from = $this->option('from') ?: config('master.template_database');
        $to = $tenant->database_name;
        $host = TenantDbAdmin::host();
        $port = TenantDbAdmin::port();
        $user = TenantDbAdmin::username();
        $mysqlEnv = TenantDbAdmin::mysqlCliEnv();

        $timeout = (float) config('master.tenant_db_clone_timeout', 600);
        $this->info("Cloning [{$from}] → [{$to}] (schema only, timeout {$timeout}s) …");
        $this->line('Row data: run <info>php artisan tenants:seed-reference-data '.$tenant->slug.'</info> for tables in config/master.php → tenant_seed_tables');

        $create = Process::timeout($timeout)
            ->env($mysqlEnv)
            ->run([
                ...TenantDbAdmin::mysqlCommand('-e', "CREATE DATABASE IF NOT EXISTS `{$to}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"),
            ]);

        if (! $create->successful()) {
            $this->error($create->errorOutput());

            return self::FAILURE;
        }

        $dump = Process::timeout($timeout)
            ->env($mysqlEnv)
            ->run(TenantDbAdmin::mysqldumpCommand($from, schemaOnly: true));

        if (! $dump->successful()) {
            $this->error(TenantDbAdmin::normalizeMysqldumpError(
                trim($dump->errorOutput() ?: $dump->output())
            ));

            return self::FAILURE;
        }

        $import = Process::timeout($timeout)
            ->env($mysqlEnv)
            ->input($dump->output())
            ->run(TenantDbAdmin::mysqlCommand($to));

        if (! $import->successful()) {
            $this->error($import->errorOutput());

            return self::FAILURE;
        }

        $tenant->update(['status' => 'active', 'provision_error' => null]);
        $this->info("Done. Database [{$to}] has schema only.");

        return self::SUCCESS;
    }
}
