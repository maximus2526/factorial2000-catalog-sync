<?php
defined( 'ABSPATH' ) || exit;

/**
 * Send notification to Telegram with retry and error handling
 *
 * @param string $message Message to send
 * @param int    $retry_count Number of retries (default: 2)
 * @return bool Success status
 */
function f2000cs_send_telegram_notification( $message, $retry_count = 2 ) {
	$token    = get_option( 'f2000cs_telegram_token_id', '' );
	$user_ids = get_option( 'f2000cs_telegram_user_ids', '' );

	if ( empty( $token ) || empty( $user_ids ) ) {
		return false;
	}

	$user_ids_array = array_map( 'trim', explode( ',', $user_ids ) );
	$success        = true;

	foreach ( $user_ids_array as $user_id ) {
		if ( empty( $user_id ) ) {
			continue;
		}

		// Limit message length to prevent API errors
		if ( strlen( $message ) > 4000 ) {
			$message = substr( $message, 0, 3997 ) . '...';
		}

		$url  = "https://api.telegram.org/bot{$token}/sendMessage";
		$args = array(
			'body'    => array(
				'chat_id'    => $user_id,
				'text'       => $message,
				'parse_mode' => 'HTML',
			),
			'timeout' => 30,
		);

		$try_count       = 0;
		$request_success = false;

		while ( ! $request_success && $try_count <= $retry_count ) {
			$response = wp_remote_post( $url, $args );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$request_success = true;
				break;
			}

			++$try_count;

			if ( $try_count <= $retry_count ) {
				// Wait before retrying (exponential backoff)
				$wait_time = pow( 2, $try_count - 1 ) * 500000; // 0.5s, 1s, 2s...
				usleep( $wait_time );
			}
		}

		if ( ! $request_success ) {
			$success = false;
		}
	}

	return $success;
}

/**
 * Log plugin activity with additional context
 *
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 * @return void
 */
function f2000cs_log( $message, $level = 'info' ) {
	$debug_log = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	// Always persist errors so operators can see cron/stock failures without enabling WP_DEBUG.
	if ( ! $debug_log && 'error' !== $level ) {
		return;
	}

	$memory_usage      = round( memory_get_usage() / 1024 / 1024, 2 );
	$formatted_message = sprintf(
		'[%s] Factorial2000 Catalog Sync [%s] [Memory: %sMB]: %s',
		gmdate( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$memory_usage,
		$message
	);
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional plugin logging.
	error_log( $formatted_message );
}

/**
 * Parse the YML <offer available="..."> attribute into a WooCommerce stock status.
 *
 * YML allows: true, false, 1, 0, yes, no, y, n (case-insensitive).
 * Missing/empty attribute defaults to instock (common YML practice).
 *
 * @param string $available Raw available attribute value.
 * @return string 'instock' or 'outofstock'.
 */
function f2000cs_parse_available( $available ) {
	$available = trim( (string) $available );

	// Omitted attribute → treat as in stock.
	if ( '' === $available ) {
		return 'instock';
	}

	$lower  = strtolower( $available );
	$truthy = array( 'true', '1', 'yes', 'y' );
	$falsey = array( 'false', '0', 'no', 'n' );

	if ( in_array( $lower, $truthy, true ) ) {
		return 'instock';
	}

	if ( in_array( $lower, $falsey, true ) ) {
		return 'outofstock';
	}

	// Unknown non-empty value → out of stock (safe default).
	return 'outofstock';
}

/**
 * Safely parse a price string from XML into float.
 *
 * Handles:
 * - Plain: "1234.56"
 * - European: "1.234,56" / "99,99" / "1 500,00"
 * - US thousands: "1,234.56"
 * - Multi-dot thousands: "1.234.56"
 *
 * @param string $value Price string from XML.
 * @return float Parsed price.
 */
function f2000cs_parse_price( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0.0;
	}

	// NBSP / thin space / regular spaces as thousands separators.
	$value = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF", ' ' ), '', $value );
	// Drop currency symbols and other non-numeric junk (keep digits, separators, minus).
	$value = preg_replace( '/[^\d,.\-]/', '', $value );
	if ( null === $value || '' === $value || '-' === $value ) {
		return 0.0;
	}

	$last_dot   = strrpos( $value, '.' );
	$last_comma = strrpos( $value, ',' );

	if ( false !== $last_comma && false !== $last_dot ) {
		if ( $last_comma > $last_dot ) {
			// European: 1.234,56
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		} else {
			// US: 1,234.56
			$value = str_replace( ',', '', $value );
		}
	} elseif ( false !== $last_comma ) {
		// Only commas — last segment is decimal when 1–2 digits.
		$parts = explode( ',', $value );
		$frac  = (string) array_pop( $parts );
		if ( strlen( $frac ) <= 2 ) {
			$value = implode( '', $parts ) . '.' . $frac;
		} else {
			$value = implode( '', $parts ) . $frac;
		}
	} elseif ( false !== $last_dot ) {
		$parts = explode( '.', $value );
		if ( count( $parts ) > 2 ) {
			// Multi-dot: 1.234.56 → last segment decimal when 1–2 digits.
			$frac = (string) array_pop( $parts );
			if ( strlen( $frac ) <= 2 ) {
				$value = implode( '', $parts ) . '.' . $frac;
			} else {
				$value = implode( '', $parts ) . $frac;
			}
		}
	}

	return (float) $value;
}

