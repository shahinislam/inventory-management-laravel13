// capture.mjs
// Snapshots the RUNNING Inventory app so the static clone can be matched against it.
// Read-only: it only issues GET requests and clicks UI controls that open menus/modals
// (it never confirms a delete or submits a form other than logging in).
//
// Output (./out):
//   auth-light/      login, register, forgot/reset password          (html + png)
//   admin-light/     every page as admin                             (html + png + .snapshot.json)
//   admin-dark/      every page as admin, dark mode                  (png)
//   admin-mobile/    a few pages at 390px wide                       (png)
//   admin-states/    open menus, modals, filters, sidebar states     (png)
//   <role>-light/    dashboard as manager / staff / viewer           (html + png)
//   manifest.json    status codes, titles, console errors, role access matrix

import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const OUT = path.resolve(process.env.OUT_DIR || './out');
const PASSWORD = process.env.APP_PASSWORD || 'password';
const TZ_ID = process.env.TZ_ID || 'UTC';
const DESKTOP = { width: 1440, height: 900 };
const MOBILE = { width: 390, height: 844 };
const flag = (name) => process.env[name] === '1';

const ACCOUNTS = {
    admin: 'admin@inventory.com',
    manager: 'manager@inventory.com',
    staff: 'staff@inventory.com',
    viewer: 'viewer@inventory.com', // not seeded; skipped with a warning if it cannot log in
};

const AUTH_PAGES = [
    '/login',
    '/forgot-password',
    '/reset-password/demo-token?email=admin%40inventory.com',
];

// Pages with no record id in the URL.
const STATIC = [
    '/dashboard',
    '/products', '/products/create', '/products/labels',
    '/categories', '/categories/create',
    '/suppliers', '/suppliers/create',
    '/payment-accounts', '/payment-accounts/create',
    '/warehouses', '/warehouses/create',
    '/customers', '/customers/create',
    '/stock', '/stock/adjust', '/stock/transfer', '/stock/count',
    '/purchases', '/purchases/create',
    '/invoices', '/invoices/create',
    '/pos', '/returns/create',
    '/shifts', '/shifts/current',
    '/expenses', '/expenses/create',
    '/marketing/sms', '/marketing/sms-log',
    '/promotions', '/promotions/create',
    '/member-rewards', '/member-rewards/create',
    '/media',
    '/reports/stock', '/reports/sales', '/reports/low-stock', '/reports/dues',
    '/reports/accounts', '/reports/expiry', '/reports/profit-loss',
    '/partners', '/partners/report', '/partners/transactions', '/partners/create',
    '/settings/general', '/settings/users', '/settings/roles',
    '/settings/profile', '/settings/appearance', '/settings/security',
];

// Pages that need a record id. The id is harvested from the links on the list page.
const DISCOVER = [
    { list: '/products', tpl: '/products/{id}/edit' },
    { list: '/categories', tpl: '/categories/{id}/edit' },
    { list: '/suppliers', tpl: '/suppliers/{id}/edit' },
    { list: '/payment-accounts', tpl: '/payment-accounts/{id}/edit' },
    { list: '/warehouses', tpl: '/warehouses/{id}/edit' },
    { list: '/customers', tpl: '/customers/{id}/edit' },
    { list: '/purchases', tpl: '/purchases/{id}/edit', also: ['/purchases/{id}/return'] },
    { list: '/purchases', tpl: '/purchases/returns/{id}' },
    { list: '/invoices', tpl: '/invoices/{id}', also: ['/invoices/{id}/edit', '/invoices/{id}/receipt'] },
    { list: '/shifts', tpl: '/shifts/{id}', also: ['/shifts/{id}/report'] },
    { list: '/expenses', tpl: '/expenses/{id}/edit' },
    { list: '/promotions', tpl: '/promotions/{id}/edit' },
    { list: '/member-rewards', tpl: '/member-rewards/{id}/edit' },
    { list: '/partners', tpl: '/partners/{id}/edit' },
];

const MOBILE_PAGES = ['/dashboard', '/products', '/invoices', '/pos', '/stock', '/reports/sales'];

const warnings = [];
const warn = (message) => {
    warnings.push(message);
    console.warn('  ! ' + message);
};

