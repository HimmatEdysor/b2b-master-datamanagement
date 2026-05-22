@php
    $inputId = $inputId ?? 'password-field';
    $value = $value ?? '';
    $toggleAttr = $toggleAttr ?? 'data-toggle-password';
    $hint = $hint ?? null;
@endphp
<div class="db-credential-password">
    <input type="password"
           class="form-control db-password-input"
           id="{{ $inputId }}"
           value="{{ $value }}"
           readonly
           autocomplete="off">
    <div class="db-credential-password-actions">
        <button type="button" class="btn btn-outline btn-sm" {{ $toggleAttr }} aria-controls="{{ $inputId }}">
            Show
        </button>
        @if($value !== '')
            @include('admin.partials.copy-btn', [
                'text' => $value,
                'title' => $copyTitle ?? 'Copy password',
                'label' => 'Copy',
            ])
        @endif
    </div>
</div>
@if($hint)
    <p class="form-hint" style="margin:6px 0 0">{{ $hint }}</p>
@endif
