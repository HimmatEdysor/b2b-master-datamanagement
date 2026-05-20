<?php

namespace App\Support;

use Illuminate\Support\Str;

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

    /** @return array<int, string> */
    public static function validationRules(bool $uniqueTenants = true, ?int $ignoreTenantId = null): array
    {
        $rules = [
            'required',
            'string',
            'max:64',
            'regex:'.static::REGEX,
        ];

        if ($uniqueTenants) {
            $rules[] = 'unique:tenants,slug'.($ignoreTenantId ? ','.$ignoreTenantId : '');
        }

        return $rules;
    }
}
