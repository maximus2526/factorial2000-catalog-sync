<?php
/**
 * Admin page smoke tests.
 *
 * Loads the admin module files and calls key render / registration
 * functions inside output buffering to verify they run without fatal errors
 * and produce meaningful HTML.
 *
 * AJAX nonce/cap/Pro gates are covered by AdminSourceTest (behavioral).
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Admin smoke tests.
 */
final class F2000CS_Unit_AdminSmokeTest extends F2000CS_Unit_TestCase {

	/**
	 * Absolute path to the admin/ directory.
	 *
	 * @var string
	 */
	private $admin_dir;

	/**
	 * Load admin modules and set up WP globals for the settings API.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->admin_dir = dirname( __DIR__, 2 ) . '/admin';

		require_once $this->admin_dir . '/admin-menu.php';
		require_once $this->admin_dir . '/settings-fields.php';
		require_once $this->admin_dir . '/page-update.php';
		require_once $this->admin_dir . '/page-import.php';
		require_once $this->admin_dir . '/page-export.php';
		require_once $this->admin_dir . '/xml-editor.php';
		require_once $this->admin_dir . '/admin-assets.php';

		$GLOBALS['wp_settings_sections'] = array();
		$GLOBALS['wp_settings_fields']   = array();
	}

	/**
	 * Simulate being on a specific plugin admin page.
	 *
	 * @param string $page_slug e.g. 'f2000cs-import'.
	 * @return void
	 */
	private function set_admin_page( string $page_slug ): void {
		$_GET['page'] = $page_slug;

		$screen     = new stdClass();
		$screen->id = 'catalog-sync_page_' . $page_slug;
		F2000CS_Test_State::$current_screen = $screen;
	}

	// ---------------------------------------------------------------- export

	public function test_export_page_shows_pro_gate_on_free(): void {
		$html = $this->capture( 'f2000cs_export_page' );

		$this->assertStringContainsString( 'доступні лише у Pro версії', $html );
	}

	public function test_export_page_renders_fully_on_pro(): void {
		$this->enable_pro();

		$html = $this->capture( 'f2000cs_export_page' );

		$this->assertStringContainsString( 'Фільтр XML вигрузки', $html );
		$this->assertStringContainsString( 'create_filtered_xml', $html );
		$this->assertStringContainsString( 'Редактор вигрузок XML', $html,
			'Editor card must be embedded on the export page' );
	}

	// ---------------------------------------------------------------- import

	public function test_import_page_renders(): void {
		$html = $this->capture( 'f2000cs_import_page' );

		$this->assertStringContainsString( 'Імпорт', $html );
		$this->assertStringContainsString( 'import_xml_url', $html );
		$this->assertStringContainsString( 'import_sku_prefix', $html );
		$this->assertStringContainsString( 'start-import', $html );
		$this->assertStringContainsString( 'update-fields', $html );
		$this->assertStringContainsString( 'Зображення при імпорті', $html );
		$this->assertStringContainsString( 'f2000cs_img_png_convert', $html );
		$this->assertStringNotContainsString( 'Відкрити налаштування', $html );
	}

	// ---------------------------------------------------------------- update

	public function test_update_page_renders(): void {
		$html = $this->capture( 'f2000cs_update_page' );

		$this->assertStringContainsString( 'Оновлення', $html );
		$this->assertStringContainsString( 'Запуск оновлення', $html );
		$this->assertStringContainsString( 'run_script', $html );
	}

	// ---------------------------------------------------------------- editor

	public function test_editor_card_renders(): void {
		$html = $this->capture( 'f2000cs_xml_editor_render_card' );

		$this->assertStringContainsString( 'Редактор вигрузок XML', $html );
		$this->assertStringContainsString( 'f2000cs-xml-editor-load', $html );
		$this->assertStringContainsString( 'f2000cs-xml-editor-tree', $html );
		$this->assertStringContainsString( 'Умови', $html );
	}

	// ---------------------------------------------------------------- settings

	public function test_settings_includes_core_options(): void {
		f2000cs_settings_init();

		$registered = F2000CS_Test_State::$registered_settings['f2000cs_settings'] ?? array();

		$core = array( 'f2000cs_url', 'f2000cs_sku_prefix_1', 'f2000cs_update_interval',
			'f2000cs_show_vendor_code', 'f2000cs_telegram_token_id', 'f2000cs_telegram_user_ids' );

		foreach ( $core as $option ) {
			$this->assertContains( $option, $registered, "Core option {$option} must be registered" );
		}
	}

	public function test_settings_includes_pro_options(): void {
		f2000cs_settings_init();

		$registered = F2000CS_Test_State::$registered_settings['f2000cs_settings'] ?? array();

		$pro = array( 'f2000cs_skip_price_1', 'f2000cs_update_stock_qty_1',
			'f2000cs_hide_variable_low_instock', 'f2000cs_variable_low_instock_max' );

		foreach ( $pro as $option ) {
			$this->assertContains( $option, $registered, "Pro option {$option} must be registered" );
		}
	}

	public function test_settings_includes_image_options(): void {
		f2000cs_settings_init();

		$registered = F2000CS_Test_State::$registered_settings['f2000cs_import_images'] ?? array();

		$img = array( 'f2000cs_img_max_dimension', 'f2000cs_img_png_convert',
			'f2000cs_img_optimize', 'f2000cs_img_quality' );

		foreach ( $img as $option ) {
			$this->assertContains( $option, $registered, "Image option {$option} must be registered under import group" );
		}
	}

