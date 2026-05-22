<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesTenantLogo;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\MasterActivityLogService;
use App\Services\TenantCrmMigrateService;
use App\Services\TenantDomainService;
use App\Services\TenantProvisionerService;
use App\Services\TenantResolverService;
use App\Support\TenantDomainHost;
use App\Support\TenantSlug;
use App\Support\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantController extends Controller
{
    use HandlesTenantLogo;

    public function __construct(
        protected TenantProvisionerService $provisioner,
        protected TenantResolverService $resolver,
        protected TenantCrmMigrateService $crmMigrate,
        protected MasterActivityLogService $activityLog,
        protected TenantDomainService $domainService,
    ) {}

    public function index(Request $request): View
    {
        $statuses = ['pending', 'provisioning', 'active', 'failed', 'suspended', 'rejected'];

        $statusCounts = Tenant::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalCount = (int) $statusCounts->sum();

        $tenants = Tenant::query()
            ->with(['domains', 'subscriptionPlan'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('database_name', 'like', $term)
                        ->orWhere('contact_email', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('contact_phone', 'like', $term);
                });
            })
            ->orderByRaw("FIELD(status, 'pending', 'provisioning', 'failed', 'active', 'suspended', 'rejected')")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $hasFilters = $request->filled('q') || $request->filled('status');

        $migrateableTenantCount = Tenant::query()
            ->whereNotNull('database_name')
            ->where('database_name', '!=', '')
            ->count();

        return view('admin.tenants.index', compact(
            'tenants',
            'statuses',
            'statusCounts',
            'totalCount',
            'hasFilters',
            'migrateableTenantCount',
        ));
    }

    public function create(): View
    {
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.tenants.create', compact('plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTenant($request);

        $databaseName = ! empty($validated['database_name'])
            ? $validated['database_name']
            : $this->provisioner->reserveDatabaseName($validated['slug']);

        $logoUrl = $this->resolveTenantLogoUrl($request, slug: $validated['slug']);
        $faviconUrl = $this->resolveTenantFaviconUrl($request, slug: $validated['slug']);

        $tenant = Tenant::create(array_merge(
            $this->tenantPayload($validated, $databaseName, $logoUrl, $faviconUrl),
            [
                'slug' => $validated['slug'],
                'status' => $validated['status'] ?? 'pending',
            ]
        ));

        if (! empty($validated['custom_domain'])) {
            TenantDomain::create([
                'tenant_id' => $tenant->id,
                'host' => Str::lower($validated['custom_domain']),
                'type' => 'custom',
                'is_primary' => false,
            ]);
        }

        if ($request->boolean('approve_immediately')) {
            $withData = $request->boolean('with_data');
            try {
                $dbCredentials = $this->provisioner->approve($tenant->fresh(), Auth::user(), $withData);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('admin.tenants.show', $tenant)
                    ->with('error', 'Created but provisioning failed: '.$e->getMessage());
            }

            $cloneNote = $withData
                ? 'Full template data was copied into the tenant database.'
                : 'Database structure was cloned; reference data was seeded.';

            return $this->redirectAfterProvision(
                redirect()->route('admin.tenants.show', $tenant),
                'Company approved and database provisioned. '.$cloneNote,
                $dbCredentials
            );
        }

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('success', 'Company saved as pending. Approve to create database and go live.');
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load([
            'domains',
            'subscriptionPlan',
            'approver',
            'operationLogs' => fn ($q) => $q->with('user')->latest()->limit(20),
        ]);

        $resolveHost = TenantUrl::hostForTenant($tenant);
        $resolveUrl = $tenant->isActive() && $resolveHost
            ? url('/api/v1/tenant/resolve?host='.$resolveHost)
            : null;

        return view('admin.tenants.show', compact('tenant', 'resolveUrl'));
    }

    public function edit(Tenant $tenant): View
    {
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('name')->get();
        $tenant->load('domains');

        return view('admin.tenants.edit', compact('tenant', 'plans'));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validateTenant($request, $tenant->id, isUpdate: true);

        $logoUrl = $this->resolveTenantLogoUrl($request, $tenant);
        $faviconUrl = $this->resolveTenantFaviconUrl($request, $tenant);

        $payload = collect($validated)
            ->except(['custom_domain', 'approve_immediately', 'slug', 'logo', 'remove_logo', 'favicon', 'remove_favicon', 'favicon_url'])
            ->all();

        $payload['logo_url'] = $logoUrl;
        $payload['favicon_url'] = $faviconUrl;

        if (array_key_exists('subscription_status', $payload) && $payload['subscription_status'] === '') {
            $payload['subscription_status'] = null;
        }

        $tenant->update($payload);

        if (! empty($validated['custom_domain'])) {
            $host = Str::lower($validated['custom_domain']);
            if (! $tenant->domains()->where('host', $host)->exists()) {
                try {
                    $this->domainService->addCustomDomain($tenant, $host);
                } catch (\Illuminate\Validation\ValidationException) {
                    return back()
                        ->withInput()
                        ->withErrors(['custom_domain' => 'Could not add custom domain. It may already be in use.']);
                }
            }
        }

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('success', 'Company updated.');
    }

    public function approve(Request $request, Tenant $tenant): RedirectResponse
    {
        try {
            $dbCredentials = $this->provisioner->approve(
                $tenant,
                Auth::user(),
                $request->boolean('with_data')
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Provisioning failed: '.$e->getMessage());
        }

        $withData = $request->boolean('with_data');
        $cloneNote = $withData
            ? 'All data from the template database was copied into the new tenant database.'
            : 'Database structure was cloned; reference data was seeded (no full data copy).';

        return $this->redirectAfterProvision(
            back(),
            'Company approved. '.$cloneNote,
            $dbCredentials
        );
    }

    public function reject(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->provisioner->reject($tenant, Auth::user(), $request->input('reason'));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Registration rejected.');
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspended']);
        $this->resolver->forgetHostCache($tenant);
        $this->activityLog->domain('suspend', 'ok', 'Company suspended — CRM resolve disabled', $tenant, Auth::user());

        return back()->with('success', 'Company suspended.');
    }

    public function reactivate(Tenant $tenant): RedirectResponse
    {
        if (! in_array($tenant->status, ['suspended', 'failed'], true)) {
            return back()->with('error', 'Only suspended or failed companies can be reactivated.');
        }

        if ($tenant->database_name === '' || $tenant->database_name === null) {
            return back()->with('error', 'Cannot reactivate: no tenant database configured.');
        }

        $tenant->update([
            'status' => 'active',
            'provision_error' => null,
        ]);

        $this->resolver->forgetHostCache($tenant);
        $this->activityLog->domain('reactivate', 'ok', 'Company reactivated — CRM URL available again', $tenant, Auth::user());

        return back()->with('success', 'Company reactivated. CRM URL is available again.');
    }

    /**
     * JSON list of companies + domains for progressive migrate UI (re-fetch when new companies exist).
     */
    public function migrationQueue(Request $request): \Illuminate\Http\JsonResponse
    {
        $slug = $request->query('slug');
        $slug = is_string($slug) ? trim($slug) : null;
        if ($slug === '') {
            $slug = null;
        }

        $queue = $this->crmMigrate->migrationQueue($slug);

        return response()->json([
            'success' => true,
            'crm_path' => config('master.tenant_crm_path'),
            'data' => $queue,
        ]);
    }

    /**
     * Run B2B CRM migrate on one company database (master triggers CRM artisan subprocess).
     */
    public function migrateDatabase(Request $request, Tenant $tenant): \Illuminate\Http\JsonResponse
    {
        if (! config('master.tenant_crm_path')) {
            return response()->json([
                'success' => false,
                'message' => 'Set TENANT_CRM_PATH in .env to the B2B CRM project root (folder with artisan).',
            ], 503);
        }

        $run = $this->crmMigrate->migrate($tenant->fresh(['domains', 'subscriptionPlan']), Auth::user());
        $tenant->refresh();

        return response()->json([
            'success' => $run['ok'],
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database_name,
                'domains' => $tenant->domains->pluck('host')->values()->all(),
                'primary_url' => TenantUrl::urlForHost(TenantUrl::hostForTenant($tenant)),
                'message' => $run['message'],
                'migration_status' => $tenant->migration_status,
                'last_migration_at' => $tenant->last_migration_at?->toIso8601String(),
            ],
        ], $run['ok'] ? 200 : 500);
    }

    public function migrateDatabases(Request $request): RedirectResponse
    {
        $request->validate([
            'migrate_all' => ['required', 'in:1'],
        ]);

        $timeLimit = (int) config('master.tenant_crm_bulk_migrate_time_limit', 0);
        if ($timeLimit > 0) {
            set_time_limit($timeLimit);
        } else {
            set_time_limit(0);
        }

        $summary = $this->crmMigrate->migrateAll(Auth::user());

        if ($summary['run_count'] === 0) {
            return redirect()
                ->route('admin.tenants.index', $this->tenantIndexQuery($request))
                ->with('error', 'No company databases found to migrate.');
        }

        return redirect()
            ->route('admin.tenants.index', $this->tenantIndexQuery($request))
            ->with('migrate_results', $summary['results'])
            ->with('migrate_ok_count', $summary['ok_count'])
            ->with('migrate_fail_count', $summary['fail_count'])
            ->with('migrate_total_eligible', $summary['total_eligible'])
            ->with('migrate_run_count', $summary['run_count']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tenantIndexQuery(Request $request): array
    {
        return array_filter([
            'q' => $request->string('q')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'page' => $request->input('page'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function validateTenant(Request $request, ?int $ignoreId = null, bool $isUpdate = false): array
    {
        if (! $isUpdate) {
            $request->merge([
                'slug' => TenantSlug::normalize($request->input('slug')),
            ]);
        }

        if ($request->filled('custom_domain')) {
            $request->merge([
                'custom_domain' => preg_replace('/\s+/', '', strtolower(trim($request->input('custom_domain')))),
            ]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:120'],
            'company_website' => ['nullable', 'url', 'max:500'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'database_name' => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9_]+$/', 'unique:tenants,database_name,'.($ignoreId ?? 'NULL')],
            'status' => [
                'nullable',
                'in:'.implode(',', config('master.tenant_statuses', ['pending', 'provisioning', 'active', 'failed', 'suspended', 'rejected'])),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_designation' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'subscription_status' => [
                'nullable',
                'in:'.implode(',', config('master.subscription_statuses', ['pending', 'trial', 'active', 'cancelled', 'expired', 'suspended'])),
            ],
            'brand_name' => ['nullable', 'string', 'max:255'],
            ...$this->logoValidationRules(),
            'support_email' => ['nullable', 'email', 'max:255'],
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:'.TenantDomainHost::CUSTOM_DOMAIN_REGEX],
            'registration_notes' => ['nullable', 'string', 'max:2000'],
            'approve_immediately' => ['nullable', 'boolean'],
            'with_data' => ['nullable', 'boolean'],
        ];

        if ($isUpdate) {
            $rules['database_name'] = ['required', 'string', 'max:128', 'regex:/^[a-z0-9_]+$/', 'unique:tenants,database_name,'.$ignoreId];
        } else {
            $rules['slug'] = TenantSlug::validationRules();
        }

        return $request->validate($rules, [
            'slug.required' => 'Subdomain is required.',
            'slug.regex' => 'Subdomain can only contain lowercase letters, numbers, and hyphens — no spaces (e.g. data not "data test").',
            'slug.unique' => 'This subdomain is already taken.',
            ...$this->logoValidationMessages(),
        ]);
    }

    /**
     * @param  array{username: string, password: string}|null  $dbCredentials
     */
    protected function redirectAfterProvision(RedirectResponse $redirect, string $message, ?array $dbCredentials): RedirectResponse
    {
        $redirect = $redirect->with('success', $message);

        if ($dbCredentials !== null) {
            $redirect->with('tenant_db_credentials', $dbCredentials);
        }

        return $redirect;
    }

    protected function tenantPayload(array $validated, string $databaseName, ?string $logoUrl = null, ?string $faviconUrl = null): array
    {
        return [
            'name' => $validated['name'],
            'database_name' => $databaseName,
            'database_host' => config('master.tenant_db_host'),
            'database_port' => (int) config('master.tenant_db_port'),
            'database_username' => null,
            'database_password' => null,
            'business_type' => $validated['business_type'] ?? null,
            'company_website' => $validated['company_website'] ?? null,
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_designation' => $validated['contact_designation'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'registration_notes' => $validated['registration_notes'] ?? null,
            'brand_name' => $validated['brand_name'] ?? $validated['name'],
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
            'support_email' => $validated['support_email'] ?? null,
            'subscription_plan_id' => $validated['subscription_plan_id'] ?? null,
            'subscription_status' => $validated['subscription_status'] ?? 'pending',
        ];
    }
}
