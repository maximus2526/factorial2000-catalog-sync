// Scene 1 (0.0–3.2s): Claude Code terminal. Mirrors the real CLI: the prompt
// is typed into the input box, moves into the transcript on Enter, a spinner
// runs while Claude replies, calls the woodmart MCP and ticks off its todos.
import { T, COMMAND, CONFIG, typeTimes } from '../timeline.js';
import { el, css, show, prog, ease, lerp, $, $$ } from './lib.js';

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
const call = (server, c) =>
  `<span class="dot">⏺</span> <b>${esc(server)} - ${esc(c.tool)}</b> <span class="dim">(MCP)</span>${c.ability ? `<span class="dim">(ability_name: </span><span class="arg">"${esc(c.ability)}"</span><span class="dim">)</span>` : ''}`;
const res = (txt) => `<span class="dim">  ⎿  </span>${txt}`;

// transcript rows from the config: reply, MCP calls with results, todo list
function feedRows(term) {
  const rows = [{ html: `<span class="dot w">⏺</span> ${esc(term.reply)}`, gap: true }];
  for (const c of term.calls || []) {
    rows.push({ html: call(term.server || 'mcp', c), gap: true });
    if (c.result) rows.push({ html: res(c.result) });
  }
  if (term.todos?.length) rows.push({ html: `<span class="dot">⏺</span> <b>Update Todos</b>`, gap: true });
  return rows;
}
const SPIN = ['·', '✢', '✳', '✶', '✻', '✽'];

