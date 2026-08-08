<?php

defined( 'ABSPATH' ) || exit;

/**
 * Remove plugin options, cron, transients and private upload dirs.
 *
 * Hooked to Freemius `after_uninstall` (no uninstall.php — Freemius requirement).
 *
 * @return void
 */
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

	$scan_max = 200;
	for ( $i = 1; $i <= $scan_max; $i++ ) {
		if ( $i > 1 ) {
			delete_option( 'f2000cs_url_' . $i );
		}
		delete_option( 'f2000cs_sku_prefix_' . $i );
		delete_option( 'f2000cs_skip_price_' . $i );
		delete_option( 'f2000cs_update_stock_qty_' . $i );
		delete_option( 'f2000cs_price_adjust_type_' . $i );
		delete_option( 'f2000cs_price_adjust_direction_' . $i );
		delete_option( 'f2000cs_price_adjust_value_' . $i );
	}

	delete_option( 'f2000cs_update_interval' );
	delete_option( 'f2000cs_hide_variable_low_instock' );
	delete_option( 'f2000cs_variable_low_instock_max' );
	delete_option( 'f2000cs_show_vendor_code' );
	delete_option( 'f2000cs_img_png_convert' );
	delete_option( 'f2000cs_img_optimize' );
	delete_option( 'f2000cs_img_quality' );
	delete_option( 'f2000cs_img_max_dimension' );
	delete_option( 'f2000cs_telegram_user_ids' );
	delete_option( 'f2000cs_telegram_token_id' );
	delete_option( 'f2000cs_legacy_trial_ends' );
	delete_option( 'f2000cs_legacy_trial_initialized' );
	delete_option( 'f2000cs_last_stock_update_day' );
	delete_option( 'f2000cs_db_version' );
	delete_option( 'f2000cs_sku_prefix' );

	// Remove leftover transients created during import sessions
	delete_transient( 'f2000cs_import_variations_temp' );
	delete_transient( 'f2000cs_selected_attributes_temp' );
	delete_transient( 'f2000cs_activated' );
	delete_transient( 'f2000cs_bg_batch_remaining' );

	// Private upload dirs created by the plugin.
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['basedir'] ) ) {
		$basedir = trailingslashit( $upload_dir['basedir'] );
		f2000cs_uninstall_delete_dir( $basedir . 'f2000cs-exports' );
		f2000cs_uninstall_delete_dir( $basedir . 'f2000cs-editor-sessions' );
	}
}

/**
 * Best-effort recursive delete for plugin-owned upload subdirs.
 *
 * @param string $dir Absolute directory path.
 * @return void
 */
function f2000cs_uninstall_delete_dir( $dir ) {
	$dir = untrailingslashit( (string) $dir );
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return;
	}

	$items = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Uninstall best-effort.
	if ( ! is_array( $items ) ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			f2000cs_uninstall_delete_dir( $path );
		} else {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Uninstall cleanup.
		}
	}

	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Uninstall cleanup.
}
