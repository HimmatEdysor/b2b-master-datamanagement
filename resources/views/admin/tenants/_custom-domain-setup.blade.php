@php
    $guide = $guide ?? \App\Support\TenantDomainHost::setupGuide($host, $tenant);
@endphp
<div class="custom-domain-setup card-inner">
    <h4 class="custom-domain-setup-title">DNS &amp; SSL setup for <code>{{ $guide['host'] }}</code></h4>
    <p class="form-hint">Complete these steps at your domain registrar after the company is <strong>active</strong>.</p>

    <div class="custom-domain-setup-section">
        <strong>1. DNS records</strong>
        <table class="detail-table detail-table-compact custom-domain-dns-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Name / host</th>
                    <th>Value / points to</th>
                </tr>
            </thead>
            <tbody>
                @foreach($guide['dns_records'] as $record)
                    <tr>
                        <td><code>{{ $record['type'] }}</code></td>
                        <td>
                            <code>{{ $record['name'] }}</code>
                            @include('admin.partials.copy-btn', ['text' => $record['name'], 'title' => 'Copy name'])
                        </td>
                        <td>
                            <code>{{ $record['value'] }}</code>
                            @include('admin.partials.copy-btn', ['text' => $record['value'], 'title' => 'Copy value'])
                            <span class="form-hint" style="display:block;margin-top:4px">{{ $record['note'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($guide['server_ip'])
            <p class="form-hint">Server IP (from master config): <code>{{ $guide['server_ip'] }}</code>
                @include('admin.partials.copy-btn', ['text' => $guide['server_ip'], 'title' => 'Copy IP'])
            </p>
        @else
            <p class="form-hint">Set <code>CUSTOM_DOMAIN_SERVER_IP</code> in master <code>.env</code> to show the A record IP here.</p>
        @endif
    </div>

    <div class="custom-domain-setup-section">
        <strong>2. SSL certificate (Let’s Encrypt)</strong>
        <p class="form-hint">Run on the server that serves the CRM (after DNS propagates).</p>
        <pre class="custom-domain-ssl-commands" tabindex="0">{{ implode("\n", $guide['ssl_commands']) }}</pre>
        @include('admin.partials.copy-btn', [
            'text' => implode("\n", $guide['ssl_commands']),
            'title' => 'Copy SSL commands',
            'label' => 'Copy commands',
        ])
    </div>

    @if($guide['nginx_snippet'])
    <div class="custom-domain-setup-section">
        <strong>3. Nginx (example)</strong>
        <pre class="custom-domain-ssl-commands" tabindex="0">{{ $guide['nginx_snippet'] }}</pre>
    </div>
    @endif
</div>
