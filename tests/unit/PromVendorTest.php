<?php
/**
 * Prom vendor normalization: one brand per offer.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Prom_Feed;

final class F2000CS_Unit_PromVendorTest extends F2000CS_Unit_TestCase {

	/**
	 * Kiborg + Militex resolves to Militex.
	 *
	 * @return void
	 */
	public function test_kiborg_militex_picks_militex() {
		$this->assertSame( 'Militex', Prom_Feed::apply_vendor_for_prom( 'Kiborg, Militex', array() ) );
		$this->assertSame( 'Militex', Prom_Feed::apply_vendor_for_prom( 'Militex, Kiborg', array() ) );
	}

	/**
	 * Placeholders drop out, Kiborg stays.
	 *
	 * @return void
	 */
	public function test_placeholder_brand_is_dropped() {
		$this->assertSame( 'Kiborg', Prom_Feed::apply_vendor_for_prom( 'Без бренду, Kiborg', array() ) );
		$this->assertSame( 'Kiborg', Prom_Feed::apply_vendor_for_prom( 'Bandit, Kiborg', array() ) );
	}

	/**
	 * Single vendors are untouched.
	 *
	 * @return void
	 */
	public function test_single_vendor_is_untouched() {
		$this->assertSame( 'Армолайн', Prom_Feed::apply_vendor_for_prom( 'Армолайн', array() ) );
		$this->assertSame( 'Власне виробництво', Prom_Feed::apply_vendor_for_prom( '', array( 'default_vendor' => 'Власне виробництво' ) ) );
	}
}
