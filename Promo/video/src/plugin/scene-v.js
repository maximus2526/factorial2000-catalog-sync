// Vertical Reels cut — every visual is a pure function of time t.
import { el, css, show, prog, ease, lerp, spring, decay, $, $$ } from '../scenes/lib.js';
import { ACTS, DURATION, BOARD, bulletAt, TITLE_AT } from './timeline-v.js';

const ASSET = (n) => `/assets/${n}.png`;
const SIZES = { import: [1920, 1740], update: [1920, 1560], market: [1920, 1200] };

function envelope(t, [a, b]) {
  return {
    on: t >= a - 0.02 && t <= b + 0.02,
    rt: t - a,
    inn: ease.outExpo(prog(t, a, a + 0.7)),
    out: ease.inCubic(prog(t, b - 0.4, b)),
    dur: b - a,
  };
}
const pop = (rt, at) => Math.min(1.08, spring(rt - at, 11, 20));
const slide = (n, k, dx = 0, dy = 0) => css(n, { opacity: String(Math.min(1, k * 1.4)), transform: `translate(${(1 - k) * dx}px, ${(1 - k) * dy}px)` });
const fade = (root, e) => css(root, { opacity: String(1 - e.out), filter: e.out > 0.01 ? `blur(${e.out * 10}px)` : 'none' });

// text on top, tilted app window below
function splitAct(stage, { range, kicker, tagText, pro, title, bullets, img }) {
  const root = el('div', 'act', null, stage);
  const [iw, ih] = SIZES[img];
  const VIEW_W = 940;
  const VIEW_H = 600;
  const ZOOM = 1.45;
  const imgH = (VIEW_W * ZOOM * ih) / iw;

  const txt = el('div', 'v-text', null, root);
  const kick = el('div', 'kicker', `${kicker}<span class="tag${pro ? ' pro' : ''}">${tagText}</span>`, txt);
  const h2 = el('h2', null, title, txt);
  const items = bullets.map((b) => el('div', 'bul', `<i></i><div>${b.t}${b.s ? `<small>${b.s}</small>` : ''}</div>`, txt));
  const codeB = bullets.find((b) => b.code);
  const codeEl = codeB ? el('div', null, `<span class="code">${codeB.code}</span>`, txt) : null;

  const wrap = el('div', 'shot3d', null, root);
  css(wrap, { left: '70px', top: '960px', width: `${VIEW_W}px` });
  const win = el('div', 'win', `<div class="bar"><i></i><i></i><i></i></div><div class="view"><img src="${ASSET(img)}"></div><div class="glare"></div>`, wrap);
  css($(win, '.view'), { height: `${VIEW_H}px` });
  const im = $(win, 'img');
  css(im, { width: `${VIEW_W * ZOOM}px`, left: `${-0.108 * VIEW_W * ZOOM + 12}px` });
  const glare = $(win, '.glare');

  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      fade(root, e);
      slide(kick, ease.outCubic(prog(e.rt, 0.1, 0.5)), -40);
      slide(h2, ease.outCubic(prog(e.rt, 0.2, 0.7)), -50);
      items.forEach((n, i) => slide(n, ease.outCubic(prog(e.rt, bulletAt(i), bulletAt(i) + 0.45)), -60));
      if (codeEl) slide(codeEl, ease.outCubic(prog(e.rt, bulletAt(items.length), bulletAt(items.length) + 0.45)), 0, 20);
      css(wrap, {
        opacity: String(Math.min(1, e.inn * 1.5)),
        transform: `translateY(${(1 - e.inn) * 260}px) scale(${0.9 + 0.1 * e.inn}) rotateX(${8 - (1 - e.inn) * 18 + Math.cos(t * 0.6)}deg) rotateY(${Math.sin(t * 0.7) * 2.2}deg)`,
      });
      const pan = Math.max(0, imgH - VIEW_H) * ease.inOutCubic(prog(e.rt, 0.9, e.dur - 0.5));
      css(im, { transform: `translateY(${-pan}px)` });
      css(glare, { transform: `translateX(${lerp(-60, 190, prog(e.rt, 0.4, 1.7)) * 8}px) skewX(-8deg)`, opacity: String(prog(e.rt, 0.4, 0.6) * (1 - prog(e.rt, 1.4, 1.7))) });
    },
  };
}

