// Desktop and mobile captures of the storefront, for comparing changes.
// BASE_URL=http://localhost:8080 node scripts/screenshots.mjs after [home category product]
// Needs Playwright: npm install --no-save playwright && npx playwright install chromium
// The store needs the generated catalog (seed 1), which makes the product below.
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const baseURL = process.env.BASE_URL || 'http://localhost:8080';
const label = process.argv[2] || 'after';
const selected = process.argv.slice(3);
const cases = [
  { name: 'home', path: '/' },
  { name: 'category', path: '/shoes.html' },
  { name: 'product', path: '/kestrel-and-finch-studio-waterproofing-spray-dc-000002.html' },
];
const viewports = [
  { name: 'desktop', width: 1440, height: 900, isMobile: false },
  { name: 'mobile', width: 390, height: 844, isMobile: true },
];

if (!/^[a-zA-Z0-9_-]+$/.test(label)) throw new Error('Use a simple capture label, e.g. before or after.');
for (const name of selected) {
  if (!cases.some(item => item.name === name)) throw new Error(`Unknown page: ${name}`);
}
const targets = cases.filter(item => selected.length === 0 || selected.includes(item.name));
const out = path.resolve('.screenshots', label);
await mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true });
try {
  for (const item of targets) {
    for (const { name, width, height, isMobile } of viewports) {
      const context = await browser.newContext({
        viewport: { width, height }, isMobile, hasTouch: isMobile,
        deviceScaleFactor: 1, colorScheme: 'light', reducedMotion: 'reduce',
        locale: 'en-US', timezoneId: 'UTC', serviceWorkers: 'block',
      });
      try {
        context.setDefaultTimeout(30000);
        const page = await context.newPage();
        const response = await page.goto(new URL(item.path, baseURL).href, { waitUntil: 'load' });
        if (!response?.ok()) throw new Error(`${item.name}: HTTP ${response?.status() ?? 'no response'}`);
        await page.locator('#maincontent').waitFor({ state: 'visible' });
        // Scroll through so lazy images load, then wait for them and the fonts.
        await page.evaluate(async () => {
          for (let y = 0; y < document.body.scrollHeight; y += 600) {
            window.scrollTo(0, y);
            await new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 50)));
          }
          window.scrollTo(0, 0);
        });
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => document.fonts.ready);
        const file = path.join(out, `${item.name}-${name}.png`);
        await page.screenshot({ path: file, fullPage: true, animations: 'disabled', caret: 'hide' });
        console.log(file);
      } finally {
        await context.close();
      }
    }
  }
} finally {
  await browser.close();
}
