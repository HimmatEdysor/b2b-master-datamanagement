@extends('layouts.admin')

@php
    use App\Support\TenantUrl;
    $crmHost = TenantUrl::hostForTenant($tenant);
    $crmFullUrl = TenantUrl::urlForTenant($tenant);
@endphp

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
        Save these credentials now — the password is stored encrypted and is only shown here once.
        <dl class="tenant-db-credentials-list">
            <dt>Username</dt>
            <dd><code>{{ $dbCreds['username'] }}</code> @include('admin.partials.copy-btn', ['text' => $dbCreds['username'], 'title' => 'Copy username'])</dd>
            <dt>Password</dt>
            <dd><code>{{ $dbCreds['password'] }}</code> @include('admin.partials.copy-btn', ['text' => $dbCreds['password'], 'title' => 'Copy password'])</dd>
        </dl>
    </div>
@endif
<div class="tenant-show">
    <div class="page-toolbar tenant-show-toolbar">
        <div>
            <p class="page-lead">
                <span class="badge badge-{{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span>
                <code class="code-pill" style="margin-left:8px">{{ $tenant->slug }}</code>
            </p>
        </div>
        <div class="tenant-show-actions">
            <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline btn-sm">← All companies</a>
            <a href="{{ route('admin.tenants.edit', $tenant) }}" class="btn btn-primary btn-sm">Edit</a>
            @if($tenant->isPending())
                <form method="POST" action="{{ route('admin.tenants.reject', $tenant) }}" class="inline-form" onsubmit="return confirm('Reject this registration?');">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Reject</button>
                </form>
            @elseif($tenant->status === 'active')
                <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" class="inline-form" onsubmit="return confirm('Suspend this company?');">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Suspend</button>
                </form>
            @elseif(in_array($tenant->status, ['suspended', 'failed'], true))
                <form method="POST" action="{{ route('admin.tenants.reactivate', $tenant) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Reactivate</button>
                </form>
            @endif
        </div>
    </div>

    @if($tenant->isPending())
    <div class="card admin-form-card tenant-provision-card" style="margin-bottom: 1.25rem;">
        <h2 class="tenant-detail-heading" style="margin-bottom: 0.75rem;">Provision tenant database</h2>
        <p class="page-lead" style="margin-bottom: 1rem;">
            Clones from template <code>{{ config('master.template_database') }}</code> into
            <code>{{ $tenant->database_name }}</code>. Choose whether to copy structure only or all data.
        </p>
        <form method="POST" action="{{ route('admin.tenants.approve', $tenant) }}" class="admin-form"
              data-clone-db-prompt id="tenantApproveForm">
            @csrf
            @include('admin.tenants._clone-database-options', ['withDataChecked' => old('with_data')])
            <div class="form-actions" style="margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">Approve & provision database</button>
            </div>
        </form>
    </div>
    @endif

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
                @include('admin.tenants._detail-row', ['label' => 'Status', 'value' => ucfirst($tenant->status)])
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

        {{-- Subscription --}}
        <div class="card tenant-detail-card">
            <h2 class="tenant-detail-heading">Subscription</h2>
            <table class="detail-table">
                @include('admin.tenants._detail-row', ['label' => 'Plan', 'value' => $tenant->subscriptionPlan?->name])
                @include('admin.tenants._detail-row', ['label' => 'Subscription status', 'value' => $tenant->subscription_status ? ucfirst($tenant->subscription_status) : null])
                @include('admin.tenants._detail-row', [
                    'label' => 'Expires',
                    'value' => $tenant->subscription_expires_at?->format('d M Y'),
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

        {{-- Domains --}}
        <div class="card tenant-detail-card span-full">
            <h2 class="tenant-detail-heading">Domains & CRM URLs</h2>
            @include('admin.tenants._domains-manage')
        </div>

        {{-- Database --}}
        <div class="card tenant-detail-card">
            <h2 class="tenant-detail-heading">Database (CRM)</h2>
            <table class="detail-table">
                @include('admin.tenants._detail-row', ['label' => 'Database name', 'value' => $tenant->database_name])
                @include('admin.tenants._detail-row', ['label' => 'Host', 'value' => $tenant->databaseHost()])
                @include('admin.tenants._detail-row', ['label' => 'Port', 'value' => $tenant->database_port])
                @include('admin.tenants._detail-row', [
                    'label' => 'Username',
                    'value' => $tenant->database_username
                        ?: ($tenant->isActive() ? null : 'Created on approval (same as database name)'),
                ])
                @include('admin.tenants._detail-row', [
                    'label' => 'Password',
                    'value' => $tenant->database_password
                        ? 'Stored encrypted (shown once after approval)'
                        : ($tenant->isActive() ? null : 'Generated on approval'),
                ])
                @include('admin.tenants._detail-row', ['label' => 'S3 folder', 'value' => $tenant->slug])
            </table>
        </div>

        {{-- Provisioning & approval --}}
        <div class="card tenant-detail-card">
            <h2 class="tenant-detail-heading">Provisioning & approval</h2>
            <table class="detail-table">
                @include('admin.tenants._detail-row', [
                    'label' => 'Approved at',
                    'value' => $tenant->approved_at?->format('d M Y, H:i'),
                ])
                @include('admin.tenants._detail-row', [
                    'label' => 'Approved by',
                    'value' => $tenant->approver?->name ?? $tenant->approver?->email,
                ])
                @include('admin.tenants._detail-row', [
                    'label' => 'Rejected at',
                    'value' => $tenant->rejected_at?->format('d M Y, H:i'),
                ])
                @include('admin.tenants._detail-row', ['label' => 'Migration status', 'value' => $tenant->migration_status])
                @include('admin.tenants._detail-row', [
                    'label' => 'Last migration',
                    'value' => $tenant->last_migration_at?->format('d M Y, H:i'),
                ])
            </table>
            @if($tenant->provision_error)
                <div class="detail-alert detail-alert-error">
                    <strong>Provision error</strong>
                    <pre class="detail-pre">{{ $tenant->provision_error }}</pre>
                </div>
            @endif
            @if($tenant->migration_error)
                <div class="detail-alert detail-alert-error">
                    <strong>Migration error</strong>
                    <pre class="detail-pre">{{ $tenant->migration_error }}</pre>
                </div>
            @endif
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
            @if(master_can('logs.view'))
                <p class="form-hint" style="margin-top:0"><a href="{{ route('admin.logs.index', ['channel' => 'database']) }}">View full activity logs →</a></p>
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
@endpush
@endsection
