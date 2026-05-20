/**
 * Copy buttons: data-copy="text to copy"
 */
(function () {
    let toastTimer = null;

    function showToast(message) {
        let toast = document.getElementById('copy-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'copy-toast';
            toast.className = 'copy-toast';
            toast.setAttribute('role', 'status');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 2000);
    }

    async function copyText(text, button) {
        if (!text) {
            return;
        }
        try {
            await navigator.clipboard.writeText(text);
            showToast('Copied!');
            if (button) {
                const prev = button.getAttribute('title');
                button.setAttribute('title', 'Copied!');
                setTimeout(function () {
                    if (prev) {
                        button.setAttribute('title', prev);
                    }
                }, 1500);
            }
        } catch (e) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showToast('Copied!');
            } catch (err) {
                showToast('Copy failed');
            }
            document.body.removeChild(ta);
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-copy');
        if (!btn || btn.disabled || btn.classList.contains('is-disabled')) {
            return;
        }
        const text = btn.getAttribute('data-copy');
        if (text) {
            e.preventDefault();
            copyText(text, btn);
        }
    });
})();
