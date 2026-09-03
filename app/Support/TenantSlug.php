<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantSlug
{
    public const PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public const REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Normalize user input: lowercase, no spaces, only a-z 0-9 and single hyphens.
     */
    public static function normalize(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $slug = Str::lower(trim($value));
        $slug = preg_replace('/\s+/', '', $slug);
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);

        return trim($slug, '-');
    }

    public static function fromName(string $name): string
    {
        return static::normalize(
            Str::slug($name, '-')
        );
    }

    public static function isValid(?string $slug): bool
    {
        return $slug !== null && $slug !== '' && (bool) preg_match(static::REGEX, $slug);
    }

    /**
     * Infrastructure labels that must not become {slug}.TENANT_BASE_DOMAIN.
     *
     * @return list<string>
     */
    public static function reserved(): array
    {
        $reserved = config('master.reserved_tenant_slugs', [
            'main', 'www', 'master', 'api', 'next', 'mail',
        ]);

        if (! is_array($reserved)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $reserved
        ))));
    }

    /**
     * Reserved labels excluding the platform default slug (that company uses the apex).
     *
     * @return list<string>
     */
    public static function reservedForNewCompanies(): array
    {
        $default = TenantUrl::platformDefaultSlug();

        return array_values(array_filter(
            static::reserved(),
            static fn (string $slug) => $slug !== $default
        ));
    }

    /** @return array<int, mixed> */
    public static function validationRules(bool $uniqueTenants = true, ?int $ignoreTenantId = null): array
    {
        $rules = [
            'required',
            'string',
            'max:64',
            'regex:'.static::REGEX,
        ];

        $reserved = static::reservedForNewCompanies();
        if ($reserved !== []) {
            $rules[] = Rule::notIn($reserved);
        }

        if ($uniqueTenants) {
            $rules[] = 'unique:tenants,slug'.($ignoreTenantId ? ','.$ignoreTenantId : '');
        }

        return $rules;
    }
}
