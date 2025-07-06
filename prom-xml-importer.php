<?php
/**
 * Plugin Name: Prom XML Importer
 * Description: Плагін для імпорту XML даних та оновлення статусу запасів.
 * Version: 1.2
 * Author: KMax
 * Text Domain: xml-prom
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

// Define constants
define( 'PROM_XML_IMPORTER_VERSION', '1.2' );
define( 'PROM_XML_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROM_XML_IMPORTER_URL', plugin_dir_url( __FILE__ ) );
define( 'PROM_XML_IMPORTER_BASENAME', plugin_basename( __FILE__ ) );

// Include required files
require_once PROM_XML_IMPORTER_PATH . 'includes/class-cron-job.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/class-stock-updater.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/parsers/class-xml-parser.php';
require_once PROM_XML_IMPORTER_PATH . 'includes/functions.php';
require_once PROM_XML_IMPORTER_PATH . 'admin/settings-page.php';

// Hook activation, deactivation and uninstall
register_activation_hook( __FILE__, 'prom_xml_importer_activate' );
register_deactivation_hook( __FILE__, array( 'Cron_Job', 'deactivate' ) );

// Initialization functions
add_action( 'plugins_loaded', 'prom_xml_importer_init' );

/**
 * Initialize plugin on plugins_loaded action.
 */
function prom_xml_importer_init() {
	// Load text domain for translation
	load_plugin_textdomain( 'xml-prom', false, PROM_XML_IMPORTER_BASENAME . '/languages' );

	// Ensure cron job runs
	add_action( Cron_Job::CRON_HOOK, array( 'Cron_Job', 'update_stock' ) );

	// Add admin notices
	add_action( 'admin_notices', 'prom_xml_importer_check_resources' );

	// Check for required PHP extensions
	add_action( 'admin_init', 'prom_xml_importer_check_requirements' );
}

/**
 * Plugin activation hook.
 */
function prom_xml_importer_activate() {
	// Call Cron_Job activation
	Cron_Job::activate();

	// Set default options if not already set
	if ( ! get_option( 'prom_xml_update_interval' ) ) {
		update_option( 'prom_xml_update_interval', 'hourly' );
	}

	// Set a flag for admin notice after activation
	set_transient( 'prom_xml_importer_activated', true, 60 );
}

/**
 * Add admin notice if server resources are not optimal.
 */
function prom_xml_importer_check_resources() {
	// Show activation notice
	if ( get_transient( 'prom_xml_importer_activated' ) ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>Prom XML Importer:</strong> ' . esc_html__( 'Plugin has been activated. Please configure settings to start updating stock status.', 'xml-prom' ) . '</p>';
		echo '</div>';
		delete_transient( 'prom_xml_importer_activated' );
	}

	// Only show on plugin pages
	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'prom-xml-importer' ) === false ) {
		return;
	}

	// Check if XML URL is set
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
