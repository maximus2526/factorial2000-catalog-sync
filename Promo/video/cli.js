#!/usr/bin/env node
// Promo video generator — from a prompt + store URLs to a 60 fps motion-graphics video.
//
//   node cli.js new "Build the store like https://a.com, also https://b.com" [--name shop]
//                                    → projects/<name>.json (edit copy/brand there)
//   node cli.js all      [project]   capture (if needed) → audio → frames → mux → out/<name>.mp4
//   node cli.js capture  [project]   grab design + interaction states → work/<name>/site<i>/
//   node cli.js video    [project]   [--from 0 --to 8] [--blur 4] [--crf 14] [--workers 6]
//   node cli.js audio    [project]   synthesize the soundtrack → work/<name>/audio.wav
//   node cli.js mux      [project]   merge video + audio (--audio track.wav to use your own)
//   node cli.js stills   [project]   --t 1.2,3.4,6.9   PNG frames for review
//   node cli.js preview  [project]   live loop in your browser
//
// [project] is a name in projects/ or a path to a .json config (default: promo.config.json).
// `all` and `video` open a progress window (live frame, ETA, Stop); --no-window disables it.
import { mkdir, writeFile, readFile, stat } from 'node:fs/promises';
import http from 'node:http';
import { spawn } from 'node:child_process';
import path from 'node:path';
import { parseArgs } from 'node:util';
import { FPS, WIDTH, HEIGHT, configure } from './src/timeline.js';
import * as timeline from './src/timeline.js';
import { resolveConfig, configFromPrompt } from './src/config.js';
import { renderVideo, renderStills } from './src/render/video.js';
import { synthesize, toWav } from './src/audio/synth.js';
import { run } from './src/render/ffmpeg.js';
import { ROOT } from './src/render/browser.js';
import { captureSite } from './src/capture/site.js';
import { startMonitor } from './src/render/monitor.js';

const { positionals, values: o } = parseArgs({
  allowPositionals: true,
  allowNegative: true,
  options: {
    config: { type: 'string' },
    name: { type: 'string' },
    out: { type: 'string' },
    audio: { type: 'string' },
    workers: { type: 'string' },
    from: { type: 'string' },
    to: { type: 'string' },
    crf: { type: 'string' },
    preset: { type: 'string', default: 'slow' },
    blur: { type: 'string' },
    t: { type: 'string', default: '1,3.5,5,7.5,9.5,11,13,15' },
    port: { type: 'string', default: '5178' },
    window: { type: 'boolean', default: true },
  },
});
const cmd = positionals[0] || 'all';
const rel = (p) => path.relative(ROOT, p);

// ── project ────────────────────────────────────────────────────
async function newProject() {
  const text = positionals.slice(1).join(' ');
  if (!text) throw new Error('Usage: node cli.js new "your prompt with https://store-urls"');
  const raw = configFromPrompt(text, { name: o.name });
  const file = path.join(ROOT, 'projects', `${raw.name}.json`);
  await mkdir(path.dirname(file), { recursive: true });
  await writeFile(file, JSON.stringify(raw, null, 2) + '\n');
  console.log(`✓ ${rel(file)}`);
  console.log(`  prompt: ${raw.prompt}`);
  raw.sites.forEach((s, i) => console.log(`  site ${i}: ${s.url}${i ? '' : '  (main store: full purchase flow)'}`));
  console.log(`\nEdit copy/brand in that file if you like (see promo.config.json for every option), then:\n  node cli.js all ${raw.name}`);
}

async function loadProject() {
  const arg = o.config || positionals[1];
  const file = !arg ? path.join(ROOT, 'promo.config.json') : arg.endsWith('.json') ? path.resolve(arg) : path.join(ROOT, 'projects', `${arg}.json`);
  const config = resolveConfig(JSON.parse(await readFile(file, 'utf8')));
  const work = path.join(ROOT, 'work', config.name);
  await mkdir(work, { recursive: true });
  return { file, config, work };
}

