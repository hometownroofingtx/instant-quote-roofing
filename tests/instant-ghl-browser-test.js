const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { pathToFileURL } = require('node:url');
(async () => {
 const browser = await chromium.launch({ headless: true, channel: process.env.HTR_BROWSER_CHANNEL || undefined });
 try {
  const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.route('https://**', route => route.fulfill({ contentType: 'text/html', body: '<p>Fixture GHL form</p>' }));
  await page.addInitScript(() => {
   window.HTR_RR = { browserKey: 'fixture', contactMode: 'ghl_form', ghlFormId: 'fixture', ghlFormBase: 'https://example.test/form/', ajaxUrl: '/ajax' };
   window.calls = { draft_prepare: 0, reprice: 0, save_contact: 0 };
   class Address extends HTMLElement {}
   customElements.define('gmp-place-autocomplete', Address);
   window.google = { maps: { async importLibrary(name) {
    if (name === 'places') return { PlaceAutocompleteElement: Address };
    if (name === 'maps') return { Map: class { addListener() {} setCenter() {} setZoom() {} } };
    if (name === 'marker') return { Marker: class { addListener() {} setPosition() {} } };
   } } };
   window.fetch = async (url, opts) => {
    const action = new URL(url, location.href).searchParams.get('action').replace('htr_rr_', '');
    if (Object.hasOwn(window.calls, action)) window.calls[action]++;
    const input = JSON.parse(opts.body);
    if (action === 'status') return { ok: true, json: async () => ({ ok: true, linked: !!window.serverLinked, ...(window.serverLinked ? { report: { token: 'fixture', url: 'https://example.test/report', measured: false, roofType: 'metal', firstName: 'Saved', address: '123 Main St', disclaimer: 'Fixture disclaimer' } } : {}) }) };
    if (action === 'save_contact') { window.savedContact = input; if (window.calls.save_contact < 3) return {ok:false,json:async()=>({ok:false})}; window.serverLinked = true; return { ok: true, json: async () => ({ ok: true, saved: true }) }; }
    if (action === 'preview') { await new Promise(resolve => setTimeout(resolve, 800)); return { ok: true, json: async () => ({ ok: true, measured: false, message: 'Fixture' }) }; }
    await new Promise(resolve => setTimeout(resolve, 50));
    return { ok: true, json: async () => ({ ok: true, report_id: 8, contact_key: 'owned-fixture', contact_expires: 9999999999, roof_type: input.material, report: { token: 'fixture', url: 'https://example.test/report', measured: false, roofType: input.material, firstName: '', address: '123 Main St', disclaimer: 'Fixture disclaimer' } }) };
   };
  });
  await page.route('https://example.test/quote', route => route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(__dirname, '.instant-preview.html'), 'utf8') }));
  await page.route('https://example.test/report', route => route.fulfill({ contentType: 'text/html', body: '<p>Verified roof report fixture</p>' }));
  await page.goto('https://example.test/quote');
  await page.evaluate(() => {
   const event = new Event('gmp-select'); event.placePrediction = { toPlace: () => ({ formattedAddress: '123 Main St, TX', location: { lat: () => 29.4, lng: () => -98.4 }, addressComponents: [{ types: ['street_number'], longText: '123' }], fetchFields: async () => {} }) };
   document.querySelector('gmp-place-autocomplete').dispatchEvent(event);
  });
  await page.locator('[data-quote-find]').click();
  assert.equal(await page.locator('[data-quote-step]').count(), 3, 'Exactly three progress steps');
  await page.locator('#htr-rr-confirm').click();
  assert.equal(await page.locator('[data-mat=other]').count(), 1, 'Configured Other roof type preserved');
  assert.equal(await page.locator('[data-card="3"]').isVisible(), true);
  for (const width of [375, 768, 1366]) {
   await page.setViewportSize({ width, height: 900 });
   assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Wizard fits viewport ' + width);
  }
  await page.evaluate(() => { document.querySelector('[data-mat=asphalt]').click(); document.querySelector('[data-mat=metal]').click(); document.querySelector('[data-mat=asphalt]').click(); });
  await page.locator('[data-quote-contact]').click();
  await page.locator('#htr-rr-ghl iframe').waitFor();
  assert.equal(await page.evaluate(() => window.calls.draft_prepare), 1);
  await page.evaluate(() => { window.oldFormWindow = document.querySelector('#htr-rr-ghl iframe').contentWindow; });
  await page.locator('[data-quote-back="3"]').click();
  await page.locator('[data-mat=metal]').click();
  await page.locator('[data-quote-contact]').click();
  await page.waitForFunction(() => window.calls.reprice === 1 && document.querySelector('#htr-rr-ghl iframe'));
  assert.equal(await page.evaluate(() => window.calls.draft_prepare), 1, 'Changing roof reuses one report');
  await page.evaluate(() => window.dispatchEvent(new MessageEvent('message', { source: window.oldFormWindow, origin: 'https://example.test', data: ['msgsndr-form-submitted'] })));
  assert.equal(await page.locator('#htr-rr-result').textContent(), '', 'Removed iframe cannot complete another selection');
  await page.evaluate(() => {
   const frame = document.querySelector('#htr-rr-ghl iframe');
   const send = data => window.dispatchEvent(new MessageEvent('message', { source: frame.contentWindow, origin: 'https://example.test', data }));
   send(['set-sticky-contacts', '_ud', JSON.stringify({ first_name: 'Saved', last_name: 'Fixture', email: 'fixture@example.test', phone_number: '+639175550123' }), 'location', 'fingerprint']);
   send(['set-sticky-contacts', 'embedded_iframe_' + frame.id, frame.id, 'location', 'fingerprint']);
  });
  assert.equal(await page.locator('.htr-rr-submission-loader').isVisible(), true, 'Submission feedback appears while saving/retrying');
  assert.equal(await page.locator('.htr-rr-submission-loader').getAttribute('role'), 'status');
  await page.waitForTimeout(150);
  assert.equal(await page.locator('#htr-rr-result').textContent(), '', 'Failed contact save cannot reveal report');
  assert.equal(await page.locator('[data-quote-step="2"]').getAttribute('aria-current'), 'step');
  // Contact persistence must succeed before navigating; exercise two transient failures.
  await page.waitForFunction(() => document.querySelector('#htr-rr-result').textContent.length > 0);
  assert.equal(page.url(), 'https://example.test/quote', 'Submission stays on the quote page');
  assert.equal(await page.locator('[data-quote-step="3"]').getAttribute('aria-current'), 'step');
  assert.equal(await page.locator('[data-card="6"]').isVisible(), true, 'Saved report appears in Step 3');
  assert.equal(await page.locator('.htr-rr-submission-loader').isHidden(), true, 'Submission loader stops when Step 3 is ready');
  assert.equal(await page.evaluate(() => window.calls.save_contact), 3, 'Persistence retries finish before displaying report');
  assert.deepEqual(errors, []);
  console.log('Three-step checks passed: inline property controls, responsive layout, draft reuse, stale iframe rejection and save-before-Step-3 with persistence retries and no page redirect.');
 } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
