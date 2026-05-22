@php
    use App\Support\TenantUrl;
    $baseDomain = TenantUrl::baseDomain();
    $defaultPlatformUrl = TenantUrl::urlForHost($baseDomain);
    $isPlatformDefault = $tenant->slug === config('master.platform_default_slug', 'guaranteeadmit');
@endphp

@if($isPlatformDefault)
    <p class="form-hint domain-platform-note">
        <strong>Default platform CRM:</strong>
        <a href="{{ $defaultPlatformUrl }}" target="_blank" rel="noopener">{{ $defaultPlatformUrl }}</a>
        — apex domain for Guarantee Admit. Partner companies use <code>{slug}.{{ $baseDomain }}</code>.
    </p>
@endif

@if($tenant->domains->isEmpty())
    <p class="detail-empty-block">No domains configured yet. Subdomain is created on approval.</p>
    @if($tenant->slug)
        <p class="form-hint">Expected: <code>{{ TenantUrl::urlForSlug($tenant->slug) }}</code></p>
    @endif
@else
    <table class="detail-table detail-table-domains">
        <thead>
            <tr>
                <th>Host</th>
                <th>Type</th>
                <th>Role</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($tenant->domains->sortBy([
                fn ($a, $b) => ($b->is_primary <=> $a->is_primary) ?: strcmp($a->host, $b->host),
            ]) as $domain)
                @php
                    $displayHost = TenantUrl::normalizeHostForEnvironment($domain->host, $tenant->slug);
                    $domainUrl = TenantUrl::urlForHost($displayHost);
                    $isCanonical = $domain->host === TenantUrl::subdomainHost($tenant->slug) && $domain->type === 'subdomain';
                @endphp
                <tr>
                    <td>
                        @if($domainUrl)
                            <a href="{{ $domainUrl }}" target="_blank" rel="noopener"><code>{{ $displayHost }}</code></a>
                        @else
                            <code>{{ $displayHost }}</code>
                        @endif
                    </td>
                    <td>{{ ucfirst($domain->type) }}</td>
                    <td>
                        @if($domain->is_primary)
                            <span class="badge badge-active">Primary</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="domain-actions">
                        @can('tenants.edit')
                            @if(! $domain->is_primary)
                                <form method="POST" action="{{ route('admin.tenants.domains.primary', [$tenant, $domain]) }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">Set primary</button>
                                </form>
                            @endif
                            @if(! $isCanonical)
                                <form method="POST" action="{{ route('admin.tenants.domains.destroy', [$tenant, $domain]) }}" class="inline-form"
                                      onsubmit="return confirm('Remove domain {{ $displayHost }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline btn-sm btn-danger-text">Remove</button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@can('tenants.edit')
<div class="domain-add-forms">
    <h3 class="detail-subheading">Add domain</h3>
    <div class="domain-add-grid">
        <form method="POST" action="{{ route('admin.tenants.domains.store', $tenant) }}" class="domain-add-form">
            @csrf
            <input type="hidden" name="domain_type" value="subdomain_alias">
            <div class="form-group">
                <label for="alias-{{ $tenant->id }}">Additional subdomain</label>
                <div class="input-with-suffix">
                    <input type="text" id="alias-{{ $tenant->id }}" name="alias" class="form-control"
                           pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="sales" autocomplete="off" spellcheck="false">
                    <span class="input-suffix">.{{ $baseDomain }}</span>
                </div>
                <p class="form-hint">Letters, numbers, hyphens only — no spaces.</p>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Add subdomain</button>
        </form>

        <form method="POST" action="{{ route('admin.tenants.domains.store', $tenant) }}" class="domain-add-form">
            @csrf
            <input type="hidden" name="domain_type" value="custom">
            <div class="form-group">
                <label for="custom-host-{{ $tenant->id }}">Custom domain</label>
                <input type="text" id="custom-host-{{ $tenant->id }}" name="host" class="form-control"
                       placeholder="crm.yourcompany.com" autocomplete="off" spellcheck="false">
                <p class="form-hint">Full hostname (white-label). No spaces.</p>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Add custom domain</button>
        </form>
    </div>
</div>
@endcan
