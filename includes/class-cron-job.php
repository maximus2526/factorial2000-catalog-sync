<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class Cron_Job
 *
 * Handles the scheduling and execution of cron jobs for updating stock status.
 */
class Cron_Job {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'prom_update_stock_cron';

	/**
	 * Activates the cron job.
	 *
	 * @return void
	 */
	public static function activate() {
		$interval = get_option( 'prom_xml_update_interval', 'hourly' );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), $interval, self::CRON_HOOK );
			add_action( self::CRON_HOOK, array( __CLASS__, 'update_stock' ) );
		}
	}

	/**
	 * Deactivates the cron job.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Adds custom cron schedules.
	 *
	 * @param array $schedules Array of existing cron schedules.
	 * @return array Modified array of cron schedules.
	 */
	public static function add_custom_cron_schedule( $schedules ) {
		$schedules['5_minute'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'xml-prom' ),
		);

		if ( ! isset( $schedules['hourly'] ) ) {
			$schedules['hourly'] = array(
				'interval' => 3600,
				'display'  => __( 'Every Hour', 'xml-prom' ),
			);
		}

		if ( ! isset( $schedules['twicedaily'] ) ) {
			$schedules['twicedaily'] = array(
				'interval' => 43200,
				'display'  => __( 'Twice Daily', 'xml-prom' ),
			);
		}

		if ( ! isset( $schedules['daily'] ) ) {
			$schedules['daily'] = array(
				'interval' => 86400,
				'display'  => __( 'Once Daily', 'xml-prom' ),
			);
		}

		return $schedules;
	}

	/**
	 * Updates stock status based on XML data.
	 *
	 * @return void
	 */
	public static function update_stock() {
		// Get all configured XML URLs
		$xml_urls = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$url = get_option( 'prom_xml_url' . ( $i === 1 ? '' : '_' . $i ), '' );
			if ( ! empty( $url ) ) {
				$xml_urls[ $i ] = $url;
			}
		}

		if ( ! empty( $xml_urls ) ) {
			// Clean up transients before starting the update process
			prom_cleanup_wc_transients();

			// Process each XML URL
			foreach ( $xml_urls as $index => $xml_url ) {
				try {
					$sku_prefix = get_option( 'prom_xml_sku_prefix_' . $index, '' );
					$skip_price = get_option( 'prom_xml_skip_price_' . $index, '0' );
					$updater    = new XML_Stock_Updater( $xml_url, $sku_prefix, ( $skip_price === '1' || $skip_price === 'yes' || $skip_price === 'on' ) );
					$updater->update_products_stock_status();
				} catch ( Exception $e ) {
					// Silent error handling
				}
			}

			// Clean up again after the process completes
			prom_cleanup_wc_transients();
		} else {
			// No XML URLs configured - silent
		}
	}
}

add_filter( 'cron_schedules', array( 'Cron_Job', 'add_custom_cron_schedule' ) );
