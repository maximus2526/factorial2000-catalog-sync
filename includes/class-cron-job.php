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
	 * @param int $first_run Timestamp of the first run (0 = now).
	 * @return void
	 */
	public static function activate( $first_run = 0 ) {
		$interval = function_exists( 'f2000cs_get_effective_update_interval' )
			? f2000cs_get_effective_update_interval()
			: get_option( 'f2000cs_update_interval', 'hourly' );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// The hook itself is already attached to update_stock() on every request via f2000cs_init().
			wp_schedule_event( $first_run > 0 ? (int) $first_run : time(), $interval, self::CRON_HOOK );
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
	 * Keep the scheduled event on the effective interval.
	 *
	 * The interval changes without a settings save when a trial starts or ends,
	 * a license is activated or expires, or the free build replaces the Pro one
	 * (whose frequent schedules then no longer exist). A stopped cron (no event)
	 * is left stopped.
	 *
	 * @return void
	 */
	public static function sync_schedule() {
		$current = wp_get_schedule( self::CRON_HOOK );
		if ( false === $current ) {
			return;
		}

		$interval = function_exists( 'f2000cs_get_effective_update_interval' )
			? f2000cs_get_effective_update_interval()
			: 'daily';

		if ( $current !== $interval ) {
			// Keep the next run where it was: a changed interval (e.g. a free build
			// replacing Pro) must not trigger an immediate full sync.
			$next = (int) wp_next_scheduled( self::CRON_HOOK );

			self::deactivate();
			self::activate( $next > time() ? $next : time() + 300 );
		}
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
					f2000cs_make_stock_updater( $index, $xml_url )->update_products_stock_status();
				} catch ( Exception $e ) {
					$msg = sprintf(
						'Stock update failed for supplier #%d (%s): %s',
						(int) $index,
						f2000cs_telegram_feed_label( $xml_url ),
						$e->getMessage()
					);
					if ( function_exists( 'f2000cs_log' ) ) {
						f2000cs_log( $msg, 'error' );
					}
					f2000cs_notify_supplier_failure( (int) $index, (string) $xml_url, $e->getMessage() );
				}
			}

			f2000cs_after_stock_update_complete();

			f2000cs_cleanup_wc_transients();
		} else { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElse -- No XML URLs configured - silent.
			// No XML URLs configured - silent
		}
	}
}

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
