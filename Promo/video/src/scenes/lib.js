// Pure helpers: every visual is a function of time t (seconds), no CSS
// transitions or rAF timing, so any frame can be rendered in any order.

export const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
export const lerp = (a, b, k) => a + (b - a) * k;
export const prog = (t, a, b) => clamp((t - a) / (b - a));

export const ease = {
  linear: (x) => x,
  inCubic: (x) => x * x * x,
  outCubic: (x) => 1 - Math.pow(1 - x, 3),
  inOutCubic: (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2),
  outQuart: (x) => 1 - Math.pow(1 - x, 4),
  inOutSine: (x) => -(Math.cos(Math.PI * x) - 1) / 2,
  outExpo: (x) => (x >= 1 ? 1 : 1 - Math.pow(2, -10 * x)),
  inExpo: (x) => (x <= 0 ? 0 : Math.pow(2, 10 * x - 10)),
  inOutExpo: (x) =>
    x <= 0 ? 0 : x >= 1 ? 1 : x < 0.5 ? Math.pow(2, 20 * x - 10) / 2 : (2 - Math.pow(2, -20 * x + 10)) / 2,
  outBack: (x, s = 1.70158) => 1 + (s + 1) * Math.pow(x - 1, 3) + s * Math.pow(x - 1, 2),
};

// Damped spring from 0 to 1; dt = seconds since start.
export const spring = (dt, k = 9, w = 18) => (dt <= 0 ? 0 : 1 - Math.exp(-k * dt) * Math.cos(w * dt));

// Decaying impulse, e.g. flashes and shakes after a hit.
export const decay = (t, at, k = 10) => (t < at ? 0 : Math.exp(-(t - at) * k));
export const hits = (t, list, k = 10) => list.reduce((s, h) => s + decay(t, h, k), 0);

// Keyframed 2D path with eased segments: [[t, x, y], ...]
export function path(t, keys, fn = ease.inOutCubic) {
  if (t <= keys[0][0]) return [keys[0][1], keys[0][2]];
  for (let i = 1; i < keys.length; i++) {
    const [t1, x1, y1] = keys[i];
    if (t <= t1) {
      const [t0, x0, y0] = keys[i - 1];
      const k = fn(prog(t, t0, t1));
      return [lerp(x0, x1, k), lerp(y0, y1, k)];
    }
  }
  const last = keys[keys.length - 1];
  return [last[1], last[2]];
}

export function rng(seed) {
  return () => {
    seed |= 0;
    seed = (seed + 0x6d2b79f5) | 0;
    let r = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r;
    return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
  };
}

export function el(tag, cls, html, parent) {
  const n = document.createElement(tag);
  if (cls) n.className = cls;
  if (html != null) n.innerHTML = html;
  if (parent) parent.appendChild(n);
  return n;
}

// Style setter that skips unchanged values (cheap per-frame updates).
export function css(node, props) {
  const cache = (node.__css ||= {});
  for (const k in props) {
    const v = props[k];
    if (cache[k] !== v) {
      cache[k] = v;
      if (k.startsWith('--')) node.style.setProperty(k, v);
      else node.style[k] = v;
    }
  }
}

export const show = (node, on) => css(node, { display: on ? '' : 'none' });

// Offset of `node` inside `ancestor`, ignoring CSS transforms.
export function offsetIn(node, ancestor) {
  let x = 0;
  let y = 0;
  let n = node;
  while (n && n !== ancestor) {
    x += n.offsetLeft;
    y += n.offsetTop;
    n = n.offsetParent;
  }
  return { x, y, w: node.offsetWidth, h: node.offsetHeight };
}

export const $ = (root, sel) => root.querySelector(sel);
export const $$ = (root, sel) => [...root.querySelectorAll(sel)];
