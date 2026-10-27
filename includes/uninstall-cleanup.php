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
	wp_clear_scheduled_hook( 'f2000cs_temp_cleanup_cron' );

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

	f2000cs_clear_marketplace_cron();

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
	delete_option( 'f2000cs_allow_insecure_ssl' );
	delete_option( 'f2000cs_img_png_convert' );
	delete_option( 'f2000cs_img_optimize' );
	delete_option( 'f2000cs_img_quality' );
	delete_option( 'f2000cs_img_max_dimension' );
	delete_option( 'f2000cs_telegram_user_ids' );
	delete_option( 'f2000cs_telegram_token_id' );
	delete_option( 'f2000cs_telegram_debug' );
	delete_option( 'f2000cs_free_update_day' );
	delete_option( 'f2000cs_free_update_count' );
	delete_option( 'f2000cs_db_version' );
	delete_option( 'f2000cs_sku_prefix' );

	delete_metadata( 'user', 0, 'f2000cs_last_import', '', true );

	// Remove leftover transients created during import sessions
	delete_transient( 'f2000cs_import_variations_temp' );
	delete_transient( 'f2000cs_selected_attributes_temp' );
	delete_transient( 'f2000cs_activated' );
	delete_transient( 'f2000cs_bg_batch_remaining' );

	// Per-session transients (import XML cache, resume checkpoints, export download tokens).
	global $wpdb;
	if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'esc_like' ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall cleanup of this plugin's own transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_f2000cs_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_f2000cs_' ) . '%'
			)
		);

		// Marketplace feeds (Pro): settings, build state, last result and file token per feed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall cleanup of this plugin's own options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'f2000cs_mp_' ) . '%'
			)
		);

		// Supplier feed outage marks (start time of an unreachable streak per feed URL).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall cleanup of this plugin's own options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'f2000cs_feed_down_' ) . '%'
			)
		);

		// Resume positions of stock runs cut short by the time limit (per feed URL).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall cleanup of this plugin's own options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'f2000cs_resume_' ) . '%'
			)
		);
	}

	// Private upload dirs created by the plugin.
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['basedir'] ) ) {
		$basedir = trailingslashit( $upload_dir['basedir'] );
		f2000cs_uninstall_delete_dir( $basedir . 'f2000cs-exports' );
		f2000cs_uninstall_delete_dir( $basedir . 'f2000cs-editor-sessions' );
		f2000cs_uninstall_delete_dir( $basedir . 'f2000cs-feeds' );
	}

	// Import temp files in the system temp dir (any age: the plugin is going away).
	f2000cs_cleanup_stale_temp_files( 0 );
}

/**
 * Unschedule every marketplace feed event (recurring build and its continuation).
 *
 * The events belong to the Pro marketplace module but are cleared from the free
 * code, so deactivation, uninstall and the free build can stop them as well.
 *
 * @return void
 */
function f2000cs_clear_marketplace_cron() {
	wp_unschedule_hook( 'f2000cs_marketplace_build' );
	wp_unschedule_hook( 'f2000cs_marketplace_continue' );
	wp_unschedule_hook( 'f2000cs_marketplace_after_sync' );
}

/**
 * Free build: withdraw marketplace feeds left behind by the Pro build.
 *
 * The free build cannot rebuild them, so the published files would keep feeding
 * the marketplace frozen prices and stock. Their events and files are removed;
 * the saved settings stay for a later return to Pro.
 *
 * @return void
 */
function f2000cs_cleanup_orphaned_marketplace_feeds() {
	if ( f2000cs_has_premium_code() ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$feeds_dir  = empty( $upload_dir['basedir'] ) ? '' : trailingslashit( $upload_dir['basedir'] ) . 'f2000cs-feeds';

	if ( ( '' === $feeds_dir || ! is_dir( $feeds_dir ) ) && ! f2000cs_has_marketplace_events() ) {
		return;
	}

	f2000cs_clear_marketplace_cron();

	if ( '' !== $feeds_dir ) {
		f2000cs_uninstall_delete_dir( $feeds_dir );
	}
}
add_action( 'admin_init', 'f2000cs_cleanup_orphaned_marketplace_feeds' );
add_action( 'f2000cs_stock_update_complete', 'f2000cs_cleanup_orphaned_marketplace_feeds' );

/**
 * Whether any marketplace event is scheduled (they carry a feed id argument).
 *
 * @return bool
 */
function f2000cs_has_marketplace_events() {
	$crons = _get_cron_array();

	if ( ! is_array( $crons ) ) {
		return false;
	}

	foreach ( $crons as $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}

		if ( ! empty( $hooks['f2000cs_marketplace_build'] ) || ! empty( $hooks['f2000cs_marketplace_continue'] ) || ! empty( $hooks['f2000cs_marketplace_after_sync'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Delete stale f2000cs temp files from the system temp dir.
 *
 * A URL import uses a deterministic per-session file; runs that died before
 * their session cleanup ran (failed downloads, abandoned imports) leave such
 * files behind. The daily cron event and the admin sweep call this to keep
 * the temp dir tidy.
 *
 * @param int $max_age Files younger than this (in seconds) are kept. Default 2 hours; pass 0 to remove all.
 * @return int Number of files removed.
 */
function f2000cs_cleanup_stale_temp_files( $max_age = 2 * HOUR_IN_SECONDS ) {
	$files = glob( get_temp_dir() . 'f2000cs_*.tmp' );

	if ( ! is_array( $files ) ) {
		return 0;
	}

	$now     = time();
	$removed = 0;

	foreach ( $files as $file ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The file may vanish between glob() and stat(); a missing mtime is skipped.
		$mtime = @filemtime( $file );

		if ( false === $mtime || ( $now - $mtime ) < $max_age ) {
			continue;
		}

		if ( file_exists( $file ) ) { // The file may be gone already if another sweep just ran.
			wp_delete_file( $file );
			++$removed;
		}
	}

	return $removed;
}

/**
 * Run the stale temp-file sweep from the admin, at most once an hour.
 *
 * Also makes sure the daily cron event exists: it can be missing on sites that
 * never ran the admin or after another plugin cleared the cron array.
 *
 * @return void
 */
function f2000cs_maybe_cleanup_stale_temp_files() {
	if ( false === get_transient( 'f2000cs_temp_sweep_done' ) ) {
		set_transient( 'f2000cs_temp_sweep_done', 1, HOUR_IN_SECONDS );
		f2000cs_cleanup_stale_temp_files();
	}

	if ( ! wp_next_scheduled( 'f2000cs_temp_cleanup_cron' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'f2000cs_temp_cleanup_cron' );
	}
}
add_action( 'f2000cs_temp_cleanup_cron', 'f2000cs_cleanup_stale_temp_files' );
add_action( 'admin_init', 'f2000cs_maybe_cleanup_stale_temp_files' );

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
