<?php
/**
 * Admin AJAX guard tests (behavioral).
 *
 * Exercises nonce, capability and Pro gates on import AJAX handlers via the
 * offline wp_send_json_* stubs — not source-string matching.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Admin AJAX security / Pro-gate tests.
 */
final class F2000CS_Unit_AdminSourceTest extends F2000CS_Unit_TestCase {

	/**
	 * Load import admin module (registers handler functions).
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/admin/page-import.php';
	}

	/**
	 * Invoke an AJAX handler and return the intercepted JSON response.
	 *
	 * @param callable $handler Handler.
	 * @return F2000CS_JsonResponseException
	 */
	private function invoke_ajax( callable $handler ): F2000CS_JsonResponseException {
		try {
			$handler();
			$this->fail( 'Expected wp_send_json_* to abort the handler' );
		} catch ( F2000CS_JsonResponseException $response ) {
			return $response;
		}
	}

	/**
	 * Import action rejects invalid nonces.
	 *
	 * @return void
	 */
	public function test_import_action_rejects_invalid_nonce() {
		F2000CS_Test_State::$nonce_valid = false;
		$_POST['f2000cs_import_nonce']   = 'bad';

		$response = $this->invoke_ajax( 'f2000cs_handle_import_action' );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'nonce', strtolower( (string) ( $response->data['message'] ?? '' ) ) );
	}

	/**
	 * Import action rejects users without manage_options.
	 *
	 * @return void
	 */
	public function test_import_action_rejects_missing_capability() {
		$_POST['f2000cs_import_nonce']            = 'ok';
		F2000CS_Test_State::$can_manage_options = false;

		$response = $this->invoke_ajax( 'f2000cs_handle_import_action' );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'прав', (string) ( $response->data['message'] ?? '' ) );
	}

	/**
	 * Update-fields handler requires Pro even when nonce and cap pass.
	 *
	 * @return void
	 */
	public function test_update_fields_handler_requires_pro() {
		$_POST['f2000cs_import_nonce'] = 'ok';
		// Free plan: no Freemius trial / paid license in the unit suite.

		$response = $this->invoke_ajax( 'f2000cs_handle_update_fields_action' );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'Pro', (string) ( $response->data['message'] ?? '' ) );
	}

	/**
	 * XML editor AJAX requires Pro even when nonce and cap pass.
	 *
	 * @return void
	 */
	public function test_xml_editor_ajax_requires_pro() {
		require_once dirname( __DIR__, 2 ) . '/admin/xml-editor.php';

		$_POST['nonce'] = 'ok';
		$_POST['sub']   = 'load';

		$response = $this->invoke_ajax( 'f2000cs_xml_editor_ajax' );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'Pro', (string) ( $response->data['message'] ?? '' ) );
	}

	/**
	 * Analyze-groups handler rejects invalid nonces.
	 *
	 * @return void
	 */
	public function test_analyze_groups_rejects_invalid_nonce() {
		F2000CS_Test_State::$nonce_valid = false;
		$_POST['f2000cs_import_nonce']   = 'bad';

		$response = $this->invoke_ajax( 'f2000cs_handle_analyze_groups' );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'nonce', strtolower( (string) ( $response->data['message'] ?? '' ) ) );
	}

	/**
	 * Admin menu module lists the three expected page slugs (needs WooCommerce at runtime).
	 *
	 * @return void
	 */
	public function test_admin_menu_source_lists_expected_pages() {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/admin-menu.php' );

		$this->assertStringContainsString( "'f2000cs-update'", $source );
		$this->assertStringContainsString( "'f2000cs-import'", $source );
		$this->assertStringContainsString( "'f2000cs-export'", $source );
		$this->assertStringContainsString( "class_exists( 'WooCommerce' )", $source );
	}
}
