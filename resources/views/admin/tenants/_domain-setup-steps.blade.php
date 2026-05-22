@php
    $dns = $dns ?? [];
    $ssl = $dns['ssl'] ?? [];
    $showSslPanel = ($dns['linked'] ?? false) && ($ssl['required'] ?? true);
    $showReady = ($dns['ready'] ?? false) && ! empty($ssl['access_url']);
@endphp
<div class="domain-setup-steps" id="domain-{{ $domain->id }}">
    <ol class="domain-setup-steps-list">
        <li class="domain-setup-step {{ ($dns['linked'] ?? false) ? 'is-done' : 'is-current' }}">
            <span class="domain-setup-step-head">
                <span class="domain-setup-step-num">1</span>
                <span class="domain-setup-step-title">DNS</span>
                <span class="badge {{ $dns['badge'] ?? 'badge-draft' }}">{{ $dns['label'] ?? 'DNS' }}</span>
            </span>
            @if($dns['a_record'] ?? null)
                <p class="form-hint domain-dns-record-hint">
                    A <code>{{ $dns['a_record']['name'] }}</code> → <code>{{ $dns['a_record']['value'] }}</code>
                </p>
            @endif
            @if(master_can('tenants.edit') || master_can('tenants.approve'))
                @if($dns['can_auto_cloudflare'] ?? false)
                    <p class="form-hint">One click creates the A record in {{ $dns['dns_provider'] ?? 'Cloudflare' }} via API.</p>
                @elseif($dns['can_local_link'] ?? false)
                    <p class="form-hint">Local dev: marks DNS linked for <code>{{ $dns['a_record']['name'] ?? $domain->host }}</code> → <code>{{ $dns['a_record']['value'] ?? '127.0.0.1' }}</code> (add to <code>/etc/hosts</code> if needed).</p>
                @elseif($dns['pending'] ?? false)
                    <p class="form-hint">Host is outside the Cloudflare zone — use the button to mark pending or add the A record manually in Cloudflare.</p>
                @endif
                @if($dns['can_update_dns'] ?? $dns['can_provision'] ?? false)
                    <form method="POST" action="{{ route('admin.tenants.domains.dns-provision', [$tenant, $domain]) }}" class="inline-form domain-step-form">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">DNS Update</button>
                    </form>
                @endif
                @if($dns['can_verify'] ?? false)
                    <form method="POST" action="{{ route('admin.tenants.domains.dns-verify', [$tenant, $domain]) }}" class="inline-form domain-step-form">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm">Verify DNS complete</button>
                    </form>
                @endif
            @endif
            @if($dns['linked'] ?? false)
                <p class="domain-setup-step-done-msg">DNS complete.</p>
            @endif
        </li>

        <li class="domain-setup-step {{ ($ssl['complete'] ?? false) ? 'is-done' : (($dns['linked'] ?? false) ? 'is-current' : 'is-waiting') }}">
            <span class="domain-setup-step-head">
                <span class="domain-setup-step-num">2</span>
                <span class="domain-setup-step-title">SSL</span>
                <span class="badge {{ $ssl['badge'] ?? 'badge-draft' }}">{{ $ssl['label'] ?? 'SSL' }}</span>
            </span>
            @if($showSslPanel)
                <p class="form-hint">After DNS propagates, run Certbot on the CRM server (commands below for custom domains), then check or mark complete.</p>
                @if(master_can('tenants.edit') || master_can('tenants.approve'))
                    @if($ssl['can_apply_ssl'] ?? false)
                        <form method="POST" action="{{ route('admin.tenants.domains.ssl-complete', [$tenant, $domain]) }}" class="inline-form domain-step-form">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">SSL Apply</button>
                        </form>
                    @endif
                    @if($ssl['can_check'] ?? false)
                        <form method="POST" action="{{ route('admin.tenants.domains.ssl-check', [$tenant, $domain]) }}" class="inline-form domain-step-form">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm">Check SSL</button>
                        </form>
                    @endif
                @endif
            @elseif($dns['linked'] ?? false)
                <p class="domain-setup-step-done-msg">SSL not required in local HTTP mode.</p>
            @else
                <p class="form-hint">Complete step 1 first.</p>
            @endif
            @if($ssl['complete'] ?? false)
                <p class="domain-setup-step-done-msg">SSL complete.</p>
            @endif
        </li>

        <li class="domain-setup-step {{ $showReady ? 'is-done is-current' : 'is-waiting' }}">
            <span class="domain-setup-step-head">
                <span class="domain-setup-step-num">3</span>
                <span class="domain-setup-step-title">Access</span>
                @if($showReady)
                    <span class="badge badge-active">Ready</span>
                @endif
            </span>
            @if($showReady)
                <a href="{{ $ssl['access_url'] }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm domain-open-crm-btn">
                    {{ $ssl['access_label'] ?? 'Open CRM' }}
                </a>
                <code class="domain-access-url">{{ $ssl['access_url'] }}</code>
            @else
                <p class="form-hint">Available after DNS{{ ($ssl['required'] ?? true) ? ' and SSL' : '' }} are complete.</p>
            @endif
        </li>
    </ol>
</div>
