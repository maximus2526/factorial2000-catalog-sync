// Render monitor: a small local web page that opens while rendering.
// Shows the current stage, live frame preview, progress/ETA and a Stop
// button; when the render finishes it plays the final video.
import http from 'node:http';
import { spawn } from 'node:child_process';
import { createReadStream, statSync } from 'node:fs';

export function startMonitor({ port = 5179, title = 'Render', videoPath, openBrowser = true } = {}) {
  const state = { stage: 'start', done: 0, total: 0, elapsed: 0, logs: [], finished: false, stopped: false, error: null, video: false };
  let frame = null;
  let frameId = 0;
  let lastPush = 0;
  const clients = new Set();
  const stopHandlers = [];
  let closeResolve;
  const closed = new Promise((r) => (closeResolve = r));

  const push = (force = false) => {
    const now = Date.now();
    if (!force && now - lastPush < 150) return;
    lastPush = now;
    const msg = `data: ${JSON.stringify({ ...state, frameId })}\n\n`;
    for (const c of clients) c.write(msg);
  };

  const server = http.createServer((req, res) => {
    const u = new URL(req.url, 'http://x');
    if (u.pathname === '/') {
      res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
      return res.end(page(title));
    }
    if (u.pathname === '/events') {
      res.writeHead(200, { 'content-type': 'text/event-stream', 'cache-control': 'no-cache', connection: 'keep-alive' });
      clients.add(res);
      res.write(`data: ${JSON.stringify({ ...state, frameId })}\n\n`);
      req.on('close', () => {
        clients.delete(res);
        // tab closed after the render is over → let the CLI exit
        if ((state.finished || state.stopped) && clients.size === 0) setTimeout(() => clients.size === 0 && closeResolve(), 1500);
      });
      return;
    }
    if (u.pathname === '/frame') {
      if (!frame) return res.writeHead(204).end();
      res.writeHead(200, { 'content-type': 'image/png', 'cache-control': 'no-store' });
      return res.end(frame);
    }
    if (u.pathname === '/video' && state.video && videoPath) {
      const { size } = statSync(videoPath);
      const m = /bytes=(\d*)-(\d*)/.exec(req.headers.range || '');
      if (m) {
        const start = m[1] ? +m[1] : 0;
        const end = m[2] ? +m[2] : size - 1;
        res.writeHead(206, { 'content-type': 'video/mp4', 'accept-ranges': 'bytes', 'content-range': `bytes ${start}-${end}/${size}`, 'content-length': end - start + 1 });
        return createReadStream(videoPath, { start, end }).pipe(res);
      }
      res.writeHead(200, { 'content-type': 'video/mp4', 'accept-ranges': 'bytes', 'content-length': size });
      return createReadStream(videoPath).pipe(res);
    }
    if (u.pathname === '/stop' && req.method === 'POST') {
      if (!state.stopped && !state.finished) {
        state.stopped = true;
        state.logs.push('⏹ Зупинка за запитом…');
        push(true);
        stopHandlers.forEach((f) => f());
      }
      return res.writeHead(204).end();
    }
    if (u.pathname === '/close' && req.method === 'POST') {
      res.writeHead(204).end();
      return closeResolve();
    }
    res.writeHead(404).end();
  });

  const url = `http://localhost:${port}/`;
  server.listen(port, () => {
    if (!openBrowser) return;
    const opener = process.platform === 'darwin' ? 'open' : process.platform === 'win32' ? 'explorer' : 'xdg-open';
    spawn(opener, [url], { stdio: 'ignore', detached: true }).unref();
  });

  return {
    url,
    get stopped() {
      return state.stopped;
    },
    onStop: (fn) => stopHandlers.push(fn),
    stage(name) {
      state.stage = name;
      push(true);
    },
    log(line) {
      state.logs.push(line);
      if (state.logs.length > 40) state.logs.shift();
      push(true);
    },
    progress(done, total, elapsed) {
      Object.assign(state, { done, total, elapsed });
      push();
    },
    frame(buf) {
      frame = buf;
      frameId++;
    },
    finish({ error, video } = {}) {
      Object.assign(state, { finished: true, stage: error ? 'error' : state.stopped ? 'stopped' : 'done', error: error ? String(error.message || error) : null, video: !!video });
      push(true);
    },
    // resolves when the user closes the tab / presses "Закрити", or after a timeout
    async waitClosed(ms = 10 * 60 * 1000) {
      await Promise.race([closed, new Promise((r) => setTimeout(r, ms).unref())]);
      for (const c of clients) c.end();
      server.close();
    },
    close() {
      for (const c of clients) c.end();
      server.close();
    },
  };
}

