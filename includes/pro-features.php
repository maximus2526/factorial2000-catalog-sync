<?php
/**
 * Pro sync features: stock quantity updates, the variable low-instock rule, frequent cron intervals and the export download proxy.
 *
 * Premium-only file (listed in @fs_premium_only of the main plugin file), so it
 * is not shipped in the free build. The free code reaches these features only
 * through the filters and actions hooked below and falls back to no-op defaults.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-slot «update stock quantity» option (Pro / trial only).
 *
 * @param bool $enabled Default (off).
 * @param int  $index   Supplier slot index.
 * @return bool
 */
function f2000cs_pro_supplier_updates_stock_qty( $enabled, $index ) {
	if ( ! f2000cs_is_pro() ) {
		return (bool) $enabled;
	}

	return f2000cs_supplier_has_saved_stock_qty( $index );
}
add_filter( 'f2000cs_supplier_updates_stock_qty', 'f2000cs_pro_supplier_updates_stock_qty', 10, 2 );

/**
 * «Import stock quantity from XML» on the Import page (Pro / trial only).
 *
 * @param bool $allowed Default (not allowed).
 * @return bool
 */
function f2000cs_pro_import_stock_qty_allowed( $allowed ) {
	return f2000cs_is_pro() ? true : (bool) $allowed;
}
add_filter( 'f2000cs_import_stock_qty_allowed', 'f2000cs_pro_import_stock_qty_allowed' );

/**
 * Cron intervals selectable in Pro.
 *
 * @return string[]
 */
function f2000cs_pro_update_intervals() {
	return array( '5_minute', '10_minute', '15_minute', '30_minute', 'hourly', '8_times_daily', '6_times_daily', '4_times_daily', 'twicedaily', 'daily' );
}

/**
 * Use the saved update interval for Pro / trial (the free build always runs daily).
 *
 * @param string $effective Default interval (daily).
 * @param string $interval  Saved interval.
 * @return string
 */
function f2000cs_pro_effective_update_interval( $effective, $interval ) {
	if ( ! f2000cs_is_pro() ) {
		return $effective;
	}

	return in_array( $interval, f2000cs_pro_update_intervals(), true ) ? $interval : 'hourly';
}
add_filter( 'f2000cs_effective_update_interval', 'f2000cs_pro_effective_update_interval', 10, 2 );

/**
 * Adds the custom cron schedules used by the Pro update intervals.
 *
 * @param array $schedules Array of existing cron schedules.
 * @return array Modified array of cron schedules.
 */
function f2000cs_pro_cron_schedules( $schedules ) {
	// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- User-selectable sync intervals, including sub-15-min for Pro.
	$schedules['5_minute']  = array(
		'interval' => 300,
		'display'  => __( 'Що 5 хвилин', 'factorial2000-catalog-sync' ),
	);
	$schedules['10_minute'] = array(
		'interval' => 600,
		'display'  => __( 'Що 10 хвилин', 'factorial2000-catalog-sync' ),
	);
	$schedules['15_minute'] = array(
		'interval' => 900,
		'display'  => __( 'Що 15 хвилин', 'factorial2000-catalog-sync' ),
	);
	$schedules['30_minute'] = array(
		'interval' => 1800,
		'display'  => __( 'Що 30 хвилин', 'factorial2000-catalog-sync' ),
	);

	if ( ! isset( $schedules['hourly'] ) ) {
		$schedules['hourly'] = array(
			'interval' => 3600,
			'display'  => __( 'Щогодини', 'factorial2000-catalog-sync' ),
		);
	}

	$schedules['8_times_daily'] = array(
		'interval' => 10800,
		'display'  => __( '8 разів на день', 'factorial2000-catalog-sync' ),
	);

	$schedules['6_times_daily'] = array(
		'interval' => 14400,
		'display'  => __( '6 разів на день', 'factorial2000-catalog-sync' ),
	);

	$schedules['4_times_daily'] = array(
		'interval' => 21600,
		'display'  => __( '4 рази на день', 'factorial2000-catalog-sync' ),
	);

	if ( ! isset( $schedules['twicedaily'] ) ) {
		$schedules['twicedaily'] = array(
			'interval' => 43200,
			'display'  => __( 'Двічі на день', 'factorial2000-catalog-sync' ),
		);
	}

	if ( ! isset( $schedules['daily'] ) ) {
		$schedules['daily'] = array(
			'interval' => 86400,
			'display'  => __( 'Щодня', 'factorial2000-catalog-sync' ),
		);
	}

	return $schedules;
}
// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 5-minute sync is an explicitly user-selectable plugin feature (Pro plan).
add_filter( 'cron_schedules', 'f2000cs_pro_cron_schedules' );

