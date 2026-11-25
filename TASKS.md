# Backlog (from the code audit)

Status legend: `[ ]` open · `[x]` done. Severity: **H** high · **M** medium · **L** low.
File:line references are from the audit and may have shifted.

## Done

- [x] **M** Import session id was mixed-case but read through `sanitize_key()` → lowercased at generation (`admin/page-import.php`).
- [x] **M** `admin-import.js`: supplier-controlled attribute names and server messages were rendered with `.html()` → now text only (DOM-XSS).
- [x] **L** Uninstall now also removes `f2000cs_*` transients (import cache, resume checkpoints, download tokens).
- [x] **H** Products the plugin drafted because they vanished from the feed are published again when the feed lists them (marker meta `_f2000cs_auto_drafted`, filter `f2000cs_republish_returned_products`).
- [x] **H** Global `wp_cache_flush()` calls are gone: flushes go through `f2000cs_flush_transients_cache()` (`transients` group only) and `f2000cs_flush_runtime_cache()` (in-process memory, WP 6.0+) at the two periodic spots; a source-scan unit test keeps a global flush from coming back.

## Open

### Worldwide version (branch `wordwide-feed`) — «Factorial2000 Dropshipping & Feed Sync»

**Hard rule — a paying Pro customer runs this plugin (rechovyk.com.ua).** Nothing that is saved may be lost or change meaning on update:
- Never rename or re-shape a stored option, post meta key, cron hook, feed file name/token or offer id without a migration. Bump `F2000CS_DB_VERSION` and add a step to `f2000cs_maybe_run_migrations()`; a migration must be idempotent, keep the old value until the new one is written, and have a unit test with the old data shape.
- Option keys that must keep working as they are: `f2000cs_url`, `f2000cs_url_N`, `f2000cs_sku_prefix_N`, `f2000cs_skip_price_N`, `f2000cs_ignore_discounts_N`, `f2000cs_update_stock_qty_N`, `f2000cs_import_missing_N`, `f2000cs_price_adjust_{type,direction,value,max_share}_N`, `f2000cs_update_interval`, marketplace settings (`Marketplace_Registry::option_name()`), `f2000cs_mp_token_*` (the feed link given to Prom must not change).
- Product identity: existing products are matched by SKU = prefix + supplier offer id. A new «match by» mode (supplier id in its own meta field) is opt-in per supplier; existing suppliers stay on SKU unless the owner switches, and a switch needs a migration that fills the new meta from the current SKUs first.
- Marketplace offer ids (`<offer id>` = WooCommerce product/variation id) must not change: Prom and ROZETKA treat a new id as a new product.
- Product meta in use: `_f2000tr_prom_keywords`, `_f2000tr_prom_title`, `f2000cs-updater-vendor`, `_f2000cs_auto_drafted`.
- YML suppliers must behave exactly as today after the reader refactor: keep the current stock-sync tests green and add a golden test (same feed → same writes) before touching `XML_Stock_Updater`.
- Before each release: update a copy of the customer's settings (staging `stage.rechovyk.com.ua`) and compare the Prom feed before/after.

Planned work (not started; design agreed first):
- [ ] **English source strings**, Ukrainian as a translation (`.po/.mo` shipped in the Pro build); Ukrainian literals in logic (brand attribute names, «без бренду», default currency, Freemius texts) become settings or per-language lists.
- [ ] **Feed readers behind one interface** (no converter to YML): `Feed_Reader` with one implementation per format — YML (current logic moved as is), CSV/TSV, XLSX, custom XML, Google Shopping XML (RSS/Atom `g:`), JSON (file or URL). Each reader declares its own options (delimiter, encoding, sheet, item tag, list path) and yields the same item object; stock sync and import consume items, not `XMLReader`.
- [ ] **Field mapping** per supplier with auto-guess from the real columns/tags and a preview of the first rows; a dry run («N matched, N not found») before the first sync.
- [ ] **Product identification choice** per supplier: SKU (with prefix, as now) or a separate supplier-id meta field (prefix + vendor id).
- [ ] **Import page**: format select with the options of that format only; the same for the update page.
- [ ] **Sources UI refactor**: suppliers as a list with status and an «add source» wizard (URL/file → detected format + preview → mapping → identification and price rules → dry run); one source serves both import and update.
- [ ] **More marketplaces**: research the most used feed-based platforms and add them on `Marketplace_Feed` (candidates: Meta catalog, TikTok, Pinterest, Microsoft Merchant; EU price comparison — Ceneo, Heureka, Skroutz, Idealo).
- [ ] Order forwarding to the supplier (email/CSV) and tracking numbers back — expected under the «Dropshipping» name.
- [ ] Hide «fake discount» outside Ukraine (EU Omnibus directive).

