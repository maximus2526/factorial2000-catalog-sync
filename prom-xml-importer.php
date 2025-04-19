<?php
/**
 * Plugin Name: Prom XML Importer
 * Description: Плагін для імпорту XML даних та оновлення статусу запасів.
 * Version: 1.1
 * Author: KMax
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-cron-job.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-stock-updater.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/parsers/class-xml-parser.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/settings-page.php';

register_deactivation_hook( __FILE__, array( 'Cron_Job', 'deactivate' ) );
add_action( Cron_Job::CRON_HOOK, array( 'Cron_Job', 'update_stock' ) );