/**
 * Download a URL to a temporary file, with SSL verification disabled.
 *
 * Many supplier XML/image servers use self-signed or expired certificates.
 *
 * @param string $url     The URL to download.
 * @param int    $timeout Download timeout in seconds.
 * @return string|WP_Error Local temporary file path, or WP_Error on failure.
 */
function f2000cs_download_url( $url, $timeout = 300 ) {
	add_filter( 'http_request_args', 'f2000cs_disable_ssl_verify', 99, 1 );
	try {
		return download_url( $url, $timeout );
	} finally {
		remove_filter( 'http_request_args', 'f2000cs_disable_ssl_verify', 99 );
	}
}

/**
 * Filter callback: disable SSL verification on HTTP requests.
 *
 * @param array $args Request arguments.
 * @return array Modified arguments.
 */
function f2000cs_disable_ssl_verify( $args ) {
	$args['sslverify'] = false;
	return $args;
}

/**
 * Get plugin settings URL

(Showing lines 90-133 of 837. Use offset=134 to continue.)
 *
 * @return string Admin URL for plugin settings
 */
function f2000cs_get_settings_url() {
	return admin_url( 'admin.php?page=f2000cs-update' );
}

/**
 * Get the price adjustment settings (type, direction, value) for a supplier slot.
 *
 * @param int $index Supplier slot index (1-5).
 * @return array{type: string, direction: string, value: float}
 */
function f2000cs_get_price_adjust_settings( $index ) {
	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		return array(
			'type'      => 'markup',
			'direction' => 'add',
			'value'     => 0.0,
		);
	}

	return array(
		'type'      => get_option( 'f2000cs_price_adjust_type_' . $index, 'markup' ),
		'direction' => get_option( 'f2000cs_price_adjust_direction_' . $index, 'add' ),
		'value'     => (float) get_option( 'f2000cs_price_adjust_value_' . $index, '0' ),
	);
}

/**
 * Whether quantity updates are enabled for a supplier slot (Pro only; default off).
 *
 * @param int $index Supplier slot 1+.
 * @return bool
 */
function f2000cs_supplier_updates_stock_qty( $index ) {
	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		return false;
	}

	$flag = get_option( 'f2000cs_update_stock_qty_' . absint( $index ), '0' );

	return ( '1' === $flag || 'yes' === $flag || 'on' === $flag );
}

/**
 * Soft ceiling when scanning/registering dynamic supplier option keys.
 * Pro/trial can add as many as needed up to this safety cap.
 */
define( 'F2000CS_SUPPLIER_SLOT_SCAN_MAX', 200 );

/**
 * Option name for a supplier XML URL (slot 1 keeps legacy key f2000cs_url).
 *
 * @param int $index Supplier slot 1+.
 * @return string
 */
function f2000cs_get_supplier_url_option_key( $index ) {
	$index = absint( $index );

	return 1 === $index ? 'f2000cs_url' : 'f2000cs_url_' . $index;
}

/**
 * Saved XML URL for a supplier slot.
 *
 * @param int $index Supplier slot 1+.
 * @return string
 */
function f2000cs_get_supplier_url( $index ) {
	return trim( (string) get_option( f2000cs_get_supplier_url_option_key( $index ), '' ) );
}

