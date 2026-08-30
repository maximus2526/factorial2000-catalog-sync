<?php

namespace F2000CS;

use Exception;

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
	const CRON_HOOK = 'f2000cs_update_stock_cron';

	/**
	 * Activates the cron job.
	 *
	 * @return void
	 */
	public static function activate() {
		$interval = function_exists( 'f2000cs_get_effective_update_interval' )
			? f2000cs_get_effective_update_interval()
			: get_option( 'f2000cs_update_interval', 'hourly' );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// The hook itself is already attached to update_stock() on every request via f2000cs_init().
			wp_schedule_event( time(), $interval, self::CRON_HOOK );
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

	/**
	 * Updates stock status based on XML data.
	 *
	 * @return void
	 */
	public static function update_stock() {
		$xml_urls = function_exists( 'f2000cs_get_active_supplier_urls' )
			? f2000cs_get_active_supplier_urls()
			: array();

		if ( empty( $xml_urls ) ) {
			$highest = function_exists( 'f2000cs_get_highest_saved_supplier_slot' )
				? f2000cs_get_highest_saved_supplier_slot()
				: 5;
			for ( $i = 1; $i <= $highest; $i++ ) {
				$url = get_option( 'f2000cs_url' . ( $i === 1 ? '' : '_' . $i ), '' );
				if ( ! empty( $url ) ) {
					$xml_urls[ $i ] = $url;
				}
			}
		}

		if ( ! empty( $xml_urls ) ) {
			// The scheduled cron does not consume the Free manual-update quota:
			// the quota gates only the «Запустити оновлення» button in the admin.
			// The cron frequency itself is already limited by the effective interval.

			// Clean up transients before starting the update process
			f2000cs_cleanup_wc_transients();

			foreach ( $xml_urls as $index => $xml_url ) {
				try {
					$sku_prefix   = get_option( 'f2000cs_sku_prefix_' . $index, '' );
					$skip_price   = get_option( 'f2000cs_skip_price_' . $index, '0' );
					$price_adjust = f2000cs_get_price_adjust_settings( $index );
					$update_qty   = function_exists( 'f2000cs_supplier_updates_stock_qty' )
						? f2000cs_supplier_updates_stock_qty( $index )
						: false;
					$updater      = new XML_Stock_Updater(
						$xml_url,
						$sku_prefix,
						( $skip_price === '1' || $skip_price === 'yes' || $skip_price === 'on' ),
						$price_adjust,
						$update_qty
					);
					$updater->update_products_stock_status();
				} catch ( Exception $e ) {
					$msg = sprintf(
						'Stock update failed for supplier #%d (%s): %s',
						(int) $index,
						(string) $xml_url,
						$e->getMessage()
					);
					if ( function_exists( 'f2000cs_log' ) ) {
						f2000cs_log( $msg, 'error' );
					}
					if ( function_exists( 'f2000cs_send_telegram_notification' ) ) {
						f2000cs_send_telegram_notification( '❌ ' . $msg );
					}
				}
			}

			f2000cs_after_stock_update_complete();

			f2000cs_cleanup_wc_transients();
		} else { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElse -- No XML URLs configured - silent.
			// No XML URLs configured - silent
		}
	}
}

	// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 5-minute sync is an explicitly user-selectable plugin feature (Pro plan).
	add_filter( 'cron_schedules', array( Cron_Job::class, 'add_custom_cron_schedule' ) );

/**
 * Reschedule the cron job whenever the update interval setting changes,
 * so the new interval takes effect immediately instead of after the next
 * manual stop/start.
 *
 * @param mixed $old_value Previous option value.
 * @param mixed $new_value New option value.
 * @return void
 */
// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Module keeps its helper function next to the class.
function f2000cs_reschedule_cron_on_interval_change( $old_value, $new_value ) {
	if ( $old_value === $new_value ) {
		return;
	}

	if ( wp_next_scheduled( Cron_Job::CRON_HOOK ) ) {
		Cron_Job::deactivate();
		Cron_Job::activate();
	}
}
add_action( 'update_option_f2000cs_update_interval', 'F2000CS\f2000cs_reschedule_cron_on_interval_change', 10, 2 );
