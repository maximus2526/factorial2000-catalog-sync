// YouTube channel avatar (800x800, cropped to a circle) and channel banner (2560x1440, safe area 1546x423).
// Brand = a developer: code braces around the "F".
import { chromium } from '../video/node_modules/playwright/index.mjs';
import { writeFile, mkdir, rm } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outDir = path.join(root, 'youtube');
await mkdir(outDir, { recursive: true });

const ICON = `<!doctype html><meta charset="utf-8"><body style="margin:0;width:800px;height:800px;overflow:hidden">
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800" font-family="'Segoe UI',Arial,sans-serif">
<defs>
<linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#18246e"/><stop offset="1" stop-color="#070b1f"/></linearGradient>
<radialGradient id="glow" cx=".78" cy=".15" r=".75"><stop offset="0" stop-color="#4f6dff" stop-opacity=".75"/><stop offset="1" stop-color="#4f6dff" stop-opacity="0"/></radialGradient>
<radialGradient id="glow2" cx=".1" cy=".95" r=".6"><stop offset="0" stop-color="#14c9a3" stop-opacity=".45"/><stop offset="1" stop-color="#14c9a3" stop-opacity="0"/></radialGradient>
</defs>
<rect width="800" height="800" fill="url(#g)"/><rect width="800" height="800" fill="url(#glow)"/><rect width="800" height="800" fill="url(#glow2)"/>
<text x="400" y="505" text-anchor="middle" font-weight="900" font-size="400" fill="#fff" letter-spacing="-10">F</text>
<text x="195" y="500" text-anchor="middle" font-family="Consolas,monospace" font-weight="700" font-size="330" fill="#7fe9cf">{</text>
<text x="605" y="500" text-anchor="middle" font-family="Consolas,monospace" font-weight="700" font-size="330" fill="#7fe9cf">}</text>
<text x="400" y="610" text-anchor="middle" font-weight="800" font-size="70" fill="#9aa6d6" letter-spacing="16">2000</text>
</svg></body>`;

const BANNER = `<!doctype html><meta charset="utf-8"><style>
*{box-sizing:border-box;margin:0}
body{width:2560px;height:1440px;overflow:hidden;font-family:'Segoe UI',Arial,sans-serif;color:#fff;position:relative;
background:radial-gradient(1500px 900px at 85% 10%,rgba(79,109,255,.55),transparent 60%),radial-gradient(1200px 800px at 5% 105%,rgba(20,201,163,.4),transparent 60%),linear-gradient(120deg,#0a1030,#101a5c)}
.grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:80px 80px}
.code{position:absolute;font:600 40px Consolas,monospace;color:rgba(127,233,207,.28);white-space:pre;line-height:1.5}
.safe{position:absolute;left:507px;top:508px;width:1546px;height:423px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center}
h1{font-size:150px;font-weight:900;letter-spacing:-.03em;line-height:1}h1 em{font-style:normal;color:#7fe9cf}
p{font-size:52px;font-weight:600;color:#cfd6ff;margin-top:26px}
.tags{display:flex;gap:18px;margin-top:30px}.tags b{font-size:34px;font-weight:700;padding:8px 24px;border-radius:99px;background:rgba(255,255,255,.12);border:2px solid rgba(255,255,255,.3)}
</style><body><div class="grid"></div>
<div class="code" style="left:70px;top:80px">add_action( 'init', 'f2000cs_boot' );
function f2000cs_sync() {
  $feed = fetch( $supplier_url );
  update_stock( $feed );
}</div>
<div class="code" style="right:70px;bottom:80px;text-align:right">wp f2000cs marketplace build prom
✓ feed rebuilt
✓ stock synced</div>
<div class="safe"><h1>Factorial<em>2000</em></h1><p>Плагіни для WooCommerce та WordPress</p>
<div class="tags"><b>Prom.ua</b><b>Автоматизація</b><b>Розробка</b></div></div></body>`;

const browser = await chromium.launch();
async function render(html, w, h, file) {
  const f = path.join(outDir, '_tmp.html');
  await writeFile(f, html);
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  await page.goto(pathToFileURL(f).href);
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(outDir, file) });
  await ctx.close();
  await rm(f);
}
await render(ICON, 800, 800, 'channel-icon-800x800.png');
await render(BANNER, 2560, 1440, 'channel-banner-2560x1440.png');
await browser.close();
console.log('done');
