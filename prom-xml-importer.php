<?php
/**
 * Plugin Name:       Prom XML Importer
 * Description:       Плагін для імпорту XML даних та оновлення статусу запасів із платформи Prom.ua.
 * Version:           0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            KMax (Maxim Kliakhin)
 * Author URI:        https://github.com/maximus2526
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       prom-xml-importer
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'PROM_XML_IMPORTER_VERSION', '0.1' );
define( 'PROM_XML_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROM_XML_IMPORTER_URL', plugin_dir_url( __FILE__ ) );
define( 'PROM_XML_IMPORTER_BASENAME', plugin_basename( __FILE__ ) );

require_once PROM_XML_IMPORTER_PATH . 'includes/class-cron-job.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/class-stock-updater.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/parsers/class-xml-parser.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/class-xml-export-filter.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/functions.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/class-frontend-display.php';
require_once PROM_XML_IMPORTER_PATH . 'admin/settings-page.php';
require_once PROM_XML_IMPORTER_PATH . 'admin/admin-assets.php';
require_once PROM_XML_IMPORTER_PATH . 'admin/support-widget.php';

register_activation_hook( __FILE__, 'prom_xml_importer_activate' );
register_deactivation_hook( __FILE__, array( 'Cron_Job', 'deactivate' ) );

add_action( 'plugins_loaded', 'prom_xml_importer_init' );

/**
 * Initialize plugin on plugins_loaded action.
 */
function prom_xml_importer_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'prom_xml_importer_woocommerce_missing_notice' );
		return;
	}

	load_plugin_textdomain( 'xml-prom', false, PROM_XML_IMPORTER_BASENAME . '/languages' );

	add_action( Cron_Job::CRON_HOOK, array( 'Cron_Job', 'update_stock' ) );

	if ( class_exists( 'Frontend_Display' ) ) {
		Frontend_Display::init();
	}

	add_action( 'admin_notices', 'prom_xml_importer_check_resources' );

	add_action( 'admin_init', 'prom_xml_importer_check_requirements' );
}

/**
 * Admin notice when WooCommerce is not active.
 */
function prom_xml_importer_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo '<strong>Prom XML Importer:</strong> ';
	echo esc_html__( 'This plugin requires WooCommerce to be installed and active.', 'xml-prom' );
	echo '</p></div>';
}

/**
 * Plugin activation hook.
 */
function prom_xml_importer_activate() {
	Cron_Job::activate();

	if ( ! get_option( 'prom_xml_update_interval' ) ) {
		update_option( 'prom_xml_update_interval', 'hourly' );
	}

	set_transient( 'prom_xml_importer_activated', true, 60 );
}

/**
 * Add admin notice if server resources are not optimal.
 */
function prom_xml_importer_check_resources() {
	if ( get_transient( 'prom_xml_importer_activated' ) ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>Prom XML Importer:</strong> ' . esc_html__( 'Plugin has been activated. Please configure settings to start updating stock status.', 'xml-prom' ) . '</p>';
		echo '</div>';
		delete_transient( 'prom_xml_importer_activated' );
	}

	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'prom-xml-importer' ) === false ) {
		return;
	}

	if ( ! prom_is_configured() && isset( $_GET['page'] ) && $_GET['page'] === 'prom-xml-importer-update' ) {
		echo '<div class="notice notice-warning">';
		echo '<p><strong>Prom XML Importer:</strong> ' . esc_html__( 'Please configure an XML URL to start updating stock status.', 'xml-prom' ) . '</p>';
		echo '</div>';
	}
}

/**
 * Check if required PHP extensions are installed.
 */
function prom_xml_importer_check_requirements() {
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
				echo '<p><strong>Prom XML Importer:</strong> ' .
				esc_html__( 'The following PHP extensions are required: ', 'xml-prom' ) .
				implode( ', ', $missing ) . '</p>';
				echo '</div>';
			}
		);
	}
}
