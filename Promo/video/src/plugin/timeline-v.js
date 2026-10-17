// Timeline for the vertical Reels / Shorts cut (1080x1920, 32 s, 120 BPM grid).
export const FPS = 60;
export const WIDTH = 1080;
export const HEIGHT = 1920;
export const BEAT = 0.5;

export const ACTS = {
  intro: [0, 4],
  import: [4, 9],
  sync: [9, 14],
  board: [14, 20],
  market: [20, 26],
  outro: [26, 32],
};
export const DURATION = 32;
export const TITLE_AT = 2.0; // seconds into the intro when the title lands

export const bulletAt = (i) => 0.9 + i * 0.6;
export const BOARD = { free: 1.0, pro: 2.2, step: 0.3 };

export const beatsBetween = (a, b) => {
  const out = [];
  for (let t = a; t < b - 1e-6; t += BEAT) out.push(+t.toFixed(3));
  return out;
};