export function build(stage) {
  const tcfg = CONFIG.terminal || {};
  const FEED = feedRows(tcfg);
  const TODOS = (tcfg.todos || []).slice(0, 4);
  const cwd = tcfg.cwd || '~/project';
  const root = el(
    'div',
    'scene s1',
    `
    <div class="s1-sky"></div>
    <div class="s1-gridwrap"><div class="s1-grid floor"></div><div class="s1-grid ceil"></div></div>
    <div class="s1-watermark">${esc(CONFIG.brand?.watermark || CONFIG.brand?.name || "")}<i>.</i></div>
    <div class="s1-cam">
      <div class="term">
        <div class="term-bar"><i></i><i></i><i></i><span>claude — ${esc(cwd)}</span></div>
        <div class="term-body"><div class="feed">
          <div class="cc-welcome">
            <div><span class="star">✻</span> Welcome to <strong>Claude Code</strong>!</div>
            <div class="dim">/help for help, /status for your current setup</div>
            <div class="dim">cwd: ${esc(cwd.replace(/^~/, "/Users/you"))}</div>
          </div>
          <div class="r hist gap"><span class="hb"><span class="dim">&gt;</span> ${esc(COMMAND)}</span></div>
          ${FEED.map((r) => `<div class="r ln${r.gap ? ' gap' : ''}">${r.html}</div>`).join('')}
          ${TODOS.map((h, i) => `<div class="r todo"><span class="dim">${i ? '     ' : '  ⎿  '}</span><span class="box">☐</span> <span class="tt">${h}</span></div>`).join('')}
          <div class="r spin gap"><span class="sg">✻</span> <span class="sw">${esc(tcfg.spinner || "Working…")}</span> <span class="dim">(<span class="st">0s</span> · ↑ <span class="tk">0</span> tokens · esc to interrupt)</span></div>
          <div class="cc-input"><span class="pr">&gt;</span><span class="typed"></span><span class="caret"></span></div>
          <div class="cc-hint dim">? for shortcuts</div>
        </div></div>
      </div>
    </div>`,
    stage,
  );

  const times = typeTimes();
  const typed = $(root, '.typed');
  const caret = $(root, '.caret');
  const term = $(root, '.term');
  const cam = $(root, '.s1-cam');
  const body = $(root, '.term-body');
  const feed = $(root, '.feed');
  const welcome = $(root, '.cc-welcome');
  const hist = $(root, '.hist');
  const lines = $$(root, '.ln');
  const todos = $$(root, '.todo');
  const spin = $(root, '.spin');
  const [sg, st, tk] = ['.sg', '.st', '.tk'].map((q) => $(spin, q));
  const grids = $$(root, '.s1-grid');
  const mark = $(root, '.s1-watermark');
  const sky = $(root, '.s1-sky');

  // rows grow from 0 height to their natural height when they appear
  const rows = [hist, ...lines, ...todos, spin];
  const heights = new Map(rows.map((r) => [r, r.offsetHeight]));
  rows.forEach((r) => css(r, { height: '0px', opacity: '0' }));
  const grow = (t, r, at, dur = 0.08) => {
    const k = ease.outCubic(prog(t, at, at + dur));
    css(r, { height: `${(heights.get(r) * k).toFixed(2)}px`, opacity: String(k) });
  };
  let lastN = -1;

  return {
    update(t) {
      const s = T.s1;
      const on = t < s.exit[1] + 0.05;
      show(root, on);
      if (!on) return;

      // background: grid rushing toward camera, pulsing watermark
      const gy = (t * 140) % 90;
      grids.forEach((g, i) => css(g, { backgroundPosition: `0 ${i ? -gy : gy}px` }));
      const pulse = 0.5 + 0.5 * Math.sin(t * 5.2);
      css(mark, {
        opacity: String(0.22 + 0.14 * pulse),
        textShadow: `0 0 ${30 + 40 * pulse}px rgba(131,183,53,${0.35 + 0.3 * pulse})`,
      });
      css(sky, { opacity: String(0.7 + 0.3 * pulse) });

      // terminal in / out
      const pin = ease.outQuart(prog(t, ...s.termIn));
      const pout = ease.inOutCubic(prog(t, ...s.exit));
      const push = lerp(1, 1.045, ease.inOutSine(prog(t, 0, s.exit[0])));
      css(cam, {
        transform: `translateY(${(1 - pin) * 60}px) scale(${(0.93 + 0.07 * pin) * push * (1 + 0.5 * pout)})`,
        opacity: String(pin * (1 - pout)),
        filter: pout > 0.001 ? `blur(${pout * 14}px)` : 'none',
      });

      const typing = t > s.typeStart && t < s.enter ? 1 : 0;
      const enterHit = t > s.enter ? Math.exp(-(t - s.enter) * 6) : 0;
      const g = 0.32 + 0.12 * pulse + 0.18 * typing + 0.4 * enterHit;
      css(term, {
        boxShadow: `0 0 0 1px rgba(131,183,53,${0.35 + g * 0.5}), 0 0 ${40 + 60 * g}px rgba(131,183,53,${g * 0.55}), 0 50px 140px rgba(0,0,0,.75)`,
      });
      css(welcome, { opacity: String(prog(t, 0.1, 0.3)) });

      // typing into the input box; on Enter the prompt moves into the transcript
      let n = 0;
      while (n < times.length && times[n] <= t) n++;
      if (t >= s.enter) n = 0;
      if (n !== lastN) {
        typed.textContent = COMMAND.slice(0, n);
        lastN = n;
      }
      const blink = Math.floor(t * 2.4) % 2 === 0;
      css(caret, { opacity: typing || blink ? '1' : '0' });
      grow(t, hist, s.enter, 0.06);

      // spread however many rows the config has over the transcript window
      const [l0, l1] = [s.lines[0], s.lines[s.lines.length - 1]];
      FEED.forEach((_, i) => grow(t, lines[i], l0 + ((l1 - l0) * i) / Math.max(1, FEED.length - 1)));
      todos.forEach((r, i) => {
        grow(t, r, s.todos[i], 0.05);
        const done = t >= s.checks[i];
        const box = r.querySelector('.box');
        if (box.textContent !== (done ? '☒' : '☐')) box.textContent = done ? '☒' : '☐';
        r.classList.toggle('done', done);
      });

      // spinner: glyph cycles, timer and token counter race (time-lapse)
      grow(t, spin, s.spinner, 0.06);
      const sp = prog(t, s.spinner, s.exit[1]);
      sg.textContent = SPIN[Math.floor(t * 12) % SPIN.length];
      const secs = Math.round(ease.inCubic(sp) * 252);
      st.textContent = secs < 60 ? `${secs}s` : `${Math.floor(secs / 60)}m ${secs % 60}s`;
      tk.textContent = `${(ease.inCubic(sp) * 21.7).toFixed(1)}k`;

      // terminal scrolls once the transcript overflows
      const over = Math.max(0, feed.offsetHeight - body.clientHeight);
      css(feed, { transform: `translateY(${-over}px)` });
    },
  };
}
