@php
    $progress = $provisionProgress ?? [];
    $cloneDone = $progress['clone_done'] ?? false;
    $mysqlDone = $progress['mysql_user_done'] ?? false;
    $crmDone = $progress['crm_admin_done'] ?? false;
    $canResume = $progress['can_resume'] ?? false;
    $resume = $tenant->status === 'provisioning'
        || ($tenant->status === 'active' && ! $tenant->isDatabaseProvisioned())
        || $canResume;
    $isQueued = $provisioningQueued ?? ($progress['is_queued'] ?? false);
    $isStalled = $progress['is_stalled'] ?? false;
    $needsRetry = $progress['needs_retry'] ?? false;
    $stageLabel = $progress['stage_label'] ?? 'Provisioning';
    $provisionError = $progress['error_message'] ?? $tenant->provision_error;
    $adminDbError = $progress['admin_db_error'] ?? null;
    $isFailed = $tenant->status === 'failed' || ($provisionError && ($isStalled || $needsRetry));
    $heading = match (true) {
        $isFailed => 'Retry database provisioning',
        $resume => 'Complete database provisioning',
        default => 'Approve & provision database',
    };
    $retryLabel = $canResume && $cloneDone
        ? 'Retry — finish MySQL user & CRM login'
        : ($canResume ? 'Retry — finish provisioning (no re-clone)' : 'Retry — clone database & provision');
    $buttonLabel = $isQueued
        ? 'Queued…'
        : ($isFailed || $needsRetry ? $retryLabel : ($resume ? 'Queue provisioning' : 'Approve & queue provisioning'));
