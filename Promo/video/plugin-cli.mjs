#!/usr/bin/env node
// Factorial2000 Catalog Sync promo.
//   node plugin-cli.mjs stills [--t 1,5,9]   review PNGs → work/plugin/stills
//   node plugin-cli.mjs audio                synthesize → work/plugin/audio.wav
//   node plugin-cli.mjs video [--blur 4]     frames → work/plugin/video.mp4
//   node plugin-cli.mjs mux                  → out/factorial2000-catalog-sync.mp4
//   node plugin-cli.mjs all
// Add --reels for the vertical 1080x1920 cut (out/factorial2000-catalog-sync-reels.mp4).
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { parseArgs } from 'node:util';
const REELS = process.argv.includes('--reels');
const TL = await import(REELS ? './src/plugin/timeline-v.js' : './src/plugin/timeline.js');
const { FPS, WIDTH, HEIGHT, DURATION } = TL;
import { renderVideo, renderStills } from './src/render/video.js';
import { run } from './src/render/ffmpeg.js';
import { ROOT } from './src/render/browser.js';
const { synthesize, toWav } = await import(REELS ? './src/plugin/synth-v.js' : './src/plugin/synth.js');

const { positionals, values: o } = parseArgs({
  allowPositionals: true,
  strict: false,
  options: { reels: { type: 'boolean' }, t: { type: 'string', default: '1.5,3.5,6,9,13,19,26,34,41' }, blur: { type: 'string', default: '4' }, crf: { type: 'string', default: '14' }, preset: { type: 'string', default: 'slow' }, workers: { type: 'string' } },
});
const cmd = positionals[0] || 'all';
const WORK = path.join(ROOT, 'work', REELS ? 'plugin-reels' : 'plugin');
const VIDEO = path.join(WORK, 'video.mp4');
const AUDIO = path.join(WORK, 'audio.wav');
const OUT = path.join(ROOT, 'out', REELS ? 'factorial2000-catalog-sync-reels.mp4' : 'factorial2000-catalog-sync.mp4');
const ENTRY = REELS ? '/src/plugin/index-v.html' : '/src/plugin/index.html';
await mkdir(WORK, { recursive: true });

const bar = (done, total, secs) => process.stdout.write(`\r  ${done}/${total} frames · ${(done / secs).toFixed(1)} fps`);

const steps = {
  async stills() {
    await mkdir(path.join(WORK, 'stills'), { recursive: true });
    const files = await renderStills({ times: o.t.split(',').map(Number), width: WIDTH, height: HEIGHT, outDir: path.join(WORK, 'stills'), project: 'plugin', entry: ENTRY });
    files.forEach((f) => console.log('✓', f));
  },
  async audio() {
    await writeFile(AUDIO, toWav(synthesize()));
    console.log('✓', AUDIO);
  },
  async video() {
    const r = await renderVideo({ out: VIDEO, fps: FPS, width: WIDTH, height: HEIGHT, project: 'plugin', entry: ENTRY, from: 0, to: DURATION, workers: o.workers && +o.workers, crf: +o.crf, preset: o.preset, blur: +o.blur, onProgress: bar });
    console.log(`\n✓ ${r.frames} frames in ${r.seconds.toFixed(0)}s`);
  },
  async mux() {
    const target = 'I=-14:TP=-1:LRA=11';
    const measured = await new Promise((resolve, reject) => {
      const p = spawn('ffmpeg', ['-hide_banner', '-i', AUDIO, '-af', `loudnorm=${target}:print_format=json`, '-f', 'null', '-']);
      let err = '';
      p.stderr.on('data', (d) => (err += d));
      p.on('close', (c) => (c === 0 ? resolve(JSON.parse(err.slice(err.lastIndexOf('{'), err.lastIndexOf('}') + 1))) : reject(new Error(err))));
    });
    const ln = `loudnorm=${target}:measured_I=${measured.input_i}:measured_TP=${measured.input_tp}:measured_LRA=${measured.input_lra}:measured_thresh=${measured.input_thresh}:offset=${measured.target_offset}:linear=true`;
    await mkdir(path.dirname(OUT), { recursive: true });
    await run(['-i', VIDEO, '-i', AUDIO, '-map', '0:v:0', '-map', '1:a:0', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '320k', '-ar', '48000', '-af', `${ln},afade=t=out:st=${DURATION - 0.3}:d=0.3`, '-t', String(DURATION), '-movflags', '+faststart', OUT]).done;
    console.log('✓', OUT);
  },
  async all() {
    await steps.audio();
    await steps.video();
    await steps.mux();
  },
};
if (!steps[cmd]) throw new Error(`Unknown command ${cmd}`);
await steps[cmd]();
