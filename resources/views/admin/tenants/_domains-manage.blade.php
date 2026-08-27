@php
    use App\Support\TenantUrl;
    $baseDomain = TenantUrl::baseDomain();
    $defaultPlatformUrl = TenantUrl::urlForHost($baseDomain);
    $isPlatformDefault = $tenant->slug === config('master.platform_default_slug', 'guaranteeadmit');
    $dnsService = $dnsService ?? app(\App\Services\TenantDomainDnsService::class);
    $canManageDomains = master_can('tenants.edit') || master_can('tenants.approve');
    $domainActivityLog = $domainActivityLog ?? [];
    $dnsApplyHost = session('dns_apply_host');
@endphp

<div class="domain-manage-flash" aria-live="polite">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
</div>

@if($isPlatformDefault)
    <p class="form-hint domain-platform-note">
        <strong>Default platform CRM:</strong>
        <a href="{{ $defaultPlatformUrl }}" target="_blank" rel="noopener">{{ $defaultPlatformUrl }}</a>
        — Laravel apex for Guarantee Admit. Partner companies use <code>{slug}.{{ $baseDomain }}</code>
        (Next portal: <code>{slug}.guaranteeadmit.com</code>).
    </p>
@endif

@if(config('master.is_local') && TenantUrl::baseDomain() === 'localhost')
    <div class="alert alert-warning domain-local-dns-note" role="status">
        <strong>Local master:</strong> <code>*.localhost</code> DNS Update only marks the database — not Cloudflare.
        Hosts under <code>{{ config('master.dns_cloudflare_base_domain', 'guaranteeadmit.com') }}</code> use the Cloudflare API when you click DNS Update.
    </div>
@endif

@if($dnsService->serverIp())
    <p class="form-hint domain-dns-ip-note">
        CRM server IP for DNS A records: <code>{{ $dnsService->serverIp() }}</code>
        @if($dnsService->serverIp() === '127.0.0.1' && ! config('master.is_local'))
            <span class="field-error"> — use your public server IP for Cloudflare, not loopback.</span>
        @endif
        @if(master_can('settings.view'))
            · <a href="{{ route('admin.settings.edit') }}">Web settings</a>
        @endif
        @php $dnsProvider = $dnsService->dnsProviderLabel(); @endphp
        @if($dnsService->autoProvisionEnabled())
            · {{ $dnsProvider }} API enabled
        @else
            · Set DNS provider + Cloudflare token in <a href="{{ route('admin.settings.edit') }}">Web settings</a> for automatic A records
        @endif
        @php
            $pendingDnsCount = $tenant->domains->filter(fn ($d) => $dnsService->isPending($d, $tenant))->count();
        @endphp
        @if($pendingDnsCount > 0 && $canManageDomains)
            <span class="domain-dns-bulk-wrap">
                · <form method="POST" action="{{ route('admin.tenants.domains.dns-provision-all', $tenant) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">
                        Auto-link all pending DNS ({{ $pendingDnsCount }})
                    </button>
                </form>
            </span>
        @endif
    </p>
@elseif(master_can('settings.view'))
    <p class="form-hint domain-dns-ip-note">
        Set CRM server IP in <a href="{{ route('admin.settings.edit') }}">Web settings</a> to enable DNS update (local dev uses <code>127.0.0.1</code> when unset).
    </p>
@endif