@endphp
<div class="tenant-provision-block" id="tenant-provision">
    <h3 class="detail-subheading">{{ $heading }}</h3>

    @if(! master_queue_uses_background_worker())
        <div class="detail-alert detail-alert-error" role="alert" style="margin-bottom:12px">
            <strong>Queue is set to <code>sync</code></strong> — provisioning runs in the browser request and often times out.
            Set <code>QUEUE_CONNECTION=redis</code> in <code>.env</code>, restart <code>php artisan horizon</code>, then click Retry.
        </div>
    @endif

    @if($adminDbError)
        <div class="detail-alert detail-alert-error" role="alert" style="margin-bottom:12px">
            <strong>Tenant database admin connection failed</strong> — {{ $adminDbError }}
        </div>
    @endif

    @if($resume || ($canProvision ?? false))
        <ul class="tenant-provision-checklist" aria-label="Provisioning steps">
            <li class="{{ $cloneDone ? 'is-done' : ($needsRetry && ! $cloneDone ? 'is-failed' : '') }}">
                <span class="tenant-provision-check-icon">{{ $cloneDone ? '✓' : ($needsRetry ? '✗' : '○') }}</span>
                Database <code>{{ $tenant->database_name }}</code> cloned from template
            </li>
            <li class="{{ $mysqlDone ? 'is-done' : ($needsRetry && ! $mysqlDone ? 'is-failed' : '') }}">
                <span class="tenant-provision-check-icon">{{ $mysqlDone ? '✓' : ($needsRetry ? '✗' : '○') }}</span>
                Dedicated MySQL user &amp; password saved
            </li>
            <li class="{{ $crmDone ? 'is-done' : ($needsRetry && ! $crmDone ? 'is-failed' : '') }}">
                <span class="tenant-provision-check-icon">{{ $crmDone ? '✓' : ($needsRetry ? '✗' : '○') }}</span>
                Default CRM admin login created
            </li>
        </ul>
    @endif

    @if($provisionError && ($isStalled || $isFailed || $needsRetry))
        <div class="detail-alert detail-alert-error tenant-provision-error" role="alert" id="tenant-provision-error">
            <strong>Provisioning failed</strong>
            <p class="tenant-provision-error-message" style="margin:8px 0 0;font-family:ui-monospace,monospace;font-size:0.9em;white-space:pre-wrap">{{ $provisionError }}</p>
        </div>
    @elseif($isQueued)
        <div id="tenant-provision-poll" class="tenant-provision-poll" data-status-url="{{ route('admin.tenants.provisioning-status', $tenant) }}">
            <p class="db-credentials-status db-credentials-status-pending" style="margin-top:0">
                <strong>Provisioning in progress</strong> —
                <span id="tenant-provision-stage-label">{{ $stageLabel }}</span>.
                This page refreshes when finished.
            </p>
            <p id="tenant-provision-poll-error" class="detail-alert detail-alert-error" role="alert" hidden style="margin-top:8px"></p>
            @if(master_can_view_horizon())
                <p class="form-hint tenant-provision-worker-hint" style="margin-top:8px">
                    <a href="{{ url('/horizon') }}" target="_blank" rel="noopener">Horizon dashboard</a> for queue details.
                </p>
            @endif
        </div>
    @elseif($isStalled || $isFailed)
        <div class="detail-alert detail-alert-error tenant-provision-stalled" role="alert">
            <strong>Provisioning did not finish.</strong>
            @if($cloneDone && ! $mysqlDone && ! $crmDone)
                <p style="margin:8px 0 0">Database clone succeeded; MySQL user and CRM admin were not created. Click <strong>Retry</strong> to finish those steps.</p>
            @elseif(! $cloneDone)
                <p style="margin:8px 0 0">Database was not cloned from the template. Click <strong>Retry</strong> to run provisioning again.</p>
            @else
                <p style="margin:8px 0 0">Some steps did not complete. Click <strong>Retry</strong> to run only what is still missing.</p>
            @endif
        </div>
        @if($tenant->approved_at)
            <p class="form-hint" style="margin:4px 0 12px">
                Approved {{ $tenant->approved_at->format('d M Y, H:i') }}
                @if($tenant->approver)
                    by {{ $tenant->approver->name ?? $tenant->approver->email }}
                @endif
            </p>
        @endif
    @elseif($tenant->status === 'pending' || $isStalled || $isFailed)
        <p class="form-hint" style="margin-top:0;margin-bottom:12px">
            @if(config('master.tenant_provision_sync_local'))
                <strong>Local:</strong> use <strong>Provision now</strong> for a fast schema + reference seed (~15–40s, no Horizon).
                Use <strong>Queue in background</strong> when Horizon is running.
            @else
                Clones schema from template <code>{{ config('master.template_database') }}</code> into
                <code>{{ $tenant->database_name }}</code>, then copies rows only from
                <code>tenant_seed_tables</code> in config (not the full template database).
            @endif
            Do not stop Horizon (^C) while a queued job is <strong>RUNNING</strong>.
        </p>
    @endif

    @can('tenants.approve')
        @if(! $isQueued)
            <form method="POST" action="{{ route('admin.tenants.approve', $tenant) }}" class="admin-form tenant-provision-retry-form"
                  id="tenantApproveForm">
                @csrf
                @if(! $canResume)
                    @include('admin.tenants._clone-database-info')
                @else
                    @if($needsRetry && $cloneDone)
                        <p class="form-hint">Retry will <strong>not</strong> re-clone — only MySQL user, seed, and CRM admin.</p>
                    @endif
                @endif
                <div class="form-actions tenant-provision-retry-actions">
                    @if(config('master.tenant_provision_sync_local') && ! ($canResume && $cloneDone))
                        <button type="submit" name="run_sync" value="1" class="btn btn-primary">
                            {{ $needsRetry ? 'Retry now (fast)' : 'Provision now (~20s)' }}
                        </button>
                        <button type="submit" class="btn btn-outline">
                            {{ $isFailed || $needsRetry ? 'Retry via queue' : 'Queue in background' }}
                        </button>
                        <span class="form-hint" style="margin:0;flex:1 1 100%">
                            Fast path runs immediately (schema only). Queue needs <code>php artisan horizon</code> and does not block the browser.
                        </span>
                    @else
                        <button type="submit" class="btn btn-primary">{{ $buttonLabel }}</button>
                    @endif
                </div>
            </form>
        @else
            <p class="form-hint">Provisioning is running — this page will refresh automatically.</p>
        @endif
    @endcan
</div>
