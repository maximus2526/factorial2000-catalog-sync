// Single source of truth for timing, built from the project config and the
// captured site layouts. The browser scenes AND the Node audio synth call
// configure() with the same inputs, so every hit, click and keystroke lands
// on the same frame. Exports are live bindings: read them after configure().

export const FPS = 60;
export const WIDTH = 1920;
export const HEIGHT = 1080;
export const BPM = 120;
export const BEAT = 60 / BPM;
export const OFF = 1e6; // time for segments a project doesn't have (never reached)

export let CONFIG = null;
export let COMMAND = '';
export let DURATION = 0;
export let T = null;

const r2 = (x) => Math.round(x * 1000) / 1000;
const off2 = [OFF, OFF + 1];

/**
 * @param {object} config   project config (see promo.config.json)
 * @param {object[]} sites  captured layout.json per site (sites[0] = main store)
 */
export function configure(config, sites = []) {
  CONFIG = config;
  COMMAND = config.prompt;

  // typing speed stays constant (~60 chars/s); a longer prompt shifts everything after it
  const typeDur = Math.min(2.2, Math.max(0.7, COMMAND.length / 60));
  const d = typeDur - 1.02;
  const s1t = (x) => r2(x + (x >= 1.52 ? d : 0));
  const s1 = {
    start: 0,
    termIn: [0, 0.5],
    typeStart: 0.5,
    typeEnd: s1t(1.52),
    enter: s1t(1.62),
    spinner: s1t(1.7),
    lines: [1.76, 1.9, 1.98, 2.08, 2.16, 2.26, 2.34, 2.46].map(s1t),
    todos: [2.5, 2.54, 2.58, 2.62].map(s1t),
    checks: [2.66, 2.74, 2.82, 2.9].map(s1t),
    exit: [2.95, 3.32].map(s1t),
  };
  s1.end = s1t(3.2);

  // scene 2: fixed choreography relative to the drop
  const D = s1.end;
  const s2t = (x) => r2(D + (x - 3.2));
  const s2 = {
    start: D,
    end: s2t(7.2),
    drop: D,
    tra: [3.2, 3.325, 3.45].map(s2t),
    headers: [3.2, 3.45, 3.7].map(s2t),
    hero: s2t(3.95),
    heroImg: s2t(4.05),
    strip: s2t(4.2),
    cards: [4.25, 4.35, 4.45, 4.55].map(s2t),
    badges: [4.45, 4.7, 4.95, 5.2].map(s2t),
    mega: [4.7, 5.55].map(s2t),
    card: [5.7, 6.85].map(s2t),
    impact: s2t(5.7),
    roll: [6.7, 6.825, 6.9, 6.95, 7.0, 7.05, 7.1, 7.15].map(s2t),
    outro: [6.8, 7.25].map(s2t),
  };

  // scene 3: a sequence of optional segments, depending on what the capture found
  const H = s2.end;
  const main = sites[0] || {};
  const s3 = {
    start: r2(H - 0.1),
    hit: H,
    enter: [H - 0.1, H + 1.1].map(r2),
    sweep: [H + 0.5, H + 1.4].map(r2),
    glare: [H + 0.2, H + 1.4].map(r2),
    stickySweep: [H + 1.5, H + 2.3].map(r2),
    scroll: [H + 0.7, H + 1.95].map(r2),
    cursorIn: r2(H + 1.4),
    pins: [H + 0.8, H + 1.15, OFF].map(r2),
  };
  let c = H + 2.0;
  const hasFlow = !!(main.hover && main.quickview && main.sidecart);
  Object.assign(s3, {
    hover: hasFlow || main.hover ? r2(c) : OFF,
    quickClick: hasFlow ? r2(c + 0.4) : OFF,
    modal: hasFlow ? [c + 0.42, c + 0.85].map(r2) : off2,
    addClick: hasFlow ? r2(c + 1.2) : OFF,
    modalOut: hasFlow ? [c + 1.24, c + 1.44].map(r2) : off2,
    cart: hasFlow ? [c + 1.28, c + 1.72].map(r2) : off2,
    closeClick: hasFlow ? r2(c + 2.0) : OFF,
    cartOut: hasFlow ? [c + 2.02, c + 2.3].map(r2) : off2,
  });
  c += hasFlow ? 2.75 : main.hover ? 0.9 : 0.2;

  const hasShop = !!(main.shop && main.shopNav);
  const hasFilter = hasShop && !!main.shop.filter && !!main.shop.filteredHeight;
  Object.assign(s3, {
    shopClick: hasShop ? r2(c) : OFF,
    nav: hasShop ? [c + 0.05, c + 0.45].map(r2) : off2,
    scroll3: hasShop ? [c + 0.5, c + 1.25].map(r2) : off2,
    filterClick: hasFilter ? r2(c + 1.55) : OFF,
    filter: hasFilter ? [c + 1.6, c + 1.95].map(r2) : off2,
  });
  c += hasShop ? (hasFilter ? 2.0 : 1.4) : 0;

  // each extra store opens in its own browser tab
  s3.extra = sites.slice(1).map(() => {
    const seg = { tab: [c, c + 0.25].map(r2), switch: [c + 0.05, c + 0.45].map(r2), scroll: [c + 0.6, c + 1.7].map(r2) };
    c += 1.85;
    return seg;
  });

  const psiOn = config.pagespeed?.enabled !== false;
  s3.pins[2] = config.pins?.mobile === false ? OFF : r2(c - 0.05);
  s3.psi = psiOn ? [c, c + 0.75].map(r2) : off2;
  if (psiOn) c += 0.9;
  // end titles land on the beat grid
  const L = r2(D + Math.ceil((c - D) / BEAT - 1e-6) * BEAT);
  s3.lockup = [L, r2(L + 1)];
  s3.end = r2(L + 1);
  s3.cursorOut = s3.extra[0]?.tab[0] ?? (psiOn ? s3.psi[0] : L);

  T = { s1, s2, s3 };
  DURATION = s3.end;
  return { T, DURATION };
}

// Deterministic keystroke times (seeded jitter) for the typing animation and
// the matching key-click sounds.
export function typeTimes() {
  const n = COMMAND.length;
  const { typeStart, typeEnd } = T.s1;
  let seed = 1337;
  const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
  const w = [];
  for (let i = 0; i < n; i++) w.push(0.55 + rnd() * 0.9 + (COMMAND[i] === ' ' ? 0.6 : 0));
  const sum = w.reduce((a, b) => a + b, 0);
  const out = [];
  let acc = typeStart;
  for (let i = 0; i < n; i++) {
    acc += (w[i] / sum) * (typeEnd - typeStart);
    out.push(acc);
  }
  return out;
}

// Every downbeat from the drop to the end titles (scene pulses, kicks).
export function beats(from = T.s2.drop, to = T.s3.lockup[0]) {
  const out = [];
  for (let t = from; t < to - 1e-6; t += BEAT) out.push(+t.toFixed(4));
  return out;
}
