<?php

namespace App\Providers;

use App\Models\Page;
use App\Services\MasterSettingsService;
use App\Support\TenantCrmPath;
use App\Support\TenantUrl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(MasterSettingsService::class)->applyToConfig();

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }

        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        $host = parse_url($appUrl, PHP_URL_HOST);
        $useHttps = $scheme === 'https'
            || (config('app.env') === 'production' && ! in_array($host, ['localhost', '127.0.0.1'], true));

        if ($useHttps) {
            URL::forceScheme('https');
            if (env('SESSION_SECURE_COOKIE') === null) {
                config(['session.secure' => true]);
            }
        }

        if (! config('master.tenant_crm_path')) {
            config(['master.tenant_crm_path' => TenantCrmPath::resolve()]);
        }

        Paginator::useBootstrapFive();

        View::composer('layouts.website', function ($view) {
            $view->with('navPages', Page::query()->inNav()->get());
        });

        View::composer('layouts.admin', function ($view) {
            $view->with('urlEnvironment', [
                'label' => TenantUrl::environmentLabel(),
                'is_local' => TenantUrl::isLocal(),
                'tenant_base' => TenantUrl::baseDomain(),
                'scheme' => TenantUrl::scheme(),
                'port' => TenantUrl::portSuffix(),
            ]);
        });
    }
}
