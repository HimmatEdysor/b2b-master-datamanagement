@php
    use App\Services\CloudflareDnsService;
    $cf = app(CloudflareDnsService::class);
    $zone = $cf->zoneBaseDomain();
    $host = \App\Support\TenantDomainHost::normalize($host ?? '');
    $recordName = $cf->recordNameForApi($host, $zone);
    $serverIp = $dnsService->serverIp() ?? config('master.custom_domain_server_ip');
    $proxied = filter_var(config('master.dns_cloudflare_proxied', false), FILTER_VALIDATE_BOOLEAN);
    $fqdn = $host !== '' ? $host : ($recordName.'.'.$zone);
@endphp

@if($serverIp && $host !== '' && $cf->hostIsInZone($host, $zone) && ! str_ends_with($host, '.localhost'))
    <div class="cloudflare-dns-guide">
        <h4 class="detail-subheading">Add in Cloudflare — zone <code>{{ $zone }}</code></h4>
        <p class="form-hint" style="margin-top:0">
            In Cloudflare: <strong>DNS</strong> → <strong>Records</strong> → <strong>Add record</strong>
            (or use <strong>DNS Update</strong> above to create this via API).
        </p>
        <table class="detail-table detail-table-compact cloudflare-dns-guide-table">
            <tbody>
                <tr>
                    <th scope="row">Type</th>
                    <td><code>A</code></td>
                </tr>
                <tr>
                    <th scope="row">Name</th>
                    <td>
                        <code>{{ $recordName }}</code>
                        @include('admin.partials.copy-btn', ['text' => $recordName, 'title' => 'Copy record name'])
                        <span class="form-hint">→ full host <code>{{ $fqdn }}</code></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">IPv4 address</th>
                    <td>
                        <code>{{ $serverIp }}</code>
                        @include('admin.partials.copy-btn', ['text' => $serverIp, 'title' => 'Copy IP'])
                    </td>
                </tr>
                <tr>
                    <th scope="row">Proxy status</th>
                    <td>
                        @if($proxied)
                            <strong>Proxied</strong> (orange cloud) — per your settings
                        @else
                            <strong>DNS only</strong> (grey cloud) — recommended so IP verify matches CRM server
                        @endif
                    </td>
                </tr>
                <tr>
                    <th scope="row">TTL</th>
                    <td>Auto or 300 seconds</td>
                </tr>
            </tbody>
        </table>
        <p class="form-hint">
            After saving, propagation is usually a few minutes. Then click <strong>DNS Update</strong> in master or run
            <code>php artisan tenant:dns-update {{ $tenant->slug }}</code>.
            CRM URL: <code>https://{{ $fqdn }}/login</code>
        </p>
    </div>
@endif
