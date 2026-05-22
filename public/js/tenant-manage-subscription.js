(function () {
    const form = document.getElementById('tenantManageSubscriptionForm');
    if (!form) return;

    const planSelect = document.getElementById('subscription_plan_id_manage');
    const billedWrap = document.getElementById('tenant-billed-at-wrap');
    const expiresWrap = document.getElementById('tenant-expires-at-wrap');
    const renewWrap = document.getElementById('tenant-renew-billing-wrap');
    const freeNote = document.getElementById('tenant-billing-free-note');
    const billedInput = document.getElementById('subscription_billed_at_manage');
    const expiresInput = document.getElementById('subscription_expires_at_manage');
    const expiresHint = document.getElementById('tenant-expires-hint');

    let planMeta = {};
    try {
        planMeta = JSON.parse(form.dataset.planBilling || '{}');
    } catch (e) {
        planMeta = {};
    }

    function selectedPlanId() {
        return planSelect ? String(planSelect.value || '') : '';
    }

    function metaForPlan(id) {
        return planMeta[id] || null;
    }

    function addMonths(dateStr, months) {
        if (!dateStr) return '';
        const parts = dateStr.split('-').map(Number);
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        d.setMonth(d.getMonth() + months);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function addYears(dateStr, years) {
        if (!dateStr) return '';
        const parts = dateStr.split('-').map(Number);
        const d = new Date(parts[0] + years, parts[1] - 1, parts[2]);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function computeExpires() {
        const meta = metaForPlan(selectedPlanId());
        if (!meta || meta.no_expiry || !billedInput || !expiresInput) return;

        const billed = billedInput.value;
        if (!billed) return;

        const next = meta.interval === 'yearly' ? addYears(billed, 1) : addMonths(billed, 1);
        if (next) {
            expiresInput.value = next;
        }
        if (expiresHint) {
            expiresHint.textContent = 'Auto: last billing + 1 ' + (meta.interval_label || 'month') + ' (override if needed).';
        }
    }

    function syncBillingUi() {
        const meta = metaForPlan(selectedPlanId());
        const noPlan = !selectedPlanId();
        const noExpiry = noPlan || (meta && meta.no_expiry);

        [billedWrap, expiresWrap, renewWrap].forEach(function (el) {
            if (el) el.hidden = noExpiry;
        });

        if (freeNote) freeNote.hidden = !noExpiry || noPlan;

        if (billedInput) billedInput.disabled = noExpiry;
        if (expiresInput) expiresInput.disabled = noExpiry;

        if (noExpiry) {
            if (billedInput) billedInput.value = '';
            if (expiresInput) expiresInput.value = '';
        } else {
            computeExpires();
        }
    }

    planSelect?.addEventListener('change', syncBillingUi);
    billedInput?.addEventListener('change', computeExpires);

    syncBillingUi();
})();
