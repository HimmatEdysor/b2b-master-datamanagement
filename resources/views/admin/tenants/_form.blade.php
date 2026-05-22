@php
    $isCreate = $isCreate ?? ! isset($tenant);
    $tenant = $tenant ?? null;
    $businessTypes = ['Education consultancy', 'Study abroad agency', 'Immigration consultant', 'Language institute', 'University / institution', 'Corporate / other'];
    use App\Support\TenantUrl;
    $baseDomain = TenantUrl::baseDomain();
    $slugPreviewExample = TenantUrl::urlForSlug('your-slug');
    $dbPrefix = config('master.tenant_database_prefix', 'b2b_tenant_');
    $tenantStatuses = config('master.tenant_statuses', []);
    $tenantStatusLabels = config('master.tenant_status_labels', []);
    $subscriptionStatuses = config('master.subscription_statuses', []);
    $subscriptionStatusLabels = config('master.subscription_status_labels', []);
    $createStatuses = ['pending', 'provisioning'];
@endphp

<fieldset class="admin-form-section">
    <legend>Company details</legend>
    <div class="admin-form-grid">
        <div class="form-group span-2">
            <label for="name">Company name <span class="required">*</span></label>
            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $tenant?->name) }}" required>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="slug">Subdomain slug <span class="required">*</span></label>
            @if($isCreate)
            <input type="text" id="slug" name="slug" class="form-control"
                   value="{{ old('slug', $tenant?->slug) }}"
                   pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                   autocomplete="off" spellcheck="false"
                   placeholder="data" required>
            <p class="form-hint">Letters, numbers, hyphens only — no spaces.</p>
            @else
            <input type="text" id="slug" class="form-control" value="{{ $tenant->slug }}" readonly disabled>
            @endif
            <p class="form-hint">CRM URL: <code id="slug-preview">{{ TenantUrl::urlForSlug(old('slug', $tenant?->slug ?? 'your-slug')) }}</code></p>
            @error('slug')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="business_type">Business type</label>
            <select id="business_type" name="business_type" class="form-control">
                <option value="">— Select —</option>
                @foreach($businessTypes as $type)
                    <option value="{{ $type }}" @selected(old('business_type', $tenant?->business_type) === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @error('business_type')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group span-2">
            <label for="company_website">Company website</label>
            <input type="url" id="company_website" name="company_website" class="form-control"
                   value="{{ old('company_website', $tenant?->company_website) }}" placeholder="https://example.com">
            @error('company_website')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group span-2">
            <label for="address_line">Office address</label>
            <input type="text" id="address_line" name="address_line" class="form-control"
                   value="{{ old('address_line', $tenant?->address_line) }}">
            @error('address_line')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="city">City</label>
            <input type="text" id="city" name="city" class="form-control" value="{{ old('city', $tenant?->city) }}">
            @error('city')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="state">State / province</label>
            <input type="text" id="state" name="state" class="form-control" value="{{ old('state', $tenant?->state) }}">
            @error('state')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="country">Country</label>
            <input type="text" id="country" name="country" class="form-control" value="{{ old('country', $tenant?->country ?? 'India') }}">
            @error('country')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="admin-form-section">
    <legend>CRM & database</legend>
    <div class="admin-form-grid">
        <div class="form-group">
            <label for="database_name">Database name <span class="required">*</span></label>
            <input type="text" id="database_name" name="database_name" class="form-control"
                   value="{{ old('database_name', $tenant?->database_name ?? '') }}"
                   placeholder="{{ $dbPrefix }}edysor"
                   pattern="[a-z0-9_]+"
                   {{ $isCreate ? '' : 'required' }}>
            @if($isCreate)
                <p class="form-hint">Auto-filled from slug; edit if you need a custom database name.</p>
            @endif
            @error('database_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="status">{{ $isCreate ? 'Initial status' : 'Company status' }}</label>
            <select id="status" name="status" class="form-control">
                @foreach($isCreate ? $createStatuses : $tenantStatuses as $statusValue)
                    <option value="{{ $statusValue }}" @selected(old('status', $tenant?->status ?? 'pending') === $statusValue)>
                        {{ $tenantStatusLabels[$statusValue] ?? ucfirst($statusValue) }}
                    </option>
                @endforeach
            </select>
            @if(!$isCreate && $tenant?->isPending())
                <p class="form-hint">Use <strong>Approve & provision</strong> on the company page to create the database and set status to active.</p>
            @endif
            @error('status')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="subscription_plan_id">Subscription plan</label>
            <select id="subscription_plan_id" name="subscription_plan_id" class="form-control">
                <option value="">— None —</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(old('subscription_plan_id', $tenant?->subscription_plan_id) == $plan->id)>
                        {{ $plan->name }}
                    </option>
                @endforeach
            </select>
            @error('subscription_plan_id')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="subscription_status">Subscription status</label>
            <select id="subscription_status" name="subscription_status" class="form-control">
                <option value="">— Select —</option>
                @foreach($subscriptionStatuses as $subStatus)
                    <option value="{{ $subStatus }}" @selected(old('subscription_status', $tenant?->subscription_status ?? ($isCreate ? 'pending' : '')) === $subStatus)>
                        {{ $subscriptionStatusLabels[$subStatus] ?? ucfirst($subStatus) }}
                    </option>
                @endforeach
            </select>
            @error('subscription_status')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group span-2">
            <label for="custom_domain">{{ $isCreate ? 'Custom domain (optional)' : 'Add custom domain (optional)' }}</label>
            <input type="text" id="custom_domain" name="custom_domain" class="form-control"
                   value="{{ old('custom_domain') }}"
                   placeholder="crm.example.com">
            <p class="form-hint">
                @if($isCreate)
                    White-label hostname. Subdomain <code>{slug}.{{ $baseDomain }}</code> is added on approval ({{ TenantUrl::environmentLabel() }}).
                @else
                    Saves a new custom hostname. Manage all domains on the <a href="{{ route('admin.tenants.show', $tenant) }}">company detail</a> page.
                @endif
            </p>
            @error('custom_domain')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="admin-form-section">
    <legend>Primary contact</legend>
    <div class="admin-form-grid">
        <div class="form-group">
            <label for="contact_name">Contact name</label>
            <input type="text" id="contact_name" name="contact_name" class="form-control"
                   value="{{ old('contact_name', $tenant?->contact_name) }}">
            @error('contact_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="contact_designation">Job title</label>
            <input type="text" id="contact_designation" name="contact_designation" class="form-control"
                   value="{{ old('contact_designation', $tenant?->contact_designation) }}">
            @error('contact_designation')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="contact_email">Contact email</label>
            <input type="email" id="contact_email" name="contact_email" class="form-control"
                   value="{{ old('contact_email', $tenant?->contact_email) }}">
            @error('contact_email')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="contact_phone">Contact phone</label>
            <input type="text" id="contact_phone" name="contact_phone" class="form-control"
                   value="{{ old('contact_phone', $tenant?->contact_phone) }}">
            @error('contact_phone')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="admin-form-section">
    <legend>Branding</legend>
    <div class="admin-form-grid">
        <div class="form-group">
            <label for="brand_name">CRM display name</label>
            <input type="text" id="brand_name" name="brand_name" class="form-control"
                   value="{{ old('brand_name', $tenant?->brand_name) }}" placeholder="Defaults to company name">
            @error('brand_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="support_email">Support email</label>
            <input type="email" id="support_email" name="support_email" class="form-control"
                   value="{{ old('support_email', $tenant?->support_email) }}">
            @error('support_email')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group span-2">
            @include('partials.logo-upload', [
                'tenant' => $tenant ?? null,
                'errorClass' => 'field-error',
            ])
        </div>

        <div class="form-group">
            <label for="favicon">Favicon</label>
            @if ($tenant?->favicon_url)
                <div class="mb-2">
                    <img src="{{ $tenant->favicon_url }}" alt="Favicon" width="32" height="32" class="d-block mb-1">
                    <div class="form-check">
                        <input type="checkbox" name="remove_favicon" value="1" id="remove_favicon" class="form-check-input">
                        <label for="remove_favicon" class="form-check-label">Remove favicon</label>
                    </div>
                </div>
            @endif
            <input type="file" id="favicon" name="favicon" class="form-control"
                   accept="image/png,image/jpeg,image/webp,image/x-icon,.ico,.svg">
            <p class="form-hint">PNG, ICO, SVG, or WebP — max 512KB. Upload only (no URL).</p>
            @error('favicon')<p class="field-error">{{ $message }}</p>@enderror
        </div>

    </div>
</fieldset>

@if(! $isCreate)
<fieldset class="admin-form-section">
    <legend>Database &amp; storage (per company)</legend>
    <p class="form-hint" style="margin-top:0">CRM reads these from the company record via resolve API — not from master <code>.env</code>. Filled automatically on approval.</p>
    <div class="admin-form-grid">
        <div class="form-group">
            <label for="database_host">Database host</label>
            <input type="text" id="database_host" name="database_host" class="form-control"
                   value="{{ old('database_host', $tenant?->database_host) }}"
                   placeholder="Set on approval">
            @error('database_host')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="database_port">Database port</label>
            <input type="number" id="database_port" name="database_port" class="form-control"
                   value="{{ old('database_port', $tenant?->database_port ?? 3306) }}" min="1" max="65535">
            @error('database_port')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="s3_folder">S3 folder</label>
            <input type="text" id="s3_folder" name="s3_folder" class="form-control"
                   value="{{ old('s3_folder', $tenant?->s3_folder) }}"
                   pattern="[a-z0-9][a-z0-9_-]*"
                   placeholder="{{ $tenant?->slug ?? 'slug' }}">
            <p class="form-hint">Prefix in shared bucket for this company’s files.</p>
            @error('s3_folder')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group span-2">
            <p class="form-hint">MySQL username/password are created on approval and stored encrypted. Domains are managed on the <a href="{{ route('admin.tenants.show', $tenant) }}">detail page</a>.</p>
        </div>
    </div>
</fieldset>
@endif

<fieldset class="admin-form-section">
    <legend>Notes</legend>
    <div class="form-group">
        <label for="registration_notes">Internal notes</label>
        <textarea id="registration_notes" name="registration_notes" class="form-control" rows="4">{{ old('registration_notes', $tenant?->registration_notes) }}</textarea>
        @error('registration_notes')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    @if($isCreate)
    <div class="form-group checkbox-row">
        <label>
            <input type="checkbox" name="approve_immediately" value="1" @checked(old('approve_immediately'))>
            Approve immediately and provision database
        </label>
        <p class="form-hint">Runs clone + domain setup now. Leave unchecked to review later.</p>
    </div>
    @include('admin.tenants._clone-database-options', ['withDataChecked' => old('with_data')])
    @endif
</fieldset>

@push('scripts')
<script src="{{ asset('js/tenant-slug-input.js') }}"></script>
<script>
(function () {
    const slugInput = document.getElementById('slug');
    const dbInput = document.getElementById('database_name');
    const nameInput = document.getElementById('name');
    const customDomainInput = document.getElementById('custom_domain');
    const slugPreview = document.getElementById('slug-preview');
    const form = document.querySelector('form.admin-form');
    const dbPrefix = @json($dbPrefix);
    const baseDomain = @json($baseDomain);
    const tenantScheme = @json(TenantUrl::scheme());
    const tenantPortSuffix = @json(TenantUrl::portSuffix());
    const isCreate = @json($isCreate);

    function slugFromName(text) {
        return window.sanitizeTenantSlugInput(
            text.toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
        );
    }

    function updateSlugPreview(slug) {
        if (!slugPreview || !slugInput) return;
        slug = (slug || slugInput.value || '').trim() || 'your-slug';
        slugPreview.textContent = tenantScheme + '://' + slug + '.' + baseDomain + tenantPortSuffix;
    }

    function suggestDatabase() {
        if (!isCreate || !dbInput || !slugInput) return;
        if (dbInput.dataset.userEdited === '1') return;
        const slug = slugInput.value.trim();
        if (slug) dbInput.value = dbPrefix + slug.replace(/-/g, '_');
    }

    if (isCreate && slugInput) {
        window.bindTenantSlugInput(slugInput, {
            onChange: function (slug) {
                updateSlugPreview(slug);
                suggestDatabase();
            },
        });
    }

    customDomainInput?.addEventListener('input', function () {
        const cleaned = customDomainInput.value.replace(/\s/g, '').toLowerCase();
        if (customDomainInput.value !== cleaned) customDomainInput.value = cleaned;
    });
    customDomainInput?.addEventListener('keydown', function (e) {
        if (e.key === ' ') e.preventDefault();
    });

    form?.addEventListener('submit', function () {
        if (isCreate && slugInput) slugInput.value = window.sanitizeTenantSlugInput(slugInput.value);
        if (customDomainInput?.value) {
            customDomainInput.value = customDomainInput.value.replace(/\s/g, '').toLowerCase();
        }
    });

    dbInput?.addEventListener('input', function () {
        dbInput.dataset.userEdited = '1';
    });

    nameInput?.addEventListener('blur', function () {
        if (isCreate && slugInput && !slugInput.value.trim() && nameInput.value.trim()) {
            slugInput.value = slugFromName(nameInput.value);
            updateSlugPreview();
            suggestDatabase();
        }
    });

    updateSlugPreview();
    if (isCreate && dbInput && !dbInput.value && slugInput?.value) suggestDatabase();
})();
</script>
@endpush
