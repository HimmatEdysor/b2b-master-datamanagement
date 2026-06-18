<?php

namespace App\Services;

use App\Events\TenantProvisioningProgress;
use App\Models\Tenant;
use App\Support\SafeBroadcast;

class TenantProvisioningBroadcastService
{
    public function stage(Tenant $tenant, string $stage, ?string $detail = null): void
    {
        $this->emit($tenant, $stage, $detail);
    }

    public function cloneTable(Tenant $tenant, int $current, int $total, string $table): void
    {
        if ($total > 1 && $current < $total && $current % 5 !== 0) {
            return;
        }

        $detail = $total > 0
            ? sprintf('Cloning table %d of %d: %s', $current, $total, $table)
            : 'Cloning database schema…';

        $this->emit($tenant, 'cloning', $detail, [
            'clone_current' => $current,
            'clone_total' => $total,
            'current_table' => $table,
        ]);
    }

    public function seedTable(Tenant $tenant, int $current, int $total, string $table): void
    {
        $detail = $total > 0
            ? sprintf('Seeding %d of %d: %s', $current, $total, $table)
            : 'Seeding reference data…';

        $this->emit($tenant, 'seeding', $detail, [
            'seed_current' => $current,
            'seed_total' => $total,
            'current_table' => $table,
        ]);
    }

    public function failed(Tenant $tenant, string $message): void
    {
        $this->emit($tenant, 'failed', $message, [
            'failed' => true,
            'provision_error' => $message,
        ]);
    }

    public function completed(Tenant $tenant): void
    {
        $this->emit($tenant, 'completed', null, [
            'done' => true,
            'clone_done' => true,
            'mysql_user_done' => true,
            'crm_admin_done' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function emit(Tenant $tenant, string $stage, ?string $detail = null, array $extra = []): void
    {
        if (! master_broadcast_uses_reverb()) {
            return;
        }

        $cloneCurrent = isset($extra['clone_current']) ? (int) $extra['clone_current'] : null;
        $cloneTotal = isset($extra['clone_total']) ? (int) $extra['clone_total'] : null;
        $seedCurrent = isset($extra['seed_current']) ? (int) $extra['seed_current'] : null;
        $seedTotal = isset($extra['seed_total']) ? (int) $extra['seed_total'] : null;

        $payload = array_merge([
            'tenant_id' => $tenant->id,
            'stage' => $stage,
            'stage_label' => $this->stageLabel($stage),
            'detail' => $detail,
            'percent' => $this->percentForStage($stage, $cloneCurrent, $cloneTotal, $seedCurrent, $seedTotal),
            'status' => $tenant->status,
            'provision_error' => $tenant->provision_error,
            'failed' => $stage === 'failed',
            'done' => $stage === 'completed',
        ], $extra);

        SafeBroadcast::dispatch(new TenantProvisioningProgress($tenant->id, $payload));
    }

    protected function stageLabel(string $stage): string
    {
        return match ($stage) {
            'queued' => 'Queued — waiting for worker',
            'running' => 'Starting…',
            'preparing' => 'Domains & storage',
            'cloning' => 'Cloning database schema',
            'mysql_user' => 'Creating MySQL user',
            'seeding' => 'Seeding reference data',
            'crm_admin' => 'Creating CRM admin login',
            'completed' => 'Complete',
            'failed' => 'Failed',
            default => 'Provisioning',
        };
    }

    public function percentForStage(
        string $stage,
        ?int $cloneCurrent = null,
        ?int $cloneTotal = null,
        ?int $seedCurrent = null,
        ?int $seedTotal = null,
    ): int {
        return match ($stage) {
            'queued' => 2,
            'running' => 5,
            'preparing' => 10,
            'cloning' => $this->subProgress(12, 52, $cloneCurrent, $cloneTotal),
            'mysql_user' => 58,
            'seeding' => $this->subProgress(62, 84, $seedCurrent, $seedTotal),
            'crm_admin' => 90,
            'completed' => 100,
            'failed' => 0,
            default => 8,
        };
    }

    protected function subProgress(int $min, int $max, ?int $current, ?int $total): int
    {
        if ($current === null || $total === null || $total < 1) {
            return $min;
        }

        $ratio = min(1, max(0, $current / $total));

        return (int) round($min + ($max - $min) * $ratio);
    }
}
