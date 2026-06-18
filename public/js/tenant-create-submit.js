(function () {
    const form = document.querySelector('form.admin-form[data-clone-db-prompt]');
    if (!form) return;

    const submitBtn = document.getElementById('tenant-create-submit');
    const approveCheckbox = form.querySelector('input[name="approve_immediately"]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const buildOverlay = function () {
        const overlay = document.createElement('div');
        overlay.className = 'tenant-create-overlay';
        overlay.id = 'tenant-create-overlay';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = '<div class="tenant-create-overlay-panel">'
            + '<strong id="tenant-create-overlay-title">Creating company</strong>'
            + '<p class="form-hint" id="tenant-create-overlay-hint" style="margin:8px 0 12px">Saving company record…</p>'
            + '<div class="tenant-provision-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">'
            + '<div class="tenant-provision-progress-bar" id="tenant-create-progress-bar" style="width:2%"></div>'
            + '</div>'
            + '<p class="tenant-provision-percent" id="tenant-create-percent" style="margin:8px 0 0">2%</p>'
            + '</div>';
        document.body.appendChild(overlay);
        return overlay;
    };

    const setOverlayPercent = function (pct, hint) {
        const bar = document.getElementById('tenant-create-progress-bar');
        const label = document.getElementById('tenant-create-percent');
        const hintEl = document.getElementById('tenant-create-overlay-hint');
        const p = Math.max(0, Math.min(100, pct));
        if (bar) bar.style.width = p + '%';
        if (label) label.textContent = p + '%';
        if (hint && hintEl) hintEl.textContent = hint;
    };

    const resetForm = function () {
        form.dataset.submitting = '0';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create company';
        }
        const overlay = document.getElementById('tenant-create-overlay');
        if (overlay) overlay.remove();
    };

    const parseResponseBody = function (response) {
        return response.text().then(function (text) {
            if (!text) {
                return { ok: response.ok, data: {} };
            }

            try {
                return { ok: response.ok, data: JSON.parse(text) };
            } catch (e) {
                if (response.status === 419) {
                    return {
                        ok: false,
                        data: { message: 'Session expired. Refresh the page and sign in again.' },
                    };
                }

                if (response.status === 422) {
                    return {
                        ok: false,
                        data: { message: 'Validation failed. Check the form fields.' },
                    };
                }

                const title = text.match(/<title>([^<]+)<\/title>/i);
                return {
                    ok: false,
                    data: {
                        message: title
                            ? title[1]
                            : ('Server error (HTTP ' + response.status + '). See storage/logs/laravel.log'),
                    },
                };
            }
        });
    };

    const formatValidationMessage = function (data) {
        if (data.message) {
            return data.message;
        }

        if (data.errors && typeof data.errors === 'object') {
            const parts = [];
            Object.keys(data.errors).forEach(function (key) {
                const msgs = data.errors[key];
                if (Array.isArray(msgs) && msgs[0]) {
                    parts.push(msgs[0]);
                }
            });

            if (parts.length) {
                return parts.join('\n');
            }
        }

        return 'Could not create company.';
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (form.dataset.submitting === '1') {
            return;
        }

        form.dataset.submitting = '1';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = approveCheckbox && approveCheckbox.checked
                ? 'Creating & queuing…'
                : 'Creating…';
        }

        buildOverlay();
        setOverlayPercent(5, 'Validating form…');

        const body = new FormData(form);
        let tick = 5;

        const timer = window.setInterval(function () {
            if (tick < 25) {
                tick += 1;
                setOverlayPercent(tick, 'Saving company & queueing database job…');
            }
        }, 400);

        fetch(form.action, {
            method: 'POST',
            body: body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
        })
            .then(parseResponseBody)
            .then(function (result) {
                window.clearInterval(timer);

                if (!result.ok || result.data.ok === false) {
                    const msg = formatValidationMessage(result.data);
                    setOverlayPercent(0, msg);
                    resetForm();
                    window.alert(msg);

                    if (result.data.redirect) {
                        window.location.href = result.data.redirect;
                    }

                    return;
                }

                if (!result.data.redirect) {
                    resetForm();
                    window.alert('Company saved but no redirect URL was returned.');
                    return;
                }

                setOverlayPercent(100, 'Redirecting to live progress…');
                window.setTimeout(function () {
                    window.location.href = result.data.redirect;
                }, 350);
            })
            .catch(function (err) {
                window.clearInterval(timer);
                resetForm();
                const msg = err && err.message
                    ? err.message
                    : 'Network error — is php artisan serve running on port 8001?';
                window.alert(msg);
            });
    });
})();
