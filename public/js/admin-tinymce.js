/**
 * TinyMCE for master-portal admin (pages, blog).
 * Expects: meta[name="csrf-token"], meta[name="tinymce-upload-url"]
 */
window.initAdminTinyMce = function (selector, opts) {
    opts = opts || {};
    const csrfEl = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
    const uploadMeta = document.querySelector('meta[name="tinymce-upload-url"]');
    const uploadUrl = (uploadMeta && uploadMeta.getAttribute('content')) || '/admin/upload-image';

    function uploadImageFile(file) {
        return new Promise(function (resolve, reject) {
            if (!file || !file.type || !file.type.startsWith('image/')) {
                reject(new Error('Please select a valid image file.'));
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                reject(new Error('Image must not exceed 10MB.'));
                return;
            }

            const formData = new FormData();
            formData.append('file', file, file.name);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl, true);
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.onload = function () {
                try {
                    const json = JSON.parse(xhr.responseText || '{}');
                    if (xhr.status >= 200 && xhr.status < 300 && json.location) {
                        resolve(String(json.location).trim());
                        return;
                    }
                    reject(new Error(json.message || 'Upload failed.'));
                } catch (e) {
                    reject(new Error('Upload failed.'));
                }
            };
            xhr.onerror = function () {
                reject(new Error('Network error during upload.'));
            };
            xhr.send(formData);
        });
    }

    return tinymce.init({
        selector: selector,
        license_key: 'gpl',
        plugins: 'advlist autolink lists link image charmap preview anchor code',
        toolbar:
            'undo redo | blocks | bold italic underline strikethrough | ' +
            'alignleft aligncenter alignright | bullist numlist outdent indent | ' +
            'link image | removeformat code',
        height: opts.height || 420,
        width: '100%',
        menubar: false,
        branding: false,
        promotion: false,
        resize: true,
        relative_urls: false,
        remove_script_host: false,
        paste_data_images: false,
        automatic_uploads: true,
        images_upload_url: uploadUrl,
        images_file_types: 'jpeg,jpg,png,gif,webp',
        images_upload_handler: function (blobInfo) {
            return uploadImageFile(blobInfo.blob());
        },
        file_picker_types: 'image',
        file_picker_callback: function (callback) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = function () {
                const file = input.files && input.files[0];
                if (!file) {
                    return;
                }
                uploadImageFile(file)
                    .then(function (url) {
                        callback(url, { alt: file.name });
                    })
                    .catch(function (err) {
                        alert(err.message || 'Upload failed.');
                    });
            };
            input.click();
        },
        setup: function (editor) {
            editor.on('change keyup', function () {
                editor.save();
            });
        },
    });
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof tinymce === 'undefined') {
        return;
    }

    document.querySelectorAll('textarea.tinymce-editor').forEach(function (el) {
        if (!el.id || el.dataset.tinymceInitialized === '1') {
            return;
        }
        el.dataset.tinymceInitialized = '1';
        window.initAdminTinyMce('#' + el.id);
    });

    document.querySelectorAll('form[data-tinymce-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }
        });
    });
});
