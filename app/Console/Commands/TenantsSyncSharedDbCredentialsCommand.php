<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\TenantDbAdmin;
use Illuminate\Console\Command;

class TenantsSyncSharedDbCredentialsCommand extends Command
{
    protected $signature = 'tenants:sync-shared-db-credentials';

    protected $description = 'Set database_username/password on all companies to TENANT_DB_USERNAME (RDS shared b2b_master mode)';

    public function handle(): int
    {
        if (! TenantDbAdmin::usesSharedTenantCredentials()) {
            $this->error('Enable TENANT_DB_SHARED_CREDENTIALS=true (or Web settings → Use shared MySQL user) first.');

            return self::FAILURE;
        }

        TenantDbAdmin::assertCanProvision();

        $user = TenantDbAdmin::username();
        $pass = TenantDbAdmin::password();
        $updated = 0;

        $query = Tenant::query()
            ->whereNotNull('database_name')
            ->where('database_name', '!=', '');

        foreach ($query->cursor() as $tenant) {
            $tenant->update([
                'database_username' => $user,
                'database_password' => $pass,
                'database_host' => $tenant->database_host ?: TenantDbAdmin::host(),
                'database_port' => $tenant->database_port ?: TenantDbAdmin::port(),
            ]);
            $updated++;
            $this->line("  {$tenant->slug} → `{$tenant->database_name}` as `{$user}`");
        }

        $this->info("Updated {$updated} companies to use shared MySQL user `{$user}`.");

        return self::SUCCESS;
    }
}
