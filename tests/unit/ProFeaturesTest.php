<?php
/**
 * Pro sync features moved out of the free code (includes/pro-features.php,
 * admin/pro-settings.php): price math edge cases, settings wiring through the
 * free filters, option sanitizing and guards, supplier card markup, cron
 * intervals and the variable low-instock hook.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Cron_Job;
use F2000CS\Marketplace_Registry;
use F2000CS\XML_Stock_Updater;

/**
 * Pro features tests.
 */
final class F2000CS_Unit_ProFeaturesTest extends F2000CS_Unit_TestCase {

	/**
	 * Hooks as they were before the test (restored afterwards, so a test that
	 * replaces or removes a hook cannot leak into the next one).
	 *
	 * @var array
	 */
	private $hooks_snapshot = array();

	/**
	 * Load the admin modules the card / settings tests render.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$admin = dirname( __DIR__, 2 ) . '/admin';
		require_once $admin . '/settings-fields.php';
		require_once $admin . '/pro-settings.php';
		require_once $admin . '/page-import.php';
		require_once $admin . '/images-settings.php';

		$this->hooks_snapshot = F2000CS_Test_State::$hooks;
	}

	/**
	 * Restore the hooks.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		F2000CS_Test_State::$hooks = $this->hooks_snapshot;

		parent::tearDown();
	}

	/**
	 * Price after the rule.
	 *
	 * @param mixed  $price     Price.
	 * @param string $type      Type.
	 * @param mixed  $value     Value.
	 * @param string $direction Direction.
	 * @return float
	 */
	private function adjust( $price, string $type, $value, string $direction = 'add' ) {
		return f2000cs_adjust_price(
			$price,
			array(
				'type'      => $type,
				'direction' => $direction,
				'value'     => $value,
			)
		);
	}

	// ---------------------------------------------------------------- price math

	/**
	 * Markup boundaries: rounding, -100 % and beyond never go below zero.
	 *
	 * @return void
	 */
	public function test_markup_boundaries() {
		$this->assertSame( 109.99, $this->adjust( 99.99, 'markup', 10 ) );
		$this->assertSame( 0.0, $this->adjust( 100, 'markup', 100, 'subtract' ) );
		$this->assertSame( 0.0, $this->adjust( 100, 'markup', 150, 'subtract' ), 'More than -100 % must clamp to 0' );
		$this->assertSame( 0.01, $this->adjust( 0.01, 'markup', 0.001 ) );
		$this->assertSame( 1100000000.0, $this->adjust( 1000000000, 'markup', 10 ) );
	}

	/**
	 * Margin always raises the price, ignores the direction and is capped at 99.99 %.
	 *
	 * @return void
	 */
	public function test_margin_boundaries() {
		$this->assertSame( 200.0, $this->adjust( 100, 'margin', 50 ) );
		$this->assertSame( 200.0, $this->adjust( 100, 'margin', 50, 'subtract' ), 'Direction does not apply to margin' );
		$this->assertSame( 1000000.0, $this->adjust( 100, 'margin', 99.99 ) );
		$this->assertSame( 1000000.0, $this->adjust( 100, 'margin', 100 ), '100 % margin must not divide by zero' );
		$this->assertSame( 1000000.0, $this->adjust( 100, 'margin', 500 ) );
	}

	/**
	 * Fixed amount: subtracting more than the price clamps to zero.
	 *
	 * @return void
	 */
	public function test_fixed_boundaries() {
		$this->assertSame( 150.0, $this->adjust( 100, 'fixed', 50 ) );
		$this->assertSame( 0.0, $this->adjust( 100, 'fixed', 100, 'subtract' ) );
		$this->assertSame( 0.0, $this->adjust( 100, 'fixed', 1000, 'subtract' ) );
		$this->assertSame( 10.0, $this->adjust( 10, 'fixed', 0.004 ) );
	}

