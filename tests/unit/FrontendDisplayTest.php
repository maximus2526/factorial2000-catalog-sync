<?php
/**
 * Frontend_Display tests (includes/class-frontend-display.php).
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Frontend_Display;

/**
 * Frontend display tests.
 */
final class F2000CS_Unit_FrontendDisplayTest extends F2000CS_Unit_TestCase {

	/**
	 * init() must be a no-op when WooCommerce is not installed.
	 *
	 * @return void
	 */
	public function test_init_returns_without_woocommerce() {
		$this->assertFalse( class_exists( 'WooCommerce' ) );

		Frontend_Display::init();

		$this->assertArrayNotHasKey( 'wp_enqueue_scripts', F2000CS_Test_State::$hooks );
		$this->assertArrayNotHasKey( 'wp_footer', F2000CS_Test_State::$hooks );
	}

	/**
	 * Assets enqueue only for logged-in admins on product pages.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_for_admin_on_product() {
		F2000CS_Test_State::$is_product         = true;
		F2000CS_Test_State::$is_user_logged_in  = true;
		F2000CS_Test_State::$can_manage_options = true;

		Frontend_Display::enqueue_assets();

		$this->assertArrayHasKey( 'f2000cs-frontend-vendor', F2000CS_Test_State::$enqueued_styles );
		$this->assertArrayHasKey( 'f2000cs-frontend-vendor', F2000CS_Test_State::$enqueued_scripts );
		$this->assertArrayHasKey( 'f2000csVendor', F2000CS_Test_State::$localized['f2000cs-frontend-vendor'] ?? array() );
	}

	/**
	 * Assets stay quiet for anonymous visitors.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_skips_anonymous() {
		F2000CS_Test_State::$is_product        = true;
		F2000CS_Test_State::$is_user_logged_in = false;

		Frontend_Display::enqueue_assets();

		$this->assertArrayNotHasKey( 'f2000cs-frontend-vendor', F2000CS_Test_State::$enqueued_styles );
	}

	/**
	 * Simple product footer renders the vendor code panel for admins.
	 *
	 * @return void
	 */
	public function test_display_vendor_code_footer_for_simple_product() {
		F2000CS_Test_State::$is_product         = true;
		F2000CS_Test_State::$is_user_logged_in  = true;
		F2000CS_Test_State::$can_manage_options = true;
		F2000CS_Test_State::$queried_object_id  = 55;
		F2000CS_Test_State::$products[55]       = new WC_Product( 55, 'simple' );
		F2000CS_Test_State::$post_meta['55:f2000cs-updater-vendor'] = 'VENDOR-55';

		$html = $this->capture( array( Frontend_Display::class, 'display_vendor_code_footer' ) );

		$this->assertStringContainsString( 'f2000cs-vendor-code-footer--simple', $html );
		$this->assertStringContainsString( 'VENDOR-55', $html );
		$this->assertStringContainsString( 'data-product-id="55"', $html );
	}

	/**
	 * Footer stays empty when the product has no vendor meta.
	 *
	 * @return void
	 */
	public function test_display_vendor_code_footer_skips_without_meta() {
		F2000CS_Test_State::$is_product         = true;
		F2000CS_Test_State::$is_user_logged_in  = true;
		F2000CS_Test_State::$can_manage_options = true;
		F2000CS_Test_State::$queried_object_id  = 56;
		F2000CS_Test_State::$products[56]       = new WC_Product( 56, 'simple' );

		$html = $this->capture( array( Frontend_Display::class, 'display_vendor_code_footer' ) );

		$this->assertSame( '', $html );
	}

	/**
	 * Setting off disables enqueue and footer for admins.
	 *
	 * @return void
	 */
	public function test_disabled_option_skips_display() {
		update_option( Frontend_Display::OPTION_SHOW_VENDOR_CODE, '0' );

		F2000CS_Test_State::$is_product         = true;
		F2000CS_Test_State::$is_user_logged_in  = true;
		F2000CS_Test_State::$can_manage_options = true;
		F2000CS_Test_State::$queried_object_id  = 57;
		F2000CS_Test_State::$products[57]       = new WC_Product( 57, 'simple' );
		F2000CS_Test_State::$post_meta['57:f2000cs-updater-vendor'] = 'VENDOR-57';

		$this->assertFalse( Frontend_Display::is_enabled() );

		Frontend_Display::enqueue_assets();
		$html = $this->capture( array( Frontend_Display::class, 'display_vendor_code_footer' ) );

		$this->assertArrayNotHasKey( 'f2000cs-frontend-vendor', F2000CS_Test_State::$enqueued_styles );
		$this->assertSame( '', $html );
	}
}
