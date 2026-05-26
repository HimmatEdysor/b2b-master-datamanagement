<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;

class TenantDefaultUserService
{
    /** @var array<string, list<string>> */
    protected array $usersColumnCache = [];

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

        $userData = $this->buildUserRowForDatabase($db, [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'phone_no' => $tenant->contact_phone,
            'roles_ids' => $templateUser->roles_ids ?? null,
            'permission_ids' => $templateUser->permission_ids ?? '0',
            'is_active' => 1,
            'type' => $templateUser->type ?? 'user',
            'email_verified_at' => $now,
            'active_status' => 0,
            'avatar' => 'avatar.png',
            'dark_mode' => '0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = $this->upsertUserRow($db, $existing, $userData);

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

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function buildUserRowForDatabase(string $database, array $values): array
    {
        $allowed = $this->usersTableColumns($database);
        $row = [];

        foreach ($values as $column => $value) {
            if (in_array($column, $allowed, true)) {
                $row[$column] = $value;
            }
        }

        foreach (['name', 'email', 'password'] as $required) {
            if (! array_key_exists($required, $row)) {
                throw new \RuntimeException(
                    "Tenant database `{$database}` users table is missing required column `{$required}`."
                );
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function upsertUserRow(string $database, ?object $existing, array $data): int
    {
        if ($existing) {
            $sets = [];
            $bindings = [];

            foreach ($data as $column => $value) {
                if ($column === 'created_at') {
                    continue;
                }
                $sets[] = $this->quoteIdentifier($column).' = ?';
                $bindings[] = $value;
            }

            $bindings[] = $existing->id;
            DB::update(
                'UPDATE '.$this->quoteIdentifier($database).'.'.$this->quoteIdentifier('users')
                .' SET '.implode(', ', $sets).' WHERE id = ?',
                $bindings
            );

            return (int) $existing->id;
        }

        $columns = array_keys($data);
        $columnList = implode(', ', array_map(fn (string $c) => $this->quoteIdentifier($c), $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        DB::insert(
            'INSERT INTO '.$this->quoteIdentifier($database).'.'.$this->quoteIdentifier('users')
            ." ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );

        return (int) DB::getPdo()->lastInsertId();
    }

    protected function fetchTemplateReferenceUser(string $email): stdClass
    {
        $from = (string) config('master.template_database');
        $selectColumns = array_values(array_intersect(
            ['id', 'roles_ids', 'permission_ids', 'type'],
            $this->usersTableColumns($from)
        ));

        if ($selectColumns === []) {
            $selectColumns = ['id'];
        }

        $columnList = implode(', ', array_map(fn (string $c) => $this->quoteIdentifier($c), $selectColumns));

        $user = DB::selectOne(
            "SELECT {$columnList} FROM ".$this->quoteIdentifier($from).'.'.$this->quoteIdentifier('users')
            .' WHERE email = ? LIMIT 1',
            [$email]
        ) ?? DB::selectOne(
            "SELECT {$columnList} FROM ".$this->quoteIdentifier($from).'.'.$this->quoteIdentifier('users')
            .' ORDER BY id ASC LIMIT 1'
        );

        $roleIds = [];
        if ($user && $this->tableExists($from, 'model_has_roles')) {
            $roleIds = DB::select(
                'SELECT role_id FROM '.$this->quoteIdentifier($from).'.'.$this->quoteIdentifier('model_has_roles')
                .' WHERE model_id = ? AND model_type = ?',
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

        if ($db === null || $db === '' || ! $this->tableExists($db, 'model_has_roles')) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::delete(
                'DELETE FROM '.$this->quoteIdentifier($db).'.'.$this->quoteIdentifier('model_has_roles')
                .' WHERE model_id = ? AND model_type = ?',
                [$userId, 'App\\Models\\User']
            );

            foreach ($roleIds as $roleId) {
                DB::insert(
                    'INSERT IGNORE INTO '.$this->quoteIdentifier($db).'.'.$this->quoteIdentifier('model_has_roles')
                    .' (role_id, model_type, model_id) VALUES (?, ?, ?)',
                    [$roleId, 'App\\Models\\User', $userId]
                );
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return list<string>
     */
    protected function usersTableColumns(string $database): array
    {
        if (isset($this->usersColumnCache[$database])) {
            return $this->usersColumnCache[$database];
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$database, 'users']
        );

        $this->usersColumnCache[$database] = array_map(
            fn ($row) => (string) $row->COLUMN_NAME,
            $rows
        );

        return $this->usersColumnCache[$database];
    }

    protected function tableExists(string $database, string $table): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$database, $table]
        );

        return (int) ($row->c ?? 0) > 0;
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
