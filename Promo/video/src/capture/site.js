// Headless capture of a live Woodmart site: full-page render, element
// geometry and real interaction states (hover, quick view, sticky header,
// dropdown menu, mobile). Scenes 2–3 are composed from these assets.
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const W = 1480;
const H = 828;

const CLEAN_CSS = `
  .wd-side-hidden:not(.wd-opened), .wd-close-side, .cookies-popup, .wd-cookies-popup,
  .scrollToTop, .wd-sticky-nav, #wpadminbar, .woocommerce-store-notice { display: none !important; }
  html { margin-top: 0 !important; scrollbar-width: none; }
  ::-webkit-scrollbar { display: none; }
`;

const rect = (el) => {
  if (!el) return null;
  const b = el.getBoundingClientRect();
  return { x: Math.round(b.x), y: Math.round(b.y + scrollY), w: Math.round(b.width), h: Math.round(b.height) };
};

async function settle(page, ms = 600) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(ms);
}

// Marketing popups (Woodmart Popup Builder, newsletter, promo) appear on timers
// and block clicks; close and remove them before every step.
async function dismissPopups(page) {
  await page
    .evaluate(() => {
      const builder = document.querySelector('.wd-popup-builder-wrap, .wd-promo-popup, .wd-age-verify');
      if (builder && window.jQuery?.magnificPopup) window.jQuery.magnificPopup.close();
      document.querySelectorAll('.wd-popup-builder-wrap, .wd-promo-popup, .wd-age-verify, .wd-popup-builder').forEach((e) => e.remove());
      if (!document.querySelector('.mfp-wrap')) {
        document.querySelectorAll('.mfp-bg').forEach((e) => e.remove());
        document.documentElement.style.removeProperty('overflow');
        document.documentElement.style.removeProperty('margin-right');
      }
    })
    .catch(() => {});
}

async function scrollThrough(page) {
  const h = await page.evaluate(() => document.documentElement.scrollHeight);
  for (let y = 0; y < h; y += 500) {
    await page.evaluate((yy) => scrollTo(0, yy), y);
    await page.waitForTimeout(120);
  }
  await page.evaluate(() => scrollTo(0, 0));
  await settle(page, 900);
  await dismissPopups(page);
}

