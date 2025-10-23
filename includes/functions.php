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
			prom_log( "Failed to send Telegram notification to chat_id: $user_id", 'error' );
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
		prom_log( "Scheduled background sync for XML: $xml_url with SKU prefix: $sku_prefix", 'info' );
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
			prom_cleanup_wc_transients( true );
		}
	}
);
