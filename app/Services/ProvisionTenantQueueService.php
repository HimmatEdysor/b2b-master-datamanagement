<?php

namespace App\Services;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProvisionTenantQueueService
{
    /**
     * Push a fresh job and confirm it reached Redis (fixes "queued" UI with empty Horizon).
     */
    public function dispatchProvisioning(int $tenantId, ?int $approvedByUserId): void
    {
        if (config('queue.default') !== 'redis') {
            ProvisionTenantJob::dispatch($tenantId, $approvedByUserId);

            return;
        }

        $this->prepareFreshDispatch($tenantId);

        $queue = $this->queueName();
        $before = $this->pendingJobCount($queue);

        ProvisionTenantJob::dispatch($tenantId, $approvedByUserId);

        usleep(100_000);

        $after = $this->pendingJobCount($queue);

        if ($after <= $before && ! $this->hasJobPayloadForTenant($queue, $tenantId)) {
            throw new \RuntimeException(
                'Job was not added to the Redis queue. Run `php artisan horizon` and try again, or use **Provision now** for instant local provisioning.'
            );
        }
    }

    /**
     * Remove stale Redis queue payloads for this tenant.
     */
    public function prepareFreshDispatch(int $tenantId): void
    {
        $this->purgeRedisQueueJobsForTenant($tenantId);
    }