	// ---------------------------------------------------------------- assets

	public function test_admin_assets_base_enqueue(): void {
		$this->set_admin_page( 'f2000cs-update' );
		f2000cs_enqueue_admin_assets( '' );

		$this->assertArrayHasKey( 'f2000cs-admin-settings',
			F2000CS_Test_State::$enqueued_styles );
		$this->assertArrayHasKey( 'f2000cs-admin-settings',
			F2000CS_Test_State::$enqueued_scripts );
		$this->assertArrayHasKey( 'f2000csAdmin',
			F2000CS_Test_State::$localized['f2000cs-admin-settings'] ?? array() );
	}

	public function test_import_page_enqueues_import_js(): void {
		$this->set_admin_page( 'f2000cs-import' );
		f2000cs_enqueue_admin_assets( '' );

		$this->assertArrayHasKey( 'f2000cs-admin-import',
			F2000CS_Test_State::$enqueued_scripts );
		$this->assertArrayHasKey( 'f2000csImport',
			F2000CS_Test_State::$localized['f2000cs-admin-import'] ?? array() );
	}

	public function test_export_page_enqueues_editor_js(): void {
		$this->set_admin_page( 'f2000cs-export' );
		f2000cs_enqueue_admin_assets( '' );

		$this->assertArrayHasKey( 'f2000cs-admin-xml-editor',
			F2000CS_Test_State::$enqueued_scripts );
		$this->assertArrayHasKey( 'f2000csXmlEditor',
			F2000CS_Test_State::$localized['f2000cs-admin-xml-editor'] ?? array() );
	}

	// ---------------------------------------------------------------- resolve source

	public function test_resolve_source_empty_url_error(): void {
		$r = f2000cs_xml_editor_resolve_source( 'url', '', array() );
		$this->assertSame( '', $r['content'] );
		$this->assertNotSame( '', $r['error'] );
	}

	public function test_resolve_source_local_file(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_src_' );
		file_put_contents( $tmp, '<yml_catalog><shop><offers><offer id="1"/></offers></shop></yml_catalog>' );
		$r = f2000cs_xml_editor_resolve_source( 'url', $tmp, array() );
		$this->assertSame( '', $r['error'] );
		$this->assertStringContainsString( 'yml_catalog', $r['content'] );
		unlink( $tmp );
	}

	public function test_resolve_source_invalid_url(): void {
		$r = f2000cs_xml_editor_resolve_source( 'url', 'not-a-url', array() );
		$this->assertNotSame( '', $r['error'] );
	}

	public function test_resolve_source_file_upload(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_up_' );
		file_put_contents( $tmp, '<yml_catalog><shop><offers><offer id="1"/></offers></shop></yml_catalog>' );
		$_FILES['xml_file'] = array( 'error' => 0, 'name' => 'test.xml', 'tmp_name' => $tmp, 'size' => filesize( $tmp ) );
		$r = f2000cs_xml_editor_resolve_source( 'file', '', $_FILES['xml_file'] );
		$this->assertSame( '', $r['error'] );
		$this->assertStringContainsString( 'yml_catalog', $r['content'] );
		$this->assertSame( 'test.xml', $r['label'] );
		unlink( $tmp );
		unset( $_FILES['xml_file'] );
	}

	public function test_resolve_source_non_xml_rejected(): void {
		$_FILES['xml_file'] = array( 'error' => 0, 'name' => 'test.txt', 'tmp_name' => __FILE__, 'size' => 0 );
		$r = f2000cs_xml_editor_resolve_source( 'file', '', $_FILES['xml_file'] );
		$this->assertNotSame( '', $r['error'] );
		$this->assertStringContainsString( 'xml', strtolower( $r['error'] ) );
		unset( $_FILES['xml_file'] );
	}

	public function test_resolve_source_http_200(): void {
		F2000CS_Test_State::$http_get_responses['http://stub.test/feed.xml'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<yml_catalog><shop><offers><offer id="1"/></offers></shop></yml_catalog>',
		);
		$r = f2000cs_xml_editor_resolve_source( 'url', 'http://stub.test/feed.xml', array() );
		$this->assertSame( '', $r['error'] );
		$this->assertStringContainsString( 'yml_catalog', $r['content'] );
	}

	public function test_resolve_source_http_404(): void {
		F2000CS_Test_State::$http_get_responses['http://stub.test/404.xml'] = array(
			'response' => array( 'code' => 404 ), 'body' => '' );
		$r = f2000cs_xml_editor_resolve_source( 'url', 'http://stub.test/404.xml', array() );
		$this->assertNotSame( '', $r['error'] );
		$this->assertStringContainsString( '404', $r['error'] );
	}

	public function test_resolve_source_http_error(): void {
		F2000CS_Test_State::$http_get_responses['http://stub.test/fail.xml'] = new WP_Error( 'http_failure', 'Connection refused' );
		$r = f2000cs_xml_editor_resolve_source( 'url', 'http://stub.test/fail.xml', array() );
		$this->assertNotSame( '', $r['error'] );
		$this->assertStringContainsString( 'Connection refused', $r['error'] );
	}
}
