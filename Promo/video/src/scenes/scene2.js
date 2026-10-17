// Scene 2 (3.2–7.2s): beat drop, the captured site's design system
// assembling in 3D from real crops (header, hero, products, mega menu).
import { T, CONFIG, beats } from '../timeline.js';
import { el, css, show, prog, ease, lerp, spring, decay, hits, rng, $, $$ } from './lib.js';

const ICONS = {
  bolt: '<svg viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7z" fill="currentColor"/></svg>',
  menu: '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2" opacity=".5"/></svg>',
  eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
  heart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/></svg>',
};

// up to four feature badges around the assembly (labels come from the config)
const BADGE_SLOTS = [
  { icon: 'bolt', x: 120, y: 250 },
  { icon: 'menu', x: 1470, y: 205 },
  { icon: 'eye', x: 100, y: 760 },
  { icon: 'heart', x: 1440, y: 735 },
];
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');

const PLATE = { w: 960, h: 930, pad: 21 };

// Pose shared with scene 3's opening frame: page scale 0.6, same tilt.
export const HANDOFF_POSE = { scale: 0.6, rx: 10, ry: -16 };
// Rig transform that puts the page top/scale where the scene-3 browser page starts
// (browser 1480×880 centred, page begins 52px under its top edge).
const HANDOFF = (K) => {
  const rigScale = HANDOFF_POSE.scale / K;
  const browserPageTop = 540 + (-440 + 52) * HANDOFF_POSE.scale;
  const rigPageTop = 540 + (16 - PLATE.h / 2) * rigScale;
  return { scale: rigScale, y: browserPageTop - rigPageTop, rx: HANDOFF_POSE.rx, ry: HANDOFF_POSE.ry };
};

const SKELETON = (dark, label) =>
  `<div class="hd ${dark ? 'hd-b' : 'hd-a'}"><b class="logo serif">${esc(label)}<i>.</i></b><nav><span class="on">Shop</span><span>Plants</span><span>Pots</span><span>Story</span><span>Care</span></nav><div class="ic"><i></i><i></i><i></i></div></div>`;

