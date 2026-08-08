<?php
/**
 * Test bootstrap.
 *
 * Loads lightweight WordPress stubs so the plugin's critical logic can be
 * unit-tested without a full WordPress + WooCommerce install.
 *
 * @package Factorial2000_Catalog_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

// WordPress time constants used by the plugin.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * 86400 );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 30 * 86400 );
}

require_once __DIR__ . '/includes/wp-stubs.php';
require_once __DIR__ . '/includes/reflection.php';
require_once __DIR__ . '/includes/BaseTestCase.php';

// Keep Freemius credentials in placeholder state so the SDK is never
// bootstrapped during tests (f2000cs_fs() returns null).
if ( ! defined( 'F2000CS_FS_ID' ) ) {
	define( 'F2000CS_FS_ID', 'YOUR_PLUGIN_ID' );
}
if ( ! defined( 'F2000CS_FS_PUBLIC_KEY' ) ) {
	define( 'F2000CS_FS_PUBLIC_KEY', 'YOUR_PUBLIC_KEY' );
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/class-cron-job.php';
require_once __DIR__ . '/../includes/class-licensing.php';
require_once __DIR__ . '/../includes/freemius-uk-i18n.php';
require_once __DIR__ . '/../includes/class-stock-updater.php';
require_once __DIR__ . '/../includes/parsers/class-xml-parser.php';
require_once __DIR__ . '/includes/ImportSpyParser.php';
require_once __DIR__ . '/../includes/parsers/class-fields-updater.php';
require_once __DIR__ . '/../includes/trait-xml-rebuilder.php';
require_once __DIR__ . '/../includes/class-xml-export-filter.php';
require_once __DIR__ . '/../includes/class-xml-editor.php';
require_once __DIR__ . '/../includes/class-image-processor.php';
require_once __DIR__ . '/../includes/class-frontend-display.php';

if ( ! defined( 'F2000CS_VERSION' ) ) {
	define( 'F2000CS_VERSION', '0.6.5' );
}

if ( ! defined( 'F2000CS_DB_VERSION' ) ) {
	define( 'F2000CS_DB_VERSION', 1 );
}

if ( ! defined( 'F2000CS_URL' ) ) {
	define( 'F2000CS_URL', 'http://example.org/wp-content/plugins/factorial2000-catalog-sync/' );
}
