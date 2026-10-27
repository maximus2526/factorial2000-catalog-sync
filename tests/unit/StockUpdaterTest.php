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

		$this->assertStringContainsString( '[OK]', $all );
		$this->assertStringContainsString( 'У вигрузці: 2', $all );
		$this->assertStringContainsString( 'Немає на сайті: 2', $all );

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

		$this->assertStringContainsString( '[ERROR]', implode( "\n", $texts ) );
		$this->assertStringContainsString( 'немає жодної пропозиції', implode( "\n", $texts ) );
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
		update_option( 'f2000cs_telegram_debug', '1' );

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
	 * Build a minimal WC_Product stand-in for republish tests.
	 *
	 * @param int    $id     Product ID.
	 * @param string $status Post status.
	 * @return object
	 */
	private function fake_product( $id, $status ) {
		return new class( $id, $status ) {
			private $id;
			private $status;

			public function __construct( $id, $status ) {
				$this->id     = $id;
				$this->status = $status;
			}

			public function get_id() {
				return $this->id;
			}

			public function get_status() {
				return $this->status;
			}
		};
	}

	/**
	 * A product the plugin drafted comes back when the feed lists it again.
	 *
	 * @return void
	 */
	public function test_republishes_product_drafted_by_the_plugin() {
		update_post_meta( 42, XML_Stock_Updater::AUTO_DRAFT_META, 1700000000 );

		$updater = new XML_Stock_Updater( 'x.xml', 'P_' );
		$result  = F2000CS_Test_Reflection::invoke( $updater, 'maybe_republish_product', array( $this->fake_product( 42, 'draft' ) ) );

		$this->assertTrue( $result );
		$this->assertSame( array( array( 'ID' => 42, 'post_status' => 'publish' ) ), F2000CS_Test_State::$updated_posts );
		$this->assertEmpty( get_post_meta( 42, XML_Stock_Updater::AUTO_DRAFT_META, true ), 'The marker must be cleared after republishing' );
	}

	/**
	 * Products an administrator drafted (no marker) are never published automatically.
	 *
	 * @return void
	 */
	public function test_does_not_republish_manually_drafted_or_published_products() {
		$updater = new XML_Stock_Updater( 'x.xml', 'P_' );

		$manual = F2000CS_Test_Reflection::invoke( $updater, 'maybe_republish_product', array( $this->fake_product( 7, 'draft' ) ) );
		update_post_meta( 8, XML_Stock_Updater::AUTO_DRAFT_META, 1700000000 );
		$live = F2000CS_Test_Reflection::invoke( $updater, 'maybe_republish_product', array( $this->fake_product( 8, 'publish' ) ) );

		$this->assertFalse( $manual );
		$this->assertFalse( $live );
		$this->assertSame( array(), F2000CS_Test_State::$updated_posts );
	}

	/**
	 * The behavior can be switched off with a filter.
	 *
	 * @return void
	 */
	public function test_republish_can_be_disabled_by_filter() {
		update_post_meta( 9, XML_Stock_Updater::AUTO_DRAFT_META, 1700000000 );
		add_filter( 'f2000cs_republish_returned_products', '__return_false' );

		$updater = new XML_Stock_Updater( 'x.xml', 'P_' );
		$result  = F2000CS_Test_Reflection::invoke( $updater, 'maybe_republish_product', array( $this->fake_product( 9, 'draft' ) ) );

		// Hooks are not reset between tests: do not leak the filter.
		remove_filter( 'f2000cs_republish_returned_products', '__return_false' );

		$this->assertFalse( $result );
		$this->assertSame( array(), F2000CS_Test_State::$updated_posts );
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

		$this->assertStringContainsString( 'Зникли з вигрузки: 1', $all, 'The short report counts products missing from the feed' );

		unlink( $tmp );
	}

	/**
	 * Run an update against feed contents and return the Telegram text.
	 *
	 * @param string $xml    Feed contents.
	 * @param string $prefix SKU prefix.
	 * @param array  $rows   Rows the "products with this prefix" query returns.
	 * @return string All Telegram messages.
	 */
	private function run_with_feed( string $xml, string $prefix, array $rows, bool $keep_managed = false ): string {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, $xml );

		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );
		$GLOBALS['wpdb']->results_queue = array( $rows );

		( new XML_Stock_Updater( $tmp, $prefix, false, array(), false, $keep_managed ) )->update_products_stock_status();
		unlink( $tmp );

		$texts = array();
		foreach ( F2000CS_Test_State::$http_posts as $post ) {
			$texts[] = $post['args']['body']['text'];
		}

		return implode( "\n", $texts );
	}

	/**
	 * Rows as the DB query returns them.
	 *
	 * @return array<int,object>
	 */
	private function supplier_rows(): array {
		return array(
			(object) array( 'id' => 11, 'type' => 'product', 'parent' => 0 ),
			(object) array( 'id' => 12, 'type' => 'product_variation', 'parent' => 11 ),
		);
	}

	/**
	 * A truncated feed takes the supplier's products out of stock.
	 *
	 * @return void
	 */
	public function test_truncated_feed_sets_everything_out_of_stock() {
		update_post_meta( 11, '_stock_status', 'instock' );
		update_post_meta( 12, '_stock_status', 'instock' );

		$all = $this->run_with_feed(
			'<?xml version="1.0"?><yml_catalog><shop><offers><offer id="1" available="true"><price>10</price></offer><offer id="2" avail',
			'SUP_',
			$this->supplier_rows()
		);

		$this->assertStringContainsString( '[ERROR]', $all );
		$this->assertSame( 'outofstock', get_post_meta( 11, '_stock_status', true ) );
		$this->assertSame( 'outofstock', get_post_meta( 12, '_stock_status', true ) );
		$this->assertStringNotContainsString( 'У вигрузці:', $all, 'No updates are applied from a broken feed' );
	}

	/**
	 * A well-formed feed without offers is invalid as well.
	 *
	 * @return void
	 */
	public function test_feed_without_offers_sets_everything_out_of_stock() {
		update_post_meta( 11, '_stock_status', 'instock' );

		$all = $this->run_with_feed( '<?xml version="1.0"?><yml_catalog><shop><offers></offers></shop></yml_catalog>', 'SUP_', $this->supplier_rows() );

		$this->assertStringContainsString( 'немає жодної пропозиції', $all );
		$this->assertSame( 'outofstock', get_post_meta( 11, '_stock_status', true ) );
	}

	/**
	 * Without a SKU prefix the whole shop would be affected: nothing is changed.
	 *
	 * @return void
	 */
	public function test_invalid_feed_without_prefix_changes_nothing() {
		update_post_meta( 11, '_stock_status', 'instock' );

		$all = $this->run_with_feed( '<yml_catalog><shop>', '', $this->supplier_rows() );

		$this->assertStringContainsString( 'SKU-префікс не задано', $all );
		$this->assertSame( 'instock', get_post_meta( 11, '_stock_status', true ) );
	}

	/**
	 * A valid feed never triggers the out-of-stock path.
	 *
	 * @return void
	 */
	public function test_valid_feed_keeps_statuses() {
		update_post_meta( 11, '_stock_status', 'instock' );

		$all = $this->run_with_feed(
			'<?xml version="1.0"?><yml_catalog><shop><offers><offer id="1" available="true"><price>10</price></offer></offers></shop></yml_catalog>',
			'SUP_',
			array()
		);

		$this->assertStringContainsString( 'У вигрузці: 1', $all );
		$this->assertStringNotContainsString( '[ERROR]', $all );
		$this->assertSame( 'instock', get_post_meta( 11, '_stock_status', true ) );
	}

	/**
	 * A WC_Product stand-in whose prices are read from post meta, like WC does.
	 *
	 * @param int $id Product ID.
	 * @return object
	 */
	private function meta_priced_product( int $id, int $parent_id = 0 ) {
		return new class( $id, $parent_id ) {
			/**
			 * Product ID.
			 *
			 * @var int
			 */
			private $id;

			/**
			 * Parent ID (a variation when not 0).
			 *
			 * @var int
			 */
			private $parent_id;

			/**
			 * @param int $id        Product ID.
			 * @param int $parent_id Parent ID.
			 */
			public function __construct( $id, $parent_id ) {
				$this->id        = $id;
				$this->parent_id = $parent_id;
			}

			/**
			 * @param string $type Type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return ( $this->parent_id ? 'variation' : 'simple' ) === $type;
			}

			/**
			 * @return int
			 */
			public function get_parent_id() {
				return $this->parent_id;
			}

			/**
			 * @return int
			 */
			public function get_id() {
				return $this->id;
			}

			/**
			 * @return string
			 */
			public function get_regular_price() {
				return (string) get_post_meta( $this->id, '_regular_price', true );
			}

			/**
			 * @return string
			 */
			public function get_sale_price() {
				return (string) get_post_meta( $this->id, '_sale_price', true );
			}
		};
	}

	/**
	 * When the supplier ends a promotion by restoring the old price, the sale ends too.
	 *
	 * @return void
	 */
	public function test_sale_ends_when_oldprice_disappears_and_price_returns_to_regular() {
		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', 'SUP_' );
		$product = $this->meta_priced_product( 21 );

		// Run 1: promotion — price 80, oldprice 100.
		F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $product, 80, 100 ) );
		$this->assertSame( '100.00', get_post_meta( 21, '_regular_price', true ) );
		$this->assertSame( '80.00', get_post_meta( 21, '_sale_price', true ) );

		// Run 2: promotion over — price back to 100, no oldprice.
		$changed = F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $product, 100, 0 ) );

		$this->assertTrue( $changed );
		$this->assertSame( '', (string) get_post_meta( 21, '_sale_price', true ), 'The sale price must be removed' );
		$this->assertSame( '100.00', get_post_meta( 21, '_price', true ), 'The active price must be the regular price again' );
		$this->assertSame( '100.00', get_post_meta( 21, '_regular_price', true ) );
	}

	/**
	 * A supplier sale with dates (left from an import or an earlier run) is ended with its dates.
	 *
	 * @return void
	 */
	public function test_sale_end_clears_past_sale_dates() {
		update_post_meta( 23, '_regular_price', '100.00' );
		update_post_meta( 23, '_sale_price', '85.00' );
		update_post_meta( 23, '_price', '85.00' );
		update_post_meta( 23, '_sale_price_dates_from', (string) ( time() - 2 * DAY_IN_SECONDS ) );
		update_post_meta( 23, '_sale_price_dates_to', (string) ( time() - DAY_IN_SECONDS ) );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', 'SUP_' );
		$changed = F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $this->meta_priced_product( 23 ), 100, 0 ) );

		$this->assertTrue( $changed );
		$this->assertSame( '', (string) get_post_meta( 23, '_sale_price', true ) );
		$this->assertSame( '', (string) get_post_meta( 23, '_sale_price_dates_from', true ) );
		$this->assertSame( '', (string) get_post_meta( 23, '_sale_price_dates_to', true ) );
		$this->assertSame( '100.00', get_post_meta( 23, '_price', true ) );
	}

	/**
	 * A sale the owner scheduled with an end date ahead stays; only the regular price follows the feed.
	 *
	 * @return void
	 */
	public function test_owner_scheduled_sale_is_kept() {
		update_post_meta( 24, '_regular_price', '100.00' );
		update_post_meta( 24, '_sale_price', '70.00' );
		update_post_meta( 24, '_price', '70.00' );
		update_post_meta( 24, '_sale_price_dates_to', (string) ( time() + DAY_IN_SECONDS ) );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', 'SUP_' );
		$changed = F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $this->meta_priced_product( 24 ), 110, 0 ) );

		$this->assertTrue( $changed );
		$this->assertSame( '70.00', get_post_meta( 24, '_sale_price', true ) );
		$this->assertSame( '70.00', get_post_meta( 24, '_price', true ) );
		$this->assertSame( '110.00', get_post_meta( 24, '_regular_price', true ) );
	}

	/**
	 * A variation whose sale ended queues its parent for a price resync.
	 *
	 * @return void
	 */
	public function test_variation_price_change_queues_parent_sync() {
		update_post_meta( 25, '_regular_price', '100.00' );
		update_post_meta( 25, '_sale_price', '60.00' );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', 'SUP_' );
		F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $this->meta_priced_product( 25, 90 ), 100, 0 ) );

		$this->assertSame( array( 90 => true ), F2000CS_Test_Reflection::get( $updater, 'price_changed_parents' ) );
	}

	/**
	 * The lookup row's «on sale» flag follows the sale price.
	 *
	 * @return void
	 */
	public function test_price_lookup_clears_onsale_flag() {
		update_post_meta( 26, '_regular_price', '100.00' );
		update_post_meta( 26, '_price', '100.00' );
		$GLOBALS['wpdb']->var_queue = array( 26 );

		f2000cs_sync_price_lookup( 26 );

		$this->assertSame( 0, $GLOBALS['wpdb']->last_update_data['onsale'] );

		update_post_meta( 26, '_sale_price', '80.00' );
		$GLOBALS['wpdb']->var_queue = array( 26 );

		f2000cs_sync_price_lookup( 26 );

		$this->assertSame( 1, $GLOBALS['wpdb']->last_update_data['onsale'] );
	}

	/**
	 * The stock lookup write is a single upsert, so an unchanged row never causes «Duplicate entry».
	 *
	 * @return void
	 */
	public function test_stock_lookup_is_upsert() {
		$GLOBALS['wpdb']->queries = array();

		f2000cs_sync_single_lookup_stock( 27, 'instock' );

		$this->assertCount( 1, $GLOBALS['wpdb']->queries );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', $GLOBALS['wpdb']->queries[0] );
	}

	/**
	 * memory_limit -1 (WP-CLI) means no limit and is never lowered.
	 *
	 * @return void
	 */
	public function test_unlimited_memory_is_not_lowered() {
		$before = ini_get( 'memory_limit' );
		ini_set( 'memory_limit', '-1' );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', 'SUP_' );
		F2000CS_Test_Reflection::invoke( $updater, 'increase_memory_limit' );
		$after = ini_get( 'memory_limit' );

		ini_set( 'memory_limit', $before );

		$this->assertSame( '-1', $after );
	}

	/**
	 * A run cut short by the time limit keeps its position instead of starting over.
	 *
	 * @return void
	 */
	public function test_interrupted_run_keeps_resume_position() {
		$url = 'http://shop.test/resume.xml';
		$key = 'f2000cs_resume_' . md5( $url );
		update_option( $key, 2 );

		$updater = new XML_Stock_Updater( $url, 'SUP_' );
		F2000CS_Test_Reflection::set( $updater, 'batch_size', 1 );
		F2000CS_Test_Reflection::set( $updater, 'max_execution_time', 1.0E-9 );

		$updates = array();
		foreach ( array( 'SUP_1', 'SUP_2', 'SUP_3', 'SUP_4' ) as $sku ) {
			$updates[ $sku ] = array( 'stock_status' => 'instock', 'price' => 10, 'old_price' => 0 );
		}

		F2000CS_Test_Reflection::invoke( $updater, 'process_updates_in_batches', array( $updates ) );
		$this->assertSame( 2, (int) get_option( $key ), 'Cut short before any batch: the next run starts from the same place' );

		F2000CS_Test_Reflection::set( $updater, 'max_execution_time', 0 );
		F2000CS_Test_Reflection::invoke( $updater, 'process_updates_in_batches', array( $updates ) );
		$this->assertFalse( get_option( $key ), 'A finished run clears the position' );
	}

	/**
	 * Without a sale and with an unchanged price nothing is written.
	 *
	 * @return void
	 */
	public function test_unchanged_regular_price_without_sale_writes_nothing() {
		update_post_meta( 22, '_regular_price', '100.00' );
		update_post_meta( 22, '_price', '100.00' );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', 'SUP_' );
		$changed = F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $this->meta_priced_product( 22 ), 100, 0 ) );

		$this->assertFalse( $changed );
	}

	/**
	 * Feeds with a DOCTYPE and a named entity (&nbsp;) in an offer are valid YML.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function entity_feeds(): array {
		$offers = '<shop><offers>'
			. '<offer id="1" available="true"><name>Куртка&nbsp;XL</name><price>10</price></offer>'
			. '<offer id="2" available="true"><price>20</price></offer>'
			. '</offers></shop></yml_catalog>';

		return array(
			'external shops.dtd' => array( '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE yml_catalog SYSTEM "shops.dtd"><yml_catalog date="2026-10-24">' . $offers ),
			'internal subset'    => array( '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE yml_catalog [<!ENTITY nbsp "&#160;">]><yml_catalog date="2026-10-24">' . $offers ),
		);
	}

	/**
	 * A named entity inside an offer must not make the whole feed "broken".
	 *
	 * @dataProvider entity_feeds
	 * @param string $xml Feed contents.
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'entity_feeds' )]
	public function test_entity_in_offer_keeps_the_feed_valid( string $xml ) {
		update_post_meta( 11, '_stock_status', 'instock' );
		update_post_meta( 12, '_stock_status', 'instock' );

		$all = $this->run_with_feed( $xml, 'SUP_', array() );

		$this->assertStringNotContainsString( '[ERROR]', $all );
		$this->assertStringContainsString( 'У вигрузці: 2', $all, 'Both offers are read, including the one with &nbsp;' );
		$this->assertSame( 'instock', get_post_meta( 11, '_stock_status', true ) );
		$this->assertSame( 'instock', get_post_meta( 12, '_stock_status', true ) );
	}

	/**
	 * Run an update against a feed URL that cannot be fetched at all.
	 *
	 * @param string $url  Unreachable feed location.
	 * @param array  $rows Rows the "products with this prefix" query returns.
	 * @return string All Telegram messages.
	 */
	private function run_unreachable( string $url, array $rows ): string {
		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );
		$GLOBALS['wpdb']->results_queue = array( $rows );

		( new XML_Stock_Updater( $url, 'SUP_' ) )->update_products_stock_status();

		$texts = array();
		foreach ( F2000CS_Test_State::$http_posts as $post ) {
			$texts[] = $post['args']['body']['text'];
		}

		return implode( "\n", $texts );
	}

	/**
	 * A feed location that neither XMLReader nor the HTTP fallback can open.
	 *
	 * @return string
	 */
	private function unreachable_url(): string {
		return get_temp_dir() . 'f2000cs_unreachable-' . uniqid() . '.xml';
	}

	/**
	 * A feed that cannot be downloaded keeps the current stock and only alerts.
	 *
	 * @return void
	 */
	public function test_unreachable_feed_keeps_stock_and_alerts() {
		update_post_meta( 11, '_stock_status', 'instock' );
		update_post_meta( 12, '_stock_status', 'instock' );
		$url = $this->unreachable_url();

		$all = $this->run_unreachable( $url, $this->supplier_rows() );

		$this->assertStringContainsString( 'недоступна', $all );
		$this->assertSame( 'instock', get_post_meta( 11, '_stock_status', true ) );
		$this->assertSame( 'instock', get_post_meta( 12, '_stock_status', true ) );
		$this->assertGreaterThan( 0, (int) get_option( 'f2000cs_feed_down_' . md5( $url ) ), 'The start of the outage is remembered' );
	}

	/**
	 * A feed that stays unreachable past the grace period takes the products off sale.
	 *
	 * @return void
	 */
	public function test_unreachable_feed_past_grace_takes_products_off_sale() {
		update_post_meta( 11, '_stock_status', 'instock' );
		update_post_meta( 12, '_stock_status', 'instock' );
		$url = $this->unreachable_url();
		update_option( 'f2000cs_feed_down_' . md5( $url ), time() - 13 * HOUR_IN_SECONDS );

		$this->run_unreachable( $url, $this->supplier_rows() );

		$this->assertSame( 'outofstock', get_post_meta( 11, '_stock_status', true ) );
		$this->assertSame( 'outofstock', get_post_meta( 12, '_stock_status', true ) );
	}

	/**
	 * A feed that is reachable again ends the outage.
	 *
	 * @return void
	 */
	public function test_valid_feed_clears_the_outage_mark() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, '<?xml version="1.0"?><yml_catalog><shop><offers><offer id="1" available="true"><price>10</price></offer></offers></shop></yml_catalog>' );
		update_option( 'f2000cs_feed_down_' . md5( $tmp ), time() - HOUR_IN_SECONDS );
		$GLOBALS['wpdb']->results_queue = array( array() );

		( new XML_Stock_Updater( $tmp, 'SUP_' ) )->update_products_stock_status();
		unlink( $tmp );

		$this->assertFalse( get_option( 'f2000cs_feed_down_' . md5( $tmp ) ) );
	}

	/**
	 * A product that manages its own stock keeps it on a broken feed while quantities are frozen:
	 * later valid runs do not write its status, so it could never come back.
	 *
	 * @return void
	 */
	public function test_broken_feed_keeps_managed_stock_when_quantities_are_frozen() {
		$managed = new class() {
			/**
			 * @return bool
			 */
			public function managing_stock() {
				return true;
			}
		};
		$plain   = new class() {
			/**
			 * @return bool
			 */
			public function managing_stock() {
				return false;
			}
		};

		F2000CS_Test_State::$products[31] = $managed;
		F2000CS_Test_State::$products[32] = $plain;
		update_post_meta( 31, '_stock_status', 'instock' );
		update_post_meta( 32, '_stock_status', 'instock' );

		$this->run_with_feed(
			'<?xml version="1.0"?><yml_catalog><shop><offers><offer id="1" avail',
			'SUP_',
			array(
				(object) array( 'id' => 31, 'type' => 'product', 'parent' => 0 ),
				(object) array( 'id' => 32, 'type' => 'product', 'parent' => 0 ),
			),
			true
		);

		$this->assertSame( 'instock', get_post_meta( 31, '_stock_status', true ), 'Managed stock is left to its quantity' );
		$this->assertSame( 'outofstock', get_post_meta( 32, '_stock_status', true ) );
	}

	/**
	 * The wp_tempnam() fallback file of a broken feed is removed by the finally block.
	 *
	 * @return void
	 */
	public function test_broken_feed_leaves_no_temp_file_behind() {
		$before = (array) glob( get_temp_dir() . 'f2000cs_*' );

		update_option( 'f2000cs_telegram_token_id', '123:abc' );
		update_option( 'f2000cs_telegram_user_ids', '111' );
		$GLOBALS['wpdb']->results_queue = array( $this->supplier_rows() );

		// A local path that cannot be opened: XMLReader falls back to the fetch + temp file path.
		$missing = get_temp_dir() . 'f2000cs_missing-' . uniqid() . '.xml';
		F2000CS_Test_State::$http_get_responses[ $missing ] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<?xml version="1.0"?><yml_catalog><shop><offers><offer id="1" available="true"><price>10</price></offer><offer id="2" avail',
		);

		( new XML_Stock_Updater( $missing, 'SUP_' ) )->update_products_stock_status();

		$leftover = array_values( array_diff( (array) glob( get_temp_dir() . 'f2000cs_*' ), $before ) );

		$this->assertSame( array(), $leftover, 'wp_tempnam() fallback files must be deleted in finally' );
	}

	/**
	 * Resource cleanup flushes only the transients group, not the whole object cache.
	 *
	 * @return void
	 */
	public function test_cleanup_resources_flushes_only_the_transients_group() {
		$updater = new XML_Stock_Updater( 'x.xml', 'SUP_' );

		F2000CS_Test_Reflection::invoke( $updater, 'cleanup_resources' );

		$this->assertSame( 0, F2000CS_Test_State::$cache_flush_calls, 'No global object-cache flush' );
		$this->assertContains( 'transients', F2000CS_Test_State::$cache_flushed_groups );
	}

	/**
	 * Resource cleanup frees the in-process (runtime) object cache so a long
	 * run does not hold every loaded product in memory until the end.
	 *
	 * @return void
	 */
	public function test_cleanup_resources_frees_the_runtime_cache() {
		$updater = new XML_Stock_Updater( 'x.xml', 'SUP_' );

		F2000CS_Test_Reflection::invoke( $updater, 'cleanup_resources' );

		$this->assertSame( 1, F2000CS_Test_State::$cache_runtime_flush_calls, 'cleanup_resources() must free the runtime cache' );
		$this->assertSame( 0, F2000CS_Test_State::$cache_flush_calls, 'Still no global object-cache flush' );
	}

	/**
	 * A stock run frees the runtime cache periodically while batches are
	 * processed, not only once at the end.
	 *
	 * @return void
	 */
	public function test_stock_run_frees_the_runtime_cache() {
		$this->run_with_feed(
			'<?xml version="1.0"?><yml_catalog><shop><offers><offer id="1" available="true"><price>10</price></offer></offers></shop></yml_catalog>',
			'SUP_',
			array()
		);

		// Once for the first batch (every 10 batches) + once in cleanup_resources().
		$this->assertSame( 2, F2000CS_Test_State::$cache_runtime_flush_calls );
	}
}
