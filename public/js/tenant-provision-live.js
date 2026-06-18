(function () {
    const pollRoot = document.getElementById('tenant-provision-poll');
    if (!pollRoot) return;

    const tenantId = pollRoot.dataset.tenantId;
    const statusUrl = pollRoot.dataset.statusUrl || pollRoot.dataset.provisioningPollUrl;
    const doneUrl = pollRoot.dataset.doneUrl || '';
    const useReverb = pollRoot.dataset.useReverb === '1';

    const stageEl = document.getElementById('tenant-provision-stage-label');
    const detailEl = document.getElementById('tenant-provision-stage-detail');
    const percentEl = document.getElementById('tenant-provision-percent');
    const barEl = document.getElementById('tenant-provision-progress-bar');
    const stepNumEl = document.getElementById('tenant-provision-step-num');
    const stepsList = document.getElementById('tenant-provision-steps');
    const errorEl = document.getElementById('tenant-provision-poll-error');
    const checklist = document.querySelector('.tenant-provision-checklist');
    const statusSelect = document.getElementById('tenant_status_manage');
    const statusBadge = document.querySelector('.tenant-manage-status-badge');

    let finished = false;
    let pollTimer = null;

    const stageToStep = {
        queued: 1,
        running: 1,
        preparing: 1,
        cloning: 2,
        mysql_user: 3,
        seeding: 3,
        crm_admin: 4,
        completed: 4,
    };

    const stepIndexByKey = {
        preparing: 1,
        cloning: 2,
        seeding: 3,
        crm_admin: 4,
    };

    const percentByStage = {
        queued: 5,
        running: 8,
        preparing: 12,
        cloning: 35,
        mysql_user: 58,
        seeding: 72,
        crm_admin: 90,
        completed: 100,
    };

    const resolveStage = function (payload) {
        return payload.stage || (payload.progress && payload.progress.stage) || '';
    };

    const resolveStageLabel = function (payload) {
        return payload.stage_label
            || (payload.progress && payload.progress.stage_label)
            || '';
    };

    const resolvePercent = function (payload) {
        if (typeof payload.percent === 'number') {
            return payload.percent;
        }
        if (payload.progress && typeof payload.progress.percent === 'number') {
            return payload.progress.percent;
        }
        const stage = resolveStage(payload);
        return percentByStage[stage] !== undefined ? percentByStage[stage] : 0;
    };

    const updateProgressBar = function (percent) {
        const p = Math.max(0, Math.min(100, Number(percent) || 0));
        if (barEl) {
            barEl.style.width = p + '%';
            barEl.setAttribute('aria-valuenow', String(p));
        }
        if (percentEl) {
            percentEl.textContent = p + '%';
        }
    };

    const updateStepList = function (stage) {
        if (!stepsList || !stage) return;
        const current = stageToStep[stage] || 1;
        if (stepNumEl) stepNumEl.textContent = String(current);
        stepsList.querySelectorAll('li').forEach(function (li) {
            const step = stepIndexByKey[li.dataset.step] || 0;
            li.classList.remove('is-active', 'is-done');
            if (step > 0 && step < current) li.classList.add('is-done');
            if (step === current) li.classList.add('is-active');
        });
    };

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

    const updateChecklist = function (progress) {
        if (!checklist || !progress) return;

        const items = checklist.querySelectorAll('li');
        const flags = [
            progress.clone_done,
            progress.mysql_user_done,
            progress.crm_admin_done,
        ];
        items.forEach(function (li, i) {
            const icon = li.querySelector('.tenant-provision-check-icon');
            li.classList.remove('is-done', 'is-failed');
            if (flags[i]) {
                li.classList.add('is-done');
                if (icon) icon.textContent = '✓';
            } else if (progress.needs_retry || progress.failed) {
                li.classList.add('is-failed');
                if (icon) icon.textContent = '✗';
            }
        });
    };

    const finishSuccess = function () {
        finished = true;
        if (pollTimer) window.clearInterval(pollTimer);
        updateProgressBar(100);
        updateStepList('completed');
        if (stageEl) stageEl.textContent = 'Complete';
        const target = doneUrl || window.location.href;
        window.setTimeout(function () {
            window.location.href = target;
        }, 800);
    };

    const applyPayload = function (payload, fromPoll) {
        if (finished) return;

        const stage = resolveStage(payload);
        const stageLabel = resolveStageLabel(payload);

        if (stageEl && stageLabel) {
            stageEl.textContent = stageLabel;
        }
        if (stage) {
            updateStepList(stage);
        }
        if (detailEl) {
            detailEl.textContent = payload.detail || '';
            detailEl.hidden = !payload.detail;
        }

        updateProgressBar(resolvePercent(payload));

        if (fromPoll) {
            updateCompanyStatus(payload);
            if (payload.provision_error) {
                showError(payload.provision_error);
            }
            if (payload.progress) {
                updateChecklist(payload.progress);
            }
        } else if (payload.provision_error) {
            showError(payload.provision_error);
        }

        const failed = payload.failed || payload.stage === 'failed';
        if (failed) {
            finished = true;
            if (pollTimer) window.clearInterval(pollTimer);
            showError(payload.provision_error || payload.detail || 'Provisioning failed');
            return;
        }

        const done = payload.done || payload.stage === 'completed'
            || (fromPoll && payload.done === true);

        if (done) {
            finishSuccess();
        }
    };

    const fetchStatus = function () {
        if (!statusUrl || finished) return;
        fetch(statusUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) { applyPayload(data, true); })
            .catch(function () {});
    };

    const startPolling = function () {
        if (!statusUrl) return;
        fetchStatus();
        pollTimer = window.setInterval(fetchStatus, 3000);
    };

    if (useReverb && tenantId && window.Echo) {
        try {
            window.Echo.private('tenant-provision.' + tenantId)
                .listen('.provision.progress', function (e) {
                    applyPayload(e, false);
                });
        } catch (e) {
            /* Reverb unavailable — polling still updates the UI */
        }
    }

    startPolling();
})();
