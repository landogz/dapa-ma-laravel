import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const WIDTH = 1440;
const HEIGHT = 812;
const BASE = process.env.ADMIN_URL || 'http://127.0.0.1:8000';
const EMAIL = process.env.ADMIN_EMAIL || 'superadmin@yopmail.com';
const PASSWORD = process.env.ADMIN_PASSWORD || 'password';
const HERE = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.resolve(HERE, '../figures');

const PAGES = [
    ['/admin/dashboard', '02-dashboard.png'],
    ['/admin/posts', '03-posts.png'],
    ['/admin/contests', '05-contests.png'],
    ['/admin/iec-materials', '06-iec-materials.png'],
    ['/admin/rehab-centers', '07-rehab-centers.png'],
    ['/admin/care-toolkit', '08-care-toolkit.png'],
    ['/admin/care-checks', '09-care-checks.png'],
    ['/admin/care-support', '10-care-support.png'],
    ['/admin/diary', '11-journal.png'],
    ['/admin/hope-directory', '12-hope-directory.png'],
    ['/admin/hope-events', '13-hope-events.png'],
    ['/admin/notifications', '14-notifications.png'],
    ['/admin/analytics', '15-analytics.png'],
    ['/admin/users', '16-users.png'],
    ['/admin/settings', '18-settings.png'],
    ['/admin/lookups', '19-lookups.png'],
    ['/admin/app-translations', '20-app-translations.png'],
    ['/admin/word-blocking', '21-word-blocking.png'],
    ['/admin/profile', '22-profile.png'],
];

async function settle(page) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(400);
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
    await page.waitForFunction(() => !document.body?.innerText?.includes('Loading role...'), {
        timeout: 12000,
    }).catch(() => {});
    await page.waitForTimeout(700);
}

async function shot(page, name) {
    const dest = path.join(OUT, name);
    await page.screenshot({
        path: dest,
        type: 'png',
        clip: { x: 0, y: 0, width: WIDTH, height: HEIGHT },
    });
    console.log(`saved ${name}`);
}

async function closeDialog(page) {
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForSelector('.swal2-container', { state: 'detached', timeout: 4000 }).catch(() => {});
    await page.waitForTimeout(250);
}

async function openDialog(page, readyExpr, callExpr) {
    await page.waitForFunction(readyExpr, { timeout: 10000 });
    await page.evaluate(`void (${callExpr})()`);
    await page.waitForSelector('.swal2-container', { timeout: 15000, state: 'attached' });
    await page.waitForTimeout(900);
}

const browser = await chromium.launch({
    channel: 'chrome',
    headless: true,
});

const context = await browser.newContext({
    viewport: { width: WIDTH, height: HEIGHT },
    deviceScaleFactor: 1,
    screen: { width: WIDTH, height: HEIGHT },
});

const page = await context.newPage();
page.setDefaultTimeout(20000);
page.on('pageerror', (error) => console.log('pageerror', error.message));

await page.goto(`${BASE}/admin/login`, { waitUntil: 'domcontentloaded' });
await settle(page);
await page.evaluate(() => {
    document.querySelectorAll('details').forEach((el) => el.remove());
});
await page.waitForTimeout(200);
await shot(page, '01-login.png');

await page.fill('#email', EMAIL);
await page.fill('#password', PASSWORD);
await page.click('#admin-login-form [type="submit"]');
await page.waitForURL(/\/admin(\/dashboard)?/, { timeout: 20000 });
await settle(page);

for (const [route, file] of PAGES) {
    await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
    await settle(page);
    await shot(page, file);

    if (file === '03-posts.png') {
        try {
            await page.waitForFunction(() => typeof window.Posts?.createDraftPrompt === 'function', {
                timeout: 15000,
            });
            await page.evaluate(() => {
                void window.Posts.createDraftPrompt();
            });
            await page.waitForSelector('.swal2-container', { timeout: 15000, state: 'attached' });
            await page.waitForTimeout(900);
            await shot(page, '04-posts-create.png');
            await closeDialog(page);
        } catch (error) {
            console.log('skip 04-posts-create.png', error.message);
        }
    }

    if (file === '16-users.png') {
        try {
            await openDialog(
                page,
                '() => typeof window.Users?.create === "function"',
                '() => window.Users.create()',
            );
            await shot(page, '17-users-add.png');
            await closeDialog(page);
        } catch (error) {
            console.log('skip 17-users-add.png', error.message);
        }
    }
}

await browser.close();
console.log('done');
