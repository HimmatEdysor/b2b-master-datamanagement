<?php

namespace App\Services;

use App\Models\Permission;

class MasterPermissionSyncService
{
    /**
     * Insert permissions from config/master_permissions.php (same idea as B2B PermissionSyncService).
     *
     * @return array{inserted: int, total: int}
     */
    public function sync(): array
    {
        $groups = config('master_permissions.groups', []);
        $inserted = 0;

        foreach ($groups as $group => $names) {
            if (! is_array($names)) {
                continue;
            }
            foreach ($names as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }

                $permission = Permission::query()->firstOrCreate(
                    ['name' => $name],
                    [
                        'group' => (string) $group,
                        'description' => str_replace('.', ' ', ucfirst($name)),
                        'is_active' => true,
                    ]
                );

                if ($permission->wasRecentlyCreated) {
                    $inserted++;
                } elseif ($permission->group !== $group) {
                    $permission->update(['group' => (string) $group]);
                }
            }
        }

        $total = Permission::query()->count();

        return ['inserted' => $inserted, 'total' => $total];
    }
}
