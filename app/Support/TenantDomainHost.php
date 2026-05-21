<?php

namespace App\Support;

class TenantDomainHost
{
    public const CUSTOM_DOMAIN_REGEX = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

    public static function normalize(string $host): string
    {
        return strtolower(trim(preg_replace('/\s+/', '', $host) ?? ''));
    }

    public static function customDomainRules(): array
    {
        return ['required', 'string', 'max:255', 'regex:'.self::CUSTOM_DOMAIN_REGEX];
    }

    public static function isBaseDomainHost(string $host): bool
    {
        $host = self::normalize($host);
        $base = TenantUrl::baseDomain();

        return $host === $base || $host === 'www.'.$base;
    }
}
