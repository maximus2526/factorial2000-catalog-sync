// Renders the WordPress.org icon + banner (HTML → PNG) and processes the screenshots.
import { chromium } from '../video/node_modules/playwright/index.mjs';
import { writeFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const out = path.join(root, 'wp-org');
await mkdir(out, { recursive: true });
const shot = pathToFileURL(path.join(root, 'shots-raw', 'update.png')).href;

const ICON = (s) => `<!doctype html><body style="margin:0;width:${s}px;height:${s}px;overflow:hidden">
<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 256 256">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4f6dff"/><stop offset="1" stop-color="#2a3fc4"/></linearGradient>
<linearGradient id="s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>
<rect width="256" height="256" rx="56" fill="url(#g)"/><rect width="256" height="128" rx="56" fill="url(#s)"/>
<g fill="none" stroke="#fff" stroke-width="16" stroke-linecap="round" stroke-linejoin="round">
<path d="M58 118a72 72 0 0 1 124-42"/><path d="M186 52v28h-28"/>
<path d="M198 138a72 72 0 0 1-124 42"/><path d="M70 204v-28h28"/></g>
<text x="128" y="144" text-anchor="middle" font-family="Segoe UI,Arial,sans-serif" font-weight="800" font-size="52" fill="#fff">XML</text>
</svg>`;

const BANNER = `<!doctype html><meta charset="utf-8"><style>
*{box-sizing:border-box;margin:0}body{width:1544px;height:500px;overflow:hidden;font-family:'Segoe UI',Arial,sans-serif;color:#fff;
background:radial-gradient(900px 500px at 85% 20%,#3e57e8 0,transparent 60%),radial-gradient(700px 400px at 0% 100%,#18c4a0 0,transparent 55%),linear-gradient(120deg,#0c1230,#16206b);position:relative}
.grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:44px 44px;mask-image:linear-gradient(90deg,#000,transparent 70%)}
.t{position:absolute;left:80px;top:96px;width:760px}
.kick{display:inline-block;font-weight:700;font-size:20px;letter-spacing:.14em;text-transform:uppercase;color:#7fe9cf;margin-bottom:18px}
h1{font-size:76px;line-height:1.02;font-weight:800;letter-spacing:-.02em}h1 span{color:#7fe9cf}
p{font-size:27px;margin-top:22px;color:#cfd6ff;line-height:1.35}
.chips{display:flex;gap:12px;margin-top:30px}.chips b{font-size:19px;font-weight:600;padding:9px 18px;border-radius:99px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25)}
.card{position:absolute;right:70px;top:62px;width:640px;height:420px;border-radius:18px;overflow:hidden;background:#f0f0f1;box-shadow:0 40px 80px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.2);transform:perspective(1600px) rotateY(-14deg) rotateX(4deg) rotate(1.5deg)}
.card .bar{height:30px;background:#e3e5ea;display:flex;gap:7px;align-items:center;padding:0 12px}.bar i{width:11px;height:11px;border-radius:50%;background:#ff5f57}.bar i:nth-child(2){background:#febc2e}.bar i:nth-child(3){background:#28c840}
.card img{width:1000px;margin-left:-6px;display:block}
</style><body><div class="grid"></div>
<div class="t"><div class="kick">WooCommerce · Prom.ua · YML</div><h1>Factorial2000<br><span>Catalog Sync</span></h1><p>Імпорт XML постачальників, синхронізація наявності й цін, фід для Prom.ua.</p>
<div class="chips"><b>Імпорт XML</b><b>Наявність і ціни</b><b>Фід Prom.ua</b></div></div>
<div class="card"><div class="bar"><i></i><i></i><i></i></div><img src="${shot}"></div></body>`;

const browser = await chromium.launch();
async function render(html, w, h, scale, file) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: scale });
  const p = await ctx.newPage();
  const f = path.join(out, '_tmp.html');
  await writeFile(f, html);
  await p.goto(pathToFileURL(f).href);
  await p.waitForLoadState('load');
  await p.waitForTimeout(300);
  await p.screenshot({ path: path.join(out, file), omitBackground: false });
  await ctx.close();
}
await render(ICON(256), 256, 256, 1, 'icon-256x256.png');
await render(ICON(256), 256, 256, 0.5, 'icon-128x128.png');
await render(BANNER, 1544, 500, 1, 'banner-1544x500.png');
await render(BANNER, 1544, 500, 0.5, 'banner-772x250.png');
await browser.close();
console.log('done');
