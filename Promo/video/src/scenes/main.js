// Entry point: loads the project (config + captured sites), builds all layers
// and exposes window.__seek(t) for the headless renderer.
//   ?project=<name>  which work/<name>/ to load (default: leafline)
//   ?play            loop in real time      ?t=3.2  freeze on a frame
import { WIDTH, HEIGHT, configure } from '../timeline.js';
import * as timeline from '../timeline.js';
import * as scene1 from './scene1.js';
import * as scene2 from './scene2.js';
import * as scene3 from './scene3.js';
import * as overlay from './overlay.js';

const stage = document.getElementById('stage');
const params = new URLSearchParams(location.search);
const project = params.get('project') || 'leafline';
const WORK = `/work/${project}`;

// only what the capture actually produced (a missing image would 404)
const mainAssets = (l) =>
  [
    'page',
    l.hover && 'hover',
    l.quickview && 'quickview',
    l.sidecart && 'sidecart',
    l.sticky && 'sticky',
    l.stickyCart && 'sticky-cart',
    l.menu && 'menu',
    l.shop && 'shop',
    l.shop?.filteredHeight && 'shop-filtered',
  ].filter(Boolean);

async function loadProject() {
  const json = (u) => fetch(u).then((r) => (r.ok ? r.json() : null), () => null);
  const config = await json(`${WORK}/config.json`);
  if (!config) throw new Error(`No ${WORK}/config.json — run "node cli.js capture" first`);
  const sites = [];
  for (let i = 0; i < config.sites.length; i++) {
    const layout = await json(`${WORK}/site${i}/layout.json`);
    if (!layout) continue;
    sites.push({ ...layout, base: `${WORK}/site${i}/`, label: config.sites[i].label || layout.title?.split(/\s[–|-]\s/)[0] || `Store ${i + 1}`, url: config.sites[i].url });
  }
  if (!sites.length) throw new Error('No captured sites');
  // decode every capture up front so no frame is grabbed before an image paints
  const files = [...mainAssets(sites[0]).map((n) => `${sites[0].base}${n}.png`), ...sites.slice(1).map((s) => `${s.base}page.png`)];
  window.__imgs = await Promise.all(
    files.map(async (src) => {
      const img = new Image();
      img.src = src;
      await img.decode().catch(() => {});
      return img;
    }),
  );
  configure(config, sites);
  return { ...sites[0], extras: sites.slice(1) };
}

window.__ready = Promise.all([document.fonts.ready, loadProject()]).then(([, site]) => {
  const layers = [scene1, scene2, scene3, overlay].map((s) => s.build(stage, site));
  window.__seek = (t) => {
    for (const l of layers) l.update(t);
    return t;
  };
  window.__seek(0);
  return { duration: timeline.DURATION, width: WIDTH, height: HEIGHT };
});

if (params.has('play') || params.has('t')) {
  document.body.classList.add('preview');
  const fit = () => (stage.style.transform = `scale(${Math.min(innerWidth / WIDTH, innerHeight / HEIGHT)})`);
  addEventListener('resize', fit);
  fit();
  window.__ready.then(() => {
    if (params.has('t')) return window.__seek(+params.get('t'));
    const t0 = performance.now();
    const loop = () => {
      window.__seek(((performance.now() - t0) / 1000) % timeline.DURATION);
      requestAnimationFrame(loop);
    };
    loop();
  });
}
