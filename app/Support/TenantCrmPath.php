<?php

namespace App\Support;

class TenantCrmPath
{
    public static function resolve(): ?string
    {
        $explicit = env('TENANT_CRM_PATH');
        if (is_string($explicit) && $explicit !== '' && is_file($explicit.DIRECTORY_SEPARATOR.'artisan')) {
            return realpath($explicit) ?: $explicit;
        }

        foreach (self::candidateDirectories() as $dir) {
            if (is_file($dir.DIRECTORY_SEPARATOR.'artisan')) {
                return realpath($dir) ?: $dir;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected static function candidateDirectories(): array
    {
        $base = base_path();

        $parent = dirname($base);

        return array_values(array_unique([
            $parent.DIRECTORY_SEPARATOR.'B2B_CRM',
            $parent.DIRECTORY_SEPARATOR.'b2b-crm',
            $parent.DIRECTORY_SEPARATOR.'B2B_CRM'.DIRECTORY_SEPARATOR.'crm',
            dirname($base, 2).DIRECTORY_SEPARATOR.'B2B_CRM',
            $parent,
        ]));
    }
}
