<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantDomainService;
use App\Support\TenantDomainHost;
use App\Support\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantDomainsController extends Controller
{
    public function __construct(
        protected TenantDomainService $domains,
    ) {}

    protected function tenantBySlug(string $slug): Tenant
    {
        return Tenant::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * List domains for a tenant (CRM /user settings page).
     *
     * GET /api/v1/tenants/{slug}/domains
     */
    public function index(string $slug): JsonResponse
    {
        $tenant = $this->tenantBySlug($slug);

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $tenant->slug,
                'base_domain' => TenantUrl::baseDomain(),
                'default_platform_url' => TenantUrl::urlForHost(TenantUrl::baseDomain()),
                'primary_url' => TenantUrl::urlForTenant($tenant),
                'domains' => $this->domains->listForTenant($tenant),
            ],
        ]);
    }

    /**
     * Add custom domain or subdomain alias.
     *
     * POST /api/v1/tenants/{slug}/domains
     * Body: { "type": "custom", "host": "crm.example.com" }
     *   or: { "type": "subdomain_alias", "alias": "sales" }
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->tenantBySlug($slug);

        try {
            $type = $request->string('type')->toString();

            $domain = match ($type) {
                'subdomain_alias' => $this->domains->addSubdomainAlias(
                    $tenant,
                    (string) $request->input('alias', '')
                ),
                'custom' => $this->domains->addCustomDomain(
                    $tenant,
                    (string) $request->input('host', '')
                ),
                default => throw ValidationException::withMessages([
                    'type' => ['Type must be custom or subdomain_alias.'],
                ]),
            };
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $tenant->refresh()->load('domains');

        return response()->json([
            'success' => true,
            'data' => [
                'domain' => $this->domains->toListItem($domain, $tenant),
                'domains' => $this->domains->listForTenant($tenant),
            ],
        ], 201);
    }

    public function setPrimary(string $slug, TenantDomain $domain): JsonResponse
    {
        $tenant = $this->tenantBySlug($slug);

        try {
            $this->domains->setPrimary($tenant, $domain);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $tenant->refresh()->load('domains');

        return response()->json([
            'success' => true,
            'data' => [
                'domains' => $this->domains->listForTenant($tenant),
                'primary_url' => TenantUrl::urlForTenant($tenant),
            ],
        ]);
    }

    public function destroy(string $slug, TenantDomain $domain): JsonResponse
    {
        $tenant = $this->tenantBySlug($slug);

        try {
            $this->domains->remove($tenant, $domain);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $tenant->refresh()->load('domains');

        return response()->json([
            'success' => true,
            'data' => [
                'domains' => $this->domains->listForTenant($tenant),
            ],
        ]);
    }
}
