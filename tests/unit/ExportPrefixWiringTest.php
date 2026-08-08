<?php
/**
 * Export prefix wiring tests.
 *
 * Guards the slot-1 SKU prefix key (`f2000cs_sku_prefix_1`) used by the export
 * page prefill, Settings API registration and stock-update admin paths.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Export prefix wiring tests.
 */
final class F2000CS_Unit_ExportPrefixWiringTest extends F2000CS_Unit_TestCase {

	/**
	 * Absolute path to the admin/ directory.
	 *
	 * @var string
	 */
	private $admin_dir;

	/**
	 * Load export / settings admin modules.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->admin_dir = dirname( __DIR__, 2 ) . '/admin';

		require_once $this->admin_dir . '/admin-menu.php';
		require_once $this->admin_dir . '/settings-fields.php';
		require_once $this->admin_dir . '/page-export.php';
		require_once $this->admin_dir . '/page-update.php';
	}

	/**
	 * Export page HTML must pre-fill the prefix input from f2000cs_sku_prefix_1.
	 *
	 * @return void
	 */
	public function test_export_page_prefills_prefix_from_slot_one_option() {
		$this->enable_pro();
		update_option( 'f2000cs_sku_prefix_1', 'SLOT1_' );

		$html = $this->capture( 'f2000cs_export_page' );

		$this->assertStringContainsString( 'id="sku_prefix"', $html );
		$this->assertStringContainsString( 'value="SLOT1_"', $html );
		$this->assertStringNotContainsString( 'value="NEW_"', $html );
	}

	/**
	 * Settings API registers the slot-1 prefix key used by the export page.
	 *
	 * @return void
	 */
	public function test_slot_one_prefix_key_registered() {
		f2000cs_settings_init();

		$registered = F2000CS_Test_State::$registered_settings['f2000cs_settings'] ?? array();

		$this->assertContains( 'f2000cs_sku_prefix_1', $registered );
	}

	/**
	 * Dynamic supplier slots register f2000cs_sku_prefix_{n} keys.
	 *
	 * @return void
	 */
	public function test_dynamic_slot_prefix_keys_registered() {
		f2000cs_settings_init();

		$registered = F2000CS_Test_State::$registered_settings['f2000cs_settings'] ?? array();

		$this->assertContains( 'f2000cs_sku_prefix_2', $registered );
	}

	/**
	 * Update page reads slot-scoped prefix options (not the legacy bare key).
	 *
	 * @return void
	 */
	public function test_stock_update_paths_use_slot_prefix_option() {
		$source = (string) file_get_contents( $this->admin_dir . '/page-update.php' );

		$this->assertStringContainsString( "get_option( 'f2000cs_sku_prefix_' . \$index, '' )", $source );
		$this->assertStringNotContainsString( "get_option( 'f2000cs_sku_prefix',", $source );
	}
}
