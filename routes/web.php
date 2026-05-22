<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\DashboardController;
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

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::post('upload-image', EditorImageUploadController::class)->name('upload-image');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('permission:logs.view')->group(function () {
        Route::get('logs', [ActivityLogController::class, 'index'])->name('logs.index');
    });

    Route::middleware('permission:tenants.view')->group(function () {
        Route::post('tenants/{tenant}/approve', [TenantController::class, 'approve'])
            ->middleware('permission:tenants.approve')
            ->name('tenants.approve');
        Route::post('tenants/{tenant}/reject', [TenantController::class, 'reject'])
            ->middleware('permission:tenants.approve')
            ->name('tenants.reject');
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.suspend');
        Route::post('tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.reactivate');
        Route::get('tenants/migration-queue', [TenantController::class, 'migrationQueue'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.migration-queue');
        Route::post('tenants/{tenant}/migrate-database', [TenantController::class, 'migrateDatabase'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.migrate-database');
        Route::post('tenants/{tenant}/domains', [TenantDomainController::class, 'store'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.domains.store');
        Route::post('tenants/{tenant}/domains/{domain}/primary', [TenantDomainController::class, 'setPrimary'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.domains.primary');
        Route::delete('tenants/{tenant}/domains/{domain}', [TenantDomainController::class, 'destroy'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.domains.destroy');
        Route::post('tenants/migrate-databases', [TenantController::class, 'migrateDatabases'])
            ->middleware('permission:tenants.edit')
            ->name('tenants.migrate-databases');
        Route::resource('tenants', TenantController::class)->except(['destroy']);
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
});

require __DIR__.'/auth.php';
