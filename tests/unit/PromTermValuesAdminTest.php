<?php
/**
 * Prom term-values admin handler: nonce, capability and saving.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Prom_Term_Values_Admin;

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Minimal textarea sanitizer (the offline suite has no WP core).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_textarea_field( $text ) {
		return trim( (string) $text );
	}
}

if ( ! function_exists( 'update_term_meta' ) ) {
	/**
	 * Record term meta writes for assertions.
	 *
	 * @param int    $term_id Term id.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	function update_term_meta( $term_id, $key, $value ) {
		$GLOBALS['f2000cs_test_term_meta_updates'][] = array( (int) $term_id, (string) $key, $value );

		return true;
	}
}

if ( ! function_exists( 'delete_term_meta' ) ) {
	/**
	 * Record term meta deletes for assertions.
	 *
	 * @param int    $term_id Term id.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	function delete_term_meta( $term_id, $key ) {
		$GLOBALS['f2000cs_test_term_meta_updates'][] = array( (int) $term_id, (string) $key, null );

		return true;
	}
}

/**
 * Admin handler tests.
 */
final class F2000CS_Unit_PromTermValuesAdminTest extends F2000CS_Unit_TestCase {

	/**
	 * Load the admin class and reset request globals.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/admin/class-prom-term-values-admin.php';

		$_GET                                      = array();
		$_POST                                     = array();
		$GLOBALS['f2000cs_test_term_meta_updates'] = array();
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
	 * The search returns official values of the category.
	 *
	 * @return void
	 */
	public function test_ajax_search_returns_official_values() {
		F2000CS_Test_State::$nonce_valid          = true;
		F2000CS_Test_State::$can_manage_options   = true;
		$_GET['category']                         = '30807';
		$_GET['q']                                = 'Чорний';

		$response = $this->invoke_ajax( array( Prom_Term_Values_Admin::class, 'ajax_search' ) );

		$this->assertTrue( $response->success );
		$this->assertNotEmpty( $response->data );
		$this->assertSame( 'Чорний', $response->data[0]['value'] );
	}

	/**
	 * A bad nonce stops the search.
	 *
	 * @return void
	 */
	public function test_ajax_search_rejects_bad_nonce() {
		F2000CS_Test_State::$nonce_valid = false;
		$_GET['category']                = '30807';

		$response = $this->invoke_ajax( array( Prom_Term_Values_Admin::class, 'ajax_search' ) );

		$this->assertFalse( $response->success );
	}

	/**
	 * Users without product access cannot search.
	 *
	 * @return void
	 */
	public function test_ajax_search_rejects_missing_capability() {
		F2000CS_Test_State::$nonce_valid        = true;
		F2000CS_Test_State::$can_manage_options = false;
		$_GET['category']                       = '30807';

		$response = $this->invoke_ajax( array( Prom_Term_Values_Admin::class, 'ajax_search' ) );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'Недостатньо прав', (string) ( $response->data['message'] ?? '' ) );
	}

	/**
	 * A saved map is sanitized: bad lines and empty values are dropped.
	 *
	 * @return void
	 */
	public function test_save_term_stores_a_sanitized_map() {
		F2000CS_Test_State::$nonce_valid        = true;
		F2000CS_Test_State::$can_manage_options = true;
		$_POST['f2000cs_prom_term_values_nonce'] = 'ok';
		$_POST['f2000cs_prom_value_map']         = "30807=Чорний\n777777=Black official\nbadline\n=empty\n999999=";

		Prom_Term_Values_Admin::save_term( 99, 1, 'pa_color' );

		$this->assertSame(
			array(
				array(
					99,
					'_f2000cs_prom_value_map',
					array(
						'30807'  => 'Чорний',
						'777777' => 'Black official',
					),
				),
			),
			$GLOBALS['f2000cs_test_term_meta_updates']
		);
	}

	/**
	 * Foreign taxonomies and invalid nonces never write term meta.
	 *
	 * @return void
	 */
	public function test_save_term_guards_taxonomy_and_nonce() {
		F2000CS_Test_State::$nonce_valid        = true;
		F2000CS_Test_State::$can_manage_options = true;
		$_POST['f2000cs_prom_term_values_nonce'] = 'ok';
		$_POST['f2000cs_prom_value_map']         = '30807=Чорний';

		Prom_Term_Values_Admin::save_term( 99, 1, 'product_cat' );
		$this->assertSame( array(), $GLOBALS['f2000cs_test_term_meta_updates'] );

		F2000CS_Test_State::$nonce_valid = false;
		Prom_Term_Values_Admin::save_term( 99, 1, 'pa_color' );
		$this->assertSame( array(), $GLOBALS['f2000cs_test_term_meta_updates'] );
	}
}
