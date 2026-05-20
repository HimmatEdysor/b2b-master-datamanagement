<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantOperationLog;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TenantCrmMigrateService
{
    public function __construct(
        protected TenantResolverService $resolver
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

        return [
            'tenants' => $tenants->map(fn (Tenant $t) => $this->resolver->toMigrationQueueItem($t))->values()->all(),
            'total' => $total,
            'returned' => $tenants->count(),
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

        $tenants = (clone $eligibleQuery)
            ->orderBy('id')
            ->limit($maxTenants)
            ->get();

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
        $phpBinary = (is_string($php) && $php !== '') ? $php : PHP_BINARY;

        $dbEnv = [
            'DB_HOST' => $tenant->databaseHost(),
            'DB_PORT' => (string) ($tenant->database_port ?: config('master.tenant_db_port')),
            'DB_DATABASE' => $tenant->database_name,
            'DB_USERNAME' => $tenant->databaseUsername(),
            'DB_PASSWORD' => $tenant->databasePassword(),
        ];

        $timeout = (float) config('master.tenant_crm_migrate_timeout', 600);

        $process = new Process(
            [$phpBinary, 'artisan', 'migrate', '--force'],
            $crmPath,
            $this->subprocessEnvironment($dbEnv),
            null,
            $timeout
        );
        $process->run();

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $err = $stderr !== ''
                ? $stderr
                : ($stdout !== '' ? $stdout : 'Migration command exited with code '.$process->getExitCode().'.');
            $this->persistFailure($tenant, $err, $output, $by);

            return [
                'ok' => false,
                'message' => Str::limit($err, 500),
                'output' => Str::limit($output, 4000),
                'persisted' => true,
            ];
        }

        $this->persistSuccess($tenant, $output, $by);

        return [
            'ok' => true,
            'message' => 'Migrations finished successfully.',
            'output' => Str::limit($output, 4000),
            'persisted' => true,
        ];
    }

    /**
     * Symfony Process no longer supports inheritEnvironmentVariables(); pass a full env map
     * so the child PHP/artisan process keeps PATH, HOME, etc., while DB_* is overridden per tenant.
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

        foreach ($overrides as $key => $value) {
            $env[(string) $key] = (string) $value;
        }

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
    }
}
