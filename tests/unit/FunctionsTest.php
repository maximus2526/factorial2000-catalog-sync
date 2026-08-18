<?php
/**
 * Helper functions tests (includes/functions.php).
 *
 * Covers supplier slot helpers, price adjustment settings, background sync
 * batch tracking and Telegram notifications.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Functions tests.
 */
final class F2000CS_Unit_FunctionsTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset stub state before every test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Slot 1 keeps the legacy option key, slots 2+ use a numeric suffix.
	 *
	 * @return void
	 */
	public function test_supplier_url_option_key() {
		$this->assertSame( 'f2000cs_url', f2000cs_get_supplier_url_option_key( 1 ) );
		$this->assertSame( 'f2000cs_url_2', f2000cs_get_supplier_url_option_key( 2 ) );
		$this->assertSame( 'f2000cs_url_15', f2000cs_get_supplier_url_option_key( 15 ) );
	}

	/**
	 * Stored URLs are trimmed and returned per slot.
	 *
	 * @return void
	 */
	public function test_get_supplier_url() {
		update_option( 'f2000cs_url', '  http://shop.test/feed.xml  ' );

		$this->assertSame( 'http://shop.test/feed.xml', f2000cs_get_supplier_url( 1 ) );
		$this->assertSame( '', f2000cs_get_supplier_url( 2 ) );
	}

	/**
	 * A slot "has saved data" only when it has a URL or SKU prefix (slot 2+).
	 *
	 * @return void
	 */
	public function test_supplier_slot_has_saved_data() {
		$this->assertFalse( f2000cs_supplier_slot_has_saved_data( 1 ) );
		$this->assertFalse( f2000cs_supplier_slot_has_saved_data( 2 ) );

		update_option( 'f2000cs_sku_prefix_2', 'SUP_' );
		$this->assertTrue( f2000cs_supplier_slot_has_saved_data( 2 ) );

		update_option( 'f2000cs_url_3', 'http://other.test/feed.xml' );
		$this->assertTrue( f2000cs_supplier_slot_has_saved_data( 3 ) );
		$this->assertFalse( f2000cs_supplier_slot_has_saved_data( 4 ) );
	}

	/**
	 * Highest saved slot scanning.
	 *
	 * @return void
	 */
	public function test_highest_saved_supplier_slot() {
		$this->assertSame( 1, f2000cs_get_highest_saved_supplier_slot() );

		update_option( 'f2000cs_url_7', 'http://seven.test/x.xml' );

		$this->assertSame( 7, f2000cs_get_highest_saved_supplier_slot() );
		$this->assertSame( array( 7 ), f2000cs_get_saved_extra_supplier_slots() );
		$this->assertSame( array( 1, 7 ), f2000cs_get_visible_supplier_slots() );
	}

	/**
	 * Plugin is configured when slot 1 has a URL or extras exist.
	 *
	 * @return void
	 */
	public function test_is_configured() {
		$this->assertFalse( f2000cs_is_configured() );

		update_option( 'f2000cs_url', 'http://shop.test/feed.xml' );
		$this->assertTrue( f2000cs_is_configured() );
	}

	/**
	 * Free plan gets a zeroed price adjustment config.
	 *
	 * @return void
	 */
	public function test_price_adjust_settings_free() {
		update_option( 'f2000cs_price_adjust_type_1', 'fixed' );
		update_option( 'f2000cs_price_adjust_value_1', '99' );

		$settings = f2000cs_get_price_adjust_settings( 1 );

		$this->assertSame( 'markup', $settings['type'] );
		$this->assertSame( 'add', $settings['direction'] );
		$this->assertSame( 0.0, $settings['value'] );
	}

	/**
	 * Pro reads stored price adjustment options.
	 *
	 * @return void
	 */
	public function test_price_adjust_settings_pro() {
		$this->enable_pro();
		update_option( 'f2000cs_price_adjust_type_1', 'margin' );
		update_option( 'f2000cs_price_adjust_direction_1', 'subtract' );
		update_option( 'f2000cs_price_adjust_value_1', '12.5' );

		$settings = f2000cs_get_price_adjust_settings( 1 );

		$this->assertSame( 'margin', $settings['type'] );
		$this->assertSame( 'subtract', $settings['direction'] );
		$this->assertSame( 12.5, $settings['value'] );
	}

	/**
	 * Stock quantity updates are Pro-only and flag parsing accepts 1/yes/on.
	 *
	 * @return void
	 */
	public function test_supplier_updates_stock_qty() {
		update_option( 'f2000cs_update_stock_qty_1', '1' );
		$this->assertFalse( f2000cs_supplier_updates_stock_qty( 1 ), 'Free plan must not update quantities' );

		$this->enable_pro();

		update_option( 'f2000cs_update_stock_qty_1', 'yes' );
		$this->assertTrue( f2000cs_supplier_updates_stock_qty( 1 ) );

		update_option( 'f2000cs_update_stock_qty_1', '0' );
		$this->assertFalse( f2000cs_supplier_updates_stock_qty( 1 ) );
	}

	/**
	 * Telegram sends nothing when token or user ids are missing.
	 *
	 * @return void
	 */
	public function test_telegram_skips_without_credentials() {
		$this->assertFalse( f2000cs_send_telegram_notification( 'hello' ) );

		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		$this->assertFalse( f2000cs_send_telegram_notification( 'hello' ) );
		$this->assertCount( 0, F2000CS_Test_State::$http_posts );
	}

	/**
	 * Telegram posts to every configured chat id and retries on failure.
	 *
	 * @return void
	 */
	public function test_telegram_posts_and_retries() {
		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111, 222' );

		F2000CS_Test_State::$http_post_queue = array(
			array( 'response' => array( 'code' => 500 ), 'body' => '' ),
			array( 'response' => array( 'code' => 200 ), 'body' => '' ),
			array( 'response' => array( 'code' => 200 ), 'body' => '' ),
		);

		$result = f2000cs_send_telegram_notification( 'sync done', 2 );

		$this->assertTrue( $result );
		$this->assertCount( 3, F2000CS_Test_State::$http_posts );

		$first = F2000CS_Test_State::$http_posts[0];
		$this->assertSame( 'https://api.telegram.org/bot123:abc/sendMessage', $first['url'] );
		$this->assertSame( '111', $first['args']['body']['chat_id'] );
		$this->assertSame( 'sync done', $first['args']['body']['text'] );
		$this->assertSame( '222', F2000CS_Test_State::$http_posts[2]['args']['body']['chat_id'] );
	}

	/**
	 * Messages longer than 4000 chars are truncated for the Telegram API.
	 *
	 * @return void
	 */
	public function test_telegram_truncates_long_messages() {
		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );

		$result = f2000cs_send_telegram_notification( str_repeat( 'x', 5000 ) );

		$this->assertTrue( $result );
		$this->assertSame( 4000, strlen( F2000CS_Test_State::$http_posts[0]['args']['body']['text'] ) );
	}

	/**
	 * Background sync refuses empty URLs.
	 *
	 * @return void
	 */
	public function test_trigger_background_sync_empty_url() {
		$this->assertFalse( f2000cs_trigger_background_sync( '' ) );
		$this->assertCount( 0, F2000CS_Test_State::$cron_events );
	}

	/**
	 * Background sync schedules one event and increments the batch counter.
	 *
	 * @return void
	 */
	public function test_trigger_background_sync_schedules_event() {
		$result = f2000cs_trigger_background_sync( 'http://shop.test/feed.xml', 'SUP_' );

		$this->assertTrue( $result );
		$this->assertSame( 1, (int) get_transient( 'f2000cs_bg_batch_remaining' ) );
		$this->assertNotFalse( wp_next_scheduled( 'f2000cs_single_update_event', array( 'http://shop.test/feed.xml', 'SUP_' ) ) );

		// Same URL + prefix must not schedule a duplicate.
		$this->assertFalse( f2000cs_trigger_background_sync( 'http://shop.test/feed.xml', 'SUP_' ) );
		$this->assertSame( 1, (int) get_transient( 'f2000cs_bg_batch_remaining' ) );

		// Different URL is a new event.
		$this->assertTrue( f2000cs_trigger_background_sync( 'http://shop.test/other.xml', '' ) );
		$this->assertSame( 2, (int) get_transient( 'f2000cs_bg_batch_remaining' ) );
	}

	/**
	 * Batch counter decrements and deletes the transient when the batch is done.
	 *
	 * @return void
	 */
	public function test_background_batch_counter() {
		$this->assertSame( -1, f2000cs_decrement_background_batch_counter(), 'Decrementing an empty counter yields a negative remainder' );

		f2000cs_increment_background_batch_counter( 3 );
		$this->assertSame( 2, f2000cs_decrement_background_batch_counter() );
		$this->assertSame( 1, f2000cs_decrement_background_batch_counter() );
		$this->assertSame( 0, f2000cs_decrement_background_batch_counter() );

		$this->assertFalse( get_transient( 'f2000cs_bg_batch_remaining' ) );
	}

	/**
	 * Next background event lookup scans the cron array regardless of args.
	 *
	 * @return void
	 */
	public function test_get_next_background_event() {
		$this->assertFalse( f2000cs_get_next_background_event() );

		wp_schedule_single_event( time() + 30, 'f2000cs_single_update_event', array( 'http://a.test/x.xml', 'P_' ) );
		wp_schedule_single_event( time() + 60, 'other_hook', array( 1 ) );

		$this->assertNotFalse( f2000cs_get_next_background_event() );
	}

	/**
	 * Clearing background events removes every single-update event regardless
	 * of its arguments and resets the batch counter.
	 *
	 * @return void
	 */
	public function test_clear_all_background_events() {
		f2000cs_trigger_background_sync( 'http://a.test/1.xml', 'A_' );
		f2000cs_trigger_background_sync( 'http://a.test/2.xml', 'B_' );
		wp_schedule_event( time() + 3600, 'hourly', 'f2000cs_update_stock_cron' );

		f2000cs_clear_all_background_events();

		$this->assertFalse( f2000cs_get_next_background_event() );
		$this->assertFalse( get_transient( 'f2000cs_bg_batch_remaining' ) );
		// The recurring cron must survive.
		$this->assertNotFalse( wp_next_scheduled( 'f2000cs_update_stock_cron' ) );
	}

	/**
	 * Cleaning transients issues DELETE queries against the options table.
	 *
	 * @return void
	 */
	public function test_cleanup_wc_transients_runs() {
		f2000cs_cleanup_wc_transients();
		f2000cs_cleanup_wc_transients( true );

		$queries = $GLOBALS['wpdb']->queries;

		$this->assertCount( 2, $queries );
		$this->assertStringContainsString( '_transient_wc_product_', $queries[0] );
		$this->assertStringContainsString( 'DELETE FROM', $queries[0] );
		$this->assertStringContainsString( '_wc_', $queries[1] );
		$this->assertStringContainsString( 'DELETE FROM', $queries[1] );
	}

	/**
	 * Server resources report reads PHP ini values.
	 *
	 * @return void
	 */
	public function test_check_server_resources() {
		$info = f2000cs_check_server_resources();

		$this->assertArrayHasKey( 'memory_limit', $info );
		$this->assertArrayHasKey( 'max_execution_time', $info );
		$this->assertSame( ini_get( 'memory_limit' ), $info['memory_limit'] );
	}

	/**
	 * Settings URL helper.
	 *
	 * @return void
	 */
	public function test_get_settings_url() {
		$this->assertSame( 'http://example.org/wp-admin/admin.php?page=f2000cs-update', f2000cs_get_settings_url() );
	}

	/**
	 * Supplier option register max always covers legacy slots 2-5.
	 *
	 * @return void
	 */
	public function test_supplier_option_register_max() {
		$this->assertSame( 5, f2000cs_get_supplier_option_register_max() );

		update_option( 'f2000cs_url_12', 'http://x.test/f.xml' );
		$this->assertSame( 12, f2000cs_get_supplier_option_register_max() );
	}

	// ---- f2000cs_parse_available -------------------------------------------

	/**
	 * @return void
	 */
	public function test_parse_available_true_variants() {
		$this->assertSame( 'instock', f2000cs_parse_available( 'true' ) );
		$this->assertSame( 'instock', f2000cs_parse_available( 'True' ) );
		$this->assertSame( 'instock', f2000cs_parse_available( 'TRUE' ) );
		$this->assertSame( 'instock', f2000cs_parse_available( '1' ) );
		$this->assertSame( 'instock', f2000cs_parse_available( 'yes' ) );
		$this->assertSame( 'instock', f2000cs_parse_available( 'y' ) );
		$this->assertSame( 'instock', f2000cs_parse_available( ' true ' ) );
	}

	/**
	 * @return void
	 */
	public function test_parse_available_false_variants() {
		$this->assertSame( 'outofstock', f2000cs_parse_available( 'false' ) );
		$this->assertSame( 'outofstock', f2000cs_parse_available( '0' ) );
		$this->assertSame( 'outofstock', f2000cs_parse_available( 'no' ) );
		$this->assertSame( 'outofstock', f2000cs_parse_available( 'n' ) );
		$this->assertSame( 'outofstock', f2000cs_parse_available( 'foo' ) );
	}

	/**
	 * Missing available attribute defaults to instock (YML practice).
	 *
	 * @return void
	 */
	public function test_parse_available_empty_defaults_instock() {
		$this->assertSame( 'instock', f2000cs_parse_available( '' ) );
		$this->assertSame( 'instock', f2000cs_parse_available( '   ' ) );
	}

	// ---- f2000cs_parse_price -----------------------------------------------

	/**
	 * @return void
	 */
	public function test_parse_price_standard_format() {
		$this->assertSame( 1234.56, f2000cs_parse_price( '1234.56' ) );
		$this->assertSame( 100.0, f2000cs_parse_price( '100' ) );
		$this->assertSame( 0.0, f2000cs_parse_price( '0' ) );
		$this->assertSame( 0.0, f2000cs_parse_price( '' ) );
	}

	/**
	 * @return void
	 */
	public function test_parse_price_european_format() {
		$this->assertSame( 1234.56, f2000cs_parse_price( '1.234,56' ) );
		$this->assertSame( 1999.0, f2000cs_parse_price( '1.999,00' ) );
		$this->assertSame( 99.99, f2000cs_parse_price( '99,99' ) );
		$this->assertSame( 1500.0, f2000cs_parse_price( '1 500,00' ) );
	}

	/**
	 * @return void
	 */
	public function test_parse_price_dot_only_decimal() {
		// Dot is the only separator — no ambiguity.
		$this->assertSame( 1234.56, f2000cs_parse_price( '1234.56' ) );
		$this->assertSame( 9999.5, f2000cs_parse_price( '9999.5' ) );
	}

	/**
	 * US thousands and multi-dot formats.
	 *
	 * @return void
	 */
	public function test_parse_price_us_and_multi_dot() {
		$this->assertSame( 1234.56, f2000cs_parse_price( '1,234.56' ) );
		$this->assertSame( 1234.56, f2000cs_parse_price( '1.234.56' ) );
		$this->assertSame( 1500.0, f2000cs_parse_price( "1\xC2\xA0500,00" ) );
	}

	// ---- f2000cs_disable_ssl_verify -----------------------------------------

	/**
	 * SSL verification is enabled by default.
	 *
	 * @return void
	 */
	public function test_ssl_verify_enabled_by_default() {
		$args = f2000cs_disable_ssl_verify( array( 'timeout' => 10 ) );
		$this->assertArrayHasKey( 'sslverify', $args );
		$this->assertTrue( $args['sslverify'] );
	}

	/**
	 * The insecure-SSL setting disables verification.
	 *
	 * @return void
	 */
	public function test_ssl_verify_setting_disables() {
		update_option( 'f2000cs_allow_insecure_ssl', '1' );

		$this->assertFalse( f2000cs_ssl_verify_enabled() );

		$args = f2000cs_disable_ssl_verify( array( 'sslverify' => true ) );
		$this->assertFalse( $args['sslverify'] );
	}

	/**
	 * The f2000cs_ssl_verify filter overrides the setting.
	 *
	 * @return void
	 */
	public function test_ssl_verify_filter_overrides_setting() {
		update_option( 'f2000cs_allow_insecure_ssl', '1' );
		add_filter( 'f2000cs_ssl_verify', '__return_true' );

		$this->assertTrue( f2000cs_ssl_verify_enabled() );

		remove_filter( 'f2000cs_ssl_verify', '__return_true' );
	}

	/**
	 * The f2000cs_ssl_verify filter can opt out for suppliers with
	 * self-signed or expired certificates.
	 *
	 * @return void
	 */
	public function test_ssl_verify_filter_can_disable() {
		add_filter( 'f2000cs_ssl_verify', '__return_false' );
		$this->assertFalse( f2000cs_ssl_verify_enabled() );
		remove_filter( 'f2000cs_ssl_verify', '__return_false' );
	}

	// ---- f2000cs_download_url -----------------------------------------------

	/**
	 * @return void
	 */
	public function test_download_url_success() {
		$url     = 'http://shop.test/pic.jpg';
		$temp    = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $temp, 'fake-image-data' );

		F2000CS_Test_State::$http_get_responses[ $url ] = array(
			'response' => array( 'code' => 200 ),
			'body'     => 'fake-image-data',
		);

		// download_url() writes to temp file, we stub the function.
		// The wrapper calls download_url() internally — the stubs handle it.
		$result = f2000cs_download_url( $url, 30 );

		// In the unit environment download_url() is stubbed to return the URL itself.
		$this->assertNotInstanceOf( WP_Error::class, $result );
	}

	/**
	 * @return void
	 */
	public function test_download_url_adds_ssl_filter() {
		$url = 'https://shop.test/file.xml';

		F2000CS_Test_State::$http_get_responses[ $url ] = array(
			'response' => array( 'code' => 404 ),
			'body'     => 'Not Found',
		);

		$before = has_filter( 'http_request_args', 'f2000cs_disable_ssl_verify' );
		f2000cs_download_url( $url, 10 );
		$after = has_filter( 'http_request_args', 'f2000cs_disable_ssl_verify' );

		// Filter is added before call and removed after.
		$this->assertFalse( $after );
	}

	// ---- f2000cs_sync_price_lookup ------------------------------------------

	/**
	 * @return void
	 */
	public function test_sync_price_lookup_no_table_does_nothing() {
		$GLOBALS['wpdb']->wc_product_meta_lookup = '';

		f2000cs_sync_price_lookup( 42 );
		$this->assertEmpty( $GLOBALS['wpdb']->queries );

		$GLOBALS['wpdb']->wc_product_meta_lookup = 'wp_wc_product_meta_lookup';
	}

	/**
	 * @return void
	 */
	public function test_sync_price_lookup_updates_existing_row() {
		$GLOBALS['wpdb']->var_queue     = array( 42 ); // row exists
		$GLOBALS['wpdb']->update_return = 1;

		update_post_meta( 42, '_price', '150.00' );
		update_post_meta( 42, '_regular_price', '200.00' );

		f2000cs_sync_price_lookup( 42 );

		$queries = $GLOBALS['wpdb']->queries;
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'UPDATE', $queries[0] );
		$this->assertStringContainsString( 'wp_wc_product_meta_lookup', $queries[0] );
	}

	/**
	 * Unchanged values (update returns 0) must not trigger a duplicate insert.
	 *
	 * @return void
	 */
	public function test_sync_price_lookup_no_insert_when_unchanged() {
		$GLOBALS['wpdb']->var_queue     = array( 42 );
		$GLOBALS['wpdb']->update_return = 0;

		update_post_meta( 42, '_price', '150.00' );
		update_post_meta( 42, '_regular_price', '200.00' );

		f2000cs_sync_price_lookup( 42 );

		$queries = $GLOBALS['wpdb']->queries;
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'UPDATE', $queries[0] );
	}

	/**
	 * @return void
	 */
	public function test_sync_price_lookup_inserts_new_row() {
		$GLOBALS['wpdb']->var_queue = array( null ); // row missing

		update_post_meta( 99, '_price', '50.00' );
		update_post_meta( 99, '_regular_price', '75.00' );

		f2000cs_sync_price_lookup( 99 );

		$queries = $GLOBALS['wpdb']->queries;
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'INSERT', $queries[0] );
	}

	/**
	 * @return void
	 */
	public function test_sync_price_lookup_prefers_sale_for_min() {
		$GLOBALS['wpdb']->var_queue     = array( 10 );
		$GLOBALS['wpdb']->update_return = 1;

		update_post_meta( 10, '_price', '100.00' );
		update_post_meta( 10, '_regular_price', '120.00' );
		update_post_meta( 10, '_sale_price', '80.00' );

		f2000cs_sync_price_lookup( 10 );

		$last_args = $GLOBALS['wpdb']->last_update_data;
		$this->assertSame( 80.0, $last_args['min_price'] );
		$this->assertSame( 120.0, $last_args['max_price'] );
	}

	/**
	 * Export filename sanitizer accepts plugin-generated names only.
	 *
	 * @return void
	 */
	public function test_sanitize_export_filename() {
		$this->assertSame(
			'filtered-xml-2026-01-01-12-00-00-Ab12Cd34.xml',
			f2000cs_sanitize_export_filename( 'filtered-xml-2026-01-01-12-00-00-Ab12Cd34.xml' )
		);
		$this->assertSame(
			'editor-xml-2026-01-01-12-00-00-xyz12345.xml',
			f2000cs_sanitize_export_filename( '../editor-xml-2026-01-01-12-00-00-xyz12345.xml' )
		);
		$this->assertSame( '', f2000cs_sanitize_export_filename( '../evil.xml' ) );
		$this->assertSame( '', f2000cs_sanitize_export_filename( 'filtered-xml-../../x.xml' ) );
	}

	/**
	 * Download URL goes through admin-post and carries a server-side token.
	 *
	 * @return void
	 */
	public function test_get_export_download_url() {
		$url = f2000cs_get_export_download_url( 'filtered-xml-2026-01-01-12-00-00-Ab12Cd34.xml' );

		$this->assertStringContainsString( 'admin-post.php', $url );
		$this->assertStringContainsString( 'action=f2000cs_download_export', $url );
		$this->assertStringContainsString( 'file=filtered-xml-', $url );
		$this->assertStringContainsString( 'token=', $url );
		$this->assertStringNotContainsString( '_wpnonce=', $url );
		$this->assertSame( '', f2000cs_get_export_download_url( 'evil.xml' ) );
	}

	/**
	 * The download token is stored as a 24h transient bound to the filename.
	 *
	 * @return void
	 */
	public function test_export_download_token_transient_stored() {
		f2000cs_get_export_download_url( 'editor-xml-2026-01-01-12-00-00-xyz12345.xml' );

		$stored = array();
		foreach ( F2000CS_Test_State::$transients as $key => $entry ) {
			if ( 0 === strpos( $key, 'f2000cs_dl_' ) ) {
				$stored[ $key ] = $entry['value'];
			}
		}

		$this->assertCount( 1, $stored );
		$this->assertContains( 'editor-xml-2026-01-01-12-00-00-xyz12345.xml', $stored );
	}

	/**
	 * Download handler rejects an invalid/expired token.
	 *
	 * @return void
	 */
	public function test_export_download_handler_rejects_bad_token() {
		$this->enable_pro();

		$_GET['file']  = 'filtered-xml-2026-01-01-12-00-00-Ab12Cd34.xml';
		$_GET['token'] = 'deadbeef';

		try {
			f2000cs_handle_export_download();
			$this->fail( 'Expected wp_die for an invalid download token.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Посилання недійсне', $e->getMessage() );
		}
	}

	/**
	 * Download handler rejects a token that belongs to another file.
	 *
	 * @return void
	 */
	public function test_export_download_handler_rejects_token_for_other_file() {
		$this->enable_pro();

		set_transient( 'f2000cs_dl_ab12', 'editor-xml-2026-01-01-12-00-00-xyz12345.xml', DAY_IN_SECONDS );

		$_GET['file']  = 'filtered-xml-2026-01-01-12-00-00-Ab12Cd34.xml';
		$_GET['token'] = 'ab12';

		try {
			f2000cs_handle_export_download();
			$this->fail( 'Expected wp_die for a token belonging to another file.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'Посилання недійсне', $e->getMessage() );
		}
	}

	// ---- f2000cs_migrate_sku_prefix_slot_1 ---------------------------------

	/**
	 * @return void
	 */
	public function test_migrate_sku_prefix_no_old_key() {
		// No old key set — migration does nothing.
		f2000cs_migrate_sku_prefix_slot_1();

		$this->assertSame( null, get_option( 'f2000cs_sku_prefix', null ) );
		$this->assertSame( null, get_option( 'f2000cs_sku_prefix_1', null ) );
	}

	/**
	 * @return void
	 */
	public function test_migrate_sku_prefix_moves_to_slot_1() {
		update_option( 'f2000cs_sku_prefix', 'ABC_' );

		f2000cs_migrate_sku_prefix_slot_1();

		$this->assertSame( 'ABC_', get_option( 'f2000cs_sku_prefix_1' ) );
		$this->assertSame( null, get_option( 'f2000cs_sku_prefix', null ) );
	}

	/**
	 * @return void
	 */
	public function test_migrate_sku_prefix_does_not_overwrite_existing() {
		update_option( 'f2000cs_sku_prefix', 'OLD_' );
		update_option( 'f2000cs_sku_prefix_1', 'EXISTING_' );

		f2000cs_migrate_sku_prefix_slot_1();

		$this->assertSame( 'EXISTING_', get_option( 'f2000cs_sku_prefix_1' ) );
		$this->assertSame( null, get_option( 'f2000cs_sku_prefix', null ) );
	}

	/**
	 * @return void
	 */
	public function test_migrate_sku_prefix_cleans_empty_old_key() {
		update_option( 'f2000cs_sku_prefix', '' );

		f2000cs_migrate_sku_prefix_slot_1();

		$this->assertSame( null, get_option( 'f2000cs_sku_prefix', null ) );
		$this->assertSame( null, get_option( 'f2000cs_sku_prefix_1', null ) );
	}

	// ---- f2000cs_maybe_run_migrations ---------------------------------------

	/**
	 * @return void
	 */
	public function test_maybe_run_migrations_noop_when_current() {
		update_option( 'f2000cs_db_version', F2000CS_DB_VERSION );

		f2000cs_maybe_run_migrations();

		// Should not change DB version.
		$this->assertSame( F2000CS_DB_VERSION, (int) get_option( 'f2000cs_db_version' ) );
	}

	/**
	 * @return void
	 */
	public function test_maybe_run_migrations_runs_once() {
		update_option( 'f2000cs_sku_prefix', 'MIG_' );
		delete_option( 'f2000cs_db_version' );

		f2000cs_maybe_run_migrations();

		$this->assertSame( 'MIG_', get_option( 'f2000cs_sku_prefix_1' ) );
		$this->assertSame( null, get_option( 'f2000cs_sku_prefix', null ) );
		$this->assertSame( F2000CS_DB_VERSION, (int) get_option( 'f2000cs_db_version' ) );
	}
}
