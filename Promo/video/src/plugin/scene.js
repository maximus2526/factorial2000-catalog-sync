// Factorial2000 Catalog Sync promo — every visual is a pure function of time t.
import { el, css, show, prog, ease, lerp, spring, decay, $, $$ } from '../scenes/lib.js';
import { ACTS, DURATION, BOARD, bulletAt } from './timeline.js';

const ASSET = (n) => `/assets/${n}.png`;
const SIZES = { import: [1920, 1740], update: [1920, 1560], market: [1920, 1200], editor: [1920, 1696], images: [1920, 1127] };

// 0 → 1 over the first ~0.8 s of the act, 1 → 0 over the last 0.45 s
function envelope(t, [a, b]) {
  return {
    on: t >= a - 0.02 && t <= b + 0.02,
    rt: t - a,
    inn: ease.outExpo(prog(t, a, a + 0.8)),
    out: ease.inCubic(prog(t, b - 0.45, b)),
    dur: b - a,
  };
}

const pop = (rt, at) => Math.min(1.08, spring(rt - at, 11, 20));
const slide = (n, k, dx = 0, dy = 0) =>
  css(n, { opacity: String(Math.min(1, k * 1.4)), transform: `translate(${(1 - k) * dx}px, ${(1 - k) * dy}px)` });

// split layout: text on one side, tilted app window on the other
function splitAct(stage, { range, side, kicker, pro, title, bullets, img, tagText }) {
  const root = el('div', 'act', null, stage);
  const [iw, ih] = SIZES[img];
  const VIEW_W = 960;
  const VIEW_H = 730;
  const ZOOM = img === 'import' || img === 'update' || img === 'market' ? 1.45 : 1; // drop the admin menu, bigger text
  const imgH = (VIEW_W * ZOOM * ih) / iw;
  const cardLeft = side === 'right' ? 910 : 60;
  const textLeft = side === 'right' ? 90 : 1070;

  const txt = el('div', 'txt', null, root);
  css(txt, { left: `${textLeft}px`, transform: 'translateY(-50%)' });
  el('div', 'kicker', `${kicker}<span class="tag${pro ? ' pro' : ''}">${tagText}</span>`, txt);
  el('h2', null, title, txt);
  const items = bullets.map((b) =>
    el('div', 'bul', `<i></i><div>${b.t}${b.s ? `<small>${b.s}</small>` : ''}</div>`, txt),
  );
  const codeEl = bullets.find((b) => b.code) ? el('div', null, `<span class="code">${bullets.find((b) => b.code).code}</span>`, txt) : null;
  if (codeEl) css(codeEl, { marginTop: '10px' });
  const kick = $(txt, '.kicker');
  const h2 = $(txt, 'h2');

  const wrap = el('div', 'shot3d', null, root);
  css(wrap, { left: `${cardLeft}px`, top: `${(1080 - VIEW_H - 38) / 2}px`, width: `${VIEW_W}px` });
  const win = el('div', 'win', `<div class="bar"><i></i><i></i><i></i></div><div class="view"><img src="${ASSET(img)}"></div><div class="glare"></div>`, wrap);
  const view = $(win, '.view');
  const im = $(win, 'img');
  const glare = $(win, '.glare');
  css(view, { height: `${VIEW_H}px` });
  css(im, { width: `${VIEW_W * ZOOM}px`, left: `${-0.108 * VIEW_W * ZOOM + (ZOOM > 1 ? 12 : 0)}px` });

  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      css(root, { opacity: String(1 - e.out), filter: e.out > 0.01 ? `blur(${e.out * 10}px)` : 'none' });
      slide(kick, ease.outCubic(prog(e.rt, 0.1, 0.6)), -40);
      slide(h2, ease.outCubic(prog(e.rt, 0.25, 0.85)), -60);
      items.forEach((n, i) => slide(n, ease.outCubic(prog(e.rt, bulletAt(i), bulletAt(i) + 0.5)), side === 'right' ? -70 : 70));
      if (codeEl) slide(codeEl, ease.outCubic(prog(e.rt, bulletAt(items.length), bulletAt(items.length) + 0.5)), 0, 20);
      const dir = side === 'right' ? 1 : -1;
      const ry = -10 * dir + Math.sin(t * 0.7) * 1.4;
      css(wrap, {
        opacity: String(Math.min(1, e.inn * 1.5)),
        transform: `translateX(${(1 - e.inn) * 260 * dir}px) scale(${0.9 + 0.1 * e.inn}) rotateY(${ry + (1 - e.inn) * -18 * dir}deg) rotateX(${3 + Math.cos(t * 0.6)}deg)`,
      });
      const pan = Math.max(0, imgH - VIEW_H) * ease.inOutCubic(prog(e.rt, 1.0, e.dur - 0.6));
      css(im, { transform: `translateY(${-pan}px)` });
      css(glare, { transform: `translateX(${lerp(-60, 190, prog(e.rt, 0.5, 1.9)) * 9}px) skewX(-8deg)`, opacity: String(prog(e.rt, 0.5, 0.7) * (1 - prog(e.rt, 1.6, 1.9))) });
    },
  };
}

