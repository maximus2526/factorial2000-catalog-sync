<?php
/**
 * Last import SKU prefix memory (the XML URL is not remembered).
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
		$this->assertSame( array( 'sku_prefix' => '' ), f2000cs_get_last_import_prefs() );
	}

	/**
	 * Save and read back the prefix; an empty prefix keeps the stored one.
	 *
	 * @return void
	 */
	public function test_save_last_import_prefix(): void {
		f2000cs_save_last_import_prefs( 'CS_' );
		$this->assertSame( 'CS_', f2000cs_get_last_import_prefs()['sku_prefix'] );

		f2000cs_save_last_import_prefs( '' );
		$this->assertSame( 'CS_', f2000cs_get_last_import_prefs()['sku_prefix'] );

		f2000cs_save_last_import_prefs( 'NEW_' );
		$this->assertSame( 'NEW_', f2000cs_get_last_import_prefs()['sku_prefix'] );
	}

	/**
	 * The import request never stores the URL, and a URL saved by an older version is dropped.
	 *
	 * @return void
	 */
	public function test_url_is_not_remembered(): void {
		update_user_meta(
			1,
			F2000CS_LAST_IMPORT_META,
			array(
				'xml_url'    => 'https://example.com/old.xml',
				'sku_prefix' => 'CS_',
			)
		);

		$_POST = array(
			'import_source'  => 'url',
			'import_xml_url' => 'https://example.com/feed.xml',
			'sku_prefix'     => 'CS_',
		);
		f2000cs_remember_last_import_from_request();
		$_POST = array();

		$this->assertSame( array( 'sku_prefix' => 'CS_' ), get_user_meta( 1, F2000CS_LAST_IMPORT_META, true ) );
		$this->assertArrayNotHasKey( 'xml_url', f2000cs_get_last_import_prefs() );
	}

	/**
	 * The import page renders the URL field empty.
	 *
	 * @return void
	 */
	public function test_import_page_url_field_is_empty(): void {
		f2000cs_save_last_import_prefs( 'CS_' );

		$html = $this->capture( 'f2000cs_import_page' );

		$this->assertMatchesRegularExpression( '/id="import_xml_url"[^>]*value=""/', $html );
		$this->assertStringContainsString( 'value="CS_"', $html, 'The SKU prefix is still prefilled' );
	}

	/**
	 * Uninstall cleanup removes the user meta for every user.
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_last_import_meta(): void {
		f2000cs_save_last_import_prefs( 'CS_' );
		$this->assertSame( 'CS_', f2000cs_get_last_import_prefs()['sku_prefix'] );

		require_once dirname( __DIR__, 2 ) . '/includes/uninstall-cleanup.php';
		f2000cs_uninstall_cleanup();

		$this->assertSame( '', f2000cs_get_last_import_prefs()['sku_prefix'] );
	}
}
