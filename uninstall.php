<?php

defined( 'ABSPATH' ) || exit;

function prom_uninstall_cleanup() {
	// Clear scheduled events
	wp_clear_scheduled_hook( 'prom_update_stock_event' );
	wp_clear_scheduled_hook( 'prom_update_stock_cron' );

	// Remove all plugin options
	delete_option( 'prom_xml_url' );
	delete_option( 'prom_xml_url_1' );
	delete_option( 'prom_xml_url_2' );
	delete_option( 'prom_xml_url_3' );
	delete_option( 'prom_xml_url_4' );
	delete_option( 'prom_xml_url_5' );
	delete_option( 'prom_xml_sku_prefix_1' );
	delete_option( 'prom_xml_sku_prefix_2' );
	delete_option( 'prom_xml_sku_prefix_3' );
	delete_option( 'prom_xml_sku_prefix_4' );
	delete_option( 'prom_xml_sku_prefix_5' );
	delete_option( 'prom_xml_update_interval' );
	delete_option( 'telegram_user_ids' );
	delete_option( 'telegram_token_id' );
}

prom_uninstall_cleanup();
