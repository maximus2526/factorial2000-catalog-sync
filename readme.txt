=== Factorial2000 Catalog Sync for Prom.ua ===
Contributors: factorial2000
Donate link: https://send.monobank.ua/jar/8CiFBAfJKK
Tags: woocommerce, import, xml, stock, prom
Requires at least: 5.8
Tested up to: 7.0.4
Requires PHP: 7.4
Stable tag: 0.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import Prom.ua/YML feeds into WooCommerce, sync stock and prices, export your products to Prom.ua. Free forever + 14-day Pro trial.

== Description ==

This plugin connects your WooCommerce store with supplier XML feeds for automatic catalog synchronization.

**Standout features (Pro):** the Marketplaces page publishes your WooCommerce catalog as a Prom.ua YML feed at a permanent URL and can rebuild it after every stock sync, so Prom always gets current prices and stock. The XML Editor lets you visually build a custom Prom.ua export — pick categories and products, apply price/stock filters, and generate a ready-to-upload XML in seconds.

**Key features:**

* Free forever plan + **14-day Pro trial with no credit card** (full Pro features during the trial in the Pro version, which Freemius offers to install when the trial starts)
* Import simple and variable products from XML — names, descriptions, images (picture), attributes (param), categories, weight, barcode, dimensions
* Automatic stock status and price sync via WP-Cron: daily in Free (plus 3 manual runs a day); in Pro every 5/10/15/30 minutes, hourly, 8/6/4 times daily, twice daily or daily, with no manual-run limit
* Supplier slots with individual SKU prefixes and a skip-price option (Free: 1 supplier; Pro: multiple suppliers and stock quantity updates). Price adjustments — margin/markup/fixed — are available for every supplier on all plans
* Telegram notifications with sync reports
* XML export (Pro): filter to keep only new products, or use the visual XML editor to build a custom feed by category/product — **key differentiator** for marketplace sellers
* Marketplaces (Pro): export WooCommerce products to Prom.ua as a YML feed (variations, brands, Prom categories); rebuilt on a schedule and/or right after each stock sync
* Fields updater (Pro): update selected fields (name, description, images, attributes, categories, tags, vendorCode) on already-imported products
* Image processing (Pro): convert PNG/JPG/WebP/AVIF to WebP, AVIF or JPG on import (GIF and BMP unchanged); optimize, resize
* Currency conversion via XML currencies/currencyId
* Built-in documentation page with sticky sidebar navigation
* Clean uninstall removes plugin settings, transients, cron jobs, generated exports and marketplace feeds
* Automated test suite: 300+ PHPUnit unit tests (offline stubs, no WordPress/WooCommerce required to run) covering XML parse/import modes, stock sync, fields update, export filter/editor, marketplace feeds, licensing, and admin AJAX security gates

**Requirements:**

* WordPress 5.8 or higher
* WooCommerce 3.0 or higher
* PHP 7.4 or higher with XML, SimpleXML, and cURL extensions