const page = (title) => `<!doctype html>
<html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${title} · Render</title>
<style>
  :root { --bg:#0b0b0b; --card:#151515; --line:#262626; --text:#eaeaea; --dim:#8a8a8a; --green:#83b735; --red:#e5484d; }
  * { box-sizing: border-box; margin: 0; }
  body { background: var(--bg); color: var(--text); font: 15px/1.5 -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif; padding: 28px 16px; }
  .wrap { max-width: 1080px; margin: 0 auto; }
  header { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
  header h1 { font-size: 20px; font-weight: 700; }
  header h1 i { font-style: normal; color: var(--green); }
  .badge { margin-left: auto; padding: 5px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; background: #1f2a12; color: var(--green); }
  .badge.stopped, .badge.error { background: #2a1314; color: var(--red); }
  .stages { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
  .stages span { padding: 5px 12px; border-radius: 8px; border: 1px solid var(--line); color: var(--dim); font-size: 13px; }
  .stages span.on { border-color: var(--green); color: var(--text); }
  .stages span.ok { color: var(--green); }
  .screen { position: relative; aspect-ratio: 16/9; background: #000; border-radius: 12px; overflow: hidden; border: 1px solid var(--line); }
  .screen img, .screen video { width: 100%; height: 100%; object-fit: contain; display: block; }
  .screen .ph { position: absolute; inset: 0; display: grid; place-items: center; color: var(--dim); }
  .bar { height: 10px; background: #222; border-radius: 6px; overflow: hidden; margin: 16px 0 10px; }
  .bar i { display: block; height: 100%; width: 0; background: linear-gradient(90deg, #5f8f22, var(--green), #c6ef7f); transition: width .2s; }
  .row { display: flex; gap: 22px; flex-wrap: wrap; align-items: center; color: var(--dim); font-size: 14px; }
  .row b { color: var(--text); font-variant-numeric: tabular-nums; }
  .row .sp { flex: 1; }
  button { border: 0; border-radius: 10px; padding: 11px 22px; font: 600 15px inherit; font-family: inherit; cursor: pointer; }
  #stop { background: var(--red); color: #fff; }
  #stop:disabled { opacity: .4; cursor: default; }
  #close { background: #262626; color: var(--text); display: none; }
  pre { margin-top: 16px; background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; font: 12.5px/1.55 Menlo, monospace; color: #bdbdbd; max-height: 190px; overflow: auto; white-space: pre-wrap; }
</style></head>
<body><div class="wrap">
  <header><h1>${title}<i>.</i> Render</h1><span class="badge" id="badge">Підготовка…</span></header>
  <div class="stages" id="stages"><span data-s="capture">Захоплення сайтів</span><span data-s="audio">Звук</span><span data-s="video">Кадри</span><span data-s="mux">Зведення</span><span data-s="done">Готово</span></div>
  <div class="screen" id="screen"><img id="prev" alt=""><div class="ph" id="ph">Очікую перший кадр…</div></div>
  <div class="bar"><i id="fill"></i></div>
  <div class="row">
    <span>Кадри <b id="frames">—</b></span><span>Швидкість <b id="fps">—</b></span><span>Минуло <b id="el">—</b></span><span>Лишилось <b id="eta">—</b></span>
    <span class="sp"></span><button id="close">Закрити</button><button id="stop">⏹ Стоп</button>
  </div>
  <pre id="log"></pre>
</div>
<script>
  const $ = (id) => document.getElementById(id);
  const order = ['capture', 'audio', 'video', 'mux', 'done'];
  const fmt = (s) => !isFinite(s) ? '—' : s < 60 ? Math.round(s) + ' с' : Math.floor(s / 60) + ' хв ' + Math.round(s % 60) + ' с';
  let loading = false, shownId = -1, played = false;
  $('prev').onload = () => { loading = false; $('ph').style.display = 'none'; };
  $('prev').onerror = () => (loading = false);
  $('stop').onclick = () => { $('stop').disabled = true; fetch('/stop', { method: 'POST' }); };
  $('close').onclick = () => fetch('/close', { method: 'POST' }).finally(() => window.close());
  const es = new EventSource('/events');
  es.onmessage = (e) => {
    const s = JSON.parse(e.data);
    const idx = order.indexOf(s.stage);
    document.querySelectorAll('#stages span').forEach((el, i) => {
      el.className = el.dataset.s === s.stage ? 'on' : (idx > i || s.stage === 'done') ? 'ok' : '';
    });
    const label = { start: 'Підготовка…', capture: 'Захоплення сайтів', audio: 'Синтез звуку', video: 'Рендер кадрів', mux: 'Зведення', done: '✓ Готово', stopped: '⏹ Зупинено', error: '✕ Помилка' }[s.stage] || s.stage;
    $('badge').textContent = s.stopped && !s.finished ? 'Зупиняю…' : label;
    $('badge').className = 'badge ' + (s.stopped ? 'stopped' : s.stage === 'error' ? 'error' : '');
    if (s.total) {
      const k = s.done / s.total, fps = s.done / Math.max(s.elapsed, 0.001);
      $('fill').style.width = (k * 100).toFixed(1) + '%';
      $('frames').textContent = s.done + ' / ' + s.total;
      $('fps').textContent = fps.toFixed(1) + ' fps';
      $('el').textContent = fmt(s.elapsed);
      $('eta').textContent = s.done && !s.stopped && !s.finished ? fmt((s.total - s.done) / fps) : '—';
    }
    if (s.stage === 'done') $('fill').style.width = '100%';
    if (s.frameId !== shownId && !loading && !s.video) { loading = true; shownId = s.frameId; $('prev').src = '/frame?' + s.frameId; }
    if (s.video && !played) {
      played = true;
      $('screen').innerHTML = '<video src="/video" controls autoplay playsinline></video>';
    }
    if (s.finished) { $('stop').style.display = 'none'; $('close').style.display = 'inline-block'; }
    $('log').textContent = s.logs.join('\\n') + (s.error ? '\\n✕ ' + s.error : '');
    $('log').scrollTop = 1e9;
  };
</script></body></html>`;