function introAct(stage) {
  const range = ACTS.intro;
  const root = el('div', 'act', null, stage);
  const flow = el('div', 'flow', null, root);
  const chipDefs = [['XML', 'XML постачальника'], ['W', 'WooCommerce'], ['P', 'Prom.ua']];
  const chips = [];
  const arrows = [];
  chipDefs.forEach(([ic, label], i) => {
    chips.push(el('div', 'chip', `<b>${ic}</b>${label}`, flow));
    if (i < chipDefs.length - 1) arrows.push(el('div', 'arrow', '<div class="fill"></div><div class="pk"></div>', flow));
  });
  const title = el('div', 'title', `<img class="ic" src="${ASSET('icon')}"><h1>Factorial2000<br><span>Catalog Sync</span></h1><p>Імпорт. Синхронізація. Публікація.</p>`, root);
  css(title, { top: '170px' });
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
        const k = pop(e.rt, 0.3 + i * 0.7);
        css(c, { opacity: String(Math.min(1, k * 2)), transform: `scale(${0.6 + 0.4 * k})` });
      });
      arrows.forEach((a, i) => {
        const p = prog(e.rt, 0.7 + i * 0.7, 1.15 + i * 0.7);
        $(a, '.fill').style.width = `${p * 100}%`;
        const pk = $(a, '.pk');
        css(pk, { left: `${((e.rt * 0.9 + i * 0.3) % 1) * 130}px`, opacity: String(p >= 1 ? 1 : 0) });
      });
      const fout = ease.inCubic(prog(e.rt, 2.2, 2.7));
      css(flow, { opacity: String(1 - fout), transform: `scale(${1 + 0.25 * fout}) translateY(${-fout * 40}px)`, filter: fout > 0.01 ? `blur(${fout * 12}px)` : 'none' });
      const k = spring(e.rt - 2.55, 9, 16);
      css(ic, { opacity: String(Math.min(1, k * 2)), transform: `scale(${0.5 + 0.5 * k})` });
      slide(h1, ease.outExpo(prog(e.rt, 2.7, 3.3)), 0, 50);
      slide(sub, ease.outCubic(prog(e.rt, 3.0, 3.5)), 0, 30);
    },
  };
}

