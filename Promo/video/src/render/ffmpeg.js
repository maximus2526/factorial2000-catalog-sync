import { spawn } from 'node:child_process';

export function run(args, { stdin = false, quiet = true } = {}) {
  const proc = spawn('ffmpeg', ['-hide_banner', '-loglevel', quiet ? 'error' : 'info', '-y', ...args], {
    stdio: [stdin ? 'pipe' : 'ignore', 'inherit', 'inherit'],
  });
  const done = new Promise((resolve, reject) => {
    proc.on('error', reject);
    proc.on('close', (code) => (code === 0 ? resolve() : reject(new Error(`ffmpeg exited with ${code}`))));
  });
  return { proc, done };
}

// Writes a buffer to the encoder respecting backpressure.
export function write(stream, buf) {
  return stream.write(buf) ? Promise.resolve() : new Promise((r) => stream.once('drain', r));
}