/**
 * Whether an extra supplier slot (2+) already has saved configuration.
 *
 * Used to grandfather Free users who configured multiple suppliers before Pro.
 *
 * @param int $index Supplier slot.
 * @return bool
 */
function f2000cs_supplier_slot_has_saved_data( $index ) {
	$index = absint( $index );

	if ( $index < 2 ) {
		return false;
	}

	if ( '' !== f2000cs_get_supplier_url( $index ) ) {
		return true;
	}

	if ( '' !== trim( (string) get_option( 'f2000cs_sku_prefix_' . $index, '' ) ) ) {
		return true;
	}

	return false;
}

/**
 * Highest supplier slot index that has saved data (at least 1).
 *
 * @return int
 */
function f2000cs_get_highest_saved_supplier_slot() {
	$highest = 1;

	for ( $i = 2; $i <= F2000CS_SUPPLIER_SLOT_SCAN_MAX; $i++ ) {
		if ( f2000cs_supplier_slot_has_saved_data( $i ) ) {
			$highest = $i;
		}
	}

	return $highest;
}

/**
 * Max slot index to register with the Settings API for the current request.
 *
 * Always covers legacy 2–5, any saved higher slots, and slots present in POST (new Add).
 *
 * @return int
 */
function f2000cs_get_supplier_option_register_max() {
	$max = max( 5, f2000cs_get_highest_saved_supplier_slot() );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only inspecting keys to register settings before save.
	if ( ! empty( $_POST ) && is_array( $_POST ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only inspecting keys to register settings before save.
		foreach ( array_keys( $_POST ) as $key ) {
			if ( preg_match( '/^f2000cs_(?:url|sku_prefix|skip_price|update_stock_qty|price_adjust_(?:type|direction|value))_(\d+)$/', (string) $key, $m ) ) {
				$max = max( $max, absint( $m[1] ) );
			}
		}
	}

	return min( F2000CS_SUPPLIER_SLOT_SCAN_MAX, $max );
}

/**
 * All extra supplier slot indexes (2+) that currently have saved data.
 *
 * @return int[]
 */
function f2000cs_get_saved_extra_supplier_slots() {
	$slots = array();
	$max   = f2000cs_get_highest_saved_supplier_slot();

	for ( $i = 2; $i <= $max; $i++ ) {
		if ( f2000cs_supplier_slot_has_saved_data( $i ) ) {
			$slots[] = $i;
		}
	}

	return $slots;
}

/**
 * Supplier slots visible in the repeater: always 1, plus extras that already have saved data.
 *
 * @return int[]
 */
function f2000cs_get_visible_supplier_slots() {
	return array_merge( array( 1 ), f2000cs_get_saved_extra_supplier_slots() );
}

/**
 * Check if all required settings are configured
 *
 * @return bool True if configured, false otherwise
 */
function f2000cs_is_configured() {
	if ( '' !== f2000cs_get_supplier_url( 1 ) ) {
		return true;
	}

	return ! empty( f2000cs_get_saved_extra_supplier_slots() );
}

/**
 * Clean up WooCommerce transients to free memory
 *
 * @param bool $aggressive Whether to perform aggressive cleanup
 * @return void
 */
function f2000cs_cleanup_wc_transients( $aggressive = false ) {
	global $wpdb;

	// Delete specific WooCommerce transients that might be using memory
	if ( $aggressive ) {
		// More aggressive cleanup for production environments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup with static query; caching not applicable.
		$wpdb->query(
			"
            DELETE FROM $wpdb->options 
            WHERE option_name LIKE '%_transient_%' 
            AND (
                option_name LIKE '%_wc_%' 
                OR option_name LIKE '%_product_%' 
                OR option_name LIKE '%_woocommerce_%'
            )
        "
		);
	} else {
		// Standard cleanup - only product specific transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup with static query; caching not applicable.
		$wpdb->query(
			"
            DELETE FROM $wpdb->options 
            WHERE option_name LIKE '%_transient_wc_product_%' 
            OR option_name LIKE '%_transient_timeout_wc_product_%'
        "
		);
	}

	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

/**
 * Check server resources availability for XML processing
 *
 * @return array Status information
 */
function f2000cs_check_server_resources() {
	$memory_limit        = ini_get( 'memory_limit' );
	$max_execution_time  = ini_get( 'max_execution_time' );
	$post_max_size       = ini_get( 'post_max_size' );
	$upload_max_filesize = ini_get( 'upload_max_filesize' );

	return array(
		'memory_limit'        => $memory_limit,
		'max_execution_time'  => $max_execution_time,
		'post_max_size'       => $post_max_size,
		'upload_max_filesize' => $upload_max_filesize,
	);
}

/**
 * Bulk-update stock_status in wc_product_meta_lookup (fast path, no full product save).
 *
 * @param array  $product_ids  Product or variation IDs.
 * @param string $stock_status Stock status value.
 * @return void
 */
function f2000cs_bulk_sync_lookup_stock_status( array $product_ids, $stock_status = 'outofstock' ) {
	global $wpdb;

	if ( empty( $product_ids ) || empty( $wpdb->wc_product_meta_lookup ) ) {
		return;
	}

	$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );

	if ( empty( $product_ids ) ) {
		return;
	}

	$stock_status = wc_clean( $stock_status );
	$table        = $wpdb->wc_product_meta_lookup;

	foreach ( array_chunk( $product_ids, 500 ) as $chunk ) {
		$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$table} is an internal WC lookup table name and $placeholders is a generated %d list; all values passed to prepare().
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET stock_status = %s
				WHERE product_id IN ($placeholders)
				  AND stock_status != %s",
				array_merge( array( $stock_status ), $chunk, array( $stock_status ) )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}
}

