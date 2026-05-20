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

    public static function portSuffix(): string
    {
        $port = static::port();

        if ($port === null) {
            return '';
        }

        $scheme = static::scheme();
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return '';
        }

        return ':'.$port;
    }

    public static function subdomainHost(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
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
        $host = strtolower(trim($host));

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

        return static::scheme().'://'.trim($host).static::portSuffix();
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

    /** Host only (no scheme), for slug preview fields. */
    public static function displayHostForSlug(string $slug): string
    {
        return static::subdomainHost($slug);
    }
}