function boardAct(stage) {
  const range = ACTS.board;
  const root = el('div', 'act', null, stage);
  const bt = el('div', 'btitle', 'Безкоштовно назавжди. <em>Pro — коли ростете.</em>', root);
  const FREE = ['Імпорт простих і варіативних товарів', 'Щоденне оновлення наявності та цін', '1 постачальник · 3 ручні запуски на день', 'Націнка, маржа або фіксована сума', 'Сповіщення в Telegram', 'Вбудована документація'];
  const PRO = ['Кілька постачальників + кількість на складі', 'Оновлення від кожних 5 хвилин', 'Фід Prom.ua після кожної синхронізації', 'Фільтр і візуальний редактор XML', 'Оновлення окремих полів', 'Конвертація фото: WebP / AVIF'];
  const cols = el('div', 'cols', null, root);
  const mk = (cls, name, note, list) => {
    const c = el('div', `col ${cls}`, `<h3>${name}<span>${note}</span></h3><ul>${list.map((x) => `<li>${x}</li>`).join('')}</ul>`, cols);
    return { c, lis: $$(c, 'li') };
  };
  const f = mk('', 'FREE', 'назавжди', FREE);
  const p = mk('pro', 'PRO', '14 днів Pro · без картки', PRO);

  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      css(root, { opacity: String(1 - e.out), filter: e.out > 0.01 ? `blur(${e.out * 10}px)` : 'none' });
      slide(bt, ease.outExpo(prog(e.rt, 0.1, 0.8)), 0, -50);
      slide(f.c, ease.outExpo(prog(e.rt, 0.5, 1.2)), -120);
      slide(p.c, ease.outExpo(prog(e.rt, 0.8, 1.5)), 120);
      f.lis.forEach((li, i) => slide(li, ease.outCubic(prog(e.rt, BOARD.free + i * BOARD.step, BOARD.free + i * BOARD.step + 0.4)), -40));
      p.lis.forEach((li, i) => slide(li, ease.outCubic(prog(e.rt, BOARD.pro + i * BOARD.step, BOARD.pro + i * BOARD.step + 0.4)), 40));
    },
  };
}

function trioAct(stage) {
  const range = ACTS.pro3;
  const root = el('div', 'act', null, stage);
  const head = el('div', 'ptitle', '<div class="kicker">Можливості<span class="tag pro">PRO</span></div><h2>Створено для <em>продавців на маркетплейсах</em></h2>', root);
  const trio = el('div', 'trio', null, root);
  const cards = [
    el('div', 'tcard', '<div class="kicker" style="font-size:18px">Експорт</div><h3>Редактор XML</h3><p>Оберіть категорії та товари, відфільтруйте за ціною й наявністю — і отримайте готовий XML для Prom.ua.</p><div class="pic"><img src="/assets/editor.png"></div>', trio),
    el('div', 'tcard', '<div class="kicker" style="font-size:18px">Імпорт</div><h3>Робота з фото</h3><p>Конвертація PNG, JPG, WebP та AVIF у WebP або AVIF під час імпорту. Оптимізація та зменшення.</p><div class="pic"><img src="/assets/images.png"></div>', trio),
    el('div', 'tcard', '<div class="kicker" style="font-size:18px">Обслуговування</div><h3>Оновлення полів</h3><p>Оновлюйте лише потрібні поля в уже імпортованих товарах.</p><div class="fchips"><b>Назва</b><b>Опис</b><b>Фото</b><b>Атрибути</b><b>Категорії</b><b>Теги</b><b>vendorCode</b></div>', trio),
  ];
  const pics = [$(cards[0], 'img'), $(cards[1], 'img')];
  const picH = [(1920 - 60) * 0 + 0, 0];

  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      css(root, { opacity: String(1 - e.out), filter: e.out > 0.01 ? `blur(${e.out * 10}px)` : 'none' });
      slide(head, ease.outExpo(prog(e.rt, 0.1, 0.8)), 0, -50);
      cards.forEach((c, i) => {
        const k = pop(e.rt, 0.9 + i * 0.4);
        css(c, { opacity: String(Math.min(1, k * 2)), transform: `translateY(${(1 - k) * 80}px) scale(${0.9 + 0.1 * Math.min(1, k)})` });
      });
      // slow pan inside the two screenshot cards
      const w = (trio.offsetWidth - 88) / 3 - 72;
      pics.forEach((im, i) => {
        const nat = i === 0 ? w * (1696 / 1920) : w * (1127 / 1920);
        const over = Math.max(0, nat - 290);
        css(im, { transform: `translateY(${-over * ease.inOutCubic(prog(e.rt, 1.8, e.dur - 0.8))}px)` });
      });
      void picH;
    },
  };
}

