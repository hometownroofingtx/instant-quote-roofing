(function () {
    'use strict';
    const form = document.querySelector('.htr-pricing-form');
    if (!form) return;
    let nextIndex = Date.now();
    const feedback = form.querySelector('.htr-pricing-feedback');
    form.addEventListener('click', function (event) {
        const chooseImage = event.target.closest('.htr-material-image__choose');
        if (chooseImage) {
            const picker = chooseImage.closest('.htr-material-image');
            const frame = wp.media({ title: 'Choose roof type image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
            frame.on('open', function () {
                const id = Number(picker.querySelector('input[type="hidden"]').value);
                if (id) frame.state().get('selection').add(wp.media.attachment(id));
            });
            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                picker.querySelector('input[type="hidden"]').value = attachment.id;
                const image = document.createElement('img');
                image.src = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                image.alt = '';
                chooseImage.replaceChildren(image);
                picker.querySelector('.htr-material-image__clear').hidden = false;
                feedback.textContent = 'Roof image selected. Save pricing to apply it to the customer form.';
            });
            frame.open();
            return;
        }
        const clearImage = event.target.closest('.htr-material-image__clear');
        if (clearImage) {
            const picker = clearImage.closest('.htr-material-image');
            picker.querySelector('input[type="hidden"]').value = '0';
            picker.querySelector('.htr-material-image__choose').innerHTML = '<span class="dashicons dashicons-format-image" aria-hidden="true"></span><span class="htr-image-add-label">Add image</span>';
            clearImage.hidden = true;
            picker.querySelector('.htr-material-image__choose').focus();
            feedback.textContent = 'Roof image removed. Save pricing to apply the change.';
            return;
        }
        const add = event.target.closest('[data-htr-add]');
        if (add) {
            const kind = add.dataset.htrAdd;
            const template = document.getElementById('htr-' + kind + '-template');
            const target = document.getElementById(kind === 'material' ? 'htr-material-rows' : 'htr-area-rows');
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace('__INDEX__', String(nextIndex));
            });
            nextIndex++;
            const row = fragment.querySelector('tr');
            target.appendChild(fragment);
            row.querySelector('input').focus();
            feedback.textContent = 'New ' + (kind === 'material' ? 'roof type' : 'service area') + ' added. Save pricing to keep it.';
        }
        const remove = event.target.closest('.htr-remove-pricing-row');
        if (remove) {
            const row = remove.closest('tr');
            const kind = row.closest('tbody').id === 'htr-material-rows' ? 'material' : 'area';
            row.remove();
            form.querySelector('[data-htr-add="' + kind + '"]').focus();
            feedback.textContent = 'Unsaved row removed.';
        }
    });
}());
