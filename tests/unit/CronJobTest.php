<?php
/**
 * Cron scheduling tests (includes/class-cron-job.php).
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Cron_Job;

/**
 * Cron job tests.
 */
final class F2000CS_Unit_CronJobTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset stub state before every test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * CRON_HOOK constant.
	 *
	 * @return void
	 */
	public function test_cron_hook_constant() {
		$this->assertSame( 'f2000cs_update_stock_cron', Cron_Job::CRON_HOOK );
	}

	/**
	 * Custom schedules add 5_minute and keep existing entries untouched.
	 *
	 * @return void
	 */
	public function test_add_custom_cron_schedule() {
		$schedules = Cron_Job::add_custom_cron_schedule(
			array(
				'hourly' => array(
					'interval' => 3600,
					'display'  => 'Once hourly',
				),
			)
		);

		$this->assertSame( 300, $schedules['5_minute']['interval'] );
		$this->assertSame( 600, $schedules['10_minute']['interval'] );
		$this->assertSame( 900, $schedules['15_minute']['interval'] );
		$this->assertSame( 1800, $schedules['30_minute']['interval'] );
		$this->assertSame( 3600, $schedules['hourly']['interval'], 'Existing hourly must not be replaced' );
		$this->assertArrayHasKey( 'twicedaily', $schedules );
		$this->assertArrayHasKey( 'daily', $schedules );
	}

	/**
	 * Activate schedules the cron once, repeated calls do not duplicate it.
	 *
	 * @return void
	 */
	public function test_activate_schedules_once() {
		Cron_Job::activate();
		Cron_Job::activate();

		$count = 0;
		foreach ( F2000CS_Test_State::$cron_events as $event ) {
			if ( Cron_Job::CRON_HOOK === $event['hook'] ) {
				++$count;
			}
		}

		$this->assertSame( 1, $count );
	}

	/**
	 * Activate respects the stored interval option.
	 *
	 * @return void
	 */
	public function test_activate_uses_stored_interval() {
		update_option( 'f2000cs_update_interval', 'daily' );

		Cron_Job::activate();

		$this->assertNotFalse( wp_next_scheduled( Cron_Job::CRON_HOOK ) );
	}

	/**
	 * Deactivate clears the scheduled cron.
	 *
	 * @return void
	 */
	public function test_deactivate_clears_cron() {
		Cron_Job::activate();
		Cron_Job::deactivate();

		$this->assertFalse( wp_next_scheduled( Cron_Job::CRON_HOOK ) );
	}

	/**
	 * Interval change reschedules the cron (deactivate + activate).
	 *
	 * @return void
	 */
	public function test_reschedule_on_interval_change() {
		Cron_Job::activate();
		$before = count( F2000CS_Test_State::$cron_events );

		\F2000CS\f2000cs_reschedule_cron_on_interval_change( 'hourly', 'daily' );

		$this->assertSame( $before, count( F2000CS_Test_State::$cron_events ) );
		$this->assertNotFalse( wp_next_scheduled( Cron_Job::CRON_HOOK ) );
	}

	/**
	 * Interval change without a scheduled cron does nothing.
	 *
	 * @return void
	 */
	public function test_reschedule_without_scheduled_cron() {
		\F2000CS\f2000cs_reschedule_cron_on_interval_change( 'hourly', 'daily' );

		$this->assertCount( 0, F2000CS_Test_State::$cron_events );
	}

	/**
	 * update_stock with no URLs configured runs without side effects.
	 *
	 * @return void
	 */
	public function test_update_stock_without_urls() {
		Cron_Job::update_stock();

		$this->assertFalse( get_option( 'f2000cs_last_stock_update_day', false ) );
	}

	/**
	 * update_stock processes a configured local XML file end-to-end.
	 *
	 * @return void
	 */
	public function test_update_stock_with_configured_url() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents(
			$tmp,
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" available="true"><price>100</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		update_option( 'f2000cs_url', $tmp );

		Cron_Job::update_stock();

		// Free plan records the daily run.
		$this->assertSame( gmdate( 'Y-m-d' ), get_option( 'f2000cs_last_stock_update_day' ) );

		unlink( $tmp );
	}

	/**
	 * The cron_schedules filter is registered.
	 *
	 * @return void
	 */
	public function test_cron_schedules_filter_registered() {
		$this->assertArrayHasKey( 'cron_schedules', F2000CS_Test_State::$hooks );
	}

	/**
	 * Interval option change hook is registered.
	 *
	 * @return void
	 */
	public function test_interval_change_hook_registered() {
		$this->assertArrayHasKey( 'update_option_f2000cs_update_interval', F2000CS_Test_State::$hooks );
	}
}
