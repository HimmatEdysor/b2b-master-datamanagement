@php
    use App\Support\TenantUrl;
    use App\Services\TenantSubscriptionService;

    $subscriptionService = app(TenantSubscriptionService::class);
    $tenantStatusLabels = $tenantStatusLabels ?? config('master.tenant_status_labels', []);
    $planBillingMeta = $planBillingMeta ?? collect();
    $currentPlanNoExpiry = $tenant->subscriptionPlan?->hasNoExpiry() ?? true;
    $progress = $provisionProgress ?? [];
    $provisionComplete = ($progress['clone_done'] ?? false)
        && ($progress['mysql_user_done'] ?? false)
        && ($progress['crm_admin_done'] ?? false)
        && empty($progress['admin_db_error'] ?? null);
    $canSetCompanyActive = $tenant->isDatabaseProvisioned() || $provisionComplete;
    $statusActiveMismatch = $tenant->status === 'active' && ! $canSetCompanyActive;
    $tenantStatuses = $tenantStatuses ?? config('master.tenant_statuses', []);
    $subscriptionStatusLabels = $subscriptionStatusLabels ?? config('master.subscription_status_labels', []);
    $subscriptionStatuses = $subscriptionStatuses ?? config('master.subscription_statuses', []);
    $plans = $plans ?? collect();
    $canProvision = $canProvision ?? false;
    $databaseReady = $tenant->isDatabaseProvisioned() || $provisionComplete;
    $statusNeedsFix = $databaseReady && in_array($tenant->status, ['failed', 'provisioning'], true);
    $showProvisionPanel = $canProvision || $statusNeedsFix;
    $crmLoginUrl = TenantUrl::loginUrlForTenant($tenant);
    $crmAdminEmail = $tenant->crmAdminEmail();
    $hasCrmPassword = $tenant->hasCrmAdminPassword();
    $provisionError = $progress['error_message'] ?? $tenant->provision_error;
    $provisioningQueued = $provisioningQueued ?? ($progress['is_queued'] ?? false);
    $effectiveCompanyStatus = $tenant->status;
    if ($provisionError && ! $provisionComplete) {
        $effectiveCompanyStatus = 'failed';
    } elseif ($provisioningQueued) {
        $effectiveCompanyStatus = 'provisioning';
    }
    $companyLabel = $tenantStatusLabels[$effectiveCompanyStatus] ?? ucfirst($effectiveCompanyStatus);
    if ($effectiveCompanyStatus === 'failed') {
        $companyLabel = 'Provisioning failed';
    } elseif ($tenant->status === 'provisioning' && ($progress['is_stalled'] ?? false) && ! $provisionError) {
        $companyLabel = 'Setup incomplete — retry required';
    } elseif ($tenant->status === 'provisioning' && ($progress['is_queued'] ?? false) && ! empty($progress['stage_label'])) {
        $companyLabel = 'Provisioning — '.$progress['stage_label'];
    } elseif ($tenant->status === 'pending' && ($progress['is_queued'] ?? false)) {
        $companyLabel = 'Provisioning (queued)';
    } elseif ($tenant->status === 'pending' && ($progress['can_resume'] ?? false)) {
        $companyLabel = 'Setup incomplete (finish provisioning)';
    } elseif ($tenant->status === 'pending' && $tenant->database_name) {
        $companyLabel = 'Pending — approve to provision database';
    }
    $subLabel = $tenant->subscription_status
        ? ($subscriptionStatusLabels[$tenant->subscription_status] ?? ucfirst($tenant->subscription_status))
        : '—';
