// staged.mjs
// Listing screenshots that need a little interaction: a filled POS cart, the payment
// dialog, the product search, a date filter. Nothing is saved: no sale is completed.
//
// Run after `npm run capture`, with the same BASE_URL / TZ_ID / APP_PASSWORD, plus:
//   INVOICE_ID   an invoice with tax and several lines (default 1)
//   CUSTOMER_ID  the member to put on the POS sale (default 1)
//   POS_ITEMS    product ids to add, repeated for quantity (default "1,3,3,3,7,7,8,5,5")
//
// Output: ./out/staged/*.png (1440x900, mobile 390x844)

import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const OUT = path.resolve(process.env.OUT_DIR || './out', 'staged');
const PASSWORD = process.env.APP_PASSWORD || 'password';
const TZ_ID = process.env.TZ_ID || 'UTC';
const INVOICE_ID = process.env.INVOICE_ID || '1';
const CUSTOMER_ID = Number(process.env.CUSTOMER_ID || 1);
const POS_ITEMS = (process.env.POS_ITEMS || '1,3,3,3,7,7,8,5,5').split(',').map(Number);
const DESKTOP = { width: 1440, height: 900 };
const MOBILE = { width: 390, height: 844 };

async function settle(page) {
    await page.waitForLoadState('load').catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
    await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
    await page.waitForTimeout(600);
}

async function session(browser, { theme = 'light', viewport = DESKTOP } = {}) {
    const ctx = await browser.newContext({ viewport, deviceScaleFactor: 1, colorScheme: theme, locale: 'en-US', timezoneId: TZ_ID });
    await ctx.addInitScript((appearance) => {
        try {
            localStorage.setItem('flux.appearance', appearance);
        } catch {}
        window.print = () => {};
    }, theme);

    const page = await ctx.newPage();
    await page.goto(BASE + '/login');
    await settle(page);
    await page.fill('input[name="email"]', 'admin@inventory.com');
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
        // php -S is single-threaded, so the first dashboard render can be slow.
        page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 90000, waitUntil: 'commit' }),
        page.locator('[data-test="login-button"], form button[type="submit"]').first().click(),
    ]);
    await settle(page);

    return { ctx, page };
}

async function go(page, url) {
    await page.goto(BASE + url, { waitUntil: 'load', timeout: 45000 });
    await settle(page);
}

async function shot(page, name, { fullPage = false } = {}) {
    await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage });
    console.log('  saved ' + name + '.png');
}

/** Call a method on the POS component, the same way its buttons do. */
async function pos(page, method, ...args) {
    await page.evaluate(
        ([m, a]) => {
            const component = window.Livewire.all().find((c) => c.name.includes('pos'));
            if (!component) throw new Error('POS component not found');
            return component.$wire[m](...a);
        },
        [method, args],
    );
    await page.waitForTimeout(250);
}

async function fillCart(page) {
    await go(page, '/pos');
    await pos(page, 'selectCustomer', CUSTOMER_ID);
    for (const id of POS_ITEMS) await pos(page, 'quickAdd', id);
    await settle(page);
}

/** Click something that triggers a Livewire round trip and wait for the answer. */
async function clickText(page, text) {
    const answered = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST', { timeout: 60000 });
    await page.getByText(text, { exact: true }).first().click({ timeout: 5000 });
    await answered;
    await settle(page);
}

// ---------------------------------------------------------------------------

await fs.mkdir(OUT, { recursive: true });
const browser = await chromium.launch();

for (const theme of ['light', 'dark']) {
    console.log(`\n[${theme}]`);
    const { ctx, page } = await session(browser, { theme });

    await go(page, '/dashboard');
    await clickText(page, 'This Month').catch((e) => console.warn('  ! This Month: ' + e.message));
    await shot(page, `dashboard-${theme}`);

    await fillCart(page);
    await shot(page, `pos-cart-${theme}`);

    if (theme === 'light') {
        await pos(page, 'openPaymentModal');
        await settle(page);
        await shot(page, 'pos-payment-light');

        await go(page, '/pos');
        // An editable div (see components/search-select), not an <input>.
        const search = page.locator('[data-placeholder^="Scan barcode"]').first();
        await search.click();
        await search.pressSequentially('il', { delay: 80 });
        await page.getByText('Sunflower Oil (5L)').first().waitFor({ timeout: 60000 });
        await settle(page);
        await shot(page, 'pos-search-light');

        await go(page, `/invoices/${INVOICE_ID}`);
        await shot(page, 'invoice-light', { fullPage: true });

        await go(page, `/invoices/${INVOICE_ID}/receipt`);
        await shot(page, 'receipt-light', { fullPage: true });

        await go(page, '/reports/profit-loss');
        await shot(page, 'profit-loss-light');

        await go(page, '/settings/general');
        await page.getByText('Brand Colours', { exact: true }).first().evaluate((el) => {
            el.scrollIntoView({ block: 'start' });
            window.scrollBy(0, -40);
        });
        await page.waitForTimeout(400);
        await shot(page, 'settings-brand-light');
    }

    if (theme === 'dark') {
        await go(page, '/reports/sales');
        await shot(page, 'sales-report-dark');
    }

    await ctx.close();
}

console.log('\n[mobile]');
{
    const { ctx, page } = await session(browser, { viewport: MOBILE });
    await go(page, '/dashboard');
    await shot(page, 'dashboard-mobile');
    await ctx.close();
}

await browser.close();
console.log(`\nDone. Output: ${OUT}`);