/**
 * Keep the previous option value when the feature is Pro-only.
 *
 * Also preserves the old value when options.php passes null (field absent from POST).
 *
 * @param mixed $value     New value.
 * @param mixed $old_value Old value.
 * @return mixed
 */
function f2000cs_block_pro_option_update( $value, $old_value ) {
	return $old_value;
}

/**
 * Keep Pro-only options untouched on the free plan (Pro build without license / trial).
 *
 * Also preserves the stored values when options.php omits a field from POST.
 *
 * @return void
 */
function f2000cs_register_pro_feature_guards() {
	$max  = f2000cs_get_supplier_option_register_max();
	$keys = array(
		'f2000cs_hide_variable_low_instock',
		'f2000cs_variable_low_instock_max',
		'f2000cs_img_png_convert',
		'f2000cs_img_optimize',
		'f2000cs_img_quality',
		'f2000cs_img_max_dimension',
	);

	for ( $i = 1; $i <= $max; $i++ ) {
		$keys[] = 'f2000cs_update_stock_qty_' . $i;
	}

	$is_pro = f2000cs_is_pro();

	foreach ( $keys as $key ) {
		add_filter( 'pre_update_option_' . $key, 'f2000cs_preserve_option_if_missing_from_post', 5, 2 );

		if ( ! $is_pro ) {
			add_filter( 'pre_update_option_' . $key, 'f2000cs_block_pro_option_update', 10, 2 );
		}
	}
}
add_action( 'admin_init', 'f2000cs_register_pro_feature_guards', 1 );

/**
 * Get the configured max in-stock variations threshold for variable parent products.
 *
 * @return int
 */
function f2000cs_get_variable_low_instock_threshold() {
	$max = absint( get_option( 'f2000cs_variable_low_instock_max', 2 ) );

	return $max;
}

/**
 * Mark variable parent products as out of stock when they have too few in-stock variations.
 *
 * @return array{updated: int, examples: array<int, array{id: int, title: string, sku: string, instock_count: int}>}
 */
