<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantOperationLog;
use App\Models\User;
use App\Support\TenantUrl;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class TenantProvisionerService
{
    public function __construct(
        protected TenantResolverService $resolver,
        protected TenantSeedDataService $seedDataService,
        protected TenantDefaultUserService $defaultUserService,
        protected TenantS3FolderService $s3FolderService,
        protected TenantDatabaseUserService $databaseUserService,
        protected MasterActivityLogService $activityLog,
    ) {}

    /**
     * @return array{username: string, password: string}|null DB credentials when provision succeeds
     */
    public function approve(Tenant $tenant, ?User $by = null, bool $withData = false): ?array
    {
        if (! in_array($tenant->status, ['pending', 'failed'], true)) {
            throw new \InvalidArgumentException('Only pending or failed companies can be approved.');
        }

        $tenant->update([
            'status' => 'provisioning',
            'approved_at' => now(),
            'approved_by' => $by?->id,
            'rejected_at' => null,
            'provision_error' => null,
        ]);

        $this->log($tenant, 'approve', 'ok', 'Approval started', $by);

        try {
            $this->ensureDomains($tenant);
            $s3Folder = $this->s3FolderService->ensureFolderForTenant($tenant);
            $this->cloneDatabase($tenant, $withData);
            $dbCredentials = $this->databaseUserService->provisionForTenant($tenant->fresh());
            if (! $withData) {
                $this->seedDataService->seedFromTemplate($tenant);
                $this->activityLog->database(
                    'seed_reference',
                    'ok',
                    'Reference data seeded from template',
                    $tenant,
                    $by
                );
            }
            $this->defaultUserService->provisionDefaultAdmin($tenant);

            $tenant->update([
                'status' => 'active',
                'provision_error' => null,
                'subscription_status' => $tenant->subscription_status ?: 'active',
            ]);

            $this->resolver->forgetHostCache($tenant);
            $this->log(
                $tenant,
                'provision',
                'ok',
                ($withData ? 'Full DB copy' : 'Schema clone + reference seed')
                    .', dedicated MySQL user ('.$dbCredentials['username'].'), S3 folder ('.($s3Folder ?? $tenant->slug).'), domains, and default CRM admin ready',
                $by
            );

            return $dbCredentials;
        } catch (\Throwable $e) {
            $tenant->update([
                'status' => 'failed',
                'provision_error' => $e->getMessage(),
            ]);
            $this->log($tenant, 'provision', 'failed', $e->getMessage(), $by);
            throw $e;
        }
    }

    public function reject(Tenant $tenant, ?User $by = null, ?string $reason = null): void
    {
        if (! in_array($tenant->status, ['pending', 'failed'], true)) {
            throw new \InvalidArgumentException('Only pending or failed companies can be rejected.');
        }

        $tenant->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'registration_notes' => trim(($tenant->registration_notes ?? '')."\nRejected: ".($reason ?? '')),
        ]);

        $this->log($tenant, 'reject', 'ok', $reason ?? 'Registration rejected', $by);
    }

    public function ensureDomains(Tenant $tenant): void
    {
        $subdomain = TenantUrl::subdomainHost($tenant->slug);

        $atHost = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('host', $subdomain)
            ->first();

        if ($atHost) {
            $atHost->update([
                'type' => 'subdomain',
                'is_primary' => true,
            ]);
            $this->activityLog->domain('ensure_subdomain', 'ok', "Primary subdomain set to {$subdomain}", $tenant);
        } elseif (TenantDomain::query()->where('host', $subdomain)->exists()) {
            return;
        } else {
            TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'host' => $subdomain,
                'type' => 'subdomain',
                'is_primary' => true,
            ]);
            $this->activityLog->domain('ensure_subdomain', 'ok', "Created primary subdomain {$subdomain}", $tenant);
        }

        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'subdomain')
            ->where('host', '!=', $subdomain)
            ->each(function (TenantDomain $domain) use ($tenant) {
                cache()->forget('tenant:host:'.$domain->host);
                $this->activityLog->domain('remove_stale_subdomain', 'ok', "Removed stale subdomain {$domain->host}", $tenant);
                $domain->delete();
            });
    }

    public function cloneDatabase(Tenant $tenant, bool $withData = false): void
    {
        $from = config('master.template_database');
        $to = $tenant->database_name;
        $host = config('master.tenant_db_host');
        $port = config('master.tenant_db_port');
        $user = config('master.tenant_db_username');
        $pass = config('master.tenant_db_password');
        $passArgs = ($pass !== '' && $pass !== null) ? ['-p'.$pass] : [];

        $create = Process::run([
            'mysql', '-h', $host, '-P', (string) $port, '-u', $user, ...$passArgs,
            '-e', "CREATE DATABASE IF NOT EXISTS `{$to}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);

        if (! $create->successful()) {
            throw new \RuntimeException($create->errorOutput());
        }

        $dumpFlags = $withData ? [] : ['--no-data'];
        $dump = Process::run([
            'mysqldump', '-h', $host, '-P', (string) $port, '-u', $user, ...$passArgs,
            ...$dumpFlags, '--skip-routines', '--skip-triggers', '--single-transaction', $from,
        ]);

        if (! $dump->successful()) {
            throw new \RuntimeException($dump->errorOutput());
        }

        $import = Process::input($dump->output())->run([
            'mysql', '-h', $host, '-P', (string) $port, '-u', $user, ...$passArgs, $to,
        ]);

        if (! $import->successful()) {
            $this->activityLog->database(
                'clone_database',
                'failed',
                trim($import->errorOutput() ?: 'Import failed'),
                $tenant,
                null,
                ['from' => $from, 'to' => $to, 'with_data' => $withData]
            );
            throw new \RuntimeException($import->errorOutput());
        }

        $this->activityLog->database(
            'clone_database',
            'ok',
            $withData
                ? "Cloned schema and data from {$from} to {$to}"
                : "Cloned schema from {$from} to {$to}",
            $tenant,
            null,
            ['from' => $from, 'to' => $to, 'with_data' => $withData]
        );
    }

    public function reserveDatabaseName(string $slug): string
    {
        return config('master.tenant_database_prefix').Str::slug($slug, '');
    }

    protected function log(Tenant $tenant, string $action, string $status, ?string $message, ?User $by): void
    {
        TenantOperationLog::create([
            'tenant_id' => $tenant->id,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'user_id' => $by?->id,
        ]);

        $this->activityLog->database(
            $action,
            $status,
            $message ?? '',
            $tenant,
            $by
        );
    }
}
