const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { pathToFileURL } = require('node:url');
(async () => {
    const browser = await chromium.launch({ headless: true, channel: process.env.HTR_BROWSER_CHANNEL || undefined });
    const output = path.resolve(__dirname, '../../../../reports/calculator-design'); fs.mkdirSync(output, { recursive: true });
    try {
        for (const mode of ['plugin', 'ghl_form']) {
            const page = await browser.newPage({ viewport: { width: 1440, height: 1050 } });
            const errors = []; page.on('pageerror', e => errors.push(e.message));
            await page.addInitScript(mode => {
                window.HTR_RR = { browserKey: 'fixture', contactMode: mode, ghlFormId: 'fixture', ghlFormBase: 'https://example.test/form/', ajaxUrl: '/ajax' };
                if (mode === 'ghl_form') window.fetch = async () => ({ ok: false, json: async () => ({ ok: false, message: 'Fixture preparation failed. Please retry.' }) });
                class Address extends HTMLElement { connectedCallback() { this.textContent = 'Address search fixture'; } }
                customElements.define('gmp-place-autocomplete', Address);
                window.google = { maps: { async importLibrary(name) {
                    if (name === 'places') return { PlaceAutocompleteElement: Address };
                    if (name === 'maps') return { Map: class { addListener() {} setCenter() {} setZoom() {} } };
                    if (name === 'marker') return { Marker: class { addListener() {} setPosition() {} } };
                } } };
            }, mode);
            await page.goto(pathToFileURL(path.join(__dirname, '.calculator-' + mode + '-preview.html')).href);
            assert.equal(await page.locator('[data-calc-stage]:visible').count(), 1);
            assert.equal(await page.locator('.htr-estimate-header').count(), 0);
            assert.equal(await page.locator('[data-htr-calculator]').evaluate(el => getComputedStyle(el).backgroundImage), 'none');
            assert.equal(await page.locator('[data-calc-progress="1"]').getAttribute('aria-current'), 'step');
            if (mode === 'plugin') await page.screenshot({ path: path.join(output, 'desktop.png'), fullPage: true });
            await page.locator('.htr-calc-roof').first().click();
            await page.locator('[data-calc-calculate]').click();
            assert.equal(await page.locator('[data-calc-stage="2"]').isVisible(), true);
            await page.locator('[data-calc-address-next]').click();
            assert.equal(await page.locator('[data-address-error]').isVisible(), true);
            await page.evaluate(() => {
                const event = new Event('gmp-select');
                event.placePrediction = { toPlace: () => ({ formattedAddress: '123 Main St, San Antonio, TX', location: { lat: () => 29.4, lng: () => -98.4 }, addressComponents: [{ types: ['street_number'], longText: '123' }], fetchFields: async () => {} }) };
                document.querySelector('gmp-place-autocomplete').dispatchEvent(event);
            });
            await page.locator('[data-calc-property]').waitFor({ state: 'visible' });
            await page.locator('[data-calc-address-next]').click();
            assert.equal(await page.locator('[data-calc-stage="3"]').isVisible(), true);
            assert.equal(await page.locator(mode === 'plugin' ? '[data-calc-lead]' : '[data-calc-continue]').isVisible(), true);
            if (mode === 'ghl_form') assert.match(await page.locator('[data-calc-continue]').textContent(), /Retry/);
            await page.locator('[data-calc-back="2"]').click();
            await page.locator('[data-calc-back="1"]').click();
            assert.equal(await page.locator('[name=material]:checked').count(), 1);
            for (const width of [1000, 375]) {
                await page.setViewportSize({ width, height: 850 });
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'No calculator overflow at ' + width);
            }
            if (mode === 'plugin') await page.screenshot({ path: path.join(output, 'mobile.png'), fullPage: true });
            if (mode === 'plugin') {
                await page.locator('[data-calc-calculate]').click();
                await page.locator('[data-calc-address-next]').click();
                await page.locator('[name=first_name]').fill('Fixture'); await page.locator('[name=last_name]').fill('Customer');
                await page.locator('[name=email]').fill('fixture@example.test'); await page.locator('[name=phone]').fill('2105550100');
                await page.evaluate(() => {
                    window.fetch = async () => ({ ok: true, status: 200, json: async () => ({ ok: true, estimate: { ok: true, low: 12825, high: 18525, material_label: 'Asphalt', area_sqft: 2850, billed_sqft: 2850, rate_low: 4.5, rate_high: 6.5, pitch_mult: 1, complexity: 'Medium', complexity_mult: 1, region_mult: 1 }, town: 'San Antonio', waste: 0, report: { url: 'https://example.test/?report=fixture' } }) });
                });
                await page.locator('[data-calc-send]').click();
                await page.locator('[data-calc-stage="4"]').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-calc-stage]:visible').count(), 1);
                assert.equal(await page.locator('[data-calc-progress="4"]').getAttribute('aria-current'), 'step');
                assert.match(await page.locator('[data-calc-result]').textContent(), /12,825/);
            }
            if (mode === 'ghl_form') {
                await page.route('https://example.test/**', route => route.fulfill({ contentType: 'text/html', body: '<form>GHL fixture contact form</form>' }));
                await page.route('https://link.msgsndr.com/**', route => route.abort());
                await page.evaluate(() => {
                    window.fetch = async (url, options) => { if (String(url).includes('save_contact')) { window.fixtureSavedContact = JSON.parse(options.body); window.fixtureSaveAttempts = (window.fixtureSaveAttempts || 0) + 1; if (window.fixtureSaveAttempts < 3) return { ok: false, json: async () => ({ ok: false }) }; return { ok: true, json: async () => ({ ok: true, saved: true }) }; } return ({ ok: true, json: async () => ({ ok: true, linked: false, report_id: 1, contact_key: 'fixture-capability', contact_expires: 9999999999, lead_type: 'Calculator', estimate: { ok: true, low: 12825, high: 18525, material_label: 'Asphalt', area_sqft: 2850, billed_sqft: 2850, rate_low: 4.5, rate_high: 6.5, pitch_mult: 1, complexity: 'Medium', complexity_mult: 1, region_mult: 1 }, town: 'San Antonio', waste: 0, report: { token: 'fixture', url: 'https://example.test/report' } }) }); };
                });
                await page.locator('[data-calc-calculate]').click();
                await page.locator('[data-calc-address-next]').click();
                await page.locator('[data-calc-ghl] iframe').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-calc-continue]').isVisible(), false);
                assert.equal(await page.locator('[data-calc-result]').isVisible(), false);
                assert.match(await page.locator('[data-calc-ghl] iframe').getAttribute('src'), /htr_report_id=1/);
                await page.evaluate(() => {
                    const frame = document.querySelector('[data-calc-ghl] iframe');
                    window.dispatchEvent(new MessageEvent('message', { source: frame.contentWindow, origin: 'https://attacker.example', data: ['msgsndr-form-submitted'] }));
                    window.dispatchEvent(new MessageEvent('message', { source: window, origin: 'https://api.leadconnectorhq.com', data: ['msgsndr-form-submitted'] }));
                    window.dispatchEvent(new MessageEvent('message', { source: frame.contentWindow, origin: 'https://api.leadconnectorhq.com', data: { type: 'resize', height: 800 } }));
                    window.dispatchEvent(new MessageEvent('message', { source: frame.contentWindow, origin: 'https://api.leadconnectorhq.com', data: ['set-sticky-contacts', '_ud', '{}', 'location-fixture', 'fingerprint-fixture'] }));
                    window.dispatchEvent(new MessageEvent('message', { source: frame.contentWindow, origin: 'https://api.leadconnectorhq.com', data: ['set-sticky-contacts', 'embedded_iframe_other-form', 'other-form', 'location-fixture', 'fingerprint-fixture'] }));
                });
                assert.equal(await page.locator('[data-calc-result]').isVisible(), false, 'Untrusted and resize messages cannot advance');
                await page.evaluate(() => {
                    const frame = document.querySelector('[data-calc-ghl] iframe');
                    window.dispatchEvent(new MessageEvent('message', { source: frame.contentWindow, origin: 'https://api.leadconnectorhq.com', data: ['set-sticky-contacts', '_ud', JSON.stringify({ first_name: 'Browser', last_name: 'Fixture', email: 'browser@example.test', phone: '2105550100' }), 'location-fixture', 'fingerprint-fixture'] }));
                    window.dispatchEvent(new MessageEvent('message', { source: frame.contentWindow, origin: 'https://api.leadconnectorhq.com', data: ['set-sticky-contacts', 'embedded_iframe_' + frame.id, frame.id, 'location-fixture', 'fingerprint-fixture'] }));
                });
                await page.locator('[data-calc-stage="4"]').waitFor({ state: 'visible' });
                assert.match(await page.locator('[data-calc-result]').textContent(), /12,825/);
                await page.getByText('Contact details saved to this report.', { exact: true }).waitFor();
                assert.equal(await page.evaluate(() => window.fixtureSavedContact.contact.first_name), 'Browser');
                assert.equal(await page.evaluate(() => window.fixtureSavedContact.contact_key), 'fixture-capability');
                assert.equal(await page.evaluate(() => window.fixtureSaveAttempts), 3, 'Transient backend failures are retried');
            }
            assert.deepEqual(errors, []);
            await page.close();
        }
        console.log('Calculator design browser checks passed for both contact modes, steps, validation, retained inputs and responsive widths.');
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
