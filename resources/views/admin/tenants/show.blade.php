@extends('layouts.admin')

@section('title', $tenant->name)
@section('page-title', $tenant->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logo-upload.css') }}">
@endpush

@section('content')
@if(session('tenant_db_credentials'))
    @php($dbCreds = session('tenant_db_credentials'))
    <div class="alert alert-success tenant-db-credentials-alert" role="status">
        <strong>Dedicated database user created.</strong>
        Password is stored encrypted — you can also view and change it anytime under <strong>Database (CRM)</strong> below.
        <dl class="tenant-db-credentials-list">
            <dt>Username</dt>
            <dd><code>{{ $dbCreds['username'] }}</code> @include('admin.partials.copy-btn', ['text' => $dbCreds['username'], 'title' => 'Copy username'])</dd>
            <dt>Password</dt>
            <dd><code>{{ $dbCreds['password'] }}</code> @include('admin.partials.copy-btn', ['text' => $dbCreds['password'], 'title' => 'Copy password'])</dd>
        </dl>
    </div>
@endif
<div class="tenant-show">
    @include('admin.tenants._show-header')

    @if(($dnsAutoOk ?? collect())->isNotEmpty() || ($dnsAutoFail ?? collect())->isNotEmpty())
        @if($dnsAutoOk->isNotEmpty())
            <div class="alert alert-success dns-auto-alert span-full" role="status">
                <strong>DNS auto-linked</strong>
                @foreach($dnsAutoOk as $row)
                    <code>{{ \App\Support\TenantUrl::normalizeHostForEnvironment($row['host'], $tenant->slug) }}</code>@if(! $loop->last), @endif
                @endforeach
                @if($dnsService->autoProvisionEnabled())
                    via {{ $dnsService->dnsProviderLabel() }} API.
                @endif
                Complete SSL in <strong>Manage company → Domains</strong> if needed.
            </div>
        @endif
        @if($dnsAutoFail->isNotEmpty())
            <div class="alert alert-warning dns-pending-alert span-full" role="status">
                <strong>DNS auto-link failed</strong>
                <ul class="dns-auto-fail-list">
                    @foreach($dnsAutoFail as $row)
                        <li><code>{{ $row['host'] }}</code> — {{ $row['message'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
    @if(isset($dnsPendingDomains) && $dnsPendingDomains->isNotEmpty())
        <div class="alert alert-warning dns-pending-alert span-full" role="status">
            <strong>Domain setup incomplete</strong> for
            @foreach($dnsPendingDomains as $domain)
                <code>{{ \App\Support\TenantUrl::normalizeHostForEnvironment($domain->host, $tenant->slug) }}</code>@if(! $loop->last), @endif
            @endforeach.
            @if($dnsService->autoProvisionEnabled())
                Cloudflare/Route53 is configured — use <strong>Add DNS</strong> below or fix API errors above.
            @else
                In <strong>Manage company</strong>: <strong>DNS Update</strong> → <strong>SSL Apply</strong> → open CRM.
            @endif
            @if($dnsService->serverIp())
                Server IP: <code>{{ $dnsService->serverIp() }}</code>.
            @elseif(master_can('settings.view'))
                Set CRM server IP in <a href="{{ route('admin.settings.edit') }}">Web settings</a>.
            @endif
        </div>
    @endif

    @include('admin.tenants._manage-card', [
        'plans' => $plans ?? collect(),
        'planBillingMeta' => $planBillingMeta ?? collect(),
        'canProvision' => $canProvision ?? false,
        'provisionProgress' => $provisionProgress ?? [],
        'provisioningQueued' => $provisioningQueued ?? false,
        'dnsService' => $dnsService ?? null,
        'domainActivityLog' => $domainActivityLog ?? [],
    ])

    <div class="tenant-detail-grid">
        {{-- Overview --}}
        <div class="card tenant-detail-card">
            <h2 class="tenant-detail-heading">Overview</h2>
            <table class="detail-table">
                @include('admin.tenants._detail-row', ['label' => 'ID', 'value' => $tenant->id])
                @include('admin.tenants._detail-row', ['label' => 'Legal / company name', 'value' => $tenant->name])
                <tr>
                    <th scope="row">Subdomain slug</th>
                    <td>
                        <div class="cell-copyable">
                            <code class="code-pill">{{ $tenant->slug }}</code>
                            @include('admin.partials.copy-btn', ['text' => $tenant->slug, 'title' => 'Copy slug'])
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">CRM domain</th>
                    <td>
                        @if($crmHost)
                            <div class="cell-copyable cell-copyable-stack">
                                <code>{{ $crmHost }}</code>
                                <span class="cell-copy-actions">
                                    @include('admin.partials.copy-btn', ['text' => $crmHost, 'title' => 'Copy domain'])
                                    @if($crmFullUrl)
                                        @include('admin.partials.copy-btn', ['text' => $crmFullUrl, 'title' => 'Copy full URL', 'label' => 'URL'])
                                    @endif
                                </span>
                            </div>
                        @else
                            <span class="detail-empty">—</span>
                        @endif
                    </td>
                </tr>
                @if($crmFullUrl)
                <tr>
                    <th scope="row">Full path</th>
                    <td>
                        <div class="cell-copyable">
                            <a href="{{ $crmFullUrl }}" target="_blank" rel="noopener">{{ $crmFullUrl }}</a>
                            @include('admin.partials.copy-btn', ['text' => $crmFullUrl, 'title' => 'Copy full URL'])
                        </div>
                    </td>
                </tr>
                @endif
                @include('admin.tenants._detail-row', ['label' => 'Registered', 'value' => $tenant->created_at?->format('d M Y, H:i')])
                @include('admin.tenants._detail-row', ['label' => 'Last updated', 'value' => $tenant->updated_at?->format('d M Y, H:i')])
            </table>
        </div>

        {{-- Company --}}
        <div class="card tenant-detail-card">
            <h2 class="tenant-detail-heading">Company</h2>
            <table class="detail-table">
                @include('admin.tenants._detail-row', ['label' => 'Business type', 'value' => $tenant->business_type])
                @include('admin.tenants._detail-row', [
                    'label' => 'Website',
                    'html' => $tenant->company_website
                        ? '<a href="'.e($tenant->company_website).'" target="_blank" rel="noopener">'.e($tenant->company_website).'</a>'
                        : null,
                ])
                @include('admin.tenants._detail-row', ['label' => 'Address', 'value' => $tenant->address_line])
                @include('admin.tenants._detail-row', ['label' => 'City', 'value' => $tenant->city])
                @include('admin.tenants._detail-row', ['label' => 'State / province', 'value' => $tenant->state])
                @include('admin.tenants._detail-row', ['label' => 'Country', 'value' => $tenant->country])
            </table>
        </div>

        {{-- Contact --}}
        <div class="card tenant-detail-card">
            <h2 class="tenant-detail-heading">Primary contact</h2>
            <table class="detail-table">
                @include('admin.tenants._detail-row', ['label' => 'Full name', 'value' => $tenant->contact_name])
                @include('admin.tenants._detail-row', ['label' => 'Designation', 'value' => $tenant->contact_designation])
                @include('admin.tenants._detail-row', [
                    'label' => 'Work email',
                    'html' => $tenant->contact_email
                        ? '<a href="mailto:'.e($tenant->contact_email).'">'.e($tenant->contact_email).'</a>'
                        : null,
                ])
                @include('admin.tenants._detail-row', [
                    'label' => 'Phone',
                    'html' => $tenant->contact_phone
                        ? '<a href="tel:'.e(preg_replace('/\s+/', '', $tenant->contact_phone)).'">'.e($tenant->contact_phone).'</a>'
                        : null,
                ])
            </table>
        </div>

        {{-- Branding --}}
        <div class="card tenant-detail-card tenant-detail-card-branding">
            <h2 class="tenant-detail-heading">Branding</h2>
            <table class="detail-table">
                @include('admin.tenants._detail-row', ['label' => 'CRM display name', 'value' => $tenant->brand_name])
                @include('admin.tenants._detail-row', [
                    'label' => 'Support email',
                    'html' => $tenant->support_email
                        ? '<a href="mailto:'.e($tenant->support_email).'">'.e($tenant->support_email).'</a>'
                        : null,
                ])
                @include('admin.tenants._detail-row', [
                    'label' => 'Favicon URL',
                    'html' => $tenant->favicon_url
                        ? '<a href="'.e($tenant->favicon_url).'" target="_blank" rel="noopener">'.e($tenant->favicon_url).'</a>'
                        : null,
                ])
                @if($tenant->primary_color)
                    @include('admin.tenants._detail-row', [
                        'label' => 'Primary colour',
                        'html' => '<span class="color-swatch" style="background:'.e($tenant->primary_color).'"></span> '.e($tenant->primary_color),
                    ])
                @else
                    @include('admin.tenants._detail-row', ['label' => 'Primary colour', 'value' => null])
                @endif
            </table>
            @if($tenant->logo_url)
                <div class="tenant-logo-block">
                    <p class="detail-subheading">Logo</p>
                    <div class="logo-preview-frame" style="--logo-aspect: {{ config('website.logo.aspect_width') }}/{{ config('website.logo.aspect_height') }}; max-width:360px">
                        <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->brand_name ?? $tenant->name }} logo">
                    </div>
                    <a href="{{ $tenant->logo_url }}" target="_blank" rel="noopener" class="detail-link">Open full image</a>
                </div>
            @endif
        </div>

        {{-- Database --}}
        <div class="card tenant-detail-card span-full">
            <h2 class="tenant-detail-heading">Database (CRM)</h2>
            @include('admin.tenants._database-credentials', ['canProvision' => $canProvision ?? false])
        </div>

        {{-- Notes --}}
        <div class="card tenant-detail-card span-full">
            <h2 class="tenant-detail-heading">Registration notes</h2>
            @if($tenant->registration_notes)
                <div class="detail-notes">{{ $tenant->registration_notes }}</div>
            @else
                <p class="detail-empty-block">No notes provided.</p>
            @endif
        </div>

        @if(isset($subdomainCheckStats) && $subdomainCheckStats->isNotEmpty())
        <div class="card tenant-detail-card span-full">
            <h2 class="tenant-detail-heading">Subdomain resolve checks</h2>
            <p class="form-hint" style="margin-top:0">
                How many times CRM requested this company via <code>/api/v1/tenant/resolve</code>.
                <a href="{{ route('admin.subdomain-checks.index', ['q' => $tenant->slug]) }}">View all logs</a>
            </p>
            <div class="table-scroll">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Host</th>
                            <th>Total checks</th>
                            <th>Allowed</th>
                            <th>Denied</th>
                            <th>Last checked</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subdomainCheckStats as $stat)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.subdomain-checks.show', ['host' => $stat->host]) }}">
                                        <code>{{ $stat->host }}</code>
                                    </a>
                                </td>
                                <td><strong>{{ number_format($stat->check_count) }}</strong></td>
                                <td>{{ number_format($stat->allowed_count) }}</td>
                                <td>{{ number_format($stat->denied_count + $stat->not_found_count) }}</td>
                                <td>{{ $stat->last_checked_at?->format('d M Y H:i:s') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($resolveUrl)
        <div class="card tenant-detail-card span-full">
            <h2 class="tenant-detail-heading">Tenant resolve API</h2>
            <p class="form-hint" style="margin-top:0">Call from CRM or curl (active tenant):</p>
            <code class="detail-code-block">{{ $resolveUrl }}</code>
        </div>
        @endif

        @if($tenant->operationLogs->isNotEmpty())
        <div class="card tenant-detail-card span-full">
            <h2 class="tenant-detail-heading">Recent activity</h2>
            @if(master_can_view_activity_logs())
                <p class="form-hint" style="margin-top:0">
                    <a href="{{ route('admin.logs.index', ['channel' => 'database']) }}">View activity logs</a>
                    · <code class="code-pill code-pill-muted">{{ route('admin.logs.index', ['channel' => 'database']) }}</code>
                </p>
            @endif
            <div class="table-scroll">
                <table class="detail-table detail-table-logs">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenant->operationLogs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $log->action }}</td>
                                <td><span class="badge badge-{{ $log->status === 'success' ? 'active' : ($log->status === 'failed' ? 'failed' : 'pending') }}">{{ $log->status }}</span></td>
                                <td>{{ Str::limit($log->message, 80) }}</td>
                                <td>{{ $log->user?->email ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/copy-to-clipboard.js') }}"></script>
    @if($provisioningQueued ?? false)
        @include('partials.tenant-provision-echo')
        <script src="{{ asset('js/tenant-provision-live.js') }}"></script>
    @endif
    <script src="{{ asset('js/tenant-manage-subscription.js') }}"></script>
@endpush
@endsection
