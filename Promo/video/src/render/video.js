// Frame-accurate capture: N headless pages render frames in parallel, frames
// are streamed in order into ffmpeg (image2pipe → H.264, 60 fps CFR).
import os from 'node:os';
import { launch, openStage } from './browser.js';
import { run, write } from './ffmpeg.js';

// Motion blur: each output frame averages `blur` sub-frames spread over a
// 180° shutter (half a frame interval), like a real camera.
export async function renderVideo({ out, fps, width, height, from, to, workers, crf = 14, preset = 'slow', blur = 4, shutter = 0.5, onProgress, monitor, project, entry }) {
  const first = Math.round(from * fps);
  const last = Math.round(to * fps); // exclusive
  const total = last - first;
  workers = Math.max(1, Math.min(workers || Math.min(6, os.cpus().length - 2), total));

  const browser = await launch();
  const stages = await Promise.all(Array.from({ length: workers }, () => openStage(browser, { width, height, project, entry })));

  const sub = Math.max(1, blur | 0);
  const subTime = (f, k) => (sub === 1 ? f / fps : (f + (k / (sub - 1) - 0.5) * shutter) / fps);
  const blurFilter = sub > 1 ? ['-vf', `tmix=frames=${sub},select='eq(mod(n,${sub}),${sub - 1})',setpts=N/(${fps}*TB)`] : [];
  const enc = run([
    '-f', 'image2pipe', '-framerate', String(fps * sub), '-c:v', 'png', '-i', '-', ...blurFilter,
    '-c:v', 'libx264', '-preset', preset, '-crf', String(crf), '-tune', 'animation',
    '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-colorspace', 'bt709', '-color_primaries', 'bt709', '-color_trc', 'bt709',
    '-r', String(fps), '-movflags', '+faststart', out,
  ], { stdin: true });

  const started = Date.now();
  let stopped = false;
  enc.done.catch(() => {}); // a stopped render kills ffmpeg on purpose
  try {
    const jobs = total * sub;
    for (let base = 0; base < jobs; base += workers) {
      if (monitor?.stopped) {
        stopped = true;
        break;
      }
      const batch = stages.slice(0, Math.min(workers, jobs - base));
      const frames = await Promise.all(
        batch.map((s, k) => {
          const j = base + k;
          const t = subTime(first + Math.floor(j / sub), j % sub);
          return s.frame(Math.min(to, Math.max(0, t)));
        }),
      );
      for (const f of frames) await write(enc.proc.stdin, f);
      const done = Math.floor(Math.min(jobs, base + workers) / sub);
      const secs = (Date.now() - started) / 1000;
      onProgress?.(done, total, secs);
      monitor?.frame(frames[frames.length - 1]);
      monitor?.progress(done, total, secs);
    }
    const errs = stages.flatMap((s) => s.errors);
    if (errs.length) throw new Error(`Scene errors during render:\n${errs.join('\n')}`);
  } finally {
    if (stopped) enc.proc.kill('SIGKILL');
    else enc.proc.stdin.end();
    await browser.close();
  }
  if (stopped) return { stopped: true, frames: 0, seconds: (Date.now() - started) / 1000 };
  await enc.done;
  return { frames: total, seconds: (Date.now() - started) / 1000 };
}

export async function renderStills({ times, width, height, outDir, project, entry }) {
  const { writeFile } = await import('node:fs/promises');
  const browser = await launch();
  const stage = await openStage(browser, { width, height, project, entry });
  const files = [];
  for (const t of times) {
    const file = `${outDir}/still-${t.toFixed(2)}s.png`;
    await writeFile(file, await stage.frame(t));
    files.push(file);
  }
  await browser.close();
  return files;
}