### Next up — Prom feed (planned for 2026-10-15)
Found while comparing the feed with the Prom seller help (`support.prom.ua`, «Імпорт через YML — формат файлу», «Імпорт позицій із файла»).
- [ ] **Weight and dimensions (`<dimensions>`).** Prom accepts `<dimensions><weight unit="kg">…</weight><width unit="cm">…</width><height unit="cm">…</height><length unit="cm">…</length></dimensions>`; the feed does not send it although WooCommerce has the fields. Read them in `Marketplace_WC_Source::row()` (`$product->get_weight() / get_width() / get_height() / get_length()`, convert from the store units `woocommerce_weight_unit` / `woocommerce_dimension_unit` to kg and cm), send them from `Prom_Feed::compose_offer()` only when set, and take the parent's values for a variation without its own. Tests: unit conversion, variation fallback, no tag when empty.
- [ ] **Compressed feed (ZIP/GZIP).** Prom accepts ZIP and GZIP for large files (recommended YML size up to 180 MB); the plugin only publishes plain XML. Publish a `.xml.gz` next to the `.xml` (or as an option «Стиснути вигрузку») in `Marketplace_Job::finalize()` / `Marketplace_Storage`, show both links on the page, keep the 180 MB warning for the uncompressed size. Also covers the older item «Gzip the published file».
- [ ] **Stock quantity (`<quantity_in_stock>`).** Prom reads it (priority over `<stock_quantity>`); the feed does not send it although `Marketplace_WC_Source::row()` already has `Stock`. Send it for managed-stock products only.
- [ ] **Prom category list refresh.** `includes/marketplace/data/prom-categories.tsv` is a copy of `https://my.prom.ua/cabinet/export_categories/xls` (2026-10-14); rerun `bin/update-prom-categories.py` before releases. The picker flags ids that are no longer in the list.
- [ ] **Relative links in the description.** Descriptions with `src="/wp-content/…"` or `href="/…"` are not fetched by Prom. In `Prom_Feed::normalize_description_html()` turn root-relative `src` / `href` (and `srcset`) into absolute URLs with the feed's `site_url` setting (leave `http(s)://`, `//`, `data:`, `mailto:`, `tel:`, `#` alone). Tests: img src, a href, protocol-relative, already absolute, site in a sub-folder.

### Marketplaces module (next steps)
- [x] **Rozetka** feed (`Rozetka_Feed`, merged 2026-11-25). **Hotline** still open: new `Marketplace_Feed` subclasses (own `head/tail/process_unit`, field schema) registered through `f2000cs_marketplace_feeds`; reuse `Marketplace_WC_Source` rows, `Marketplace_Job` and the admin page tabs (placeholders already shown).
- [x] UI to map WooCommerce categories → marketplace portal categories (table on the Marketplaces page; rules match a path prefix).
- [x] Report of skipped products with the reason (no price / no photo / no category / duplicate variation params). A dry run is still open.
- [x] Filters per feed: rule builder «Відбір товарів у вигрузку» (brand, category, SKU, name, attribute, tag, price, stock, id). Price markup per marketplace is still open.
- [x] `Marketplace_WC_Source` pages by an ID cursor.
- [ ] Orphan variations (parent missing) and grouped products are not exported; the Python tool had a "lone variation" pass.
- [ ] Support `If-Modified-Since` for big feeds (gzip: see «Next up»).

### H — Stock sync safety
- [ ] **Mass-draft guard.** `XML_Stock_Updater::find_missing_products_in_xml()` drafts/zeroes everything absent from the XML with no threshold. Skip the "missing" step (and alert) when the feed looks broken: parse errors (`libxml_get_errors`, stop using `LIBXML_NOERROR` blindly), offer count far below the DB SKU count (e.g. < 50 %), HTTP body suspiciously small.
- [ ] **Variable parents reported as missing every run.** Parent SKU is `prefix + group_id` but only offer ids are compared. Add `group_id` values (prefixed) to the XML SKU set; stop re-reporting products that are already drafted.
- [ ] **No sync lock.** Add a transient/option mutex around `update_products_stock_status()` (cron + manual run + 5/10-minute intervals overlap). The "time limit approaching → continue next run" branch does not resume; store an offset or process in cron-chained batches.
- [ ] **M** `f2000cs_cleanup_wc_transients()` still deletes WooCommerce transients with leading-wildcard `LIKE '%…%'` scans of `wp_options`; replace with targeted `wc_delete_product_transients()` (the cache flush itself is already targeted — see Done).

### H — Import scalability
- [ ] `XML_Parser::import_products()` re-reads and re-extracts the whole feed for every product (limit 1). Index offers once (byte offsets or a temp SQLite/JSON index per session) or process a chunk of N products per request.

### M
- [ ] Feeds are downloaded into memory (`wp_remote_get`) in import and stock sync: use `stream => true` + `filename`, then `XMLReader` on the file. Consider `wp_safe_remote_get` for admin-supplied URLs.
- [ ] Telegram: `parse_mode=HTML` with unescaped text breaks on `<`/`&`; feed URLs (may carry tokens) are sent to chats and written to `error_log`; byte-based truncation can cut UTF-8. Escape / strip query strings, use `mb_strcut`.
- [ ] Global transients for import state (`f2000cs_import_variations_temp`, `f2000cs_selected_attributes_temp`) → pass through the parser constructor / per-session key.
- [ ] Verify on real WooCommerce: products are created with `wp_insert_post` + raw meta, `wc_product_meta_lookup` gets only `stock_status`; import never calls `f2000cs_sync_price_lookup`. Check price sorting/filter and `wc_get_product_id_by_sku()` for imported products; if broken, finish creation through `WC_Product` CRUD or `wc_update_product_lookup_tables()`.
- [ ] `group_id`/attribute names used in jQuery selectors (`admin-import.js`) break on special characters; use `.filter()` on data attributes.
- [ ] Remaining Pro limits in shared code (update interval, supplier slots, price adjustment, quantity options) are still "locked functionality" for wordpress.org — second phase of the free/Pro split.

### L
- [ ] Validate uploaded import file (`is_uploaded_file`, extension).
- [ ] SKU lookups scan `postmeta.meta_value`; use `wc_product_meta_lookup.sku` (and handle case-insensitive collations).
- [ ] XML editor "local path" source reads any XML readable by PHP; restrict to uploads.
- [ ] `composer audit`: PHP_CodeSniffer advisory GHSA-hmqg-cxww-wqhq (dev dependency, < 3.13.6).
- [ ] Tests for the import AJAX handlers and the main sync loop.
- [ ] Premium helpers left in the free build (`f2000cs_handle_export_download` & co. in `includes/functions.php`).
