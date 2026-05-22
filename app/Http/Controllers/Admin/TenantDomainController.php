<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantDomainDnsService;
use App\Services\TenantDomainService;
use App\Services\TenantDomainSslService;
use App\Support\TenantDomainHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantDomainController extends Controller
{
    public function __construct(
        protected TenantDomainService $domains,
        protected TenantDomainDnsService $dns,
        protected TenantDomainSslService $ssl,
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
            $request->merge([
                'host' => TenantDomainHost::prepareNullableHost($request->input('host')),
            ]);

            $validated = $request->validate([
                'host' => TenantDomainHost::requiredCustomDomainRules(),
            ], [
                'host.required' => 'Enter a custom domain hostname (e.g. crm.yourcompany.com).',
                'host.regex' => 'Enter a valid hostname only — no http://, spaces, or paths.',
            ]);

            $host = $validated['host'];
            $this->domains->addCustomDomain($tenant, $host);

            return back()
                ->with('success', 'Custom domain added. Configure DNS and SSL below.')
                ->with('custom_domain_setup', TenantDomainHost::setupGuide($host, $tenant));
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

    public function provisionAllDns(Tenant $tenant): RedirectResponse
    {
        $results = $this->dns->autoProvisionPendingForTenant($tenant->fresh(['domains']));

        if ($results === []) {
            return back()->with('warning', 'No domains are waiting for DNS linking.');
        }

        $ok = collect($results)->where('verified', true);
        $fail = collect($results)->where('verified', false);

        $message = $ok->isNotEmpty()
            ? 'Linked: '.$ok->pluck('host')->join(', ').'.'
            : '';

        if ($fail->isNotEmpty()) {
            $message .= ($message !== '' ? ' ' : '')
                .'Pending/failed: '.$fail->map(fn ($r) => $r['host'].' — '.$r['message'])->join(' | ');
        }

        return back()
            ->with($ok->isNotEmpty() ? 'success' : 'warning', $message !== '' ? trim($message) : 'DNS run finished.')
            ->withFragment('tenant-manage-domains');
    }

    public function provisionDns(Tenant $tenant, TenantDomain $domain): RedirectResponse
    {
        if ($domain->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $this->dns->provisionForDomain($domain, $tenant);

        if ($result['verified']) {
            $domain->refresh();
            $flash = ($result['link_source'] ?? $domain->dns_link_source) === 'local' ? 'warning' : 'success';

            return back()
                ->with($flash, $result['message'].(($flash === 'success') ? ' Next: click SSL Apply.' : ''))
                ->with('dns_apply_host', ($result['link_source'] ?? '') === 'cloudflare' ? $domain->host : null)
                ->withFragment('tenant-manage-domains');
        }

        return back()
            ->with($result['ok'] ? 'warning' : 'error', $result['message'])
            ->withFragment('tenant-manage-domains');
    }

    public function verifyDns(Tenant $tenant, TenantDomain $domain): RedirectResponse
    {
        if ($domain->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $this->dns->verifyForDomain($domain, $tenant);

        $message = $result['verified']
            ? $result['message'].' Next: set up SSL below.'
            : $result['message'];

        return back()
            ->with($result['verified'] ? 'success' : 'error', $message)
            ->withFragment('tenant-manage-domains');
    }

    public function checkSsl(Tenant $tenant, TenantDomain $domain): RedirectResponse
    {
        if ($domain->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $this->ssl->checkForDomain($domain, $tenant);

        return back()
            ->with($result['active'] ? 'success' : ($result['ok'] ? 'warning' : 'error'), $result['message'])
            ->withFragment('tenant-manage-domains');
    }

    public function markSslComplete(Tenant $tenant, TenantDomain $domain): RedirectResponse
    {
        if ($domain->tenant_id !== $tenant->id) {
            abort(404);
        }

        $result = $this->ssl->markComplete($domain, $tenant);

        return back()
            ->with($result['active'] ? 'success' : 'error', $result['message'])
            ->withFragment('tenant-manage-domains');
    }
}
