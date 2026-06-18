@extends('layouts.admin')

@section('title', 'Provisioning — '.$tenant->name)
@section('page-title', 'Provisioning database')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">
        Setting up <strong>{{ $tenant->name }}</strong> (<code>{{ $tenant->slug }}</code>).
        Clone and seed run in the background — this page updates automatically.
    </p>
    <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline btn-sm">← Companies</a>
</div>

<div class="card admin-form-card tenant-provision-screen">
    @php
        $stageLabel = $progress['stage_label'] ?? 'Provisioning';
    @endphp

    <div id="tenant-provision-poll" class="tenant-provision-poll"
         data-tenant-id="{{ $tenant->id }}"
         data-use-reverb="{{ master_broadcast_uses_reverb() ? '1' : '0' }}"
         data-status-url="{{ route('admin.tenants.provisioning-status', $tenant) }}"
         data-done-url="{{ route('admin.tenants.show', $tenant) }}">

        <p class="db-credentials-status db-credentials-status-pending" style="margin-top:0">
            <strong>Step <span id="tenant-provision-step-num">1</span> of 4</strong> —
            <span id="tenant-provision-stage-label">{{ $stageLabel }}</span>
            <span id="tenant-provision-percent" class="tenant-provision-percent">0%</span>
        </p>

        <div class="tenant-provision-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Provisioning progress">
            <div class="tenant-provision-progress-bar" id="tenant-provision-progress-bar" style="width:0%"></div>
        </div>

        <ol class="tenant-provision-steps" id="tenant-provision-steps">
            <li data-step="preparing" class="is-active">Save company &amp; queue job</li>
            <li data-step="cloning">Clone database schema</li>
            <li data-step="seeding">Seed reference data</li>
            <li data-step="crm_admin">Create CRM admin</li>
        </ol>

        <p id="tenant-provision-stage-detail" class="form-hint tenant-provision-stage-detail" hidden></p>

        <p class="form-hint" style="margin-top:8px">
            @if(master_broadcast_uses_reverb())
                Progress updates every few seconds; Reverb adds instant updates when <code>php artisan reverb:start</code> is running.
            @else
                Set <code>BROADCAST_CONNECTION=reverb</code> and run <code>php artisan reverb:start</code> for instant updates.
                Status still refreshes automatically every few seconds.
            @endif
        </p>

        <p id="tenant-provision-poll-error" class="detail-alert detail-alert-error" role="alert" hidden style="margin-top:8px"></p>

        @if(master_can_view_horizon())
            <p class="form-hint tenant-provision-worker-hint" style="margin-top:12px">
                <a href="{{ url('/horizon') }}" target="_blank" rel="noopener">Horizon dashboard</a> — job queue <code>{{ config('master.tenant_provision_queue', 'provisioning') }}</code>
            </p>
        @endif
    </div>
</div>
@endsection

@push('scripts')
    @include('partials.tenant-provision-echo')
    <script src="{{ asset('js/tenant-provision-live.js') }}"></script>
@endpush
