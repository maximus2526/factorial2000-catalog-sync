<?php
/**
 * Licensing / Pro feature gate tests (includes/class-licensing.php).
 *
 * Covers Freemius credentials guard, Free vs Pro supplier limits,
 * daily update quota and option guards. Pro is unlocked via filter
 * (Freemius is not bootstrapped in the unit suite).
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Licensing tests.
 */
final class F2000CS_Unit_LicensingTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset stub state before every test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Placeholder Freemius credentials are not ready.
	 *
	 * @return void
	 */
	public function test_credentials_not_ready_with_placeholders() {
		$this->assertFalse( f2000cs_fs_credentials_ready() );
		$this->assertNull( f2000cs_fs() );
	}

	/**
	 * Without an active license or Freemius trial the plugin is Free.
	 *
	 * @return void
	 */
	public function test_free_by_default() {
		$this->assertFalse( f2000cs_is_paying() );
		$this->assertFalse( f2000cs_is_fs_trial() );
		$this->assertFalse( f2000cs_is_pro() );
	}

	/**
	 * Filter unlocks Pro when Freemius is unavailable in tests.
	 *
	 * @return void
	 */
	public function test_filter_unlocks_pro() {
		$this->enable_pro();

		$this->assertTrue( f2000cs_is_pro() );
	}

	/**
	 * Without Freemius trial site data, trial end is 0.
	 *
	 * @return void
	 */
	public function test_get_trial_ends_at() {
		$this->assertSame( 0, f2000cs_get_trial_ends_at() );
		$this->assertSame( 0, f2000cs_get_fs_trial_ends() );
	}

	/**
	 * Free plan: 1 supplier; Pro/trial: unlimited (0).
	 *
	 * @return void
	 */
	public function test_get_max_suppliers() {
		$this->assertSame( 1, f2000cs_get_max_suppliers() );

		$this->enable_pro();
		$this->assertSame( 0, f2000cs_get_max_suppliers() );
	}

	/**
	 * can_use_supplier: slot 1 always, extras need Pro or saved data.
	 *
	 * @return void
	 */
	public function test_can_use_supplier() {
		$this->assertFalse( f2000cs_can_use_supplier( 0 ) );
		$this->assertTrue( f2000cs_can_use_supplier( 1 ) );
		$this->assertFalse( f2000cs_can_use_supplier( 2 ), 'Empty extra slot is Pro-only' );

		update_option( 'f2000cs_url_2', 'http://x.test/f.xml' );
		$this->assertTrue( f2000cs_can_use_supplier( 2 ), 'Grandfathered slots stay editable on Free' );

		$this->enable_pro();
		$this->assertTrue( f2000cs_can_use_supplier( 2 ), 'Pro can use any slot' );
	}

	/**
	 * Adding new supplier slots is Pro-only.
	 *
	 * @return void
	 */
	public function test_can_add_supplier_slot() {
		$this->assertFalse( f2000cs_can_add_supplier_slot() );

		$this->enable_pro();
		$this->assertTrue( f2000cs_can_add_supplier_slot() );
	}

	/**
	 * Active supplier URLs respect the license: Free keeps grandfathered
	 * slots (any slot that already has saved data), Pro uses every configured
	 * slot. Empty slots never appear.
	 *
	 * @return void
	 */
	public function test_get_active_supplier_urls() {
		update_option( 'f2000cs_url', 'http://one.test/f.xml' );
		update_option( 'f2000cs_url_2', 'http://two.test/f.xml' );
		update_option( 'f2000cs_url_3', 'http://three.test/f.xml' );

		// Free: all configured slots are grandfathered, empty slot 4 stays out.
		$this->assertSame(
			array(
				1 => 'http://one.test/f.xml',
				2 => 'http://two.test/f.xml',
				3 => 'http://three.test/f.xml',
			),
			f2000cs_get_active_supplier_urls()
		);

		$this->enable_pro();

		$this->assertSame(
			array(
				1 => 'http://one.test/f.xml',
				2 => 'http://two.test/f.xml',
				3 => 'http://three.test/f.xml',
			),
			f2000cs_get_active_supplier_urls()
		);
	}

	/**
	 * A slot without a saved URL is never included, even on Pro.
	 *
	 * @return void
	 */
	public function test_get_active_supplier_urls_skips_empty_slots() {
		update_option( 'f2000cs_url', 'http://one.test/f.xml' );
		update_option( 'f2000cs_url_2', 'http://two.test/f.xml' );
		$this->enable_pro();

		$this->assertSame(
			array(
				1 => 'http://one.test/f.xml',
				2 => 'http://two.test/f.xml',
			),
			f2000cs_get_active_supplier_urls()
		);
	}

	/**
	 * Free plan runs three updates per day, Pro runs any time.
	 *
	 * @return void
	 */
	public function test_can_run_stock_update() {
		$this->assertTrue( f2000cs_can_run_stock_update() );

		// After 1 and 2 runs, updates are still available.
		f2000cs_record_stock_update_run();
		$this->assertFalse( f2000cs_free_update_used_today() );
		$this->assertTrue( f2000cs_can_run_stock_update() );

		f2000cs_record_stock_update_run();
		$this->assertFalse( f2000cs_free_update_used_today() );
		$this->assertTrue( f2000cs_can_run_stock_update() );

		// 3rd run exhausts the limit.
		f2000cs_record_stock_update_run();
		$this->assertTrue( f2000cs_free_update_used_today() );
		$this->assertFalse( f2000cs_can_run_stock_update() );

		$this->enable_pro();
		$this->assertTrue( f2000cs_can_run_stock_update() );
	}

	/**
	 * Free update count resets when a new day comes.
	 *
	 * @return void
	 */
	public function test_free_update_count_resets_on_new_day() {
		f2000cs_record_stock_update_run();
		f2000cs_record_stock_update_run();
		f2000cs_record_stock_update_run();

		$this->assertTrue( f2000cs_free_update_used_today() );

		// Simulate a new day.
		update_option( 'f2000cs_free_update_day', '2000-01-01' );

		$this->assertFalse( f2000cs_free_update_used_today() );
		$this->assertTrue( f2000cs_can_run_stock_update() );
	}

	/**
	 * f2000cs_free_updates_remaining returns correct counts.
	 *
	 * @return void
	 */
	public function test_free_updates_remaining_counts() {
		$this->assertSame( 3, f2000cs_free_updates_remaining() );

		f2000cs_record_stock_update_run();
		$this->assertSame( 2, f2000cs_free_updates_remaining() );

		f2000cs_record_stock_update_run();
		$this->assertSame( 1, f2000cs_free_updates_remaining() );

		f2000cs_record_stock_update_run();
		$this->assertSame( 0, f2000cs_free_updates_remaining() );
	}

	/**
	 * Free plan is forced to daily; Pro keeps allowed intervals.
	 *
	 * @return void
	 */
	public function test_get_effective_update_interval() {
		update_option( 'f2000cs_update_interval', 'hourly' );
		$this->assertSame( 'daily', f2000cs_get_effective_update_interval(), 'Free is always daily' );

		$this->enable_pro();

		$this->assertSame( 'hourly', f2000cs_get_effective_update_interval() );
		$this->assertSame( '5_minute', f2000cs_get_effective_update_interval( '5_minute' ) );
		$this->assertSame( '8_times_daily', f2000cs_get_effective_update_interval( '8_times_daily' ) );
		$this->assertSame( '6_times_daily', f2000cs_get_effective_update_interval( '6_times_daily' ) );
		$this->assertSame( '4_times_daily', f2000cs_get_effective_update_interval( '4_times_daily' ) );
		$this->assertSame( 'daily', f2000cs_get_effective_update_interval( 'daily' ) );
		$this->assertSame( 'hourly', f2000cs_get_effective_update_interval( 'bogus' ), 'Unknown intervals fall back to hourly' );
	}

	/**
	 * Guard: null (missing from POST) always preserves the old value.
	 *
	 * @return void
	 */
	public function test_guard_extra_supplier_option_null() {
		$this->assertSame( 'http://x.test/f.xml', f2000cs_guard_extra_supplier_option( null, 'http://x.test/f.xml', 2, 'url' ) );
	}

	/**
	 * Guard: submitted empty values are allowed (clear).
	 *
	 * @return void
	 */
	public function test_guard_extra_supplier_option_empty() {
		$this->assertSame( '', f2000cs_guard_extra_supplier_option( '', 'http://x.test/f.xml', 2, 'url' ) );
		$this->assertSame( '0', f2000cs_guard_extra_supplier_option( '0', '1', 2, 'skip' ) );
	}

	/**
	 * Guard: brand-new data on an empty Free slot is blocked.
	 *
	 * @return void
	 */
	public function test_guard_extra_supplier_option_blocks_free() {
		$this->assertSame(
			'',
			f2000cs_guard_extra_supplier_option( 'http://new.test/f.xml', '', 2, 'url' ),
			'New slot data must be rejected on Free'
		);
	}

	/**
	 * Guard: slots with previous data stay editable on Free.
	 *
	 * @return void
	 */
	public function test_guard_extra_supplier_option_allows_existing() {
		update_option( 'f2000cs_url_2', 'http://old.test/f.xml' );

		$this->assertSame(
			'http://new.test/f.xml',
			f2000cs_guard_extra_supplier_option( 'http://new.test/f.xml', 'http://old.test/f.xml', 2, 'url' )
		);

		// A previously non-empty field itself unlocks editing.
		$this->assertSame(
			'http://next.test/f.xml',
			f2000cs_guard_extra_supplier_option( 'http://next.test/f.xml', 'http://prev.test/f.xml', 4, 'url' )
		);
	}

	/**
	 * Pro-only option updates always keep the previous value.
	 *
	 * @return void
	 */
	public function test_block_pro_option_update() {
		$this->assertSame( 'old', f2000cs_block_pro_option_update( 'new', 'old' ) );
	}

	/**
	 * Missing POST fields are preserved.
	 *
	 * @return void
	 */
	public function test_preserve_option_if_missing_from_post() {
		$this->assertSame( 'keep', f2000cs_preserve_option_if_missing_from_post( null, 'keep' ) );
		$this->assertSame( 'new', f2000cs_preserve_option_if_missing_from_post( 'new', 'old' ) );
	}

	/**
	 * USD→UAH rate defaults to 45 and is filterable.
	 *
	 * @return void
	 */
	public function test_get_usd_uah_rate() {
		$this->assertSame( 45.0, f2000cs_get_usd_uah_rate() );

		add_filter(
			'f2000cs_usd_uah_rate',
			function ( $rate ) {
				return 50.0;
			}
		);

		$this->assertSame( 50.0, f2000cs_get_usd_uah_rate() );

		remove_filter( 'f2000cs_usd_uah_rate' );
	}

	/**
	 * Pro price formatting includes the approximate UAH value.
	 *
	 * @return void
	 */
	public function test_format_pro_price_usd() {
		$this->assertSame( '$7.99 (~360 грн)', f2000cs_format_pro_price_usd( 7.99 ) );
		$this->assertSame( '$71.88 (~3 235 грн)', f2000cs_format_pro_price_usd( 71.88 ) );
	}

	/**
	 * Plan price helpers.
	 *
	 * @return void
	 */
	public function test_plan_prices() {
		$this->assertSame( '$7.99 (~360 грн)', f2000cs_get_pro_price_monthly() );
		$this->assertSame( '$71.88 (~3 235 грн)', f2000cs_get_pro_price_yearly() );
		$this->assertStringContainsString( '(~9 900 грн)', f2000cs_get_pro_price_lifetime() );
	}

	/**
	 * Licensing hooks are registered at include time.
	 *
	 * @return void
	 */
	public function test_hooks_registered() {
		$this->assertArrayHasKey( 'plugins_loaded', F2000CS_Test_State::$hooks );
		$this->assertArrayHasKey( 'admin_body_class', F2000CS_Test_State::$hooks );
		$this->assertArrayHasKey( 'admin_init', F2000CS_Test_State::$hooks );

		$admin_init = array_column( F2000CS_Test_State::$hooks['admin_init'], 'callback' );
		$this->assertContains( 'f2000cs_enable_license_key_on_free_build', $admin_init );
	}
}
