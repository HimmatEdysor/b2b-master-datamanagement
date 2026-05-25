@php
    use App\Services\TenantDatabaseUserService;
    use App\Services\TenantProvisionerService;
    use App\Support\TenantDbAdmin;

    $sharedDbUser = TenantDbAdmin::usesSharedTenantCredentials();
    $expectedUsername = $tenant->database_name
        ? ($sharedDbUser
            ? TenantDbAdmin::username()
            : app(TenantDatabaseUserService::class)->deriveUsername($tenant->database_name))
        : null;
    $hasStoredCreds = $tenant->isDatabaseProvisioned();
    $hasPartialCreds = $tenant->hasPartialDatabaseCredentials();
    $isPending = $tenant->isPending();
    $canProvision = $canProvision ?? app(TenantProvisionerService::class)->canApprove($tenant);
    $plannedHost = TenantDbAdmin::host();
    $plannedPort = TenantDbAdmin::port();
    $approvalNeverFinished = $tenant->isActive() && $tenant->approved_at === null;
    $showLiveEndpoint = $hasStoredCreds
        || $tenant->approved_at
        || filled($tenant->database_host)
        || $tenant->isActive();
    $dbHost = $showLiveEndpoint ? ($tenant->database_host ?: $plannedHost) : null;
    $dbPort = $showLiveEndpoint ? ($tenant->database_port ?: $plannedPort) : null;
@endphp

<div id="tenant-database-credentials">
@if($sharedDbUser)
    <p class="form-hint db-credentials-status" style="margin-bottom:12px">
        <strong>RDS shared MySQL user:</strong> every tenant uses <code>{{ TenantDbAdmin::username() }}</code>
        with access via <code>{{ TenantDbAdmin::tenantDatabaseGrantPattern() }}</code>.
        Change password in <a href="{{ route('admin.settings.edit') }}">Web settings</a> (not per-company <code>CREATE USER</code>).
    </p>
@endif
@if($hasStoredCreds)
    <p class="db-credentials-status db-credentials-status-ok">
        <strong>Database credentials are saved.</strong>
        Password is stored encrypted and can be viewed anytime below (Show / Copy).
    </p>
@elseif($hasPartialCreds)
    <p class="db-credentials-status db-credentials-status-warn">
        <strong>Username is saved but password is missing.</strong>
        Use <strong>Create / reset database user</strong> below to generate and store a new password.
    </p>
@elseif($approvalNeverFinished || ($tenant->isActive() && ! $hasStoredCreds))
    <p class="db-credentials-status db-credentials-status-warn">
        <strong>Why you see this:</strong>
        Company status is <span class="badge badge-active">Active</span> but provisioning never finished
        @if($tenant->approved_at)
            (approval started but MySQL user / password were not saved).
        @else
            (<strong>Approved at</strong> is empty — <code>Approve &amp; provision</code> was not completed, or status was changed manually).
        @endif
        <a href="#tenant-provision">Complete database provisioning</a> in Manage company, or use the button below.
    </p>
@elseif($canProvision)
    <p class="db-credentials-status db-credentials-status-pending">
        <strong>Database not provisioned yet.</strong>
        Use <a href="#tenant-provision">Manage company → provisioning</a> to create <code>{{ $tenant->database_name }}</code> and the MySQL user.
    </p>
@endif

<table class="detail-table">
    @include('admin.tenants._detail-row', ['label' => 'Database name', 'value' => $tenant->database_name])

    <tr>
        <th scope="row">Host</th>
        <td>
            @if($dbHost)
                <code class="code-pill">{{ $dbHost }}</code>
                @if($isPending && ! $tenant->approved_at)
                    <span class="form-hint" style="display:block;margin-top:4px">From master config — confirmed on approval.</span>
                @endif
            @else
                <span class="detail-muted">{{ $plannedHost }} <span class="detail-empty">(set on approval)</span></span>
            @endif
        </td>
    </tr>

    <tr>
        <th scope="row">Port</th>
        <td>
            @if($dbPort)
                {{ $dbPort }}
            @else
                <span class="detail-muted">{{ $plannedPort }} <span class="detail-empty">(set on approval)</span></span>
            @endif
        </td>
    </tr>

    <tr>
        <th scope="row">{{ $tenant->hasDatabaseUsername() ? 'Username' : 'Planned username' }}</th>
        <td>
            @if($tenant->hasDatabaseUsername())
                <div class="cell-copyable">
                    <code class="code-pill">{{ $tenant->database_username }}</code>
                    @include('admin.partials.copy-btn', [
                        'text' => $tenant->database_username,
                        'title' => 'Copy database username',
                    ])
                </div>
            @elseif($expectedUsername)
                <div class="cell-copyable">
                    <code class="code-pill code-pill-muted">{{ $expectedUsername }}</code>
                    @include('admin.partials.copy-btn', [
                        'text' => $expectedUsername,
                        'title' => 'Copy planned username',
                    ])
                </div>
                <p class="form-hint" style="margin:6px 0 0">
                    Not created in MySQL yet — run
                    <a href="#tenant-provision">Run provisioning</a> in Manage company, or <strong>Create / reset database user</strong> below.
                </p>
            @else
                <span class="detail-empty">—</span>
            @endif
        </td>
    </tr>

    <tr>
        <th scope="row">Password</th>
        <td>
            @if($hasStoredCreds)
                @include('admin.partials.password-field', [
                    'inputId' => 'tenant-db-password',
                    'value' => $tenant->database_password,
                    'toggleAttr' => 'data-toggle-db-password',
                    'copyTitle' => 'Copy database password',
                    'hint' => 'Stored encrypted in master DB. CRM resolve API uses this for the tenant connection.',
                ])
            @else
                <span class="detail-empty">—</span>
                <p class="form-hint" style="margin:6px 0 0">
                    Generated when provisioning creates the MySQL user. Use the actions below to create or set a password.
                </p>
            @endif
        </td>
    </tr>

    @include('admin.tenants._detail-row', [
        'label' => 'S3 folder',
        'value' => $tenant->s3_folder ?: ($tenant->slug ?: null),
    ])
</table>

@can('tenants.edit')
    <div class="db-credentials-actions">
        @if($tenant->hasDatabaseUsername() && $tenant->database_name)
            <form method="POST" action="{{ route('admin.tenants.database-password', $tenant) }}" class="db-password-update-form">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="tenant-db-password-new">Set new MySQL password</label>
                    <input type="password" id="tenant-db-password-new" name="password" class="form-control"
                           minlength="8" maxlength="128" required autocomplete="new-password"
                           placeholder="Min. 8 characters">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Update MySQL password</button>
                <p class="form-hint">
                    @if($sharedDbUser)
                        Updates the stored copy for CRM resolve; MySQL password is shared — change it in Web settings.
                    @else
                        Runs <code>ALTER USER</code> on the server and saves the password on this company record.
                    @endif
                </p>
            </form>
        @endif

        @if($tenant->database_name && ! $isPending)
            <form method="POST" action="{{ route('admin.tenants.database-user', $tenant) }}" class="inline-form db-password-regenerate-form"
                  onsubmit="return confirm('Create or reset the dedicated MySQL user for {{ $tenant->database_name }}? Any existing password will be replaced with a new random one.');">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Generate new MySQL user password</button>
            </form>
            @if(! $hasStoredCreds)
                <span class="form-hint" style="display:block;margin-top:6px">Creates <code>{{ $expectedUsername }}</code> if missing.</span>
            @endif
        @endif
    </div>
@endcan
</div>

@include('admin.partials.password-toggle-script')
