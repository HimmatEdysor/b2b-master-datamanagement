<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use Illuminate\Console\Command;

class SyncTenantDomainsCommand extends Command
{
    protected $signature = 'tenants:sync-domains {--tenant= : Tenant ID to sync only}';

    protected $description = 'Update subdomain host records to match TENANT_BASE_DOMAIN (e.g. slug.localhost locally)';

    public function handle(TenantProvisionerService $provisioner): int
    {
        $query = Tenant::query()->orderBy('id');

        if ($id = $this->option('tenant')) {
            $query->whereKey($id);
        }

        $count = 0;

        $query->each(function (Tenant $tenant) use ($provisioner, &$count) {
            $provisioner->ensureDomains($tenant);
            $count++;
        });

        $this->info("Synced subdomain hosts for {$count} tenant(s).");

        return self::SUCCESS;
    }
}