function introAct(stage) {
  const range = ACTS.intro;
  const root = el('div', 'act', null, stage);
  const flow = el('div', 'flow-v', null, root);
  const defs = [['XML', 'Фід постачальника'], ['W', 'WooCommerce'], ['P', 'Prom.ua']];
  const chips = [];
  const arrows = [];
  defs.forEach(([ic, label], i) => {
    chips.push(el('div', 'chip', `<b>${ic}</b>${label}`, flow));
    if (i < defs.length - 1) arrows.push(el('div', 'arrow', '<div class="fill"></div><div class="pk"></div>', flow));
  });
  const title = el('div', 'v-title', `<img class="ic" src="${ASSET('icon')}"><h1>Factorial2000<br><span>Catalog Sync</span></h1><p>Імпорт. Синхронізація. Публікація.</p>`, root);
  css(title, { top: '470px' });
  const ic = $(title, '.ic');
  const h1 = $(title, 'h1');
  const sub = $(title, 'p');

  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      css(root, { opacity: String(1 - e.out) });
      chips.forEach((c, i) => {
        const k = pop(e.rt, 0.2 + i * 0.45);
        css(c, { opacity: String(Math.min(1, k * 2)), transform: `scale(${0.6 + 0.4 * k})` });
      });
      arrows.forEach((a, i) => {
        const p = prog(e.rt, 0.4 + i * 0.45, 0.75 + i * 0.45);
        css($(a, '.fill'), { height: `${p * 100}%` });
        css($(a, '.pk'), { top: `${((e.rt * 0.9 + i * 0.3) % 1) * 70}px`, opacity: String(p >= 1 ? 1 : 0) });
      });
      const fout = ease.inCubic(prog(e.rt, TITLE_AT - 0.45, TITLE_AT + 0.05));
      css(flow, { opacity: String(1 - fout), transform: `scale(${1 + 0.2 * fout}) translateY(${-fout * 40}px)`, filter: fout > 0.01 ? `blur(${fout * 12}px)` : 'none' });
      const k = spring(e.rt - TITLE_AT, 9, 16);
      css(ic, { opacity: String(Math.min(1, k * 2)), transform: `scale(${0.5 + 0.5 * k})` });
      slide(h1, ease.outExpo(prog(e.rt, TITLE_AT + 0.1, TITLE_AT + 0.7)), 0, 50);
      slide(sub, ease.outCubic(prog(e.rt, TITLE_AT + 0.4, TITLE_AT + 0.9)), 0, 30);
    },
  };
}

function boardAct(stage) {
  const range = ACTS.board;
  const root = el('div', 'act', null, stage);
  const bt = el('div', 'v-btitle', 'Безкоштовно назавжди.<br><em>Pro — коли ростете.</em>', root);
  const FREE = ['Імпорт простих і варіативних товарів', 'Щоденне оновлення наявності та цін', '1 постачальник · 3 ручні запуски на день', 'Націнка, маржа або фіксована сума', 'Сповіщення в Telegram', 'Вбудована документація'];
  const PRO = ['Кілька постачальників + кількість на складі', 'Оновлення від кожних 5 хвилин', 'Фід Prom.ua після кожної синхронізації', 'Фільтр і візуальний редактор XML', 'Оновлення окремих полів', 'Конвертація фото: WebP / AVIF'];
  const cols = el('div', 'v-cols', null, root);
  const mk = (cls, name, note, list) => {
    const c = el('div', `col ${cls}`, `<h3>${name}<span>${note}</span></h3><ul>${list.map((x) => `<li>${x}</li>`).join('')}</ul>`, cols);
    return { c, lis: $$(c, 'li') };
  };
  const f = mk('', 'FREE', 'назавжди', FREE);
  const p = mk('pro', 'PRO', '14 днів · без картки', PRO);
  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      fade(root, e);
      slide(bt, ease.outExpo(prog(e.rt, 0.1, 0.7)), 0, -40);
      slide(f.c, ease.outExpo(prog(e.rt, 0.4, 1.0)), -100);
      slide(p.c, ease.outExpo(prog(e.rt, 0.7, 1.3)), 100);
      f.lis.forEach((li, i) => slide(li, ease.outCubic(prog(e.rt, BOARD.free + i * BOARD.step, BOARD.free + i * BOARD.step + 0.35)), -40));
      p.lis.forEach((li, i) => slide(li, ease.outCubic(prog(e.rt, BOARD.pro + i * BOARD.step, BOARD.pro + i * BOARD.step + 0.35)), 40));
    },
  };
}