const slug = (url) =>
    (url.replace(/^\//, '').replace(/[^a-zA-Z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'root').slice(0, 120);

/** Drop scripts and the huge Livewire state attributes so the saved HTML is readable. */
const clean = (html) =>
    html
        .replace(/<script\b[\s\S]*?<\/script>/gi, '<!-- script removed -->')
        .replace(/\swire:snapshot="[^"]*"/g, ' wire:snapshot="..."')
        .replace(/\swire:effects="[^"]*"/g, ' wire:effects="..."');

/** Wait until the page (and Livewire, if present) has finished rendering. */
async function settle(page) {
    await page.waitForLoadState('load').catch(() => {});
    await page
        .waitForFunction(() => !document.querySelector('[wire\\:id]') || window.Livewire, null, { timeout: 15000 })
        .catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
    await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
    await page.waitForTimeout(500);
}

/** A fresh browser context, optionally logged in as `role`. */
async function newSession(browser, { role = null, theme = 'light', viewport = DESKTOP } = {}) {
    const ctx = await browser.newContext({
        viewport,
        deviceScaleFactor: 1,
        colorScheme: theme,
        locale: 'en-US',
        timezoneId: TZ_ID,
    });

    await ctx.addInitScript((appearance) => {
        try {
            localStorage.setItem('flux.appearance', appearance);
        } catch {}
        window.print = () => {}; // the receipt page auto-opens the print dialog
    }, theme);

    const page = await ctx.newPage();
    page.__errors = [];
    page.on('pageerror', (e) => page.__errors.push('pageerror: ' + e.message));
    page.on('console', (m) => {
        if (m.type() === 'error') page.__errors.push('console: ' + m.text().slice(0, 300));
    });

    if (role) {
        await page.goto(BASE + '/login', { waitUntil: 'load' });
        await settle(page);
        await page.fill('input[name="email"]', ACCOUNTS[role]);
        await page.fill('input[name="password"]', PASSWORD);
        await Promise.all([
            page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 90000, waitUntil: 'commit' }),
            page.locator('[data-test="login-button"], form button[type="submit"]').first().click(),
        ]);
        await settle(page);
    }

    return { ctx, page };
}

/** Visit one URL and save its html / screenshot. Returns a record for the manifest. */
async function capture(page, url, dir, { html = true, png = true } = {}) {
    page.__errors = [];
    const started = Date.now();
    let status = null;

    try {
        const response = await page.goto(BASE + url, { waitUntil: 'load', timeout: 45000 });
        status = response ? response.status() : null;
        await settle(page);
    } catch (e) {
        warn(`${url}: ${e.message.split('\n')[0]}`);
    }

    const final = new URL(page.url());
    const record = {
        url,
        status,
        finalPath: final.pathname + final.search,
        title: await page.title().catch(() => ''),
        dark: await page.evaluate(() => document.documentElement.classList.contains('dark')).catch(() => null),
        ms: Date.now() - started,
        errors: [...page.__errors],
    };

    await fs.mkdir(dir, { recursive: true });
    const name = slug(url);

    if (html) {
        await fs.writeFile(path.join(dir, name + '.html'), clean(await page.content()));

        const snapshots = await page
            .evaluate(() =>
                [...document.querySelectorAll('[wire\\:snapshot]')]
                    .map((el) => {
                        try {
                            return JSON.parse(el.getAttribute('wire:snapshot'));
                        } catch {
                            return null;
                        }
                    })
                    .filter(Boolean),
            )
            .catch(() => []);
        if (snapshots.length) {
            await fs.writeFile(path.join(dir, name + '.snapshot.json'), JSON.stringify(snapshots, null, 1));
        }
    }

    if (png) {
        await page
            .screenshot({ path: path.join(dir, name + '.png'), fullPage: true, animations: 'disabled', caret: 'hide' })
            .catch(() => warn(`${url}: screenshot failed`));
    }

    const moved = record.finalPath !== url ? ` -> ${record.finalPath}` : '';
    console.log(`  ${record.status ?? '---'}  ${url}${moved}${record.errors.length ? `  (${record.errors.length} console errors)` : ''}`);

    return record;
}