@endphp
<div class="card tenant-detail-card tenant-manage-card span-full" id="tenant-manage"
     @if($provisioningQueued) data-provisioning-poll-url="{{ route('admin.tenants.provisioning-status', $tenant) }}" @endif>
    <h2 class="tenant-detail-heading">Manage company</h2>
    <p class="form-hint tenant-manage-lead">
        One place for subscription, database provisioning, and CRM access for <strong>{{ $tenant->slug }}</strong>.
    </p>

    <div class="tenant-manage-summary" aria-label="Current status">
        <span class="badge badge-{{ $effectiveCompanyStatus }} tenant-manage-status-badge" title="Company status">{{ $companyLabel }}</span>
        <span class="badge badge-{{ in_array($tenant->subscription_status, ['active', 'trial'], true) ? 'active' : 'pending' }}">
            Subscription: {{ $subLabel }}
        </span>
        @if($tenant->subscriptionPlan)
            <span class="badge badge-pending">{{ $tenant->subscriptionPlan->name }}</span>
        @endif
        @if($tenant->subscriptionPlan && $currentPlanNoExpiry)
            <span class="badge badge-active">No expiry</span>
        @elseif($tenant->subscription_expires_at)
            <span class="badge badge-{{ $tenant->subscription_expires_at->isPast() ? 'failed' : 'pending' }}">
                Expires {{ $tenant->subscription_expires_at->format('d M Y') }}
            </span>
        @endif
        @if($tenant->isDatabaseProvisioned())
            <span class="badge badge-active">Database ready</span>
        @else
            <span class="badge badge-failed">Database not ready</span>
        @endif
        @if($tenant->approved_at)
            <span class="form-hint tenant-manage-summary-meta">
                Approved {{ $tenant->approved_at->format('d M Y, H:i') }}
                @if($tenant->approver)
                    · {{ $tenant->approver->name ?? $tenant->approver->email }}
                @endif
            </span>
        @endif
    </div>

    @if($statusActiveMismatch)
        <div class="detail-alert detail-alert-error span-full" role="alert">
            <strong>Company is Active but the database is not ready.</strong>
            CRM login will not work until provisioning finishes. Use <a href="#tenant-provision">Retry provisioning</a> above, or set status to <strong>Provisioning</strong> and save.
        </div>
    @endif

    @if($statusNeedsFix)
        @include('admin.tenants._provision-ready', [
            'provisionProgress' => $progress,
            'statusNeedsFix' => true,
        ])
    @elseif($showProvisionPanel)
        @include('admin.tenants._provision-database', [
            'canProvision' => $canProvision,
            'provisionProgress' => $progress,
            'provisioningQueued' => $provisioningQueued ?? ($progress['is_queued'] ?? false),
        ])
    @endif

    @if(master_can('tenants.edit') || master_can('tenants.approve'))
        <div class="tenant-manage-subscription" id="tenant-manage-subscription">
            <h3 class="detail-subheading">Status &amp; subscription</h3>
            <p class="form-hint" style="margin-top:0">
                @if($canProvision)
                    You can save <strong>subscription &amp; plan</strong> below anytime. <strong>Company → Active</strong> is enabled only after all provisioning steps above are complete (or provisioning sets it automatically).
                @else
                    Controls whether users can open the CRM (requires Active company, active/trial subscription, and database ready).
                @endif
            </p>
            <form method="POST" action="{{ route('admin.tenants.manage', $tenant) }}" class="tenant-manage-form admin-form"
                  id="tenantManageSubscriptionForm"
                  data-plan-billing="{{ $planBillingMeta->toJson() }}">
                @csrf
                @method('PUT')

                <div class="tenant-manage-form-top">
                    <div class="form-group">
                        <label for="tenant_status_manage">Company status</label>
                        <select id="tenant_status_manage" name="status" class="form-control"
                                data-can-set-active="{{ $canSetCompanyActive ? '1' : '0' }}">
                            @foreach($tenantStatuses as $statusValue)
                                <option value="{{ $statusValue }}"
                                        @disabled($statusValue === 'active' && ! $canSetCompanyActive)
                                        @selected(old('status', $effectiveCompanyStatus) === $statusValue)>
                                    {{ $tenantStatusLabels[$statusValue] ?? ucfirst($statusValue) }}
                                    @if($statusValue === 'active' && ! $canSetCompanyActive)
                                        (after provisioning)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @if($provisionComplete && in_array($tenant->status, ['failed', 'provisioning'], true))
                            <p class="form-hint" id="tenant-status-reconcile-hint">
                                Database and CRM login are ready. Choose <strong>Active</strong> and save, or use <strong>Set company to Active</strong> above.
                            </p>
                        @elseif($effectiveCompanyStatus === 'failed')
                            <p class="form-hint" id="tenant-status-failed-hint">
                                Status is <strong>Failed</strong> because provisioning did not complete. Fix provisioning above, then set status when ready.
                            </p>
                        @elseif($provisioningQueued)
                            <p class="form-hint" id="tenant-status-provisioning-hint">
                                Status stays <strong>Provisioning</strong> while the queue job runs. It becomes <strong>Active</strong> automatically when all steps succeed.
                            </p>
                        @elseif(! $canSetCompanyActive)
                            <p class="form-hint" id="tenant-status-active-hint">
                                <strong>Active</strong> unlocks after MySQL user and CRM login are created — use provisioning above.
                            </p>
                        @endif
                        @error('status')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="subscription_plan_id_manage">Subscription plan</label>
                        <select id="subscription_plan_id_manage" name="subscription_plan_id" class="form-control">
                            <option value="">— No plan —</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}"
                                        data-no-expiry="{{ $plan->hasNoExpiry() ? '1' : '0' }}"
                                        data-interval="{{ $plan->interval }}"
                                        @selected(old('subscription_plan_id', $tenant->subscription_plan_id) == $plan->id)>
                                    {{ $plan->name }}
                                    @if($plan->hasNoExpiry())
                                        (no expiry)
                                    @elseif($plan->interval === 'yearly')
                                        (yearly billing)
                                    @else
                                        (monthly billing)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('subscription_plan_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="subscription_status_manage">Subscription status</label>
                        <select id="subscription_status_manage" name="subscription_status" class="form-control">
                            <option value="">—</option>
                            @foreach($subscriptionStatuses as $subStatus)
                                <option value="{{ $subStatus }}" @selected(old('subscription_status', $tenant->subscription_status) === $subStatus)>
                                    {{ $subscriptionStatusLabels[$subStatus] ?? ucfirst($subStatus) }}
                                </option>
                            @endforeach
                        </select>
                        @error('subscription_status')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group tenant-billing-field" id="tenant-billed-at-wrap">
                        <label for="subscription_billed_at_manage">Last billing date</label>
                        <input type="date" id="subscription_billed_at_manage" name="subscription_billed_at" class="form-control"
                               value="{{ old('subscription_billed_at', $tenant->subscription_billed_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                        <p class="form-hint">Start of the current paid period. Expiry is calculated from this date + plan interval.</p>
                        @error('subscription_billed_at')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group tenant-billing-field" id="tenant-expires-at-wrap">
                        <label for="subscription_expires_at_manage">Subscription expires</label>
                        <input type="date" id="subscription_expires_at_manage" name="subscription_expires_at" class="form-control"
                               value="{{ old('subscription_expires_at', $tenant->subscription_expires_at?->format('Y-m-d')) }}">
                        <p class="form-hint" id="tenant-expires-hint">Auto-calculated from last billing + plan interval. Override only if needed.</p>
                        @error('subscription_expires_at')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group tenant-billing-field checkbox-row" id="tenant-renew-billing-wrap">
                        <label>
                            <input type="checkbox" name="renew_billing" value="1" @checked(old('renew_billing'))>
                            <span><strong>Renew billing period</strong> — set last billing to today and recalculate expiry</span>
                        </label>
                    </div>

                    <div class="form-group tenant-billing-free-note" id="tenant-billing-free-note" @if(! $currentPlanNoExpiry) hidden @endif>
                        <p class="form-hint" style="margin:0">
                            <strong>Free plan</strong> — no billing or expiry dates. CRM access depends on subscription status only.
                        </p>
                    </div>
                </div>

                <div class="tenant-manage-form-actions">
                    <button type="submit" class="btn btn-primary">Save status &amp; subscription</button>
                </div>
            </form>
        </div>
    @else
        <div class="tenant-manage-readonly tenant-manage-subscription">
            <h3 class="detail-subheading">Status &amp; subscription</h3>
            <p><strong>Subscription:</strong> {{ $subLabel }} · {{ $tenant->subscriptionPlan?->name ?? 'No plan' }}</p>
            <p class="form-hint">{{ $subscriptionService->expiryLabelForTenant($tenant) }}</p>
            <p class="form-hint">You need <code>tenants.edit</code> or <code>tenants.approve</code> permission to change subscription here.</p>
        </div>
    @endif

    @if(! $canProvision && $tenant->migration_error)
        <div class="detail-alert detail-alert-error tenant-manage-migration-error">
            <strong>Migration error</strong>
            <pre class="detail-pre">{{ $tenant->migration_error }}</pre>
        </div>
    @endif

    @if($tenant->domains->isNotEmpty() || master_can('tenants.edit'))
        <div class="tenant-manage-domains" id="tenant-manage-domains">
            <h3 class="detail-subheading">Domains, DNS &amp; SSL</h3>
            <p class="form-hint" style="margin-top:0">
                Use <strong>DNS Update</strong> to create or refresh the A record (Cloudflare API or local link).
                Use <strong>SSL Apply</strong> after DNS is linked (marks HTTPS ready or runs Certbot on the server).
            </p>
            @include('admin.tenants._domains-manage', [
                'tenant' => $tenant,
                'dnsService' => $dnsService ?? app(\App\Services\TenantDomainDnsService::class),
                'domainActivityLog' => $domainActivityLog ?? [],
            ])
        </div>
    @endif

    @if($databaseReady && $tenant->database_name && $crmLoginUrl)
        <div class="tenant-manage-crm" id="tenant-crm-login">
            <h3 class="detail-subheading">CRM login</h3>
            <p class="form-hint" style="margin:0 0 10px">Tenant CRM sign-in (not this master portal).</p>
            <a href="{{ $crmLoginUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm tenant-crm-login-btn">
                Open CRM login
            </a>
            <table class="detail-table detail-table-compact" style="margin-top:12px">
                <tr>
                    <th scope="row">Login URL</th>
                    <td>
                        <div class="cell-copyable">
                            <code>{{ $crmLoginUrl }}</code>
                            @include('admin.partials.copy-btn', ['text' => $crmLoginUrl, 'title' => 'Copy login URL'])
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Email</th>
                    <td>
                        <code>{{ $crmAdminEmail }}</code>
                        @include('admin.partials.copy-btn', ['text' => $crmAdminEmail, 'title' => 'Copy email'])
                    </td>
                </tr>
                <tr>
                    <th scope="row">Password</th>
                    <td>
                        @if($hasCrmPassword)
                            @include('admin.partials.password-field', [
                                'inputId' => 'tenant-crm-admin-password',
                                'value' => $tenant->crm_admin_password,
                                'toggleAttr' => 'data-toggle-crm-password',
                                'copyTitle' => 'Copy CRM password',
                                'hint' => 'Stored encrypted on master. Same password is set on the CRM users table for this email.',
                            ])
                        @else
                            <span class="detail-muted">Not set — complete provisioning or generate below.</span>
                        @endif
                    </td>
                </tr>
            </table>
            @can('tenants.edit')
                <div class="crm-password-actions" style="margin-top:12px">
                    <form method="POST" action="{{ route('admin.tenants.crm-admin-password.update', $tenant) }}" class="db-password-update-form">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="tenant-crm-password-new">Set new CRM login password</label>
                            <input type="password" id="tenant-crm-password-new" name="password" class="form-control"
                                   minlength="8" maxlength="128" required autocomplete="new-password"
                                   placeholder="Min. 8 characters">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Update CRM password</button>
                    </form>
                    <form method="POST" action="{{ route('admin.tenants.crm-admin-password', $tenant) }}" class="inline-form db-password-regenerate-form"
                          onsubmit="return confirm('Generate a new random CRM login password for {{ $tenant->name }}?');">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm">Generate new CRM password</button>
                    </form>
                </div>
            @endcan
        </div>
    @endif
</div>

@include('admin.partials.password-toggle-script')
