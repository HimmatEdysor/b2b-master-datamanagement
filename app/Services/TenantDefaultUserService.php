<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use stdClass;

class TenantDefaultUserService
{
    /**
     * Create default CRM login after tenant DB is cloned and reference data is seeded.
     */
    public function provisionDefaultAdmin(Tenant $tenant): void
    {
        $this->ensureDefaultAdminUser($tenant);
    }

    protected function ensureDefaultAdminUser(Tenant $tenant): void
    {
        $cfg = config('master.tenant_default_user');
        $email = strtolower(trim((string) ($cfg['email'] ?? 'himmat@edysor.in')));
        $password = (string) ($cfg['password'] ?? '12341234');
        $name = trim((string) ($tenant->contact_name ?: ($cfg['name'] ?? 'Admin')));

        if ($email === '' || $password === '') {
            throw new \RuntimeException('Default tenant user email and password must be configured.');
        }

        $templateUser = $this->fetchTemplateReferenceUser($email);
        $db = $tenant->database_name;
        $now = now()->format('Y-m-d H:i:s');

        $existing = DB::selectOne(
            "SELECT id FROM `{$db}`.`users` WHERE email = ? LIMIT 1",
            [$email]
        );

        $userData = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'phone_no' => $tenant->contact_phone,
            'roles_ids' => $templateUser->roles_ids ?? null,
            'permission_ids' => $templateUser->permission_ids ?? '0',
            'is_active' => 1,
            'type' => $templateUser->type ?? 'user',
            'email_verified_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::update(
                "UPDATE `{$db}`.`users` SET
                    name = ?, email = ?, password = ?, phone_no = ?,
                    roles_ids = ?, permission_ids = ?, is_active = ?,
                    type = ?, email_verified_at = ?, updated_at = ?
                WHERE id = ?",
                [
                    $userData['name'],
                    $userData['email'],
                    $userData['password'],
                    $userData['phone_no'],
                    $userData['roles_ids'],
                    $userData['permission_ids'],
                    $userData['is_active'],
                    $userData['type'],
                    $userData['email_verified_at'],
                    $userData['updated_at'],
                    $existing->id,
                ]
            );

            $userId = (int) $existing->id;
        } else {
            DB::insert(
                "INSERT INTO `{$db}`.`users` (
                    name, email, password, phone_no, roles_ids, permission_ids,
                    is_active, type, email_verified_at, active_status, avatar, dark_mode,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'avatar.png', '0', ?, ?)",
                [
                    $userData['name'],
                    $userData['email'],
                    $userData['password'],
                    $userData['phone_no'],
                    $userData['roles_ids'],
                    $userData['permission_ids'],
                    $userData['is_active'],
                    $userData['type'],
                    $userData['email_verified_at'],
                    $now,
                    $now,
                ]
            );

            $userId = (int) DB::getPdo()->lastInsertId();
        }

        $this->syncModelHasRoles($tenant, $userId, $templateUser->role_ids ?? []);
    }

    protected function fetchTemplateReferenceUser(string $email): stdClass
    {
        $from = config('master.template_database');

        $user = DB::selectOne(
            "SELECT id, roles_ids, permission_ids, type FROM `{$from}`.`users` WHERE email = ? LIMIT 1",
            [$email]
        ) ?? DB::selectOne(
            "SELECT id, roles_ids, permission_ids, type FROM `{$from}`.`users` ORDER BY id ASC LIMIT 1"
        );

        $roleIds = [];
        if ($user) {
            $roleIds = DB::select(
                "SELECT role_id FROM `{$from}`.`model_has_roles` WHERE model_id = ? AND model_type = ?",
                [$user->id, 'App\\Models\\User']
            );
            $roleIds = array_map(fn ($r) => (int) $r->role_id, $roleIds);
        }

        return (object) [
            'roles_ids' => $user->roles_ids ?? null,
            'permission_ids' => $user->permission_ids ?? '0',
            'type' => $user->type ?? 'user',
            'role_ids' => $roleIds,
        ];
    }

    /** @param array<int, int> $roleIds */
    protected function syncModelHasRoles(Tenant $tenant, int $userId, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $db = $tenant->database_name;

        if (! $this->tableExists($db, 'model_has_roles')) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::delete(
                "DELETE FROM `{$db}`.`model_has_roles` WHERE model_id = ? AND model_type = ?",
                [$userId, 'App\\Models\\User']
            );

            foreach ($roleIds as $roleId) {
                DB::insert(
                    "INSERT IGNORE INTO `{$db}`.`model_has_roles` (role_id, model_type, model_id) VALUES (?, ?, ?)",
                    [$roleId, 'App\\Models\\User', $userId]
                );
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    protected function tableExists(string $database, string $table): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$database, $table]
        );

        return (int) ($row->c ?? 0) > 0;
    }
}
