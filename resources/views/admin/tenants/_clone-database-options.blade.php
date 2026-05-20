@php
    $templateDb = config('master.template_database', 'template');
    $withDataChecked = old('with_data', $withDataChecked ?? false);
@endphp

<div class="form-group clone-db-options" id="cloneDbOptions">
    <label class="checkbox-row">
        <input type="checkbox" name="with_data" value="1" id="tenant_with_data"
            @checked($withDataChecked)>
        Copy <strong>all data</strong> from template database (<code>{{ $templateDb }}</code>) into the new tenant database
    </label>
    <p class="form-hint">
        <strong>Unchecked (default):</strong> copy table structure only, then seed reference data (roles, stages, theme, etc.).<br>
        <strong>Checked:</strong> full database copy including applications, users, and history — can be very large and slow.
    </p>
</div>

@once
    @push('scripts')
    <script>
        (function () {
            function bindCloneDbPrompt(form) {
                if (!form || form.dataset.cloneDbBound) return;
                form.dataset.cloneDbBound = '1';
                form.addEventListener('submit', function (e) {
                    const withData = form.querySelector('[name="with_data"]')?.checked;
                    if (!withData || form.dataset.cloneConfirmed === '1') {
                        return;
                    }
                    e.preventDefault();
                    const templateDb = @json($templateDb);
                    const msg =
                        'Copy ALL data from the template database (“' + templateDb + '”) into this tenant’s new database?\n\n' +
                        'This includes every row in every table (applications, users, messages, etc.) and may take a long time.\n\n' +
                        'Choose Cancel to copy structure only (recommended).';
                    if (window.confirm(msg)) {
                        form.dataset.cloneConfirmed = '1';
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                });
            }

            document.querySelectorAll('form[data-clone-db-prompt]').forEach(bindCloneDbPrompt);

            const approveNow = document.querySelector('[name="approve_immediately"]');
            const cloneBlock = document.getElementById('cloneDbOptions');
            if (approveNow && cloneBlock) {
                const toggle = () => {
                    cloneBlock.style.display = approveNow.checked ? '' : 'none';
                    if (!approveNow.checked) {
                        const cb = cloneBlock.querySelector('[name="with_data"]');
                        if (cb) cb.checked = false;
                    }
                };
                approveNow.addEventListener('change', toggle);
                toggle();
            }
        })();
    </script>
    @endpush
@endonce
