@php
    $logoCfg = config('website.logo');
    $existingLogo = $existingLogoUrl ?? null;
    if (! $existingLogo && isset($tenant) && $tenant?->logo_url) {
        $existingLogo = $tenant->logo_url;
    }
    $errorClass = $errorClass ?? 'form-error';
@endphp

<div class="logo-upload"
     id="logo-upload-root"
     data-aspect-ratio="{{ $logoCfg['aspect_ratio'] }}"
     data-output-width="{{ $logoCfg['output_width'] }}"
     data-output-height="{{ $logoCfg['output_height'] }}"
     data-max-kb="{{ $logoCfg['max_upload_kb'] }}">

    <label class="logo-upload-label">{{ $label ?? 'Company logo' }}</label>
    <p class="logo-upload-hint">
        {{ $logoCfg['output_width'] }}×{{ $logoCfg['output_height'] }}px
        ({{ $logoCfg['aspect_width'] }}:{{ $logoCfg['aspect_height'] }} ratio).
        PNG or JPEG, max {{ (int) ($logoCfg['max_upload_kb'] / 1024) }}MB. Optional.
    </p>

    @if($existingLogo)
        <div class="logo-upload-current" id="logo_current_wrap">
            <span class="logo-upload-current-label">Current logo</span>
            <div class="logo-preview-frame" style="--logo-aspect: {{ $logoCfg['aspect_width'] }}/{{ $logoCfg['aspect_height'] }}">
                <img src="{{ $existingLogo }}" alt="Current logo" id="logo_current_img">
            </div>
        </div>
    @endif

    <input type="file" id="logo_file_picker" accept="image/jpeg,image/png,image/webp" class="logo-upload-file-input" aria-hidden="true" tabindex="-1">
    <input type="file" name="logo" id="logo_file_submit" class="logo-upload-file-input" aria-hidden="true" tabindex="-1">
    <input type="hidden" name="remove_logo" id="remove_logo" value="0">

    <div class="logo-upload-actions" id="logo_actions_initial">
        <button type="button" class="btn btn-outline btn-sm" id="logo_choose_btn">
            {{ $existingLogo ? 'Upload new logo' : 'Choose logo image' }}
        </button>
    </div>

    <div class="logo-crop-panel" id="logo_crop_panel" hidden>
        <p class="logo-crop-title">Crop your logo ({{ $logoCfg['aspect_width'] }}:{{ $logoCfg['aspect_height'] }})</p>
        <div class="logo-crop-container">
            <img src="" alt="" id="logo_crop_image" class="logo-crop-image">
        </div>
        <div class="logo-crop-buttons">
            <button type="button" class="btn btn-primary btn-sm" id="logo_apply_crop">Apply crop</button>
            <button type="button" class="btn btn-outline btn-sm" id="logo_cancel_crop">Cancel</button>
        </div>
    </div>

    <div class="logo-preview-result" id="logo_preview_result" hidden>
        <p class="logo-crop-title">Preview</p>
        <div class="logo-preview-frame" style="--logo-aspect: {{ $logoCfg['aspect_width'] }}/{{ $logoCfg['aspect_height'] }}">
            <img src="" alt="Logo preview" id="logo_preview_img">
        </div>
        <div class="logo-crop-buttons">
            <button type="button" class="btn btn-outline btn-sm" id="logo_change_btn">Change image</button>
            <button type="button" class="btn btn-outline btn-sm logo-btn-remove" id="logo_remove_btn">Remove</button>
        </div>
    </div>

    <p class="logo-upload-error {{ $errorClass }}" id="logo_client_error" hidden role="alert"></p>
    @error('logo')
        <p class="{{ $errorClass }}">{{ $message }}</p>
    @enderror
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
        <link rel="stylesheet" href="{{ asset('css/logo-upload.css') }}">
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
        <script src="{{ asset('js/logo-upload.js') }}"></script>
    @endpush
@endonce
