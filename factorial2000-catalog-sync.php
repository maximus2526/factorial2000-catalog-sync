<?php
/**
 * Plugin Name:       Factorial2000 Catalog Sync for Prom.ua
 * Description:       Імпорт та синхронізація товарів WooCommerce з XML (YML/Prom.ua). Оновлення стоку й цін за розкладом, імпорт простих і варіативних товарів з фото/атрибутами/категоріями, оновлення окремих полів (Pro), редактор вигрузок XML (Pro), обробка зображень (Pro), Telegram-сповіщення. Free назавжди + безкоштовний Pro-тріал 14 днів без карти. Підтримка кількох постачальників, SKU-префіксів, конвертації валют. Містить автоматизовані PHPUnit-тести.
 * Version:           0.6.6
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            KMax (Maxim Kliakhin)
 * Author URI:        https://github.com/maximus2526
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       factorial2000-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Freemius free/premium conflict resolution.
 * If another copy already defined the helper, register this file as the premium basename.
 *
 * @see https://freemius.com/help/documentation/wordpress-sdk/
 */
if ( function_exists( 'f2000cs_fs' ) ) {
	$f2000cs_fs = f2000cs_fs();
	if ( $f2000cs_fs ) {
		$f2000cs_fs->set_basename( true, __FILE__ );
	}
} else {

	define( 'F2000CS_VERSION', '0.6.6' );
	define( 'F2000CS_DB_VERSION', 1 ); // Increment when DB migrations are needed.
	define( 'F2000CS_PATH', plugin_dir_path( __FILE__ ) );
	define( 'F2000CS_URL', plugin_dir_url( __FILE__ ) );
	define( 'F2000CS_BASENAME', plugin_basename( __FILE__ ) );

	require_once F2000CS_PATH . 'includes/class-cron-job.php';
	require_once F2000CS_PATH . 'includes/class-stock-updater.php';
	require_once F2000CS_PATH . 'includes/parsers/class-xml-parser.php';
	require_once F2000CS_PATH . 'includes/parsers/class-fields-updater.php';
	require_once F2000CS_PATH . 'includes/trait-xml-rebuilder.php';
	require_once F2000CS_PATH . 'includes/class-xml-export-filter.php';
	require_once F2000CS_PATH . 'includes/class-xml-editor.php';
	require_once F2000CS_PATH . 'includes/class-image-processor.php';
	require_once F2000CS_PATH . 'includes/functions.php';
	require_once F2000CS_PATH . 'includes/uninstall-cleanup.php';
	require_once F2000CS_PATH . 'includes/class-licensing.php';
	require_once F2000CS_PATH . 'includes/freemius-uk-i18n.php';
	require_once F2000CS_PATH . 'includes/class-frontend-display.php';
	require_once F2000CS_PATH . 'admin/settings-page.php';
	require_once F2000CS_PATH . 'admin/xml-editor.php';
	require_once F2000CS_PATH . 'admin/admin-assets.php';
	require_once F2000CS_PATH . 'admin/support-widget.php';

	register_activation_hook( __FILE__, 'f2000cs_activate' );
	register_deactivation_hook( __FILE__, array( 'F2000CS\Cron_Job', 'deactivate' ) );

	// Early so cron/front can see migrated SKU prefixes before first admin visit.
	add_action( 'plugins_loaded', 'f2000cs_maybe_run_migrations', 5 );
	add_action( 'admin_init', 'f2000cs_maybe_run_migrations' );
	add_action( 'plugins_loaded', 'f2000cs_init' );

	/**
	 * Initialize plugin on plugins_loaded action.
	 */
	function f2000cs_init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'f2000cs_woocommerce_missing_notice' );
			return;
		}

		add_action( \F2000CS\Cron_Job::CRON_HOOK, array( 'F2000CS\Cron_Job', 'update_stock' ) );

		if ( class_exists( 'F2000CS\Frontend_Display' ) ) {
			\F2000CS\Frontend_Display::init();
		}

		add_action( 'admin_notices', 'f2000cs_check_resources' );

		add_action( 'admin_init', 'f2000cs_check_requirements' );
	}

	/**
	 * Admin notice when WooCommerce is not active.
	 */
	function f2000cs_woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo '<strong>Factorial2000 Catalog Sync:</strong> ';
		echo esc_html__( 'Цей плагін потребує встановленого та активного WooCommerce.', 'factorial2000-catalog-sync' );
		echo '</p></div>';
	}

	/**
	 * Plugin activation hook.
	 */
	function f2000cs_activate() {
		\F2000CS\Cron_Job::activate();

		if ( ! get_option( 'f2000cs_update_interval' ) ) {
			update_option( 'f2000cs_update_interval', 'hourly' );
		}

		f2000cs_maybe_run_migrations();

		set_transient( 'f2000cs_activated', true, 60 );
	}

	/**
	 * Add admin notice if server resources are not optimal.
	 */
	function f2000cs_check_resources() {
		if ( get_transient( 'f2000cs_activated' ) ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>Factorial2000 Catalog Sync:</strong> ' . esc_html__( 'Плагін активовано. Налаштуйте параметри, щоб розпочати оновлення наявності.', 'factorial2000-catalog-sync' ) . '</p>';
			echo '</div>';
			delete_transient( 'f2000cs_activated' );
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'f2000cs-' ) === false ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check for an admin notice, no data is processed.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! f2000cs_is_configured() && 'f2000cs-update' === $current_page ) {
			echo '<div class="notice notice-warning">';
			echo '<p><strong>Factorial2000 Catalog Sync:</strong> ' . esc_html__( 'Вкажіть URL XML, щоб розпочати оновлення наявності.', 'factorial2000-catalog-sync' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Check if required PHP extensions are installed.
	 */
	function f2000cs_check_requirements() {
		$missing = array();

		if ( ! extension_loaded( 'xml' ) ) {
			$missing[] = 'XML';
		}

		if ( ! extension_loaded( 'simplexml' ) ) {
			$missing[] = 'SimpleXML';
		}

		if ( ! extension_loaded( 'curl' ) ) {
			$missing[] = 'cURL';
		}

		if ( ! empty( $missing ) ) {
			add_action(
				'admin_notices',
				function () use ( $missing ) {
					echo '<div class="notice notice-error">';
					echo '<p><strong>Factorial2000 Catalog Sync:</strong> ' .
					esc_html__( 'Потрібні такі розширення PHP: ', 'factorial2000-catalog-sync' ) .
					esc_html( implode( ', ', $missing ) ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

} // End Freemius set_basename else.
