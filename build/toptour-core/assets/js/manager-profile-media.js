(function () {
    if (typeof window.wp === 'undefined' || typeof window.wp.media === 'undefined') {
        return;
    }

    var imageInput = document.getElementById('toptour_manager_image_id');
    var selectButton = document.querySelector('.toptour-manager-select-image');
    var removeButton = document.querySelector('.toptour-manager-remove-image');
    var previewContainer = document.querySelector('.toptour-manager-image-preview');
    var previewImage = document.querySelector('.toptour-manager-image-preview-img');

    if (!imageInput || !selectButton || !removeButton || !previewContainer || !previewImage) {
        return;
    }

    var mediaFrame = null;

    selectButton.addEventListener('click', function (event) {
        event.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = window.wp.media({
            title: 'Select manager image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var imageUrl = attachment.url;

            if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
                imageUrl = attachment.sizes.thumbnail.url;
            }

            imageInput.value = attachment.id;
            previewImage.setAttribute('src', imageUrl);
            previewContainer.hidden = false;
        });

        mediaFrame.open();
    });

    removeButton.addEventListener('click', function (event) {
        event.preventDefault();

        imageInput.value = '';
        previewImage.setAttribute('src', '');
        previewContainer.hidden = true;
    });
})();
