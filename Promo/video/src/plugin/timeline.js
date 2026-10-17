// Timeline for the Factorial2000 Catalog Sync promo. One source of truth for the
// scenes (browser) and the soundtrack (Node): every act lands on the 120 BPM grid.
export const FPS = 60;
export const WIDTH = 1920;
export const HEIGHT = 1080;
export const BEAT = 0.5;

export const ACTS = {
  intro: [0, 4],
  import: [4, 10],
  sync: [10, 16],
  board: [16, 23],
  market: [23, 30],
  pro3: [30, 37],
  outro: [37, 44],
};
export const DURATION = 44;

// bullets/items appear this many seconds after their act starts
export const bulletAt = (i) => 1.0 + i * 0.7;
export const BOARD = { free: 1.2, pro: 2.2, step: 0.32 };

export const beatsBetween = (a, b) => {
  const out = [];
  for (let t = a; t < b - 1e-6; t += BEAT) out.push(+t.toFixed(3));
  return out;
};
