<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ProvisionTenantQueueService;
use App\Services\TenantProvisionerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public function __construct(
        public int $tenantId,
        public ?int $approvedByUserId,
    ) {
        $cloneTimeout = (int) config('master.tenant_db_clone_timeout', 3000);

        $this->timeout = max(600, $cloneTimeout + 300);
        $this->onQueue((string) config('master.tenant_provision_queue', 'provisioning'));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $expire = max(600, (int) config('master.tenant_db_clone_timeout', 3000) + 600);

        return [
            (new WithoutOverlapping('provision-tenant-'.$this->tenantId))
                ->expireAfter($expire)
                ->releaseAfter(15),
        ];
    }

    public function handle(TenantProvisionerService $provisioner): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $user = $this->approvedByUserId
            ? User::find($this->approvedByUserId)
            : null;

        try {
            $result = $provisioner->approve($tenant->fresh(), $user);

            if ($result !== null) {
                Cache::put($this->credentialsCacheKey(), $result, now()->addHour());
            }
        } catch (\Throwable $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->recordFailure($exception);
    }

    protected function recordFailure(?\Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $queue = app(ProvisionTenantQueueService::class);
        $message = $queue->normalizeProvisionErrorMessage(
            $queue->friendlyProvisionError($exception)
        );

        $tenant->update([
            'status' => 'failed',
            'provision_error' => $message,
            'provisioning_stage' => 'failed',
        ]);

        Log::error('ProvisionTenantJob failed', [
            'tenant_id' => $this->tenantId,
            'error' => $message,
        ]);
    }

    public function credentialsCacheKey(): string
    {
        return 'tenant:'.$this->tenantId.':provision_credentials';
    }
}
