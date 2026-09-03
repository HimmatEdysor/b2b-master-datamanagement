<?php

namespace App\Support;

use App\Models\Tenant;

class TenantUrl
{
    public static function isLocal(): bool
    {
        return (bool) config('master.is_local');
    }

    public static function environmentLabel(): string
    {
        return static::isLocal() ? 'Local' : 'Production';
    }

    public static function baseDomain(): string
    {
        return (string) config('master.tenant_base_domain');
    }

    public static function scheme(): string
    {
        return (string) config('master.tenant_url_scheme', 'https');
    }

    /** Port number or null (e.g. 8000 for local CRM). */
    public static function port(): ?int
    {
        $port = config('master.tenant_crm_port');

        return $port !== null && $port !== '' ? (int) $port : null;
    }

    /**
     * Whether tenant CRM links should include a non-standard port (local dev only).
     */
    public static function usesPortInUrls(): bool
    {
        if (filter_var(config('master.tenant_crm_port_force'), FILTER_VALIDATE_BOOLEAN)) {
            return static::port() !== null;
        }

        if (! static::isLocal()) {
            return false;
        }

        if (static::baseDomainIsProduction()) {
            return false;
        }

        return static::port() !== null;
    }

    public static function portSuffix(): string
    {
        if (! static::usesPortInUrls()) {
            return '';
        }

        $port = static::port();
        $scheme = static::scheme();

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return '';
        }

        return ':'.$port;
    }

    public static function baseDomainIsProduction(): bool
    {
        $base = static::baseDomain();
        $prod = (string) config('master.tenant_base_domain_production', 'main.guaranteeadmit.com');

        return $base === $prod
            || ($prod !== '' && str_ends_with($base, '.'.$prod))
            || ($base !== '' && str_ends_with($prod, '.'.$base));
    }

    public static function platformDefaultSlug(): string
    {
        return strtolower(trim((string) config('master.platform_default_slug', 'guaranteeadmit')));
    }

    public static function isPlatformDefaultSlug(?string $slug): bool
    {
        $slug = strtolower(trim((string) $slug));
        $default = static::platformDefaultSlug();

        return $slug !== '' && $default !== '' && $slug === $default;
    }

    /**
     * Laravel CRM apex (and www), including the production base when TENANT_BASE_DOMAIN
     * is still the old registrable domain (guaranteeadmit.com).
     */
    public static function isApexHost(string $host): bool
    {
        $host = static::normalizeHost($host);
        if ($host === '') {
            return false;
        }

        $skip = ['localhost', '127.0.0.1', '::1'];
        $bases = array_unique(array_filter([
            static::normalizeHost(static::baseDomain()),
            static::normalizeHost((string) config('master.tenant_base_domain_production', 'main.guaranteeadmit.com')),
        ]));

        foreach ($bases as $base) {
            if ($base === '' || in_array($base, $skip, true)) {
                continue;
            }
            if ($host === $base || $host === 'www.'.$base) {
                return true;
            }
        }

        return false;
    }

    /** Strip slashes, ports, and whitespace from a hostname. */
    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = rtrim($host, '/');

        if (preg_match('/^(.+):(\d+)$/', $host, $matches) && ! str_contains($matches[1], ']')) {
            $host = $matches[1];
        }

        return $host;
    }

    public static function subdomainHost(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '' || static::isPlatformDefaultSlug($slug)) {
            return static::baseDomain();
        }

        return $slug.'.'.static::baseDomain();
    }

    public static function hostForTenant(Tenant $tenant): ?string
    {
        $primary = $tenant->primaryDomain()?->host;

        if ($primary) {
            return static::normalizeHostForEnvironment($primary, $tenant->slug);
        }

        if ($tenant->slug) {
            return static::subdomainHost($tenant->slug);
        }

        return null;
    }

    /**
     * When DB has production/test host but APP_ENV=local, show {slug}.localhost for subdomain tenants.
     */
    public static function normalizeHostForEnvironment(string $host, ?string $slug = null): string
    {
        $host = static::normalizeHost($host);

        if (! static::isLocal() || $slug === null || $slug === '') {
            return $host;
        }

        $expected = static::subdomainHost($slug);

        if ($host === $expected) {
            return $host;
        }

        if (str_starts_with($host, $slug.'.')) {
            return $expected;
        }

        return $host;
    }

    public static function urlForHost(?string $host): ?string
    {
        if ($host === null || trim($host) === '') {
            return null;
        }

        return static::scheme().'://'.static::normalizeHost($host).static::portSuffix();
    }

    public static function urlForSlug(string $slug): string
    {
        return static::urlForHost(static::subdomainHost($slug)) ?? '';
    }

    public static function urlForTenant(Tenant $tenant): ?string
    {
        $host = static::hostForTenant($tenant);

        return $host ? static::urlForHost($host) : null;
    }

    public static function loginUrlForHost(?string $host): ?string
    {
        $base = static::urlForHost($host);

        return $base ? rtrim($base, '/').'/login' : null;
    }

    public static function loginUrlForTenant(Tenant $tenant): ?string
    {
        $host = static::hostForTenant($tenant);

        return $host ? static::loginUrlForHost($host) : null;
    }

    /** Host only (no scheme), for slug preview fields. */
    public static function displayHostForSlug(string $slug): string
    {
        return static::subdomainHost($slug);
    }
}
