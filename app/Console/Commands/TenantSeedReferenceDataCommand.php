<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantSeedDataService;
use Illuminate\Console\Command;

class TenantSeedReferenceDataCommand extends Command
{
    protected $signature = 'tenants:seed-reference-data {slug? : Tenant slug (all active tenants if omitted)}';

    protected $description = 'Copy reference data and admin color theme (web_settings only) from template DB';

    public function handle(TenantSeedDataService $seeder): int
    {
        $slug = $this->argument('slug');

        $query = Tenant::query()->whereIn('status', ['active', 'provisioning', 'suspended']);

        if ($slug) {
            $query->where('slug', $slug);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenant(s).');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            if (! $tenant->database_name) {
                $this->warn("Skipping [{$tenant->slug}]: no database_name.");

                continue;
            }

            $seeder->seedFromTemplate($tenant);
            $this->info("Seeded reference data for [{$tenant->slug}] → {$tenant->database_name}");
        }

        return self::SUCCESS;
    }
}
