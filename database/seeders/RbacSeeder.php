<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\EnsureDefaultAdmin;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $groups = config('master_permissions.groups', []);
        $permissionIds = [];

        foreach ($groups as $group => $names) {
            foreach ($names as $name) {
                $permission = Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['group' => $group, 'description' => str_replace('.', ' ', ucfirst($name))]
                );
                $permissionIds[] = $permission->id;
            }
        }

        $superAdmin = Role::query()->updateOrCreate(
            ['slug' => config('master_permissions.super_admin_role', 'super-admin')],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to master portal',
            ]
        );
        $superAdmin->permissions()->sync($permissionIds);

        $supportAgent = Role::query()->updateOrCreate(
            ['slug' => 'support-agent'],
            [
                'name' => 'Support Agent',
                'description' => 'Manage support tickets and view companies',
            ]
        );
        $supportAgent->permissions()->sync(
            Permission::query()->whereIn('name', [
                'dashboard.view',
                'tenants.view',
                'tickets.view',
                'tickets.reply',
                'tickets.manage',
            ])->pluck('id')
        );

        $admin = EnsureDefaultAdmin::run();
        $admin->roles()->sync([$superAdmin->id]);

        User::query()
            ->where('email', config('master_permissions.default_admin_email'))
            ->each(fn (User $user) => $user->roles()->syncWithoutDetaching([$superAdmin->id]));
    }
}
