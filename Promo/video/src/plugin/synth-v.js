// Procedural soundtrack (no samples): ambient intro + typing, "tra-ta-ta" drop
// at 2.5s, 120 BPM groove, UI sounds locked to the same timeline as visuals.
import { ACTS, DURATION, BEAT, BOARD, TITLE_AT, bulletAt, beatsBetween } from './timeline-v.js';

const TAU = Math.PI * 2;

function rng(seed) {
  return () => ((seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0) / 4294967296);
}

// Chamberlin state-variable filter (stable below sr/6).
function svf(sr) {
  let low = 0;
  let band = 0;
  return (x, fc, damp = 0.7) => {
    const f = 2 * Math.sin((Math.PI * Math.min(fc, sr / 6.2)) / sr);
    const high = x - low - damp * band;
    band += f * high;
    low += f * band;
    return { low, band, high };
  };
}

const note = (m) => 440 * Math.pow(2, (m - 69) / 12); // MIDI → Hz

export function synthesize({ sampleRate: SR = 48000 } = {}) {
  const N = Math.ceil(DURATION * SR);
  const L = new Float32Array(N);
  const R = new Float32Array(N);
  const SL = new Float32Array(N); // reverb send
  const SRt = new Float32Array(N);
  const ML = new Float32Array(N); // music bus (sidechained to the kick)
  const MR = new Float32Array(N);
  const DL = new Float32Array(N); // echo send
  const DR = new Float32Array(N);
  const kicks = [];
  const rand = rng(2026);
  const noise = () => rand() * 2 - 1;

  function add(t0, dur, voice, { gain = 1, pan = 0, send = 0, bus = 'd', echo = 0 } = {}) {
    const [BL, BR] = bus === 'm' ? [ML, MR] : [L, R];
    const s0 = Math.max(0, Math.round(t0 * SR));
    const s1 = Math.min(N, Math.round((t0 + dur) * SR));
    const gl = gain * Math.cos(((pan + 1) * Math.PI) / 4) * Math.SQRT2;
    const gr = gain * Math.sin(((pan + 1) * Math.PI) / 4) * Math.SQRT2;
    const st = {};
    for (let i = s0; i < s1; i++) {
      const v = voice((i - s0) / SR, st);
      BL[i] += v * gl;
      BR[i] += v * gr;
      if (echo) {
        DL[i] += v * gl * echo;
        DR[i] += v * gr * echo;
      }
      if (send) {
        SL[i] += v * gl * send;
        SRt[i] += v * gr * send;
      }
    }
  }

  // ── instruments ─────────────────────────────────────────────
  const kick = (punch = 1, dec = 7) => (t, s) => {
    const f = 44 + 120 * punch * Math.exp(-t * 30);
    s.ph = (s.ph || 0) + (TAU * f) / SR;
    const click = t < 0.005 ? noise() * (1 - t / 0.005) * 0.5 : 0;
    return Math.tanh(1.8 * Math.sin(s.ph) * Math.exp(-t * dec)) + click;
  };
  const snare = (tone = 190, bright = 3600) => (t, s) => {
    s.f ||= svf(SR);
    const n = s.f(noise(), bright, 0.6);
    const body = Math.sin(TAU * tone * t - 6 * Math.exp(-t * 40)) * Math.exp(-t * 28);
    return (0.9 * (n.high + n.band) * Math.exp(-t * 15) + 0.7 * body) * 0.8;
  };
  const clap = (t, s) => {
    s.f ||= svf(SR);
    const env = [0, 0.011, 0.022].reduce((a, d) => a + (t >= d ? Math.exp(-(t - d) * (d === 0.022 ? 18 : 140)) : 0), 0);
    return s.f(noise(), 1500, 0.4).band * env * 1.4;
  };
  const hat = (open = false) => (t, s) => {
    s.f ||= svf(SR);
    return s.f(noise(), 7600, 0.5).high * Math.exp(-t * (open ? 11 : 55));
  };
  const crash = (t, s) => {
    s.f ||= svf(SR);
    const metal = [3.1, 4.7, 6.3, 8.9].reduce((a, k) => a + Math.sign(Math.sin(TAU * 540 * k * t)), 0) * 0.05;
    return (s.f(noise(), 6200, 0.6).high + metal) * Math.exp(-t * 2.6);
  };
  const bass = (freq, len) => (t, s) => {
    s.f ||= svf(SR);
    s.ph = (s.ph || 0) + freq / SR;
    const saw = 2 * (s.ph % 1) - 1;
    const env = Math.min(1, t / 0.004) * (t < len ? Math.exp(-t * 3) : Math.exp(-len * 3 - (t - len) * 60));
    const fc = 180 + 1500 * Math.exp(-t * 14);
    return (Math.tanh(1.6 * s.f(saw, fc, 0.45).low) + 0.6 * Math.sin(TAU * freq * t)) * env;
  };
  const stab = (freqs, len) => (t, s) => {
    s.f ||= svf(SR);
    s.ph ||= freqs.flatMap(() => [rand(), rand()]);
    let x = 0;
    freqs.forEach((f, i) => {
      s.ph[2 * i] += (f * 1.004) / SR;
      s.ph[2 * i + 1] += (f * 0.996) / SR;
      x += (s.ph[2 * i] % 1) + (s.ph[2 * i + 1] % 1) - 1;
    });
    const env = Math.min(1, t / 0.003) * Math.exp(-t * (t < len ? 5 : 30));
    return s.f(x / freqs.length, 300 + 5200 * Math.exp(-t * 9), 0.35).low * env;
  };
  const pad = (freqs, dur, attack, release, fc0, fc1) => (t, s) => {
    s.f ||= [svf(SR), svf(SR)];
    s.ph ||= freqs.flatMap(() => [rand(), rand(), rand()]);
    let x = 0;
    freqs.forEach((f, i) => {
      [1.006, 0.994, 0.5].forEach((d, j) => {
        s.ph[3 * i + j] += (f * d) / SR;
        x += j === 2 ? Math.sin(TAU * s.ph[3 * i + j]) * 0.8 : 2 * (s.ph[3 * i + j] % 1) - 1;
      });
    });
    const env = Math.min(1, t / attack) * Math.min(1, Math.max(0, (dur - t) / release));
    const fc = fc0 + (fc1 - fc0) * Math.min(1, t / dur);
    return s.f[1](s.f[0](x / freqs.length, fc, 0.6).low, fc, 0.6).low * env;
  };
  const riser = (dur, f0 = 200, f1 = 1600) => (t, s) => {
    s.f ||= svf(SR);
    const k = t / dur;
    s.ph = (s.ph || 0) + (f0 * Math.pow(f1 / f0, k)) / SR;
    const n = s.f(noise(), 300 * Math.pow(25, k), 0.35).band;
    return (n * 0.9 + 0.35 * Math.sin(TAU * s.ph)) * k * k;
  };
  const whoosh = (dur, up = true) => (t, s) => {
    s.f ||= svf(SR);
    const k = t / dur;
    const fc = up ? 400 * Math.pow(12, k) : 5000 * Math.pow(1 / 10, k);
    return s.f(noise(), fc, 0.3).band * Math.sin(Math.PI * k) ** 2;
  };
  const impact = (t, s) => {
    s.f ||= svf(SR);
    const sub = Math.sin(TAU * (36 + 30 * Math.exp(-t * 8)) * t) * Math.exp(-t * 2.2);
    const crunch = s.f(noise(), 900 + 3000 * Math.exp(-t * 6), 0.5).low * Math.exp(-t * 5);
    return Math.tanh(1.5 * sub) + crunch * 0.8;
  };
  const pop = (f = 700) => (t) => Math.sin(TAU * (f + f * 1.2 * Math.min(1, t / 0.03)) * t) * Math.exp(-t * 32);
  const keyClick = (t, s) => {
    s.f ||= svf(SR);
    return s.f(noise(), 3800, 0.4).high * Math.exp(-t * 420) + Math.sin(TAU * 1800 * t) * Math.exp(-t * 600) * 0.3;
  };
  const uiClick = (t) => (Math.sin(TAU * 2600 * t) * 0.6 + noise() * 0.4) * Math.exp(-t * 260);
  const bell = (f) => (t) =>
    (Math.sin(TAU * f * t + 1.2 * Math.sin(TAU * f * 3.5 * t) * Math.exp(-t * 6)) * Math.exp(-t * 3.2));

  // ── extra instruments ───────────────────────────────────────
  // plucked synth: detuned saws through a fast-closing lowpass
  const pluck = (freq, len = 0.3) => (t, s) => {
    s.f ||= svf(SR);
    s.ph ||= [rand(), rand()];
    s.ph[0] += (freq * 1.003) / SR;
    s.ph[1] += (freq * 0.997) / SR;
    const x = (s.ph[0] % 1) + (s.ph[1] % 1) - 1;
    const env = Math.min(1, t / 0.002) * Math.exp(-t / len);
    return s.f(x, 500 + 5200 * Math.exp(-t * 9), 0.3).low * env;
  };
  // soft sine/triangle lead with vibrato
  const lead = (freq, len) => (t, s) => {
    s.ph = (s.ph || 0) + (freq * (1 + 0.004 * Math.sin(TAU * 5.2 * t) * Math.min(1, t / 0.25))) / SR;
    const tri = 2 * Math.abs(2 * (s.ph % 1) - 1) - 1;
    const env = Math.min(1, t / 0.012) * (t < len ? 1 : Math.exp(-(t - len) * 14)) * Math.exp(-t * 0.6);
    return (0.7 * Math.sin(TAU * s.ph) + 0.3 * tri + 0.18 * Math.sin(TAU * s.ph * 2)) * env;
  };
  const sub = (freq, len) => (t, s) => {
    s.ph = (s.ph || 0) + freq / SR;
    const env = Math.min(1, t / 0.006) * (t < len ? 1 : Math.exp(-(t - len) * 40));
    return Math.tanh(1.4 * Math.sin(TAU * s.ph)) * env;
  };
  const shaker = (t, s) => {
    s.f ||= svf(SR);
    return s.f(noise(), 9500, 0.45).high * Math.exp(-t * 38) * (1 - Math.exp(-t * 700));
  };

  // ── arrangement (A minor: Am – F – C – G, two seconds per chord) ──
  const A = ACTS;
  const starts = Object.values(A).map((r) => r[0]);
  const PROG = [
    { root: 45, tones: [57, 60, 64, 67], arp: [57, 64, 60, 67, 64, 60, 67, 64] },
    { root: 41, tones: [53, 57, 60, 64], arp: [53, 60, 57, 64, 60, 57, 64, 60] },
    { root: 48, tones: [55, 60, 64, 67], arp: [60, 67, 64, 72, 67, 64, 72, 67] },
    { root: 43, tones: [55, 59, 62, 67], arp: [55, 62, 59, 67, 62, 59, 67, 62] },
  ];
  const chordAt = (t) => PROG[Math.floor(t / 2) % PROG.length];
  const GROOVE = [A.import[0], A.outro[0] + 3]; // drums play from the import act to the outro
  const TITLE_HIT = A.intro[0] + TITLE_AT;

  // pads: warm, slow attack, ducked by the kick; the last chord rings out
  for (let t = 0, i = 0; t < DURATION; t += 2, i++) {
    const c = chordAt(t);
    const last = t + 2 >= DURATION;
    const len = last ? 4 : 2.1;
    add(t, len, pad(c.tones.map((m) => note(m - 12)), len, 0.35, last ? 2.2 : 0.3, 380 + (i % 4) * 140, 1300 + (i % 4) * 200), { gain: 0.34, send: 0.55, bus: 'm' });
    add(t, len, pad(c.tones.slice(1).map((m) => note(m)), len, 0.5, 0.4, 700, 2200), { gain: 0.12, send: 0.7, bus: 'm', pan: i % 2 ? 0.4 : -0.4 });
  }

  // arpeggio (8th notes, ping-pong echo) joins in the intro and plays to the end
  const arpFrom = A.intro[0] + 1.0;
  for (let t = arpFrom, k = 0; t < DURATION - 2.5; t += BEAT / 2, k++) {
    const c = chordAt(t);
    const m = c.arp[k % 8];
    const shy = Math.min(1, (t - arpFrom) / 2.5);
    const showcase = t >= A.board[0] && t < A.board[1];
    add(t, 0.45, pluck(note(m), 0.22), { gain: (0.2 + (k % 4 === 0 ? 0.06 : 0)) * shy * (showcase ? 1.25 : 1), pan: k % 2 ? 0.45 : -0.45, send: 0.35, echo: 0.5, bus: 'm' });
  }

  // intro: bells with each chip, riser, title hit
  [0.2, 0.65, 1.1].forEach((t, i) => add(A.intro[0] + t, 0.3, bell(note(81 + [0, 4, 7][i])), { gain: 0.16, pan: -0.4 + i * 0.4, send: 0.6, echo: 0.3 }));
  add(A.intro[0] + TITLE_AT - 0.8, 0.8, riser(0.8, 200, 1900), { gain: 0.4, send: 0.4 });
  add(TITLE_HIT, 1.8, impact, { gain: 0.85, send: 0.5 });
  add(TITLE_HIT, 1.4, crash, { gain: 0.2, send: 0.5 });
  kicks.push(TITLE_HIT);
  [[45, 0], [52, 0.05], [57, 0.1], [64, 0.15]].forEach(([m, d]) => add(TITLE_HIT + d, 0.9, lead(note(m + 12), 0.4), { gain: 0.14, send: 0.6, echo: 0.3, bus: 'm', pan: d - 0.07 }));

  // drums + bass
  beatsBetween(GROOVE[0], GROOVE[1]).forEach((b, i) => {
    kicks.push(b);
    add(b, 0.5, kick(1, 7), { gain: 0.8 });
    if (i % 2 === 1) {
      add(b, 0.3, clap, { gain: 0.3, send: 0.35 });
      add(b, 0.25, snare(200, 4200), { gain: 0.12, send: 0.2 });
    }
    add(b + BEAT / 2, 0.2, hat(i % 4 === 3), { gain: 0.14, pan: 0.25 });
    add(b + BEAT / 4, 0.1, shaker, { gain: 0.1, pan: -0.3 });
    add(b + BEAT * 0.75, 0.1, shaker, { gain: 0.12, pan: 0.3 });
    const r = chordAt(b).root - 12;
    add(b + BEAT / 2, 0.3, sub(note(r), 0.22), { gain: 0.5 });
    add(b + BEAT / 2, 0.3, bass(note(r), 0.18), { gain: 0.24 });
    if (i % 4 === 3) add(b + BEAT * 0.75, 0.2, sub(note(r + 7), 0.14), { gain: 0.36 });
  });

  // act changes: riser, whoosh, impact on the downbeat
  starts.slice(1).forEach((t) => {
    add(t - 0.6, 0.6, riser(0.6, 300, 2200), { gain: 0.28, send: 0.4 });
    add(t - 0.3, 0.4, whoosh(0.4), { gain: 0.24 });
    add(t, 1.2, impact, { gain: 0.5, send: 0.5 });
    add(t, 1.6, crash, { gain: 0.12, send: 0.5 });
  });

  // lead melody over each content act: a simple 4-note hook per chord
  const HOOK = [[0, 0], [0.5, 2], [1, 1], [1.5, 3]];
  [A.import, A.sync, A.market].forEach((r) => {
    for (let t = r[0] + 1; t < r[1] - 0.5; t += 2) {
      const c = chordAt(t);
      HOOK.forEach(([d, k]) => add(t + d, 0.5, lead(note(c.tones[k] + 12), 0.28), { gain: 0.12, pan: 0.2, send: 0.5, echo: 0.35, bus: 'm' }));
    }
  });

  // bullet pops
  [A.import, A.sync, A.market].forEach((r, k) => {
    [0, 1, 2].forEach((i) => add(r[0] + bulletAt(i), 0.25, pop(820 + i * 120 + k * 40), { gain: 0.2, pan: k === 1 ? 0.4 - i * 0.2 : -0.4 + i * 0.2, send: 0.4 }));
    add(r[0] + 0.5, 0.5, whoosh(0.5, false), { gain: 0.12 });
  });
  // board: items tick with rising bells
  [0, 1, 2, 3, 4, 5].forEach((i) => {
    add(A.board[0] + BOARD.free + i * BOARD.step, 0.1, uiClick, { gain: 0.26, pan: -0.5, send: 0.3 });
    add(A.board[0] + BOARD.pro + i * BOARD.step, 0.1, uiClick, { gain: 0.26, pan: 0.5, send: 0.3 });
    add(A.board[0] + BOARD.pro + i * BOARD.step, 0.7, bell(note(84 + [0, 2, 4, 7, 9, 12][i])), { gain: 0.07, pan: 0.5, send: 0.6, echo: 0.3 });
  });
  // outro: chime run on the title, final sparkle
  [81, 84, 88, 93].forEach((m, i) => add(A.outro[0] + 0.06 + i * 0.09, 0.9, bell(note(m)), { gain: 0.1, send: 0.7, echo: 0.3 }));
  add(A.outro[0] + 1.5, 0.3, pop(900), { gain: 0.24, send: 0.4 });
  [93, 96, 100].forEach((m, i) => add(A.outro[0] + 3.2 + i * 0.12, 1.5, bell(note(m)), { gain: 0.06, send: 0.8, echo: 0.4 }));

  // ── reverb (Freeverb-style combs + allpasses) ──────────────
  function reverb(input, spread) {
    const out = new Float32Array(N);
    const combs = [1557, 1617, 1491, 1422, 1277, 1356].map((d) => ({ buf: new Float32Array(Math.round(((d + spread) * SR) / 44100)), i: 0, lp: 0 }));
    const aps = [556, 441, 341].map((d) => ({ buf: new Float32Array(Math.round(((d + spread) * SR) / 44100)), i: 0 }));
    for (let n = 0; n < N; n++) {
      let acc = 0;
      for (const c of combs) {
        const y = c.buf[c.i];
        c.lp = y * 0.65 + c.lp * 0.35;
        c.buf[c.i] = input[n] + c.lp * 0.8;
        c.i = (c.i + 1) % c.buf.length;
        acc += y;
      }
      for (const a of aps) {
        const b = a.buf[a.i];
        const y = -acc + b;
        a.buf[a.i] = acc + b * 0.5;
        a.i = (a.i + 1) % a.buf.length;
        acc = y;
      }
      out[n] = acc * 0.06;
    }
    return out;
  }
  // echo (ping-pong, dotted/straight eighths) built from the echo send
  function echo(inp, delay, fb) {
    const out = new Float32Array(N);
    const d = Math.round(delay * SR);
    for (let n = d; n < N; n++) out[n] = inp[n - d] + out[n - d] * fb;
    return out;
  }
  const ecL = echo(DL, BEAT * 0.75, 0.42);
  const ecR = echo(DR, BEAT * 1.0, 0.42);
  // sidechain: the music bus ducks ~58% on every kick
  const duck = new Float32Array(N).fill(1);
  for (const kt of kicks) {
    const s0 = Math.round(kt * SR);
    for (let i = s0; i < Math.min(N, s0 + SR * 0.45); i++) duck[i] = Math.min(duck[i], 1 - 0.58 * Math.exp(-(i - s0) / (0.13 * SR)));
  }
  const rvL = reverb(SL, 0);
  const rvR = reverb(SRt, 23);

  // ── master: sum, soft clip, normalise, edge fades ──
  let peak = 0;
  for (let i = 0; i < N; i++) {
    L[i] = Math.tanh((L[i] + (ML[i] + ecL[i] * 0.7) * duck[i] + rvL[i]) * 0.42);
    R[i] = Math.tanh((R[i] + (MR[i] + ecR[i] * 0.7) * duck[i] + rvR[i]) * 0.42);
    peak = Math.max(peak, Math.abs(L[i]), Math.abs(R[i]));
  }
  const g = 0.89 / (peak || 1);
  for (let i = 0; i < N; i++) {
    const t = i / SR;
    const fade = Math.min(1, t / 0.01, (DURATION - t) / 0.6);
    L[i] *= g * fade;
    R[i] *= g * fade;
  }
  return { L, R, sampleRate: SR };
}

export function toWav({ L, R, sampleRate }) {
  const n = L.length;
  const buf = Buffer.alloc(44 + n * 4);
  buf.write('RIFF', 0);
  buf.writeUInt32LE(36 + n * 4, 4);
  buf.write('WAVEfmt ', 8);
  buf.writeUInt32LE(16, 16);
  buf.writeUInt16LE(1, 20);
  buf.writeUInt16LE(2, 22);
  buf.writeUInt32LE(sampleRate, 24);
  buf.writeUInt32LE(sampleRate * 4, 28);
  buf.writeUInt16LE(4, 32);
  buf.writeUInt16LE(16, 34);
  buf.write('data', 36);
  buf.writeUInt32LE(n * 4, 40);
  for (let i = 0; i < n; i++) {
    buf.writeInt16LE(Math.round(Math.max(-1, Math.min(1, L[i])) * 32767), 44 + i * 4);
    buf.writeInt16LE(Math.round(Math.max(-1, Math.min(1, R[i])) * 32767), 46 + i * 4);
  }
  return buf;
}