/** Find a sample record id for each templated URL by reading links off its list page. */
async function discoverUrls(page) {
    const urls = [];
    const missing = [];

    for (const d of DISCOVER) {
        await page.goto(BASE + d.list, { waitUntil: 'load' }).catch(() => {});
        await settle(page);

        const hrefs = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => new URL(a.href).pathname));
        const pattern = new RegExp('^' + d.tpl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace('\\{id\\}', '(\\d+)') + '$');
        const hit = hrefs.map((h) => pattern.exec(h)).find(Boolean);

        if (!hit) {
            missing.push(d.tpl);
            continue;
        }

        for (const tpl of [d.tpl, ...(d.also || [])]) urls.push(tpl.replace('{id}', hit[1]));
    }

    const productId = urls.find((u) => /^\/products\/\d+\/edit$/.test(u))?.match(/\d+/)?.[0];
    if (productId) {
        const items = JSON.stringify([{ id: Number(productId), qty: 3, quantity: 3, copies: 3 }]);
        urls.push('/products/labels/print?items=' + encodeURIComponent(items) + '&size=38x25');
    }

    for (const tpl of missing) warn(`no sample record found for ${tpl} (create one in the app to capture it)`);

    return [...new Set(urls)];
}

/** Open menus, modals, filters and sidebar states. Nothing here saves or deletes anything. */
async function captureStates(page, dir) {
    await fs.mkdir(dir, { recursive: true });
    const results = [];

    const go = async (url) => {
        await page.goto(BASE + url, { waitUntil: 'load' }).catch(() => {});
        await settle(page);
    };

    const step = async (name, action) => {
        try {
            await action();
            await page.waitForTimeout(450);
            await page.screenshot({ path: path.join(dir, name + '.png'), animations: 'disabled', caret: 'hide' });
            results.push({ name, ok: true });
            console.log('  state  ' + name);
        } catch (e) {
            const error = e.message.split('\n')[0];
            results.push({ name, ok: false, error });
            warn(`state ${name}: ${error}`);
        }
    };

    const dismiss = () => page.keyboard.press('Escape').catch(() => {});

    // Sidebar: accordion groups, collapsed mode, user menu.
    await go('/dashboard');
    const groups = page.locator('[data-flux-sidebar] ui-disclosure > button');
    const groupCount = await groups.count().catch(() => 0);
    for (let i = 0; i < groupCount; i++) {
        await step(`sidebar-group-${i}`, () => groups.nth(i).click({ timeout: 5000 }));
    }
    await step('sidebar-collapsed', () => page.locator('[data-flux-sidebar-collapse]').first().click({ timeout: 5000 }));
    await page.locator('[data-flux-sidebar-collapse]').first().click({ timeout: 3000 }).catch(() => {});
    await go('/dashboard');
    await step('user-menu', () => page.locator('[data-flux-sidebar-profile], [data-flux-profile]').first().click({ timeout: 5000 }));
    await dismiss();

    // Products list: live search, filters, sorting, row menu, delete confirmation.
    await go('/products');
    await step('products-search', async () => {
        const search = page.locator('input[placeholder^="Search by name"]').first();
        await search.click();
        await search.fill('oil');
        await page.waitForTimeout(900);
    });
    await go('/products');
    await step('products-filters', () => page.locator('button', { hasText: 'Filters' }).first().click({ timeout: 5000 }));
    await go('/products');
    await step('products-sort-price', () => page.locator('[data-flux-column]', { hasText: 'Price' }).first().click({ timeout: 5000 }));
    await go('/products');
    await step('products-row-menu', () =>
        page.locator('[data-flux-rows] [data-flux-row]').first().locator('[data-flux-button]').first().click({ timeout: 5000 }),
    );
    await step('products-delete-modal', () => page.locator('[data-flux-menu-item]', { hasText: 'Delete' }).first().click({ timeout: 5000 }));
    await dismiss();

    // POS: the gate (no shift) or the terminal, then a product search.
    await go('/pos');
    await step('pos-initial', async () => {});
    await step('pos-search', async () => {
        // An editable div (see components/search-select), not an <input>.
        const search = page.locator('[data-placeholder*="earch" i]').first();
        await search.click({ timeout: 5000 });
        await search.pressSequentially('Oil', { delay: 60 });
        await page.waitForTimeout(900);
    });

    return results;
}

// ---------------------------------------------------------------------------

