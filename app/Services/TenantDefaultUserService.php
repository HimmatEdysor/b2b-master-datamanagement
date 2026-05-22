<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;

class TenantDefaultUserService
{
    /**
     * Create default CRM login after tenant DB is cloned and reference data is seeded.
     *
     * @return array{email: string, password: string} Plain CRM login (stored encrypted on tenant)
     */
    public function provisionDefaultAdmin(Tenant $tenant): array
    {
        return $this->ensureDefaultAdminUser($tenant);
    }

    /**
     * Set CRM admin login password in tenant DB and on master tenant row.
     *
     * @return array{email: string, password: string}
     */
    public function setCrmAdminPassword(Tenant $tenant, ?string $plainPassword = null): array
    {
        $plainPassword = $plainPassword ?? Str::password(16, letters: true, numbers: true, symbols: false);

        if (strlen($plainPassword) < 8) {
            throw new \InvalidArgumentException('CRM password must be at least 8 characters.');
        }

        $email = $tenant->crmAdminEmail();

        if ($email === '') {
            throw new \RuntimeException('CRM admin email is not configured for this company.');
        }

        if ($tenant->database_name === null || $tenant->database_name === '') {
            throw new \RuntimeException('Company database is not provisioned yet.');
        }

        $db = $tenant->database_name;
        $hashed = Hash::make($plainPassword);
        $now = now()->format('Y-m-d H:i:s');

        if (! $this->tableExists($db, 'users')) {
            throw new \RuntimeException("Tenant database `{$db}` has no `users` table.");
        }

        $updated = DB::update(
            "UPDATE `{$db}`.`users` SET password = ?, updated_at = ? WHERE email = ?",
            [$hashed, $now, $email]
        );

        if ($updated === 0) {
            throw new \RuntimeException(
                "No CRM user with email {$email} in `{$db}`. Use Regenerate CRM password to create the admin user first."
            );
        }

        $tenant->update([
            'crm_admin_email' => $email,
            'crm_admin_password' => $plainPassword,
        ]);

        return [
            'email' => $email,
            'password' => $plainPassword,
        ];
    }

    /**
     * @return array{email: string, password: string}
     */
    protected function ensureDefaultAdminUser(Tenant $tenant): array
    {
        $cfg = config('master.tenant_default_user');
        $email = strtolower(trim((string) ($cfg['email'] ?? '')));
        $name = trim((string) ($tenant->contact_name ?: ($cfg['name'] ?? 'Admin')));

        if ($email === '') {
            throw new \RuntimeException('Set TENANT_DEFAULT_USER_EMAIL in master .env for the default CRM admin email.');
        }

        $plainPassword = Str::password(16, letters: true, numbers: true, symbols: false);

        $templateUser = $this->fetchTemplateReferenceUser($email);
        $db = $tenant->database_name;
        $now = now()->format('Y-m-d H:i:s');

        if (! $this->tableExists($db, 'users')) {
            throw new \RuntimeException(
                "Tenant database `{$db}` has no `users` table. Re-run Approve & provision to clone the full schema from "
                .config('master.template_database').' (an incomplete database was detected and should be replaced).'
            );
        }

        $existing = DB::selectOne(
            "SELECT id FROM `{$db}`.`users` WHERE email = ? LIMIT 1",
            [$email]
        );

        $userData = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
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

        $tenant->update([
            'crm_admin_email' => $email,
            'crm_admin_password' => $plainPassword,
        ]);

        return [
            'email' => $email,
            'password' => $plainPassword,
        ];
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