@include('admin.tenants._cloudflare-dns-record-guide', [
    'tenant' => $tenant,
    'dnsService' => $dnsService,
])

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
                <th>Setup</th>
                <th>Role</th>
                <th>Actions</th>
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
                    $dns = $dnsService->statusFor($domain, $tenant);
                    $ssl = $dns['ssl'] ?? [];
                    $showSetupRow = ! ($dns['ready'] ?? false);
                @endphp
                <tr id="domain-row-{{ $domain->id }}">
                    <td>
                        @if(($dns['ready'] ?? false) && ! empty($ssl['access_url']))
                            <a href="{{ $ssl['access_url'] }}" target="_blank" rel="noopener"><code>{{ $displayHost }}</code></a>
                        @elseif($domainUrl)
                            <a href="{{ $domainUrl }}" target="_blank" rel="noopener"><code>{{ $displayHost }}</code></a>
                        @else
                            <code>{{ $displayHost }}</code>
                        @endif
                    </td>
                    <td>{{ ucfirst($domain->type) }}</td>
                    <td class="domain-setup-cell">
                        @if($dns['ready'] ?? false)
                            <span class="badge badge-active">Ready</span>
                            @if(! empty($ssl['access_url']))
                                <a href="{{ $ssl['access_url'] }}" target="_blank" rel="noopener" class="form-hint domain-setup-ready-link">{{ $ssl['access_label'] ?? 'Open' }}</a>
                            @endif
                        @else
                            <span class="badge {{ $dns['badge'] }}">{{ $dns['label'] }}</span>
                            @if($dns['linked'] ?? false)
                                <span class="badge {{ $ssl['badge'] ?? 'badge-draft' }}">{{ $ssl['label'] ?? 'SSL' }}</span>
                            @endif
                        @endif
                        @if($dns['a_record'] ?? null)
                            <p class="form-hint domain-dns-applied-record">
                                <strong>A</strong>
                                <code>{{ $dns['a_record']['name'] }}</code>
                                → <code>{{ $dns['a_record']['value'] }}</code>
                                @if($dns['linked'] ?? false)
                                    · @if(($dns['dns_link_source'] ?? '') === 'local')
                                        <strong>Local only — not in Cloudflare</strong>
                                    @elseif(($dns['dns_link_source'] ?? '') === 'cloudflare')
                                        Cloudflare API
                                    @elseif(($dns['dns_link_source'] ?? '') === 'route53')
                                        Route53 API
                                    @elseif(($dns['dns_link_source'] ?? '') === 'marked')
                                        Marked manually
                                    @else
                                        Linked
                                    @endif
                                @endif
                            </p>
                        @endif
                        @if($domain->dns_verified_at)
                            <p class="form-hint domain-dns-applied-meta">
                                DNS linked {{ $domain->dns_verified_at->diffForHumans() }}
                                @if($domain->dns_target_ip)
                                    · target <code>{{ $domain->dns_target_ip }}</code>
                                @endif
                            </p>
                        @endif
                        @if(($ssl['complete'] ?? false) && ($ssl['required'] ?? true))
                            <p class="form-hint domain-dns-applied-meta">SSL active</p>
                        @elseif($dns['linked'] ?? false)
                            <p class="form-hint domain-dns-applied-meta">{{ $ssl['label'] ?? 'SSL pending' }}</p>
                        @endif
                        @if($dnsApplyHost && $dnsApplyHost === $domain->host)
                            <p class="form-hint domain-dns-applied-meta"><strong>Last DNS Update applied to this host.</strong></p>
                        @endif
                    </td>
                    <td>
                        @if($domain->is_primary)
                            <span class="badge badge-active">Primary</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="domain-actions">
                        @if($canManageDomains)
                            <div class="domain-actions-primary">
                                @if($dns['can_update_dns'] ?? false)
                                    <form method="POST" action="{{ route('admin.tenants.domains.dns-provision', [$tenant, $domain]) }}" class="inline-form">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">DNS Update</button>
                                    </form>
                                @endif
                                @if($ssl['can_apply_ssl'] ?? false)
                                    <form method="POST" action="{{ route('admin.tenants.domains.ssl-complete', [$tenant, $domain]) }}" class="inline-form">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm">SSL Apply</button>
                                    </form>
                                @elseif($dns['can_update_dns'] ?? false)
                                    <span class="form-hint domain-actions-hint">SSL Apply after DNS Update</span>
                                @endif
                            </div>
                        @endif
                        @if($canManageDomains && master_can('tenants.edit'))
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
                        @endif
                    </td>
                </tr>
                @if($showSetupRow)
                <tr class="domain-setup-row">
                    <td colspan="5">
                        @include('admin.tenants._domain-setup-steps', ['domain' => $domain, 'tenant' => $tenant, 'dns' => $dns])
                        @include('admin.tenants._cloudflare-dns-record-guide', [
                            'host' => $domain->host,
                            'tenant' => $tenant,
                            'dnsService' => $dnsService,
                        ])
                        @if($domain->type === 'custom')
                            @include('admin.tenants._custom-domain-setup', ['host' => $domain->host, 'tenant' => $tenant])
                        @endif
                    </td>
                </tr>
                @elseif($domain->type === 'custom')
                <tr class="domain-setup-row">
                    <td colspan="5">
                        @include('admin.tenants._cloudflare-dns-record-guide', [
                            'host' => $domain->host,
                            'tenant' => $tenant,
                            'dnsService' => $dnsService,
                        ])
                        @include('admin.tenants._custom-domain-setup', ['host' => $domain->host, 'tenant' => $tenant])
                    </td>
                </tr>
                @else
                <tr class="domain-setup-row domain-setup-row--cf-guide">
                    <td colspan="5">
                        @include('admin.tenants._cloudflare-dns-record-guide', [
                            'host' => $domain->host,
                            'tenant' => $tenant,
                            'dnsService' => $dnsService,
                        ])
                    </td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif

