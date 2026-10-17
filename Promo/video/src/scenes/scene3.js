// Scene 3: the captured stores in a 3D browser. One continuous browsing
// session: scroll → hover → quick view → add to cart → side cart → "Shop" →
// shop page → AJAX filter → extra stores in new tabs → PageSpeed → titles.
// Every segment is optional: timeline.js only schedules what the capture found.
import { T, CONFIG, OFF } from '../timeline.js';
import { el, css, show, prog, ease, lerp, spring, path, $ } from './lib.js';
import { HANDOFF_POSE } from './scene2.js';

const PIN_POS = [
  { key: 'designSystem', x: 70, y: 150, tx: 560, ty: 300 },
  { key: 'pageLoad', x: 90, y: 780, tx: 560, ty: 720 },
  { key: 'mobile', x: 1310, y: 120, tx: 1420, ty: 300 },
];

const CURSOR =
  '<svg viewBox="0 0 28 28"><path d="M5 3l17 9.5-7.4 1.8 4.6 8.2-3.3 1.8-4.6-8.2L6 21z" fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';

const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
const host = (u) => String(u || '').replace(/^https?:\/\//, '').replace(/\/$/, '');
const active = (t, seg) => seg && seg[0] < OFF && t >= seg[0];

// "0.2s" counts down from 3.4s, "100%" counts up from 0, anything else is static
function counter(value, k) {
  const m = /^([\d.]+)\s*(s|%)$/.exec(String(value || ''));
  if (!m) return String(value || '');
  const v = +m[1];
  return m[2] === 's' ? `${lerp(Math.max(3.4, v), v, k).toFixed(1)}s` : `${Math.round(v * k)}%`;
}

export function build(stage, site) {
  const VW = site.viewport.w;
  const VH = site.viewport.h;
  const extras = site.extras || [];
  const tabSuffix = CONFIG.brand?.tabSuffix || CONFIG.brand?.name || '';
  const img = (name, base = site.base) => `url(${base}${name}.png)`;
  const tc = site.cards?.[site.target] || site.cards?.[0];
  const S1 = site.flowScroll ?? (tc ? Math.max(0, tc.y - 260) : Math.min(1200, site.height - VH)); // where the flow was captured
  const shop = site.shop;
  const SF = shop?.filter;
  const S3 = shop ? Math.min(shop.height - VH, Math.max(0, (SF ? SF.y : 700) - 430)) : 0; // filters + grid in view
  const shopPath = shop ? new URL(shop.url).pathname : '/shop/';
  const box = (n, r) => r && css(n, { left: `${r.x}px`, top: `${r.y}px`, width: `${r.w}px`, height: `${r.h}px` });

  const root = el('div', 'scene s3', null, stage);
  el('div', 's3-bg', '<i class="l1"></i><i class="l2"></i><i class="floor"></i>', root);
  const cam = el('div', 's3-cam', null, root);
  const shadow = el('div', 's3-shadow', null, cam);
  const tabs = [site, ...extras].map((s, i) => `<div class="tab${i ? ' tx' : ''}">${esc(s.label)}${tabSuffix ? ` — ${esc(tabSuffix)}` : ''}</div>`).join('');
  const browser = el(
    'div',
    'browser',
    `<div class="bar"><i></i><i></i><i></i>${tabs}<div class="url"><span>🔒</span><em>${esc(host(site.url))}</em></div></div>`,
    cam,
  );
  const chrome = $(browser, '.bar');
  const tabEls = [...browser.querySelectorAll('.tab')];
  const urlText = $(browser, '.url em');
  const vp = el('div', 'st-viewport', null, browser);

  // main store home page + its real hover state
  const page = el('div', 'site-page', null, vp);
  css(page, { height: `${site.height}px`, backgroundImage: img('page') });
  const hover = el('div', 'site-hover', null, page);
  if (site.hover) {
    box(hover, site.hover);
    css(hover, { backgroundImage: img('hover') });
  }
  const qvRing = el('div', 'qv-ring', null, page);
  const qv = site.hover?.qv || tc?.qv;
  if (qv) css(qvRing, { left: `${qv.x + qv.w / 2}px`, top: `${qv.y + qv.h / 2}px` });
  const sweep = el('div', 'sweep', null, page);
  css(sweep, { height: `${site.header?.h || 120}px` });

  // shop page (and the same page after the AJAX filter)
  const shopPage = el('div', 'site-page', null, vp);
  const shopFiltered = el('div', 'site-page', null, shopPage);
  css(shopPage, { opacity: '0' });
  if (shop) {
    css(shopPage, { height: `${shop.height}px`, backgroundImage: img('shop') });
    if (shop.filteredHeight) css(shopFiltered, { height: `${shop.filteredHeight}px`, backgroundImage: img('shop-filtered'), opacity: '0' });
  }
  const loader = el('div', 'grid-loader', '<i></i>', shopPage);
  if (shop?.grid) box(loader, { ...shop.grid, h: Math.min(shop.grid.h, 700) });

  // extra stores, each opened in its own tab
  const extraPages = extras.map((x) => {
    const p = el('div', 'site-page', null, vp);
    const sw = el('div', 'sweep', null, p);
    css(p, { height: `${x.height}px`, backgroundImage: img('page', x.base), opacity: '0' });
    css(sw, { height: `${x.header?.h || 140}px` });
    const target = Math.min(x.height - VH, Math.max(0, (x.cards?.[0]?.y ?? 1500) - 230));
    return { p, sw, target, x };
  });

  const sticky = el('div', 'site-sticky', '<div class="sweep"></div>', vp);
  css(sticky, { height: `${site.sticky?.h || 72}px`, backgroundImage: site.sticky ? img('sticky') : 'none' });
  const stickySweep = $(sticky, '.sweep');
  const stickyCart = el('div', 'site-sticky-cart', null, sticky);
  if (site.stickyCart) css(stickyCart, { backgroundImage: img('sticky-cart') });

  // captured quick view popup + side cart, placed exactly where the site draws them
  const overlay = el('div', 'st-overlay', null, vp);
  const Q = site.quickview;
  const C = site.sidecart;
  const modal = el('div', 'qv-modal', null, vp);
  const addPress = el('div', 'qv-add-press', null, modal);
  const qvX = el('div', 'qv-x', null, vp);
  const sideCart = el('div', 'side-cart', null, vp);
  if (Q && C) {
    box(modal, Q);
    css(modal, { backgroundImage: img('quickview') });
    if (Q.add) box(addPress, { ...Q.add, x: Q.add.x - Q.x, y: Q.add.y - Q.y });
    if (Q.close) box(qvX, Q.close);
    box(sideCart, C);
    css(sideCart, { backgroundImage: img('sidecart') });
  }

  const glare = el('div', 'browser-glare', null, browser);
  const cursor = el('div', 'cursor', CURSOR, vp);
  const ripple = el('div', 'ripple', null, vp);

  // HUD pins (copy from the config; a pin set to false is skipped)
  const svgNS = 'http://www.w3.org/2000/svg';
  const lines = document.createElementNS(svgNS, 'svg');
  lines.setAttribute('class', 's3-lines');
  lines.setAttribute('viewBox', '0 0 1920 1080');
  root.appendChild(lines);
  const pins = PIN_POS.map((p, i) => {
    const cfg = CONFIG.pins?.[p.key];
    if (!cfg) return null;
    const ln = document.createElementNS(svgNS, 'path');
    lines.appendChild(ln);
    const dot = el('div', 'pin-dot', '<i></i>', root);
    const lead = cfg.value != null ? `<span class="big">${esc(cfg.value)}</span>` : '<span class="pi">◆</span>';
    const card = el('div', 'pin', `${lead}<span><b>${esc(cfg.title)}</b><small>${esc(cfg.text)}</small></span>`, root);
    css(card, { left: `${p.x}px`, top: `${p.y}px` });
    css(dot, { left: `${p.tx}px`, top: `${p.ty}px` });
    return { ...p, i, cfg, ln, dot, card, big: $(card, '.big') };
  }).filter(Boolean);

  // PageSpeed-style report: four score gauges
  const PSI = ['Performance', 'Accessibility', 'Best Practices', 'SEO'];
  const scores = CONFIG.pagespeed?.scores || [100, 100, 100, 100];
  const CIRC = 2 * Math.PI * 52;
  const psi = el(
    'div',
    'psi',
    `<div class="psi-head"><span class="psi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M4 16a8 8 0 1 1 16 0"/><path d="M12 16l4.5-5"/></svg></span><b>PageSpeed Insights</b><span class="psi-tabs"><i class="on">Mobile</i><i>Desktop</i></span></div>
     <div class="psi-gauges">${PSI.map(
       (l) => `<div class="g"><div class="ring"><svg viewBox="0 0 120 120"><circle class="bg" cx="60" cy="60" r="52"/><circle class="fg" cx="60" cy="60" r="52" stroke-dasharray="${CIRC.toFixed(1)}"/></svg><span class="n">0</span></div><label>${l}</label></div>`,
     ).join('')}</div>
     <div class="psi-foot"><i></i>Core Web Vitals Assessment: <b>Passed</b></div>`,
    root,
  );
  const gauges = [...psi.querySelectorAll('.g')].map((g) => ({ fg: g.querySelector('.fg'), n: g.querySelector('.n') }));

  const lockup = el(
    'div',
    's3-lockup',
    `<div class="lk"><b>${esc(CONFIG.lockup?.title)}</b><span>${esc(CONFIG.lockup?.tagline)}</span></div>`,
    root,
  );

  let keys = null;
  function cursorKeys() {
    const s = T.s3;
    const mid = (r) => [r.x + r.w / 2, r.y + r.h / 2];
    const k = [[s.cursorIn, 1180, 790]];
    if (tc && s.hover < OFF) {
      const card = [tc.x + tc.w * 0.5, tc.y + tc.h * 0.38 - S1];
      k.push([s.hover, ...card], [s.hover + 0.2, card[0] + 30, card[1] - 10]);
    }
    if (s.quickClick < OFF) {
      const btn = [qv.x + qv.w / 2, qv.y + qv.h / 2 - S1];
      const add = mid(Q.add);
      const close = mid(C.close || { x: C.x + C.w - 50, y: 20, w: 40, h: 30 });
      k.push([s.quickClick - 0.06, ...btn], [s.quickClick + 0.15, ...btn], [s.addClick - 0.08, ...add], [s.addClick + 0.12, ...add], [s.closeClick - 0.08, ...close], [s.closeClick + 0.12, ...close]);
    }
    if (s.shopClick < OFF) {
      const nav = mid(site.shopNav);
      k.push([s.shopClick - 0.07, ...nav], [s.shopClick + 0.2, ...nav]);
      if (s.filterClick < OFF) {
        const sw = [SF.x + 12, SF.y + SF.h / 2 - S3];
        const res = shop.grid ? [shop.grid.x + 130, shop.grid.y + 220 - S3] : [700, 500];
        k.push([s.scroll3[1], sw[0] + 180, sw[1] - 60], [s.filterClick - 0.07, ...sw], [s.filterClick + 0.15, ...sw], [s.filter[1] + 0.25, ...res]);
      }
    }
    return k;
  }

  return {
    update(t) {
      const s = T.s3;
      const on = t > s.start;
      show(root, on);
      if (!on) return;
      keys ||= cursorKeys();
      // crossfade with scene 2's page, which ends in exactly this pose
      css(root, { opacity: String(ease.inOutSine(prog(t, s.start, s.start + 0.15))) });

      // tab switches: k[i] = progress of switching to extra store i
      const ks = s.extra.map((e, i) => (extras[i] ? ease.inOutCubic(prog(t, ...e.switch)) : 0));
      const k0 = ks[0] || 0;

      // camera: continues from the handoff pose, slow drift, push into quick view and filter
      const pin = ease.inOutCubic(prog(t, ...s.enter));
      const drift = ease.inOutSine(prog(t, s.enter[0], s.end));
      const push = ease.inOutSine(prog(t, s.modal[0] - 0.2, s.modal[1] + 0.2)) * (1 - ease.inOutSine(prog(t, s.cartOut[0], s.cartOut[1] + 0.3)));
      const fpush = ease.inOutSine(prog(t, s.scroll3[0], s.filterClick)) * (1 - ease.inOutSine(prog(t, s.filter[1], s.cursorOut + 0.4)));
      const swing = s.extra.reduce((a, e) => a + Math.sin(Math.PI * prog(t, ...e.switch)), 0);
      const ry = lerp(HANDOFF_POSE.ry, -12, pin) + drift * 8 + push * 4 + fpush * 5 + swing * 6;
      const rx = lerp(HANDOFF_POSE.rx, 7, pin) - drift * 3;
      const sc = lerp(HANDOFF_POSE.scale, 0.79, pin) + drift * 0.02 + push * 0.05 + fpush * 0.04;
      const ty = -drift * 8;
      const tx = lerp(-25, 25, drift) * pin + fpush * 60 - swing * 40;
      css(browser, { transform: `translate(${tx}px,${ty}px) rotateX(${rx}deg) rotateY(${ry}deg) scale(${sc})` });
      css(chrome, { opacity: String(ease.inOutSine(prog(t, s.start + 0.05, s.start + 0.5))) });
      css(shadow, { transform: `translate(${tx}px,0) rotateY(${ry * 0.5}deg) scale(${sc})`, opacity: String(0.8 * pin) });
      css(glare, { transform: `translateX(${lerp(-130, 170, ease.inOutSine(prog(t, ...s.glare)))}%) skewX(-18deg)` });

      // main store: home page, then the shop page; both leave when the first extra tab opens
      const sy = S1 * ease.inOutCubic(prog(t, ...s.scroll));
      const nv = shop ? ease.inOutCubic(prog(t, ...s.nav)) : 0;
      const sy3 = S3 * ease.inOutCubic(prog(t, ...s.scroll3));
      css(page, { transform: `translateY(${-sy - nv * 24}px) translateX(${-k0 * 80}px)`, opacity: String((1 - nv) * (1 - k0)) });
      css(shopPage, { transform: `translateY(${-sy3 + (1 - nv) * 24}px) translateX(${-k0 * 80}px)`, opacity: String(nv * (1 - k0)) });
      extraPages.forEach((e, i) => {
        const kin = ks[i];
        const kout = ks[i + 1] || 0;
        const y = e.target * ease.inOutCubic(prog(t, ...s.extra[i].scroll));
        css(e.p, { transform: `translate(${(1 - kin) * 80 - kout * 80}px,${-y}px)`, opacity: String(kin * (1 - kout)) });
        css(e.sw, { transform: `translateX(${lerp(-500, VW + 200, ease.inOutSine(prog(t, s.extra[i].switch[1], s.extra[i].switch[1] + 0.6)))}px) skewX(-22deg)` });
      });

      // tab strip: new tabs slide in and become active
      const current = ks.filter((k) => k > 0.5).length; // 0 = main store
      tabEls.forEach((tab, i) => {
        const tb = i ? ease.outCubic(prog(t, ...s.extra[i - 1].tab)) : 1;
        css(tab, {
          opacity: String(tb),
          transform: `translateY(${(1 - tb) * 100}%)`,
          background: i === current ? '#2d2d30' : 'transparent',
          color: i === current ? '#ddd' : '#8a8a8a',
        });
      });
      const url = current ? host(extras[current - 1].url) : `${host(site.url)}${active(t, [s.nav[0] + 0.1]) ? shopPath.replace(/\/$/, '') + '/' : ''}`;
      if (urlText.textContent !== url) urlText.textContent = url;

      const curY = nv < 0.5 ? sy : sy3;
      const stick = ease.outCubic(prog(curY, (site.header?.h || 120) + 60, (site.header?.h || 120) + 260));
      css(sticky, { transform: `translateY(${(stick - 1) * 105}%)`, boxShadow: `0 10px 30px rgba(0,0,0,${0.1 * stick})`, opacity: String(1 - k0) });
      css(sweep, { transform: `translateX(${lerp(-500, VW + 200, ease.inOutSine(prog(t, ...s.sweep)))}px) skewX(-22deg)` });
      css(stickySweep, { transform: `translateX(${lerp(-500, VW + 200, ease.inOutSine(prog(t, ...s.stickySweep)))}px) skewX(-22deg)` });
      css(stickyCart, { opacity: String(prog(t, s.cart[0] + 0.1, s.cart[0] + 0.2)) });

      // AJAX filter: loader veil over the grid, then the filtered result
      css(shopFiltered, { opacity: String(ease.inOutSine(prog(t, s.filter[0] + 0.12, s.filter[1]))) });
      css(loader, { opacity: String(0.85 * Math.sin(Math.PI * prog(t, ...s.filter))), '--spin': `${(t * 720) % 360}deg` });

      // real hover state of the product card (stays until the side cart closes)
      const hv = ease.inOutSine(prog(t, s.hover - 0.15, s.hover + 0.2)) * (1 - prog(t, s.cartOut[0], s.cartOut[0] + 0.3)) * (1 - nv);
      css(hover, { opacity: String(hv) });
      const ring = prog(t, s.quickClick - 0.1, s.quickClick + 0.4);
      css(qvRing, { opacity: String(ring > 0 && ring < 1 ? 1 - ring : 0), transform: `translate(-50%,-50%) scale(${0.6 + ring * 1.4})` });

      // quick view grows out of the product card, closes after Add to cart; side cart follows
      css(overlay, { opacity: String(0.55 * prog(t, s.modal[0], s.modal[0] + 0.2) * (1 - ease.inOutCubic(prog(t, ...s.cartOut)))) });
      if (Q && C && tc) {
        const m = ease.outQuart(prog(t, ...s.modal));
        const mo = ease.inOutCubic(prog(t, ...s.modalOut));
        const from = [tc.x + tc.w / 2 - (Q.x + Q.w / 2), tc.y + tc.h * 0.4 - S1 - (Q.y + Q.h / 2)];
        css(modal, {
          opacity: String(prog(t, s.modal[0], s.modal[0] + 0.12) * (1 - mo)),
          transform: `translate(${from[0] * (1 - m)}px,${from[1] * (1 - m)}px) scale(${lerp(tc.w / Q.w, 1, m) * (1 - 0.05 * mo)})`,
        });
        css(qvX, { opacity: String(prog(t, s.modal[1] - 0.15, s.modal[1]) * (1 - mo)) });
        css(addPress, { opacity: String(0.28 * Math.max(0, 1 - Math.abs(t - s.addClick) / 0.12)) });
        const ci = ease.outQuart(prog(t, ...s.cart));
        const co = ease.inOutCubic(prog(t, ...s.cartOut));
        css(sideCart, { transform: `translateX(${(1 - ci + co) * 100}%)`, opacity: String(ci > 0 && co < 1 ? 1 : 0) });
      }

      // cursor + click ripple (ripple stays where the click happened)
      const [cx, cy] = path(t, keys, ease.inOutSine);
      const clicks = [s.quickClick, s.addClick, s.closeClick, s.shopClick, s.filterClick].filter((c) => c < OFF);
      const pressed = clicks.some((c) => t > c - 0.04 && t < c + 0.08);
      css(cursor, {
        opacity: String(prog(t, s.cursorIn, s.cursorIn + 0.2) * (1 - prog(t, s.cursorOut - 0.1, s.cursorOut + 0.15))),
        transform: `translate(${cx}px,${cy}px) scale(${pressed ? 0.84 : 1})`,
      });
      const last = clicks.filter((c) => t >= c).pop();
      if (last != null) {
        const k = prog(t, last, last + 0.45);
        const [px, py] = path(last, keys, ease.inOutSine);
        css(ripple, { opacity: String(1 - k), transform: `translate(${px}px,${py}px) scale(${0.2 + 1.6 * ease.outCubic(k)})` });
      } else css(ripple, { opacity: '0' });

      // HUD pins: the first two leave when the session moves on to the shop / next tab
      const leaveAt = s.nav[0] < OFF ? s.nav : s.extra[0]?.switch || s.psi;
      pins.forEach((p) => {
        const at = s.pins[p.i];
        const sp = spring(t - at, 7, 12);
        const fl = Math.sin(t * 1.8 + p.i * 2.1) * 5;
        const k = ease.inOutCubic(prog(t, at, at + 0.45));
        const hide = p.i < 2 ? 1 - prog(t, s.modal[0] - 0.2, s.modal[0]) : 1;
        const fade = p.i < 2 ? 1 - ease.inOutSine(prog(t, ...leaveAt)) : 1;
        css(p.card, { opacity: String(prog(t, at, at + 0.12) * fade), transform: `translateY(${fl}px) scale(${Math.max(0, sp)})` });
        css(p.dot, { opacity: String(prog(t, at, at + 0.15) * hide * fade), '--ring': String((t * 1.2 + p.i * 0.3) % 1) });
        const ax = p.x + (p.x < 960 ? p.card.offsetWidth : p.card.offsetWidth * 0.3);
        const ay = p.y + fl + (p.x < 960 ? p.card.offsetHeight / 2 : p.card.offsetHeight);
        p.ln.setAttribute('d', `M${ax} ${ay} L${lerp(ax, p.tx, k)} ${lerp(ay, p.ty, k)}`);
        p.ln.setAttribute('opacity', String(prog(t, at, at + 0.15) * 0.9 * hide * fade));
        if (p.big) {
          const txt = counter(p.cfg.value, ease.outCubic(prog(t, at, at + 0.8)));
          if (p.big.textContent !== txt) p.big.textContent = txt;
        }
      });

      // PageSpeed card: springs in, gauges fill one after another
      const pk = spring(t - s.psi[0], 7, 11);
      css(psi, {
        opacity: String(prog(t, s.psi[0], s.psi[0] + 0.12)),
        transform: `translateY(${Math.sin(t * 1.6) * 4}px) scale(${Math.max(0, pk)}) rotate(${(1 - Math.min(1, pk)) * -4}deg)`,
      });
      gauges.forEach((g, i) => {
        const k = ease.outCubic(prog(t, s.psi[0] + 0.15 + i * 0.1, s.psi[1] - 0.3 + i * 0.1));
        const score = scores[i] ?? 100;
        g.fg.setAttribute('stroke-dashoffset', (CIRC * (1 - (k * score) / 100)).toFixed(2));
        const v = String(Math.round(score * k));
        if (g.n.textContent !== v) g.n.textContent = v;
      });

      const lk = ease.outQuart(prog(t, ...s.lockup));
      css(lockup, { opacity: String(ease.inOutSine(prog(t, s.lockup[0], s.lockup[0] + 0.3))), transform: `translateY(${(1 - lk) * 40}px)` });
    },
  };
}
