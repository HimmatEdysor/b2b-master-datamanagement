<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantOperationLog;
use App\Models\User;
use App\Support\TenantDbAdmin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Process\Process;

class TenantCrmMigrateService
{
    public function __construct(
        protected TenantResolverService $resolver,
        protected MasterActivityLogService $activityLog,
    ) {}

    /**
     * Companies with a database, for master UI / B2B CRM bulk migrate (fresh read from master DB).
     *
     * @return array{
     *     tenants: list<array<string, mixed>>,
     *     total: int,
     *     returned: int,
     *     capped: bool
     * }
     */
    public function migrationQueue(?string $slug = null): array
    {
        $maxTenants = max(1, (int) config('master.tenant_crm_migrate_bulk_max_tenants', 500));

        $query = Tenant::query()
            ->with(['domains', 'subscriptionPlan'])
            ->whereNotNull('database_name')
            ->where('database_name', '!=', '')
            ->orderBy('id');

        if ($slug !== null && $slug !== '') {
            $query->where('slug', $slug);
        }

        $total = (int) (clone $query)->count();
        $tenants = $query->limit($maxTenants)->get();

        $ready = $tenants->filter(fn (Tenant $t) => $t->hasMigratableDatabase());

        return [
            'tenants' => $ready->map(fn (Tenant $t) => $this->resolver->toMigrationQueueItem($t))->values()->all(),
            'total' => $total,
            'returned' => $ready->count(),
            'skipped_not_provisioned' => $tenants->count() - $ready->count(),
            'capped' => $total > $tenants->count(),
        ];
    }

