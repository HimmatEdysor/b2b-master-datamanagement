(function () {
    document.querySelectorAll('[data-media-upload]').forEach(function (root) {
        var input = root.querySelector('[data-media-file]');
        var newPreview = root.querySelector('[data-media-new-preview]');
        var newImg = root.querySelector('[data-media-new-img]');
        var isImage = root.getAttribute('data-media-type') === 'image';

        if (!input) {
            return;
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                if (newPreview) {
                    newPreview.hidden = true;
                }
                return;
            }

            if (isImage && newImg && newPreview) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    newImg.src = e.target.result;
                    newPreview.hidden = false;
                };
                reader.readAsDataURL(file);
            } else if (newPreview) {
                newPreview.textContent = 'Selected: ' + file.name;
                newPreview.hidden = false;
            }
        });
    });
})();
