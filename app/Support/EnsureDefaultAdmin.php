<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EnsureDefaultAdmin
{
    public static function run(?User $user = null): User
    {
        $email = config('master_permissions.default_admin_email', 'admin@master.local');

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('master_permissions.default_admin_name', 'Master Admin'),
                'password' => Hash::make(config('master_permissions.default_admin_password', 'password')),
            ]
        );

        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => config('master_permissions.super_admin_role', 'super-admin')],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to master portal',
            ]
        );

        $target = $user ?? $admin;
        if (! $target->roles()->where('roles.id', $superAdmin->id)->exists()) {
            $target->roles()->syncWithoutDetaching([$superAdmin->id]);
        }

        return $admin;
    }
}
