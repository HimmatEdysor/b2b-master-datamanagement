<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use App\Support\TenantDbAdmin;
use Illuminate\Console\Command;

class TenantCloneDatabaseCommand extends Command
{
    protected $signature = 'tenant:clone-database
        {slug : Tenant slug}
        {--from= : Source database (default: TENANT_TEMPLATE_DATABASE)}
        {--method= : pdo or mysqldump (default: TENANT_DB_CLONE_METHOD)}';

    protected $description = 'Clone MySQL schema only from template DB into tenant database (no row data). Run tenants:seed-reference-data to copy config tables.';

    public function handle(TenantProvisionerService $provisioner): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        TenantDbAdmin::assertCanProvision();

        $from = $this->option('from') ?: config('master.template_database');
        $to = $tenant->database_name;

        if ($this->option('method')) {
            config(['master.tenant_db_clone_method' => strtolower($this->option('method'))]);
        }

        $method = TenantDbAdmin::cloneMethod();

        $this->info("Cloning [{$from}] → [{$to}] (schema only, method: {$method}) …");
        $this->line('Row data: run <info>php artisan tenants:seed-reference-data '.$tenant->slug.'</info> for tables in config/master.php → tenant_seed_tables');

        if ($method === 'mysqldump') {
            $this->warn('mysqldump may fail on AWS RDS. Use TENANT_DB_CLONE_METHOD=pdo.');
        }

        try {
            $provisioner->cloneDatabase($tenant);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $tenant->update(['status' => 'active', 'provision_error' => null]);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
