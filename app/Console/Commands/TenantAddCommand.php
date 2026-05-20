<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\TenantUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TenantAddCommand extends Command
{
    protected $signature = 'tenant:add
        {slug : Subdomain slug, e.g. edysor}
        {--name= : Display name}
        {--database= : MySQL database name}
        {--domain= : Extra custom domain host}
        {--status=active : Tenant status}';

    protected $description = 'Register a company in the master portal (domains + DB config).';

    public function handle(): int
    {
        $slug = Str::lower($this->argument('slug'));
        $name = $this->option('name') ?: Str::title(str_replace('-', ' ', $slug));
        $database = $this->option('database') ?: config('master.tenant_database_prefix').$slug;
        $base = TenantUrl::baseDomain();

        if (Tenant::query()->where('slug', $slug)->exists()) {
            $this->error("Tenant slug [{$slug}] already exists.");

            return self::FAILURE;
        }

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => $this->option('status'),
            'database_name' => $database,
            'database_host' => config('master.tenant_db_host'),
            'database_port' => (int) config('master.tenant_db_port'),
            'database_username' => config('master.tenant_db_username'),
            'database_password' => config('master.tenant_db_password'),
            'brand_name' => $name.' CRM',
            'subscription_status' => 'active',
        ]);

        $subdomain = $slug.'.'.$base;

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'host' => $subdomain,
            'type' => 'subdomain',
            'is_primary' => true,
        ]);

        if ($custom = $this->option('domain')) {
            TenantDomain::create([
                'tenant_id' => $tenant->id,
                'host' => Str::lower($custom),
                'type' => 'custom',
                'is_primary' => false,
            ]);
        }

        $this->info("Tenant [{$name}] created (id: {$tenant->id}).");
        $this->line("  Subdomain: {$subdomain}");
        $this->line("  Database:  {$database}");
        $this->line('  Test API:  '.url("/api/v1/tenant/resolve?host={$subdomain}"));

        return self::SUCCESS;
    }
}
