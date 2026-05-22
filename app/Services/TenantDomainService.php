<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\TenantDomainHost;
use App\Support\TenantSlug;
use App\Support\TenantUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantDomainService
{
    public function __construct(
        protected TenantResolverService $resolver,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForTenant(Tenant $tenant): array
    {
        $tenant->loadMissing('domains');

        return $tenant->domains
            ->sortBy([
                fn (TenantDomain $a, TenantDomain $b) => ($b->is_primary <=> $a->is_primary) ?: strcmp($a->host, $b->host),
            ])
            ->values()
            ->map(fn (TenantDomain $domain) => $this->toListItem($domain, $tenant))
            ->all();
    }

    public function addCustomDomain(Tenant $tenant, string $host): TenantDomain
    {
        $host = TenantDomainHost::normalize($host);

        $this->assertHostAvailable($host, $tenant);

        return DB::transaction(function () use ($tenant, $host) {
            $domain = TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'host' => $host,
                'type' => 'custom',
                'is_primary' => false,
            ]);

            $this->resolver->forgetHostCache($tenant->fresh(['domains']));

            return $domain;
        });
    }

    public function addSubdomainAlias(Tenant $tenant, string $aliasSlug): TenantDomain
    {
        $aliasSlug = TenantSlug::normalize($aliasSlug);

        if ($aliasSlug === '') {
            throw ValidationException::withMessages([
                'alias' => ['Subdomain alias is required.'],
            ]);
        }

        if (! TenantSlug::isValid($aliasSlug)) {
            throw ValidationException::withMessages([
                'alias' => ['Subdomain can only contain lowercase letters, numbers, and hyphens — no spaces.'],
            ]);
        }

        $host = TenantUrl::subdomainHost($aliasSlug);
        $canonical = TenantUrl::subdomainHost($tenant->slug);

        if ($host === $canonical) {
            throw ValidationException::withMessages([
                'alias' => ['This subdomain is already the company’s primary CRM host.'],
            ]);
        }

        if (Tenant::query()->where('slug', $aliasSlug)->where('id', '!=', $tenant->id)->exists()) {
            throw ValidationException::withMessages([
                'alias' => ['This subdomain slug is already used by another company.'],
            ]);
        }

        $this->assertHostAvailable($host, $tenant);

        return DB::transaction(function () use ($tenant, $host) {
            $domain = TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'host' => $host,
                'type' => 'subdomain',
                'is_primary' => false,
            ]);

            $this->resolver->forgetHostCache($tenant->fresh(['domains']));

            return $domain;
        });
    }

    public function setPrimary(Tenant $tenant, TenantDomain $domain): TenantDomain
    {
        if ($domain->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'domain' => ['Domain does not belong to this company.'],
            ]);
        }

        DB::transaction(function () use ($tenant, $domain) {
            TenantDomain::query()
                ->where('tenant_id', $tenant->id)
                ->update(['is_primary' => false]);

            $domain->update(['is_primary' => true]);
        });

        $this->resolver->forgetHostCache($tenant->fresh(['domains']));

        return $domain->fresh();
    }

    public function remove(Tenant $tenant, TenantDomain $domain): void
    {
        if ($domain->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'domain' => ['Domain does not belong to this company.'],
            ]);
        }

        $canonical = TenantUrl::subdomainHost($tenant->slug);

        if ($domain->host === $canonical && $domain->type === 'subdomain') {
            throw ValidationException::withMessages([
                'domain' => ['The primary CRM subdomain for this company cannot be removed.'],
            ]);
        }

        if ($domain->is_primary && $tenant->domains()->count() > 1) {
            throw ValidationException::withMessages([
                'domain' => ['Set another domain as primary before removing this one.'],
            ]);
        }

        $host = $domain->host;
        $domain->delete();

        cache()->forget('tenant:host:'.app(TenantResolverService::class)->normalizeHost($host));
        $this->resolver->forgetHostCache($tenant->fresh(['domains']));
    }

    protected function assertHostAvailable(string $host, Tenant $tenant): void
    {
        if ($host === '') {
            throw ValidationException::withMessages([
                'host' => ['Host is required.'],
            ]);
        }

        $exists = TenantDomain::query()
            ->where('host', $host)
            ->where('tenant_id', '!=', $tenant->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'host' => ['This domain is already assigned to another company.'],
            ]);
        }

        if ($tenant->domains()->where('host', $host)->exists()) {
            throw ValidationException::withMessages([
                'host' => ['This domain is already registered for this company.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(TenantDomain $domain, Tenant $tenant): array
    {
        $host = TenantUrl::normalizeHostForEnvironment($domain->host, $tenant->slug);
        $isDefaultPlatform = $tenant->slug === config('master.platform_default_slug', 'guaranteeadmit')
            && in_array($domain->type, ['primary', 'custom'], true)
            && TenantDomainHost::isBaseDomainHost($domain->host);

        return [
            'id' => $domain->id,
            'host' => $host,
            'type' => $domain->type,
            'is_primary' => (bool) $domain->is_primary,
            'url' => TenantUrl::urlForHost($host),
            'is_platform_default' => $isDefaultPlatform,
            'label' => $this->domainLabel($domain, $tenant, $isDefaultPlatform),
        ];
    }

    protected function domainLabel(TenantDomain $domain, Tenant $tenant, bool $isDefaultPlatform): string
    {
        if ($isDefaultPlatform) {
            return 'Default platform domain';
        }

        if ($domain->host === TenantUrl::subdomainHost($tenant->slug)) {
            return 'Company CRM subdomain';
        }

        return match ($domain->type) {
            'subdomain' => 'Additional subdomain',
            'custom' => 'Custom domain',
            'primary' => 'Primary domain',
            default => ucfirst($domain->type),
        };
    }
}
