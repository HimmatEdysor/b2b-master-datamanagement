/**
 * Blog slug: auto from title, spaces → hyphens, unique suffix handled on server.
 */
window.slugifyBlogTitle = function (value) {
    if (typeof value !== 'string') {
        return '';
    }
    return value
        .toLowerCase()
        .trim()
        .replace(/[\s_]+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');
};

window.bindBlogSlugInput = function (titleInput, slugInput, options) {
    if (!titleInput || !slugInput) {
        return;
    }

    options = options || {};
    const preview = options.previewEl || null;
    let manual = Boolean(options.startManual);

    if (slugInput.value.trim() !== '') {
        manual = true;
    }

    function updatePreview(slug) {
        if (preview) {
            preview.textContent = slug ? '/blog/' + slug : '/blog/…';
        }
    }

    function applySlugFromTitle() {
        if (manual) {
            return;
        }
        const slug = window.slugifyBlogTitle(titleInput.value);
        slugInput.value = slug;
        updatePreview(slug);
    }

    function sanitizeSlugField() {
        const cleaned = window.slugifyBlogTitle(slugInput.value);
        if (slugInput.value !== cleaned) {
            slugInput.value = cleaned;
        }
        updatePreview(cleaned);
    }

    titleInput.addEventListener('input', applySlugFromTitle);

    slugInput.addEventListener('input', function () {
        manual = true;
        sanitizeSlugField();
    });

    slugInput.addEventListener('keydown', function (e) {
        if (e.key === ' ') {
            e.preventDefault();
            const start = slugInput.selectionStart;
            const end = slugInput.selectionEnd;
            const val = slugInput.value;
            slugInput.value = val.slice(0, start) + '-' + val.slice(end);
            slugInput.selectionStart = slugInput.selectionEnd = start + 1;
            manual = true;
            sanitizeSlugField();
        }
    });

    slugInput.addEventListener('paste', function (e) {
        e.preventDefault();
        manual = true;
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        const start = slugInput.selectionStart;
        const end = slugInput.selectionEnd;
        slugInput.value = window.slugifyBlogTitle(
            slugInput.value.slice(0, start) + pasted + slugInput.value.slice(end)
        );
        sanitizeSlugField();
    });

    if (options.resetManualBtn) {
        options.resetManualBtn.addEventListener('click', function (e) {
            e.preventDefault();
            manual = false;
            applySlugFromTitle();
        });
    }

    sanitizeSlugField();
    if (!manual) {
        applySlugFromTitle();
    }
};

document.addEventListener('DOMContentLoaded', function () {
    const title = document.getElementById('blog_title');
    const slug = document.getElementById('blog_slug');
    if (!title || !slug) {
        return;
    }
    window.bindBlogSlugInput(title, slug, {
        previewEl: document.getElementById('blog_slug_preview'),
        resetManualBtn: document.getElementById('blog_slug_sync_title'),
        startManual: slug.dataset.manual === '1',
    });
});
