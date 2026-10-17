// Project configs: defaults + derivation, so a project can be as small as
// { "prompt": "…", "sites": [{ "url": "https://…" }] }.

const isUk = (s) => /[а-яіїєґ]/i.test(s);

const COPY = {
  uk: {
    reply: 'Зніму дизайн сайту й зберу магазин на {brand} через MCP.',
    todos: ['Зняти дизайн сайту', 'Хедер і мега-меню', 'Головна сторінка', 'Товари та Quick View'],
  },
  en: {
    reply: "I'll capture the site's design and build the store on {brand} via MCP.",
    todos: ['Capture the site design', 'Header & mega menu', 'Home page', 'Products & Quick View'],
  },
};

export const DEFAULTS = {
  brand: { name: 'Woodmart', watermark: 'WOODMART', tabSuffix: 'Woodmart' },
  terminal: {
    server: 'woodmart',
    spinner: 'Building…',
    calls: [
      { tool: 'mcp-adapter-discover-abilities', result: 'Found <b>90</b> abilities' },
      { tool: 'mcp-adapter-execute-ability', ability: 'woodmart/create-header', result: 'Header created · sticky · mega menu' },
      { tool: 'mcp-adapter-execute-ability', ability: 'woodmart/elementor-create-content', result: 'Home page created · 9 sections' },
    ],
  },
  features: ['AJAX Quick Shop', 'Mega Menu', 'AJAX Quick View', 'Wishlist & Compare'],
  marquee: ['DESIGN SYSTEM', 'HEADER BUILDER', 'AJAX QUICK SHOP', 'QUICK VIEW', 'MEGA MENU', 'PRODUCT HOVERS'],
  headline: { kicker: 'WooCommerce theme', lines: ['#1 Best Selling', 'WooCommerce Theme'], stars: true },
  pins: {
    designSystem: { title: 'Woodmart Design System', text: 'Header Builder, presets & layouts' },
    pageLoad: { value: '0.2s', title: 'Page Load', text: 'Optimized CSS & lazy assets' },
    mobile: { value: '100%', title: 'Mobile Optimized', text: 'Responsive on every breakpoint' },
  },
  pagespeed: { enabled: true, scores: [100, 100, 100, 100] },
  lockup: { title: 'MCP Woodmart', tagline: 'Your complete WooCommerce store — built by AI.' },
  audio: { track: null },
  render: { blur: 4, crf: 14 },
};

const isObj = (v) => v && typeof v === 'object' && !Array.isArray(v);
function merge(base, over) {
  if (!isObj(base) || !isObj(over)) return over === undefined ? base : over;
  const out = { ...base };
  for (const k of Object.keys(over)) out[k] = merge(base[k], over[k]);
  return out;
}

export const slug = (s) =>
  String(s)
    .toLowerCase()
    .replace(/^https?:\/\//, '')
    .replace(/\.(s\d+-)?tastewp\.com.*$|(\.[a-z]{2,})?\.[a-z]{2,}(\/.*)?$/, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '') || 'promo';

export function resolveConfig(raw) {
  if (!raw?.prompt) throw new Error('config: "prompt" is required');
  if (!raw.sites?.length || !raw.sites[0].url) throw new Error('config: "sites" needs at least one { "url": … }');
  const cfg = merge(DEFAULTS, raw);
  cfg.name = slug(raw.name || raw.sites[0].url);
  const lang = raw.language || (isUk(raw.prompt) ? 'uk' : 'en');
  const copy = COPY[lang] || COPY.en;
  cfg.language = lang;
  cfg.terminal = {
    ...cfg.terminal,
    cwd: raw.terminal?.cwd || `~/stores/${cfg.name}`,
    reply: raw.terminal?.reply || copy.reply.replace('{brand}', cfg.brand.name),
    todos: raw.terminal?.todos || copy.todos,
  };
  return cfg;
}

// "Make a promo … https://a.com and https://b.com" → { prompt, sites }
export function configFromPrompt(text, { name } = {}) {
  const urls = [...text.matchAll(/https?:\/\/[^\s,;)]+/g)].map((m) => m[0].replace(/[.:]+$/, ''));
  if (!urls.length) throw new Error('Put at least one site URL into the prompt');
  const prompt = text
    .replace(/https?:\/\/[^\s,;)]+/g, '')
    .replace(/\s+([,.:;!?])/g, '$1')
    .replace(/\s{2,}/g, ' ')
    .replace(/([\s,:;—-]+(і|й|та|ще|і ще|and|also|plus|or))+\s*$/i, '')
    .replace(/[\s,:;—-]+$/g, '')
    .trim();
  // the URL itself isn't shown in the terminal, so close a dangling "…design of" / "…дизайн з"
  const ending = /\b(of|from|like|at|on)$/i.test(prompt) ? ' this site' : /(^|\s)(з|із|зі|як|на)$/i.test(prompt) ? ' цього сайту' : '';
  return { name: name || slug(urls[0]), prompt: prompt ? prompt + ending : 'Build the full store from this design', sites: urls.map((url) => ({ url })) };
}
