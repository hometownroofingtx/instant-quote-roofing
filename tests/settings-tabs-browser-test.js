const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
(async function () {
    const browser = await chromium.launch({ headless: true, channel: process.env.HTR_BROWSER_CHANNEL || undefined });
    try {
        const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await page.goto(pathToFileURL(path.join(__dirname, '.settings-preview.html')).href);
        assert.equal(await page.locator('[data-settings-panel]:visible').count(), 1);
        await page.locator('[data-settings-tab=leads]').click();
        await page.locator('[name=ghl_form_id]').fill('retained-test-id');
        await page.locator('[data-settings-tab=customer]').click();
        await page.locator('[data-settings-tab=leads]').click();
        assert.equal(await page.locator('[name=ghl_form_id]').inputValue(), 'retained-test-id');
        assert.equal(await page.locator('[name=ghl_form_id]').isDisabled(), false);
        await page.locator('[data-settings-tab=test]').click();
        assert.equal(await page.locator('.htr-settings-test').isVisible(), true);
        assert.equal(await page.locator('.htr-settings-save').isVisible(), false);
        assert.equal(await page.locator('.htr-settings-test form').count(), 1);
        await page.locator('[data-settings-tab=leads]').focus(); await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('[data-settings-tab=customer]').getAttribute('aria-selected'), 'true');
        assert.equal(await page.locator('.htr-settings-save').isVisible(), true);
        await page.locator('[data-settings-tab=floating]').click();
        assert.equal(await page.locator('.htr-settings-save').isVisible(), false);
        await page.locator('[name=notify_email]').dispatchEvent('invalid');
        assert.equal(await page.locator('[data-settings-tab=leads]').getAttribute('aria-selected'), 'true');
        await page.setViewportSize({ width: 375, height: 850 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        assert.deepEqual(errors, []);
        console.log('Settings browser checks passed: tabs, retained fields, keyboard, validation, separate forms and mobile.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
