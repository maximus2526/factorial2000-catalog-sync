// Headless Chromium page that serves the project over a virtual origin
// (ES modules need http, not file://) and exposes the scene's __seek(t).
import { chromium } from 'playwright';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const ORIGIN = 'http://promo.local';
const TYPES = { '.json': 'application/json', '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png' };

export async function launch() {
  return chromium.launch({
    headless: true,
    args: ['--force-color-profile=srgb', '--font-render-hinting=none', '--disable-lcd-text', '--hide-scrollbars'],
  });
}

export async function openStage(browser, { width, height, scale = 1, project, entry = '/src/scenes/index.html' }) {
  const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: scale });
  await context.route(`${ORIGIN}/**`, async (route) => {
    const rel = decodeURIComponent(new URL(route.request().url()).pathname);
    const file = path.join(ROOT, rel);
    if (!file.startsWith(ROOT)) return route.fulfill({ status: 403 });
    try {
      const body = await readFile(file);
      route.fulfill({ status: 200, body, contentType: TYPES[path.extname(file)] || 'application/octet-stream' });
    } catch {
      route.fulfill({ status: 404, body: 'not found' });
    }
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  await page.goto(`${ORIGIN}${entry}?project=${encodeURIComponent(project)}`);
  await page.evaluate(() => window.__ready);
  if (errors.length) throw new Error(`Scene errors:\n${errors.join('\n')}`);
  const cdp = await context.newCDPSession(page);
  return {
    page,
    errors,
    async frame(t, format = 'png') {
      await page.evaluate((tt) => window.__seek(tt), t);
      const { data } = await cdp.send('Page.captureScreenshot', {
        format,
        quality: format === 'jpeg' ? 95 : undefined,
        optimizeForSpeed: true,
        captureBeyondViewport: false,
      });
      return Buffer.from(data, 'base64');
    },
  };
}
