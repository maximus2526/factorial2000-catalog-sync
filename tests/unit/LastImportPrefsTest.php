<?php
/**
 * Last import URL / SKU prefix memory.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Last import prefs tests.
 */
final class F2000CS_Unit_LastImportPrefsTest extends F2000CS_Unit_TestCase {

	/**
	 * Load import helpers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__, 2 ) . '/admin/page-import.php';
		F2000CS_Test_State::$current_user_id = 1;
	}

	/**
	 * Empty prefs when nothing is stored.
	 *
	 * @return void
	 */
	public function test_get_last_import_prefs_empty(): void {
		$prefs = f2000cs_get_last_import_prefs();

		$this->assertSame( '', $prefs['xml_url'] );
		$this->assertSame( '', $prefs['sku_prefix'] );
	}

	/**
	 * Save and read back a valid URL and prefix.
	 *
	 * @return void
	 */
	public function test_save_last_import_prefs(): void {
		f2000cs_save_last_import_prefs( 'https://example.com/feed.xml', 'CS_' );

		$prefs = f2000cs_get_last_import_prefs();

		$this->assertSame( 'https://example.com/feed.xml', $prefs['xml_url'] );
		$this->assertSame( 'CS_', $prefs['sku_prefix'] );
	}

	/**
	 * Empty URL on a later save must not wipe a remembered feed.
	 *
	 * @return void
	 */
	public function test_save_keeps_url_when_new_url_empty(): void {
		f2000cs_save_last_import_prefs( 'https://example.com/feed.xml', 'CS_' );
		f2000cs_save_last_import_prefs( '', 'NEW_' );

		$prefs = f2000cs_get_last_import_prefs();

		$this->assertSame( 'https://example.com/feed.xml', $prefs['xml_url'] );
		$this->assertSame( 'NEW_', $prefs['sku_prefix'] );
	}

	/**
	 * javascript: URLs are rejected.
	 *
	 * @return void
	 */
	public function test_rejects_non_http_url(): void {
		f2000cs_save_last_import_prefs( 'javascript:alert(1)', 'CS_' );

		$prefs = f2000cs_get_last_import_prefs();

		$this->assertSame( '', $prefs['xml_url'] );
		$this->assertSame( 'CS_', $prefs['sku_prefix'] );
	}

	/**
	 * Uninstall cleanup removes the user meta for every user.
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_last_import_meta(): void {
		f2000cs_save_last_import_prefs( 'https://example.com/feed.xml', 'CS_' );
		$this->assertNotEmpty( f2000cs_get_last_import_prefs()['xml_url'] );

		require_once dirname( __DIR__, 2 ) . '/includes/uninstall-cleanup.php';
		f2000cs_uninstall_cleanup();

		$prefs = f2000cs_get_last_import_prefs();
		$this->assertSame( '', $prefs['xml_url'] );
		$this->assertSame( '', $prefs['sku_prefix'] );
	}
}
