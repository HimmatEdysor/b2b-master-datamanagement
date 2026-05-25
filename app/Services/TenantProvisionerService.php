<?php

namespace App\Services;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantOperationLog;
use App\Models\User;
use App\Support\TenantDbAdmin;
use App\Support\TenantUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class TenantProvisionerService
{
    public function __construct(
        protected TenantResolverService $resolver,
        protected TenantSeedDataService $seedDataService,
        protected TenantDefaultUserService $defaultUserService,
        protected TenantS3FolderService $s3FolderService,
        protected TenantDatabaseUserService $databaseUserService,
        protected MasterActivityLogService $activityLog,
        protected TenantSubscriptionService $subscriptions,
        protected ProvisionTenantQueueService $provisionQueue,
        protected TenantDatabaseSchemaCloneService $schemaClone,
        protected TenantDbAdminCapabilityService $dbCapabilities,
    ) {}

    /**
     * @return array{
     *     database: array{username: string, password: string},
     *     crm: array{email: string, password: string}
     * }|null
     */
    public function canApprove(Tenant $tenant): bool
    {
        if (in_array($tenant->status, ['pending', 'failed', 'provisioning'], true)) {
            return true;
        }

        return $tenant->status === 'active' && ! $tenant->isDatabaseProvisioned();
    }

    public function canResumeAfterClone(Tenant $tenant): bool
    {
        $databaseName = $tenant->database_name;

        if ($databaseName === null || $databaseName === '' || $tenant->isDatabaseProvisioned()) {
            return false;
        }

        return $this->tenantHasProvisionedSchema($databaseName);
    }

    /**
     * @return array{
     *     clone_done: bool,
     *     mysql_user_done: bool,
     *     crm_admin_done: bool,
     *     can_resume: bool,
     *     admin_db_error: string|null
     * }
     */
    public function provisioningProgress(Tenant $tenant): array
    {
        $databaseName = $tenant->database_name;
        $adminDbError = null;
        $cloneDone = false;

        if ($databaseName !== null && $databaseName !== '') {
            $check = $this->evaluateProvisionedSchema($tenant, $databaseName);
            $cloneDone = $check['clone_done'];
            $adminDbError = $check['admin_db_error'];
        }

        $stage = $tenant->provisioning_stage;
        $mysqlUserDone = $tenant->hasDatabaseUsername() && $tenant->hasDatabasePassword();
        $crmAdminDone = $tenant->hasCrmAdminPassword();
        $isQueued = in_array($stage, ['queued', 'running', 'preparing', 'cloning', 'mysql_user', 'seeding', 'crm_admin'], true);
        $isStalled = $this->isProvisioningStalled($tenant);

        $incompleteSteps = [];
        if (! $cloneDone) {
            $incompleteSteps[] = 'clone';
        }
        if (! $mysqlUserDone) {
            $incompleteSteps[] = 'mysql_user';
        }
        if (! $crmAdminDone) {
            $incompleteSteps[] = 'crm_admin';
        }

        $errorMessage = $this->provisionQueue->resolveProvisionError($tenant);

        $stageLabel = $errorMessage !== null && $errorMessage !== ''
            ? 'Failed'
            : ($isStalled
                ? 'Stopped — use Retry below'
                : $this->provisioningStageLabel($stage));

        return [
            'clone_done' => $cloneDone,
            'mysql_user_done' => $mysqlUserDone,
            'crm_admin_done' => $crmAdminDone,
            'can_resume' => $cloneDone && ! $tenant->isDatabaseProvisioned(),
            'stage' => $stage,
            'stage_label' => $stageLabel,
            'is_queued' => $isQueued,
            'is_stalled' => $isStalled,
            'needs_retry' => $this->needsProvisioningRetry($tenant),
            'incomplete_steps' => $incompleteSteps,
            'error_message' => $errorMessage,
            'admin_db_error' => $adminDbError,
        ];
    }

    /**
     * @return array{clone_done: bool, admin_db_error: string|null}
     */
    protected function evaluateProvisionedSchema(Tenant $tenant, string $databaseName): array
    {
        $pdo = $this->pdoForSchemaInspection($tenant);

        if ($pdo === null) {
            $audit = $this->dbCapabilities->audit();
            $failed = collect($audit['checks'])->first(fn (array $c) => ! $c['ok']);
            $detail = is_array($failed) ? ($failed['detail'] ?? '') : '';

            return [
                'clone_done' => $this->inferCloneDoneFromStage($tenant),
                'admin_db_error' => trim($audit['summary'].' '.$detail)
                    .' Run `php artisan tenant:db-admin-check` on the server.',
            ];
        }

        try {
            return [
                'clone_done' => $this->tenantHasProvisionedSchemaOnPdo($pdo, $databaseName),
                'admin_db_error' => null,
            ];
        } catch (\PDOException $e) {
            return [
                'clone_done' => $this->inferCloneDoneFromStage($tenant),
                'admin_db_error' => TenantDbAdmin::connectionErrorMessage($e),
            ];
        }
    }

    protected function inferCloneDoneFromStage(Tenant $tenant): bool
    {
        if ($tenant->isDatabaseProvisioned()) {
            return true;
        }

        return in_array($tenant->provisioning_stage, [
            'mysql_user',
            'seeding',
            'crm_admin',
            'completed',
            'done',
        ], true);
    }

    protected function pdoForSchemaInspection(Tenant $tenant): ?\PDO
    {
        if ($tenant->hasDatabaseUsername() && $tenant->hasDatabasePassword()) {
            $host = (string) ($tenant->database_host ?: TenantDbAdmin::host());
            $port = (int) ($tenant->database_port ?: TenantDbAdmin::port());

            try {
                return new \PDO(
                    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
                    (string) $tenant->database_username,
                    (string) $tenant->database_password,
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
            } catch (\PDOException) {
                // Fall back to platform admin user.
            }
        }

        return TenantDbAdmin::tryAdminPdo();
    }

    protected function tenantHasProvisionedSchemaOnPdo(\PDO $pdo, string $databaseName): bool
    {
        if (! $this->tenantDatabaseExistsOnPdo($pdo, $databaseName)) {
            return false;
        }

        foreach ($this->requiredProvisionTables() as $table) {
            if (! $this->tenantTableExistsOnPdo($pdo, $databaseName, $table)) {
                return false;
            }
        }

        return true;
    }

    public function provisioningStageLabel(?string $stage): string
    {
        return match ($stage) {
            'queued' => 'Queued — waiting for worker',
            'running' => 'Starting…',
            'preparing' => 'Domains & storage',
            'cloning' => 'Cloning database (may take several minutes)',
            'mysql_user' => 'Creating MySQL user',
            'seeding' => 'Seeding reference data',
            'crm_admin' => 'Creating CRM admin login',
            'completed' => 'Complete',
            'failed' => 'Failed',
            default => 'Provisioning',
        };
    }

    public function setProvisioningStage(Tenant $tenant, string $stage): void
    {
        $tenant->update(['provisioning_stage' => $stage]);
    }

    public function isProvisioningQueued(Tenant $tenant): bool
    {
        return $tenant->status === 'provisioning'
            && in_array($tenant->provisioning_stage, ['queued', 'running', 'preparing', 'cloning', 'mysql_user', 'seeding', 'crm_admin'], true);
    }

    /**
     * Job finished, failed, or never ran — company still marked provisioning without DB credentials.
     */
    public function isProvisioningStalled(Tenant $tenant): bool
    {
        if ($tenant->status === 'failed' || $tenant->provisioning_stage === 'failed') {
            return true;
        }

        if ($tenant->status !== 'provisioning') {
            return false;
        }

        if ($this->isProvisioningQueued($tenant)) {
            return false;
        }

        return ! $tenant->isDatabaseProvisioned();
    }

    public function needsProvisioningRetry(Tenant $tenant): bool
    {
        if ($tenant->status === 'failed') {
            return true;
        }

        return $this->isProvisioningStalled($tenant)
            || ($tenant->status === 'pending' && $this->canResumeAfterClone($tenant));
    }

    public function queueProvisioning(Tenant $tenant, ?User $by): void
    {
        if (! $this->canApprove($tenant)) {
            throw new \InvalidArgumentException('This company cannot be provisioned.');
        }

        $this->dbCapabilities->assertReadyForProvisioning();

        $this->provisionQueue->dispatchProvisioning(
            $tenant->id,
            $by?->id,
        );

        $tenant->update([
            'status' => 'provisioning',
            'approved_at' => $tenant->approved_at ?? now(),
            'approved_by' => $tenant->approved_by ?? $by?->id,
            'rejected_at' => null,
            'provision_error' => null,
            'provisioning_stage' => 'queued',
        ]);

        $this->log($tenant, 'approve', 'ok', 'Provisioning queued for background worker', $by);
    }

    /**
     * @return array{database: array{username: string, password: string}, crm: array{email: string, password: string}}|null
     */
    public function pullQueuedProvisionCredentials(int $tenantId): ?array
    {
        $key = 'tenant:'.$tenantId.':provision_credentials';
        $cached = Cache::pull($key);

        return is_array($cached) ? $cached : null;
    }

    /**
     * Finish provisioning when the DB clone already exists (e.g. browser timeout after mysqldump).
     *
     * @return array{
     *     database: array{username: string, password: string},
     *     crm: array{email: string, password: string}
     * }
     */
    public function resumeProvisioning(Tenant $tenant, ?User $by = null): array
    {
        if (! $this->canResumeAfterClone($tenant)) {
            throw new \InvalidArgumentException('Database is not ready to resume. Run full provisioning instead.');
        }

        $tenant->update([
            'status' => 'provisioning',
            'provision_error' => null,
        ]);

        try {
            return $this->completeProvisioning($tenant, $by);
        } catch (\Throwable $e) {
            $message = $this->provisionQueue->normalizeProvisionErrorMessage($e->getMessage());
            $tenant->update([
                'status' => 'failed',
                'provision_error' => $message,
                'provisioning_stage' => 'failed',
            ]);
            $this->log($tenant, 'provision', 'failed', $message, $by);
            throw $e;
        }
    }

    public function approve(Tenant $tenant, ?User $by = null): ?array
    {
        if (! $this->canApprove($tenant)) {
            throw new \InvalidArgumentException('This company cannot be provisioned (use Suspend/Reactivate for other status changes).');
        }

        $this->dbCapabilities->assertReadyForProvisioning();

        $resume = $this->canResumeAfterClone($tenant)
            || $tenant->status === 'provisioning'
            || ($tenant->status === 'active' && ! $tenant->isDatabaseProvisioned());

        $tenant->update([
            'status' => 'provisioning',
            'approved_at' => $tenant->approved_at ?? now(),
            'approved_by' => $tenant->approved_by ?? $by?->id,
            'rejected_at' => null,
            'provision_error' => null,
            'provisioning_stage' => 'running',
        ]);

        $this->log($tenant, 'approve', 'ok', 'Approval started', $by);

        $cloneTimeout = (int) config('master.tenant_db_clone_timeout', 600);
        if (function_exists('set_time_limit')) {
            @set_time_limit($cloneTimeout + 120);
        }

        try {
            $this->setProvisioningStage($tenant, 'preparing');
            $this->ensureDomains($tenant);
            $this->persistTenantDatabaseEndpoint($tenant);
            $s3Folder = $this->s3FolderService->ensureFolderForTenant($tenant);

            $dbName = $tenant->database_name;
            $schemaReady = $this->tenantHasProvisionedSchema($dbName);

            if ($this->tenantDatabaseExists($dbName) && ! $schemaReady) {
                $this->dropTenantDatabase($dbName);
                $this->log(
                    $tenant,
                    'provision',
                    'ok',
                    'Dropped incomplete tenant database (missing required tables) before re-clone',
                    $by
                );
            }

            $skipClone = $resume && $schemaReady;

            if (! $skipClone) {
                $this->setProvisioningStage($tenant, 'cloning');
                $this->cloneDatabase($tenant);
            } else {
                $this->log($tenant, 'provision', 'ok', 'Skipped DB clone (schema already complete)', $by);
            }

            return $this->completeProvisioning($tenant, $by, $s3Folder);
        } catch (\Throwable $e) {
            $message = $this->provisionQueue->normalizeProvisionErrorMessage($e->getMessage());
            $tenant->update([
                'status' => 'failed',
                'provision_error' => $message,
                'provisioning_stage' => 'failed',
            ]);
            $this->log($tenant, 'provision', 'failed', $message, $by);
            throw $e;
        }
    }

    /**
     * @return array{
     *     database: array{username: string, password: string},
     *     crm: array{email: string, password: string}
     * }
     */
    protected function completeProvisioning(Tenant $tenant, ?User $by, ?string $s3Folder = null): array
    {
        $this->setProvisioningStage($tenant, 'mysql_user');
        $dbCredentials = $this->databaseUserService->provisionForTenant($tenant->fresh());

        $tenant->update([
            'database_host' => TenantDbAdmin::host(),
            'database_port' => TenantDbAdmin::port(),
        ]);

        $this->setProvisioningStage($tenant, 'seeding');
        $this->seedDataService->seedFromTemplate($tenant);
        $this->activityLog->database(
            'seed_reference',
            'ok',
            'Reference data seeded from template (config tenant_seed_tables)',
            $tenant,
            $by
        );

        $this->seedDataService->applyCloneCustomization($tenant);

        $this->setProvisioningStage($tenant, 'crm_admin');
        $crmCredentials = $this->defaultUserService->provisionDefaultAdmin($tenant->fresh());

        $tenant->loadMissing('subscriptionPlan');
        $plan = $tenant->subscriptionPlan;
        $billedAt = now()->startOfDay();
        $expiresAt = $plan
            ? $this->subscriptions->expiresAtFromBilling($plan, $billedAt)
            : null;

        if ($plan && $this->subscriptions->planHasNoExpiry($plan)) {
            $billedAt = null;
            $expiresAt = null;
        }

        $tenant->update([
            'status' => 'active',
            'provision_error' => null,
            'provisioning_stage' => 'completed',
            'subscription_status' => $tenant->subscription_status ?: 'active',
            'subscription_billed_at' => $billedAt,
            'subscription_expires_at' => $expiresAt,
        ]);

        $this->resolver->forgetHostCache($tenant);
        $this->log(
            $tenant,
            'provision',
            'ok',
            'Schema clone + reference seed (tenant_seed_tables)'
                .', dedicated MySQL user ('.$dbCredentials['username'].'), S3 folder ('.($s3Folder ?? $tenant->slug).'), domains, and default CRM admin ready',
            $by
        );

        return [
            'database' => $dbCredentials,
            'crm' => $crmCredentials,
        ];
    }

    public function reject(Tenant $tenant, ?User $by = null, ?string $reason = null): void
    {
        if (! in_array($tenant->status, ['pending', 'failed'], true)) {
            throw new \InvalidArgumentException('Only pending or failed companies can be rejected.');
        }

        $tenant->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'registration_notes' => trim(($tenant->registration_notes ?? '')."\nRejected: ".($reason ?? '')),
        ]);

        $this->log($tenant, 'reject', 'ok', $reason ?? 'Registration rejected', $by);
    }

    public function ensureDomains(Tenant $tenant): void
    {
        $subdomain = TenantUrl::subdomainHost($tenant->slug);

        $atHost = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('host', $subdomain)
            ->first();

        if ($atHost) {
            $atHost->update([
                'type' => 'subdomain',
                'is_primary' => true,
            ]);
            $this->activityLog->domain('ensure_subdomain', 'ok', "Primary subdomain set to {$subdomain}", $tenant);
        } elseif (TenantDomain::query()->where('host', $subdomain)->exists()) {
            return;
        } else {
            TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'host' => $subdomain,
                'type' => 'subdomain',
                'is_primary' => true,
            ]);
            $this->activityLog->domain('ensure_subdomain', 'ok', "Created primary subdomain {$subdomain}", $tenant);
        }

        app(TenantDomainDnsService::class)->provisionAllForTenant($tenant->fresh(['domains']));

        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'subdomain')
            ->where('host', '!=', $subdomain)
            ->each(function (TenantDomain $domain) use ($tenant) {
                cache()->forget('tenant:host:'.$domain->host);
                $this->activityLog->domain('remove_stale_subdomain', 'ok', "Removed stale subdomain {$domain->host}", $tenant);
                $domain->delete();
            });
    }

    protected function tenantDatabaseExists(string $databaseName): bool
    {
        if ($databaseName === '') {
            return false;
        }

        return $this->tenantDatabaseExistsOnPdo($this->adminPdo(), $databaseName);
    }

    protected function tenantDatabaseExistsOnPdo(\PDO $pdo, string $databaseName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1'
        );
        $stmt->execute([$databaseName]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * True when the tenant database exists and has core CRM tables (e.g. users).
     */
    protected function tenantHasProvisionedSchema(string $databaseName): bool
    {
        return $this->tenantHasProvisionedSchemaOnPdo($this->adminPdo(), $databaseName);
    }

    /**
     * @return list<string>
     */
    protected function requiredProvisionTables(): array
    {
        $tables = config('master.tenant_provision_required_tables', ['users']);

        return array_values(array_filter(
            is_array($tables) ? $tables : ['users'],
            fn ($t) => is_string($t) && $t !== ''
        ));
    }

    protected function tenantTableExists(string $databaseName, string $table): bool
    {
        return $this->tenantTableExistsOnPdo($this->adminPdo(), $databaseName, $table);
    }

    protected function tenantTableExistsOnPdo(\PDO $pdo, string $databaseName, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
        );
        $stmt->execute([$databaseName, $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    protected function dropTenantDatabase(string $databaseName): void
    {
        if ($databaseName === '') {
            return;
        }

        TenantDbAdmin::assertCanProvision();

        $quoted = '`'.str_replace('`', '``', $databaseName).'`';
        $this->adminPdo()->exec("DROP DATABASE IF EXISTS {$quoted}");
    }

    protected function adminPdo(): \PDO
    {
        return TenantDbAdmin::adminPdo();
    }

    public function cloneDatabase(Tenant $tenant): void
    {
        TenantDbAdmin::assertCanProvision();

        $from = config('master.template_database');
        $to = $tenant->database_name;

        if (TenantDbAdmin::cloneMethod() === 'mysqldump') {
            $this->cloneDatabaseWithMysqldump($tenant, $from, $to);

            return;
        }

        $this->cloneDatabaseWithPdo($tenant, $from, $to);
    }

    protected function cloneDatabaseWithPdo(Tenant $tenant, string $from, string $to): void
    {
        try {
            $stats = $this->schemaClone->provisionTenantDatabase($from, $to);
        } catch (\Throwable $e) {
            $this->activityLog->database(
                'clone_database',
                'failed',
                $e->getMessage(),
                $tenant,
                null,
                ['method' => 'pdo', 'from' => $from, 'to' => $to]
            );

            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        $adminUser = TenantDbAdmin::username();
        $grantNote = ($stats['admin_granted'] ?? false)
            ? "Granted `{$adminUser}` on `{$to}`"
            : 'Skipped admin GRANT (tenant_db_grant_admin_on_create=false)';

        $this->activityLog->database(
            'create_tenant_database',
            'ok',
            "Created database `{$to}`; {$grantNote}",
            $tenant,
            null,
            ['method' => 'pdo', 'to' => $to, 'admin' => $adminUser]
        );

        $this->activityLog->database(
            'clone_database',
            'ok',
            "Cloned schema from {$from} to {$to} (PDO: create → grant → tables)",
            $tenant,
            null,
            ['method' => 'pdo', 'from' => $from, 'to' => $to, ...$stats]
        );
    }

    protected function cloneDatabaseWithMysqldump(Tenant $tenant, string $from, string $to): void
    {
        $mysqlEnv = TenantDbAdmin::mysqlCliEnv();
        $timeout = (float) config('master.tenant_db_clone_timeout', 600);

        $pdo = TenantDbAdmin::adminPdo();
        TenantDbAdmin::createTenantDatabase($pdo, $to);
        TenantDbAdmin::grantAndVerifyAdminAccessToTenantDatabase($pdo, $to);

        $this->activityLog->database(
            'create_tenant_database',
            'ok',
            'Created `'.$to.'` and granted `'.TenantDbAdmin::username().'`',
            $tenant,
            null,
            ['method' => 'mysqldump', 'to' => $to]
        );

        $dumpCommand = TenantDbAdmin::mysqldumpCommand($from, schemaOnly: true);

        $dump = Process::timeout($timeout)
            ->env($mysqlEnv)
            ->run($dumpCommand);

        if (! $dump->successful()) {
            $err = TenantDbAdmin::cliFailureMessage($dump, 'mysqldump');
            if (str_contains($err, 'timeout') || str_contains($err, 'exceeded')) {
                $err .= ' Increase TENANT_DB_CLONE_TIMEOUT in .env (default 3000 seconds).';
            }

            $this->activityLog->database(
                'clone_database',
                'failed',
                $err,
                $tenant,
                null,
                [
                    'method' => 'mysqldump',
                    'command' => TenantDbAdmin::mysqldumpCommandForLog($from, schemaOnly: true),
                    'build' => TenantDbAdmin::MYSQLDUMP_RDS_BUILD,
                    'exit_code' => $dump->exitCode(),
                    'from' => $from,
                    'to' => $to,
                ]
            );

            throw new \RuntimeException($err);
        }

        if (trim($dump->output()) === '') {
            throw new \RuntimeException(
                'mysqldump succeeded but produced empty output. Check template database name ('
                .config('master.template_database').') or set TENANT_DB_CLONE_METHOD=pdo for RDS.'
            );
        }

        $import = Process::timeout($timeout)
            ->env($mysqlEnv)
            ->input($dump->output())
            ->run([
                ...TenantDbAdmin::mysqlCommand('--init-command=SET SESSION FOREIGN_KEY_CHECKS=0;', $to),
            ]);

        if (! $import->successful()) {
            $this->activityLog->database(
                'clone_database',
                'failed',
                trim($import->errorOutput() ?: 'Import failed'),
                $tenant,
                null,
                ['method' => 'mysqldump', 'from' => $from, 'to' => $to]
            );
            throw new \RuntimeException($import->errorOutput());
        }

        $this->activityLog->database(
            'clone_database',
            'ok',
            "Cloned schema from {$from} to {$to} (mysqldump)",
            $tenant,
            null,
            ['method' => 'mysqldump', 'from' => $from, 'to' => $to]
        );
    }

    /**
     * RDS-safe GRANT on new tenant DB to TENANT_DB_USERNAME (mysqldump path).
     */
    protected function grantAdminOnTenantDatabaseViaCli(
        Tenant $tenant,
        string $databaseName,
        array $mysqlEnv,
        float $timeout
    ): void {
        $user = TenantDbAdmin::username();
        $grants = [
            ...TenantDbAdmin::grantStatementsForAdminOnDatabase($databaseName),
            'FLUSH PRIVILEGES',
        ];

        $result = Process::timeout($timeout)
            ->env($mysqlEnv)
            ->run([
                ...TenantDbAdmin::mysqlCommand('-e', implode('; ', $grants)),
            ]);

        if (! $result->successful()) {
            $msg = trim($result->errorOutput() ?: $result->output()) ?: 'GRANT failed';
            $this->activityLog->database('grant_admin_on_tenant_db', 'failed', $msg, $tenant, null, ['database' => $databaseName]);
            throw new \RuntimeException(
                'Could not grant '.TenantDbAdmin::username()." on `{$databaseName}`: {$msg}. "
                .'Run `php artisan tenant:db-admin-check` for RDS setup SQL (specific privileges, not ALL).'
            );
        }

        $this->activityLog->database(
            'grant_admin_on_tenant_db',
            'ok',
            'Granted '.$user.' on `'.$databaseName.'`',
            $tenant,
            null,
            ['database' => $databaseName]
        );
    }

    public function reserveDatabaseName(string $slug): string
    {
        return TenantDbAdmin::tenantDatabaseNameFromSlug($slug);
    }

    /**
     * Snapshot RDS host/port onto the tenant row (CRM reads from DB, not .env).
     */
    protected function persistTenantDatabaseEndpoint(Tenant $tenant): void
    {
        $tenant->update([
            'database_host' => (string) config('master.tenant_db_host'),
            'database_port' => (int) config('master.tenant_db_port'),
        ]);
    }

    protected function log(Tenant $tenant, string $action, string $status, ?string $message, ?User $by): void
    {
        TenantOperationLog::create([
            'tenant_id' => $tenant->id,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'user_id' => $by?->id,
        ]);

        $this->activityLog->database(
            $action,
            $status,
            $message ?? '',
            $tenant,
            $by
        );
    }
}