/**
 * HPOS-safe stock status update: writes both postmeta and the lookup table.
 *
 * @param int    $product_id   Product or variation ID.
 * @param string $stock_status 'instock' or 'outofstock'.
 * @return void
 */
function f2000cs_update_stock_status( $product_id, $stock_status ) {
	update_post_meta( $product_id, '_stock_status', $stock_status );
	f2000cs_sync_single_lookup_stock( $product_id, $stock_status );
}

/**
 * Sync a single product's stock status into the HPOS-compatible lookup table.
 *
 * @param int    $product_id   Product or variation ID.
 * @param string $stock_status 'instock' or 'outofstock'.
 * @return void
 */
function f2000cs_sync_single_lookup_stock( $product_id, $stock_status ) {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return;
	}

	$product_id   = absint( $product_id );
	$stock_status = wc_clean( $stock_status );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time HPOS sync; cache would be stale.
	$updated = $wpdb->update(
		$wpdb->wc_product_meta_lookup,
		array( 'stock_status' => $stock_status ),
		array( 'product_id' => $product_id )
	);

	// If the row doesn't exist yet (freshly imported product), insert it.
	if ( 0 === (int) $updated ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$wpdb->wc_product_meta_lookup,
			array(
				'product_id'   => $product_id,
				'stock_status' => $stock_status,
			)
		);
	}
}

/**
 * Sync a single product's prices into the HPOS-compatible lookup table.
 *
 * Reads _price, _regular_price, _sale_price from postmeta and updates
 * wc_product_meta_lookup accordingly.
 *
 * @param int $product_id Product or variation ID.
 * @return void
 */
function f2000cs_sync_price_lookup( $product_id ) {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return;
	}

	$product_id = absint( $product_id );

	$price         = get_post_meta( $product_id, '_price', true );
	$regular_price = get_post_meta( $product_id, '_regular_price', true );
	$sale_price    = get_post_meta( $product_id, '_sale_price', true );

	$price         = '' !== $price ? (float) $price : null;
	$regular_price = '' !== $regular_price ? (float) $regular_price : null;
	$sale_price    = '' !== $sale_price ? (float) $sale_price : null;

	$min_price = $sale_price > 0 ? $sale_price : $price;
	$max_price = $regular_price > 0 ? $regular_price : $price;

	if ( null === $min_price && null === $max_price ) {
		return;
	}

	$data = array(
		'min_price' => $min_price,
		'max_price' => $max_price,
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT product_id FROM {$wpdb->wc_product_meta_lookup} WHERE product_id = %d LIMIT 1",
			$product_id
		)
	);

	if ( $exists ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->wc_product_meta_lookup,
			$data,
			array( 'product_id' => $product_id )
		);
		return;
	}

	$data['product_id'] = $product_id;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->insert( $wpdb->wc_product_meta_lookup, $data );
}

/**
 * Look up WooCommerce product/variation IDs by their SKU values.
 *
 * Used by the XML import and stock sync flows.  Chunked for large sets
 * (max 500 SKUs per query).
 *
 * @param array<string> $skus SKU values to look up.
 * @return array<string, int> Map of sku => product_id.
 */
