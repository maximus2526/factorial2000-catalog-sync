<?php

// Exit if accessed directly or not called by WordPress uninstall process.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function f2000cs_uninstall_cleanup() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'f2000cs_update_stock_cron' );

	// wp_clear_scheduled_hook( 'f2000cs_single_update_event' ) without $args would only
	// remove events scheduled with an *empty* args array, but our background events are
	// always scheduled with a unique (url, sku_prefix) pair, so it would be a no-op.
	// Walk the cron array directly and unschedule every matching entry instead.
	$crons = _get_cron_array();
	if ( ! empty( $crons ) ) {
		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) || empty( $hooks['f2000cs_single_update_event'] ) ) {
				continue;
			}

			foreach ( $hooks['f2000cs_single_update_event'] as $event ) {
				$args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
				wp_unschedule_event( $timestamp, 'f2000cs_single_update_event', $args );
			}
		}
	}

	// Remove all plugin options
	delete_option( 'f2000cs_url' );
	delete_option( 'f2000cs_url_1' );
	delete_option( 'f2000cs_url_2' );
	delete_option( 'f2000cs_url_3' );
	delete_option( 'f2000cs_url_4' );
	delete_option( 'f2000cs_url_5' );
	delete_option( 'f2000cs_sku_prefix_1' );
	delete_option( 'f2000cs_sku_prefix_2' );
	delete_option( 'f2000cs_sku_prefix_3' );
	delete_option( 'f2000cs_sku_prefix_4' );
	delete_option( 'f2000cs_sku_prefix_5' );
	delete_option( 'f2000cs_skip_price_1' );
	delete_option( 'f2000cs_skip_price_2' );
	delete_option( 'f2000cs_skip_price_3' );
	delete_option( 'f2000cs_skip_price_4' );
	delete_option( 'f2000cs_skip_price_5' );
	for ( $i = 1; $i <= 5; $i++ ) {
		delete_option( 'f2000cs_price_adjust_type_' . $i );
		delete_option( 'f2000cs_price_adjust_direction_' . $i );
		delete_option( 'f2000cs_price_adjust_value_' . $i );
	}
	delete_option( 'f2000cs_update_interval' );
	delete_option( 'f2000cs_hide_variable_low_instock' );
	delete_option( 'f2000cs_variable_low_instock_max' );
	delete_option( 'f2000cs_telegram_user_ids' );
	delete_option( 'f2000cs_telegram_token_id' );

	// Remove leftover transients created during import sessions
	delete_transient( 'f2000cs_import_variations_temp' );
	delete_transient( 'f2000cs_selected_attributes_temp' );
	delete_transient( 'f2000cs_activated' );
	delete_transient( 'f2000cs_bg_batch_remaining' );
}

f2000cs_uninstall_cleanup();
