@extends('layouts.admin')

@section('title', 'Web settings')
@section('page-title', 'Web settings')

@section('content')
@php
    $tenantDbAudit = session('tenant_db_audit');
@endphp
<div class="page-toolbar">
    <p class="page-lead">
        Configure tenant URLs, DNS, database provisioning, and CRM defaults here instead of editing <code>.env</code>.
        Saved values override environment variables. Leave a field empty and save to fall back to <code>.env</code> again.
    </p>
</div>

@if($tenantDbAudit)
    <div class="card admin-form-card {{ ($tenantDbAudit['ok'] ?? false) ? 'detail-alert-success' : 'detail-alert-error' }}" role="alert" style="margin-bottom:1.25rem">
        <h2 class="tenant-detail-heading">Tenant MySQL admin check</h2>
        <p style="margin:0 0 12px"><strong>{{ $tenantDbAudit['summary'] ?? '' }}</strong></p>
        <ul class="tenant-provision-checklist" style="margin:0 0 12px">
            @foreach($tenantDbAudit['checks'] ?? [] as $check)
                <li>
                    <span class="tenant-provision-check-icon">{{ ($check['ok'] ?? false) ? '✓' : '✗' }}</span>
                    <span><code>{{ $check['name'] ?? '' }}</code> — {{ $check['detail'] ?? '' }}</span>
                </li>
            @endforeach
        </ul>
        <p class="form-hint" style="margin:0">
            Host <code>{{ $tenantDbAudit['config']['host'] ?? '' }}</code>:{{ $tenantDbAudit['config']['port'] ?? '' }},
            user <code>{{ $tenantDbAudit['config']['user'] ?? '' }}</code>,
            password from <code>{{ $tenantDbAudit['config']['password_source'] ?? '' }}</code>.
        </p>
        @if(! ($tenantDbAudit['ok'] ?? false))
            <details style="margin-top:12px">
                <summary style="cursor:pointer;font-weight:600">RDS setup SQL (run as RDS master user)</summary>
                <pre style="margin:12px 0 0;padding:12px;background:#1e1e1e;color:#e8e8e8;border-radius:6px;overflow:auto;font-size:0.85em;white-space:pre-wrap">{{ $tenantDbAudit['setup_sql'] ?? '' }}</pre>
            </details>
            <p class="form-hint" style="margin-top:12px">
                After fixing MySQL, update the admin password here if needed, save, then test again.
                On the server: <code>php artisan config:clear</code> and <code>php artisan horizon:terminate</code>.
            </p>
        @endif
    </div>
@endif

<div class="card admin-form-card master-settings-env-card">
    <h2 class="tenant-detail-heading">Still configured in <code>.env</code> only</h2>
    <p class="form-hint">Secrets and infrastructure keys stay in the environment file for security.</p>
    <table class="detail-table">
        @foreach($envOnly as $envKey => $label)
            <tr>
                <th scope="row">{{ $label }}</th>
                <td><code>{{ $envKey }}</code>
                    @if(env($envKey))
                        <span class="badge badge-active">set</span>
                    @else
                        <span class="badge badge-pending">not set</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" class="admin-form master-settings-form">
    @csrf
    @method('PUT')

    @foreach($sections as $sectionKey => $section)
        <div class="card admin-form-card master-settings-section">
            <h2 class="tenant-detail-heading">{{ $section['label'] }}</h2>
            @if(! empty($section['description']))
                <p class="form-hint section-lead">{{ $section['description'] }}</p>
            @endif

            @if($sectionKey === 'database')
                @php
                    $sharedOn = \App\Support\TenantDbAdmin::usesSharedTenantCredentials();
                @endphp
                <div class="detail-alert {{ $sharedOn ? 'detail-alert-success' : 'detail-alert-error' }}" role="status" style="margin:0 0 1rem">
                    <strong>Shared MySQL user (RDS):</strong>
                    @if($sharedOn)
                        Enabled — all companies use <code>{{ \App\Support\TenantDbAdmin::username() }}</code> with <code>{{ \App\Support\TenantDbAdmin::tenantDatabaseGrantPattern() }}</code>.
                    @else
                        Disabled — enable <strong>Use shared MySQL user for all tenants (RDS)</strong> below (or set <code>TENANT_DB_SHARED_CREDENTIALS=true</code> in <code>.env</code>), then save.
                    @endif
                </div>
            @endif

            @if($sectionKey === 'database' && master_can('settings.edit'))
                <div class="form-actions" style="margin:0 0 1rem;padding:0;border:none">
                    <form method="POST" action="{{ route('admin.settings.tenant-db-check') }}" class="inline-form">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Test tenant MySQL connection</button>
                    </form>
                    <span class="form-hint" style="display:inline;margin-left:8px">Same check as <code>php artisan tenant:db-admin-check</code> on this server.</span>
                </div>
            @endif

            @foreach($formState as $fieldKey => $state)
                @if(($state['definition']['section'] ?? '') !== $sectionKey)
                    @continue
                @endif
                @php
                    $def = $state['definition'];
                    $type = $def['type'];
                    $name = $fieldKey;
                    $id = 'setting-'.$name;
                @endphp
                <div class="form-group">
                    <label for="{{ $id }}">
                        {{ $def['label'] }}
                        @if($state['source'] === 'database')
                            <span class="badge badge-active badge-sm">saved</span>
                        @else
                            <span class="badge badge-draft badge-sm">from env</span>
                        @endif
                    </label>

                    @if($type === 'boolean')
                        <label class="checkbox-label">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1"
                                   @checked(old($name, $state['value']))>
                            Enable
                        </label>
                    @elseif($type === 'password')
                        <input type="password" id="{{ $id }}" name="{{ $name }}" class="form-control" autocomplete="new-password"
                               placeholder="{{ \App\Models\MasterSetting::query()->where('key', $name)->exists() ? '•••••••• (leave blank to keep)' : 'From .env if empty' }}">
                    @elseif($type === 'select')
                        <select id="{{ $id }}" name="{{ $name }}" class="form-control">
                            @foreach($def['options'] ?? [] as $optValue => $optLabel)
                                <option value="{{ $optValue }}" @selected(old($name, (string) ($state['value'] ?? '')) === (string) $optValue)>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" id="{{ $id }}" name="{{ $name }}" class="form-control"
                               value="{{ old($name, is_array($state['value']) ? implode(', ', $state['value']) : (string) ($state['value'] ?? '')) }}"
                               autocomplete="off" spellcheck="false">
                    @endif

                    @if(! empty($def['hint']))
                        <p class="form-hint">{{ $def['hint'] }}</p>
                    @endif
                    @error($name)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="form-actions master-settings-actions">
        <button type="submit" class="btn btn-primary">Save web settings</button>
    </div>
</form>
@endsection
