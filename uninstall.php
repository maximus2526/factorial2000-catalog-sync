<?php

defined( 'ABSPATH' ) || exit;

function prom_uninstall_cleanup() {
	// Clear scheduled events
	wp_clear_scheduled_hook( 'prom_update_stock_event' );
	wp_clear_scheduled_hook( 'prom_update_stock_cron' );

	// Remove all plugin options
	delete_option( 'prom_xml_url' );
	delete_option( 'prom_xml_update_interval' );
	delete_option( 'telegram_user_ids' );
	delete_option( 'telegram_token_id' );
}

prom_uninstall_cleanup();