	/**
	 * No-op cases leave the price exactly as it was.
	 *
	 * @return void
	 */
	public function test_noop_cases() {
		$this->assertSame( 10.123, $this->adjust( 10.123, 'markup', 0 ), 'Zero rule must not even round' );
		$this->assertSame( 10.123, $this->adjust( 10.123, 'markup', -5 ), 'Negative rule value is ignored' );
		$this->assertSame( 0.0, $this->adjust( 0, 'markup', 10 ) );
		$this->assertSame( -5.0, $this->adjust( -5, 'fixed', 10 ) );
		$this->assertSame( 10.0, f2000cs_adjust_price( 10, array() ), 'Empty rule' );
		$this->assertSame( 10.0, f2000cs_adjust_price( 10, array( 'type' => 'fixed' ) ), 'Rule without value' );
	}

	/**
	 * Unknown type falls back to markup; numeric strings work.
	 *
	 * @return void
	 */
	public function test_type_fallback_and_string_input() {
		$this->assertSame( 110.0, $this->adjust( 100, 'bogus', 10 ) );
		$this->assertSame( 110.0, $this->adjust( '100', 'markup', '10' ) );
		$this->assertSame( 110.0, f2000cs_adjust_price( 100, array( 'value' => 10 ) ), 'Missing type = markup' );
	}

	// ---------------------------------------------------------------- price rules (free)

	/**
	 * Saved rules are read on every plan.
	 *
	 * @return void
	 */
	public function test_price_adjust_settings_are_read_without_pro() {
		update_option( 'f2000cs_price_adjust_type_2', 'fixed' );
		update_option( 'f2000cs_price_adjust_direction_2', 'subtract' );
		update_option( 'f2000cs_price_adjust_value_2', '12.5' );

		$rule = f2000cs_get_price_adjust_settings( '2abc' );
		$this->assertSame( 'fixed', $rule['type'] );
		$this->assertSame( 'subtract', $rule['direction'] );
		$this->assertSame( 12.5, $rule['value'] );

		$this->assertSame( 0.0, f2000cs_get_price_adjust_settings( 3 )['value'], 'Slot without rules' );
	}

