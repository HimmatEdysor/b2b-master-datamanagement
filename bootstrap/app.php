<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SyncMasterPermissions;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(TrustProxies::class);
        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);
        $middleware->appendToGroup('web', SyncMasterPermissions::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