    public function hasJobPayloadForTenant(string $queue, int $tenantId): bool
    {
        if (config('queue.default') !== 'redis') {
            return false;
        }

        $redis = Redis::connection(config('queue.connections.redis.connection', 'default'));

        foreach (['queues:'.$queue, 'queues:'.$queue.':reserved'] as $key) {
            if (! $redis->exists($key)) {
                continue;
            }

            $items = $redis->type($key) === 'list'
                ? $redis->lrange($key, 0, -1)
                : [];

            foreach ($items as $payload) {
                if ($this->payloadMatchesTenant($payload, $tenantId)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function pendingJobCount(string $queue): int
    {
        if (config('queue.default') !== 'redis') {
            return 0;
        }

        $redis = Redis::connection(config('queue.connections.redis.connection', 'default'));

        return (int) $redis->llen('queues:'.$queue)
            + (int) $redis->zcard('queues:'.$queue.':delayed');
    }

    public function queueName(): string
    {
        return (string) config('master.tenant_provision_queue', 'provisioning');
    }

    /**
     * UI said "queued" but Redis has no job (unique lock / Horizon stopped).
     */
    public function clearStuckQueuedState(\App\Models\Tenant $tenant): bool
    {
        if ($tenant->provisioning_stage !== 'queued') {
            return false;
        }

        if ($this->hasJobPayloadForTenant($this->queueName(), $tenant->id)) {
            return false;
        }

        $tenant->update([
            'provisioning_stage' => null,
            'provision_error' => $tenant->provision_error
                ?: 'Previous queue job was lost (Horizon stopped or job never queued). Click Retry or Provision now.',
        ]);

        if ($tenant->status === 'provisioning' && ! $tenant->isDatabaseProvisioned()) {
            $tenant->update(['status' => 'failed']);
        }

        return true;
    }

    /**
     * Best error message for the admin UI (tenant row or latest failed queue job).
     */
    public function resolveProvisionError(Tenant $tenant): ?string
    {
        if (app(TenantProvisionerService::class)->isProvisionWorkflowComplete($tenant)) {
            return null;
        }

        if ($tenant->provision_error !== null && $tenant->provision_error !== '') {
            return $this->normalizeProvisionErrorMessage($tenant->provision_error);
        }

        if ($this->isActivelyProvisioning($tenant)) {
            return null;
        }

        $fromFailed = $this->lastFailedJobErrorForTenant($tenant->id);

        return $fromFailed !== null && $fromFailed !== ''
            ? $this->normalizeProvisionErrorMessage($fromFailed)
            : null;
    }

    /**
     * Align company status with queue outcome (failed / provisioning) for Status & subscription UI.
     */
    public function reconcileProvisioningState(Tenant $tenant): bool
    {
        $provisioner = app(TenantProvisionerService::class);

        if ($provisioner->isProvisionWorkflowComplete($tenant)) {
            return $provisioner->syncCompletedProvisionState($tenant->fresh());
        }

        $error = $this->resolveProvisionError($tenant);

        if ($error !== null && $error !== '') {
            if ($tenant->status === 'failed'
                && $tenant->provision_error === $error
                && $tenant->provisioning_stage === 'failed') {
                return false;
            }

            $tenant->update([
                'status' => 'failed',
                'provision_error' => $error,
                'provisioning_stage' => 'failed',
            ]);

            return true;
        }

        if ($this->isActivelyProvisioning($tenant) && $tenant->status !== 'provisioning') {
            $tenant->update(['status' => 'provisioning']);

            return true;
        }

        return false;
    }

    /**
     * @deprecated Use reconcileProvisioningState()
     */
    public function syncFailedJobErrorToTenant(Tenant $tenant): bool
    {
        return $this->reconcileProvisioningState($tenant);
    }

    public function isActivelyProvisioning(Tenant $tenant): bool
    {
        return in_array($tenant->provisioning_stage, [
            'queued', 'running', 'preparing', 'cloning', 'mysql_user', 'seeding', 'crm_admin',
        ], true);
    }

    public function normalizeProvisionErrorMessage(string $message): string
    {
        if (str_contains($message, 'Jobs\\DB') || str_contains($message, 'App\\Jobs\\DB')) {
            return 'Provisioning code is outdated (fixed in latest app code). Stop Horizon (Ctrl+C), run `php artisan horizon` again, then click Retry.';
        }

        if (str_contains($message, 'MaxAttemptsExceededException') || str_contains($message, 'interrupted')) {
            return 'Provisioning was interrupted (worker stopped or job timed out). Restart Horizon, then click Retry.';
        }

        return $message;
    }

    public function lastFailedJobErrorForTenant(int $tenantId): ?string
    {
        if (! $this->failedJobsTableExists()) {
            return null;
        }

        $rows = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['exception', 'payload']);

        foreach ($rows as $row) {
            if (! $this->payloadMatchesTenant((string) $row->payload, $tenantId)) {
                continue;
            }

            return $this->exceptionMessageFromFailedJob((string) $row->exception);
        }

        return null;
    }

    public function friendlyProvisionError(?\Throwable $exception): string
    {
        if ($exception === null) {
            return 'Provisioning job failed.';
        }

        if ($exception instanceof MaxAttemptsExceededException) {
            return 'Provisioning was interrupted (worker stopped or job timed out). Click Retry to run again.';
        }

        $message = $exception->getMessage();

        if (str_contains($message, 'MaxAttemptsExceededException')) {
            return 'Provisioning was interrupted (worker stopped or job timed out). Click Retry to run again.';
        }

        return $this->normalizeProvisionErrorMessage($message);
    }

    protected function exceptionMessageFromFailedJob(string $exception): string
    {
        if (preg_match('/^([^\n]+)/', $exception, $matches)) {
            return $this->normalizeProvisionErrorMessage($matches[1]);
        }

        return 'Provisioning job failed.';
    }

    protected function failedJobsTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('failed_jobs');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function purgeRedisQueueJobsForTenant(int $tenantId): void
    {
        if (config('queue.default') !== 'redis') {
            return;
        }

        $queue = $this->queueName();
        $redis = Redis::connection(config('queue.connections.redis.connection', 'default'));

        foreach (['queues:'.$queue, 'queues:'.$queue.':reserved', 'queues:'.$queue.':delayed'] as $key) {
            $this->purgeKey($redis, $key, $tenantId);
        }
    }

    /**
     * @param  \Illuminate\Redis\Connections\Connection  $redis
     */
    protected function purgeKey($redis, string $key, int $tenantId): void
    {
        if (! $redis->exists($key)) {
            return;
        }

        $type = $redis->type($key);

        if ($type === 'list') {
            foreach ($redis->lrange($key, 0, -1) as $payload) {
                if ($this->payloadMatchesTenant($payload, $tenantId)) {
                    $redis->lrem($key, 0, $payload);
                }
            }

            return;
        }

        if ($type === 'zset') {
            foreach ($redis->zrange($key, 0, -1) as $payload) {
                if ($this->payloadMatchesTenant($payload, $tenantId)) {
                    $redis->zrem($key, $payload);
                }
            }
        }
    }

    protected function payloadMatchesTenant(string $payload, int $tenantId): bool
    {
        if (! str_contains($payload, 'ProvisionTenantJob')) {
            return false;
        }

        if (preg_match('/tenantId[^;]*;i:(\d+);/', $payload, $matches)) {
            return (int) $matches[1] === $tenantId;
        }

        return str_contains($payload, '"tenantId":'.$tenantId)
            || str_contains($payload, '"tenantId":'.$tenantId.',');
    }
}
