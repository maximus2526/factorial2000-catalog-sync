<?php
defined( 'ABSPATH' ) || exit;

/**
 * Send notification to Telegram with retry and error handling
 *
 * @param string $message Message to send
 * @param int    $retry_count Number of retries (default: 2)
 * @return bool Success status
 */
function prom_send_telegram_notification( $message, $retry_count = 2 ) {
	$token    = get_option( 'telegram_token_id', '' );
	$user_ids = get_option( 'telegram_user_ids', '' );

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

		// Retry logic
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
function prom_log( $message, $level = 'info' ) {
	if ( WP_DEBUG && WP_DEBUG_LOG ) {
		// Add memory usage to log for debugging performance issues
		$memory_usage      = round( memory_get_usage() / 1024 / 1024, 2 );
		$formatted_message = sprintf(
			'[%s] Prom XML Importer [%s] [Memory: %sMB]: %s',
			date( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$memory_usage,
			$message
		);
		error_log( $formatted_message );
	}
}

/**
 * Get plugin settings URL
 *
 * @return string Admin URL for plugin settings
 */
function prom_get_settings_url() {
	return admin_url( 'admin.php?page=prom-xml-importer-update' );
}

/**
 * Check if all required settings are configured
 *
 * @return bool True if configured, false otherwise
 */
function prom_is_configured() {
	// Check if at least one XML URL is configured
	for ( $i = 1; $i <= 5; $i++ ) {
		$xml_url = get_option( 'prom_xml_url' . ( $i === 1 ? '' : '_' . $i ), '' );
		if ( ! empty( $xml_url ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Clean up WooCommerce transients to free memory
 *
 * @param bool $aggressive Whether to perform aggressive cleanup
 * @return void
 */
function prom_cleanup_wc_transients( $aggressive = false ) {
	global $wpdb;

	// Delete specific WooCommerce transients that might be using memory
	if ( $aggressive ) {
		// More aggressive cleanup for production environments
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
		// Standard cleanup - only product specific transients
		$wpdb->query(
			"
            DELETE FROM $wpdb->options 
            WHERE option_name LIKE '%_transient_wc_product_%' 
            OR option_name LIKE '%_transient_timeout_wc_product_%'
        "
		);
	}

	// Clear object cache if available
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

/**
 * Check server resources availability for XML processing
 *
 * @return array Status information
 */
function prom_check_server_resources() {
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
function prom_bulk_sync_lookup_stock_status( array $product_ids, $stock_status = 'outofstock' ) {
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

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET stock_status = %s
				WHERE product_id IN ($placeholders)
				  AND stock_status != %s",
				array_merge( array( $stock_status ), $chunk, array( $stock_status ) )
			)
		);
	}
}

/**
 * Get the configured max in-stock variations threshold for variable parent products.
 *
 * @return int
 */
function prom_get_variable_low_instock_threshold() {
	$max = absint( get_option( 'prom_xml_variable_low_instock_max', 2 ) );

	return $max;
}

/**
 * Mark variable parent products as out of stock when they have too few in-stock variations.
 *
 * @return array{updated: int, examples: array<int, array{id: int, title: string, sku: string, instock_count: int}>}
 */
function prom_apply_variable_low_instock_rule() {
	if ( get_option( 'prom_xml_hide_variable_low_instock', '0' ) !== '1' ) {
		return array(
			'updated'  => 0,
			'examples' => array(),
		);
	}

	global $wpdb;

	$max_instock = prom_get_variable_low_instock_threshold();

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
			$examples[]      = array(
				'id'            => $parent_id,
				'title'         => get_the_title( $parent_id ),
				'sku'           => (string) get_post_meta( $parent_id, '_sku', true ),
				'instock_count' => $instock_count,
			);
		}
	}

	prom_bulk_sync_lookup_stock_status( $lookup_sync_ids, 'outofstock' );

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
 * @param array $examples Product examples from prom_apply_variable_low_instock_rule().
 * @param int   $total    Total number of changed products.
 * @param int   $limit    Maximum examples to include.
 * @return string
 */
function prom_format_variable_low_instock_examples( array $examples, $total, $limit = 15 ) {
	if ( empty( $examples ) ) {
		return '';
	}

	$lines   = array();
	$shown   = array_slice( $examples, 0, $limit );

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
function prom_after_stock_update_complete() {
	$result  = prom_apply_variable_low_instock_rule();
	$updated = (int) ( $result['updated'] ?? 0 );

	if ( $updated > 0 ) {
		$max_instock = prom_get_variable_low_instock_threshold();
		$examples    = $result['examples'] ?? array();
		$sample_text = prom_format_variable_low_instock_examples( $examples, $updated );

		$telegram_message = sprintf(
			"Variable-товарів переведено в «Немає в наявності» (≤ %d варіацій в наявності): %d",
			$max_instock,
			$updated
		);

		if ( $sample_text !== '' ) {
			$telegram_message .= "\n\nПриклади:\n" . $sample_text;
		}

		prom_send_telegram_notification( $telegram_message );

		$log_message = sprintf(
			"Variable low-instock rule applied: threshold=%d, updated=%d",
			$max_instock,
			$updated
		);

		if ( $sample_text !== '' ) {
			$log_message .= "\nExamples:\n" . $sample_text;
		}

		prom_log( $log_message, 'info' );
	}
}

/**
 * Run product synchronization via a background process
 *
 * @param string $xml_url URL of the XML file
 * @param string $sku_prefix SKU prefix for this XML source
 * @return bool Whether sync was started
 */
function prom_trigger_background_sync( $xml_url, $sku_prefix = '' ) {
	if ( empty( $xml_url ) ) {
		return false;
	}

	if ( ! wp_next_scheduled( 'prom_update_stock_cron' ) ) {
		Cron_Job::activate();
	}

	// Schedule the update to happen in the background in 30 seconds
	if ( ! wp_next_scheduled( 'prom_single_update_event', array( $xml_url, $sku_prefix ) ) ) {
		wp_schedule_single_event( time() + 30, 'prom_single_update_event', array( $xml_url, $sku_prefix ) );
		return true;
	}

	return false;
}

// Add action for the single update event
add_action(
	'prom_single_update_event',
	function ( $xml_url, $sku_prefix = '' ) {
		if ( ! empty( $xml_url ) ) {
			prom_cleanup_wc_transients();
			// Try to detect which slot this URL belongs to, to read the skip-price flag.
			$skip_price_flag = false;
			for ( $i = 1; $i <= 5; $i++ ) {
				$cfg_url = get_option( 'prom_xml_url' . ( $i === 1 ? '' : '_' . $i ), '' );
				if ( $cfg_url === $xml_url ) {
					$skip_price = get_option( 'prom_xml_skip_price_' . $i, '0' );
					$skip_price_flag = ( $skip_price === '1' || $skip_price === 'yes' || $skip_price === 'on' );
					break;
				}
			}

			$updater = new XML_Stock_Updater( $xml_url, $sku_prefix, $skip_price_flag );
			$updater->update_products_stock_status();
			prom_after_stock_update_complete();
			prom_cleanup_wc_transients( true );
		}
	}
);