function outroAct(stage) {
  const range = ACTS.outro;
  const root = el('div', 'act', null, stage);
  const title = el('div', 'v-title', `<img class="ic" src="${ASSET('icon')}"><h1>Factorial2000<br><span>Catalog Sync</span></h1><p>Безкоштовно назавжди · 14 днів Pro без картки</p><div class="cta">Плагіни → Додати новий →<br>«Factorial2000 Catalog Sync»</div>`, root);
  css(title, { top: '430px' });
  const ic = $(title, '.ic');
  const h1 = $(title, 'h1');
  const sub = $(title, 'p');
  const cta = $(title, '.cta');
  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      const k = spring(e.rt - 0.1, 9, 16);
      css(ic, { opacity: String(Math.min(1, k * 2)), transform: `scale(${0.5 + 0.5 * k}) rotate(${Math.sin(e.rt * 1.2) * 2}deg)` });
      slide(h1, ease.outExpo(prog(e.rt, 0.3, 1.0)), 0, 60);
      slide(sub, ease.outCubic(prog(e.rt, 0.8, 1.3)), 0, 30);
      const c = pop(e.rt, 1.4);
      css(cta, { opacity: String(Math.min(1, c * 2)), transform: `scale(${0.7 + 0.3 * c})` });
    },
  };
}

export function build(stage) {
  const bg = el('div', 'bg', null, stage);
  const grid = el('div', 'grid', null, stage);
  const layers = [
    introAct(stage),
    splitAct(stage, {
      range: ACTS.import, kicker: '01 · Імпорт', tagText: 'БЕЗКОШТОВНО',
      title: 'XML постачальника → <em>WooCommerce</em>',
      bullets: [{ t: 'Прості та варіативні товари' }, { t: 'Фото, атрибути, категорії' }, { t: 'Імпорт лише товарів в наявності' }],
      img: 'import',
    }),
    splitAct(stage, {
      range: ACTS.sync, kicker: '02 · Синхронізація', tagText: 'FREE + PRO',
      title: 'Наявність і ціни <em>на автопілоті</em>',
      bullets: [{ t: 'Оновлення через WP-Cron' }, { t: 'Націнка, маржа або фіксована сума', s: 'для кожного постачальника' }, { t: 'Звіт у Telegram після кожного запуску' }],
      img: 'update',
    }),
    boardAct(stage),
    splitAct(stage, {
      range: ACTS.market, kicker: '03 · Маркетплейси', tagText: 'PRO', pro: true,
      title: 'Ваш каталог на <em>Prom.ua</em>',
      bullets: [{ t: 'Одне постійне посилання на фід' }, { t: 'Оновлення після кожної синхронізації' }, { code: 'wp f2000cs marketplace build prom', t: 'Або з командного рядка' }],
      img: 'market',
    }),
    outroAct(stage),
  ];
  const flash = el('div', 'fx-flash', null, stage);
  const starts = Object.values(ACTS).map((r) => r[0]).slice(1);
  return {
    update(t) {
      const beatPulse = Math.pow(0.5 + 0.5 * Math.cos(((t % 0.5) / 0.5) * Math.PI * 2), 3);
      css(grid, { transform: `translate(${(-t * 18) % 60}px, ${(-t * 10) % 60}px)`, opacity: String(0.7 + 0.3 * beatPulse) });
      css(bg, { filter: `brightness(${1 + 0.05 * beatPulse})` });
      for (const l of layers) l.update(t);
      const f = starts.reduce((s, a) => s + 0.35 * decay(t, a, 9), 0);
      css(flash, { opacity: String(Math.min(1, f)) });
      css(stage, { opacity: String(1 - prog(t, DURATION - 0.35, DURATION)) });
    },
  };
}
