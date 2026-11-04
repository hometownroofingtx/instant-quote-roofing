(function () {
    'use strict';
    var root = document.querySelector('[data-floating-admin]');
    if (!root) return;
    var form = root.querySelector('#htr-ft-form'), items = root.querySelector('[data-items]');
    var preview = root.querySelector('[data-preview]'), scene = root.querySelector('[data-scene]');
    var icons = JSON.parse(root.querySelector('[data-icons]').textContent), nextIndex = Date.now();
    function field(key) { return form.elements[key]; }
    function value(key) { return field(key).value; }
    function checked(key) { return field(key).checked; }
    function node(tag, cls, text) { var el = document.createElement(tag); if (cls) el.className = cls; if (text != null) el.textContent = text; return el; }
    function icon(name) { var wrap = node('span', 'htr-floating__icon'); wrap.innerHTML = icons[name] || icons.report; return wrap; }
    function update() {
        var layout = value('floating_style'), side = value('floating_position'), vertical = value('floating_vertical');
        var tab = node('aside', 'htr-floating htr-floating--' + side + ' htr-floating--' + vertical + ' htr-floating--' + layout);
        tab.setAttribute('data-htr-floating', ''); tab.setAttribute('data-preview', 'true'); tab.dataset.behavior = value('floating_behavior'); tab.setAttribute('aria-label', 'Quick links preview');
        tab.dataset.openMode = value('floating_open_mode'); tab.dataset.drawerForm = value('floating_drawer_form');
        var device = scene.dataset.device;
        tab.hidden = !checked('floating_enabled') || !checked('floating_' + device);
        root.querySelector('[data-preview-status]').textContent = !checked('floating_enabled') ? 'Disabled' : (tab.hidden ? 'Hidden on ' + device : 'Unsaved preview');
        ['color', 'hover', 'background'].forEach(function (name) { tab.style.setProperty('--htr-tab-' + (name === 'background' ? 'bg' : name), value('floating_' + name)); });
        tab.style.setProperty('--htr-tab-offset', Math.min(100, Math.max(0, Number(value('floating_offset')) || 0)) + 'px');
        tab.style.setProperty('--htr-tab-radius', Math.min(40, Math.max(0, Number(value('floating_radius')) || 0)) + 'px');
        if (!checked('floating_shadow')) tab.style.setProperty('--htr-tab-shadow', 'none');
        var page = field('floating_page').selectedOptions[0];
        tab.dataset.drawerPageUrl = page.dataset.url || '';
        var fallback = { label: value('floating_label') || 'Instant Roof Quote', url: page.dataset.url || '#', icon: 'calculator' };
        var list = Array.from(items.querySelectorAll('[data-item]')).map(function (row) {
            return { label: row.querySelector('[name$="[label]"]').value, url: row.querySelector('[name$="[url]"]').value, icon: row.querySelector('select').value };
        }).filter(function (item) { return item.label.trim() || item.url.trim(); });
        var configured = (layout !== 'quote' && list.length > 0) || value('floating_open_mode') === 'drawer' || page.value !== '0';
        if (checked('floating_enabled') && !configured) {
            tab.hidden = true;
            root.querySelector('[data-preview-status]').textContent = 'Select a destination page or choose Drawer';
        }
        if (!list.length) list.push(fallback);
        if (layout === 'quote') {
            if (value('floating_heading') || value('floating_message')) {
                var message = node('div', 'htr-floating__message'); message.setAttribute('data-htr-floating-message', '');
                var close = node('button', 'htr-floating__close', '×'); close.type = 'button'; close.setAttribute('aria-label', 'Dismiss preview message');
                var link = node('a'); link.href = '#'; link.append(node('strong', '', value('floating_heading')), node('span', '', value('floating_message')));
                message.append(close, link); tab.append(message);
            }
            var quote = node('a', 'htr-floating__tab', fallback.label); quote.href = '#'; tab.append(quote);
        } else {
            var nav = node('nav', 'htr-floating__links'); nav.id = 'htr-floating-preview-links'; nav.setAttribute('aria-label', 'Quick links');
            if (layout === 'expandable') {
                var toggle = node('button', 'htr-floating__toggle'); toggle.type = 'button'; toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-controls', nav.id); toggle.setAttribute('aria-label', 'Toggle quick links'); toggle.append(icon('report')); tab.append(toggle); nav.hidden = true;
            }
            list.forEach(function (item) { var link = node('a', 'htr-floating__item'); link.href = '#'; link.dataset.previewUrl = item.url; link.append(icon(item.icon), node('span', 'htr-floating__label', item.label || 'Link label')); nav.append(link); });
            tab.append(nav);
        }
        preview.replaceChildren(tab);
        if (window.HTRFloatingTab) window.HTRFloatingTab.init(tab);
    }
    var tabs = Array.from(root.querySelectorAll('[data-tab]'));
    function activate(button) {
        tabs.forEach(function (tab) { var active = tab === button; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1; });
        root.querySelectorAll('[data-panel]').forEach(function (panel) { panel.hidden = panel.dataset.panel !== button.dataset.tab; });
    }
    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activate(tab); });
        tab.addEventListener('keydown', function (event) {
            var next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : -1;
            if (next >= 0) { event.preventDefault(); activate(tabs[next]); tabs[next].focus(); }
        });
    });
    root.querySelector('[data-add]').addEventListener('click', function () {
        if (items.children.length >= 10) { root.querySelector('[data-item-status]').textContent = 'A maximum of 10 links is supported.'; return; }
        var fragment = root.querySelector('[data-item-template]').content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(function (input) { input.name = input.name.replace('__INDEX__', String(nextIndex)); }); nextIndex++;
        var focus = fragment.querySelector('input'); items.append(fragment); focus.focus(); changed();
    });
    items.addEventListener('click', function (event) {
        var button = event.target.closest('button'); if (!button) return;
        var row = button.closest('[data-item]');
        if (button.hasAttribute('data-remove')) { row.remove(); root.querySelector('[data-add]').focus(); }
        if (button.dataset.move === 'up' && row.previousElementSibling) items.insertBefore(row, row.previousElementSibling);
        if (button.dataset.move === 'down' && row.nextElementSibling) items.insertBefore(row.nextElementSibling, row);
        root.querySelector('[data-item-status]').textContent = 'Links updated. Save Changes to apply.'; changed();
    });
    function changed() { root.querySelector('[data-unsaved]').textContent = 'You have unsaved changes.'; update(); }
    form.addEventListener('input', changed); form.addEventListener('change', changed);
    root.querySelectorAll('[data-device]').forEach(function (button) { if (button === scene) return; button.addEventListener('click', function () { scene.dataset.device = button.dataset.device; root.querySelectorAll('button[data-device]').forEach(function (other) { other.setAttribute('aria-pressed', String(other === button)); }); update(); }); });
    root.querySelector('[data-import]').addEventListener('click', function () { root.querySelector('#htr-ft-import-file').click(); });
    root.querySelector('#htr-ft-import-file').addEventListener('change', function (event) {
        if (!event.target.files.length) return;
        if (window.confirm('Import replaces all floating-tab settings. Export a backup first. Continue?')) root.querySelector('#htr-ft-import').requestSubmit();
        else event.target.value = '';
    });
    update();
})();
