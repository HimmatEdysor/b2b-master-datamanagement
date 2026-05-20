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
    public function seedFromTemplate(Tenant $tenant): void
    {
        $from = config('master.template_database');
        $to = $tenant->database_name;
        $tables = config('master.tenant_seed_tables', []);

        if ($from === '' || $to === '' || $tables === []) {
            return;
        }

        $sqlMode = (string) (DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode ?? '');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement("SET SESSION sql_mode = ''");

        try {
            foreach ($tables as $table) {
                $this->copyTableIfEmpty($from, $to, $table);
            }

            $this->seedWebSettingsAdminTheme($from, $to);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET SESSION sql_mode = ?', [$sqlMode]);
        }
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
}
