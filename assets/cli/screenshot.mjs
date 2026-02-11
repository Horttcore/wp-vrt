import { chromium } from 'playwright';

const [,, url, output, width = '1200', height = '800'] = process.argv;

if (!url || !output) {
  console.error('Usage: node screenshot.mjs <url> <output> [width] [height]');
  process.exit(1);
}

const browser = await chromium.launch();
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: Number(width), height: Number(height) }
});
const page = await context.newPage();

await page.goto(url, { waitUntil: 'networkidle' });
await page.screenshot({ path: output, fullPage: true });
await browser.close();
