=== Prom XML Importer ===
Contributors: factorial2000
Donate link: https://send.monobank.ua/jar/8CiFBAfJKK
Tags: woocommerce, import, xml, stock, e-commerce, promua, prom
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import Prom.ua XML into WooCommerce. Sync stock and prices, import variations, filter feeds, and get Telegram alerts.

== Description ==

Prom XML Importer helps WooCommerce store owners synchronize their catalog with suppliers using Prom.ua XML exports.

**Key features:**

* Import simple and variable products from Prom.ua XML files
* Scheduled stock and price updates via WP-Cron (up to 5 XML sources)
* SKU prefixes per supplier feed
* Telegram notifications with detailed sync reports
* XML export filter to create a clean feed with new products only
* Batch processing optimized for large catalogs (10,000+ products)
* Clean uninstall removes plugin settings, transients, and cron jobs

**Requirements:**

* WordPress 5.8 or higher
* WooCommerce 3.0 or higher
* PHP 7.4 or higher with XML, SimpleXML, and cURL extensions

**Video setup guide:** [YouTube tutorial](https://www.youtube.com/watch?v=tdrMy7cAWEk)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/prom-xml-importer` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Make sure WooCommerce is installed and active.
4. Open **Оновлення XML** in the admin menu.
5. Add your supplier XML URL, choose the update interval, and optionally configure Telegram notifications.
6. Use **Імпорт XML** for the initial product import from an uploaded XML file.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. Prom XML Importer is built specifically for WooCommerce product import and synchronization.

= Which XML format is supported? =

The plugin supports Prom.ua YML/XML export format with `offer`, `group_id`, `price`, `oldprice`, and related fields.

= Can I connect multiple suppliers? =

Yes. You can configure up to 5 XML URLs. Each feed can have its own SKU prefix and optional rule to skip price updates.

= What happens to products missing from the XML feed? =

During scheduled updates, missing variations can be marked out of stock. Simple products may be moved to draft when configured with a SKU prefix.

= Does it work on shared hosting? =

Yes. The plugin uses batch processing and memory optimizations. For very large catalogs, use WP-Cron or server cron instead of running updates in the browser.

= Where can I report bugs or request features? =

Please use the support tab on WordPress.org.

== Screenshots ==

1. Stock update settings with XML URLs, cron interval, and Telegram options.
2. XML import page with simple and variable product modes.
3. Variable product group analysis with attribute selection.
4. XML export filter for creating a clean feed with new products only.
