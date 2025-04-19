<?php
/**
 * Plugin Name: Prom XML Importer
 * Description: Плагін для імпорту XML даних та оновлення статусу запасів.
 * Version: 1.2
 * Author: KMax
 */

defined( 'ABSPATH' ) || exit;

// Include required files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cron-job.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-stock-updater.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/parsers/class-xml-parser.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/settings-page.php';

// Define constants
define('PROM_XML_IMPORTER_VERSION', '1.2');
define('PROM_XML_IMPORTER_PATH', plugin_dir_path(__FILE__));
define('PROM_XML_IMPORTER_URL', plugin_dir_url(__FILE__));

// Hook deactivation to clean up cron jobs
register_deactivation_hook( __FILE__, array( 'Cron_Job', 'deactivate' ) );

// Ensure cron job runs
add_action( Cron_Job::CRON_HOOK, array( 'Cron_Job', 'update_stock' ) );

// Add admin notice if server resources are not optimal
function prom_xml_importer_check_resources() {
    $screen = get_current_screen();
    if ($screen->id !== 'toplevel_page_prom-xml-importer-update') {
        return;
    }
    
    $resources = prom_check_server_resources();
    if (!$resources['is_optimal']) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>Prom XML Importer:</strong> ';
        echo esc_html__('Your server has limited resources which may affect performance with large XML files:', 'xml-prom');
        echo '<ul>';
        echo '<li>' . esc_html__('Memory Limit:', 'xml-prom') . ' ' . esc_html($resources['memory_limit']) . '</li>';
        echo '<li>' . esc_html__('Max Execution Time:', 'xml-prom') . ' ' . esc_html($resources['max_execution_time']) . '</li>';
        echo '</ul>';
        echo '</p></div>';
    }
}
add_action('admin_notices', 'prom_xml_importer_check_resources');
