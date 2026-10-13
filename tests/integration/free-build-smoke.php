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
// Credentials look real, but the SDK never boots: f2000cs_fs() returns the
// fake below because $f2000cs_fs is already set (license / trial scenarios).
define( 'F2000CS_FS_ID', '99999' );
define( 'F2000CS_FS_PUBLIC_KEY', 'pk_smoke' );

/**
 * Stand-in for the Freemius instance: license and trial state are plain fields.
 */
final class F2000CS_Smoke_Freemius {
	/** @var bool */
	public $paying = false;
	/** @var bool */
	public $trial = false;
	/** @var string */
	public $trial_ends = '';

	public function is_paying() {
		return $this->paying;
	}

	public function is_trial() {
		return $this->trial;
	}

	public function get_site() {
		return (object) array( 'trial_ends' => $this->trial_ends );
	}

	public function get_upgrade_url() {
		return 'https://example.org/upgrade';
	}

	public function get_account_url() {
		return 'https://example.org/account';
	}
}

$f2000cs_fs = new F2000CS_Smoke_Freemius();

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
// Rendering helpers used only by the Pro marketplaces page.
if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param array  $args Query args.
	 * @param string $url  Base URL.
	 * @return string
	 */
	function add_query_arg( $args, $url = '' ) {
		return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . http_build_query( (array) $args );
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * @param string $format Format.
	 * @param int    $ts     Timestamp.
	 * @return string
	 */
	function wp_date( $format, $ts = null ) {
		return gmdate( $format, (int) $ts );
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * @return void
	 */
	function wp_nonce_field() {
		echo '<input type="hidden" name="nonce" value="x">';
	}
}
if ( ! function_exists( 'submit_button' ) ) {
	/**
	 * @param string $text Label.
	 * @return void
	 */
	function submit_button( $text = '' ) {
		echo '<button type="submit">' . esc_html( $text ) . '</button>';
	}
}
if ( ! function_exists( 'settings_errors' ) ) {
	/**
	 * @return void
	 */
	function settings_errors() {}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'selected' ) ) {
	/**
	 * @param mixed $a Value.
	 * @param mixed $b Compare.
	 * @return void
	 */
	function selected( $a, $b = true ) {
		echo (string) $a === (string) $b ? ' selected="selected"' : '';
	}
}
if ( ! function_exists( 'checked' ) ) {
	/**
	 * @param mixed $a Value.
	 * @param mixed $b Compare.
	 * @return void
	 */
	function checked( $a, $b = true ) {
		echo (string) $a === (string) $b ? ' checked="checked"' : '';
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

$classes = array( 'F2000CS\\Image_Processor', 'F2000CS\\Fields_Updater', 'F2000CS\\XML_Editor', 'F2000CS\\XML_Export_Filter', 'F2000CS\\Prom_Feed', 'F2000CS\\Marketplace_Job', 'F2000CS\\Marketplace_Registry' );
foreach ( $classes as $class ) {
	smoke_check( $class . ( $pro ? ' present' : ' absent' ), class_exists( $class, false ) === $pro );
}

$functions = array(
	'f2000cs_xml_editor_ajax',
	'f2000cs_register_image_settings',
	'f2000cs_get_recent_export_files',
	'f2000cs_marketplace_ajax_step',
	// Pro sync rules (includes/pro-features.php, admin/pro-settings.php).
	'f2000cs_pro_adjust_price',
	'f2000cs_apply_variable_low_instock_rule',
	'f2000cs_handle_export_download',
	'f2000cs_register_pro_settings',
	'f2000cs_handle_update_fields_action',
);
foreach ( $functions as $fn ) {
	smoke_check( $fn . '() ' . ( $pro ? 'present' : 'absent' ), function_exists( $fn ) === $pro );
}

smoke_check( 'free-core classes always present', class_exists( 'F2000CS\\XML_Parser', false ) && class_exists( 'F2000CS\\XML_Stock_Updater', false ) );

// ---------------------------------------------------------------- Pro gate

smoke_check( 'f2000cs_is_pro() is false without license', false === f2000cs_is_pro() );

// ---------------------------------------------------------------- license / trial (fake Freemius)

$screen                                 = new stdClass();
$screen->id                             = 'catalog-sync_page_f2000cs-update';
F2000CS_Test_State::$current_screen     = $screen;
F2000CS_Test_State::$can_manage_options = true;
update_option( 'f2000cs_update_interval', '5_minute' );

smoke_check( 'no license: no build notice', '' === trim( smoke_capture( 'f2000cs_premium_build_missing_notice' ) ) );

$f2000cs_fs->trial      = true;
$f2000cs_fs->trial_ends = gmdate( 'Y-m-d H:i:s', time() + 5 * DAY_IN_SECONDS );
smoke_check( 'trial: Pro only in the Pro build', f2000cs_is_pro() === $pro );
smoke_check( 'trial: end date read', f2000cs_get_trial_ends_at() > time() );
$html = smoke_capture( 'f2000cs_render_trial_countdown' );
smoke_check(
	'trial: countdown ' . ( $pro ? 'shown' : 'hidden on the free build' ),
	( false !== strpos( $html, 'f2000cs-trial-countdown' ) ) === $pro
);
$html = smoke_capture( 'f2000cs_premium_build_missing_notice' );
smoke_check(
	'trial: «install Pro» notice ' . ( $pro ? 'absent' : 'shown' ),
	( false !== strpos( $html, 'example.org/account' ) ) === ! $pro
);
smoke_check( 'trial: effective interval', f2000cs_get_effective_update_interval() === ( $pro ? '5_minute' : 'daily' ) );
smoke_check( 'trial: supplier slots', f2000cs_can_add_supplier_slot() === $pro );

$f2000cs_fs->trial  = false;
$f2000cs_fs->paying = true;
smoke_check( 'license: Pro only in the Pro build', f2000cs_is_pro() === $pro );
smoke_check( 'license: no countdown', '' === trim( smoke_capture( 'f2000cs_render_trial_countdown' ) ) );
smoke_check(
	'license: «install Pro» notice ' . ( $pro ? 'absent' : 'shown' ),
	( false !== strpos( smoke_capture( 'f2000cs_premium_build_missing_notice' ), 'example.org/account' ) ) === ! $pro
);
smoke_check( 'license: free quota ' . ( $pro ? 'lifted' : 'kept' ), f2000cs_free_updates_remaining() === ( $pro ? 999 : F2000CS_FREE_DAILY_UPDATES ) );

$f2000cs_fs->paying = false;
smoke_check( 'license expired: free plan', false === f2000cs_is_pro() );
delete_option( 'f2000cs_update_interval' );
F2000CS_Test_State::$current_screen = null;

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

$html = smoke_capture( 'f2000cs_marketplaces_page' );
if ( $pro ) {
	smoke_check( 'marketplaces page shows the Prom feed controls', false !== strpos( $html, 'f2000cs-mp-build' ) );
} else {
	smoke_check( 'marketplaces page is an upsell placeholder', false !== strpos( $html, 'Pro' ) && false === strpos( $html, 'f2000cs-mp-build' ) );
}

$_GET['page'] = 'f2000cs-import';
$html         = smoke_capture( 'f2000cs_import_page' );
smoke_check(
	'import stock quantity option ' . ( $pro ? 'present' : 'absent' ),
	( false !== strpos( $html, 'name="import_stock_qty"' ) ) === $pro
);
smoke_check(
	'update-fields list ' . ( $pro ? 'present' : 'replaced by an upsell' ),
	( false !== strpos( $html, 'id="start-update-fields"' ) ) === $pro
);

smoke_check( 'settings init runs', null === smoke_json( 'f2000cs_settings_init' ) );
if ( function_exists( 'f2000cs_register_pro_settings' ) ) {
	f2000cs_register_pro_settings();
}
$registered = F2000CS_Test_State::$registered_settings['f2000cs_settings'] ?? array();
smoke_check(
	'price adjustment option ' . ( $pro ? 'registered' : 'not registered' ),
	in_array( 'f2000cs_price_adjust_value_1', $registered, true ) === $pro
);

$html = smoke_capture( 'f2000cs_update_page' );
smoke_check( 'update page renders', false !== strpos( $html, 'Запуск оновлення' ) );

$html = smoke_capture(
	static function () {
		f2000cs_render_supplier_card( 1 );
		f2000cs_interval_render();
	}
);
smoke_check(
	'supplier price adjustment ' . ( $pro ? 'rendered' : 'absent' ),
	( false !== strpos( $html, 'f2000cs_price_adjust_type_1' ) ) === $pro
);
smoke_check(
	'supplier quantity option ' . ( $pro ? 'rendered' : 'absent' ),
	( false !== strpos( $html, 'f2000cs_update_stock_qty_1' ) ) === $pro
);
smoke_check(
	'interval ' . ( $pro ? 'select with frequent intervals' : 'fixed to daily' ),
	( false !== strpos( $html, 'value="5_minute"' ) ) === $pro
);

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

smoke_check(
	'update-fields AJAX action ' . ( $pro ? 'registered' : 'not registered' ),
	(bool) has_filter( 'wp_ajax_f2000cs_update_fields_action' ) === $pro
);
smoke_check(
	'export download action ' . ( $pro ? 'registered' : 'not registered' ),
	(bool) has_filter( 'admin_post_f2000cs_download_export' ) === $pro
);
if ( $pro ) {
	$_POST['f2000cs_import_nonce'] = 'x';
	smoke_check( 'update-fields handler answers with JSON (no fatal)', null !== smoke_json( 'f2000cs_handle_update_fields_action' ) );
}

// ---------------------------------------------------------------- sync rules (license filter still on)

update_option( 'f2000cs_price_adjust_value_1', '10' );
update_option( 'f2000cs_update_stock_qty_1', '1' );
update_option( 'f2000cs_update_interval', '5_minute' );

$adjust = f2000cs_get_price_adjust_settings( 1 );
smoke_check( 'price adjustment ' . ( $pro ? 'applied' : 'off' ), ( 10.0 === (float) $adjust['value'] ) === $pro );
smoke_check( 'supplier quantity update ' . ( $pro ? 'on' : 'off' ), f2000cs_supplier_updates_stock_qty( 1 ) === $pro );
// Free build with a saved markup: prices are frozen, never reset to the raw supplier price.
smoke_check( 'saved markup ' . ( $pro ? 'applied' : 'freezes prices' ), f2000cs_supplier_skip_price( 1 ) === ! $pro );
smoke_check( 'saved Pro settings detected', f2000cs_has_saved_pro_config() );
smoke_check( 'effective interval', f2000cs_get_effective_update_interval() === ( $pro ? '5_minute' : 'daily' ) );

// The next run keeps its time when the interval changes: no immediate full sync.
$next_run = time() + 3600;
wp_schedule_event( $next_run, $pro ? 'daily' : '5_minute', F2000CS\Cron_Job::CRON_HOOK );
F2000CS\Cron_Job::sync_schedule();
smoke_check( 'interval change keeps the next run time', wp_next_scheduled( F2000CS\Cron_Job::CRON_HOOK ) === $next_run );
wp_clear_scheduled_hook( F2000CS\Cron_Job::CRON_HOOK );
smoke_check(
	'frequent cron schedules ' . ( $pro ? 'registered' : 'absent' ),
	isset( apply_filters( 'cron_schedules', array() )['5_minute'] ) === $pro
);

// A cron event left on another interval (trial ended, Pro build replaced by free) follows the effective one.
wp_schedule_event( time(), $pro ? 'daily' : '5_minute', F2000CS\Cron_Job::CRON_HOOK );
F2000CS\Cron_Job::sync_schedule();
smoke_check( 'stock cron moved to the effective interval', wp_get_schedule( F2000CS\Cron_Job::CRON_HOOK ) === ( $pro ? '5_minute' : 'daily' ) );
wp_clear_scheduled_hook( F2000CS\Cron_Job::CRON_HOOK );
F2000CS\Cron_Job::sync_schedule();
smoke_check( 'stopped stock cron stays stopped', false === wp_get_schedule( F2000CS\Cron_Job::CRON_HOOK ) );

// Marketplace events and feed files left by the Pro build: the free build withdraws them, Pro keeps them.
$feeds_dir = wp_upload_dir()['basedir'] . '/f2000cs-feeds';
if ( ! is_dir( $feeds_dir ) ) {
	mkdir( $feeds_dir, 0777, true );
}
file_put_contents( $feeds_dir . '/prom-smoketoken.xml', '<yml_catalog/>' );
wp_schedule_event( time(), 'daily', 'f2000cs_marketplace_build', array( 'prom' ) );
f2000cs_cleanup_orphaned_marketplace_feeds();
smoke_check( 'marketplace events ' . ( $pro ? 'kept' : 'withdrawn' ), f2000cs_has_marketplace_events() === $pro );
smoke_check( 'marketplace feed file ' . ( $pro ? 'kept' : 'deleted' ), is_file( $feeds_dir . '/prom-smoketoken.xml' ) === $pro );
@unlink( $feeds_dir . '/prom-smoketoken.xml' );

// The free build also withdraws them after a stock update (no admin visit needed).
if ( ! $pro ) {
	mkdir( $feeds_dir, 0777, true );
	file_put_contents( $feeds_dir . '/prom-smoketoken.xml', '<yml_catalog/>' );
	do_action( 'f2000cs_stock_update_complete' );
	smoke_check( 'free build: stock update withdraws an orphaned feed', ! is_file( $feeds_dir . '/prom-smoketoken.xml' ) );
}
f2000cs_clear_marketplace_cron();
smoke_check( 'marketplace events cleared on deactivation', ! f2000cs_has_marketplace_events() );

// ---------------------------------------------------------------- shared code paths

$parser = new F2000CS\XML_Parser( 'x.xml', false, 'T_' );
$offer  = simplexml_load_string( '<offer id="1" available=""><price>10</price><qty_stock>2.000</qty_stock><picture>http://x/a.jpg</picture></offer>' );
$data   = F2000CS_Test_Reflection::invoke( $parser, 'extract_offer_data', array( $offer ) );
smoke_check( 'offer parsing works in this build', 2 === $data['quantity'] && 'outofstock' === $data['available'] );

echo "[{$mode}] {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
