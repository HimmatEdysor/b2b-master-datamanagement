<?php

namespace App\Services;

use App\Support\TenantDbAdmin;
use PDO;

/**
 * Provision tenant DB: (1) CREATE DATABASE (2) GRANT admin on DB (3) clone schema from template.
 */
class TenantDatabaseSchemaCloneService
{
    public function adminPdo(): PDO
    {
        return TenantDbAdmin::adminPdo();
    }

    /**
     * Full provision: empty tenant database → grant b2b_master → copy schema from template.
     *
     * @return array{tables: int, views: int, method: string, database_created: bool, admin_granted: bool}
     */
    /**
     * @param  callable(int, int, string): void|null  $onTableProgress  ($current, $total, $tableName)
     */
    public function provisionTenantDatabase(
        string $fromDatabase,
        string $toDatabase,
        ?PDO $pdo = null,
        ?callable $onTableProgress = null,
    ): array {
        $pdo = $pdo ?? $this->adminPdo();

        if (! $this->schemaExists($pdo, $fromDatabase)) {
            throw new \RuntimeException(
                "Template database [{$fromDatabase}] does not exist or is not visible to TENANT_DB_USERNAME."
            );
        }

        TenantDbAdmin::createTenantDatabase($pdo, $toDatabase);
        $adminGranted = TenantDbAdmin::grantAndVerifyAdminAccessToTenantDatabase($pdo, $toDatabase);

        $clone = $this->cloneSchemaInto($fromDatabase, $toDatabase, $pdo, $onTableProgress);

        return [
            ...$clone,
            'database_created' => true,
            'admin_granted' => $adminGranted,
        ];
    }

    /**
     * @deprecated Use provisionTenantDatabase() for new tenants.
     *
     * @return array{tables: int, views: int, method: string}
     */
    public function cloneSchema(string $fromDatabase, string $toDatabase, ?PDO $pdo = null): array
    {
        return $this->provisionTenantDatabase($fromDatabase, $toDatabase, $pdo);
    }

    /**
     * Copy table + view definitions into an existing tenant database (no CREATE DATABASE).
     *
     * @return array{tables: int, views: int, method: string}
     */
    /**
     * @param  callable(int, int, string): void|null  $onTableProgress
     */
    public function cloneSchemaInto(
        string $fromDatabase,
        string $toDatabase,
        ?PDO $pdo = null,
        ?callable $onTableProgress = null,
    ): array {
        $pdo = $pdo ?? $this->adminPdo();

        if (! $this->schemaExists($pdo, $toDatabase)) {
            throw new \RuntimeException("Tenant database [{$toDatabase}] does not exist. Create it first.");
        }

        $tables = $this->tableNames($pdo, $fromDatabase);
        if ($tables === []) {
            throw new \RuntimeException("No tables found in template database [{$fromDatabase}].");
        }

        $to = TenantDbAdmin::quoteIdentifier($toDatabase);
        $totalObjects = count($tables) + count($this->viewNames($pdo, $fromDatabase));

        $sqlMode = $this->beginCloneSession($pdo);

        try {
            $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS=0');
            $pdo->exec("USE {$to}");

            $tableCount = 0;
            foreach ($tables as $table) {
                $this->createTableLike($pdo, $fromDatabase, $toDatabase, $table);
                $tableCount++;
                if ($onTableProgress !== null) {
                    $onTableProgress($tableCount, $totalObjects, $table);
                }
            }

            $this->copyForeignKeys($pdo, $fromDatabase, $toDatabase);

            $viewCount = 0;
            foreach ($this->viewNames($pdo, $fromDatabase) as $view) {
                $pdo->exec($this->fetchCreateView($pdo, $fromDatabase, $view));
                $viewCount++;
                if ($onTableProgress !== null) {
                    $onTableProgress($tableCount + $viewCount, $totalObjects, $view);
                }
            }

            $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS=1');

            return [
                'tables' => $tableCount,
                'views' => $viewCount,
                'method' => 'pdo',
            ];
        } finally {
            $this->endCloneSession($pdo, $sqlMode);
        }
    }

    /**
     * @return array{tables: int, views: int, database: string}
     */
    public function inspectTemplate(string $fromDatabase, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? $this->adminPdo();

        if (! $this->schemaExists($pdo, $fromDatabase)) {
            throw new \RuntimeException("Database [{$fromDatabase}] not found.");
        }

        return [
            'database' => $fromDatabase,
            'tables' => count($this->tableNames($pdo, $fromDatabase)),
            'views' => count($this->viewNames($pdo, $fromDatabase)),
        ];
    }

    /**
     * Relax sql_mode while replaying SHOW CREATE TABLE (template DB may have legacy timestamp defaults).
     *
     * @return array{sql_mode: string, explicit_defaults_for_timestamp: string|null}
     */
    protected function beginCloneSession(PDO $pdo): array
    {
        $sqlMode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $explicitDefaults = null;
        try {
            $explicitDefaults = (string) $pdo->query('SELECT @@SESSION.explicit_defaults_for_timestamp')->fetchColumn();
            $pdo->exec('SET SESSION explicit_defaults_for_timestamp = 0');
        } catch (\Throwable) {
            $explicitDefaults = null;
        }
        $pdo->exec("SET SESSION sql_mode = ''");

        return [
            'sql_mode' => $sqlMode,
            'explicit_defaults_for_timestamp' => $explicitDefaults,
        ];
    }

