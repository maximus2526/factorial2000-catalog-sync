<?php
/**
 * Plugin Name:       Factorial2000 Catalog Sync for Prom.ua
 * Description:       Плагін для імпорту XML даних та оновлення статусу запасів із платформи Prom.ua.
 * Version:           0.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            KMax (Maxim Kliakhin)
 * Author URI:        https://github.com/maximus2526
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       factorial2000-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

define( 'F2000CS_VERSION', '0.3' );
define( 'F2000CS_PATH', plugin_dir_path( __FILE__ ) );
define( 'F2000CS_URL', plugin_dir_url( __FILE__ ) );
define( 'F2000CS_BASENAME', plugin_basename( __FILE__ ) );

require_once F2000CS_PATH . 'includes/class-cron-job.php';
require_once F2000CS_PATH . 'includes/class-stock-updater.php';
require_once F2000CS_PATH . 'includes/parsers/class-xml-parser.php';
require_once F2000CS_PATH . 'includes/class-xml-export-filter.php';
require_once F2000CS_PATH . 'includes/functions.php';
require_once F2000CS_PATH . 'includes/class-frontend-display.php';
require_once F2000CS_PATH . 'admin/settings-page.php';
require_once F2000CS_PATH . 'admin/admin-assets.php';
require_once F2000CS_PATH . 'admin/support-widget.php';

register_activation_hook( __FILE__, 'f2000cs_activate' );
register_deactivation_hook( __FILE__, array( 'F2000CS\Cron_Job', 'deactivate' ) );

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
	echo esc_html__( 'This plugin requires WooCommerce to be installed and active.', 'factorial2000-catalog-sync' );
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

	set_transient( 'f2000cs_activated', true, 60 );
}

/**
 * Add admin notice if server resources are not optimal.
 */
function f2000cs_check_resources() {
	if ( get_transient( 'f2000cs_activated' ) ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>Factorial2000 Catalog Sync:</strong> ' . esc_html__( 'Plugin has been activated. Please configure settings to start updating stock status.', 'factorial2000-catalog-sync' ) . '</p>';
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
		echo '<p><strong>Factorial2000 Catalog Sync:</strong> ' . esc_html__( 'Please configure an XML URL to start updating stock status.', 'factorial2000-catalog-sync' ) . '</p>';
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
				esc_html__( 'The following PHP extensions are required: ', 'factorial2000-catalog-sync' ) .
				esc_html( implode( ', ', $missing ) ) . '</p>';
				echo '</div>';
			}
		);
	}
}
