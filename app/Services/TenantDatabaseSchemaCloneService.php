<?php

namespace App\Services;

use App\Support\TenantDbAdmin;
use PDO;

/**
 * Clone MySQL schema without mysqldump (safe on AWS RDS — no RELOAD / FLUSH TABLES).
 */
class TenantDatabaseSchemaCloneService
{
    public function adminPdo(): PDO
    {
        TenantDbAdmin::assertCanProvision();

        return new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', TenantDbAdmin::host(), TenantDbAdmin::port()),
            TenantDbAdmin::username(),
            TenantDbAdmin::password(),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Copy table + view definitions from template DB into tenant DB (no row data).
     *
     * @return array{tables: int, views: int, method: string}
     */
    public function cloneSchema(string $fromDatabase, string $toDatabase, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? $this->adminPdo();

        if (! $this->schemaExists($pdo, $fromDatabase)) {
            throw new \RuntimeException(
                "Template database [{$fromDatabase}] does not exist or is not visible to TENANT_DB_USERNAME."
            );
        }

        $to = TenantDbAdmin::quoteIdentifier($toDatabase);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$to} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $tables = $this->tableNames($pdo, $fromDatabase);
        if ($tables === []) {
            throw new \RuntimeException("No tables found in template database [{$fromDatabase}].");
        }

        $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS=0');
        $pdo->exec("USE {$to}");

        $tableCount = 0;
        foreach ($tables as $table) {
            $pdo->exec($this->fetchCreateTable($pdo, $fromDatabase, $table));
            $tableCount++;
        }

        $viewCount = 0;
        foreach ($this->viewNames($pdo, $fromDatabase) as $view) {
            $pdo->exec($this->fetchCreateView($pdo, $fromDatabase, $view));
            $viewCount++;
        }

        $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS=1');

        return [
            'tables' => $tableCount,
            'views' => $viewCount,
            'method' => 'pdo',
        ];
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

    protected function fetchCreateTable(PDO $pdo, string $schema, string $table): string
    {
        $qualified = TenantDbAdmin::quoteIdentifier($schema).'.'.TenantDbAdmin::quoteIdentifier($table);
        $row = $pdo->query("SHOW CREATE TABLE {$qualified}")->fetch(PDO::FETCH_ASSOC);

        return $this->extractShowCreate($row, 'Create Table', $schema, $table);
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
