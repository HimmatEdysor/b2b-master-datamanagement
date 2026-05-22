(function () {
    const page = document.getElementById('companies-list-page');
    if (!page) return;

    const buttons = page.querySelectorAll('[data-panel-toggle]');
    const panels = page.querySelectorAll('.list-panel[data-panel]');

    function panelFor(name) {
        return page.querySelector('.list-panel[data-panel="' + name + '"]');
    }

    function setExpanded(btn, open) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.classList.toggle('is-active', open);
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name = btn.getAttribute('data-panel-toggle');
            const panel = panelFor(name);
            if (!panel) return;

            const willOpen = !panel.classList.contains('is-open');
            panel.classList.toggle('is-open', willOpen);
            setExpanded(btn, willOpen);

            if (name === 'migrate' && willOpen) {
                const loadBtn = document.getElementById('btn-migrate-refresh');
                if (loadBtn) loadBtn.focus();
            }
        });
    });

    panels.forEach(function (panel) {
        const name = panel.getAttribute('data-panel');
        const btn = page.querySelector('[data-panel-toggle="' + name + '"]');
        if (btn) {
            setExpanded(btn, panel.classList.contains('is-open'));
        }
    });
})();
