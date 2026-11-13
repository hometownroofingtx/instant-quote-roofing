const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

(async () => {
  const fixture = path.join(__dirname, '.instant-plugin-preview.html');
  const output = path.join(__dirname, '.admin-quote-preview.html');
  const original = fs.readFileSync(fixture, 'utf8');
  const scriptStart = original.lastIndexOf('<script>');
  assert.ok(scriptStart > 0, 'Existing frontend preview fixture is required');
  const prepare = `document.querySelector('[data-card="4"]').remove();
    document.querySelector('[data-quote-step="3"]').remove();`;
  fs.writeFileSync(output, original.slice(0, scriptStart) + '<script>' + prepare + '\n' + fs.readFileSync(path.join(__dirname, '../assets/js/roof-report.js'), 'utf8') + '</script></body></html>');
  const browser = await chromium.launch({ headless: true, channel: process.env.HTR_BROWSER_CHANNEL || undefined });
  try {
    for (const width of [375, 1366]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(() => {
        window.HTR_RR = { browserKey: 'fixture', backend: true, adminNonce: 'fixture-nonce', contactMode: 'internal', ajaxUrl: '/ajax' };
        window.quoteCalls = [];
        class Address extends HTMLElement {}
        customElements.define('gmp-place-autocomplete', Address);
        window.google = { maps: { async importLibrary(name) {
          if (name === 'places') return { PlaceAutocompleteElement: Address };
          if (name === 'maps') return { Map: class { addListener() {} setCenter() {} setZoom() {} } };
          if (name === 'marker') return { Marker: class { addListener() {} setPosition() {} } };
        } } };
        window.fetch = async (url, options) => {
          const query = new URL(url, location.href).searchParams;
          const action = query.get('action').replace('htr_rr_', '');
          window.quoteCalls.push(action);
          if (action === 'preview') return { ok: true, json: async () => ({ ok: true, measured: false }) };
          if (action !== 'admin_quote' || query.get('nonce') !== 'fixture-nonce') throw new Error('Unexpected connector request');
          window.quotePayload = JSON.parse(options.body);
          return { ok: true, json: async () => ({ ok: true, temporary: true, report: { token: 'fixture', url: 'https://example.test/?htr_roof_preview=fixture', measured: false, disclaimer: 'Fixture' } }) };
        };
      });
      await page.goto(pathToFileURL(output).href);
      assert.equal(await page.locator('[data-card="4"]').count(), 0);
      await page.evaluate(() => {
        const event = new Event('gmp-select');
        event.placePrediction = { toPlace: () => ({ formattedAddress: '123 Main St, TX', location: { lat: () => 29.4, lng: () => -98.4 }, addressComponents: [{ types: ['street_number'], longText: '123' }], fetchFields: async () => {} }) };
        document.querySelector('gmp-place-autocomplete').dispatchEvent(event);
      });
      await page.locator('#htr-rr-confirm').click();
      await page.locator('[data-mat=asphalt]').click();
      await page.locator('[data-quote-contact]').click();
      await page.waitForFunction(() => document.querySelector('#htr-rr-result').textContent.includes('no lead was sent'));
      assert.equal(await page.locator('#htr-rr-mail').count(), 0);
      assert.equal(await page.locator('[data-journey-status]').textContent(), 'Step 2 of 2');
      assert.equal(await page.locator('a[href="https://example.test/admin-report"]').count(), 0);
      assert.ok((await page.locator('#htr-rr-result').textContent()).includes('Nothing was added to Reports'));
      assert.equal(await page.locator('a[href*="htr_roof_preview"]').count(), 2);
      assert.equal(await page.evaluate(() => window.quotePayload.first_name), undefined);
      assert.deepEqual(await page.evaluate(() => window.quoteCalls.filter(action => action !== 'preview')), ['admin_quote']);
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('Admin quote browser checks passed at mobile and desktop widths: shared map journey, no contact step, nonce, no connectors, temporary downloads and no saved report link.');
  } finally { await browser.close(); fs.unlinkSync(output); }
})().catch(error => { console.error(error); process.exitCode = 1; });
