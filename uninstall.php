<?php

// Exit if accessed directly or not called by WordPress uninstall process.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function f2cs_uninstall_cleanup() {
	// Clear scheduled events
	wp_clear_scheduled_hook( 'f2cs_update_stock_cron' );
	wp_clear_scheduled_hook( 'f2cs_single_update_event' );

	// Remove all plugin options
	delete_option( 'f2cs_url' );
	delete_option( 'f2cs_url_1' );
	delete_option( 'f2cs_url_2' );
	delete_option( 'f2cs_url_3' );
	delete_option( 'f2cs_url_4' );
	delete_option( 'f2cs_url_5' );
	delete_option( 'f2cs_sku_prefix_1' );
	delete_option( 'f2cs_sku_prefix_2' );
	delete_option( 'f2cs_sku_prefix_3' );
	delete_option( 'f2cs_sku_prefix_4' );
	delete_option( 'f2cs_sku_prefix_5' );
	delete_option( 'f2cs_skip_price_1' );
	delete_option( 'f2cs_skip_price_2' );
	delete_option( 'f2cs_skip_price_3' );
	delete_option( 'f2cs_skip_price_4' );
	delete_option( 'f2cs_skip_price_5' );
	delete_option( 'f2cs_update_interval' );
	delete_option( 'f2cs_hide_variable_low_instock' );
	delete_option( 'f2cs_variable_low_instock_max' );
	delete_option( 'f2cs_telegram_user_ids' );
	delete_option( 'f2cs_telegram_token_id' );

	// Remove leftover transients created during import sessions
	delete_transient( 'f2cs_import_variations_temp' );
	delete_transient( 'f2cs_selected_attributes_temp' );
	delete_transient( 'f2cs_activated' );
}

f2cs_uninstall_cleanup();
