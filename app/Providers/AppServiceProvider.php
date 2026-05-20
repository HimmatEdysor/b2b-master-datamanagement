<?php

namespace App\Providers;

use App\Models\Page;
use App\Support\TenantUrl;
use Illuminate\Pagination\Paginator;
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
