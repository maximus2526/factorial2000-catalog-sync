# Tests

PHPUnit test suite for the Factorial2000 Catalog Sync plugin.

## Running

```bash
composer install          # one-time (installs phpunit, phpcs, wpcs)
composer test             # runs the suite
composer phpcs            # runs PHP_CodeSniffer (WordPress standard)
```

Or via the gulp build menu: `npx gulp` → "Запуск PHPUnit тестів" / "Перевірка PHPCS".

### Real supplier feed samples (offline)

Compact samples of live YML feeds live in `tests/fixtures/feeds/` (tens of KB each).
Full feeds are ~10–33 MB (powerplay varies) and are **never** downloaded during `composer test`.

| Slug | Type |
| --- | --- |
| `powerplay` | simple products only (no `group_id`) |
| `tactic-shop` | every offer has a unique `group_id` (lone → simple mode) |
| `armoline`, `vik-tailor`, `bm` | real variable groups (2+ offers) |

Refresh samples (network, ~15s):

```bash
php tests/bin/build-feed-samples.php
```

Optional live URL checks (skipped by default):

```bash
# Windows PowerShell
$env:F2000CS_LIVE_FEEDS=1; composer test -- --filter test_live_feed_urls_reachable_when_enabled
```

Private/tokenized feeds (e.g. Powerplay `hash_tag`) go in gitignored `tests/fixtures/feeds/urls.local.php` or env `F2000CS_FEED_URL_POWERPLAY` — never commit them.

## How the suite works

The suite runs **fully offline** — no WordPress or WooCommerce install, no
database, no network. `tests/bootstrap.php` loads the plugin's `includes/`
files behind a lightweight layer of WordPress function stubs
(`tests/includes/wp-stubs.php`):

- `get_option` / `update_option` / `get_transient` / `set_transient` — in-memory stores
- `wp_schedule_event` / `wp_next_scheduled` / `wp_clear_scheduled_hook` / single events — in-memory cron store
- `wp_remote_get` / `wp_remote_post` — programmable fake HTTP client (captures requests, serves queued responses)
- `add_action` / `add_filter` / `apply_filters` / `do_action` — hook registry
- `$GLOBALS['wpdb']` — fake query object with queueable results

`tests/includes/reflection.php` provides access to private/protected methods
and properties where a test needs to exercise internal logic.

## What is covered

| Module | Files under test | Highlights |
| --- | --- | --- |
| `PluginMetaTest` | main plugin file, readme.txt, all `includes/**/*.php` | version consistency (header / `F2000CS_VERSION` / Stable tag), header fields, namespace + ABSPATH guard for every include |
| `FunctionsTest` | `includes/functions.php` | supplier slots (keys, saved-data detection, visible slots), price-adjust settings, Telegram notifications (retry, truncation), background sync scheduling + batch counter |
| `CronJobTest` | `includes/class-cron-job.php` | schedule registration, activate/deactivate, interval change rescheduling, `update_stock()` end-to-end against a local XML fixture |
| `StockUpdaterTest` | `includes/class-stock-updater.php` | price adjustments (margin/markup/fixed, clamping, rounding), memory-limit parsing, full update flow with local XML + missing-product detection |
| `XmlParserTest` | `includes/parsers/class-xml-parser.php` | Cyrillic transliteration, offer data extraction, base-name extraction, variation attribute selection, category loading |
| `FieldsUpdaterTest` | `includes/parsers/class-fields-updater.php` | allowed-fields whitelist, field sanitization, per-offer dispatch decisions |
| `XmlExportFilterTest` | `includes/class-xml-export-filter.php` | SKU/group-id/min-price filtering (pure SimpleXML, runs against real feed fixtures) |
| `XmlEditorTest` | `includes/class-xml-editor.php` | category tree with descendant counts, offer listing/pagination, conditions, filtered XML generation (descendants, extra/excluded offers, oldprice stripping, SKU prefix) |
| `LicensingTest` | `includes/class-licensing.php` | Free vs Pro gates, Freemius trial wiring, supplier limits, daily update quota, option guards |
| `FreemiusUkI18nTest` | `includes/freemius-uk-i18n.php` | Ukrainian Freemius string map keys and completeness |
| `ExportPrefixWiringTest` | `admin/page-export.php` (+ settings) | runtime HTML prefill from `f2000cs_sku_prefix_1` + Settings API keys |
| `FrontendDisplayTest` | `includes/class-frontend-display.php` | WC-absent init guard, admin asset enqueue, vendor footer render |
| `AdminSourceTest` | `admin/page-import.php`, menu | behavioral AJAX nonce/cap/Pro gates + menu registration |

## What is NOT covered (and why)

- **Admin pages** (`admin/*.php`) — tightly coupled to the Settings API,
  `$_POST`/`$_FILES` flows and admin notices; needs a real WordPress install.
  Only source-level tests are run against them today.
- **Frontend rendering** (`Frontend_Display::render_*`) — requires WooCommerce
  product objects and template context.
- **Freemius SDK** (`freemius/`) — third-party SDK, excluded from tests and phpcs.
- **Database-heavy paths** — SKU lookups, `wc_product_meta_lookup` sync and the
  low-instock rule are exercised only through the fake `$wpdb` (empty results);
  real query behavior needs a WP + WooCommerce test install.
- **Remote HTTP** — all HTTP goes through the stub client; real endpoint
  behavior (Prom.ua feeds, Telegram API) is untested by design.

## Known gaps / follow-ups

- **Full integration suite** (Brain Monkey or the official
  `WP_UnitTestCase`/wp-env stack) is intentionally out of scope for this
  harness; happy-path import AJAX and real DB SKU lookups still need WP+WC.
