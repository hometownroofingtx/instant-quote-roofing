(function () {
    'use strict';
    document.querySelectorAll('[data-design-image]').forEach(function (group) {
        var input = group.querySelector('input'), preview = group.querySelector('[data-image-preview]'), status = group.querySelector('[data-image-status]');
        group.querySelector('[data-choose-image]').addEventListener('click', function () {
            var frame = wp.media({ title: 'Choose design image', button: { text: 'Use image' }, library: { type: 'image' }, multiple: false });
            frame.on('open', function () { if (Number(input.value)) frame.state().get('selection').add(wp.media.attachment(Number(input.value))); });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON(); input.value = attachment.id;
                var image = document.createElement('img'); image.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url; image.alt = '';
                preview.replaceChildren(image); status.textContent = 'Image selected. Save settings to apply.';
            });
            frame.open();
        });
        group.querySelector('[data-clear-image]').addEventListener('click', function () { input.value = '0'; preview.replaceChildren(); status.textContent = 'Default image selected. Save settings to apply.'; });
    });
})();
