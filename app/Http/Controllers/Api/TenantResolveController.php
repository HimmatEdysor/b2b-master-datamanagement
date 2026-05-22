<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantResolveController extends Controller
{
    public function __construct(
        protected TenantResolverService $resolver
    ) {}

    /**
     * Single API for the main B2B CRM: pass subdomain / host, receive DB + branding config.
     *
     * GET /api/v1/tenant/resolve?host=edysor.guaranteeadmit.com
     * Header: Authorization: Bearer {CRM_MASTER_API_TOKEN}
     * Or header: X-Tenant-Host: edysor.guaranteeadmit.com
     */
    public function __invoke(Request $request): JsonResponse
    {
        $host = $request->query('host')
            ?? $request->header('X-Tenant-Host')
            ?? $request->header('Host');

        $host = is_string($host) ? $this->resolver->normalizeHost($host) : '';

        if ($host === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing host. Use ?host=, X-Tenant-Host, or Host header.',
            ], 422);
        }

        $tenant = $this->resolver->resolveByHost($host);

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No company configured for this host.',
                'host' => $host,
            ], 404);
        }

        if ($tenant->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'This company account is suspended.',
                'host' => $host,
            ], 403);
        }

        if (! in_array($tenant->status, ['active', 'provisioning'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Company is not available.',
                'status' => $tenant->status,
            ], 503);
        }

        if (! $tenant->isDatabaseProvisioned()) {
            return response()->json([
                'success' => false,
                'message' => 'Company database is not provisioned yet.',
                'status' => $tenant->status,
            ], 503);
        }

        $tenant->loadMissing('domains');

        return response()->json([
            'success' => true,
            'host' => $host,
            'data' => $this->resolver->toApiPayload($tenant),
        ]);
    }
}
