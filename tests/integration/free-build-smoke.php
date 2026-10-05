<?php
/**
 * Smoke test for a free or Pro build of the plugin (offline, WordPress stubs).
 *
 * Usage: php tests/integration/free-build-smoke.php <plugin-dir> <free|pro>
 *
 * Normally run through bin/simulate-free-build.php, which builds both variants
 * the way Freemius does (premium-only files removed from the free one).
 *
 * @package Factorial2000_Catalog_Sync
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$repo = dirname( __DIR__, 2 );
$dir  = rtrim( (string) ( $argv[1] ?? '' ), '/\\' );
$mode = (string) ( $argv[2] ?? '' );

if ( ! is_file( $dir . '/factorial2000-catalog-sync.php' ) || ! in_array( $mode, array( 'free', 'pro' ), true ) ) {
	fwrite( STDERR, "Usage: php free-build-smoke.php <plugin-dir> <free|pro>\n" );
	exit( 2 );
}

define( 'ABSPATH', $repo . '/' );
define( 'WP_DEBUG', false );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'F2000CS_FS_ID', 'YOUR_PLUGIN_ID' ); // Keeps the Freemius SDK from booting.
define( 'F2000CS_FS_PUBLIC_KEY', 'YOUR_PUBLIC_KEY' );

require_once $repo . '/vendor/autoload.php';
require_once $repo . '/tests/includes/wp-stubs.php';
require_once $repo . '/tests/includes/reflection.php';

// Functions the main plugin file calls at load time that the shared stubs lack.
if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_dir_url( $file ) {
		return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * @return void
	 */
	function register_activation_hook() {}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * @return void
	 */
	function register_deactivation_hook() {}
}

$pass = 0;
$fail = 0;

/**
 * @param string $label  Check label.
 * @param bool   $ok     Result.
 * @param string $detail Extra info on failure.
 * @return void
 */
function smoke_check( string $label, bool $ok, string $detail = '' ): void {
	global $pass, $fail;
	echo ( $ok ? '  PASS' : '  FAIL' ) . ' | ' . $label . ( $detail && ! $ok ? ' | ' . $detail : '' ) . PHP_EOL;
	$ok ? ++$pass : ++$fail;
}

/**
 * @param callable $cb Callback that echoes.
 * @return string
 */
function smoke_capture( callable $cb ): string {
	$level = ob_get_level();
	ob_start();
	try {
		$cb();
	} finally {
		$out = (string) ob_get_clean();
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
	}
	return $out;
}

/**
 * @param callable $cb Callback that may end with wp_send_json_*.
 * @return F2000CS_JsonResponseException|null
 */
function smoke_json( callable $cb ) {
	try {
		$cb();
	} catch ( F2000CS_JsonResponseException $e ) {
		return $e;
	}
	return null;
}

// ---------------------------------------------------------------- load plugin

require_once $dir . '/factorial2000-catalog-sync.php';

$pro = ( 'pro' === $mode );

echo "[{$mode}] plugin loaded\n";
smoke_check( 'main file loads without fatal', defined( 'F2000CS_VERSION' ) );
smoke_check( 'f2000cs_has_premium_code() matches build', f2000cs_has_premium_code() === $pro );

$classes = array( 'F2000CS\\Image_Processor', 'F2000CS\\Fields_Updater', 'F2000CS\\XML_Editor', 'F2000CS\\XML_Export_Filter' );
foreach ( $classes as $class ) {
	smoke_check( $class . ( $pro ? ' present' : ' absent' ), class_exists( $class, false ) === $pro );
}

$functions = array( 'f2000cs_xml_editor_ajax', 'f2000cs_register_image_settings', 'f2000cs_get_recent_export_files' );
foreach ( $functions as $fn ) {
	smoke_check( $fn . '() ' . ( $pro ? 'present' : 'absent' ), function_exists( $fn ) === $pro );
}

smoke_check( 'free-core classes always present', class_exists( 'F2000CS\\XML_Parser', false ) && class_exists( 'F2000CS\\XML_Stock_Updater', false ) );

// ---------------------------------------------------------------- Pro gate

smoke_check( 'f2000cs_is_pro() is false without license', false === f2000cs_is_pro() );

add_filter( 'f2000cs_is_pro', '__return_true' ); // Simulate an active license / trial.

// ---------------------------------------------------------------- pages

$_GET['page'] = 'f2000cs-import';
$html         = smoke_capture( 'f2000cs_import_page' );
smoke_check( 'import page renders', false !== strpos( $html, 'import_xml_url' ) );
smoke_check( 'import page has image panel section', false !== strpos( $html, 'f2000cs_images_section' ) );
smoke_check(
	'image settings fields ' . ( $pro ? 'present' : 'absent' ),
	( false !== strpos( $html, 'f2000cs_img_png_convert' ) ) === $pro
);

$html = smoke_capture( 'f2000cs_export_page' );
if ( $pro ) {
	smoke_check( 'export page shows the XML editor', false !== strpos( $html, 'Редактор вигрузок XML' ) );
} else {
	smoke_check( 'export page is an upsell placeholder', false !== strpos( $html, 'Pro-версії' ) && false === strpos( $html, 'f2000cs-xml-editor-load' ) );
}

$html = smoke_capture( 'f2000cs_update_page' );
smoke_check( 'update page renders', false !== strpos( $html, 'Запуск оновлення' ) );

smoke_check( 'settings init runs', null === smoke_json( 'f2000cs_settings_init' ) );

// ---------------------------------------------------------------- assets

$screen                             = new stdClass();
$screen->id                         = 'catalog-sync_page_f2000cs-export';
F2000CS_Test_State::$current_screen = $screen;
$_GET['page']                       = 'f2000cs-export';
f2000cs_enqueue_admin_assets( '' );
smoke_check(
	'XML editor script ' . ( $pro ? 'enqueued' : 'not enqueued' ),
	array_key_exists( 'f2000cs-admin-xml-editor', F2000CS_Test_State::$enqueued_scripts ) === $pro
);

// ---------------------------------------------------------------- AJAX

$_POST['f2000cs_import_nonce'] = 'x';
$response                      = smoke_json( 'f2000cs_handle_update_fields_action' );
smoke_check( 'update-fields handler answers with JSON (no fatal)', null !== $response );
if ( ! $pro && $response ) {
	smoke_check( 'free build: update-fields refuses even with a license filter', false === $response->success );
}

// ---------------------------------------------------------------- shared code paths

$parser = new F2000CS\XML_Parser( 'x.xml', false, 'T_' );
$offer  = simplexml_load_string( '<offer id="1" available=""><price>10</price><qty_stock>2.000</qty_stock><picture>http://x/a.jpg</picture></offer>' );
$data   = F2000CS_Test_Reflection::invoke( $parser, 'extract_offer_data', array( $offer ) );
smoke_check( 'offer parsing works in this build', 2 === $data['quantity'] && 'outofstock' === $data['available'] );

echo "[{$mode}] {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