    /**
     * Run B2B CRM Laravel migrations on every company database (one subprocess per tenant).
     *
     * @return array{
     *     results: list<array{id: int, name: string, slug: string, database_name: string|null, ok: bool, message: string}>,
     *     ok_count: int,
     *     fail_count: int,
     *     total_eligible: int,
     *     run_count: int
     * }
     */
    public function migrateAll(?User $by = null, ?string $slug = null): array
    {
        $maxTenants = max(1, (int) config('master.tenant_crm_migrate_bulk_max_tenants', 500));

        $eligibleQuery = Tenant::query()
            ->whereNotNull('database_name')
            ->where('database_name', '!=', '');

        if ($slug !== null && $slug !== '') {
            $eligibleQuery->where('slug', $slug);
        }

        $totalEligible = (int) $eligibleQuery->count();

        if (TenantDbAdmin::usesSharedTenantCredentials()) {
            $this->syncSharedCredentialsOnTenants((clone $eligibleQuery)->pluck('id')->all());
        }

        $tenants = (clone $eligibleQuery)
            ->orderBy('id')
            ->limit($maxTenants)
            ->get()
            ->filter(fn (Tenant $t) => $t->hasMigratableDatabase());

        $results = [];
        $okCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            $run = $this->migrate($tenant, $by);
            if ($run['ok']) {
                $okCount++;
            } else {
                $failCount++;
            }
            $results[] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database_name' => $tenant->database_name,
                'ok' => $run['ok'],
                'message' => $run['message'],
            ];
        }

        return [
            'results' => $results,
            'ok_count' => $okCount,
            'fail_count' => $failCount,
            'total_eligible' => $totalEligible,
            'run_count' => $tenants->count(),
        ];
    }

    /**
     * Run `php artisan migrate --force` in the tenant CRM codebase against the tenant's MySQL database.
     *
     * @return array{ok: bool, message: string, output: string, persisted?: bool}
     */
    public function migrate(Tenant $tenant, ?User $by = null): array
    {
        $crmPath = config('master.tenant_crm_path');

        if (! is_string($crmPath) || $crmPath === '' || ! is_dir($crmPath)) {
            return [
                'ok' => false,
                'message' => 'Set TENANT_CRM_PATH in .env to the tenant CRM Laravel project root (the folder that contains artisan).',
                'output' => '',
                'persisted' => false,
            ];
        }

        $artisan = $crmPath.DIRECTORY_SEPARATOR.'artisan';
        if (! is_file($artisan)) {
            return [
                'ok' => false,
                'message' => 'TENANT_CRM_PATH does not contain an artisan file.',
                'output' => '',
                'persisted' => false,
            ];
        }

        if ($tenant->database_name === null || $tenant->database_name === '') {
            return [
                'ok' => false,
                'message' => 'This company has no tenant database configured yet.',
                'output' => '',
                'persisted' => false,
            ];
        }

        $php = config('master.tenant_crm_php_binary');
        if (is_string($php) && $php !== '') {
            $phpBinary = $php;
        } else {
            $phpBinary = PHP_BINARY;
            if (str_contains($phpBinary, 'php-fpm')) {
                $candidate = str_replace('php-fpm', 'php', $phpBinary);
                if (is_executable($candidate)) {
                    $phpBinary = $candidate;
                } else {
                    $candidate2 = str_replace(['/sbin/php-fpm', '/sbin/php'], ['/bin/php', '/bin/php'], $phpBinary);
                    if (is_executable($candidate2)) {
                        $phpBinary = $candidate2;
                    } else {
                        $phpBinary = 'php';
                    }
                }
            }
        }

        $connection = $this->resolveMigrateConnection($tenant);
        if ($connection === null) {
            return [
                'ok' => false,
                'message' => $this->migrateConnectionErrorMessage($tenant),
                'output' => '',
                'persisted' => false,
            ];
        }

        $db = $connection;
        $dbEnv = [
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
        ];
        if (! empty($db['unix_socket'])) {
            $dbEnv['DB_SOCKET'] = (string) $db['unix_socket'];
        }

        $timeout = (float) config('master.tenant_crm_migrate_timeout', 600);

        try {
            $this->syncClonedDatabaseMigrationBaseline($crmPath, $db);
        } catch (\Throwable $e) {
            Log::warning('Could not sync migration baseline for cloned tenant DB', [
                'tenant_id' => $tenant->id,
                'database' => $db['database'],
                'error' => $e->getMessage(),
            ]);
        }

        $output = '';
        $maxAttempts = (int) config('master.tenant_crm_migrate_max_skips', 200);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $process = new Process(
                [$phpBinary, 'artisan', 'migrate', '--force'],
                $crmPath,
                $this->subprocessEnvironment($dbEnv),
                null,
                $timeout
            );
            $process->run();

            $output = trim($process->getOutput()."\n".$process->getErrorOutput());

            if ($process->isSuccessful()) {
                $this->persistSuccess($tenant, $output, $by);

                return [
                    'ok' => true,
                    'message' => 'Migrations finished successfully.',
                    'output' => Str::limit($output, 4000),
                    'persisted' => true,
                ];
            }

            $migration = $this->extractSkippableMigrationName($crmPath, $output, $db);
            if ($migration === null) {
                break;
            }

            $this->recordMigrationAsRan($db, $migration);
        }

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());
        $err = $stderr !== ''
            ? $stderr
            : ($stdout !== '' ? $stdout : 'Migration command exited with code '.$process->getExitCode().'.');
        $err = $this->friendlyMigrateError($err);
        $this->persistFailure($tenant, $err, $output, $by);

        return [
            'ok' => false,
            'message' => Str::limit($err, 500),
            'output' => Str::limit($output, 4000),
            'persisted' => true,
        ];

    }

    /**
     * Symfony Process no longer supports inheritEnvironmentVariables(); pass a full env map
     * so the child PHP/artisan process keeps PATH, HOME, etc., while DB_* is overridden per tenant.
     *
     * Master portal Redis settings must not leak: this host uses REDIS_CLIENT=predis, but the
     * tenant CRM may not have predis/predis installed (Predis\Client not found on migrate).
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function subprocessEnvironment(array $overrides): array
    {
        $env = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }
            $env[$key] = (string) $value;
        }

        foreach ([
            'REDIS_CLIENT',
            'CACHE_STORE',
            'CACHE_DRIVER',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
            'BROADCAST_CONNECTION',
        ] as $key) {
            unset($env[$key]);
        }

        $env = array_merge($env, [
            'CACHE_STORE' => 'array',
            'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'BROADCAST_CONNECTION' => 'null',
        ], $overrides);

        return $env;
    }

    protected function persistSuccess(Tenant $tenant, string $output, ?User $by): void
    {
        $tenant->update([
            'migration_status' => 'ok',
            'migration_error' => null,
            'last_migration_at' => now(),
        ]);

        TenantOperationLog::create([
            'tenant_id' => $tenant->id,
            'action' => 'crm_migrate',
            'status' => 'ok',
            'message' => 'php artisan migrate --force completed',
            'user_id' => $by?->id,
            'meta' => ['output_excerpt' => Str::limit($output, 2000)],
        ]);

        $this->activityLog->database(
            'crm_migrate',
            'ok',
            'php artisan migrate --force completed',
            $tenant,
            $by,
            ['database' => $tenant->database_name]
        );
    }

    protected function persistFailure(Tenant $tenant, string $error, string $output, ?User $by): void
    {
        $tenant->update([
            'migration_status' => 'failed',
            'migration_error' => Str::limit($error, 8000),
            'last_migration_at' => now(),
        ]);

        TenantOperationLog::create([
            'tenant_id' => $tenant->id,
            'action' => 'crm_migrate',
            'status' => 'failed',
            'message' => Str::limit($error, 2000),
            'user_id' => $by?->id,
            'meta' => ['output_excerpt' => Str::limit($output, 2000)],
        ]);

        $this->activityLog->database(
            'crm_migrate',
            'failed',
            Str::limit($error, 500),
            $tenant,
            $by,
            ['database' => $tenant->database_name]
        );
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string, unix_socket?: string}|null
     */
    protected function resolveMigrateConnection(Tenant $tenant): ?array
    {
        if (! $tenant->hasMigratableDatabase()) {
            return null;
        }

        if (TenantDbAdmin::usesSharedTenantCredentials()) {
            $this->syncSharedCredentialsOnTenant($tenant);

            return TenantDbAdmin::tenantConnectionConfig($tenant->fresh());
        }

        if (! $tenant->isDatabaseProvisioned()) {
            return null;
        }

        return $tenant->connectionConfig();
    }

    protected function migrateConnectionErrorMessage(Tenant $tenant): string
    {
        if ($tenant->database_name === null || $tenant->database_name === '') {
            return 'This company has no tenant database configured yet. Run provisioning first.';
        }

        if (TenantDbAdmin::usesSharedTenantCredentials()) {
            return 'MySQL database `'.$tenant->database_name.'` was not found. '
                .'Finish provisioning (clone from template) or create the database, then retry. '
                .'Shared user: `'.TenantDbAdmin::username().'`.';
        }

        return 'This company has no database connection stored (host, username, password). '
            .'Approve provisioning or edit the company record.';
    }

    protected function friendlyMigrateError(string $err): string
    {
        if ($this->isCloneSchemaConflict($err)) {
            return 'Schema from the template clone already matches pending CRM migrations (tables/columns/indexes). '
                .'The master portal marks those migrations as run automatically; if this persists, check CRM migrations. '
                .'Raw: '.Str::limit($err, 400);
        }

        return $err;
    }

    protected function isCloneSchemaConflict(string $output): bool
    {
        return str_contains($output, '1050')
            || str_contains($output, '1054')
            || str_contains($output, '1060')
            || str_contains($output, '1061')
            || str_contains($output, '1091')
            || str_contains($output, '42S01')
            || str_contains($output, '42S21')
            || str_contains($output, '42S22')
            || str_contains($output, 'Duplicate column')
            || str_contains($output, 'Duplicate key')
            || str_contains($output, 'Unknown column')
            || str_contains($output, "Can't DROP")
            || str_contains($output, 'check that it exists')
            || (str_contains($output, 'already exists') && (
                str_contains($output, 'Table ')
                || str_contains($output, 'Column ')
                || str_contains($output, 'Key ')
            ));
    }

    /**
     * Template clone copies tables; mark matching CRM migrations as already run.
     *
     * @param  array{host: string, port: int, database: string, username: string, password: string, unix_socket?: string}  $db
     */
    protected function syncClonedDatabaseMigrationBaseline(string $crmPath, array $db): void
    {
        $dir = $crmPath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($dir)) {
            return;
        }

        $pdo = $this->tenantPdo($db);
        $this->ensureMigrationsTable($pdo);
        $batch = $this->nextMigrationBatch($pdo);
        $database = $db['database'];

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if ($this->migrationRecorded($pdo, $name)) {
                continue;
            }

            if ($this->shouldBaselineMigrationAsRan($file, $name, $pdo, $database)) {
                $this->insertMigration($pdo, $name, $batch);
            }
        }
    }

    protected function shouldBaselineMigrationAsRan(string $file, string $name, PDO $pdo, string $database): bool
    {
        if (preg_match('/_create_(.+)_table$/', $name, $matches)) {
            return $this->tableExistsOnTenant($pdo, $database, $matches[1]);
        }

        $content = @file_get_contents($file);

        return is_string($content) && $content !== ''
            && $this->alterMigrationAlreadyApplied($content, $pdo, $database);
    }

    protected function alterMigrationAlreadyApplied(string $content, PDO $pdo, string $database): bool
    {
        if (! preg_match("/Schema::table\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $tableMatch)) {
            return false;
        }

        $table = $tableMatch[1];
        if (! $this->tableExistsOnTenant($pdo, $database, $table)) {
            return false;
        }

        $upBody = $this->extractUpMethodBody($content);
        $drops = $this->parseDropColumns($upBody);
        $adds = $this->parseAddColumns($upBody);
        $changes = $this->parseChangeColumns($upBody);
        $renames = $this->parseRenameColumns($upBody);

        if ($drops === [] && $adds === [] && $changes === [] && $renames === []) {
            return false;
        }

        foreach ($renames as $rename) {
            if (! $this->renameAlreadyAppliedOnClone($rename, $pdo, $database, $table)) {
                return false;
            }
        }

        if ($changes !== []) {
            if ($this->changeMigrationSupersededByClone($changes, $pdo, $database, $table)) {
                // Template schema moved past these ->change() targets.
            } else {
                foreach ($changes as $column) {
                    if (! $this->columnExistsOnTenant($pdo, $database, $table, $column)) {
                        return false;
                    }
                }
            }
        }

        foreach ($drops as $column) {
            if ($this->columnExistsOnTenant($pdo, $database, $table, $column)) {
                return false;
            }
        }

        foreach ($adds as $column) {
            if (! $this->columnExistsOnTenant($pdo, $database, $table, $column)) {
                return false;
            }
        }

        return $drops !== [] || $adds !== [] || $changes !== [] || $renames !== [];
    }

    /**
     * @param  array{from: string, to: string}  $rename
     */
    protected function renameAlreadyAppliedOnClone(array $rename, PDO $pdo, string $database, string $table): bool
    {
        return ! $this->columnExistsOnTenant($pdo, $database, $table, $rename['from'])
            && $this->columnExistsOnTenant($pdo, $database, $table, $rename['to']);
    }

    /**
     * Template DB renamed/removed columns that a ->change() migration still references.
     *
     * @param  list<string>  $columns
     */
    protected function changeMigrationSupersededByClone(
        array $columns,
        PDO $pdo,
        string $database,
        string $table,
    ): bool {
        foreach ($columns as $column) {
            if (! $this->columnExistsOnTenant($pdo, $database, $table, $column)) {
                return true;
            }
        }

        return false;
    }

    protected function extractUpMethodBody(string $content): string
    {
        if (! preg_match('/function\s+up\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/s', $content, $match, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $length = strlen($content);

        for ($i = $start; $i < $length; $i++) {
            $char = $content[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, $i - $start);
                }
            }
        }

        return $content;
    }

    /**
     * @return list<string>
     */
    protected function parseDropColumns(string $content): array
    {
        $drops = [];

        if (preg_match_all('/dropColumn\(\s*\[\s*([^\]]+)\]/', $content, $lists)) {
            foreach ($lists[1] as $list) {
                if (preg_match_all('/[\'"](\w+)[\'"]/', $list, $cols)) {
                    foreach ($cols[1] as $column) {
                        $drops[] = $column;
                    }
                }
            }
        }

        if (preg_match_all('/dropColumn\(\s*[\'"](\w+)[\'"]/', $content, $single)) {
            foreach ($single[1] as $column) {
                $drops[] = $column;
            }
        }

        return array_values(array_unique($drops));
    }

    /**
     * @return list<string>
     */
    protected function parseAddColumns(string $content): array
    {
        if (! preg_match_all(
            '/\$table->(?!dropColumn|renameColumn)[a-zA-Z_]+\(\s*[\'"](\w+)[\'"]/',
            $content,
            $matches
        )) {
            return [];
        }

        $changeColumns = $this->parseChangeColumns($content);

        return array_values(array_diff(array_unique($matches[1]), $changeColumns));
    }

    /**
     * @return list<string>
     */
    protected function parseChangeColumns(string $content): array
    {
        if (! preg_match_all('/\$table->\w+\(\s*[\'"](\w+)[\'"]\)[^;]*->change\(\)/', $content, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    protected function parseRenameColumns(string $content): array
    {
        if (! preg_match_all(
            '/renameColumn\(\s*[\'"](\w+)[\'"]\s*,\s*[\'"](\w+)[\'"]\s*\)/',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $renames = [];
        foreach ($matches as $match) {
            $renames[] = ['from' => $match[1], 'to' => $match[2]];
        }

        return $renames;
    }

    /**
     * @param  array{host: string, port: int, database: string, username: string, password: string, unix_socket?: string}  $db
     */
    protected function recordMigrationAsRan(array $db, string $migrationName): void
    {
        $pdo = $this->tenantPdo($db);
        $this->ensureMigrationsTable($pdo);

        if ($this->migrationRecorded($pdo, $migrationName)) {
            return;
        }

        $this->insertMigration($pdo, $migrationName, $this->nextMigrationBatch($pdo));
    }

    /**
     * @param  array{host: string, port: int, database: string, username: string, password: string, unix_socket?: string}  $db
     */
    protected function extractSkippableMigrationName(string $crmPath, string $output, array $db): ?string
    {
        $migration = $this->extractRunningMigrationName($output);

        if ($migration !== null && $this->isCloneSchemaConflict($output)) {
            return $migration;
        }

        if ($migration !== null && $this->isRenameGrammarError($output)) {
            $file = $crmPath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'
                .DIRECTORY_SEPARATOR.$migration.'.php';

            if (is_file($file)) {
                $pdo = $this->tenantPdo($db);

                if ($this->shouldBaselineMigrationAsRan($file, $migration, $pdo, $db['database'])) {
                    return $migration;
                }
            }
        }

        if ($this->isCloneSchemaConflict($output)
            && preg_match("/Table '([^']+)' already exists/", $output, $matches)) {
            return $this->findMigrationNameForTable($crmPath, $matches[1]);
        }

        return null;
    }

    protected function extractRunningMigrationName(string $output): ?string
    {
        if (preg_match('/Running migrations\.\s+(\d{4}_\d{2}_\d{2}_\d{6}_[^\s]+)/', $output, $matches)) {
            return $matches[1];
        }

        if (preg_match('/Running migrations\.\s+(\S+)\s+\.\.\./', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function isRenameGrammarError(string $output): bool
    {
        return str_contains($output, 'Trying to access array offset on null')
            && (str_contains($output, 'MySqlGrammar') || str_contains($output, 'renameColumn'));
    }

    protected function findMigrationNameForTable(string $crmPath, string $table): ?string
    {
        $dir = $crmPath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($dir)) {
            return null;
        }

        $needle = '_create_'.$table.'_table';

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (str_ends_with($name, $needle)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array{host: string, port: int, database: string, username: string, password: string, unix_socket?: string}  $db
     */
    protected function tenantPdo(array $db): PDO
    {
        $socket = (string) ($db['unix_socket'] ?? '');
        if ($socket !== '') {
            $dsn = 'mysql:unix_socket='.str_replace([';', ' '], '', $socket)
                .';dbname='.$db['database'].';charset=utf8mb4';
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $db['host'],
                $db['port'],
                $db['database']
            );
        }

        return new PDO(
            $dsn,
            $db['username'],
            $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    protected function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL
            )'
        );
    }

    protected function migrationRecorded(PDO $pdo, string $migrationName): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
        $stmt->execute([$migrationName]);

        return (int) $stmt->fetchColumn() > 0;
    }

    protected function nextMigrationBatch(PDO $pdo): int
    {
        $max = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();

        return $max > 0 ? $max : 1;
    }

    protected function insertMigration(PDO $pdo, string $migrationName, int $batch): void
    {
        $stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $stmt->execute([$migrationName, $batch]);
    }

    protected function tableExistsOnTenant(PDO $pdo, string $database, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
        );
        $stmt->execute([$database, $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    protected function columnExistsOnTenant(PDO $pdo, string $database, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$database, $table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param  list<int>  $tenantIds
     */
    protected function syncSharedCredentialsOnTenants(array $tenantIds): void
    {
        if (! TenantDbAdmin::usesSharedTenantCredentials() || $tenantIds === []) {
            return;
        }

        Tenant::query()
            ->whereIn('id', $tenantIds)
            ->each(fn (Tenant $t) => $this->syncSharedCredentialsOnTenant($t));
    }

    protected function syncSharedCredentialsOnTenant(Tenant $tenant): void
    {
        if (! TenantDbAdmin::usesSharedTenantCredentials()) {
            return;
        }

        $tenant->update([
            'database_username' => TenantDbAdmin::username(),
            'database_password' => TenantDbAdmin::password(),
            'database_host' => $tenant->database_host ?: TenantDbAdmin::host(),
            'database_port' => $tenant->database_port ?: TenantDbAdmin::port(),
        ]);
    }
}