function outroAct(stage) {
  const range = ACTS.outro;
  const root = el('div', 'act', null, stage);
  const title = el('div', 'title', `<img class="ic" src="${ASSET('icon')}"><h1>Factorial2000<br><span>Catalog Sync</span></h1><p>Безкоштовно назавжди · 14 днів Pro без картки</p><div class="cta">Плагіни → Додати новий → «Factorial2000 Catalog Sync»</div>`, root);
  css(title, { top: '150px' });
  const ic = $(title, '.ic');
  const h1 = $(title, 'h1');
  const sub = $(title, 'p');
  const cta = $(title, '.cta');
  return {
    update(t) {
      const e = envelope(t, range);
      show(root, e.on);
      if (!e.on) return;
      const k = spring(e.rt - 0.15, 9, 16);
      css(ic, { opacity: String(Math.min(1, k * 2)), transform: `scale(${0.5 + 0.5 * k}) rotate(${Math.sin(e.rt * 1.2) * 2}deg)` });
      slide(h1, ease.outExpo(prog(e.rt, 0.4, 1.1)), 0, 60);
      slide(sub, ease.outCubic(prog(e.rt, 0.9, 1.5)), 0, 30);
      const c = pop(e.rt, 1.5);
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
      range: ACTS.import, side: 'right', kicker: '01 · Імпорт', tagText: 'БЕЗКОШТОВНО',
      title: 'XML постачальника → <em>WooCommerce</em>',
      bullets: [
        { t: 'Прості та варіативні товари' },
        { t: 'Фото, атрибути, категорії', s: 'вага, штрихкод і розміри — теж' },
        { t: 'Імпорт лише товарів в наявності' },
      ],
      img: 'import',
    }),
    splitAct(stage, {
      range: ACTS.sync, side: 'left', kicker: '02 · Синхронізація', tagText: 'FREE + PRO',
      title: 'Наявність і ціни <em>на автопілоті</em>',
      bullets: [
        { t: 'Оновлення через WP-Cron', s: 'Free: щодня · Pro: від 5 хвилин до доби' },
        { t: 'Націнка, маржа або фіксована сума', s: 'для кожного постачальника — безкоштовно' },
        { t: 'Звіт у Telegram після кожного запуску' },
      ],
      img: 'update',
    }),
    boardAct(stage),
    splitAct(stage, {
      range: ACTS.market, side: 'right', kicker: '03 · Маркетплейси', tagText: 'PRO', pro: true,
      title: 'Ваш каталог на <em>Prom.ua</em>',
      bullets: [
        { t: 'Одне постійне посилання на фід', s: 'прості й варіативні товари, бренди, категорії' },
        { t: 'Оновлення після кожної синхронізації', s: 'Prom завжди отримує актуальні ціни й наявність' },
        { code: 'wp f2000cs marketplace build prom', t: 'Або з командного рядка' },
      ],
      img: 'market',
    }),
    trioAct(stage),
    outroAct(stage),
  ];
  const vig = el('div', 'fx-vig', null, stage);
  const flash = el('div', 'fx-flash', null, stage);
  const starts = Object.values(ACTS).map((r) => r[0]).slice(1);

  return {
    update(t) {
      const beatPulse = Math.pow(0.5 + 0.5 * Math.cos(((t % 0.5) / 0.5) * Math.PI * 2), 3);
      css(grid, { transform: `translate(${(-t * 18) % 60}px, ${(-t * 10) % 60}px)`, opacity: String(0.7 + 0.3 * beatPulse) });
      css(bg, { filter: `brightness(${1 + 0.05 * beatPulse})` });
      for (const l of layers) l.update(t);
      const f = starts.reduce((s, a) => s + 0.35 * decay(t, a, 9), 0);
      const end = prog(t, DURATION - 0.35, DURATION);
      css(flash, { opacity: String(Math.min(1, f)) });
      css(vig, { opacity: String(1 - end * 0.0) });
      css(stage, { opacity: String(1 - end) });
    },
  };
}
