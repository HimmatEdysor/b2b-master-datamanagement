/**
 * Logo upload with Cropper.js — fixed aspect ratio, preview, cropped file on submit.
 */
(function () {
    function initLogoUpload() {
        const root = document.getElementById('logo-upload-root');
        if (!root || root.dataset.logoInit === '1') {
            return;
        }
        root.dataset.logoInit = '1';

        const aspectRatio = parseFloat(root.dataset.aspectRatio) || 3;
        const outputWidth = parseInt(root.dataset.outputWidth, 10) || 360;
        const outputHeight = parseInt(root.dataset.outputHeight, 10) || 120;
        const maxKb = parseInt(root.dataset.maxKb, 10) || 5120;

        const picker = document.getElementById('logo_file_picker');
        const submitInput = document.getElementById('logo_file_submit');
        const removeInput = document.getElementById('remove_logo');
        const chooseBtn = document.getElementById('logo_choose_btn');
        const applyBtn = document.getElementById('logo_apply_crop');
        const cancelBtn = document.getElementById('logo_cancel_crop');
        const changeBtn = document.getElementById('logo_change_btn');
        const removeBtn = document.getElementById('logo_remove_btn');
        const cropPanel = document.getElementById('logo_crop_panel');
        const cropImage = document.getElementById('logo_crop_image');
        const previewResult = document.getElementById('logo_preview_result');
        const previewImg = document.getElementById('logo_preview_img');
        const actionsInitial = document.getElementById('logo_actions_initial');
        const currentWrap = document.getElementById('logo_current_wrap');
        const clientError = document.getElementById('logo_client_error');
        const form = root.closest('form');

        let cropper = null;
        let pendingFile = false;
        let croppedReady = false;

        window.getLogoUploadValidationError = function () {
            if (pendingFile && !croppedReady) {
                return 'Please click “Apply crop” to finish your logo, or cancel the upload.';
            }
            return null;
        };

        function showError(msg) {
            if (!clientError) {
                if (msg) alert(msg);
                return;
            }
            if (msg) {
                clientError.textContent = msg;
                clientError.hidden = false;
            } else {
                clientError.textContent = '';
                clientError.hidden = true;
            }
        }

        function destroyCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        function clearSubmitFile() {
            if (submitInput) {
                submitInput.value = '';
            }
            croppedReady = false;
        }

        function hideCropPanel() {
            destroyCropper();
            if (cropPanel) cropPanel.hidden = true;
            if (cropImage) cropImage.removeAttribute('src');
            pendingFile = false;
        }

        function showInitialActions() {
            if (actionsInitial) actionsInitial.hidden = false;
        }

        function hideInitialActions() {
            if (actionsInitial) actionsInitial.hidden = true;
        }

        function revokeObjectUrl() {
            if (cropImage && cropImage.src && cropImage.src.startsWith('blob:')) {
                URL.revokeObjectURL(cropImage.src);
            }
        }

        chooseBtn?.addEventListener('click', function () {
            picker?.click();
        });

        changeBtn?.addEventListener('click', function () {
            picker?.click();
        });

        picker?.addEventListener('change', function () {
            const file = picker.files && picker.files[0];
            if (!file) {
                return;
            }

            if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                showError('Please choose a JPEG, PNG, or WebP image.');
                picker.value = '';
                return;
            }

            if (file.size > maxKb * 1024) {
                showError('Image must be smaller than ' + Math.round(maxKb / 1024) + 'MB.');
                picker.value = '';
                return;
            }

            showError('');
            pendingFile = true;
            croppedReady = false;
            clearSubmitFile();

            if (removeInput) removeInput.value = '0';
            if (currentWrap) currentWrap.hidden = true;
            if (previewResult) previewResult.hidden = true;
            hideCropPanel();

            revokeObjectUrl();
            const url = URL.createObjectURL(file);
            cropImage.src = url;
            cropPanel.hidden = false;
            hideInitialActions();

            cropImage.onload = function () {
                destroyCropper();
                cropper = new Cropper(cropImage, {
                    aspectRatio: aspectRatio,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    responsive: true,
                    background: false,
                    guides: true,
                    center: true,
                    highlight: true,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                });
            };

            picker.value = '';
        });

        applyBtn?.addEventListener('click', function () {
            if (!cropper) {
                showError('Select an image first.');
                return;
            }

            const canvas = cropper.getCroppedCanvas({
                width: outputWidth,
                height: outputHeight,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            if (!canvas) {
                showError('Could not crop image. Try another file.');
                return;
            }

            canvas.toBlob(
                function (blob) {
                    if (!blob) {
                        showError('Could not process image.');
                        return;
                    }

                    const file = new File([blob], 'logo.png', { type: 'image/png' });
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    submitInput.files = dt.files;

                    previewImg.src = canvas.toDataURL('image/png');
                    previewResult.hidden = false;
                    hideCropPanel();
                    hideInitialActions();
                    croppedReady = true;
                    pendingFile = false;
                    showError('');
                },
                'image/png',
                0.92
            );
        });

        cancelBtn?.addEventListener('click', function () {
            hideCropPanel();
            if (croppedReady) {
                previewResult.hidden = false;
            } else {
                showInitialActions();
                if (currentWrap && removeInput?.value !== '1') {
                    currentWrap.hidden = false;
                }
            }
            showError('');
        });

        removeBtn?.addEventListener('click', function () {
            hideCropPanel();
            clearSubmitFile();
            if (previewResult) previewResult.hidden = true;
            if (removeInput) removeInput.value = '1';
            if (currentWrap) currentWrap.hidden = true;
            croppedReady = false;
            pendingFile = false;
            showInitialActions();
            showError('');
        });

        if (form && form.id !== 'register-form') {
            form.addEventListener('submit', function (e) {
                const logoErr = window.getLogoUploadValidationError?.();
                if (logoErr) {
                    e.preventDefault();
                    showError(logoErr);
                    cropPanel?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    showError('');
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLogoUpload);
    } else {
        initLogoUpload();
    }
})();
