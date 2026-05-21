<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantDomainService;
use App\Support\TenantDomainHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantDomainController extends Controller
{
    public function __construct(
        protected TenantDomainService $domains,
    ) {}

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $type = $request->string('domain_type')->toString();

        if ($type === 'subdomain_alias') {
            $validated = $request->validate([
                'alias' => ['required', 'string', 'max:64'],
            ]);

            $this->domains->addSubdomainAlias($tenant, $validated['alias']);
        } else {
            $validated = $request->validate([
                'host' => TenantDomainHost::customDomainRules(),
            ]);

            $this->domains->addCustomDomain($tenant, $validated['host']);
        }

        return back()->with('success', 'Domain added.');
    }

    public function setPrimary(Tenant $tenant, TenantDomain $domain): RedirectResponse
    {
        $this->domains->setPrimary($tenant, $domain);

        return back()->with('success', 'Primary domain updated.');
    }

    public function destroy(Tenant $tenant, TenantDomain $domain): RedirectResponse
    {
        $this->domains->remove($tenant, $domain);

        return back()->with('success', 'Domain removed.');
    }
}