	/**
	 * End to end on the free plan: options → updater for the slot → price.
	 *
	 * @return void
	 */
	public function test_updater_applies_saved_rule_without_pro() {
		update_option( 'f2000cs_price_adjust_type_1', 'markup' );
		update_option( 'f2000cs_price_adjust_value_1', '20' );

		$updater = f2000cs_make_stock_updater( 1, 'x.xml' );
		$this->assertFalse( F2000CS_Test_Reflection::get( $updater, 'skip_price_updates' ), 'Prices follow the supplier' );
		$this->assertSame( 120.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 100.0 ) ) );
		$this->assertSame( 0.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 0.0 ) ), 'Missing old price stays 0' );
	}

	/**
	 * Quantity flag accepts only the values the settings form stores.
	 *
	 * @return void
	 */
	public function test_supplier_quantity_flag_values() {
		update_option( 'f2000cs_update_stock_qty_1', '1' );
		$this->assertFalse( f2000cs_supplier_updates_stock_qty( 1 ), 'Free: always off' );

		$this->enable_pro();
		foreach ( array( '1', 'yes', 'on' ) as $on ) {
			update_option( 'f2000cs_update_stock_qty_1', $on );
			$this->assertTrue( f2000cs_supplier_updates_stock_qty( 1 ), $on );
		}
		foreach ( array( '0', '', 'no', 'true', 'off' ) as $off ) {
			update_option( 'f2000cs_update_stock_qty_1', $off );
			$this->assertFalse( f2000cs_supplier_updates_stock_qty( 1 ), "'{$off}'" );
		}
		$this->assertFalse( f2000cs_supplier_updates_stock_qty( 0 ), 'Slot 0 does not exist' );
	}

	/**
	 * Import quantity option is allowed only with Pro.
	 *
	 * @return void
	 */
	public function test_import_quantity_allowed_only_with_pro() {
		$this->assertFalse( apply_filters( 'f2000cs_import_stock_qty_allowed', false ) );
		$this->enable_pro();
		$this->assertTrue( apply_filters( 'f2000cs_import_stock_qty_allowed', false ) );
	}

	// ---------------------------------------------------------------- intervals

	/**
	 * Free is always daily; Pro takes valid saved values and falls back to hourly.
	 *
	 * @return void
	 */
	public function test_effective_interval() {
		update_option( 'f2000cs_update_interval', '5_minute' );
		$this->assertSame( 'daily', f2000cs_get_effective_update_interval() );

		$this->enable_pro();
		foreach ( f2000cs_pro_update_intervals() as $interval ) {
			$this->assertSame( $interval, f2000cs_get_effective_update_interval( $interval ) );
		}
		foreach ( array( '', 'every_second', '1_minute', 'DAILY', 'weekly' ) as $bad ) {
			$this->assertSame( 'hourly', f2000cs_get_effective_update_interval( $bad ), "'{$bad}'" );
		}

		delete_option( 'f2000cs_update_interval' );
		$this->assertSame( 'hourly', f2000cs_get_effective_update_interval(), 'Default without a saved value' );
	}

	/**
	 * Every selectable interval (stock sync and marketplaces) has a cron schedule;
	 * a missing one would silently stop the cron after its first run.
	 *
	 * @return void
	 */
	public function test_every_selectable_interval_has_a_schedule() {
		$schedules = f2000cs_pro_cron_schedules( array() );

		foreach ( f2000cs_pro_update_intervals() as $interval ) {
			$this->assertArrayHasKey( $interval, $schedules, $interval );
			$this->assertGreaterThan( 0, $schedules[ $interval ]['interval'] );
		}

		foreach ( Marketplace_Registry::schedules() as $interval ) {
			if ( 'off' !== $interval ) {
				$this->assertArrayHasKey( $interval, $schedules, 'Marketplace schedule ' . $interval );
			}
		}

		$intervals = array_map(
			static function ( $s ) {
				return $s['interval'];
			},
			$schedules
		);
		$this->assertSame( 300, min( $intervals ) );
		$this->assertSame( 86400, max( $intervals ) );
	}

	/**
	 * sync_schedule leaves a correct event untouched (no early extra run).
	 *
	 * @return void
	 */
	public function test_sync_schedule_is_noop_when_interval_matches() {
		$time = time() + 5000;
		wp_schedule_event( $time, 'daily', Cron_Job::CRON_HOOK );

		Cron_Job::sync_schedule();
		Cron_Job::sync_schedule();

		$this->assertSame( $time, wp_next_scheduled( Cron_Job::CRON_HOOK ) );
		$this->assertCount( 1, F2000CS_Test_State::$cron_events );
	}

	/**
	 * Losing Pro (trial ended) moves a 5-minute cron back to daily, exactly once.
	 *
	 * @return void
	 */
	public function test_sync_schedule_after_trial_ends() {
		$this->enable_pro();
		update_option( 'f2000cs_update_interval', '5_minute' );
		Cron_Job::activate();
		$this->assertSame( '5_minute', wp_get_schedule( Cron_Job::CRON_HOOK ) );

		unset( F2000CS_Test_State::$hooks['f2000cs_is_pro'] );
		Cron_Job::sync_schedule();

		$this->assertSame( 'daily', wp_get_schedule( Cron_Job::CRON_HOOK ) );
		$this->assertCount( 1, F2000CS_Test_State::$cron_events, 'No duplicate events' );
	}

	/**
	 * Only «do not update prices» stops price updates; a saved rule never does.
	 *
	 * @return void
	 */
	public function test_skip_price_only_by_the_flag() {
		update_option( 'f2000cs_price_adjust_value_1', '20' );
		$this->assertFalse( f2000cs_supplier_skip_price( 1 ) );

		update_option( 'f2000cs_skip_price_1', 'on' );
		$this->assertTrue( f2000cs_supplier_skip_price( 1 ) );
		$this->assertFalse( f2000cs_supplier_skip_price( 2 ), 'Other slots are unaffected' );
	}

	/**
	 * A slot that saved «update quantity» keeps managed-stock statuses while quantities are not updated.
	 *
	 * @return void
	 */
	public function test_saved_quantity_without_pro_freezes_managed_status() {
		$this->assertFalse( f2000cs_supplier_qty_frozen( 1 ) );

		update_option( 'f2000cs_update_stock_qty_1', '1' );
		$this->assertFalse( f2000cs_supplier_qty_frozen( 1 ), 'No license or trial: behaves as the free plan always did' );

		// A license or trial is in force but the Pro build is missing (the free build was installed).
		add_filter( 'f2000cs_has_active_license', '__return_true' );
		$this->assertTrue( f2000cs_supplier_qty_frozen( 1 ) );
		$this->assertFalse( f2000cs_supplier_qty_frozen( 2 ), 'Only the slot that saved it' );

		$frozen = f2000cs_make_stock_updater( 1, 'x.xml' );
		$this->assertTrue( F2000CS_Test_Reflection::get( $frozen, 'keep_managed_status' ) );
		$this->assertFalse( F2000CS_Test_Reflection::get( f2000cs_make_stock_updater( 0, 'x.xml' ), 'keep_managed_status' ), 'A URL without a slot has no rules' );

		$this->enable_pro();
		$this->assertFalse( f2000cs_supplier_qty_frozen( 1 ), 'With Pro the quantity is updated' );

		$updating = f2000cs_make_stock_updater( 1, 'x.xml' );
		$this->assertTrue( F2000CS_Test_Reflection::get( $updating, 'update_stock_qty' ) );
		$this->assertFalse( F2000CS_Test_Reflection::get( $updating, 'keep_managed_status' ), 'Updating quantity wins' );

		unset( F2000CS_Test_State::$hooks['f2000cs_has_active_license'] );
	}

	/**
	 * Saved Pro settings are detected so the admin notice can warn about them.
	 *
	 * @return void
	 */
	public function test_saved_pro_config_detection() {
		$this->assertFalse( f2000cs_has_saved_pro_config() );

		update_option( 'f2000cs_update_stock_qty_1', '1' );
		$this->assertTrue( f2000cs_has_saved_pro_config() );
		$this->assertTrue( f2000cs_supplier_has_saved_stock_qty( 1 ) );
	}

	/**
	 * Without Pro, saving the form does not overwrite the stored interval with «daily».
	 *
	 * @return void
	 */
	public function test_interval_survives_a_save_without_pro() {
		update_option( 'f2000cs_update_interval', '10_minute' );
		f2000cs_settings_init();

		$sanitize = F2000CS_Test_State::$setting_args['f2000cs_update_interval']['sanitize_callback'];

		$this->assertSame( '10_minute', $sanitize( 'daily' ) );

		$this->enable_pro();
		$this->assertSame( '30_minute', $sanitize( '30_minute' ) );
	}

	/**
	 * Moving the event to another interval keeps the next run time: no immediate full sync.
	 *
	 * @return void
	 */
	public function test_sync_schedule_does_not_fire_immediately() {
		$next = time() + 1800;
		wp_schedule_event( $next, '5_minute', Cron_Job::CRON_HOOK );

		Cron_Job::sync_schedule();

		$this->assertSame( 'daily', wp_get_schedule( Cron_Job::CRON_HOOK ) );
		$this->assertSame( $next, wp_next_scheduled( Cron_Job::CRON_HOOK ) );

		// An overdue event is pushed a little into the future instead of running now.
		F2000CS_Test_State::$cron_events = array();
		wp_schedule_event( time() - 100, '5_minute', Cron_Job::CRON_HOOK );
		Cron_Job::sync_schedule();
		$this->assertGreaterThan( time() + 60, wp_next_scheduled( Cron_Job::CRON_HOOK ) );
	}

	// ---------------------------------------------------------------- settings & guards

	/**
	 * Pro options are registered for every slot, including high posted slots.
	 *
	 * @return void
	 */
	public function test_pro_settings_registered_for_posted_slots() {
		$_POST['f2000cs_price_adjust_value_12'] = '5';

		f2000cs_settings_init();
		f2000cs_register_pro_settings();
		$registered = F2000CS_Test_State::$registered_settings['f2000cs_settings'];

		foreach ( array( 1, 5, 12 ) as $slot ) {
			$this->assertContains( 'f2000cs_price_adjust_type_' . $slot, $registered );
			$this->assertContains( 'f2000cs_price_adjust_direction_' . $slot, $registered );
			$this->assertContains( 'f2000cs_price_adjust_value_' . $slot, $registered );
			$this->assertContains( 'f2000cs_update_stock_qty_' . $slot, $registered );
		}
		$this->assertNotContains( 'f2000cs_price_adjust_value_13', $registered );
	}

	/**
	 * Sanitizers clamp boundary input.
	 *
	 * @return void
	 */
	public function test_pro_settings_sanitizers() {
		f2000cs_settings_init();
		f2000cs_register_pro_settings();

		$sanitize = static function ( $option, $value ) {
			return call_user_func( F2000CS_Test_State::$setting_args[ $option ]['sanitize_callback'], $value );
		};

		$value = 'f2000cs_price_adjust_value_1';
		$this->assertSame( '0', $sanitize( $value, '-5' ) );
		$this->assertSame( '0', $sanitize( $value, 'abc' ) );
		$this->assertSame( '0', $sanitize( $value, '' ) );
		$this->assertSame( '12.34', $sanitize( $value, '12.344' ) );
		$this->assertSame( '1000000', $sanitize( $value, '1e9' ) );
		$this->assertSame( '1000000', $sanitize( $value, '1000000' ) );

		$this->assertSame( 'markup', $sanitize( 'f2000cs_price_adjust_type_1', 'bogus' ) );
		$this->assertSame( 'margin', $sanitize( 'f2000cs_price_adjust_type_1', 'margin' ) );
		$this->assertSame( 'add', $sanitize( 'f2000cs_price_adjust_direction_1', 'SUBTRACT' ) );
		$this->assertSame( 'subtract', $sanitize( 'f2000cs_price_adjust_direction_1', 'subtract' ) );

		$this->assertSame( '1', $sanitize( 'f2000cs_update_stock_qty_1', 'on' ) );
		$this->assertSame( '0', $sanitize( 'f2000cs_update_stock_qty_1', 'no' ) );
		$this->assertSame( '0', $sanitize( 'f2000cs_hide_variable_low_instock', null ) );

		$max = 'f2000cs_variable_low_instock_max';
		$this->assertSame( '0', $sanitize( $max, '-3' ), 'Negative threshold must not become positive' );
		$this->assertSame( '2', $sanitize( $max, '2.9' ) );
		$this->assertSame( '0', $sanitize( $max, 'abc' ) );
		$this->assertSame( '50', $sanitize( $max, '50' ) );
	}

	/**
	 * Pro build without a license keeps Pro options; with Pro they save; a field
	 * missing from POST (null) never wipes the stored value.
	 *
	 * @return void
	 */
	public function test_pro_option_guards() {
		$keys = array( 'f2000cs_update_stock_qty_3', 'f2000cs_hide_variable_low_instock', 'f2000cs_img_quality' );

		f2000cs_register_pro_feature_guards();
		foreach ( $keys as $key ) {
			$this->assertSame( 'old', apply_filters( 'pre_update_option_' . $key, 'new', 'old' ), "Free: {$key}" );
			$this->assertSame( 'old', apply_filters( 'pre_update_option_' . $key, null, 'old' ), "Free null: {$key}" );
		}

		foreach ( $keys as $key ) {
			unset( F2000CS_Test_State::$hooks[ 'pre_update_option_' . $key ] );
		}

		$this->enable_pro();
		f2000cs_register_pro_feature_guards();
		foreach ( $keys as $key ) {
			$this->assertSame( 'new', apply_filters( 'pre_update_option_' . $key, 'new', 'old' ), "Pro: {$key}" );
			$this->assertSame( '0', apply_filters( 'pre_update_option_' . $key, '0', 'old' ), "Pro clear: {$key}" );
			$this->assertSame( 'old', apply_filters( 'pre_update_option_' . $key, null, 'old' ), "Pro null: {$key}" );
		}
	}

	// ---------------------------------------------------------------- markup

	/**
	 * Supplier cards post the exact option names the settings and JS expect.
	 *
	 * @return void
	 */
	public function test_supplier_card_field_names() {
		$this->enable_pro();
		update_option( 'f2000cs_price_adjust_type_3', 'fixed' );
		update_option( 'f2000cs_price_adjust_direction_3', 'subtract' );
		update_option( 'f2000cs_price_adjust_value_3', '7.5' );
		update_option( 'f2000cs_update_stock_qty_3', '1' );

		$card = $this->capture(
			static function () {
				f2000cs_render_supplier_card( 3 );
			}
		);

		$this->assertStringContainsString( 'name="f2000cs_url_3"', $card );
		$this->assertStringContainsString( 'name="f2000cs_price_adjust_type_3"', $card );
		$this->assertStringContainsString( 'id="f2000cs_price_adjust_3_wrap"', $card );
		$this->assertStringContainsString( 'id="f2000cs_price_adjust_direction_3_wrap"', $card );
		$this->assertStringContainsString( 'id="f2000cs_price_adjust_unit_3"', $card );
		$this->assertMatchesRegularExpression( '/value="fixed"\s+selected/', $card );
		$this->assertMatchesRegularExpression( '/value="subtract"\s+checked/', $card );
		$this->assertStringContainsString( 'value="7.5"', $card );
		$this->assertMatchesRegularExpression( '/name="f2000cs_update_stock_qty_3"[^>]*value="1"\s+checked/', $card );
		$this->assertStringNotContainsString( 'disabled', $card );
		$this->assertSame( substr_count( $card, '<div' ), substr_count( $card, '</div>' ), 'Balanced markup' );

		$first = $this->capture(
			static function () {
				f2000cs_render_supplier_card( 1 );
			}
		);
		$this->assertStringContainsString( 'name="f2000cs_url"', $first, 'Slot 1 keeps the legacy URL key' );
		$this->assertStringContainsString( 'name="f2000cs_price_adjust_value_1"', $first );
		$this->assertStringContainsString( 'name="f2000cs_update_stock_qty_1"', $first );
	}

	/**
	 * The JS template keeps the __INDEX__ placeholders and defaults.
	 *
	 * @return void
	 */
	public function test_supplier_card_template() {
		$this->enable_pro();
		update_option( 'f2000cs_price_adjust_value_0', '99' );

		$tpl = $this->capture(
			static function () {
				f2000cs_render_supplier_card( 0, true );
			}
		);

		$this->assertStringContainsString( 'name="f2000cs_price_adjust_type___INDEX__"', $tpl );
		$this->assertStringContainsString( 'id="f2000cs_price_adjust___INDEX___wrap"', $tpl );
		$this->assertStringContainsString( 'name="f2000cs_update_stock_qty___INDEX__"', $tpl );
		$this->assertStringNotContainsString( 'value="99"', $tpl, 'Template must not read slot options' );
		$this->assertSame( substr_count( $tpl, '<div' ), substr_count( $tpl, '</div>' ) );
	}

	/**
	 * Without Pro the Pro fields are shown locked (Pro build, no license).
	 *
	 * @return void
	 */
	public function test_supplier_card_locked_without_pro() {
		$card = $this->capture(
			static function () {
				f2000cs_render_supplier_card( 1 );
			}
		);

		$this->assertDoesNotMatchRegularExpression( '/name="f2000cs_price_adjust_[a-z]+_1"[^>]*disabled/', $card, 'Price rules are free' );
		$this->assertMatchesRegularExpression( '/name="f2000cs_update_stock_qty_1"[^>]*disabled/', $card );
		$this->assertStringContainsString( 'f2000cs-pro-badge', $card );
	}

	/**
	 * The import page keeps balanced markup around the moved Pro panels.
	 *
	 * @return void
	 */
	public function test_import_page_markup_is_balanced() {
		foreach ( array( false, true ) as $pro ) {
			if ( $pro ) {
				$this->enable_pro();
			}

			$html = $this->capture( 'f2000cs_import_page' );

			$this->assertSame( substr_count( $html, '<div' ), substr_count( $html, '</div>' ), $pro ? 'Pro' : 'Free plan' );
			$this->assertSame( substr_count( $html, '<section' ), substr_count( $html, '</section>' ) );
			$this->assertSame( 1, substr_count( $html, 'id="f2000cs-panel-update-fields"' ) );
			$this->assertSame( 1, substr_count( $html, 'id="start-update-fields"' ) );
			$this->assertSame( 1, substr_count( $html, 'name="import_stock_qty"' ) );
		}
	}

	/**
	 * Settings register the variable low-instock fields in the schedule section.
	 *
	 * @return void
	 */
	public function test_schedule_section_fields() {
		f2000cs_settings_init();

		$ids = array_column( F2000CS_Test_State::$settings_fields['f2000cs']['f2000cs_section_schedule'], 'id' );

		$this->assertSame( 'f2000cs_update_interval', $ids[0] );
		$this->assertSame( 'f2000cs_hide_variable_low_instock', $ids[1], 'Pro fields follow the interval' );
		$this->assertSame( 'f2000cs_variable_low_instock_max', $ids[2] );
		$this->assertSame( 1, count( array_keys( $ids, 'f2000cs_hide_variable_low_instock', true ) ), 'Registered once' );
	}

	// ---------------------------------------------------------------- after stock update

	/**
	 * The low-instock rule runs only with Pro and the option on.
	 *
	 * @return void
	 */
	public function test_low_instock_rule_gates() {
		update_option( 'f2000cs_hide_variable_low_instock', '1' );
		$this->assertSame( 0, f2000cs_apply_variable_low_instock_rule()['updated'], 'Free' );

		$this->enable_pro();
		update_option( 'f2000cs_hide_variable_low_instock', '0' );
		$this->assertSame( 0, f2000cs_apply_variable_low_instock_rule()['updated'], 'Option off' );
	}

	/**
	 * Interval threshold option is read as a non-negative int.
	 *
	 * @return void
	 */
	public function test_low_instock_threshold() {
		$this->assertSame( 2, f2000cs_get_variable_low_instock_threshold(), 'Default' );
		update_option( 'f2000cs_variable_low_instock_max', '0' );
		$this->assertSame( 0, f2000cs_get_variable_low_instock_threshold() );
		update_option( 'f2000cs_variable_low_instock_max', '15' );
		$this->assertSame( 15, f2000cs_get_variable_low_instock_threshold() );
	}

	/**
	 * Stock update complete hooks are wired.
	 *
	 * @return void
	 */
	public function test_after_stock_update_hooks() {
		$callbacks = array_column( F2000CS_Test_State::$hooks['f2000cs_stock_update_complete'] ?? array(), 'callback' );

		$this->assertContains( 'f2000cs_pro_after_stock_update', $callbacks );
		$this->assertContains( 'f2000cs_cleanup_orphaned_marketplace_feeds', $callbacks );
	}

	// ---------------------------------------------------------------- export download

	/**
	 * Export file names: only plugin-generated basenames pass.
	 *
	 * @return void
	 */
	public function test_export_filename_boundaries() {
		$this->assertSame( 'editor-xml-a.xml', f2000cs_sanitize_export_filename( 'editor-xml-a.xml' ) );
		$this->assertSame( 'filtered-xml-a.xml', f2000cs_sanitize_export_filename( '/var/www/../filtered-xml-a.xml' ) );
		foreach ( array( '', 'editor-xml-.xml', 'editor-xml-a.XML', 'editor-xml-a.xml.php', 'x-editor-xml-a.xml', 'editor-xml-a b.xml', "editor-xml-a\0.xml" ) as $bad ) {
			$this->assertSame( '', f2000cs_sanitize_export_filename( $bad ), var_export( $bad, true ) );
		}
	}
}