export function build(stage, site) {
  const K = (PLATE.w - PLATE.pad * 2) / site.width; // page → plate scale
  const img = (name) => `url(${site.base}${name}.png)`;
  const label = site.label || 'Store';
  const brand = CONFIG.brand?.name || '';
  const BADGES = (CONFIG.features || []).slice(0, 4).map((l, i) => ({ ...BADGE_SLOTS[i], label: l }));
  const words = CONFIG.marquee?.length ? CONFIG.marquee : (CONFIG.features || []).map((f) => f.toUpperCase());
  const half = Math.ceil(words.length / 2);
  const mrow = (w) => `${w.join(' · ')} · `.repeat(Math.max(2, Math.ceil(12 / Math.max(1, w.length))));
  const head = CONFIG.headline || {};
  // a piece showing a crop of the full page screenshot
  const crop = (parent, r, at) => {
    const n = el('div', 'asm-piece crop', null, parent);
    css(n, {
      left: `${at.x}px`, top: `${at.y}px`, width: `${r.w * K}px`, height: `${r.h * K}px`,
      backgroundImage: img('page'), backgroundSize: `${site.width * K}px auto`,
      backgroundPosition: `${-r.x * K}px ${-r.y * K}px`,
    });
    return n;
  };
  const place = (r) => ({ x: PLATE.pad + r.x * K, y: 16 + r.y * K });

  const root = el('div', 'scene s2', null, stage);
  const bg = el('div', 's2-bg', null, root);
  const marquee = el(
    'div',
    's2-marquee',
    `<div class="row">${esc(mrow(words.slice(0, half)))}</div>
     <div class="row">${esc(mrow(words.slice(half).length ? words.slice(half) : words))}</div>`,
    root,
  );
  const rows = $$(marquee, '.row');
  const shaker = el('div', 's2-shaker', null, root);

  // particles: two seeded bursts
  const pwrap = el('div', 's2-particles', null, shaker);
  const parts = [];
  [
    [T.s2.drop, 90, 7],
    [T.s2.impact, 60, 99],
  ].forEach(([at, count, seed]) => {
    const r = rng(seed);
    for (let i = 0; i < count; i++) {
      const a = r() * Math.PI * 2;
      const sp = 400 + r() * 1300;
      const size = 3 + r() * 9;
      const col = ['#83b735', '#ffffff', '#c9a96e', '#83b735'][Math.floor(r() * 4)];
      const n = el('i', null, null, pwrap);
      css(n, { width: `${size}px`, height: `${size * (r() < 0.3 ? 4 : 1)}px`, background: col });
      parts.push({ n, at, vx: Math.cos(a) * sp, vy: Math.sin(a) * sp * 0.75, rot: r() * 540 - 270, life: 0.9 + r() * 0.8 });
    }
  });

  // 3D assembly built from the captured site
  const world = el('div', 's2-world', null, shaker);
  const rig = el('div', 's2-rig', null, world);
  css(rig, { width: `${PLATE.w}px`, height: `${PLATE.h}px`, left: `${(1920 - PLATE.w) / 2}px`, top: `${(1080 - PLATE.h) / 2}px` });
  const plate = el('div', 'asm-plate', '<div class="grid"></div>', rig);
  const slotLabel = el('div', 'asm-slotlabel', '', rig);

  const hdr = site.header || { x: 0, y: 0, w: site.width, h: 120 };
  const hdrAt = place(hdr);
  const names = ['Preset · Minimal', 'Preset · Dark', `Preset · ${label}`];
  const headers = [SKELETON(false, label), SKELETON(true, label)].map((h) => {
    const n = el('div', 'asm-piece asm-skel', h, rig);
    css(n, { left: `${hdrAt.x}px`, top: `${hdrAt.y}px`, width: `${hdr.w * K}px`, height: `${hdr.h * K}px` });
    return n;
  });
  headers.push(crop(rig, hdr, hdrAt));

  // hero: split into the two halves of the hero card so they can fly separately
  // generic fallbacks for sites whose page isn't built from top-level sections
  const hh = site.header?.h || 120;
  const hs = site.sections?.[0] || { x: 0, y: hh, w: site.width, h: 700 };
  const heroL = { x: hs.x + 16, y: hs.y + 16, w: hs.w / 2 - 16, h: hs.h - 32 };
  const heroR = { x: hs.x + hs.w / 2, y: hs.y + 16, w: hs.w / 2 - 16, h: hs.h - 32 };
  const hero = crop(rig, heroL, place(heroL));
  const heroImg = crop(rig, heroR, place(heroR));
  const stripR = site.sections?.[1] || { x: 0, y: hs.y + hs.h, w: site.width, h: 80 };
  const strip = crop(rig, stripR, place(stripR));

  const row = site.cards.slice(0, 4);
  const rowY = place(stripR).y + stripR.h * K + 14;
  const cards = row.map((c) => crop(rig, c, { x: PLATE.pad + c.x * K, y: rowY }));
  // real hover state of the target product, revealed as "Quick Shop"
  const tc = site.cards[site.target];
  const hoverPiece = el('div', 'asm-hover', null, cards[site.target] || cards[0] || rig);
  if (site.hover && tc) {
    css(hoverPiece, {
      backgroundImage: img('hover'), backgroundSize: `${site.hover.w * K}px auto`,
      backgroundPosition: `${(site.hover.x - tc.x) * K}px ${(site.hover.y - tc.y) * K}px`,
    });
  }

  let mega = null;
  if (site.menu) {
    mega = el('div', 'asm-piece asm-mega-img', null, rig);
    css(mega, {
      left: `${PLATE.pad + site.menu.x * K}px`, top: `${16 + site.menu.y * K}px`,
      width: `${site.menu.w * K}px`, height: `${site.menu.h * K}px`,
      backgroundImage: img('menu'), backgroundSize: '100% 100%',
    });
  }

  const dim = el('div', 's2-dim', null, shaker);
  const badges = BADGES.map((b) =>
    el('div', 'badge', `<span class="bi">${ICONS[b.icon]}</span><span><small>${esc(brand.toUpperCase())} FEATURE</small>${esc(b.label)}</span>`, shaker),
  );
  badges.forEach((b, i) => css(b, { left: `${BADGES[i].x}px`, top: `${BADGES[i].y}px` }));

  const cardWrap = el('div', 's2-cardwrap', null, shaker);
  const card = el(
    'div',
    's2-card',
    `<div class="streak"></div>
     <div class="brand"><b>${esc(brand.toUpperCase())}</b><i></i><span>${esc(head.kicker || '')}</span></div>
     ${(head.lines || []).slice(0, 2).map((l, i) => `<div class="l${i ? ' l2' : ''}"><span>${esc(l)}</span></div>`).join('')}
     <div class="stars">${head.stars === false ? '' : '★★★★★'}</div>`,
    cardWrap,
  );
  const cardLines = $$(card, '.l span');
  const stars = $(card, '.stars');
  const streak = $(card, '.streak');

  // piece animation: glide from an offset to rest (smooth quartic ease)
  function fly(node, t, at, from, dur = 0.65, z = 0) {
    const k = ease.outQuart(prog(t, at, at + dur));
    const o = ease.outCubic(prog(t, at, at + 0.18));
    const [x, y, zz, rx, ry, rz] = from;
    css(node, {
      opacity: String(o),
      transform: `translate3d(${(1 - k) * x}px,${(1 - k) * y}px,${z + (1 - k) * zz}px) rotateX(${(1 - k) * rx}deg) rotateY(${(1 - k) * ry}deg) rotateZ(${(1 - k) * rz}deg)`,
    });
  }

  return {
    update(t) {
      const s = T.s2;
      const on = t > s.drop - 0.15 && t < s.outro[1] + 0.02;
      show(root, on);
      if (!on) return;

      const flash = hits(t, s.tra, 7) + 1.2 * decay(t, s.impact, 5);
      css(bg, { '--glow': String(Math.min(1, 0.35 + 0.45 * flash)) });
      css(root, { opacity: String(ease.inOutSine(prog(t, s.drop - 0.15, s.drop))) });
      rows.forEach((r, i) => css(r, { transform: `translateX(${(i ? -1600 : 0) + (i ? 1 : -1) * (t - s.drop) * 300}px)` }));

      // gentle camera shake on hits
      const sh = s.tra.concat([s.impact]).reduce((acc, h) => acc + (t > h ? Math.exp(-(t - h) * 12) * Math.sin((t - h) * 70) : 0), 0);
      css(shaker, { transform: `translate(${sh * 5}px,${sh * -3.5}px) rotate(${sh * 0.12}deg)` });

      parts.forEach((p) => {
        const dt = t - p.at;
        if (dt < 0 || dt > p.life) return css(p.n, { opacity: '0' });
        const k = (1 - Math.exp(-dt * 2.6)) / 2.6;
        css(p.n, {
          opacity: String(1 - ease.inCubic(dt / p.life)),
          transform: `translate(${960 + p.vx * k}px,${540 + p.vy * k}px) rotate(${p.rot * dt}deg)`,
        });
      });

      // rig: slow orbit + soft beat pulse, then glides into the exact pose the
      // scene-3 browser starts from, so the assembled page *becomes* the browser
      const orbit = ease.inOutSine(prog(t, s.drop, s.outro[0]));
      const beat = hits(t, beats(s.drop + 0.5, s.end).filter((b) => b < s.card[0] || b > s.card[1] - 0.2), 9) * 0.008;
      const out = ease.inOutCubic(prog(t, ...s.outro));
      const H = HANDOFF(K);
      const rot = [lerp(22, 8, orbit), lerp(-26, 8, orbit), lerp(-3, 0, orbit)];
      const sc = (0.8 + beat) * (1 + 0.04 * ease.outCubic(prog(t, s.drop, s.drop + 0.8)));
      css(rig, {
        transform: `translateY(${lerp(0, H.y, out)}px) rotateX(${lerp(rot[0], H.rx, out)}deg) rotateY(${lerp(rot[1], H.ry, out)}deg) rotateZ(${lerp(rot[2], 0, out)}deg) scale(${lerp(sc, H.scale, out)})`,
      });
      css(world, { opacity: String(1 - prog(t, s.outro[1] - 0.12, s.outro[1])) });
      css(plate, { opacity: String(prog(t, s.drop, s.drop + 0.25) * (1 - out)) });

      // header presets cycle into the slot; the last is the captured header
      headers.forEach((h, i) => {
        const at = s.headers[i];
        const next = s.headers[i + 1];
        fly(h, t, at, [700, -460, 480, -45, 30, 10], 0.32, 30);
        if (next != null && t > next) {
          const k = ease.inOutCubic(prog(t, next, next + 0.28));
          css(h, {
            opacity: String(1 - k),
            transform: `translate3d(${-820 * k}px,${140 * k}px,${30 - 280 * k}px) rotateY(${-40 * k}deg)`,
          });
        }
      });
      const hi = s.headers.filter((h) => t >= h).length - 1;
      const lbl = hi >= 0 ? `HEADER BUILDER <i></i> ${names[hi]}` : '';
      if (slotLabel.__l !== lbl) slotLabel.innerHTML = slotLabel.__l = lbl;
      css(slotLabel, { opacity: String(prog(t, s.drop, s.drop + 0.15) * (1 - prog(t, s.drop + 1.1, s.drop + 1.4))) });

      fly(hero, t, s.hero, [-900, 40, 300, 0, 70, 0], 0.7, 18);
      fly(heroImg, t, s.heroImg, [900, -40, 300, 0, -70, 0], 0.7, 22);
      fly(strip, t, s.strip, [0, 260, 380, 70, 0, 0], 0.6, 14);
      cards.forEach((c, i) => {
        fly(c, t, s.cards[i], [0, 140, -800, -70, 18 * (i - 1.5), 0], 0.7, 28 + i * 3);
        css(c, { opacity: String(ease.outCubic(prog(t, s.cards[i], s.cards[i] + 0.18)) * (1 - out)) });
      });

      // features light up as their badges pop
      css(hoverPiece, { opacity: String(ease.inOutSine(prog(t, s.badges[0], s.badges[0] + 0.3))) });
      if (mega) {
        const [m0, m1] = s.mega;
        const mk = ease.outQuart(prog(t, m0, m0 + 0.45)) * (1 - ease.inOutCubic(prog(t, m1 - 0.3, m1)));
        css(mega, { opacity: String(Math.min(1, mk * 1.6)), transform: `translateZ(${60 + 30 * mk}px) scaleY(${0.2 + 0.8 * mk})` });
      }

      badges.forEach((b, i) => {
        const at = s.badges[i];
        const sp = spring(t - at, 7, 12);
        const leave = ease.inOutCubic(prog(t, s.card[1] - 0.25, s.card[1] + 0.05));
        const fl = Math.sin(t * 2 + i * 1.7) * 5;
        css(b, {
          opacity: String(prog(t, at, at + 0.1) * (1 - leave)),
          transform: `translateY(${fl - leave * 30}px) scale(${Math.max(0, sp)}) rotate(${(1 - Math.min(1, sp)) * -8}deg)`,
        });
      });

      // typography card
      const [c0, c1] = s.card;
      const cin = ease.outQuart(prog(t, c0, c0 + 0.55));
      const cout = ease.inCubic(prog(t, c1 - 0.25, c1 + 0.05));
      const drift = prog(t, c0, c1);
      css(dim, { opacity: String(0.6 * cin * (1 - cout)) });
      css(card, {
        opacity: String(prog(t, c0, c0 + 0.08) * (1 - prog(t, c1 - 0.05, c1 + 0.05))),
        transform: `translate3d(${(1 - cin) * 1400 - cout * 1500}px,0,${(1 - cin) * -450}px) rotateY(${(1 - cin) * -45 + drift * 5 + cout * 35}deg) rotateX(${3 - drift * 3}deg) scale(${1 + drift * 0.035})`,
      });
      cardLines.forEach((sp, i) => {
        const k = ease.outQuart(prog(t, c0 + 0.12 + i * 0.1, c0 + 0.6 + i * 0.1));
        css(sp, { transform: `translateY(${(1 - k) * 110}%)` });
      });
      css(stars, { opacity: String(prog(t, c0 + 0.35, c0 + 0.55)), letterSpacing: `${lerp(0.6, 0.18, ease.outCubic(prog(t, c0 + 0.35, c0 + 0.8)))}em` });
      css(streak, { transform: `translateX(${lerp(-120, 220, ease.inOutCubic(prog(t, c0 + 0.15, c0 + 0.85)))}%) skewX(-20deg)` });
    },
  };
}
