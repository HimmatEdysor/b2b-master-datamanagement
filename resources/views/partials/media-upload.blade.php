@php
    $name = $name ?? 'media_file';
    $removeName = $removeName ?? 'remove_'.$name;
    $label = $label ?? 'Upload file';
    $existingUrl = $existingUrl ?? null;
    $accept = $accept ?? 'image/jpeg,image/png,image/webp';
    $hint = $hint ?? null;
    $type = $type ?? 'image';
    $profile = $profile ?? 'blog_featured';
    $cfg = config("media.{$profile}", []);
    $maxMb = (int) (($cfg['max_kb'] ?? 5120) / 1024);
    $mimes = implode(', ', $cfg['mimes'] ?? []);
    $defaultHint = $type === 'image'
        ? "JPEG, PNG, or WebP — max {$maxMb}MB."
        : "Allowed: {$mimes} — max {$maxMb}MB.";
@endphp

<div class="media-upload" data-media-upload data-media-type="{{ $type }}">
    <label class="media-upload-label" for="{{ $name }}">{{ $label }}</label>
    <p class="media-upload-hint">{{ $hint ?? $defaultHint }}</p>

    @if($existingUrl)
        <div class="media-upload-current">
            @if($type === 'image')
                <div class="media-upload-preview">
                    <img src="{{ $existingUrl }}" alt="">
                </div>
            @else
                <a href="{{ $existingUrl }}" target="_blank" rel="noopener" class="media-upload-doc-link">View current file</a>
            @endif
            <label class="media-upload-remove">
                <input type="checkbox" name="{{ $removeName }}" value="1" @checked(old($removeName))>
                Remove current file
            </label>
        </div>
    @endif

    <div class="media-upload-actions">
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $name }}"
            class="form-control media-upload-file"
            accept="{{ $accept }}"
        >
    </div>

    @if($type === 'image')
        <div class="media-upload-new-preview media-upload-preview" data-media-new-preview hidden>
            <img src="" alt="Preview" data-media-new-img>
        </div>
    @else
        <p class="media-upload-new-preview" data-media-new-preview hidden></p>
    @endif

    @error($name)
        <p class="form-hint" style="color:var(--danger)">{{ $message }}</p>
    @enderror
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/media-upload.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('js/media-upload.js') }}" defer></script>
    @endpush
@endonce
