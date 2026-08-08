<?php
/**
 * XML_Stock_Updater tests (includes/class-stock-updater.php).
 *
 * Covers constructor normalization, price adjustments (margin/markup/fixed),
 * memory-limit parsing and the end-to-end stock update flow against a local
 * XML fixture with stubbed database lookups.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\XML_Stock_Updater;

/**
 * Stock updater tests.
 */
final class F2000CS_Unit_StockUpdaterTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset stub state before every test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Constructor normalizes options, flags and price adjustment settings.
	 *
	 * @return void
	 */
	public function test_constructor_normalization() {
		$updater = new XML_Stock_Updater(
			'http://shop.test/feed.xml',
			'SUP_',
			'yes',
			array(
				'type'      => 'bad_type',
				'direction' => 'weird',
				'value'     => -5,
			),
			'on'
		);

		$this->assertSame( 'SUP_', F2000CS_Test_Reflection::get( $updater, 'sku_prefix' ) );
		$this->assertTrue( F2000CS_Test_Reflection::get( $updater, 'skip_price_updates' ) );
		$this->assertTrue( F2000CS_Test_Reflection::get( $updater, 'update_stock_qty' ) );
		$this->assertSame( 'markup', F2000CS_Test_Reflection::get( $updater, 'price_adjust_type' ) );
		$this->assertSame( 'add', F2000CS_Test_Reflection::get( $updater, 'price_adjust_direction' ) );
		$this->assertEquals( 0.0, F2000CS_Test_Reflection::get( $updater, 'price_adjust_value' ), 'Negative adjustment values must be clamped to 0' );
	}

	/**
	 * Markup adjustment adds or subtracts a percentage.
	 *
	 * @return void
	 */
	public function test_price_adjustment_markup() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'markup', 'value' => 10 ) );
		$apply   = function ( $price ) use ( $updater ) {
			return F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( $price ) );
		};

		$this->assertSame( 110.0, $apply( 100 ) );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'markup', 'direction' => 'subtract', 'value' => 10 ) );
		$this->assertSame( 90.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 100 ) ) );
	}

	/**
	 * Margin is profit relative to the final price: cost / (1 - margin%).
	 *
	 * @return void
	 */
	public function test_price_adjustment_margin() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'margin', 'value' => 20 ) );

		$this->assertSame( 100.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 80 ) ) );
	}

	/**
	 * Margin is clamped to 99.99% (never a division by zero / negative result).
	 *
	 * @return void
	 */
	public function test_price_adjustment_margin_clamped() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'margin', 'value' => 150 ) );

		$result = F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 80 ) );

		$this->assertGreaterThan( 700000, $result );
	}

	/**
	 * Fixed adjustment adds or subtracts a flat currency amount.
	 *
	 * @return void
	 */
	public function test_price_adjustment_fixed() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'fixed', 'value' => 5 ) );
		$this->assertSame( 105.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 100 ) ) );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 5 ) );
		$this->assertSame( 95.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 100 ) ) );
	}

	/**
	 * Zero/negative prices and zero adjustment values are returned unchanged.
	 *
	 * @return void
	 */
	public function test_price_adjustment_noop() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'markup', 'value' => 10 ) );

		$this->assertSame( 0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 0 ) ) );
		$this->assertSame( -5, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( -5 ) ) );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'markup', 'value' => 0 ) );
		$this->assertSame( 100, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 100 ) ) );
	}

	/**
	 * Results are rounded to 2 decimals and never negative.
	 *
	 * @return void
	 */
	public function test_price_adjustment_rounding() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 200 ) );

		$this->assertSame( 0.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 100 ) ) );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', '', false, array( 'type' => 'markup', 'value' => 33 ) );
		$this->assertSame( 133.0, F2000CS_Test_Reflection::invoke( $updater, 'apply_price_adjustment', array( 100 ) ) );
	}

	/**
	 * Memory limit strings parse to bytes (M, G units).
	 *
	 * @return void
	 */
	public function test_memory_limit_parsing() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml' );

		$previous = ini_get( 'memory_limit' );

		ini_set( 'memory_limit', '128M' );
		$this->assertSame( 128 * 1024 * 1024, F2000CS_Test_Reflection::invoke( $updater, 'get_memory_limit_in_bytes' ) );

		ini_set( 'memory_limit', '1G' );
		$this->assertSame( 1024 * 1024 * 1024, F2000CS_Test_Reflection::invoke( $updater, 'get_memory_limit_in_bytes' ) );

		ini_set( 'memory_limit', '512M' );
		$this->assertSame( 512 * 1024 * 1024, F2000CS_Test_Reflection::invoke( $updater, 'get_memory_limit_in_bytes' ) );

		ini_set( 'memory_limit', (string) $previous );
	}

	/**
	 * End-to-end flow: two offers from a local XML file are parsed and
	 * reported as not found (no matching SKUs in the database).
	 *
	 * @return void
	 */
	public function test_update_products_stock_status_flow() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents(
			$tmp,
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="101" available="true" group_id="10"><price>100</price><oldprice>120</oldprice><vendorCode>V1</vendorCode></offer>' .
			'<offer id="102" available="false" group_id="10"><price>90</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );

		$updater = new XML_Stock_Updater(
			$tmp,
			'',
			false,
			array( 'type' => 'markup', 'value' => 10 )
		);

		$updater->update_products_stock_status();

		$texts = array();
		foreach ( F2000CS_Test_State::$http_posts as $post ) {
			$texts[] = $post['args']['body']['text'];
		}
		$all = implode( "\n", $texts );

		$this->assertStringContainsString( 'Found 2 products to process', $all );
		$this->assertStringContainsString( 'Результати оновлення товарів', $all );
		$this->assertStringContainsString( 'Не знайдено товарів: 2', $all );

		unlink( $tmp );
	}

	/**
	 * An XML file without offers produces the "no data" message.
	 *
	 * @return void
	 */
	public function test_update_products_stock_status_empty_xml() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, '<?xml version="1.0"?><yml_catalog><shop><offers></offers></shop></yml_catalog>' );

		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );

		$updater = new XML_Stock_Updater( $tmp );
		$updater->update_products_stock_status();

		$texts = array();
		foreach ( F2000CS_Test_State::$http_posts as $post ) {
			$texts[] = $post['args']['body']['text'];
		}

		$this->assertStringContainsString( 'No product data found in XML or XML could not be parsed', implode( "\n", $texts ) );
	}

	/**
	 * Stock quantity is parsed from <quantity> or <stock_quantity> when enabled.
	 *
	 * @return void
	 */
	public function test_update_parses_quantity_when_enabled() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents(
			$tmp,
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" available="true"><price>10</price><quantity>7</quantity></offer>' .
			'</offers></shop></yml_catalog>'
		);

		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );

		$updater = new XML_Stock_Updater( $tmp, '', false, array(), true );
		$updater->update_products_stock_status();

		$texts = array();
		foreach ( F2000CS_Test_State::$http_posts as $post ) {
			$texts[] = $post['args']['body']['text'];
		}
		$all = implode( "\n", $texts );

		$this->assertStringContainsString( '(кількість оновлюється)', $all, 'Start message must mention quantity updates' );
	}

	/**
	 * Missing-product detection reports SKUs present in the DB but absent
	 * from the XML feed (prefix-filtered).
	 *
	 * @return void
	 */
	public function test_missing_products_detection() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents(
			$tmp,
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="AAA" available="true"><price>10</price></offer>' .
			'<offer id="BBB" available="true"><price>20</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );

		$GLOBALS['wpdb']->col_queue = array(
			array( 'SUP_ZZZ' ), // db_skus LIKE SUP_%
			array(),             // missing variation ids
		);
		$GLOBALS['wpdb']->results_queue = array( array() ); // missing product details

		$updater = new XML_Stock_Updater( $tmp, 'SUP_' );
		$updater->update_products_stock_status();

		$texts = array();
		foreach ( F2000CS_Test_State::$http_posts as $post ) {
			$texts[] = $post['args']['body']['text'];
		}
		$all = implode( "\n", $texts );

		$this->assertStringContainsString( 'Товари, яких немає у вигрузці XML', $all );
		$this->assertStringContainsString( 'Всього відсутніх товарів: 1', $all );

		unlink( $tmp );
	}
}