    /**
     * @param  array{sql_mode: string, explicit_defaults_for_timestamp: string|null}  $session
     */
    protected function endCloneSession(PDO $pdo, array $session): void
    {
        $stmt = $pdo->prepare('SET SESSION sql_mode = ?');
        $stmt->execute([$session['sql_mode']]);

        if ($session['explicit_defaults_for_timestamp'] !== null) {
            $pdo->exec(
                'SET SESSION explicit_defaults_for_timestamp = '
                .((int) in_array(strtolower($session['explicit_defaults_for_timestamp']), ['1', 'on'], true))
            );
        }
    }

    /**
     * Copy table structure via CREATE TABLE ... LIKE (avoids replaying SHOW CREATE TABLE DDL on MySQL 8).
     */
    protected function createTableLike(PDO $pdo, string $fromDatabase, string $toDatabase, string $table): void
    {
        $from = TenantDbAdmin::quoteIdentifier($fromDatabase).'.'.TenantDbAdmin::quoteIdentifier($table);
        $to = TenantDbAdmin::quoteIdentifier($toDatabase).'.'.TenantDbAdmin::quoteIdentifier($table);

        try {
            $pdo->exec("DROP TABLE IF EXISTS {$to}");
            $pdo->exec("CREATE TABLE {$to} LIKE {$from}");
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Failed to clone table structure for {$toDatabase}.{$table}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * CREATE TABLE ... LIKE does not copy foreign keys — re-apply them from the template schema.
     */
    protected function copyForeignKeys(PDO $pdo, string $fromDatabase, string $toDatabase): void
    {
        $stmt = $pdo->prepare(
            'SELECT rc.CONSTRAINT_NAME, rc.TABLE_NAME, rc.REFERENCED_TABLE_NAME,
                    kcu.COLUMN_NAME, kcu.REFERENCED_COLUMN_NAME, kcu.ORDINAL_POSITION,
                    rc.UPDATE_RULE, rc.DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
              AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
              AND kcu.TABLE_NAME = rc.TABLE_NAME
             WHERE rc.CONSTRAINT_SCHEMA = ?
             ORDER BY rc.TABLE_NAME, rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION'
        );
        $stmt->execute([$fromDatabase]);

        $constraints = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['TABLE_NAME'].'|'.$row['CONSTRAINT_NAME'];
            $constraints[$key] ??= [
                'table' => (string) $row['TABLE_NAME'],
                'name' => (string) $row['CONSTRAINT_NAME'],
                'referenced_table' => (string) $row['REFERENCED_TABLE_NAME'],
                'update_rule' => (string) $row['UPDATE_RULE'],
                'delete_rule' => (string) $row['DELETE_RULE'],
                'columns' => [],
                'referenced_columns' => [],
            ];
            $constraints[$key]['columns'][(int) $row['ORDINAL_POSITION']] = (string) $row['COLUMN_NAME'];
            $constraints[$key]['referenced_columns'][(int) $row['ORDINAL_POSITION']] = (string) $row['REFERENCED_COLUMN_NAME'];
        }

        foreach ($constraints as $constraint) {
            ksort($constraint['columns']);
            ksort($constraint['referenced_columns']);

            $table = TenantDbAdmin::quoteIdentifier($toDatabase).'.'.TenantDbAdmin::quoteIdentifier($constraint['table']);
            $referenced = TenantDbAdmin::quoteIdentifier($toDatabase).'.'.TenantDbAdmin::quoteIdentifier($constraint['referenced_table']);
            $columns = implode(', ', array_map(
                static fn (string $column): string => TenantDbAdmin::quoteIdentifier($column),
                array_values($constraint['columns'])
            ));
            $referencedColumns = implode(', ', array_map(
                static fn (string $column): string => TenantDbAdmin::quoteIdentifier($column),
                array_values($constraint['referenced_columns'])
            ));
            $name = TenantDbAdmin::quoteIdentifier($constraint['name']);

            $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$columns})"
                ." REFERENCES {$referenced} ({$referencedColumns})"
                .' ON DELETE '.strtoupper($constraint['delete_rule'])
                .' ON UPDATE '.strtoupper($constraint['update_rule']);

            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                throw new \RuntimeException(
                    "Failed to copy foreign key {$constraint['name']} on {$toDatabase}.{$constraint['table']}: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }
    }

    protected function schemaExists(PDO $pdo, string $schema): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1'
        );
        $stmt->execute([$schema]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return list<string>
     */
    protected function tableNames(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME"
        );
        $stmt->execute([$schema]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return list<string>
     */
    protected function viewNames(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'VIEW'
             ORDER BY TABLE_NAME"
        );
        $stmt->execute([$schema]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    protected function fetchCreateView(PDO $pdo, string $schema, string $view): string
    {
        $qualified = TenantDbAdmin::quoteIdentifier($schema).'.'.TenantDbAdmin::quoteIdentifier($view);
        $row = $pdo->query("SHOW CREATE VIEW {$qualified}")->fetch(PDO::FETCH_ASSOC);

        return $this->extractShowCreate($row, 'Create View', $schema, $view);
    }

    /**
     * @param  array<string, mixed>|false  $row
     */
    protected function extractShowCreate(array|false $row, string $preferredKey, string $schema, string $object): string
    {
        if (! is_array($row)) {
            throw new \RuntimeException("SHOW CREATE failed for {$schema}.{$object}");
        }

        if (! empty($row[$preferredKey]) && is_string($row[$preferredKey])) {
            return $row[$preferredKey];
        }

        $values = array_values($row);
        if (isset($values[1]) && is_string($values[1]) && $values[1] !== '') {
            return $values[1];
        }

        throw new \RuntimeException("Could not read CREATE statement for {$schema}.{$object}");
    }
}
