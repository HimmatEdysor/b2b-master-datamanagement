<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTenantLogo;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\MasterActivityLogService;
use App\Services\TenantProvisionerService;
use App\Support\TenantUrl;
use App\Support\TenantSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyRegistrationController extends Controller
{
    use HandlesTenantLogo;

    public function __construct(
        protected TenantProvisionerService $provisioner,
        protected MasterActivityLogService $activityLog,
    ) {}

    public function create(): View
    {
        $plans = SubscriptionPlan::query()->activeOrdered()->get();
        $selectedPlanId = null;

        if ($slug = request('plan')) {
            $selectedPlanId = $plans->firstWhere('slug', $slug)?->id;
        }

        return view('website.register', compact('plans', 'selectedPlanId'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $plansExist = SubscriptionPlan::query()->where('is_active', true)->exists();

        $request->merge([
            'slug' => TenantSlug::normalize($request->input('slug')),
            'custom_domain' => $request->filled('custom_domain')
                ? preg_replace('/\s+/', '', strtolower(trim($request->input('custom_domain'))))
                : null,
        ]);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'max:120'],
            'company_website' => ['nullable', 'url', 'max:500'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'slug' => TenantSlug::validationRules(),
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            ...$this->logoValidationRules(),
            'support_email' => ['nullable', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_designation' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255', 'unique:tenants,contact_email'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'subscription_plan_id' => [
                Rule::requiredIf($plansExist),
                'nullable',
                'exists:subscription_plans,id',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'You must accept the terms of service to register.',
            'subscription_plan_id.required' => 'Please select a subscription plan.',
            'slug.required' => 'Subdomain is required.',
            'slug.regex' => 'Subdomain can only contain lowercase letters, numbers, and hyphens — no spaces (e.g. data not "data test").',
            'slug.unique' => 'This subdomain is already taken. Choose another.',
            ...$this->logoValidationMessages(),
        ]);

        $logoUrl = $this->resolveTenantLogoUrl($request, slug: $request->input('slug'));

        $tenant = Tenant::create([
            'name' => $validated['company_name'],
            'slug' => $validated['slug'],
            'status' => 'pending',
            'database_name' => $this->provisioner->reserveDatabaseName($validated['slug']),
            'database_host' => config('master.tenant_db_host'),
            'database_port' => (int) config('master.tenant_db_port'),
            'database_username' => null,
            'database_password' => null,
            'business_type' => $validated['business_type'],
            'company_website' => $validated['company_website'] ?? null,
            'address_line' => $validated['address_line'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'country' => $validated['country'],
            'contact_name' => $validated['contact_name'],
            'contact_designation' => $validated['contact_designation'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
            'registration_notes' => $validated['notes'] ?? null,
            'brand_name' => $validated['brand_name'] ?: $validated['company_name'],
            'logo_url' => $logoUrl,
            'support_email' => $validated['support_email'] ?: $validated['contact_email'],
            'subscription_plan_id' => $validated['subscription_plan_id'] ?? null,
            'subscription_status' => 'pending',
        ]);

        if (! empty($validated['custom_domain'])) {
            $host = Str::lower($validated['custom_domain']);
            TenantDomain::create([
                'tenant_id' => $tenant->id,
                'host' => $host,
                'type' => 'custom',
                'is_primary' => false,
            ]);
            $this->activityLog->domain('register_custom', 'ok', "Custom domain registered (pending approval): {$host}", $tenant);
            $this->activityLog->dns(
                'dns_update_required',
                'info',
                "After approval, configure DNS for {$host}",
                $tenant,
                null,
                [
                    'custom_host' => $host,
                    'record_type' => 'CNAME',
                    'recommended_target' => TenantUrl::subdomainHost($tenant->slug),
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your registration has been received. Our team will review your application and email you once approved.',
                'company' => $validated['company_name'],
            ]);
        }

        return redirect()
            ->route('register.success')
            ->with('company', $validated['company_name']);
    }

    public function success(): View
    {
        return view('website.register-success');
    }
}
