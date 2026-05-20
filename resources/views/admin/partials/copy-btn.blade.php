@php
    $copyText = $text ?? '';
    $disabled = trim((string) $copyText) === '';
@endphp
<button type="button"
        class="btn-copy{{ $disabled ? ' is-disabled' : '' }}"
        data-copy="{{ $copyText }}"
        title="{{ $title ?? 'Copy to clipboard' }}"
        aria-label="{{ $title ?? 'Copy' }}"
        @disabled($disabled)>
    <svg class="btn-copy-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <rect x="9" y="9" width="13" height="13" rx="2"/>
        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
    </svg>
    @if(!empty($label))
        <span class="btn-copy-label">{{ $label }}</span>
    @endif
</button>
