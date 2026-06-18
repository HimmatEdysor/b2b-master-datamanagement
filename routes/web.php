<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\SubdomainCheckLogController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MasterSettingsController;
use App\Http\Controllers\Admin\EditorImageUploadController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\TenantDomainController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CompanyRegistrationController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'home'])->name('home');
Route::get('/services', [WebsiteController::class, 'services'])->name('services');
Route::get('/pricing', [WebsiteController::class, 'pricing'])->name('pricing');
Route::get('/pages/{slug}', [WebsiteController::class, 'page'])->name('page.show');
Route::get('/blog', [WebsiteController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [WebsiteController::class, 'blogShow'])->name('blog.show');

Route::get('/support', [SupportTicketController::class, 'create'])->name('support.create');
Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
Route::get('/support/ticket/{ticketNumber}', [SupportTicketController::class, 'show'])->name('support.show');
Route::post('/support/ticket/{ticketNumber}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');

Route::get('/register', [CompanyRegistrationController::class, 'create'])->name('register');
Route::post('/register', [CompanyRegistrationController::class, 'store'])->name('register.store');
Route::get('/register/success', [CompanyRegistrationController::class, 'success'])->name('register.success');

Route::middleware(['web', 'auth'])->group(function () {
    Broadcast::routes();
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::post('upload-image', EditorImageUploadController::class)->name('upload-image');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('permission:logs.view,tenants.view')->group(function () {
        Route::get('logs', [ActivityLogController::class, 'index'])->name('logs.index');
        Route::get('logs/subdomain-checks', [SubdomainCheckLogController::class, 'index'])->name('subdomain-checks.index');
        Route::get('logs/subdomain-checks/{host}', [SubdomainCheckLogController::class, 'show'])
            ->where('host', '.*')
            ->name('subdomain-checks.show');
        Route::delete('logs/{channel}/{date}', [ActivityLogController::class, 'destroy'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('logs.destroy');
    });

    Route::prefix('tenants')->name('tenants.')->middleware('permission:tenants.view')->group(function () {
        // Static routes MUST be above `{tenant}` resource routes.
        Route::get('migration-queue', [TenantController::class, 'migrationQueue'])
            ->middleware('permission:tenants.edit')
            ->name('migration-queue');
        Route::post('migrate-databases', [TenantController::class, 'migrateDatabases'])
            ->middleware('permission:tenants.edit')
            ->name('migrate-databases');

        Route::post('{tenant}/approve', [TenantController::class, 'approve'])
            ->middleware('permission:tenants.approve')
            ->name('approve');
        Route::post('{tenant}/reconcile-provisioning', [TenantController::class, 'reconcileProvisioning'])
            ->middleware('permission:tenants.approve')
            ->name('reconcile-provisioning');
        Route::get('{tenant}/provisioning-status', [TenantController::class, 'provisioningStatus'])
            ->name('provisioning-status');
        Route::get('{tenant}/provisioning', [TenantController::class, 'provisioning'])
            ->name('provisioning');

        Route::post('{tenant}/database-user', [TenantController::class, 'regenerateDatabaseUser'])
            ->middleware('permission:tenants.edit')
            ->name('database-user');
        Route::put('{tenant}/database-password', [TenantController::class, 'updateDatabasePassword'])
            ->middleware('permission:tenants.edit')
            ->name('database-password');
        Route::put('{tenant}/manage', [TenantController::class, 'updateManage'])
            ->middleware('permission:tenants.edit,tenants.approve')
            ->name('manage');

        Route::post('{tenant}/crm-admin-password', [TenantController::class, 'regenerateCrmAdminPassword'])
            ->middleware('permission:tenants.edit')
            ->name('crm-admin-password');
        Route::put('{tenant}/crm-admin-password', [TenantController::class, 'updateCrmAdminPassword'])
            ->middleware('permission:tenants.edit')
            ->name('crm-admin-password.update');

        Route::post('{tenant}/reject', [TenantController::class, 'reject'])
            ->middleware('permission:tenants.approve')
            ->name('reject');
        Route::post('{tenant}/suspend', [TenantController::class, 'suspend'])
            ->middleware('permission:tenants.edit')
            ->name('suspend');
        Route::post('{tenant}/reactivate', [TenantController::class, 'reactivate'])
            ->middleware('permission:tenants.edit')
            ->name('reactivate');

        Route::post('{tenant}/migrate-database', [TenantController::class, 'migrateDatabase'])
            ->middleware('permission:tenants.edit')
            ->name('migrate-database');

        Route::post('{tenant}/domains', [TenantDomainController::class, 'store'])
            ->middleware('permission:tenants.edit')
            ->name('domains.store');
        Route::post('{tenant}/domains/{domain}/primary', [TenantDomainController::class, 'setPrimary'])
            ->middleware('permission:tenants.edit')
            ->name('domains.primary');
        Route::delete('{tenant}/domains/{domain}', [TenantDomainController::class, 'destroy'])
            ->middleware('permission:tenants.edit')
            ->name('domains.destroy');

        Route::post('{tenant}/domains/dns-provision-all', [TenantDomainController::class, 'provisionAllDns'])
            ->middleware('permission:tenants.edit,tenants.approve')
            ->name('domains.dns-provision-all');
        Route::post('{tenant}/domains/{domain}/dns-provision', [TenantDomainController::class, 'provisionDns'])
            ->middleware('permission:tenants.edit,tenants.approve')
            ->name('domains.dns-provision');
        Route::post('{tenant}/domains/{domain}/dns-verify', [TenantDomainController::class, 'verifyDns'])
            ->middleware('permission:tenants.edit,tenants.approve')
            ->name('domains.dns-verify');
        Route::post('{tenant}/domains/{domain}/ssl-check', [TenantDomainController::class, 'checkSsl'])
            ->middleware('permission:tenants.edit,tenants.approve')
            ->name('domains.ssl-check');
        Route::post('{tenant}/domains/{domain}/ssl-complete', [TenantDomainController::class, 'markSslComplete'])
            ->middleware('permission:tenants.edit,tenants.approve')
            ->name('domains.ssl-complete');

        Route::resource('/', TenantController::class)
            ->parameters(['' => 'tenant'])
            ->except(['destroy'])
            ->names([
                'index' => 'index',
                'create' => 'create',
                'store' => 'store',
                'show' => 'show',
                'edit' => 'edit',
                'update' => 'update',
            ]);
    });

    Route::middleware('permission:blog.view')->group(function () {
        Route::resource('blog', BlogPostController::class)->except(['show']);
    });

    Route::middleware('permission:plans.view')->group(function () {
        Route::resource('plans', SubscriptionPlanController::class)->except(['show']);
    });

    Route::middleware('permission:pages.view')->group(function () {
        Route::resource('pages', PageController::class)->except(['show']);
    });

    Route::middleware('permission:tickets.view')->prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/', [AdminSupportTicketController::class, 'index'])->name('index');
        Route::get('/{ticket}', [AdminSupportTicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [AdminSupportTicketController::class, 'reply'])
            ->middleware('permission:tickets.reply')
            ->name('reply');
        Route::patch('/{ticket}', [AdminSupportTicketController::class, 'update'])
            ->middleware('permission:tickets.manage')
            ->name('update');
    });

    Route::middleware('permission:users.view')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('permission:roles.view')->group(function () {
        Route::post('roles/{role}/change-status', [RoleController::class, 'changeStatus'])
            ->middleware('permission:roles.edit')
            ->name('roles.change-status');
        Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
    });

    Route::middleware('permission:permissions.view')->group(function () {
        Route::post('permissions/sync-config', [PermissionController::class, 'syncFromConfig'])
            ->middleware('permission:permissions.edit')
            ->name('permissions.sync-config');
        Route::resource('permissions', PermissionController::class)->except(['show', 'destroy']);
    });

    Route::middleware('permission:settings.view')->group(function () {
        Route::get('settings', [MasterSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [MasterSettingsController::class, 'update'])
            ->middleware('permission:settings.edit')
            ->name('settings.update');
        Route::post('settings/tenant-db-check', [MasterSettingsController::class, 'checkTenantDatabase'])
            ->middleware('permission:settings.edit')
            ->name('settings.tenant-db-check');
    });
});

require __DIR__.'/auth.php';
