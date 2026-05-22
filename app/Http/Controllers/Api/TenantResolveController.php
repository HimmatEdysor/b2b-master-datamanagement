<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantAccessService;
use App\Services\TenantResolverService;
use App\Services\TenantSubdomainCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantResolveController extends Controller
{
    public function __construct(
        protected TenantResolverService $resolver,
        protected TenantAccessService $access,
        protected TenantSubdomainCheckService $subdomainChecks,
    ) {}

    /**
     * Single API for the main B2B CRM: pass subdomain / host, receive DB + branding config.
     *
     * Checks master DB first: company status, subscription, plan expiry, then tenant DB credentials.
     * Each call is logged in master DB (per-host check count + event log).
     *
     * GET /api/v1/tenant/resolve?host=apple.localhost
     * Header: Authorization: Bearer {CRM_MASTER_API_TOKEN}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $host = $request->query('host')
            ?? $request->header('X-Tenant-Host')
            ?? $request->header('Host');

        $host = is_string($host) ? $this->resolver->normalizeHost($host) : '';

        if ($host === '') {
            $this->subdomainChecks->record(
                $host ?: '(empty)',
                null,
                'invalid_host',
                422,
                'Missing host. Use ?host=, X-Tenant-Host, or Host header.',
                null,
                $request,
            );

            return response()->json([
                'success' => false,
                'message' => 'Missing host. Use ?host=, X-Tenant-Host, or Host header.',
            ], 422);
        }

        $tenant = $this->resolver->resolveByHost($host);

        if (! $tenant) {
            $this->subdomainChecks->record(
                $host,
                null,
                'not_found',
                404,
                'No company configured for this host.',
                null,
                $request,
            );

            return response()->json([
                'success' => false,
                'message' => 'No company configured for this host.',
                'host' => $host,
            ], 404);
        }

        $access = $this->access->evaluate($tenant);

        if (! ($access['allowed'] ?? false)) {
            $this->subdomainChecks->record(
                $host,
                $tenant,
                'denied',
                $access['http_status'],
                $access['message'],
                $access['code'],
                $request,
            );

            return response()->json([
                'success' => false,
                'message' => $access['message'],
                'host' => $host,
                'status' => $access['company_status'],
                'subscription_status' => $access['subscription_status'],
                'code' => $access['code'],
            ], $access['http_status']);
        }

        $this->subdomainChecks->record(
            $host,
            $tenant,
            'allowed',
            200,
            'Tenant resolve OK',
            'allowed',
            $request,
        );

        $tenant->loadMissing('domains');

        return response()->json([
            'success' => true,
            'host' => $host,
            'data' => $this->resolver->toApiPayload($tenant),
        ]);
    }
}
