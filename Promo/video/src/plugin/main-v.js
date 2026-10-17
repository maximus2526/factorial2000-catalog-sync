import { WIDTH, HEIGHT, DURATION } from './timeline-v.js';
import * as scene from './scene-v.js';

const stage = document.getElementById('stage');
const params = new URLSearchParams(location.search);
const IMGS = ['import', 'update', 'market', 'icon'];

window.__ready = Promise.all([
  document.fonts.ready,
  ...IMGS.map(async (n) => {
    const i = new Image();
    i.src = `/assets/${n}.png`;
    await i.decode().catch(() => {});
    return i;
  }),
]).then(() => {
  const layer = scene.build(stage);
  window.__seek = (t) => (layer.update(t), t);
  window.__seek(0);
  return { duration: DURATION, width: WIDTH, height: HEIGHT };
});

if (params.has('play') || params.has('t')) {
  document.body.classList.add('preview');
  const fit = () => (stage.style.transform = `scale(${Math.min(innerWidth / WIDTH, innerHeight / HEIGHT)})`);
  addEventListener('resize', fit);
  fit();
  window.__ready.then(() => {
    if (params.has('t')) return window.__seek(+params.get('t'));
    const t0 = performance.now();
    const loop = () => (window.__seek(((performance.now() - t0) / 1000) % DURATION), requestAnimationFrame(loop));
    loop();
  });
}