const P = cmd === 'new' ? null : await loadProject();
const WORK = P?.work;
const VIDEO = WORK && path.join(WORK, 'video.mp4');
const AUDIO = WORK && path.join(WORK, 'audio.wav');
const OUTFILE = P && path.resolve(ROOT, o.out || `out/${P.config.name}.mp4`);

async function readLayouts() {
  const sites = [];
  for (let i = 0; i < P.config.sites.length; i++) {
    try {
      sites.push(JSON.parse(await readFile(path.join(WORK, `site${i}`, 'layout.json'), 'utf8')));
    } catch {
      return null;
    }
  }
  return sites;
}

// writes the resolved config for the browser and builds the timeline in Node
async function prepare() {
  const sites = await readLayouts();
  if (!sites) throw new Error(`Sites not captured yet — run: node cli.js capture ${P.config.name}`);
  P.config.sites.forEach((s, i) => (s.label ||= (sites[i].title || '').split(/\s[–|-]\s/)[0] || `Store ${i + 1}`));
  await writeFile(path.join(WORK, 'config.json'), JSON.stringify(P.config, null, 2));
  configure(P.config, sites);
}

// ── progress window ────────────────────────────────────────────
const monitor =
  P && ['all', 'video'].includes(cmd) && o.window
    ? startMonitor({ title: P.config.lockup?.title || P.config.name, videoPath: OUTFILE })
    : null;
let inVideo = false;
if (monitor) {
  const log = console.log;
  console.log = (...a) => {
    log(...a);
    monitor.log(a.join(' ').trim());
  };
  console.log(`▶ Progress window: ${monitor.url}`);
  // outside the frame loop nothing can stop cleanly mid-step, so just quit
  monitor.onStop(() => setTimeout(() => !inVideo && (monitor.finish(), setTimeout(() => process.exit(130), 400)), 50));
}
const stage = (name) => monitor?.stage(name);

const bar = (done, total, secs) => {
  const w = 30;
  const k = done / total;
  process.stdout.write(`\r  [${'█'.repeat(Math.round(k * w)).padEnd(w, '░')}] ${done}/${total} frames · ${(done / secs).toFixed(1)} fps`);
};

// ── steps ──────────────────────────────────────────────────────
async function capture() {
  stage('capture');
  for (let i = 0; i < P.config.sites.length; i++) {
    const { url } = P.config.sites[i];
    console.log(`▶ Capturing ${i ? 'extra' : 'main'} store (headless): ${url}`);
    const l = await captureSite({ url, outDir: path.join(WORK, `site${i}`), light: i > 0 });
    console.log(`✓ site${i} · ${l.width}x${l.height}` + (i ? '' : ` · quick view: ${!!l.quickview} · side cart: ${!!l.sidecart} · shop: ${!!l.shop} · filter: ${!!l.shop?.filteredHeight}`));
  }
}

async function audio() {
  stage('audio');
  await prepare();
  console.log('▶ Synthesizing soundtrack…');
  await writeFile(AUDIO, toWav(synthesize()));
  console.log(`✓ ${rel(AUDIO)}`);
}

async function video() {
  await prepare();
  stage('video');
  const blur = +(o.blur ?? P.config.render?.blur ?? 4);
  console.log(`▶ Rendering ${WIDTH}x${HEIGHT} @ ${FPS}fps, ${timeline.DURATION}s, motion blur ×${blur} (headless Chromium)…`);
  inVideo = true;
  const r = await renderVideo({
    out: VIDEO, fps: FPS, width: WIDTH, height: HEIGHT, project: P.config.name,
    from: +(o.from ?? 0), to: +(o.to ?? timeline.DURATION), workers: o.workers && +o.workers,
    crf: +(o.crf ?? P.config.render?.crf ?? 14), preset: o.preset, blur, onProgress: bar, monitor,
  });
  inVideo = false;
  if (r.stopped) {
    process.stdout.write('\n');
    console.log(`⏹ Stopped after ${r.seconds.toFixed(0)}s — ${rel(VIDEO)} is incomplete, final video not updated`);
    return false;
  }
  console.log(`\n✓ ${r.frames} frames in ${r.seconds.toFixed(1)}s → ${rel(VIDEO)}`);
  return true;
}

