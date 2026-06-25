<?php

// Exit if accessed directly or not called by WordPress uninstall process.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function prom_uninstall_cleanup() {
	// Clear scheduled events
	wp_clear_scheduled_hook( 'prom_update_stock_cron' );
	wp_clear_scheduled_hook( 'prom_single_update_event' );

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
	delete_option( 'prom_xml_skip_price_1' );
	delete_option( 'prom_xml_skip_price_2' );
	delete_option( 'prom_xml_skip_price_3' );
	delete_option( 'prom_xml_skip_price_4' );
	delete_option( 'prom_xml_skip_price_5' );
	delete_option( 'prom_xml_update_interval' );
	delete_option( 'prom_xml_hide_variable_low_instock' );
	delete_option( 'prom_xml_variable_low_instock_max' );
	delete_option( 'telegram_user_ids' );
	delete_option( 'telegram_token_id' );

	// Remove leftover transients created during import sessions
	delete_transient( 'prom_xml_import_variations_temp' );
	delete_transient( 'prom_xml_selected_attributes_temp' );
	delete_transient( 'prom_xml_importer_activated' );
}

prom_uninstall_cleanup();