**Video setup guide:** [YouTube tutorial](https://www.youtube.com/watch?v=tdrMy7cAWEk)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/factorial2000-catalog-sync` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Make sure WooCommerce is installed and active.
4. Open **Синхронізація каталогу → Оновлення XML** in the admin menu.
5. Add your supplier XML URL, SKU prefix (e.g. `SUP_`), and choose the update interval.
6. Go to **Імпорт XML** for the initial product import from your XML file.
7. Optionally configure Telegram notifications and cron interval on the **Оновлення XML** page.
8. Refer to the **Документація** tab for full usage details.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. This plugin is built specifically for WooCommerce product import and synchronization.

= Which XML format is supported? =

The plugin supports YML format used by Prom.ua and many Ukrainian suppliers. Both simple `<offer>` and variable products (with `group_id`) are supported. See the built-in Documentation page for the full field reference.

= Can I connect multiple suppliers? =

Yes, in Pro. Each supplier slot has its own XML URL, SKU prefix and an option to skip price updates; Price adjustment rules (margin, markup, fixed amount) work on every plan; Pro adds stock quantity updates per slot. The Free plan syncs one supplier (slots saved earlier, e.g. during a trial, stay editable). Pro and the free 14-day trial (no credit card) also unlock more frequent cron intervals.

= Is there a free Pro trial? =

Yes. You can start a **14-day Pro trial without entering a credit card**. Pro features live in the Pro version of the plugin: install it from your Freemius account (or the prompt shown after the trial starts) and during the trial you get the same features as Pro. After it ends, the Free plan limits apply again unless you upgrade.

= Can I export my WooCommerce products to Prom.ua? =

Yes, in Pro: open **Синхронізація каталогу → Маркетплейси**, set up the Prom.ua feed and give Prom the feed URL shown on the page (Prom → import from a link). The feed contains published products with a price, a photo and a category (drafts can be included with an option); out-of-stock products are listed with `available="false"`. It is built in the background and can be refreshed on a schedule and/or after every stock sync («Після оновлення наявності»). The feed URL contains a secret token; anyone who knows it can read your product data, the same as Prom does.

= What happens to products missing from the XML feed? =

During scheduled stock updates, if a SKU prefix is configured, the plugin detects products in WooCommerce with that prefix that are no longer present in the XML. Simple products are moved to "Draft". Variations are marked "Out of stock" (variable parent products are not drafted). If no SKU prefix is set, this check is skipped. Ensure your XML feed always contains the full catalog — a truncated feed can hide many simple products.

= Does it work on shared hosting? =

Yes. The plugin uses XMLReader streaming for efficient parsing and supports both background (WP-Cron) and synchronous update modes. For large catalogs, use the background mode. The import processes products one at a time via AJAX, which works well on most shared hosts for catalogs up to a few thousand products.

= In which language are the plugin screens and documentation? =

The plugin interface and the built-in documentation page are in Ukrainian.

= Where can I report bugs or request features? =

Please use the support tab on WordPress.org.

== External services ==

This plugin connects to external services. Both are optional and are only used when you configure them.

**Supplier XML feeds (e.g. Prom.ua)**

When you enter an XML feed URL in the settings, the plugin downloads that file during the initial import and during scheduled stock/price updates. Only a request to the URL you provide is made and no personal data is sent. The feed itself is provided by your supplier (for example Prom.ua); please refer to that provider's own terms and privacy policy.

**Telegram Bot API**

If you enter a Telegram bot token and one or more chat IDs in the settings, the plugin sends notification messages to the Telegram Bot API (https://api.telegram.org) after an import or a stock/price update completes. The data sent includes your bot token, the target chat ID(s) and the text of the sync report (for example counts of updated or missing products). Requests are made only when Telegram notifications are configured and an update runs.

This service is provided by Telegram. By enabling notifications you agree to Telegram's Terms of Service (https://telegram.org/tos) and Privacy Policy (https://telegram.org/privacy).

== Screenshots ==

1. Stock update settings: supplier XML URLs, SKU prefix, price adjustment, cron interval and Telegram notifications.
2. XML import page with simple and variable product modes and image handling options.
3. Marketplaces (Pro): the Prom.ua feed at a permanent link, rebuilt on a schedule or after every stock sync.
4. Built-in documentation with sticky sidebar navigation.

== Upgrade Notice ==

= 0.7.0 =
Pro features moved to a separate Pro build. If you use a Pro license, stock quantity updates or the marketplace feed, install the Pro version from your Freemius account before updating. Price markup now works in the Free version too.

== Changelog ==

= 0.7.0 =
**Important:** Pro features are now shipped only in the Pro version of the plugin. If you have a Pro license or an active trial and update from WordPress.org, you get the Free version and the Pro features stop working until you install the Pro version from your Freemius account. The plugin shows a notice with the link.

New:
* Price adjustment per supplier (markup / margin / fixed amount) is now free.
* (Pro) Prom categories are picked by name: the Prom category list is built in, so the store-to-Prom table, the default category and the new «Prom.ua» box in the product editor search it and fill in the id. A category chosen on the product wins over the category rules (Prom ranks the offer's portal_category_id first); variations use their product's.
* Import option «Імпортувати тільки товари в наявності» (import in-stock products only): out-of-stock offers are skipped, a variable product gets only its in-stock variations, and a group with nothing in stock is skipped.
* (Pro) «Marketplaces» page: publish your WooCommerce products to Prom.ua as a feed at a permanent private link. Simple and variable products, brands and Prom categories are supported, and large catalogs work too. The feed is refreshed on a schedule or from the command line: `wp f2000cs marketplace build prom`.
* (Pro) Option «Після оновлення наявності» (after stock update): after every stock and price sync the Prom feed is rebuilt in the background and published at the same link, so Prom always gets current prices and stock.
* Products that the stock sync set to Draft because they disappeared from the supplier feed are published again when they return to the feed. Products you drafted yourself are never touched, and drafted products are no longer reported on every run.
* A notice with a link to the Freemius account when a Pro license or trial is active but the Free version is installed.

Improved:
* (Pro) Marketplaces settings are mostly one-click presets. Brand names and store-to-Prom category mapping use searchable tables instead of text fields.
* Ukrainian translation of the Freemius screens (about 440 more strings).
* The Import page no longer remembers the last XML URL: the URL field starts empty (the SKU prefix is still remembered).
* (Pro) Prom categories are chosen in a searchable Select2 field (type a name or the start of an id) with a link to Prom's official category list with ids. The brand table lists the shop's brands: fill «Brand in Prom» only where the name differs.
* (Pro) The Prom feed page shows why products were left out (no price, picture, category …) with example SKUs.
* (Pro) The Prom feed follows Prom's import rules: the article (vendorCode) is cut to 25 allowed characters, the product code is sent as <gtin> (Prom reads <barcode> as an article), a name without letters is skipped, and the page warns about an unsupported currency and a file over 180 MB. It also explains that Prom takes prices and stock every 4 hours.
* (Pro) Product descriptions sent to Prom lose shortcodes and block editor comments; line breaks of classic descriptions are kept as paragraphs. Picture URLs with Cyrillic file names are encoded.
* (Pro) «Unpublished products» now also exports draft variations and no longer exports private products.
* A broken supplier feed no longer leaves stale stock on the site: if the XML is torn (fatal parse error) or has no offers, every product and variation with that supplier's SKU prefix is set out of stock until a valid feed returns, and the Telegram message says why the feed failed. Without a SKU prefix nothing is touched, so the whole shop can never be hit.
* Long import and sync runs no longer flush the whole object cache — only the transients cache group is cleared — so the import session cache and other caches survive a run. Plugin temp files older than two hours are removed by a daily cron event and by an hourly admin sweep.
* With the Factorial2000 Translator active and both Russian and Ukrainian enabled, the Prom feed sends the Russian text in <name>/<description> and the Ukrainian one in <name_ua>/<description_ua> (a missing Russian translation falls back to the site language); language tags never leak into the feed.

Fixed:
* After switching from Free to Pro the scheduled stock sync stopped until the next manual run. The schedule is now restored on activation and follows the real interval when a trial starts or ends.
* A marketplace feed is no longer left frozen when Pro is not active: the published file is withdrawn once it is more than 3 days old. Deactivation stops marketplace schedules; uninstall removes marketplace settings, feed files and temporary import data.
* `wp f2000cs marketplace build` now requires an active Pro license or trial.
* Price adjustment (markup / margin / fixed amount) is now available in the Free version, so a saved rule keeps working when Pro is not installed. With a license or trial but without the Pro version installed, stock statuses of products with managed quantity are left alone when quantity updates were saved before; install the Pro version to resume them.
* Switching between Free and Pro no longer starts a full stock sync right away, and saving the settings on the Free version no longer overwrites the saved update interval.
* The «Pro version missing» notice is shown on every admin screen and also when Pro settings are saved without a license.
* (Pro) A Prom feed that suddenly has less than half the products of the last published one is held back (a broken supplier feed can no longer empty the catalog on Prom); a button publishes it when the shrink is expected.
* (Pro) If the built Prom feed cannot replace the published file, the job now reports an error instead of «done», and the previous feed stays at the same link.
* (Pro) A stock sync during a long feed build no longer restarts it; one more build runs when it finishes. Products drafted during a build are no longer skipped, and two builds can no longer run at once.
* (Pro) Prom category rules match the start of the category path: «Одяг» no longer catches «Дитячий одяг».
* (Pro) A variation without a size is no longer given a guessed size unless only one size is left for it.
* Products that 0.6.x set to Draft are not published again automatically: only products drafted by 0.7.0 or later are.
* A trial started on the Free version no longer shows a «full Pro access» countdown; it explains that the Pro version has to be installed.
* Activating Free and Pro versions together no longer mixes up which copy is premium.
* A URL import could leave hundreds of leftover files in the system temp folder: the feed cache lived in a transient that the plugin's own cache cleanup dropped mid-import, so every batch request downloaded the feed into a new temp file. The cached download now uses a deterministic per-session file and is reused while it exists; uninstall removes these files too.

Security:
* Attribute names and server messages from a supplier feed are shown as plain text on the import analysis screen.

For developers:
* A free-vs-Pro build simulation now runs in CI so that Pro code can never leak into the Free build.
* Import session keys are lowercase, consistent with how they are read.

= 0.6.14 =
* Added: Pro option on the Import page — «Import stock quantity from XML». Reads `<quantity>`, `<stock_quantity>` or `<qty_stock>` (decimal values such as `1.000` are supported) and enables stock management for simple products and variations. Off by default.
* Added: `<qty_stock>` (OneBox) is now recognized by the scheduled stock sync and the fields updater, not only by import.
* Fixed: offers with an empty `available=""` attribute (OneBox) were treated as in stock — they are now out of stock. If the attribute is missing entirely, the quantity decides (above 0 = in stock), otherwise the offer stays in stock.
* Fixed: variations that share the parent's first picture no longer download the same image again for every size (faster imports, no duplicate media files).
* Changed: stock quantity is no longer imported unconditionally (0.6.11 behavior) — it follows the new import option.
* Improved: documentation updated for qty_stock, available handling, quantity import, «Новинки» subcategories and the new update intervals; the XML editor treats `available="y"` as in stock.

= 0.6.13 =
* Added: option to add product categories from the XML feed as subcategories under "Новинки" (instead of creating separate category trees) — improves organization for feeds with many categories.
* Improved: import checkpoint now includes the new `new_category_subcats` option for seamless resume of imports with category nesting enabled.

= 0.6.12 =
* Fixed: the XML editor (Pro) failed to list any products after selecting a category — the session token generated on load could contain uppercase letters, which was silently lowercased on every later request, breaking the lookup on case-sensitive (Linux) hosting.

= 0.6.11 =
* Added: three new update intervals for Pro — 4, 6 and 8 times a day.
* Fixed: stock quantity from XML (`<quantity>` or `<stock_quantity>`) is now properly extracted and imported during product import (both simple and variable products/variations).
* Fixed: `_manage_stock` is now automatically enabled when quantity is available from XML during import (previously always set to 'no').
* Improved: stock quantity handling is now consistent across import, field updates, and scheduled stock sync operations.

= 0.6.10 =
* Fixed: the Freemius setup wizard now appears after activation, so new users can opt in and start the 14-day Pro trial (SDK was initialized too late to register its activation hook).
* Improved: compatibility verified with WordPress 7.0.4.

= 0.6.9 =
* Fixed: a resumed import can now be stopped — the stop button stays active during resume and shows «stopped» instead of a fake 100% completion.
* Fixed: generated XML download links could die with «link expired» — downloads now use a server-side token instead of a nonce, and the button label no longer repeats the long filename.
* Fixed: stock quantity updates no longer revert stock status and prices written by the same sync run (Pro).
* Fixed: batch processing now respects the raised execution-time limit, so large catalogs keep making progress on shared hosting.
* Fixed: aggressive transient cleanup no longer deletes transients belonging to other plugins.
* Fixed: temporary currency files are removed on all exit paths (no temp-file leaks).
* Fixed: per-product update failures are now logged instead of being silently swallowed (capped at 10 lines per run to protect the log).
* Fixed: field updates (title, description, short description) now report and log failures instead of silently claiming success.
* Fixed: leftover temporary files are removed after image conversion, and the export page now checks admin capabilities explicitly.
* Improved: image processing settings default to «Не конвертувати» and their tooltips explain that processing loads the server and can slow the import down.
* Security: SSL/TLS certificate verification is now enabled for feed and image downloads by default; a new «Weak SSL certificates» setting (Update page) lets you opt out for suppliers with self-signed or expired certificates, as does the `f2000cs_ssl_verify` filter.
* Security: XML feeds are parsed with network entity loading disabled (XXE hardening) and feed content (titles/descriptions) is sanitized before it is stored.

= 0.6.8 =
* Fixed: GIF and BMP are not converted or re-encoded on import (GIF animation preserved). Conversion applies to PNG, JPG, WebP, and AVIF only.

= 0.6.7 =
* Added: image conversion now covers ALL source formats (PNG, JPG, WebP, AVIF, GIF) — not just PNG — with same-format and SVG passthrough. UI label shortened to «Формат зображень».
* Added: Free plan now allows 3 manual stock updates per day (was 1).
* Fixed: the scheduled cron no longer consumes or is blocked by the Free manual-update quota.
* Fixed: Freemius `is_premium` flag set to false for the wp.org build so the 14-day trial offer works on manual installs.

= 0.6.6 =
* Added: PNG → AVIF conversion on import (Pro), with host capability check.
* Improved: the Import page remembers the last XML URL and SKU prefix per admin.
* Fixed: Freemius 14-day Pro trial starts without a credit card.

= 0.6.5 =
* Improved: wordpress.org short description trimmed to the 150-character limit.

= 0.6.4 =
* Improved: Freemius free license activation UX and Ukrainian opt-in strings.

= 0.6.3 =
* Fixed: Freemius plugin-scope API paths (avoid duplicated /plugins/{id}/ prefix).

= 0.6.2 =
* Fixed: Freemius deploy uses plugin-scope API keys (Product → Settings → Keys).

= 0.6.1 =
* Fixed: Freemius GitHub deploy now fails loudly on API errors (custom deploy script).

= 0.6.0 =
* Added: Freemius freemium packaging with GitHub Actions deploy on `v*` tags.
* Fixed: removed `uninstall.php` — cleanup runs on Freemius `after_uninstall`.
* Improved: import progress UI and admin assets cache-busting.

= 0.5.9 =
* Fixed: removed `uninstall.php` for Freemius deployment — cleanup now runs on Freemius `after_uninstall`.

= 0.5.8 =
* Fixed: import progress panel styles now load after CSS updates (asset version bump + reliable show/hide without stale browser cache).
* Improved: progress panel visibility is driven by `.is-active` and fully clears the HTML `hidden` attribute.

= 0.5.7 =
* Fixed: XML export filter now reads the SKU prefix from the correct slot-1 option (`f2000cs_sku_prefix_1`) instead of the legacy key.
* Fixed: XML Editor and export filter parse XML elements and attributes case-insensitively (`categoryId`/`CATEGORYID`/`parentID` etc.).
* Fixed: numeric XML category/offer IDs round-trip as strings so the JS tree and product selection work correctly.
* Fixed: export page shows the correct product count and pre-fills the right prefix.
* Fixed: `<offer available>` now correctly handles values `1`, `yes`, `y`, `True`, `TRUE` in addition to `true` (YML spec compliance).
* Fixed: `<currencyId>` and `<currencies>` are now parsed — offer prices are converted using the currency rate when the offer currency differs from the store's default.
* Fixed: `<price>` with European comma-as-decimal formatting (e.g. "1.999,00") is now parsed correctly.
* Fixed: multiple `<categoryId>` elements per offer are now supported (comma-joined).
* Fixed: `<param>` elements with the same `name` are now concatenated with "; " instead of overwriting.
* Fixed: `<param unit="kg">` is now appended to the attribute value (e.g. "5 кг").
* Fixed: `download_url()` calls now disable SSL verification, allowing image downloads from servers with self-signed certificates.
* Fixed: `wc_product_meta_lookup` price columns are now synced after stock update price changes (HPOS compatibility).
* Added: `<weight>`, `<barcode>`, `<dimensions>` are now imported as product meta (`_weight`, `_barcode`, `_dimensions`).
* Added: cron intervals `10_minute`, `15_minute`, `30_minute` for finer scheduling granularity (Pro).
* Added: Documentation admin page with sticky sidebar navigation covering the full plugin functionality.
* Added: unit test harness with WP-function stubs (200+ tests, runs offline without WordPress/WooCommerce).
* Added: Gulp build system — interactive menu, SVN-ready release packaging, version bumping across all files, phpcs/phpunit runs.
* Added: PHPCS ruleset (WordPress standard) with project-specific cosmetic-sniff disables.

= 0.5 =
* Added: **XML Editor** — build a filtered export by selecting categories and/or individual offers, with conditions (only in stock, min/max price, keep oldprice) and live product thumbnails. Accessible from **Налаштування вигрузки**.
* Added: **Image processing (Pro)** — convert PNG to WebP/JPG, optimize, and resize product images on import.
* Added: **Fields Updater** — update selected fields (name, description, price, images, etc.) of products that are already on the site, matched by SKU.
* Added: case-insensitive parsing of XML feeds that use non-standard element casing.
* Added: modular admin pages split from the monolithic settings page.
* Improved: admin UI with collapsible category tree, search, and clearer supplier management.
* Improved: background sync event handling and transient cleanup.

= 0.4 =
* Replaced the simple per-supplier margin (%) field with a price adjustment switcher: choose between Маржа (margin, always added, 0-99%), Націнка (markup %, can be added or subtracted) or Фіксована сума (a flat currency amount, can be added or subtracted). Available when price updates are allowed for that supplier.

= 0.3 =
* First release on WordPress.org.
* Renamed functions, options, hooks, and assets to the f2000cs_ prefix for consistency.
* Bumped version and aligned plugin branding for the directory listing.

= 0.2 =
* Prepared the plugin for the WordPress.org directory: code now follows WordPress coding and security standards.
* Security: added nonce verification, capability checks, input sanitization and output escaping across all admin actions.
* All remote requests now use the WordPress HTTP API (wp_remote_get/wp_remote_post) instead of cURL and file_get_contents.
* Added a unique prefix and namespace to all functions, classes, options, hooks and meta keys to avoid conflicts with other plugins.
* Added a "Telegram notifications" setup guide and clearer field hints (how to get the bot token and chat ID).
* Documented external services (Telegram Bot API and supplier XML feeds) in the readme.
* Renamed the plugin to "Factorial2000 Catalog Sync for Prom.ua and WooCommerce" and changed the admin menu label to "Catalog Sync".
* Added a WooCommerce activation check with an admin notice when WooCommerce is missing.

= 0.1 =
* Initial version: import of simple and variable products from Prom.ua XML, scheduled stock/price updates, XML export filter and Telegram notifications.
