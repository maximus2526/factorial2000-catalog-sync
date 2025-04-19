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
