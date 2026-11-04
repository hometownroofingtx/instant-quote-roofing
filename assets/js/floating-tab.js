(function () {
    'use strict';
    function openDrawer(tab, opener) {
        var preview = tab.hasAttribute('data-preview');
        var dialog = document.querySelector(preview ? '[data-htr-preview-drawer]' : '[data-htr-drawer]');
        if (!dialog && preview) {
            dialog = document.createElement('dialog'); dialog.className = 'htr-drawer'; dialog.setAttribute('data-htr-preview-drawer', '');
            var header = document.createElement('header'); header.className = 'htr-drawer__header';
            var title = document.createElement('h2'); title.id = 'htr-drawer-preview-title'; title.textContent = 'Form drawer preview';
            var close = document.createElement('button'); close.type = 'button'; close.setAttribute('data-drawer-close', ''); close.setAttribute('aria-label', 'Close preview'); close.textContent = '×';
            header.append(title, close); dialog.setAttribute('aria-labelledby', title.id);
            var sample = document.createElement('div'); sample.className = 'htr-drawer__sample'; dialog.append(header, sample); document.body.append(dialog);
        }
        if (!dialog || typeof dialog.showModal !== 'function') return false;
        dialog.classList.toggle('htr-drawer--left', tab.classList.contains('htr-floating--left'));
        if (preview) dialog.querySelector('.htr-drawer__sample').textContent = 'On your website, the ' + (tab.dataset.drawerForm === 'calculator' ? 'Roof Calculator' : 'Instant Roof Report') + ' form loads here with your current contact-step settings. No form is submitted in this preview.';
        var frame = dialog.querySelector('iframe');
        if (frame && !frame.getAttribute('src')) {
            var body = dialog.querySelector('.htr-drawer__body');
            var loader = dialog.querySelector('.htr-drawer__loading');
            var slow = window.setTimeout(function () {
                if (loader) loader.querySelector('strong').textContent = 'Still loading your form…';
                if (loader) loader.querySelector('span:last-child').textContent = 'You can also use the new-tab link below.';
            }, 15000);
            frame.addEventListener('load', function () {
                // Ignore the iframe's initial empty document, if its load event arrives late.
                try { if (frame.contentWindow.location.href === 'about:blank') return; } catch (e) {}
                window.clearTimeout(slow);
                if (loader) loader.hidden = true;
                if (body) body.setAttribute('aria-busy', 'false');
                frame.hidden = false;
            }, false);
            frame.src = frame.dataset.src;
        }
        if (!dialog.dataset.bound) {
            dialog.dataset.bound = '1';
            dialog.querySelector('[data-drawer-close]').addEventListener('click', function () { dialog.close(); });
            dialog.addEventListener('click', function (event) {
                var rect = dialog.getBoundingClientRect();
                if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
            });
            dialog.addEventListener('close', function () { document.body.style.overflow = dialog._overflow || ''; if (dialog._opener && dialog._opener.isConnected) dialog._opener.focus(); });
        }
        dialog._opener = opener; dialog._overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden'; dialog.showModal(); dialog.querySelector('[data-drawer-close]').focus();
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && dialog.animate) {
            dialog.animate([{ transform: 'translateX(' + (tab.classList.contains('htr-floating--left') ? '-100%' : '100%') + ')' }, { transform: 'translateX(0)' }], { duration: 240, easing: 'ease-out' });
        }
        return true;
    }
    function init(tab) {
        if (tab.dataset.bound) return;
        tab.dataset.bound = '1';
        var preview = tab.hasAttribute('data-preview');
        var toggle = tab.querySelector('.htr-floating__toggle');
        var links = tab.querySelector('.htr-floating__links');
        var message = tab.querySelector('[data-htr-floating-message]');
        var close = tab.querySelector('.htr-floating__close');
        var closing = false;
        function expanded(open) { if (toggle && links) { toggle.setAttribute('aria-expanded', String(open)); links.hidden = !open; } }
        if (toggle) {
            toggle.addEventListener('click', function () { expanded(toggle.getAttribute('aria-expanded') !== 'true'); });
            tab.addEventListener('mouseenter', function () { if (tab.dataset.behavior === 'hover') expanded(true); });
            tab.addEventListener('mouseleave', function () { if (tab.dataset.behavior === 'hover' && !tab.contains(document.activeElement)) expanded(false); });
            tab.addEventListener('focusin', function () { if (!closing && tab.dataset.behavior === 'hover') expanded(true); });
            tab.addEventListener('focusout', function (event) { if (tab.dataset.behavior === 'hover' && !tab.contains(event.relatedTarget)) expanded(false); });
            tab.addEventListener('keydown', function (event) { if (event.key === 'Escape') { event.preventDefault(); closing = true; expanded(false); toggle.focus(); closing = false; } });
        }
        tab.addEventListener('click', function (event) {
            var link = event.target.closest('a'); if (!link) return;
            var quote = !!link.closest('.htr-floating__message') || link.classList.contains('htr-floating__tab');
            var linkUrl = preview && link.dataset.previewUrl ? link.dataset.previewUrl : link.href;
            var match = (tab.dataset.drawerPageUrl && linkUrl.replace(/\/$/, '') === tab.dataset.drawerPageUrl.replace(/\/$/, '')) || (tab.dataset.drawerUrl && linkUrl === tab.dataset.drawerUrl);
            if (tab.dataset.openMode === 'drawer' && (quote || match) && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) { if (openDrawer(tab, link)) event.preventDefault(); }
            if (preview) event.preventDefault();
        });
        if (!message || !close) return;
        if (!preview) { try { message.hidden = sessionStorage.getItem('htr-rr-floating-dismissed') === '1'; } catch (e) {} }
        close.addEventListener('click', function () {
            message.hidden = true;
            tab.querySelector('.htr-floating__tab').focus();
            if (!preview) { try { sessionStorage.setItem('htr-rr-floating-dismissed', '1'); } catch (e) {} }
        });
    }
    window.HTRFloatingTab = { init: init };
    document.querySelectorAll('[data-htr-floating]').forEach(init);
    window.addEventListener('message', function (event) {
        var dialog = document.querySelector('[data-htr-drawer]');
        var frame = dialog && dialog.querySelector('iframe');
        if (dialog && dialog.open && frame && event.source === frame.contentWindow && event.origin === window.location.origin && event.data && event.data.type === 'htr-drawer-close') dialog.close();
    });
    document.addEventListener('click', function (event) {
        document.querySelectorAll('[data-htr-floating]').forEach(function (tab) {
            if (tab.contains(event.target)) return;
            var toggle = tab.querySelector('.htr-floating__toggle'), links = tab.querySelector('.htr-floating__links');
            if (toggle && links) { toggle.setAttribute('aria-expanded', 'false'); links.hidden = true; }
        });
    });
})();
