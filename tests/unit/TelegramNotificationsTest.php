<?php
/**
 * Telegram notifications: one short report per supplier run by default,
 * step-by-step messages only with «Розширений дебаг».
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\XML_Stock_Updater;

/**
 * Telegram notification tests.
 */
final class F2000CS_Unit_TelegramNotificationsTest extends F2000CS_Unit_TestCase {

	/**
	 * Configure a bot and a chat.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );
	}

	/**
	 * Run a stock update against feed contents.
	 *
	 * @param string $xml  Feed contents.
	 * @param array  $rows Rows the "products with this prefix" query returns.
	 * @return array<int,string> Telegram texts in order.
	 */
	private function run_feed( string $xml, array $rows = array() ): array {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, $xml );
		$GLOBALS['wpdb']->results_queue = array( $rows );

		( new XML_Stock_Updater( $tmp, 'SUP_' ) )->update_products_stock_status();
		unlink( $tmp );

		return $this->texts();
	}

	/**
	 * Texts of all Telegram messages sent so far.
	 *
	 * @return array<int,string>
	 */
	private function texts(): array {
		return array_map(
			static function ( $post ) {
				return (string) $post['args']['body']['text'];
			},
			F2000CS_Test_State::$http_posts
		);
	}

	/**
	 * A valid two-offer feed.
	 *
	 * @return string
	 */
	private function valid_feed(): string {
		return '<?xml version="1.0"?><yml_catalog><shop><offers>'
			. '<offer id="1" available="true"><price>10</price></offer>'
			. '<offer id="2" available="false"><price>20</price></offer>'
			. '</offers></shop></yml_catalog>';
	}

	public function test_debug_is_off_by_default(): void {
		$this->assertFalse( f2000cs_telegram_debug_enabled() );

		update_option( 'f2000cs_telegram_debug', '1' );
		$this->assertTrue( f2000cs_telegram_debug_enabled() );
	}

	public function test_compact_mode_sends_one_report_per_run(): void {
		$texts = $this->run_feed( $this->valid_feed() );

		$this->assertCount( 1, $texts, 'Without debug a supplier run sends exactly one message' );
		$this->assertStringContainsString( '[OK]', $texts[0] );
		$this->assertStringContainsString( 'SUP_', $texts[0] );
		$this->assertStringContainsString( 'У вигрузці: 2', $texts[0] );
		$this->assertSame( 'HTML', F2000CS_Test_State::$http_posts[0]['args']['body']['parse_mode'] );
	}

	public function test_debug_mode_adds_step_messages_and_ends_with_the_report(): void {
		update_option( 'f2000cs_telegram_debug', '1' );

		$texts = $this->run_feed( $this->valid_feed() );

		$this->assertGreaterThan( 1, count( $texts ) );
		$this->assertStringContainsString( 'Старт', $texts[0] );
		$this->assertStringContainsString( '[OK]', end( $texts ), 'The short report comes last' );
	}

	public function test_invalid_feed_report_is_an_error_with_the_off_sale_count(): void {
		update_post_meta( 11, '_stock_status', 'instock' );
		update_post_meta( 12, '_stock_status', 'instock' );

		$texts = $this->run_feed(
			'<?xml version="1.0"?><yml_catalog><shop><offers><offer id="1" avail',
			array(
				(object) array( 'id' => 11, 'type' => 'product', 'parent' => 0 ),
				(object) array( 'id' => 12, 'type' => 'product', 'parent' => 0 ),
			)
		);

		$this->assertCount( 1, $texts );
		$this->assertStringContainsString( '[ERROR]', $texts[0] );
		$this->assertStringContainsString( 'Знято з продажу: 2', $texts[0] );
	}

	public function test_unreachable_feed_report_is_a_warning(): void {
		$url = get_temp_dir() . 'f2000cs_unreachable-' . uniqid() . '.xml';
		( new XML_Stock_Updater( $url, 'SUP_' ) )->update_products_stock_status();

		$texts = $this->texts();

		$this->assertCount( 1, $texts );
		$this->assertStringContainsString( '[WARN]', $texts[0] );
		$this->assertStringContainsString( 'недоступна', $texts[0] );
	}

	public function test_feed_label_shows_only_the_supplier_host(): void {
		$this->assertSame( 'supplier.ua', f2000cs_telegram_feed_label( 'https://supplier.ua/export/feed.xml?hash_tag=SECRET' ) );
		$this->assertSame( 'feed.xml', f2000cs_telegram_feed_label( '/var/www/uploads/feed.xml' ) );
	}

	public function test_report_never_contains_the_feed_query_string(): void {
		$report = f2000cs_telegram_stock_report(
			array(
				'url'    => 'https://supplier.ua/feed.xml?hash_tag=SECRET',
				'prefix' => '<b>',
				'total'  => 5,
			)
		);

		$this->assertStringNotContainsString( 'SECRET', $report );
		$this->assertStringContainsString( 'supplier.ua', $report );
		$this->assertStringContainsString( '&lt;b&gt;', $report, 'Values are escaped for parse_mode=HTML' );
	}

	public function test_plain_messages_are_escaped_for_html(): void {
		f2000cs_send_telegram_notification( 'Ціна < 100 & знижка' );

		$this->assertSame( 'Ціна &lt; 100 &amp; знижка', $this->texts()[0] );
	}

	public function test_long_cyrillic_message_is_cut_on_characters(): void {
		f2000cs_send_telegram_notification( str_repeat( 'ж', 5000 ) );

		$text = $this->texts()[0];
		$this->assertTrue( mb_check_encoding( $text, 'UTF-8' ), 'A cut in the middle of a character is rejected by Telegram' );
		$this->assertLessThanOrEqual( 4000, mb_strlen( $text ) );
	}

	public function test_client_errors_are_not_retried(): void {
		F2000CS_Test_State::$http_post_queue = array(
			array(
				'response' => array( 'code' => 400 ),
				'body'     => '',
			),
		);

		$this->assertFalse( f2000cs_send_telegram_notification( 'x', 2 ) );
		$this->assertCount( 1, F2000CS_Test_State::$http_posts, 'A rejected message fails the same way on every retry' );
	}

	public function test_debug_option_is_registered_and_sanitized(): void {
		require_once dirname( __DIR__, 2 ) . '/admin/settings-fields.php';
		f2000cs_settings_init();

		$this->assertContains( 'f2000cs_telegram_debug', F2000CS_Test_State::$registered_settings['f2000cs_settings'] );

		$sanitize = F2000CS_Test_State::$setting_args['f2000cs_telegram_debug']['sanitize_callback'];
		$this->assertSame( '1', $sanitize( '1' ) );
		$this->assertSame( '0', $sanitize( 'yes please' ) );
		$this->assertSame( '0', $sanitize( null ) );
	}

	public function test_supplier_failure_shows_the_host_not_the_feed_url(): void {
		f2000cs_notify_supplier_failure( 2, 'https://supplier.ua/feed.xml?hash_tag=SECRET', 'timeout <30s>' );

		$text = $this->texts()[0];
		$this->assertStringContainsString( '[ERROR]', $text );
		$this->assertStringContainsString( 'supplier.ua', $text );
		$this->assertStringContainsString( 'timeout &lt;30s&gt;', $text );
		$this->assertStringNotContainsString( 'SECRET', $text );
	}

	public function test_low_instock_report_lists_products_only_in_debug(): void {
		$this->enable_pro();
		update_option( 'f2000cs_hide_variable_low_instock', '1' );
		update_option( 'f2000cs_sku_prefix_1', 'SUP_' );
		$GLOBALS['wpdb']->col_queue = array( array( 5 ), array( 6 ) );
		update_post_meta( 6, '_stock_status', 'instock' );

		f2000cs_pro_after_stock_update();

		$text = $this->texts()[0];
		$this->assertStringContainsString( 'Знято з продажу варіативних товарів: 1', $text );
		$this->assertStringNotContainsString( 'Приклади', $text );
	}
}
