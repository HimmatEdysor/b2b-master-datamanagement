<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\TenantUrl;
use Illuminate\Support\Str;

class TenantResolverService
{
    public function resolveByHost(string $host): ?Tenant
    {
        $host = $this->normalizeHost($host);

        if ($host === '') {
            return null;
        }

        $domain = TenantDomain::query()
            ->where('host', $host)
            ->with(['tenant.subscriptionPlan'])
            ->first();

        if ($domain?->tenant) {
            return $domain->tenant;
        }

        return $this->resolveBySubdomainSlug($host);
    }

    protected function resolveBySubdomainSlug(string $host): ?Tenant
    {
        $base = $this->normalizeHost((string) config('master.tenant_base_domain'));

        if ($base === '' || ! Str::endsWith($host, '.'.$base)) {
            return null;
        }

        $slug = Str::before($host, '.'.$base);

        if ($slug === '' || str_contains($slug, '.')) {
            return null;
        }

        return Tenant::query()
            ->where('slug', $slug)
            ->with('subscriptionPlan')
            ->first();
    }

    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;

        return rtrim($host, '.');
    }

    public function forgetHostCache(Tenant $tenant): void
    {
        $tenant->loadMissing('domains');
        foreach ($tenant->domains as $domain) {
            cache()->forget('tenant:host:'.$this->normalizeHost($domain->host));
        }
        cache()->forget('tenant:host:'.$this->normalizeHost($tenant->slug.'.'.config('master.tenant_base_domain')));
    }

    public function toApiPayload(Tenant $tenant): array
    {
        $plan = $tenant->subscriptionPlan;

        return [
            'tenant_id' => $tenant->id,
            'database_id' => $tenant->id,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'status' => $tenant->status,
            'database' => [
                'driver' => 'mysql',
                'host' => $tenant->databaseHost(),
                'port' => (int) ($tenant->database_port ?: config('master.tenant_db_port')),
                'database' => $tenant->database_name,
                'username' => $tenant->databaseUsername(),
                'password' => $tenant->databasePassword(),
            ],
            'branding' => [
                'app_name' => $tenant->brand_name ?: $tenant->name,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'primary_color' => $tenant->primary_color,
                'support_email' => $tenant->support_email,
            ],
            'subscription' => [
                'plan_id' => $tenant->subscription_plan_id,
                'plan_name' => $plan?->name,
                'plan_slug' => $plan?->slug,
                'status' => $tenant->subscription_status,
                'expires_at' => $tenant->subscription_expires_at?->toIso8601String(),
            ],
            'domains' => $tenant->relationLoaded('domains')
                ? $tenant->domains->pluck('host')->values()->all()
                : [],
            'domains_detail' => $tenant->relationLoaded('domains')
                ? $tenant->domains->map(function ($domain) use ($tenant) {
                    $host = TenantUrl::normalizeHostForEnvironment($domain->host, $tenant->slug);

                    return [
                        'host' => $host,
                        'type' => $domain->type,
                        'is_primary' => (bool) $domain->is_primary,
                        'url' => TenantUrl::urlForHost($host),
                    ];
                })->values()->all()
                : [],
            'primary_url' => TenantUrl::urlForTenant($tenant),
            'default_platform_url' => TenantUrl::urlForHost(TenantUrl::baseDomain()),
            'is_platform_default' => $tenant->slug === config('master.platform_default_slug', 'guaranteeadmit'),
            'migration' => [
                'status' => $tenant->migration_status,
                'last_at' => $tenant->last_migration_at?->toIso8601String(),
                'error' => $tenant->migration_error,
            ],
            'storage' => [
                's3_folder' => $tenant->slug,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toMigrationQueueItem(Tenant $tenant): array
    {
        $payload = $this->toApiPayload($tenant);
        $host = TenantUrl::hostForTenant($tenant);

        return array_merge($payload, [
            'primary_host' => $host,
            'primary_url' => TenantUrl::urlForHost($host),
            'migration_status' => $tenant->migration_status,
            'last_migration_at' => $tenant->last_migration_at?->toIso8601String(),
            'migration_error' => $tenant->migration_error,
        ]);
    }
}
