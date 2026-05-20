/**
 * Subdomain slug fields: no spaces, lowercase a-z 0-9 hyphen only.
 */
window.sanitizeTenantSlugInput = function (value) {
    if (typeof value !== 'string') {
        return '';
    }
    return value
        .toLowerCase()
        .replace(/\s/g, '')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');
};

window.bindTenantSlugInput = function (slugInput, options) {
    if (!slugInput) {
        return;
    }

    options = options || {};
    const onChange = typeof options.onChange === 'function' ? options.onChange : function () {};

    function applySanitize() {
        const cleaned = window.sanitizeTenantSlugInput(slugInput.value);
        if (slugInput.value !== cleaned) {
            slugInput.value = cleaned;
        }
        onChange(cleaned);
    }

    slugInput.addEventListener('input', applySanitize);
    slugInput.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        const start = slugInput.selectionStart;
        const end = slugInput.selectionEnd;
        const merged =
            slugInput.value.slice(0, start) + pasted + slugInput.value.slice(end);
        slugInput.value = window.sanitizeTenantSlugInput(merged);
        applySanitize();
    });
    slugInput.addEventListener('keydown', function (e) {
        if (e.key === ' ') {
            e.preventDefault();
        }
    });

    applySanitize();
};
