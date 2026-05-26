@php
    $progress = $provisionProgress ?? [];
@endphp
<div class="tenant-provision-ready" id="tenant-provision">
    <h3 class="detail-subheading">Database provisioning complete</h3>
    <ul class="tenant-provision-checklist">
        <li class="is-done">
            <span class="tenant-provision-check-icon">✓</span>
            Database <code>{{ $tenant->database_name }}</code> cloned from template
        </li>
        <li class="is-done">
            <span class="tenant-provision-check-icon">✓</span>
            @if(\App\Support\TenantDbAdmin::usesSharedTenantCredentials())
                Shared MySQL user <code>{{ \App\Support\TenantDbAdmin::username() }}</code> (all tenant DBs)
            @else
                Dedicated MySQL user &amp; password saved
            @endif
        </li>
        <li class="is-done">
            <span class="tenant-provision-check-icon">✓</span>
            Default CRM admin login created
        </li>
    </ul>
    @if($statusNeedsFix ?? false)
        <div class="detail-alert detail-alert-warning" role="alert">
            <strong>Database is ready</strong> but company status is still <strong>{{ $tenant->status }}</strong>.
            Click below to set <strong>Active</strong> and clear the old error.
        </div>
        @can('tenants.approve')
            <form method="POST" action="{{ route('admin.tenants.reconcile-provisioning', $tenant) }}" class="inline-form">
                @csrf
                <button type="submit" class="btn btn-primary">Set company to Active</button>
            </form>
        @endcan
    @else
        <p class="db-credentials-status db-credentials-status-ok" style="margin-top:0">
            CRM database and login are ready. Use <a href="#tenant-crm-login">CRM login</a> below or open domains.
        </p>
    @endif
</div>
