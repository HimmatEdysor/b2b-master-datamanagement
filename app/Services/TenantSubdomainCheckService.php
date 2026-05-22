<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSubdomainCheckLog;
use App\Models\TenantSubdomainCheckStat;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantSubdomainCheckService
{
    public function __construct(
        protected MasterActivityLogService $activityLog,
    ) {}

    /**
     * Record a CRM tenant resolve (subdomain / host check) in master DB + file log.
     */
    public function record(
        string $host,
        ?Tenant $tenant,
        string $outcome,
        int $httpStatus,
        ?string $message = null,
        ?string $code = null,
        ?Request $request = null,
    ): void {
        $host = strtolower(trim($host));
        $now = now();

        TenantSubdomainCheckLog::create([
            'host' => $host,
            'tenant_id' => $tenant?->id,
            'slug' => $tenant?->slug,
            'outcome' => $outcome,
            'http_status' => $httpStatus,
            'code' => $code,
            'message' => $message !== null ? Str::limit($message, 1000, '') : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 512, '') : null,
            'created_at' => $now,
        ]);

        $stat = TenantSubdomainCheckStat::query()->firstOrNew(['host' => $host]);
        $stat->check_count = (int) $stat->check_count + 1;

        if ($tenant) {
            $stat->tenant_id = $tenant->id;
            $stat->slug = $tenant->slug;
        }

        match ($outcome) {
            'allowed' => $stat->allowed_count = (int) $stat->allowed_count + 1,
            'denied' => $stat->denied_count = (int) $stat->denied_count + 1,
            'not_found', 'invalid_host' => $stat->not_found_count = (int) $stat->not_found_count + 1,
            default => null,
        };

        $stat->last_http_status = $httpStatus;
        $stat->last_outcome = $outcome;
        $stat->last_code = $code;
        $stat->last_message = $message !== null ? Str::limit($message, 1000, '') : null;
        $stat->first_checked_at ??= $now;
        $stat->last_checked_at = $now;
        $stat->save();

        $status = $outcome === 'allowed' ? 'ok' : ($outcome === 'denied' ? 'failed' : 'skipped');

        $this->activityLog->resolve(
            'tenant_resolve',
            $status,
            sprintf(
                'host=%s checks=%d outcome=%s http=%d%s',
                $host,
                $stat->check_count,
                $outcome,
                $httpStatus,
                $message ? ' — '.$message : ''
            ),
            $tenant,
            null,
            [
                'host' => $host,
                'check_count' => $stat->check_count,
                'allowed_count' => $stat->allowed_count,
                'denied_count' => $stat->denied_count,
                'outcome' => $outcome,
                'http_status' => $httpStatus,
                'code' => $code,
            ]
        );
    }
}
