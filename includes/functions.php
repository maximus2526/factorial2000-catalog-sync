<?php
defined( 'ABSPATH' ) || exit;

/**
 * Send notification to Telegram
 *
 * @param string $message Message to send
 * @return bool Success status
 */
function prom_send_telegram_notification( $message ) {
	$token    = get_option( 'telegram_token_id', '' );
	$user_ids = get_option( 'telegram_user_ids', '' );

	if ( empty( $token ) || empty( $user_ids ) ) {
		return false;
	}

	$user_ids_array = array_map( 'trim', explode( ',', $user_ids ) );
	$success        = true;

	foreach ( $user_ids_array as $user_id ) {
		if (empty($user_id)) {
			continue;
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

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$success = false;
		}
	}

	return $success;
}

/**
 * Log plugin activity
 *
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 * @return void
 */
function prom_log( $message, $level = 'info' ) {
	if ( WP_DEBUG && WP_DEBUG_LOG ) {
		error_log( "Prom XML Importer [{$level}]: {$message}" );
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
	$xml_url = get_option( 'prom_xml_url', '' );
	return ! empty( $xml_url );
}

/**
 * Clean up WooCommerce transients to free memory
 *
 * @return void
 */
function prom_cleanup_wc_transients() {
	global $wpdb;

	// Delete specific WooCommerce transients that might be using memory
	$wpdb->query("
		DELETE FROM $wpdb->options 
		WHERE option_name LIKE '%_transient_wc_product_%' 
		OR option_name LIKE '%_transient_timeout_wc_product_%'
	");

	// Clear object cache if available
	if (function_exists('wp_cache_flush')) {
		wp_cache_flush();
	}
}

/**
 * Check server resources availability for XML processing
 *
 * @return array Status information
 */
function prom_check_server_resources() {
	$memory_limit = ini_get('memory_limit');
	$max_execution_time = ini_get('max_execution_time');
	$post_max_size = ini_get('post_max_size');
	$upload_max_filesize = ini_get('upload_max_filesize');

	return [
		'memory_limit' => $memory_limit,
		'max_execution_time' => $max_execution_time,
		'post_max_size' => $post_max_size,
		'upload_max_filesize' => $upload_max_filesize,
		'is_optimal' => (
			intval($memory_limit) >= 128 && 
			intval($max_execution_time) >= 60
		)
	];
}