function f2000cs_apply_variable_low_instock_rule() {
	if ( ! f2000cs_is_pro() || get_option( 'f2000cs_hide_variable_low_instock', '0' ) !== '1' ) {
		return array(
			'updated'  => 0,
			'examples' => array(),
		);
	}

	global $wpdb;

	$max_instock = f2000cs_get_variable_low_instock_threshold();

	// Only the suppliers' products: the shop's own variable products are never touched.
	$prefixes = f2000cs_get_supplier_sku_prefixes();
	if ( empty( $prefixes ) ) {
		return array(
			'updated'  => 0,
			'examples' => array(),
		);
	}

	$prefix_sql  = implode( ' OR ', array_fill( 0, count( $prefixes ), 'sku.meta_value LIKE %s' ) );
	$prefix_args = array_map(
		static function ( $prefix ) use ( $wpdb ) {
			return $wpdb->esc_like( $prefix ) . '%';
		},
		$prefixes
	);

	// A product with no more variations than the threshold (e.g. one size) is not "low".
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $prefix_sql is a generated list of %s placeholders.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live aggregate over variations; cache would be stale.
	$parent_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT v.post_parent
			FROM {$wpdb->posts} v
			INNER JOIN {$wpdb->postmeta} pm ON v.ID = pm.post_id AND pm.meta_key = '_stock_status'
			INNER JOIN {$wpdb->postmeta} sku ON v.ID = sku.post_id AND sku.meta_key = '_sku'
			WHERE v.post_type = 'product_variation'
			  AND v.post_status IN ('publish', 'private')
			  AND ( {$prefix_sql} )
			GROUP BY v.post_parent
			HAVING SUM(pm.meta_value = 'instock') <= %d AND COUNT(*) > %d",
			array_merge( $prefix_args, array( $max_instock, $max_instock ) )
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

	if ( empty( $parent_ids ) ) {
		return array(
			'updated'  => 0,
			'examples' => array(),
		);
	}

	$updated         = 0;
	$lookup_sync_ids = array();
	$transient_ids   = array();
	$examples        = array();

	foreach ( $parent_ids as $parent_id ) {
		$parent_id = (int) $parent_id;

		if ( $parent_id <= 0 ) {
			continue;
		}

		$changed       = false;
		$instock_count = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live variation lookup; cache would be stale.
		$variation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID
				FROM {$wpdb->posts}
				WHERE post_parent = %d
				  AND post_type = 'product_variation'
				  AND post_status IN ('publish', 'private')",
				$parent_id
			)
		);

		foreach ( $variation_ids as $variation_id ) {
			$variation_id = (int) $variation_id;

			if ( get_post_meta( $variation_id, '_stock_status', true ) === 'instock' ) {
				++$instock_count;
			}
		}

		foreach ( $variation_ids as $variation_id ) {
			$variation_id = (int) $variation_id;

			// Only in-stock variations are hidden; backorder ones stay orderable.
			if ( get_post_meta( $variation_id, '_stock_status', true ) !== 'instock' ) {
				continue;
			}

			update_post_meta( $variation_id, '_stock_status', 'outofstock' );
			$lookup_sync_ids[] = $variation_id;
			$changed           = true;
		}

		if ( get_post_meta( $parent_id, '_stock_status', true ) !== 'outofstock' ) {
			update_post_meta( $parent_id, '_stock_status', 'outofstock' );
			$lookup_sync_ids[] = $parent_id;
			$changed           = true;
		}

		if ( $changed ) {
			$transient_ids[] = $parent_id;
			++$updated;
			$examples[] = array(
				'id'            => $parent_id,
				'title'         => get_the_title( $parent_id ),
				'sku'           => (string) get_post_meta( $parent_id, '_sku', true ),
				'instock_count' => $instock_count,
			);
		}
	}

	f2000cs_bulk_sync_lookup_stock_status( $lookup_sync_ids, 'outofstock' );

	foreach ( array_unique( $transient_ids ) as $product_id ) {
		wc_delete_product_transients( $product_id );
	}

	return array(
		'updated'  => $updated,
		'examples' => $examples,
	);
}

/**
 * Format sample products changed by the low-instock rule for logs and notifications.
 *
 * @param array $examples Product examples from f2000cs_apply_variable_low_instock_rule().
 * @param int   $total    Total number of changed products.
 * @param int   $limit    Maximum examples to include.
 * @return string
 */
function f2000cs_format_variable_low_instock_examples( array $examples, $total, $limit = 15 ) {
	if ( empty( $examples ) ) {
		return '';
	}

	$lines = array();
	$shown = array_slice( $examples, 0, $limit );

	foreach ( $shown as $product ) {
		$sku_part = ! empty( $product['sku'] ) ? ', SKU: ' . $product['sku'] : '';
		$lines[]  = sprintf(
			'• %s%s (варіацій в наявності: %d)',
			$product['title'],
			$sku_part,
			$product['instock_count']
		);
	}

	$message = implode( "\n", $lines );

	if ( $total > count( $shown ) ) {
		$message .= "\n... та ще " . ( $total - count( $shown ) ) . ' товарів';
	}

	return $message;
}

/**
 * Apply the variable low-instock rule after a stock update cycle and report it.
 *
 * @return void
 */
function f2000cs_pro_after_stock_update() {
	$result  = f2000cs_apply_variable_low_instock_rule();
	$updated = (int) ( $result['updated'] ?? 0 );

	if ( $updated > 0 ) {
		$max_instock = f2000cs_get_variable_low_instock_threshold();
		$examples    = $result['examples'] ?? array();
		$sample_text = f2000cs_format_variable_low_instock_examples( $examples, $updated );

		$lines = array(
			sprintf(
				'🔻 Знято з продажу варіативних товарів: %d (в наявності ≤ %d варіацій)',
				$updated,
				$max_instock
			),
		);

		// The product list is a diagnostic detail: only with «Розширений дебаг».
		if ( '' !== $sample_text && f2000cs_telegram_debug_enabled() ) {
			$lines[] = "\n" . f2000cs_telegram_escape( "Приклади:\n" . $sample_text );
		}

		f2000cs_send_telegram_notification( f2000cs_telegram_format( 'info', 'Мала наявність варіацій', $lines ), 2, true );

		$log_message = sprintf(
			'Variable low-instock rule applied: threshold=%d, updated=%d',
			$max_instock,
			$updated
		);

		if ( $sample_text !== '' ) {
			$log_message .= "\nExamples:\n" . $sample_text;
		}

		f2000cs_log( $log_message, 'info' );
	}
}

