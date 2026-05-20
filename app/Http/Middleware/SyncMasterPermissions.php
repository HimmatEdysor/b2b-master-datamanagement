<?php

namespace App\Http\Middleware;

use App\Services\MasterPermissionSyncService;
use App\Support\EnsureDefaultAdmin;
use App\Support\MasterAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SyncMasterPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Cache::has('master_permissions_catalog_synced')) {
            app(MasterPermissionSyncService::class)->sync();
            Cache::put('master_permissions_catalog_synced', true, now()->addHour());
        }

        if ($request->user()) {
            $user = $request->user();

            if (
                $user->roles()->count() === 0
                && $user->email === config('master_permissions.default_admin_email')
            ) {
                EnsureDefaultAdmin::run($user);
                $user->unsetRelation('roles');
            }

            MasterAuth::syncSessionPermissions($user);
        }

        return $next($request);
    }
}
