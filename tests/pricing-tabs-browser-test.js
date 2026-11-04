const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
(async function () {
    const browser = await chromium.launch({ headless: true, channel: process.env.HTR_BROWSER_CHANNEL || undefined });
    try {
        const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await page.goto(pathToFileURL(path.join(__dirname, '.pricing-preview.html')).href);
        assert.equal(await page.locator('[data-settings-panel]:visible').count(), 1);
        const rate = page.locator('[name="mat[asphalt][low]"]');
        await rate.fill('5.25');
        await page.locator('[data-settings-tab=areas]').click();
        await page.locator('[data-htr-add=area]').click();
        assert.equal(await page.locator('#htr-area-rows tr').count(), 6);
        await page.locator('[data-settings-tab=materials]').click();
        assert.equal(await rate.inputValue(), '5.25');
        await page.locator('[data-settings-tab=transfer]').click();
        assert.equal(await page.locator('.htr-pricing-save').isVisible(), false);
        assert.equal(await page.locator('.htr-pricing-transfer form').count(), 2);
        await rate.dispatchEvent('invalid');
        assert.equal(await page.locator('[data-settings-tab=materials]').getAttribute('aria-selected'), 'true');
        assert.equal(await page.locator('.htr-pricing-save').isVisible(), true);
        await page.locator('[data-settings-tab=materials]').focus(); await page.keyboard.press('End');
        assert.equal(await page.locator('[data-settings-tab=transfer]').getAttribute('aria-selected'), 'true');
        await page.setViewportSize({ width: 375, height: 850 });
        await page.locator('[data-settings-tab=materials]').click();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        assert.deepEqual(errors, []);
        console.log('Pricing browser checks passed: tabs, retained rates, added rows, separate transfers, keyboard, validation and mobile.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
