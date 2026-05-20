<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MasterAuth
{
    public static function syncSessionPermissions(?User $user = null): void
    {
        $user ??= Auth::user();

        if (! $user) {
            session()->forget(['master_permissions', 'master_roles']);

            return;
        }

        $user->loadMissing('roles.permissions');

        session([
            'master_roles' => $user->roles->pluck('slug')->all(),
            'master_permissions' => $user->permissionNames(),
        ]);
    }

    public static function can(string $permission): bool
    {
        $permissions = session('master_permissions', []);

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }
}