async function mux() {
  await prepare();
  const own = o.audio || P.config.audio?.track;
  const track = own ? path.resolve(own) : AUDIO;
  await stat(track);
  const dur = +(o.to ?? timeline.DURATION) - +(o.from ?? 0);
  // two-pass EBU R128 loudness normalisation → -14 LUFS, true peak -1 dBTP (streaming/social standard)
  const target = 'I=-14:TP=-1:LRA=11';
  stage('mux');
  console.log('▶ Measuring loudness…');
  const measured = await new Promise((resolve, reject) => {
    const p = spawn('ffmpeg', ['-hide_banner', '-i', track, '-af', `loudnorm=${target}:print_format=json`, '-f', 'null', '-']);
    let err = '';
    p.stderr.on('data', (d) => (err += d));
    p.on('close', (c) => (c === 0 ? resolve(JSON.parse(err.slice(err.lastIndexOf('{'), err.lastIndexOf('}') + 1))) : reject(new Error(err))));
  });
  const ln = `loudnorm=${target}:measured_I=${measured.input_i}:measured_TP=${measured.input_tp}:measured_LRA=${measured.input_lra}:measured_thresh=${measured.input_thresh}:offset=${measured.target_offset}:linear=true`;
  await mkdir(path.dirname(OUTFILE), { recursive: true });
  console.log(`▶ Muxing ${rel(track)} (${measured.input_i} → -14 LUFS) → ${rel(OUTFILE)}`);
  await run([
    '-i', VIDEO, '-i', track, '-map', '0:v:0', '-map', '1:a:0',
    '-c:v', 'copy', '-c:a', 'aac', '-b:a', '320k', '-ar', '48000',
    '-af', `${ln},afade=t=out:st=${dur - 0.3}:d=0.3`, '-t', String(dur),
    '-movflags', '+faststart', OUTFILE,
  ]).done;
  console.log(`✓ ${rel(OUTFILE)}`);
}

async function stills() {
  await prepare();
  const dir = path.join(WORK, 'stills');
  await mkdir(dir, { recursive: true });
  const files = await renderStills({ times: o.t.split(',').map(Number), width: WIDTH, height: HEIGHT, outDir: dir, project: P.config.name });
  files.forEach((f) => console.log('✓', rel(f)));
}

async function preview() {
  await prepare();
  const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.png': 'image/png' };
  http
    .createServer(async (req, res) => {
      const file = path.join(ROOT, decodeURIComponent(new URL(req.url, 'http://x').pathname));
      try {
        if (!file.startsWith(ROOT)) throw new Error();
        res.writeHead(200, { 'content-type': types[path.extname(file)] || 'application/octet-stream' });
        res.end(await readFile(file));
      } catch {
        res.writeHead(404).end();
      }
    })
    .listen(+o.port, () => {
      const q = `project=${P.config.name}`;
      console.log(`▶ Live preview:  http://localhost:${o.port}/src/scenes/index.html?${q}&play`);
      console.log(`  Freeze frame:  http://localhost:${o.port}/src/scenes/index.html?${q}&t=5.8`);
    });
}

const hasAssets = async () => !!(await readLayouts());
const steps = {
  new: newProject,
  capture, video, audio, mux, stills, preview,
  all: async () => {
    if (!(await hasAssets())) await capture();
    await audio();
    if (!(await video())) return false;
    await mux();
    return true;
  },
};
if (!steps[cmd]) {
  console.error(`Unknown command "${cmd}". Use: ${Object.keys(steps).join(' | ')}`);
  process.exit(1);
}

try {
  const ok = await steps[cmd]();
  if (monitor) {
    monitor.finish({ video: ok !== false && cmd === 'all' });
    if (ok !== false && cmd === 'all') console.log('✓ Video is playing in the progress window — close the tab when done');
    await monitor.waitClosed();
  }
} catch (e) {
  monitor?.finish({ error: e });
  if (monitor) await monitor.waitClosed(60 * 1000);
  throw e;
}
