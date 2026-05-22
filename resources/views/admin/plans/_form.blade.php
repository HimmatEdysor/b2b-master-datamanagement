@php
    $isCreate = $isCreate ?? ! isset($plan);
    $plan = $plan ?? null;
    $currencies = ['INR', 'USD', 'EUR', 'GBP', 'AUD'];
@endphp

<fieldset class="admin-form-section">
    <legend>Plan details</legend>
    <div class="admin-form-grid">
        <div class="form-group">
            <label for="plan_name">Plan name <span class="required">*</span></label>
            <input type="text" id="plan_name" name="name" class="form-control"
                   value="{{ old('name', $plan?->name) }}" required placeholder="e.g. Starter">
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="plan_slug">Slug</label>
            @if($isCreate)
            <input type="text" id="plan_slug" name="slug" class="form-control"
                   value="{{ old('slug', $plan?->slug) }}"
                   pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                   placeholder="auto-generated from name">
            @else
            <input type="text" id="plan_slug" class="form-control" value="{{ $plan->slug }}" readonly disabled>
            <input type="hidden" name="slug" value="{{ $plan->slug }}">
            @endif
            <p class="form-hint">Used in URLs: <code>/pricing?plan={{ old('slug', $plan?->slug ?? 'slug') }}</code></p>
            @error('slug')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group span-2">
            <label for="plan_description">Description</label>
            <p class="form-hint" style="margin-bottom:8px">Shown on the public pricing page and registration.</p>
            <textarea id="plan_description" name="description" class="form-control tinymce-editor" rows="5">{{ old('description', $plan?->description ?? '') }}</textarea>
            @error('description')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="admin-form-section">
    <legend>Pricing</legend>
    <div class="admin-form-grid">
        <div class="form-group">
            <label for="plan_price">Price <span class="required">*</span></label>
            <input type="number" id="plan_price" name="price" class="form-control"
                   step="0.01" min="0" value="{{ old('price', $plan?->price ?? 0) }}" required>
            <p class="form-hint">Use <strong>0</strong> for a free plan.</p>
            @error('price')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="plan_currency">Currency <span class="required">*</span></label>
            <select id="plan_currency" name="currency" class="form-control" required>
                @foreach($currencies as $code)
                    <option value="{{ $code }}" @selected(old('currency', $plan?->currency ?? 'INR') === $code)>{{ $code }}</option>
                @endforeach
            </select>
            @error('currency')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="plan_interval">Billing interval <span class="required">*</span></label>
            <select id="plan_interval" name="interval" class="form-control" required>
                <option value="none" @selected(old('interval', $plan?->interval ?? '') === 'none')>None (free — no expiry)</option>
                <option value="monthly" @selected(old('interval', $plan?->interval ?? 'monthly') === 'monthly')>Monthly</option>
                <option value="yearly" @selected(old('interval', $plan?->interval ?? '') === 'yearly')>Yearly</option>
            </select>
            <p class="form-hint">Use <strong>None</strong> for free plans. Paid plans use monthly/yearly to calculate company expiry from last billing date.</p>
            @error('interval')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group">
            <label for="plan_sort_order">Sort order</label>
            <input type="number" id="plan_sort_order" name="sort_order" class="form-control"
                   min="0" max="9999" value="{{ old('sort_order', $plan?->sort_order ?? 0) }}">
            <p class="form-hint">Lower numbers appear first on pricing.</p>
            @error('sort_order')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="admin-form-section">
    <legend>Features & limits</legend>
    <div class="admin-form-grid">
        <div class="form-group span-2">
            <label for="plan_features">Features <span class="form-hint-inline">(one per line)</span></label>
            <textarea id="plan_features" name="features_text" class="form-control" rows="8"
                      placeholder="Up to 10 users&#10;Subdomain CRM&#10;Email support">{{ old('features_text', isset($plan) && $plan->features ? implode("\n", $plan->features) : '') }}</textarea>
            <p class="form-hint">Bullet list on the pricing page cards.</p>
            @error('features_text')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-group span-2">
            <label for="plan_limits">Provisioning limits <span class="form-hint-inline">(JSON)</span></label>
            <textarea id="plan_limits" name="limits_json" class="form-control code-textarea" rows="5"
                      placeholder='{"users": 10}'>{{ old('limits_json', isset($plan) && $plan->limits ? json_encode($plan->limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
            <p class="form-hint">Optional. Used for API / tenant provisioning (e.g. max users).</p>
            @error('limits_json')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="admin-form-section">
    <legend>Visibility</legend>
    <div class="admin-form-grid">
        <div class="form-group checkbox-row">
            <label>
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))>
                <span><strong>Active</strong> — show on website pricing and registration form</span>
            </label>
        </div>
        <div class="form-group checkbox-row">
            <label>
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $plan->is_featured ?? false))>
                <span><strong>Featured</strong> — highlight on pricing page (“Popular” badge)</span>
            </label>
        </div>
    </div>

    @if(! $isCreate && $plan)
    <div class="plan-preview-box">
        <p class="plan-preview-label">Pricing preview</p>
        <div class="plan-preview-card {{ $plan->is_featured ? 'featured' : '' }}">
            @if($plan->is_featured)<span class="plan-preview-badge">Popular</span>@endif
            <strong>{{ $plan->name }}</strong>
            <span class="plan-preview-price">
                @if($plan->interval === 'none' || $plan->price <= 0)
                    Free — no expiry
                @else
                    {{ $plan->currency }} {{ number_format($plan->price, 0) }}/{{ $plan->interval }}
                @endif
            </span>
            @if($plan->description)
                <span class="plan-preview-desc">{!! Str::limit(strip_tags($plan->description), 120) !!}</span>
            @endif
        </div>
        <a href="{{ route('pricing') }}" target="_blank" class="form-hint">View live pricing page →</a>
    </div>
    @endif
</fieldset>

@include('admin.partials.tinymce-scripts')

@push('scripts')
<script>
(function () {
    const nameInput = document.getElementById('plan_name');
    const slugInput = document.getElementById('plan_slug');
    const isCreate = @json($isCreate);

    function slugify(text) {
        return text.toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    nameInput?.addEventListener('blur', function () {
        if (isCreate && slugInput && !slugInput.value.trim() && nameInput.value.trim()) {
            slugInput.value = slugify(nameInput.value);
        }
    });
})();
</script>
@endpush
