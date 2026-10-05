# Backlog (from the code audit)

Status legend: `[ ]` open · `[x]` done. Severity: **H** high · **M** medium · **L** low.
File:line references are from the audit and may have shifted.

## Done

- [x] **M** Import session id was mixed-case but read through `sanitize_key()` → lowercased at generation (`admin/page-import.php`).
- [x] **M** `admin-import.js`: supplier-controlled attribute names and server messages were rendered with `.html()` → now text only (DOM-XSS).
- [x] **L** Uninstall now also removes `f2000cs_*` transients (import cache, resume checkpoints, download tokens).
- [x] **H** Products the plugin drafted because they vanished from the feed are published again when the feed lists them (marker meta `_f2000cs_auto_drafted`, filter `f2000cs_republish_returned_products`).

## Open

### H — Stock sync safety
- [ ] **Mass-draft guard.** `XML_Stock_Updater::find_missing_products_in_xml()` drafts/zeroes everything absent from the XML with no threshold. Skip the "missing" step (and alert) when the feed looks broken: parse errors (`libxml_get_errors`, stop using `LIBXML_NOERROR` blindly), offer count far below the DB SKU count (e.g. < 50 %), HTTP body suspiciously small.
- [ ] **Variable parents reported as missing every run.** Parent SKU is `prefix + group_id` but only offer ids are compared. Add `group_id` values (prefixed) to the XML SKU set; stop re-reporting products that are already drafted.
- [ ] **No sync lock.** Add a transient/option mutex around `update_products_stock_status()` (cron + manual run + 5/10-minute intervals overlap). The "time limit approaching → continue next run" branch does not resume; store an offset or process in cron-chained batches.
- [ ] **`wp_cache_flush()` in cron** (`class-stock-updater.php`, `functions.php` `f2000cs_cleanup_wc_transients`) flushes the whole site cache with Redis/Memcached. Replace with targeted `wc_delete_product_transients()` / cache group invalidation; drop the `LIKE '%…%'` scan of `wp_options`.

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
