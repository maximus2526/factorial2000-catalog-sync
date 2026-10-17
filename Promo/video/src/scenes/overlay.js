// Global finishing layers: transition flashes, vignette, animated film grain.
import { T } from '../timeline.js';
import { el, css, decay, rng } from './lib.js';

export function build(stage) {
  const flash = el('div', 'fx-flash', null, stage);
  el('div', 'fx-vignette', null, stage);
  const grain = el('div', 'fx-grain', null, stage);

  const c = document.createElement('canvas');
  c.width = c.height = 256;
  const ctx = c.getContext('2d');
  const img = ctx.createImageData(256, 256);
  const r = rng(42);
  for (let i = 0; i < img.data.length; i += 4) {
    const v = Math.floor(r() * 255);
    img.data[i] = img.data[i + 1] = img.data[i + 2] = v;
    img.data[i + 3] = 255;
  }
  ctx.putImageData(img, 0, 0);
  grain.style.backgroundImage = `url(${c.toDataURL()})`;

  return {
    update(t) {
      const f = 0.8 * decay(t, T.s2.drop, 6) + 0.3 * decay(t, T.s3.hit, 6);
      css(flash, { opacity: String(Math.min(1, f)) });
      const fr = Math.floor(t * 60);
      const rr = rng(fr + 1);
      css(grain, { backgroundPosition: `${Math.floor(rr() * 256)}px ${Math.floor(rr() * 256)}px` });
    },
  };
}
