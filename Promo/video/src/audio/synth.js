// Procedural soundtrack (no samples): ambient intro + typing, "tra-ta-ta" drop
// at 2.5s, 120 BPM groove, UI sounds locked to the same timeline as visuals.
import { T, DURATION, BEAT, CONFIG, typeTimes, beats } from '../timeline.js';

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
  const rand = rng(2026);
  const noise = () => rand() * 2 - 1;

  function add(t0, dur, voice, { gain = 1, pan = 0, send = 0 } = {}) {
    const s0 = Math.max(0, Math.round(t0 * SR));
    const s1 = Math.min(N, Math.round((t0 + dur) * SR));
    const gl = gain * Math.cos(((pan + 1) * Math.PI) / 4) * Math.SQRT2;
    const gr = gain * Math.sin(((pan + 1) * Math.PI) / 4) * Math.SQRT2;
    const st = {};
    for (let i = s0; i < s1; i++) {
      const v = voice((i - s0) / SR, st);
      L[i] += v * gl;
      R[i] += v * gr;
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

  const S1 = T.s1;
  const S2 = T.s2;
  const S3 = T.s3;
  // showcase chord bed: F → G → Am → F … every two bars, resolving to Am under the end titles
  const PROG = [[41, 45, 48, 52], [43, 47, 50, 55], [45, 48, 52, 57], [41, 45, 48, 52]];
  const CHORDS = [];
  for (let t = S3.hit, i = 0; t < S3.lockup[0] - 0.5; t += 2, i++) CHORDS.push([t, PROG[i % PROG.length]]);
  CHORDS.push([S3.lockup[0], [45, 48, 52, 57, 64]]);
  const chordAt = (t) => [...CHORDS].reverse().find(([t0]) => t >= t0)?.[1] || CHORDS[0][1];

  // ── scene 1: ambience + typing ─────────────────────────────
  add(0, S2.drop + 0.12, pad([note(45), note(48), note(52), note(57)], S2.drop + 0.12, 0.9, 0.12, 250, 1100), { gain: 0.3, send: 0.5 });
  add(0, S2.drop, (t) => Math.sin(TAU * 55 * t) * Math.min(1, t / 1.0) * 0.5, { gain: 0.25 });
  typeTimes().forEach((tt) => add(tt, 0.03, keyClick, { gain: 0.13 + 0.06 * rand(), pan: (rand() - 0.5) * 0.5 }));
  add(S1.enter, 0.4, kick(0.6, 12), { gain: 0.45 });
  add(S1.enter, 0.05, uiClick, { gain: 0.35, send: 0.3 });
  S1.lines.forEach((t, i) => i % 2 === 1 && add(t, 0.15, pop(900 + i * 70), { gain: 0.15, pan: 0.3 - i * 0.08, send: 0.4 }));
  S1.checks.slice(0, CONFIG.terminal?.todos?.length ?? 4).forEach((t, i) => add(t, 0.05, uiClick, { gain: 0.16, pan: -0.2 + i * 0.12, send: 0.3 }));
  add(S2.drop - 0.75, 0.75, riser(0.75, 160, 1500), { gain: 0.4, send: 0.4 });

  // ── scene 2: drop + groove (groove runs on through scene 3) ──
  add(S2.drop, 1.6, impact, { gain: 0.95, send: 0.5 });
  add(S2.drop, 1.6, crash, { gain: 0.22, send: 0.4 });
  add(S2.drop, 0.5, stab([note(45), note(52), note(57), note(60), note(64)], 0.18), { gain: 0.55, send: 0.45 });
  S2.tra.forEach((t, i) => {
    add(t, 0.35, snare(180 + i * 25, 3200 + i * 500), { gain: [0.85, 0.75, 1.0][i], pan: [-0.25, 0.25, 0][i], send: 0.35 });
    add(t, 0.25, clap, { gain: 0.35, send: 0.4 });
  });
  const gap = (b) => b > S2.roll[0] - 0.05 && b < S3.hit - 0.01; // break for the snare roll
  beats(S2.drop, S3.lockup[0]).forEach((b, i) => {
    if (gap(b)) return;
    const inS2 = b < S2.end;
    if (b > S2.drop + 0.01) add(b, 0.5, kick(1, 7), { gain: inS2 ? 0.95 : 0.8 });
    if (i % 2 === 1) add(b, 0.3, clap, { gain: inS2 ? 0.42 : 0.34, send: 0.35 });
    add(b + BEAT / 2, 0.2, hat(i % 4 === 3), { gain: 0.2, pan: 0.25 });
    add(b + BEAT / 4, 0.06, hat(), { gain: 0.07, pan: -0.3 });
    add(b + (3 * BEAT) / 4, 0.06, hat(), { gain: 0.09, pan: -0.3 });
    const roots = inS2 ? [45, 45, 41, 43] : Array(4).fill(chordAt(b)[0]);
    add(b + BEAT / 2, 0.3, bass(note(roots[i % 4] - 12), 0.18), { gain: inS2 ? 0.5 : 0.42 });
  });
  S2.headers.slice(1).forEach((t, i) => add(t - 0.1, 0.32, whoosh(0.32), { gain: 0.3, pan: i ? 0.5 : -0.5 }));
  [S2.hero, S2.heroImg, S2.strip, S2.cards[0]].forEach((t, i) => add(t - 0.06, 0.26, whoosh(0.26, false), { gain: 0.14, pan: [-0.5, 0.5, 0, 0][i] }));
  S2.badges.forEach((t, i) => add(t, 0.2, pop(620 + i * 110), { gain: 0.3, pan: [-0.6, 0.6, -0.6, 0.6][i], send: 0.35 }));
  add(S2.mega[0] - 0.05, 0.35, whoosh(0.35, false), { gain: 0.22, pan: 0.3 });
  add(S2.card[0] - 0.3, 0.45, whoosh(0.45), { gain: 0.42 });
  add(S2.impact, 1.0, impact, { gain: 0.55, send: 0.5 });
  add(S2.impact, 0.6, stab([note(41), note(48), note(53), note(57), note(64)], 0.22), { gain: 0.42, send: 0.5 });
  S2.roll.forEach((t, i) => add(t, 0.25, snare(200 + i * 18, 3500 + i * 300), { gain: 0.4 + i * 0.06, send: 0.3 }));
  add(S3.hit - 0.8, 0.8, riser(0.8, 300, 2400), { gain: 0.45, send: 0.4 });

  // ── scene 3: showcase ──────────────────────────────────────
  add(S3.hit, 1.4, impact, { gain: 0.7, send: 0.6 });
  add(S3.hit, 1.8, crash, { gain: 0.2, send: 0.5 });
  // chord bed for the showcase: F → G → Am → F, resolving to Am under the end titles
  CHORDS.forEach(([t0, notes], i) => {
    const t1 = i + 1 < CHORDS.length ? CHORDS[i + 1][0] + 0.05 : DURATION;
    const last = i === CHORDS.length - 1;
    add(t0, t1 - t0, pad(notes.map(note), t1 - t0, 0.08, last ? 0.8 : 0.15, 600 + i * 120, 1500 + i * 200), { gain: last ? 0.28 : 0.24, send: 0.55 });
  });
  S3.pins.forEach((t, i) => add(t, 0.2, pop(880 + i * 120), { gain: 0.22, pan: [-0.6, -0.6, 0.6][i], send: 0.4 }));
  add(S3.scroll[0], 0.6, whoosh(0.6), { gain: 0.12 });
  add(S3.modal[0] - 0.14, 0.36, whoosh(0.36), { gain: 0.28 });
  [S3.quickClick, S3.addClick, S3.closeClick].forEach((t) => add(t, 0.06, uiClick, { gain: 0.42, send: 0.2 }));
  [0, 1].forEach((i) => add(S3.addClick + 0.06 + i * 0.09, 0.8, bell(note(86 + i * 5)), { gain: 0.1, pan: 0.3, send: 0.6 }));
  add(S3.cart[0] - 0.05, 0.4, whoosh(0.4), { gain: 0.26, pan: 0.6 });
  add(S3.cartOut[0], 0.3, whoosh(0.3, false), { gain: 0.2, pan: 0.6 });
  add(S3.quickClick + 0.05, 0.9, bell(note(79)), { gain: 0.09, send: 0.6 });
  add(S3.modalOut[0], 0.3, whoosh(0.3, false), { gain: 0.2 });
  // Shop page + AJAX filter (short fill into the page change, crash on the downbeat)
  [0, 1, 2, 3].forEach((i) => add(S3.shopClick - 0.25 + i * 0.125, 0.22, snare(210 + i * 15, 3600), { gain: 0.32 + i * 0.06, send: 0.3 }));
  add(S3.nav[0] + 0.2, 1.4, crash, { gain: 0.14, send: 0.5 });
  add(S3.shopClick, 0.06, uiClick, { gain: 0.42, send: 0.2 });
  add(S3.nav[0], 0.45, whoosh(0.45), { gain: 0.24 });
  add(S3.scroll3[0], 0.7, whoosh(0.7), { gain: 0.11 });
  add(S3.filterClick, 0.06, uiClick, { gain: 0.42, send: 0.2 });
  add(S3.filter[0], 0.32, whoosh(0.32, false), { gain: 0.16, pan: 0.2 });
  add(S3.filter[1] - 0.05, 0.7, bell(note(83)), { gain: 0.09, pan: 0.2, send: 0.6 });
  // extra stores: new tab + page switch, crash on the downbeat
  S3.extra.forEach((e) => {
    add(e.tab[0], 0.06, uiClick, { gain: 0.3, pan: -0.3, send: 0.2 });
    add(e.switch[0] - 0.05, 0.45, whoosh(0.45), { gain: 0.3, pan: 0.3 });
    add(e.switch[0] + 0.2, 1.4, crash, { gain: 0.15, send: 0.5 });
    add(e.scroll[0], 0.8, whoosh(0.8), { gain: 0.11 });
  });
  add(S3.psi[0], 0.2, pop(760), { gain: 0.26, pan: -0.5, send: 0.4 });
  [0, 1, 2, 3].forEach((i) => add(S3.psi[1] - 0.3 + i * 0.1, 0.5, bell(note(84 + [0, 4, 7, 12][i])), { gain: 0.07, pan: -0.5 + i * 0.1, send: 0.6 }));
  add(S3.lockup[0], 0.7, kick(1.2, 5), { gain: 0.9 });
  add(S3.lockup[0], 0.6, impact, { gain: 0.4, send: 0.6 });
  [81, 84, 88, 93].forEach((m, i) => add(S3.lockup[0] + 0.06 + i * 0.08, 0.6, bell(note(m)), { gain: 0.09, send: 0.7 }));

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
  const rvL = reverb(SL, 0);
  const rvR = reverb(SRt, 23);

  // ── master: sum, soft clip, normalise to -1 dBFS, edge fades ──
  let peak = 0;
  for (let i = 0; i < N; i++) {
    L[i] = Math.tanh((L[i] + rvL[i]) * 0.9);
    R[i] = Math.tanh((R[i] + rvR[i]) * 0.9);
    peak = Math.max(peak, Math.abs(L[i]), Math.abs(R[i]));
  }
  const g = 0.89 / (peak || 1);
  for (let i = 0; i < N; i++) {
    const t = i / SR;
    const fade = Math.min(1, t / 0.01, (DURATION - t) / 0.4);
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
