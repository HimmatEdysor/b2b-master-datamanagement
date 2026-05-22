@php
    use App\Support\TenantUrl;

    $statusLabels = config('master.tenant_status_labels', []);
    $subLabels = config('master.subscription_status_labels', []);
    $companyLabel = $statusLabels[$tenant->status] ?? ucfirst($tenant->status);
    $subLabel = $tenant->subscription_status
        ? ($subLabels[$tenant->subscription_status] ?? ucfirst($tenant->subscription_status))
        : 'Not set';
    $crmLoginUrl = TenantUrl::loginUrlForTenant($tenant);
    $crmReady = $tenant->isActive()
        && $tenant->isDatabaseProvisioned()
        && in_array($tenant->subscription_status, ['active', 'trial'], true)
        && ($tenant->subscription_expires_at === null || $tenant->subscription_expires_at->isFuture());
@endphp

<div class="tenant-show-hero card span-full">
    <div class="tenant-show-hero-main">
        <a href="{{ route('admin.tenants.index') }}" class="tenant-show-back">← Company list</a>
        <h1 class="tenant-show-title">{{ $tenant->name }}</h1>
        <p class="tenant-show-subtitle">
            Subdomain <code class="code-pill">{{ $tenant->slug }}</code>
            @if($crmHost)
                · CRM <a href="{{ $crmFullUrl }}" target="_blank" rel="noopener"><code>{{ $crmHost }}</code></a>
            @endif
        </p>
        <div class="tenant-show-badges">
            <span class="badge badge-{{ $tenant->status }}" title="Company status">{{ $companyLabel }}</span>
            <span class="badge badge-{{ in_array($tenant->subscription_status, ['active', 'trial'], true) ? 'active' : 'pending' }}" title="Subscription">
                Plan: {{ $subLabel }}
            </span>
            @if($tenant->subscriptionPlan)
                <span class="badge badge-pending" title="Subscription plan name">{{ $tenant->subscriptionPlan->name }}</span>
            @endif
            @if($tenant->isDatabaseProvisioned())
                <span class="badge badge-active">DB provisioned</span>
            @else
                <span class="badge badge-failed">DB not ready</span>
            @endif
            @if(isset($crmAccess) && !($crmAccess['ok'] ?? false))
                <span class="badge badge-failed" title="{{ $crmAccess['message'] ?? '' }}">CRM blocked</span>
            @elseif($crmReady)
                <span class="badge badge-active">CRM can connect</span>
            @endif
        </div>
        @if(isset($crmAccess) && !($crmAccess['ok'] ?? false))
            <p class="tenant-show-access-warn">{{ $crmAccess['message'] }}</p>
        @endif
    </div>

    <div class="tenant-show-hero-actions">
        <p class="tenant-show-actions-label">Quick actions</p>
        <div class="tenant-action-groups">
            <div class="tenant-action-group">
                <span class="tenant-action-group-title">Profile</span>
                <a href="{{ route('admin.tenants.edit', $tenant) }}" class="btn btn-primary btn-sm">
                    Edit company &amp; branding
                </a>
            </div>

            @if($crmReady && $crmLoginUrl)
                <div class="tenant-action-group">
                    <span class="tenant-action-group-title">CRM</span>
                    <a href="{{ $crmLoginUrl }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                        Open agent login ↗
                    </a>
                </div>
            @endif

            <div class="tenant-action-group">
                <span class="tenant-action-group-title">Account</span>
                @if($tenant->isPending())
                    <form method="POST" action="{{ route('admin.tenants.reject', $tenant) }}" class="inline-form"
                          onsubmit="return confirm('Reject this registration? The company will not be provisioned.');">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm btn-danger-text">Reject registration</button>
                    </form>
                @elseif($tenant->status === 'active')
                    <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" class="inline-form"
                          onsubmit="return confirm('Suspend this company?\n\nUsers will not be able to open the CRM on their subdomain until you reactivate.');">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm btn-danger-text">Suspend CRM access</button>
                    </form>
                @elseif(in_array($tenant->status, ['suspended', 'failed'], true))
                    <form method="POST" action="{{ route('admin.tenants.reactivate', $tenant) }}" class="inline-form">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Restore CRM access</button>
                    </form>
                @endif
            </div>
        </div>
        <p class="form-hint tenant-show-actions-hint">
            Subscription, provisioning, and CRM login are in <a href="#tenant-manage">Manage company</a> below.
            Profile and branding: <a href="{{ route('admin.tenants.edit', $tenant) }}">Edit company</a>.
        </p>
    </div>
</div>
