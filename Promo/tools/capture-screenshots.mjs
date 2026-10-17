// Captures the plugin admin pages from a local dev site at 2x device scale.
// Usage: node tools/capture-screenshots.mjs <baseUrl> <user> <pass>
import { chromium } from '../video/node_modules/playwright/index.mjs';
import { mkdir } from 'node:fs/promises';

const [base, user, pass] = process.argv.slice(2);
const OUT = new URL('../shots-raw/', import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1');
await mkdir(OUT, { recursive: true });

const PAGES = [
  ['update', 'f2000cs-update'],
  ['import', 'f2000cs-import'],
  ['export', 'f2000cs-export'],
  ['marketplaces', 'f2000cs-marketplaces'],
  ['docs', 'f2000cs-docs'],
];

// Hide unrelated plugin clutter so the screenshot shows only the plugin.
const CSS = `
  #wpadminbar, .notice, .updated, .error:not(.f2000cs-keep), #wpfooter, .update-nag, #screen-meta-links, #f2000cs-support { display:none !important; }
  html.wp-toolbar { padding-top: 0 !important; }
  #adminmenu li#menu-posts-woodmart_layout, #adminmenu li#toplevel_page_xts_dashboard, #adminmenu li#toplevel_page_xts_theme_settings,
  #adminmenu li.menu-icon-cms_block, #adminmenu li.menu-icon-woodmart_sidebar, #adminmenu li.menu-icon-wd_popup,
  #adminmenu li.menu-icon-wd_floating_block, #adminmenu li.menu-icon-portfolio, #adminmenu li#toplevel_page_wpcf7,
  #adminmenu li#toplevel_page_f2000ws, #adminmenu li#toplevel_page_f2000tr-settings, #adminmenu li#toplevel_page_mailchimp-for-wp,
  #adminmenu li#toplevel_page_freemius, #adminmenu li#menu-comments, #adminmenu li#toplevel_page_wc-admin-path--analytics-overview,
  #adminmenu li#toplevel_page_wc-admin-path--marketing { display:none !important; }
  ::-webkit-scrollbar { display:none; }
`;

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1600, height: 900 }, deviceScaleFactor: 2, locale: 'uk-UA' });
const page = await ctx.newPage();
await page.goto(`${base}/wp-login.php`);
await page.fill('#user_login', user);
await page.fill('#user_pass', pass);
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

for (const [name, slug] of PAGES) {
  await page.goto(`${base}/wp-admin/admin.php?page=${slug}`, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: CSS });
  await page.evaluate(() => {
    // anonymise local dev data for public screenshots
    document.querySelectorAll('input[type=text],input[type=url]').forEach((i) => {
      i.value = i.value.replace(/https?:\/\/localhost:\d+\/[^\s]*/, 'https://supplier.example.com/feed.xml').replace(/^LIVED_$/, 'SUP_');
    });
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let n = w.nextNode(); n; n = w.nextNode()) n.nodeValue = n.nodeValue.replace(/https?:\/\/localhost:\d+/g, 'https://your-store.com').replace(/LIVED_/g, 'SUP_');
  });
  await page.waitForTimeout(600);
  const h = await page.evaluate(() => Math.max(document.getElementById('wpcontent').scrollHeight, document.getElementById('adminmenuwrap').scrollHeight));
  await page.setViewportSize({ width: 1600, height: Math.min(h + 20, name === "docs" || name === "marketplaces" ? 1500 : 2000) });
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${OUT}${name}.png`, fullPage: false });
  console.log(name, h);
  await page.setViewportSize({ width: 1600, height: 900 });
}
await browser.close();
