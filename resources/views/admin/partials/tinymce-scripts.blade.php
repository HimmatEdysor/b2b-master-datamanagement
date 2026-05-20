@once
    @push('styles')
        <style>
            .tox-tinymce { border-radius: 8px !important; border-color: var(--border, #e5e7eb) !important; }
            .form-group .tox-tinymce { max-width: 100%; }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
        <script src="{{ asset('js/admin-tinymce.js') }}?v={{ @filemtime(public_path('js/admin-tinymce.js')) ?: time() }}"></script>
    @endpush
@endonce
