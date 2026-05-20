<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Tenant;
use App\Services\TenantFaviconService;
use App\Services\TenantLogoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait HandlesTenantLogo
{
    protected function logoValidationRules(): array
    {
        $mimes = implode(',', config('website.logo.mimes', ['jpeg', 'jpg', 'png', 'webp']));
        $maxKb = (int) config('website.logo.max_upload_kb', 5120);
        $faviconMimes = implode(',', config('website.favicon.mimes', ['png', 'jpg', 'jpeg', 'webp', 'ico', 'svg']));
        $faviconMaxKb = (int) config('website.favicon.max_upload_kb', 512);

        return [
            'logo' => ['nullable', 'image', 'mimes:'.$mimes, 'max:'.$maxKb],
            'remove_logo' => ['nullable', 'boolean'],
            'favicon' => ['nullable', 'file', 'mimes:'.$faviconMimes, 'max:'.$faviconMaxKb],
            'remove_favicon' => ['nullable', 'boolean'],
        ];
    }

    protected function logoValidationMessages(): array
    {
        return [
            'logo.image' => 'Logo must be an image file.',
            'logo.mimes' => 'Logo must be JPEG, PNG, or WebP.',
            'logo.max' => 'Logo file is too large (max '.((int) config('website.logo.max_upload_kb', 5120) / 1024).'MB).',
        ];
    }

    protected function resolveTenantLogoUrl(Request $request, ?Tenant $tenant = null, ?string $slug = null): ?string
    {
        if ($request->boolean('remove_logo')) {
            if ($tenant?->logo_url) {
                app(TenantLogoService::class)->deleteIfStored($tenant->logo_url);
            }

            return null;
        }

        if (! $request->hasFile('logo')) {
            return $tenant?->logo_url;
        }

        try {
            $url = app(TenantLogoService::class)->store(
                $request->file('logo'),
                $slug ?? $tenant?->slug
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'logo' => [$e->getMessage() ?: 'Could not process logo image.'],
            ]);
        }

        if ($tenant?->logo_url) {
            app(TenantLogoService::class)->deleteIfStored($tenant->logo_url);
        }

        return $url;
    }

    protected function resolveTenantFaviconUrl(Request $request, ?Tenant $tenant = null, ?string $slug = null): ?string
    {
        if ($request->boolean('remove_favicon')) {
            if ($tenant?->favicon_url) {
                app(TenantFaviconService::class)->deleteIfStored($tenant->favicon_url);
            }

            return null;
        }

        if ($request->hasFile('favicon')) {
            try {
                $url = app(TenantFaviconService::class)->store(
                    $request->file('favicon'),
                    $slug ?? $tenant?->slug
                );
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'favicon' => [$e->getMessage() ?: 'Could not process favicon.'],
                ]);
            }

            if ($tenant?->favicon_url) {
                app(TenantFaviconService::class)->deleteIfStored($tenant->favicon_url);
            }

            return $url;
        }

        return $tenant?->favicon_url;
    }
}
