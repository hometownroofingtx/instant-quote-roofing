(function () {
    'use strict';
    var nav = document.querySelector('[data-settings-tabs]');
    if (!nav) return;
    var root = nav.closest('.htr-rr-admin');
    var page = nav.dataset.settingsPage || 'settings';
    var storageKey = 'htr-rr-' + page + '-tab';
    var tabs = Array.from(nav.querySelectorAll('[data-settings-tab]'));
    var panels = Array.from(root.querySelectorAll('[data-settings-panel]'));
    panels.forEach(function (panel) {
        panel.id = 'htr-settings-panel-' + panel.dataset.settingsPanel;
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', 'htr-settings-tab-' + panel.dataset.settingsPanel);
        panel.tabIndex = 0;
    });
    function activate(key) {
        if (!tabs.some(function (tab) { return tab.dataset.settingsTab === key; })) key = tabs[0].dataset.settingsTab;
        tabs.forEach(function (tab) { var active = tab.dataset.settingsTab === key; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1; });
        panels.forEach(function (panel) { panel.hidden = panel.dataset.settingsPanel !== key; });
        var save = root.querySelector(page === 'pricing' ? '.htr-pricing-save' : '.htr-settings-save');
        if (save) save.hidden = key === 'test' || key === 'floating' || key === 'transfer';
        try { sessionStorage.setItem(storageKey, key); } catch (e) {}
    }
    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activate(tab.dataset.settingsTab); });
        tab.addEventListener('keydown', function (event) {
            var next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : -1;
            if (next >= 0) { event.preventDefault(); activate(tabs[next].dataset.settingsTab); tabs[next].focus(); }
        });
    });
    root.addEventListener('invalid', function (event) {
        var panel = event.target.closest('[data-settings-panel]');
        if (panel) activate(panel.dataset.settingsPanel);
    }, true);
    var key = tabs[0].dataset.settingsTab;
    try { key = sessionStorage.getItem(storageKey) || key; } catch (e) {}
    root.classList.add('htr-' + page + '-tabbed'); nav.hidden = false; activate(key);
})();