@if($domainActivityLog !== [])
    <div class="domain-activity-log">
        <h3 class="detail-subheading">DNS &amp; SSL activity log</h3>
        <p class="form-hint" style="margin-top:0">
            Recent actions for this company.
            @if(master_can('logs.view'))
                <a href="{{ route('admin.logs.index', ['channel' => 'dns', 'date' => now()->format('Y-m-d')]) }}">View full DNS &amp; SSL log</a>
            @endif
        </p>
        <table class="detail-table detail-table-compact domain-activity-log-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                @foreach($domainActivityLog as $entry)
                    @php
                        $src = $entry['link_source'] ?? null;
                        $statusBadge = match ($entry['status']) {
                            'ok' => 'active',
                            'local' => 'pending',
                            'pending' => 'pending',
                            'failed' => 'failed',
                            default => 'draft',
                        };
                        $srcLabel = match ($src) {
                            'local' => 'Local only (no Cloudflare)',
                            'cloudflare' => 'Cloudflare API',
                            'route53' => 'Route53 API',
                            'marked' => 'Marked in DB',
                            'manual' => 'Manual',
                            default => $src ? (string) $src : '—',
                        };
                    @endphp
                    <tr>
                        <td><code class="domain-log-time">{{ $entry['at'] }}</code></td>
                        <td><code>{{ $entry['action'] }}</code></td>
                        <td>
                            <span class="badge badge-{{ $statusBadge }}">{{ $entry['status'] }}</span>
                        </td>
                        <td><span class="form-hint" style="margin:0">{{ $srcLabel }}</span></td>
                        <td>{{ $entry['message'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@elseif(master_can('logs.view'))
    <p class="form-hint domain-activity-log-empty">
        No DNS/SSL log entries yet for this company. Use <strong>DNS Update</strong> or <strong>SSL Apply</strong> — entries appear here and under
        <a href="{{ route('admin.logs.index', ['channel' => 'dns']) }}">Activity logs → DNS &amp; SSL</a>.
    </p>
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
                <p class="form-hint">Use <strong>DNS Update</strong> after adding, then <strong>SSL Apply</strong>.</p>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Add subdomain</button>
        </form>

        <form method="POST" action="{{ route('admin.tenants.domains.store', $tenant) }}" class="domain-add-form">
            @csrf
            <input type="hidden" name="domain_type" value="custom">
            <div class="form-group">
                <label for="custom-host-{{ $tenant->id }}">Custom domain hostname</label>
                <input type="text" id="custom-host-{{ $tenant->id }}" name="host" class="form-control"
                       placeholder="crm.yourcompany.com" autocomplete="off" spellcheck="false"
                       inputmode="url" autocapitalize="off">
                <p class="form-hint">Hostname only — e.g. <code>crm.yourcompany.com</code>. Then <strong>DNS Update</strong> → <strong>SSL Apply</strong>.</p>
                @error('host')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Add custom domain</button>
        </form>
    </div>
</div>
@endcan
