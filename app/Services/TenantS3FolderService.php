<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantSlug;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantS3FolderService
{
    public function isConfigured(): bool
    {
        $bucket = config('filesystems.disks.s3.bucket');

        return is_string($bucket) && $bucket !== ''
            && config('filesystems.disks.s3.key')
            && config('filesystems.disks.s3.secret');
    }

    /**
     * Create {slug}/.keep in the shared CRM bucket (same name as subdomain slug).
     */
    public function ensureFolderForTenant(Tenant $tenant): ?string
    {
        $folder = $this->folderNameForTenant($tenant);
        if ($folder === null) {
            return null;
        }

        if (! $this->isConfigured()) {
            Log::info('Tenant S3 folder skipped: AWS not configured on master portal', [
                'slug' => $folder,
            ]);

            return $folder;
        }

        $marker = $folder.'/.keep';

        try {
            Storage::disk('s3')->put($marker, '', ['visibility' => 'private']);
        } catch (\Throwable $e) {
            Log::error('Tenant S3 folder create failed', [
                'folder' => $folder,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $folder;
    }

    public function folderNameForTenant(Tenant $tenant): ?string
    {
        $slug = TenantSlug::normalize((string) $tenant->slug);
        if ($slug === '') {
            $slug = Str::slug((string) $tenant->slug);
        }

        return $slug !== '' ? $slug : null;
    }
}
