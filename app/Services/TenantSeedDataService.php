<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantSeedDataService
{
    /**
     * Copy master/reference rows from the template database into a new tenant DB.
     * Runs after schema clone (structure only); skips tables that already have rows.
     */
    /**
     * @param  callable(int, int, string): void|null  $onTableProgress  ($current, $total, $tableName)
     */
    public function seedFromTemplate(Tenant $tenant, ?callable $onTableProgress = null): void
    {
        $from = config('master.template_database');
        $to = $tenant->database_name;
        $tables = config('master.tenant_seed_tables', []);

        if ($from === '' || $to === '' || $tables === []) {
            return;
        }

        $seedSteps = count($tables) + 1;
        $sqlMode = (string) (DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode ?? '');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement("SET SESSION sql_mode = ''");

        try {
            $step = 0;
            foreach ($tables as $table) {
                $step++;
                if ($onTableProgress !== null) {
                    $onTableProgress($step, $seedSteps, $table);
                }

                if ($table === 'universities') {
                    $this->copyUniversitiesWithBlankUrmFields($from, $to);

                    continue;
                }

                $this->copyTableIfEmpty($from, $to, $table);
            }

            if ($onTableProgress !== null) {
                $onTableProgress($seedSteps, $seedSteps, 'web_settings');
            }

            $this->seedWebSettingsAdminTheme($from, $to);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET SESSION sql_mode = ?', [$sqlMode]);
        }
    }

    /**
     * After schema clone and/or reference seed: template user(s) by id, blank URM fields on universities.
     */
    public function applyCloneCustomization(Tenant $tenant): void
    {
        $from = config('master.template_database');
        $to = $tenant->database_name;

        if ($from === '' || $to === '') {
            return;
        }

        $sqlMode = (string) (DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode ?? '');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement("SET SESSION sql_mode = ''");

        try {
            $this->copyTemplateUsers($from, $to);
            $this->blankUniversitiesUrmFields($to);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET SESSION sql_mode = ?', [$sqlMode]);
        }
    }

    /**
     * Copy universities from template but force URM contact columns empty in tenant DB.
     */
    protected function copyUniversitiesWithBlankUrmFields(string $from, string $to): void
    {
        if (! $this->tableExists($from, 'universities') || ! $this->tableExists($to, 'universities')) {
            return;
        }

        $targetCount = (int) DB::selectOne(
            "SELECT COUNT(*) AS c FROM `{$to}`.`universities`"
        )->c;

        if ($targetCount > 0) {
            return;
        }

        $sourceCount = (int) DB::selectOne(
            "SELECT COUNT(*) AS c FROM `{$from}`.`universities`"
        )->c;

        if ($sourceCount === 0) {
            return;
        }

        $columns = $this->tableColumns($from, 'universities');
        $blankCols = config('master.tenant_universities_blank_columns', ['urm_name', 'urm_contact_no', 'urm_email']);
        $blankCols = array_values(array_intersect($blankCols, $columns));

        if ($columns === []) {
            return;
        }

        $selectParts = [];
        foreach ($columns as $col) {
            $selectParts[] = in_array($col, $blankCols, true)
                ? 'NULL AS `'.$col.'`'
                : '`'.$col.'`';
        }

        $columnList = implode('`, `', $columns);

        DB::statement(
            "INSERT INTO `{$to}`.`universities` (`{$columnList}`) SELECT ".implode(', ', $selectParts)." FROM `{$from}`.`universities`"
        );
    }

    /**
     * @param  list<int>  $userIds
     */
    protected function copyTemplateUsers(string $from, string $to): void
    {
        if (! $this->tableExists($from, 'users') || ! $this->tableExists($to, 'users')) {
            return;
        }

        $userIds = config('master.tenant_seed_user_ids', [1]);
        if (! is_array($userIds)) {
            $userIds = [1];
        }

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId < 1) {
                continue;
            }

            $source = DB::selectOne(
                "SELECT COUNT(*) AS c FROM `{$from}`.`users` WHERE `id` = ?",
                [$userId]
            );

            if ((int) ($source->c ?? 0) === 0) {
                continue;
            }

            DB::statement("DELETE FROM `{$to}`.`users` WHERE `id` = ?", [$userId]);
            DB::statement(
                "INSERT INTO `{$to}`.`users` SELECT * FROM `{$from}`.`users` WHERE `id` = ?",
                [$userId]
            );
        }
    }

    protected function blankUniversitiesUrmFields(string $to): void
    {
        if (! $this->tableExists($to, 'universities')) {
            return;
        }

        $blankCols = config('master.tenant_universities_blank_columns', ['urm_name', 'urm_contact_no', 'urm_email']);
        $sets = [];

        foreach ($blankCols as $col) {
            if ($this->columnExists($to, 'universities', $col)) {
                $sets[] = "`{$col}` = NULL";
            }
        }

        if ($sets === []) {
            return;
        }

        DB::statement('UPDATE `'.$to.'`.`universities` SET '.implode(', ', $sets));
    }

    protected function copyTableIfEmpty(string $from, string $to, string $table): void
    {
        if (! $this->tableExists($from, $table) || ! $this->tableExists($to, $table)) {
            return;
        }

        $targetCount = (int) DB::selectOne(
            "SELECT COUNT(*) AS c FROM `{$to}`.`{$table}`"
        )->c;

        if ($targetCount > 0) {
            return;
        }

        $sourceCount = (int) DB::selectOne(
            "SELECT COUNT(*) AS c FROM `{$from}`.`{$table}`"
        )->c;

        if ($sourceCount === 0) {
            return;
        }

        DB::statement("INSERT INTO `{$to}`.`{$table}` SELECT * FROM `{$from}`.`{$table}`");
    }

    /**
     * Copy only admin color theme from template global web_settings — not other settings.
     */
    protected function seedWebSettingsAdminTheme(string $from, string $to): void
    {
        if (! $this->tableExists($from, 'web_settings') || ! $this->tableExists($to, 'web_settings')) {
            return;
        }

        $columns = array_values(array_filter(
            config('master.tenant_web_setting_theme_columns', ['admin_theme_json', 'chat_custom_color']),
            fn (string $col) => $this->columnExists($from, 'web_settings', $col)
                && $this->columnExists($to, 'web_settings', $col)
        ));

        if ($columns === [] || ! in_array('admin_theme_json', $columns, true)) {
            return;
        }

        $select = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
        $source = DB::selectOne(
            "SELECT {$select} FROM `{$from}`.`web_settings` WHERE `user_id` IS NULL ORDER BY `id` ASC LIMIT 1"
        );

        if (! $source || empty($source->admin_theme_json)) {
            return;
        }

        $values = [];
        foreach ($columns as $col) {
            $values[$col] = $source->{$col} ?? null;
        }

        $target = DB::selectOne(
            "SELECT `id` FROM `{$to}`.`web_settings` WHERE `user_id` IS NULL ORDER BY `id` ASC LIMIT 1"
        );

        $now = now()->format('Y-m-d H:i:s');

        if ($target) {
            $sets = [];
            $bindings = [];
            foreach ($columns as $col) {
                $sets[] = "`{$col}` = ?";
                $bindings[] = $values[$col];
            }
            $sets[] = '`updated_at` = ?';
            $bindings[] = $now;
            $bindings[] = $target->id;

            DB::update(
                'UPDATE `'.$to.'`.`web_settings` SET '.implode(', ', $sets).' WHERE `id` = ?',
                $bindings
            );

            return;
        }

        $insertCols = array_merge(['user_id'], $columns, ['created_at', 'updated_at']);
        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        $bindings = array_merge(
            [null],
            array_values($values),
            [$now, $now]
        );

        DB::insert(
            'INSERT INTO `'.$to.'`.`web_settings` (`'.implode('`, `', $insertCols).'`) VALUES ('.$placeholders.')',
            $bindings
        );
    }

    protected function tableExists(string $database, string $table): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$database, $table]
        );

        return (int) ($row->c ?? 0) > 0;
    }

    protected function columnExists(string $database, string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$database, $table, $column]
        );

        return (int) ($row->c ?? 0) > 0;
    }

    /**
     * @return list<string>
     */
    protected function tableColumns(string $database, string $table): array
    {
        $rows = DB::select(
            'SELECT column_name FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ordinal_position',
            [$database, $table]
        );

        return array_map(fn ($row) => (string) $row->column_name, $rows);
    }
}