const reachable = await fetch(BASE + '/login').then((r) => r.status).catch(() => null);
if (!reachable || reachable >= 500) {
    console.error(`\nCannot reach ${BASE}/login (status: ${reachable}).`);
    console.error('Start the app first (php artisan serve, or Sail), keep "npm run dev" running if public/hot exists,');
    console.error('then set BASE_URL, e.g.  $env:BASE_URL="http://127.0.0.1:8000"\n');
    process.exit(1);
}

await fs.mkdir(OUT, { recursive: true });
const browser = await chromium.launch();

const manifest = {
    baseUrl: BASE,
    capturedAt: new Date().toISOString(),
    viewport: { desktop: DESKTOP, mobile: MOBILE },
    timezone: TZ_ID,
    pages: {},
    matrix: {},
    states: [],
    warnings,
};

try {
    // A. Public auth pages.
    console.log('\n[1/6] Auth pages (logged out)');
    {
        const { ctx, page } = await newSession(browser, { role: null });
        manifest.pages['auth-light'] = [];
        for (const url of AUTH_PAGES) manifest.pages['auth-light'].push(await capture(page, url, path.join(OUT, 'auth-light')));
        await ctx.close();
    }

    // B. Admin, every page, light mode, with html. Also discovers record ids.
    console.log('\n[2/6] Admin: all pages (light, html + png)');
    let allUrls = [...STATIC];
    {
        const { ctx, page } = await newSession(browser, { role: 'admin' });
        const dynamic = await discoverUrls(page);
        allUrls = [...STATIC, ...dynamic];
        manifest.urls = allUrls;

        manifest.pages['admin-light'] = [];
        for (const url of allUrls) manifest.pages['admin-light'].push(await capture(page, url, path.join(OUT, 'admin-light')));
        manifest.matrix.admin = Object.fromEntries(manifest.pages['admin-light'].map((r) => [r.url, { status: r.status, location: r.finalPath !== r.url ? r.finalPath : null }]));

        if (!flag('SKIP_STATES')) {
            console.log('\n[3/6] Admin: interaction states');
            manifest.states = await captureStates(page, path.join(OUT, 'admin-states'));
        }
        await ctx.close();
    }

    // C. Other roles: dashboard (sidebar differs per role) + access matrix.
    console.log('\n[4/6] Other roles: dashboard + access matrix');
    for (const role of ['manager', 'staff', 'viewer']) {
        try {
            const { ctx, page } = await newSession(browser, { role });
            manifest.pages[`${role}-light`] = [await capture(page, '/dashboard', path.join(OUT, `${role}-light`))];
            manifest.matrix[role] = {};
            for (const url of allUrls) {
                const res = await ctx.request.get(BASE + url, { maxRedirects: 0 }).catch(() => null);
                manifest.matrix[role][url] = res ? { status: res.status(), location: res.headers()['location'] || null } : { status: null, location: null };
            }
            console.log(`  ${role}: checked ${allUrls.length} urls`);
            await ctx.close();
        } catch (e) {
            warn(`role ${role} skipped: ${e.message.split('\n')[0]}`);
        }
    }

    // D. Admin dark mode.
    if (!flag('SKIP_DARK')) {
        console.log('\n[5/6] Admin: dark mode (png)');
        const { ctx, page } = await newSession(browser, { role: 'admin', theme: 'dark' });
        manifest.pages['admin-dark'] = [];
        for (const url of allUrls) manifest.pages['admin-dark'].push(await capture(page, url, path.join(OUT, 'admin-dark'), { html: false }));
        await ctx.close();
    }

    // E. Mobile.
    if (!flag('SKIP_MOBILE')) {
        console.log('\n[6/6] Admin: mobile (png)');
        const { ctx, page } = await newSession(browser, { role: 'admin', viewport: MOBILE });
        manifest.pages['admin-mobile'] = [];
        for (const url of MOBILE_PAGES) manifest.pages['admin-mobile'].push(await capture(page, url, path.join(OUT, 'admin-mobile'), { html: false }));
        await ctx.close();
    }
} finally {
    await browser.close();
    await fs.writeFile(path.join(OUT, 'manifest.json'), JSON.stringify(manifest, null, 2));
}

const total = Object.values(manifest.pages).reduce((n, list) => n + list.length, 0);
console.log(`\nDone. ${total} page captures, ${warnings.length} warnings.`);
console.log(`Output: ${OUT}`);
