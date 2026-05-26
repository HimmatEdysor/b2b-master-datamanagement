(function () {
    const pollRoot = document.getElementById('tenant-provision-poll')
        || document.getElementById('tenant-manage');
    if (!pollRoot) return;

    const statusUrl = pollRoot.dataset.statusUrl || pollRoot.dataset.provisioningPollUrl;
    const stageEl = document.getElementById('tenant-provision-stage-label');
    const errorEl = document.getElementById('tenant-provision-poll-error');
    const checklist = document.querySelector('.tenant-provision-checklist');
    const statusSelect = document.getElementById('tenant_status_manage');
    const statusBadge = document.querySelector('.tenant-manage-status-badge');

    if (!statusUrl) return;

    const updateCompanyStatus = function (data) {
        if (!data.company_status) return;

        if (statusSelect) {
            const activeOption = statusSelect.querySelector('option[value="active"]');
            if (activeOption) {
                activeOption.disabled = !data.can_set_active;
            }
            statusSelect.dataset.canSetActive = data.can_set_active ? '1' : '0';
            if (data.can_set_active && data.progress
                && data.progress.clone_done
                && data.progress.mysql_user_done
                && data.progress.crm_admin_done) {
                statusSelect.value = 'active';
            } else {
                statusSelect.value = data.company_status;
            }
        }

        if (statusBadge && data.company_status_label) {
            statusBadge.textContent = data.company_status === 'failed'
                ? 'Provisioning failed'
                : data.company_status_label;
            statusBadge.className = 'badge badge-' + data.company_status + ' tenant-manage-status-badge';
        }
    };

    const showError = function (message) {
        if (!message) return;
        if (errorEl) {
            errorEl.hidden = false;
            errorEl.innerHTML = '<strong>Provisioning failed</strong><p style="margin:8px 0 0;font-family:ui-monospace,monospace;font-size:0.9em;white-space:pre-wrap"></p>';
            errorEl.querySelector('p').textContent = message;
        }
    };

    const poll = function () {
        fetch(statusUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (stageEl && data.stage_label) {
                    stageEl.textContent = data.stage_label;
                }

                updateCompanyStatus(data);

                if (data.provision_error) {
                    showError(data.provision_error);
                }

                if (checklist && data.progress) {
                    const items = checklist.querySelectorAll('li');
                    const flags = [
                        data.progress.clone_done,
                        data.progress.mysql_user_done,
                        data.progress.crm_admin_done,
                    ];
                    items.forEach(function (li, i) {
                        const icon = li.querySelector('.tenant-provision-check-icon');
                        li.classList.remove('is-done', 'is-failed');
                        if (flags[i]) {
                            li.classList.add('is-done');
                            if (icon) icon.textContent = '✓';
                        } else if (data.progress.needs_retry || data.failed) {
                            li.classList.add('is-failed');
                            if (icon) icon.textContent = '✗';
                        }
                    });
                }

                if (data.done || data.failed || data.stalled) {
                    window.location.reload();
                }
            })
            .catch(function () {});
    };

    poll();
    window.setInterval(poll, 3000);
})();
