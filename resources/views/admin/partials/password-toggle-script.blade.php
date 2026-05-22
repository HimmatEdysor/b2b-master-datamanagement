@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-toggle-password], [data-toggle-db-password], [data-toggle-crm-password]').forEach(function (btn) {
        const id = btn.getAttribute('aria-controls');
        const input = id ? document.getElementById(id) : null;
        if (!input) return;
        btn.addEventListener('click', function () {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        });
    });
})();
</script>
@endpush
@endonce
