(function () {
    const panel = document.getElementById('tenant-migrate-panel');
    if (!panel) return;

    const queueUrl = panel.dataset.queueUrl;
    const migrateTemplate = panel.dataset.migrateUrlTemplate || '';
    const csrf = panel.dataset.csrf || '';
    const btnRefresh = document.getElementById('btn-migrate-refresh');
    const btnRun = document.getElementById('btn-migrate-run');
    const meta = document.getElementById('migrate-queue-meta');
    const progress = document.getElementById('migrate-queue-progress');
    const wrap = document.getElementById('migrate-queue-wrap');
    const tbody = document.getElementById('migrate-queue-body');

    let queue = [];
    let running = false;

    function migrateUrl(tenantId) {
        return migrateTemplate.replace('__ID__', String(tenantId));
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function badgeFor(status) {
        if (status === 'ok') return '<span class="badge badge-migrate-ok">OK</span>';
        if (status === 'failed') return '<span class="badge badge-migrate-fail">Failed</span>';
        if (status === 'running') return '<span class="badge badge-migrate-running">Running…</span>';
        if (status === 'pending') return '<span class="badge badge-migrate-pending">Pending</span>';
        return '<span class="badge badge-migrate-pending">—</span>';
    }

    function renderQueue() {
        if (!tbody) return;
        tbody.innerHTML = '';
        queue.forEach(function (t, i) {
            const domains = Array.isArray(t.domains) ? t.domains.join(', ') : '';
            const rowStatus = t._run_status || 'pending';
            const tr = document.createElement('tr');
            tr.id = 'migrate-row-' + t.tenant_id;
            tr.className = 'migrate-row-' + rowStatus;
            tr.innerHTML =
                '<td>' + (i + 1) + '</td>' +
                '<td class="migrate-result-cell">' + badgeFor(rowStatus) + '</td>' +
                '<td>' + escapeHtml(t.name) + '</td>' +
                '<td><code class="code-pill">' + escapeHtml(t.slug) + '</code></td>' +
                '<td><code class="code-pill code-pill-muted">' + escapeHtml((t.database && t.database.database) || '') + '</code></td>' +
                '<td class="migrate-domains-cell">' + escapeHtml(domains || (t.primary_host || '—')) + '</td>' +
                '<td class="migrate-msg-cell">' + escapeHtml(t._run_message || '') + '</td>';
            tbody.appendChild(tr);
        });
        if (wrap) wrap.classList.toggle('d-none', queue.length === 0);
    }

    function setRow(tenantId, status, message) {
        const item = queue.find(function (t) { return t.tenant_id === tenantId; });
        if (item) {
            item._run_status = status;
            item._run_message = message || '';
        }
        renderQueue();
    }

    async function refreshQueue() {
        if (!queueUrl) return;
        if (meta) meta.textContent = 'Loading companies from master…';
        if (btnRefresh) btnRefresh.disabled = true;
        try {
            const res = await fetch(queueUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const json = await res.json().catch(function () { return {}; });
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Could not load migration queue.');
            }
            const data = json.data || {};
            queue = (data.tenants || []).map(function (t) {
                return Object.assign({}, t, { _run_status: 'pending', _run_message: '' });
            });
            renderQueue();
            const capped = data.capped ? ' (list capped — raise TENANT_CRM_MIGRATE_BULK_MAX_TENANTS)' : '';
            if (meta) {
                meta.textContent = queue.length + ' of ' + (data.total || queue.length) +
                    ' company databases loaded. Domains included per row.' + capped;
            }
            if (btnRun) btnRun.disabled = queue.length === 0 || running;
        } catch (e) {
            if (meta) meta.textContent = 'Error: ' + (e.message || 'Refresh failed');
            queue = [];
            renderQueue();
            if (btnRun) btnRun.disabled = true;
        } finally {
            if (btnRefresh) btnRefresh.disabled = false;
        }
    }

    async function runOne(t) {
        setRow(t.tenant_id, 'running', 'php artisan migrate --force…');
        const res = await fetch(migrateUrl(t.tenant_id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: '{}',
        });
        const json = await res.json().catch(function () { return {}; });
        const ok = res.ok && json.success;
        const msg = (json.data && json.data.message) || json.message || (ok ? 'OK' : 'Migration failed');
        setRow(t.tenant_id, ok ? 'ok' : 'failed', msg);
        return ok;
    }

    async function runAll() {
        if (running || queue.length === 0) return;
        running = true;
        if (btnRun) btnRun.disabled = true;
        if (btnRefresh) btnRefresh.disabled = true;

        let okCount = 0;
        let failCount = 0;

        for (let i = 0; i < queue.length; i++) {
            const t = queue[i];
            if (progress) {
                progress.classList.remove('d-none');
                progress.textContent = 'Running ' + (i + 1) + ' / ' + queue.length + ': ' + (t.name || t.slug) + '…';
            }
            const ok = await runOne(t);
            if (ok) okCount++;
            else failCount++;
        }

        running = false;
        if (btnRefresh) btnRefresh.disabled = false;
        if (btnRun) btnRun.disabled = queue.length === 0;
        if (progress) {
            progress.textContent = 'Finished: ' + okCount + ' succeeded, ' + failCount + ' failed. Refresh list if you added new companies or domains.';
        }
    }

    if (btnRefresh) btnRefresh.addEventListener('click', refreshQueue);
    if (btnRun) {
        btnRun.addEventListener('click', function () {
            if (!confirm('Run php artisan migrate --force on ' + queue.length + ' databases, one after another?')) {
                return;
            }
            runAll();
        });
    }
})();
