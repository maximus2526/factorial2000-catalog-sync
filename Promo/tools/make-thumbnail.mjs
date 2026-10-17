// YouTube thumbnail 1280x720 (rendered at 2x → 2560x1440 downscaled by YouTube).
import { chromium } from '../video/node_modules/playwright/index.mjs';
import { writeFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outDir = path.join(root, 'youtube');
await mkdir(outDir, { recursive: true });
const shot = pathToFileURL(path.join(root, 'video', 'assets', 'update.png')).href;
const icon = pathToFileURL(path.join(root, 'wp-org', 'icon-256x256.png')).href;

const html = `<!doctype html><meta charset="utf-8"><style>
*{box-sizing:border-box;margin:0}
body{width:1280px;height:720px;overflow:hidden;font-family:'Segoe UI',Arial,sans-serif;color:#fff;position:relative;
background:radial-gradient(800px 600px at 90% 0,#4766ff 0,transparent 60%),radial-gradient(700px 500px at 0 110%,#14c9a3 0,transparent 55%),linear-gradient(120deg,#0a1030,#16206b)}
.grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);background-size:48px 48px}
.t{position:absolute;left:56px;top:54px;width:720px}
.badge{display:inline-block;font-weight:800;font-size:34px;letter-spacing:.06em;color:#06231a;background:#34d399;padding:10px 26px;border-radius:99px;box-shadow:0 0 40px rgba(52,211,153,.6)}
h1{font-size:88px;white-space:nowrap;line-height:.98;font-weight:900;letter-spacing:-.03em;margin-top:26px;text-shadow:0 6px 30px rgba(0,0,0,.45)}
h1 em{font-style:normal;color:#7fe9cf}
.flow{display:flex;align-items:center;gap:18px;margin-top:30px;font-weight:800;font-size:46px}
.flow span{padding:8px 22px;border-radius:16px;background:rgba(255,255,255,.14);border:2px solid rgba(255,255,255,.35)}
.flow i{font-style:normal;color:#7fe9cf;font-size:56px}
.sub{font-size:40px;font-weight:700;color:#ffc766;margin-top:28px;text-shadow:0 4px 20px rgba(0,0,0,.5)}
.card{position:absolute;right:-170px;top:140px;width:640px;height:480px;border-radius:20px;overflow:hidden;background:#f0f0f1;
box-shadow:0 40px 90px rgba(0,0,0,.6),0 0 0 2px rgba(255,255,255,.3),0 0 120px rgba(79,109,255,.6);transform:perspective(1500px) rotateY(-16deg) rotateX(4deg) rotate(2deg)}
.card .bar{height:34px;background:#e1e4ea;display:flex;gap:8px;align-items:center;padding:0 14px}.bar i{width:12px;height:12px;border-radius:50%;background:#ff5f57}.bar i:nth-child(2){background:#febc2e}.bar i:nth-child(3){background:#28c840}
.card img{width:1100px;margin-left:-140px;display:block}
.ic{position:absolute;right:48px;bottom:36px;width:130px;height:130px;border-radius:30px;box-shadow:0 0 60px rgba(79,109,255,.8)}
</style><body><div class="grid"></div>
<div class="t"><span class="badge">БЕЗКОШТОВНО</span>
<h1>Імпорт з<br><em>Prom.ua</em><br><span style="font-size:.78em">у WooCommerce</span></h1>
<div class="sub">+ автооновлення цін і залишків</div></div>
<div class="card"><div class="bar"><i></i><i></i><i></i></div><img src="${shot}"></div>
<img class="ic" src="${icon}"></body>`;

const f = path.join(outDir, '_thumb.html');
await writeFile(f, html);
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();
await page.goto(pathToFileURL(f).href);
await page.waitForTimeout(400);
await page.screenshot({ path: path.join(outDir, 'thumbnail-1280x720.png') });
await browser.close();
console.log('done');
