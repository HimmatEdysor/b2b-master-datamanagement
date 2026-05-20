<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantMigrationDatabasesController extends Controller
{
    public function __construct(
        protected TenantResolverService $resolver
    ) {}

    /**
     * List every company database for B2B CRM bulk migrations.
     *
     * GET /api/v1/tenants/migration-databases?slug=edysor
     * Authorization: Bearer {CRM_MASTER_API_TOKEN}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $slug = $request->query('slug');
        $slug = is_string($slug) ? trim($slug) : '';

        $query = Tenant::query()
            ->whereNotNull('database_name')
            ->where('database_name', '!=', '')
            ->orderBy('id');

        if ($slug !== '') {
            $query->where('slug', $slug);
        }

        $max = max(1, (int) config('master.tenant_crm_migrate_bulk_max_tenants', 500));
        $total = (int) (clone $query)->count();
        $tenants = $query->with(['domains', 'subscriptionPlan'])->limit($max)->get();

        $items = $tenants->map(fn (Tenant $tenant) => $this->resolver->toMigrationQueueItem($tenant))->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'tenants' => $items,
                'total' => $total,
                'returned' => count($items),
                'capped' => $total > count($items),
            ],
        ]);
    }
}