export async function captureSite({ url, outDir, target = 1, light = false, log = console.log }) {
  await mkdir(outDir, { recursive: true });
  const browser = await chromium.launch({ args: ['--hide-scrollbars', '--force-color-profile=srgb'] });
  const layout = { url, width: W, viewport: { w: W, h: H } };
  try {
    const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    page.setDefaultTimeout(15000);
    log(`  · loading ${url}`);
    await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
    await page.addStyleTag({ content: CLEAN_CSS });
    await scrollThrough(page);

    // geometry first (document coordinates, nothing hovered)
    Object.assign(
      layout,
      await page.evaluate((rectSrc) => {
        const rect = new Function(`return ${rectSrc}`)();
        const top = document.querySelector('[data-elementor-type="wp-page"]') || document.querySelector('.entry-content');
        return {
          height: document.documentElement.scrollHeight,
          title: document.title,
          header: rect(document.querySelector('.whb-header')),
          topbar: rect(document.querySelector('.whb-top-bar')),
          sections: top ? [...top.children].map(rect).filter((r) => r && r.h > 40) : [],
          cards: [...document.querySelectorAll('.wd-product, .product-grid-item')].slice(0, 8).map((e) => ({
            ...rect(e),
            img: rect(e.querySelector('.product-element-top, .product-image-link, img')),
            qv: rect(e.querySelector('.wd-quick-view-btn, .quick-view')),
            name: (e.querySelector('.wd-entities-title, h3')?.textContent || '').trim(),
          })),
          footer: rect(document.querySelector('.footer-container, footer')),
          brand: (document.querySelector('.wd-logo img')?.alt || document.querySelector('.wd-logo')?.textContent || '').trim(),
        };
      }, rect.toString()),
    );
    layout.height = Math.min(layout.height, 16000);

    log('  · full page');
    await dismissPopups(page);
    await page.screenshot({ path: path.join(outDir, 'page.png'), fullPage: true, clip: { x: 0, y: 0, width: W, height: layout.height } });
    if (light) {
      // secondary sites only need the full page and its geometry
      await writeFile(path.join(outDir, 'layout.json'), JSON.stringify(layout, null, 1));
      return layout;
    }

    // product hover state (Woodmart hover effect, action buttons)
    // sticky header
    try {
      log('  · sticky header');
      await dismissPopups(page);
      await page.mouse.move(5, 600);
      await page.evaluate(() => scrollTo(0, 1400));
      await page.waitForTimeout(1200);
      const sticky = await page.evaluate(() => {
        const h = document.querySelector('.whb-sticked, .whb-header.whb-sticked, .whb-main-header');
        const b = (h?.querySelector('.whb-sticky-row, .whb-general-header') || h)?.getBoundingClientRect();
        return b && b.top <= 1 && b.height > 30 ? Math.round(b.bottom) : null;
      });
      if (sticky) {
        await page.screenshot({ path: path.join(outDir, 'sticky.png'), clip: { x: 0, y: 0, width: W, height: sticky } });
        layout.sticky = { h: sticky };
      }
    } catch {}

    // dropdown / mega menu
    try {
      log('  · dropdown menu');
      await page.evaluate(() => scrollTo(0, 0));
      await page.waitForTimeout(600);
      await dismissPopups(page);
      const item = await page.$('.wd-header-main-nav .menu-item-has-children > a, .wd-nav-main > li.menu-item-has-children > a');
      await item.hover();
      await page.waitForTimeout(900);
      const dd = await page.evaluateHandle(() =>
        [...document.querySelectorAll('.wd-dropdown-menu, .wd-dropdown')].find((d) => {
          const b = d.getBoundingClientRect();
          return b.height > 40 && getComputedStyle(d).visibility !== 'hidden' && +getComputedStyle(d).opacity > 0.9;
        }),
      );
      const el = dd.asElement();
      if (el) {
        const b = await el.boundingBox();
        await el.screenshot({ path: path.join(outDir, 'menu.png') });
        layout.menu = { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height) };
      }
    } catch {}
    // product → quick view → add to cart → side cart: the real Woodmart purchase flow.
    // Adds one item to this headless session's cart only (gone when the browser closes).
    const ti = layout.cards[target] ? target : 0;
    const card = layout.cards[ti];
    layout.target = ti;
    let handle = null;
    if (card) {
      try {
        log('  · product hover state');
        const scrollY = Math.max(0, card.y - 260);
        await page.evaluate((y) => scrollTo(0, y), scrollY);
        await settle(page, 500);
        await dismissPopups(page);
        handle = (await page.$$('.wd-product, .product-grid-item'))[ti];
        await handle.hover({ position: { x: card.w / 2, y: card.h * 0.35 }, timeout: 8000 });
        await page.waitForTimeout(900);
        const pad = 24;
        const clip = { x: card.x - pad, y: card.y - scrollY - pad, width: card.w + pad * 2, height: card.h + pad * 2 + 60 };
        await page.screenshot({ path: path.join(outDir, 'hover.png'), clip });
        layout.flowScroll = scrollY;
        layout.hover = { x: card.x - pad, y: card.y - pad, w: clip.width, h: clip.height };
        layout.hover.qv = await handle.evaluate((e, rs) => new Function(`return ${rs}`)()(e.querySelector('.wd-quick-view-btn, .quick-view')), rect.toString());
      } catch (e) {
        log(`  ! product hover not captured (${e.message.split('\n')[0]}) — the video skips the purchase flow`);
        handle = null;
      }
    }
    if (handle && layout.hover.qv) {
      const vrect = async (sel) => {
        const h = await page.$(sel);
        const b = h && (await h.boundingBox());
        return b && { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height) };
      };
      try {
        log('  · quick view popup');
        await dismissPopups(page);
        await handle.$eval('.open-quick-view, .wd-quick-view-btn a', (a) => a.click());
        await page.waitForSelector('.mfp-ready .mfp-content .product-quick-view', { state: 'visible', timeout: 15000 });
        await settle(page, 1800);
        const box = await page.$('.mfp-content');
        await box.screenshot({ path: path.join(outDir, 'quickview.png') });
        // positions are viewport coordinates at flowScroll
        layout.quickview = {
          ...(await vrect('.mfp-content')),
          close: await vrect('.mfp-wrap .mfp-close, .mfp-wrap [class*="close"]'),
          add: await vrect('.mfp-content .single_add_to_cart_button'),
        };

        log('  · add to cart → side cart');
        await page.click('.mfp-content .single_add_to_cart_button');
        await page.waitForSelector('.cart-widget-side.wd-opened', { timeout: 15000 });
        await settle(page, 1500);
        const side = await page.$('.cart-widget-side');
        await side.screenshot({ path: path.join(outDir, 'sidecart.png') });
        layout.sidecart = { ...(await vrect('.cart-widget-side')), close: await vrect('.cart-widget-side .close-side-widget, .cart-widget-side [class*="close"]') };

        // header with the updated cart (sticky state)
        await page.click('.cart-widget-side .close-side-widget, .cart-widget-side [class*="close"]').catch(() => page.keyboard.press('Escape'));
        await page.mouse.move(5, 600);
        await page.evaluate(() => scrollTo(0, 1400));
        await page.waitForTimeout(1200);
        if (layout.sticky) {
          await page.screenshot({ path: path.join(outDir, 'sticky-cart.png'), clip: { x: 0, y: 0, width: W, height: layout.sticky.h } });
          layout.stickyCart = true;
        }
        // "Shop" link in the sticky header (viewport coords), then the shop page itself
        const nav = await page.evaluate(() => {
          const a = [...document.querySelectorAll('.whb-header .wd-nav-main > li > a')].find((e) => {
            const b = e.getBoundingClientRect();
            return b.top >= 0 && b.top < 80 && b.width > 0;
          });
          if (!a) return null;
          const b = a.getBoundingClientRect();
          return { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height), href: a.href, label: a.textContent.trim() };
        });
        if (nav) {
          layout.shopNav = nav;
          log(`  · shop page (${nav.href})`);
          await page.goto(nav.href, { waitUntil: 'networkidle', timeout: 90000 });
          await page.addStyleTag({ content: CLEAN_CSS });
          await scrollThrough(page);
          const sh = await page.evaluate(() => document.documentElement.scrollHeight);
          await page.screenshot({ path: path.join(outDir, 'shop.png'), fullPage: true, clip: { x: 0, y: 0, width: W, height: sh } });
          const shop = await page.evaluate((rs) => {
            const rect = new Function(`return ${rs}`)();
            const f = document.querySelector('.wd-swatches-filter a, .woocommerce-widget-layered-nav-list a, .wd-filter-list a');
            return {
              grid: rect(document.querySelector('.products, .wd-products')),
              filter: f ? { ...rect(f), label: f.textContent.trim(), href: f.href } : null,
            };
          }, rect.toString());
          layout.shop = { url: nav.href, height: sh, ...shop };
          if (shop.filter) {
            log(`  · AJAX filter: ${shop.filter.label}`);
            await page.evaluate((y) => scrollTo(0, y), Math.max(0, shop.filter.y - 400));
            await page.waitForTimeout(500);
            await dismissPopups(page);
            await page.click('.wd-swatches-filter a, .woocommerce-widget-layered-nav-list a, .wd-filter-list a');
            await settle(page, 2000);
            await page.evaluate(() => scrollTo(0, 0));
            await scrollThrough(page);
            const fh = await page.evaluate(() => document.documentElement.scrollHeight);
            await page.screenshot({ path: path.join(outDir, 'shop-filtered.png'), fullPage: true, clip: { x: 0, y: 0, width: W, height: fh } });
            layout.shop.filteredHeight = fh;
          }
        }
      } catch (e) {
        log(`  ! purchase flow not fully captured (${e.message.split('\n')[0]})`);
      }
    }

    await ctx.close();

    // mobile
    log('  · mobile');
    const m = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    const mp = await m.newPage();
    await mp.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
    await mp.addStyleTag({ content: CLEAN_CSS });
    await scrollThrough(mp);
    const mh = Math.min(await mp.evaluate(() => document.documentElement.scrollHeight), 4200);
    await mp.screenshot({ path: path.join(outDir, 'mobile.png'), fullPage: true, clip: { x: 0, y: 0, width: 390, height: mh } });
    layout.mobile = { w: 390, h: mh };
    await m.close();
  } finally {
    await browser.close();
  }
  await writeFile(path.join(outDir, 'layout.json'), JSON.stringify(layout, null, 1));
  return layout;
}
