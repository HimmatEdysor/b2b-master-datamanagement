<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantS3FolderService;
use Illuminate\Console\Command;

class EnsureTenantS3FolderCommand extends Command
{
    protected $signature = 'tenants:ensure-s3-folder {slug? : Company slug (all active if omitted)}';

    protected $description = 'Create S3 prefix {slug}/.keep in the shared CRM bucket';

    public function handle(TenantS3FolderService $s3): int
    {
        if (! $s3->isConfigured()) {
            $this->error('Configure AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_BUCKET in master-portal .env');

            return self::FAILURE;
        }

        $query = Tenant::query()->whereIn('status', ['active', 'provisioning', 'suspended']);

        if ($slug = $this->argument('slug')) {
            $query->where('slug', $slug);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->error('No matching company found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $folder = $s3->ensureFolderForTenant($tenant);
            $this->info("S3 folder: {$folder}/ (bucket: ".config('filesystems.disks.s3.bucket').')');
        }

        return self::SUCCESS;
    }
}
