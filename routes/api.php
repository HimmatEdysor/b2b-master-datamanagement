<?php

use App\Http\Controllers\Api\TenantDomainsController;
use App\Http\Controllers\Api\TenantMigrateController;
use App\Http\Controllers\Api\TenantMigrationDatabasesController;
use App\Http\Controllers\Api\TenantResolveController;
use App\Http\Middleware\VerifyCrmApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(VerifyCrmApiToken::class)->group(function () {
        Route::get('tenant/resolve', TenantResolveController::class);
        Route::get('tenants/migration-databases', TenantMigrationDatabasesController::class);
        Route::post('tenants/{slug}/migrate', TenantMigrateController::class);
        Route::get('tenants/{slug}/domains', [TenantDomainsController::class, 'index']);
        Route::post('tenants/{slug}/domains', [TenantDomainsController::class, 'store']);
        Route::post('tenants/{slug}/domains/{domain}', [TenantDomainsController::class, 'setPrimary']);
        Route::delete('tenants/{slug}/domains/{domain}', [TenantDomainsController::class, 'destroy']);
    });
});
