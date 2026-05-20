<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantCrmMigrateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantMigrateController extends Controller
{
    public function __construct(
        protected TenantCrmMigrateService $crmMigrate
    ) {}

    /**
     * Run B2B CRM `php artisan migrate --force` on one company database.
     *
     * POST /api/v1/tenants/{slug}/migrate
     * Authorization: Bearer {CRM_MASTER_API_TOKEN}
     */
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $slug = trim($slug);
        if ($slug === '') {
            return response()->json(['success' => false, 'message' => 'Slug is required.'], 422);
        }

        $tenant = Tenant::query()
            ->with(['domains', 'subscriptionPlan'])
            ->where('slug', $slug)
            ->first();

        if (! $tenant) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        if ($tenant->database_name === null || $tenant->database_name === '') {
            return response()->json([
                'success' => false,
                'message' => 'This company has no tenant database configured yet.',
            ], 422);
        }

        $run = $this->crmMigrate->migrate($tenant->fresh(['domains', 'subscriptionPlan']));

        return response()->json([
            'success' => $run['ok'],
            'data' => [
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'database' => $tenant->database_name,
                'domains' => $tenant->domains->pluck('host')->values()->all(),
                'message' => $run['message'],
                'migration_status' => $tenant->fresh()->migration_status,
                'last_migration_at' => $tenant->fresh()->last_migration_at?->toIso8601String(),
            ],
        ], $run['ok'] ? 200 : 500);
    }
}