add_action( 'f2000cs_stock_update_complete', 'f2000cs_pro_after_stock_update' );

/**
 * Sanitize an export filename (basename only, plugin-generated XML names).
 *
 * @param string $filename Raw filename.
 * @return string Sanitized basename or empty string when invalid.
 */
function f2000cs_sanitize_export_filename( $filename ) {
	$filename = basename( (string) $filename );

	if ( ! preg_match( '/^(filtered|editor)-xml-[A-Za-z0-9._-]+\.xml$/', $filename ) ) {
		return '';
	}

	return $filename;
}

/**
 * Capability-gated download URL for an export XML (admin-post proxy).
 *
 * The URL carries a server-side token (24h transient) bound to the current
 * user session instead of a nonce, so the link keeps working when WordPress
 * rotates session tokens (nonce-based links then die with «link expired»).
 *
 * @param string $filename Export basename.
 * @return string
 */
function f2000cs_get_export_download_url( $filename ) {
	$filename = f2000cs_sanitize_export_filename( $filename );
	if ( '' === $filename ) {
		return '';
	}

	$session = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';
	$token   = md5( wp_hash( $filename . '|' . get_current_user_id() . '|' . $session . '|' . wp_rand() ) );

	set_transient( 'f2000cs_dl_' . $token, $filename, DAY_IN_SECONDS );

	return admin_url(
		'admin-post.php?action=f2000cs_download_export&file=' . rawurlencode( $filename ) . '&token=' . $token
	);
}

/**
 * Stream a generated export XML to an authorized admin (Pro).
 *
 * @return void
 */
function f2000cs_handle_export_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__( 'Недостатньо прав для цієї дії.', 'factorial2000-catalog-sync' ),
			esc_html__( 'Помилка', 'factorial2000-catalog-sync' ),
			array( 'response' => 403 )
		);
	}

	if ( ! f2000cs_is_pro() ) {
		wp_die(
			esc_html__( 'Завантаження вигрузок доступне лише у Pro версії.', 'factorial2000-catalog-sync' ),
			esc_html__( 'Помилка', 'factorial2000-catalog-sync' ),
			array( 'response' => 403 )
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Downloads are gated by a server-side token (see f2000cs_get_export_download_url).
	$raw_file = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
	$filename = f2000cs_sanitize_export_filename( $raw_file );
	if ( '' === $filename ) {
		wp_die(
			esc_html__( 'Невірне імʼя файлу.', 'factorial2000-catalog-sync' ),
			esc_html__( 'Помилка', 'factorial2000-catalog-sync' ),
			array( 'response' => 400 )
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-gated download.
	$token = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : '';
	if ( '' === $token || get_transient( 'f2000cs_dl_' . $token ) !== $filename ) {
		wp_die(
			esc_html__( 'Посилання недійсне або прострочене. Згенеруйте файл ще раз.', 'factorial2000-catalog-sync' ),
			esc_html__( 'Помилка', 'factorial2000-catalog-sync' ),
			array( 'response' => 403 )
		);
	}

	$path = trailingslashit( f2000cs_ensure_exports_dir() ) . $filename;
	if ( ! is_file( $path ) ) {
		wp_die(
			esc_html__( 'Файл не знайдено.', 'factorial2000-catalog-sync' ),
			esc_html__( 'Помилка', 'factorial2000-catalog-sync' ),
			array( 'response' => 404 )
		);
	}

	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	}

	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . (string) filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Binary download stream.
	readfile( $path );
	exit;
}
add_action( 'admin_post_f2000cs_download_export', 'f2000cs_handle_export_download' );