function f2000cs_get_product_ids_by_skus( array $skus ): array {
	global $wpdb;

	if ( empty( $skus ) ) {
		return array();
	}

	$results = array();

	foreach ( array_chunk( $skus, 500 ) as $chunk ) {
		$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT pm.meta_value AS sku, p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'draft', 'private')
			AND pm.meta_key = '_sku'
			AND pm.meta_value IN ($placeholders)",
			$chunk
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		foreach ( (array) $rows as $row ) {
			$results[ (string) $row->sku ] = (int) $row->ID;
		}
	}

	return $results;
}

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
	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		return array(
			'updated'  => 0,
			'examples' => array(),
		);
	}

	if ( get_option( 'f2000cs_hide_variable_low_instock', '0' ) !== '1' ) {
		return array(
			'updated'  => 0,
			'examples' => array(),
		);
	}

	global $wpdb;

	$max_instock = f2000cs_get_variable_low_instock_threshold();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live aggregate over variations; cache would be stale.
	$parent_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT v.post_parent
			FROM {$wpdb->posts} v
			INNER JOIN {$wpdb->postmeta} pm ON v.ID = pm.post_id AND pm.meta_key = '_stock_status'
			WHERE v.post_type = 'product_variation'
			  AND v.post_status IN ('publish', 'private')
			GROUP BY v.post_parent
			HAVING SUM(pm.meta_value = 'instock') <= %d",
			$max_instock
		)
	);

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

			if ( get_post_meta( $variation_id, '_stock_status', true ) === 'outofstock' ) {
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
 * Run post-processing steps after a stock update cycle completes.
 *
 * @return void
 */
function f2000cs_after_stock_update_complete() {
	$result  = f2000cs_apply_variable_low_instock_rule();
	$updated = (int) ( $result['updated'] ?? 0 );

	if ( $updated > 0 ) {
		$max_instock = f2000cs_get_variable_low_instock_threshold();
		$examples    = $result['examples'] ?? array();
		$sample_text = f2000cs_format_variable_low_instock_examples( $examples, $updated );

		$telegram_message = sprintf(
			'Variable-товарів переведено в «Немає в наявності» (≤ %d варіацій в наявності): %d',
			$max_instock,
			$updated
		);

		if ( $sample_text !== '' ) {
			$telegram_message .= "\n\nПриклади:\n" . $sample_text;
		}

		f2000cs_send_telegram_notification( $telegram_message );

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

/**
 * Run product synchronization via a background process
 *
 * @param string $xml_url URL of the XML file
 * @param string $sku_prefix SKU prefix for this XML source
 * @return bool Whether sync was started
 */
function f2000cs_trigger_background_sync( $xml_url, $sku_prefix = '' ) {
	if ( empty( $xml_url ) ) {
		return false;
	}

	if ( ! wp_next_scheduled( 'f2000cs_update_stock_cron' ) ) {
		\F2000CS\Cron_Job::activate();
	}

	// Schedule the update to happen in the background in 30 seconds
	if ( ! wp_next_scheduled( 'f2000cs_single_update_event', array( $xml_url, $sku_prefix ) ) ) {
		// One more job added to the pending batch; only increment once this event is
		// actually newly scheduled, so the counter always matches the number of events
		// that will really fire.
		f2000cs_increment_background_batch_counter( 1 );
		wp_schedule_single_event( time() + 30, 'f2000cs_single_update_event', array( $xml_url, $sku_prefix ) );
		return true;
	}

	return false;
}

/**
 * Track how many background sync jobs are still pending in the current batch,
 * so post-processing (low-instock rule, Telegram summary) runs only once,
 * after the last job in the batch finishes rather than after every single job.
 *
 * @param int $count Number of jobs to add to the pending counter.
 * @return void
 */
function f2000cs_increment_background_batch_counter( $count ) {
	$remaining = (int) get_transient( 'f2000cs_bg_batch_remaining' );
	set_transient( 'f2000cs_bg_batch_remaining', $remaining + $count, HOUR_IN_SECONDS );
}

/**
 * Decrement the pending background batch counter.
 *
 * @return int Remaining jobs after decrementing (0 or less means the batch is done).
 */
function f2000cs_decrement_background_batch_counter() {
	$remaining = (int) get_transient( 'f2000cs_bg_batch_remaining' ) - 1;

	if ( $remaining > 0 ) {
		set_transient( 'f2000cs_bg_batch_remaining', $remaining, HOUR_IN_SECONDS );
	} else {
		delete_transient( 'f2000cs_bg_batch_remaining' );
	}

	return $remaining;
}

/**
 * Find the next pending 'f2000cs_single_update_event' regardless of its scheduled arguments.
 *
 * wp_next_scheduled() only matches events scheduled with the exact same arguments, but our
 * background events are always scheduled with a unique (url, sku_prefix) pair, so checking
 * with no arguments would never find them. This scans the cron array directly instead.
 *
 * @return int|false Timestamp of the next pending event, or false if none is scheduled.
 */
function f2000cs_get_next_background_event() {
	$crons = _get_cron_array();

	if ( empty( $crons ) ) {
		return false;
	}

	foreach ( $crons as $timestamp => $hooks ) {
		// The cron array also carries a non-numeric 'version' key alongside timestamp keys; skip it.
		if ( ! is_array( $hooks ) ) {
			continue;
		}

		if ( ! empty( $hooks['f2000cs_single_update_event'] ) ) {
			return (int) $timestamp;
		}
	}

	return false;
}

/**
 * Clear all pending 'f2000cs_single_update_event' cron events, regardless of their
 * scheduled arguments, and reset the background batch counter.
 *
 * wp_clear_scheduled_hook( 'f2000cs_single_update_event' ) without an $args parameter only
 * removes events scheduled with an *empty* args array. Since our background events are
 * always scheduled with a unique (url, sku_prefix) pair, that call is a no-op and leaves
 * the events (and the eventual duplicate execution) in place. This walks the cron array
 * directly and unschedules every matching entry regardless of its arguments.
 *
 * @return void
 */
function f2000cs_clear_all_background_events() {
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

	delete_transient( 'f2000cs_bg_batch_remaining' );
}

add_action(
	'f2000cs_single_update_event',
	function ( $xml_url, $sku_prefix = '' ) {
		if ( ! empty( $xml_url ) ) {
			f2000cs_cleanup_wc_transients();
			// Try to detect which slot this URL belongs to, to read the skip-price flag and price adjustment.
			$skip_price_flag = false;
			$update_qty_flag = false;
			$price_adjust    = array(
				'type'      => 'markup',
				'direction' => 'add',
				'value'     => 0,
			);
			$highest         = function_exists( 'f2000cs_get_highest_saved_supplier_slot' )
				? f2000cs_get_highest_saved_supplier_slot()
				: 5;
			for ( $i = 1; $i <= $highest; $i++ ) {
				$cfg_url = get_option( 'f2000cs_url' . ( $i === 1 ? '' : '_' . $i ), '' );
				if ( $cfg_url === $xml_url ) {
					$skip_price      = get_option( 'f2000cs_skip_price_' . $i, '0' );
					$skip_price_flag = ( $skip_price === '1' || $skip_price === 'yes' || $skip_price === 'on' );
					$price_adjust    = f2000cs_get_price_adjust_settings( $i );
					$update_qty_flag = function_exists( 'f2000cs_supplier_updates_stock_qty' )
						? f2000cs_supplier_updates_stock_qty( $i )
						: false;
					break;
				}
			}

			$updater = new \F2000CS\XML_Stock_Updater( $xml_url, $sku_prefix, $skip_price_flag, $price_adjust, $update_qty_flag );
			$updater->update_products_stock_status();

			// Only run post-processing (low-instock rule, Telegram summary) once the whole batch is done.
			if ( f2000cs_decrement_background_batch_counter() <= 0 ) {
				f2000cs_after_stock_update_complete();
			}

			f2000cs_cleanup_wc_transients( true );
		}
	},
	10,
	2
);

/**
 * Run database migrations when the stored version is older.
 *
 * Runs on plugins_loaded (so cron sees migrated options), admin_init, and activation.
 *
 * @return void
 */
function f2000cs_maybe_run_migrations() {
	$stored = (int) get_option( 'f2000cs_db_version', 0 );

	if ( $stored >= F2000CS_DB_VERSION ) {
		return;
	}

	// Migration 1: rename f2000cs_sku_prefix → f2000cs_sku_prefix_1 (slot-1 key).
	if ( $stored < 1 ) {
		f2000cs_migrate_sku_prefix_slot_1();
	}

	update_option( 'f2000cs_db_version', F2000CS_DB_VERSION, false );
}

/**
 * Migrate old f2000cs_sku_prefix to f2000cs_sku_prefix_1.
 *
 * In versions before the slot system, slot 1 used the unprefixed key.
 * After renaming, existing installs lose their prefix unless migrated.
 *
 * @return void
 */
function f2000cs_migrate_sku_prefix_slot_1() {
	$old = get_option( 'f2000cs_sku_prefix', null );

	if ( null === $old ) {
		return;
	}

	if ( '' === trim( (string) $old ) ) {
		delete_option( 'f2000cs_sku_prefix' );
		return;
	}

	// Only migrate if the new key is not already set (don't overwrite).
	$current = get_option( 'f2000cs_sku_prefix_1', null );
	if ( null === $current || '' === trim( (string) $current ) ) {
		update_option( 'f2000cs_sku_prefix_1', $old, false );
	}

	delete_option( 'f2000cs_sku_prefix' );
}

/**
 * Absolute path to the plugin exports directory under uploads.
 *
 * @return string
 */
function f2000cs_get_exports_dir() {
	$upload_dir = wp_upload_dir();

	return trailingslashit( $upload_dir['basedir'] ) . 'f2000cs-exports';
}

/**
 * Ensure an uploads subdirectory exists and is not web-listable (Apache).
 *
 * @param string $dir Absolute directory path.
 * @return string Same directory path.
 */
function f2000cs_protect_uploads_subdir( $dir ) {
	$dir = untrailingslashit( (string) $dir );

	if ( '' === $dir ) {
		return '';
	}

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		$rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Protection file written once beside private uploads.
		file_put_contents( $htaccess, $rules );
	}

	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Silence directory listing.
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	return $dir;
}

/**
 * Ensure the exports directory exists and is not listable via the web server.
 *
 * @return string Absolute directory path.
 */
function f2000cs_ensure_exports_dir() {
	return f2000cs_protect_uploads_subdir( f2000cs_get_exports_dir() );
}

/**
 * Absolute path to XML editor session storage under uploads.
 *
 * @return string
 */
function f2000cs_get_editor_sessions_dir() {
	$upload_dir = wp_upload_dir();

	return trailingslashit( $upload_dir['basedir'] ) . 'f2000cs-editor-sessions';
}

/**
 * Ensure editor session directory exists and is not web-listable.
 *
 * @return string Absolute directory path.
 */
function f2000cs_ensure_editor_sessions_dir() {
	return f2000cs_protect_uploads_subdir( f2000cs_get_editor_sessions_dir() );
}

/**
 * Recursively delete a directory under wp uploads (best-effort).
 *
 * @param string $dir Absolute directory path.
 * @return void
 */
function f2000cs_delete_uploads_subdir( $dir ) {
	$dir = untrailingslashit( (string) $dir );
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$basedir    = isset( $upload_dir['basedir'] ) ? untrailingslashit( (string) $upload_dir['basedir'] ) : '';
	if ( '' === $basedir || 0 !== strpos( $dir, $basedir ) ) {
		return;
	}

	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			f2000cs_delete_uploads_subdir( $path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Uninstall cleanup of plugin-owned temp files.
			unlink( $path );
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Uninstall cleanup.
	rmdir( $dir );
}

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
 * @param string $filename Export basename.
 * @return string
 */
function f2000cs_get_export_download_url( $filename ) {
	$filename = f2000cs_sanitize_export_filename( $filename );
	if ( '' === $filename ) {
		return '';
	}

	return wp_nonce_url(
		admin_url( 'admin-post.php?action=f2000cs_download_export&file=' . rawurlencode( $filename ) ),
		'f2000cs_download_export'
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

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		wp_die(
			esc_html__( 'Завантаження вигрузок доступне лише у Pro версії.', 'factorial2000-catalog-sync' ),
			esc_html__( 'Помилка', 'factorial2000-catalog-sync' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'f2000cs_download_export' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by check_admin_referer() above.
	$raw_file = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
	$filename = f2000cs_sanitize_export_filename( $raw_file );
	if ( '' === $filename ) {
		wp_die(
			esc_html__( 'Невірне імʼя файлу.', 'factorial2000-catalog-sync' ),
			esc_html__( 'Помилка', 'factorial2000-catalog-sync' ),
			array( 'response' => 400 )
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
