<?php
/**
 * Price options matrix: how supplier adjustments, sale handling and the Prom
 * feed combine (includes/class-stock-updater.php, includes/marketplace/class-prom-feed.php).
 *
 * The expected values are the specification of the price behaviour, pinned as a
 * matrix so a change in one option shows how it affects the others.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Category_Tree;
use F2000CS\Prom_Feed;
use F2000CS\XML_Stock_Updater;

/**
 * Price options matrix tests.
 */
final class F2000CS_Unit_PriceOptionsMatrixTest extends F2000CS_Unit_TestCase {

	/**
	 * Settings used by the Prom feed tests.
	 *
	 * @var array<string,mixed>
	 */
	private $settings;

	/**
	 * Fresh state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->settings = array(
			'shop_name'              => 'Shop',
			'company_name'           => 'Shop Ltd',
			'site_url'               => 'https://shop.test',
			'currency'               => 'UAH',
			'max_pictures'           => 10,
			'regular_price_only'     => '0',
			'fake_discount_percent'  => '0',
			'fake_discount_spread'   => '',
		);
	}

	/**
	 * Build the XML feed with one offer.
	 *
	 * @param float      $price     Current price.
	 * @param float|null $old_price Old price, null when absent.
	 * @return string
	 */
	private function feed_xml( float $price, ?float $old_price ): string {
		$offer = '<offer id="1" available="true"><price>' . $price . '</price>';

		if ( null !== $old_price ) {
			$offer .= '<oldprice>' . $old_price . '</oldprice>';
		}

		return '<?xml version="1.0"?><yml_catalog><shop><offers>' . $offer . '</offer></offers></shop></yml_catalog>';
	}

	/**
	 * Supplier feed → extracted price and old price.
	 *
	 * @return array<string,array{0:float,1:float|null,2:bool,3:array,4:float,5:float}>
	 */
	public static function supplier_feeds(): array {
		return array(
			'A1 no options'                            => array( 80, 100, false, array(), 80.0, 100.0 ),
			'A2 ignore discounts'                      => array( 80, 100, true, array(), 100.0, 0.0 ),
			'A3 markup add 10'                         => array( 80, 100, false, array( 'type' => 'markup', 'value' => 10 ), 88.0, 110.0 ),
			'A4 ignore discounts + markup add 10'      => array( 80, 100, true, array( 'type' => 'markup', 'value' => 10 ), 110.0, 0.0 ),
			'A5 margin 20'                             => array( 80, 100, false, array( 'type' => 'margin', 'value' => 20 ), 100.0, 125.0 ),
			'A6 fixed add 50'                          => array( 1000, 2000, false, array( 'type' => 'fixed', 'value' => 50 ), 1050.0, 2050.0 ),
			'A7 fixed subtract 50 (max share default)' => array( 1000, 2000, false, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 50 ), 950.0, 1950.0 ),
			'A8 fixed subtract 5 above the 5 % share'  => array( 96, 100, false, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 5 ), 96.0, 100.0 ),
			'A9 fixed subtract 6, max share 10'        => array( 100, 200, false, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 6, 'max_share' => 10 ), 94.0, 194.0 ),
			'A10 fixed subtract 3, max share 100'      => array( 4, null, false, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 3, 'max_share' => 100 ), 1.0, 0.0 ),
			'A11 ignore discounts + fixed subtract 50' => array( 1000, 2000, true, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 50 ), 1950.0, 0.0 ),
			'A12 ignore discounts + fixed subtract 5'  => array( 96, 100, true, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 5 ), 95.0, 0.0 ),
			'A13 markup add 10 without old price'      => array( 100, null, false, array( 'type' => 'markup', 'value' => 10 ), 110.0, 0.0 ),
			'A14 markup add 10 with a lower old price' => array( 100, 90, false, array( 'type' => 'markup', 'value' => 10 ), 110.0, 99.0 ),
			'A15 fixed subtract 50, max share 0'       => array( 1000, 2000, false, array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 50, 'max_share' => 0 ), 1000.0, 2000.0 ),
			'A16 markup subtract 10'                   => array( 80, 100, false, array( 'type' => 'markup', 'direction' => 'subtract', 'value' => 10 ), 72.0, 90.0 ),
		);
	}

	/**
	 * The supplier feed is turned into the price and old price the update writes.
	 *
	 * @dataProvider supplier_feeds
	 * @param float      $price            Offer price.
	 * @param float|null $old_price        Offer old price.
	 * @param bool       $ignore_discounts «Do not load supplier discounts».
	 * @param array      $adjust           Price adjustment rule.
	 * @param float      $expected_price   Expected extracted price.
	 * @param float      $expected_old     Expected extracted old price.
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'supplier_feeds' )]
	public function test_supplier_feed_price_matrix( float $price, ?float $old_price, bool $ignore_discounts, array $adjust, float $expected_price, float $expected_old ) {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, $this->feed_xml( $price, $old_price ) );

		$updater = new XML_Stock_Updater( $tmp, '', false, $adjust, false, false, $ignore_discounts );
		$updates = F2000CS_Test_Reflection::invoke( $updater, 'extract_data_from_xml' );

		unlink( $tmp );

		$this->assertEqualsWithDelta( $expected_price, $updates['1']['price'], 0.001 );
		$this->assertEqualsWithDelta( $expected_old, $updates['1']['old_price'], 0.001 );
	}

	/**
	 * «Do not update prices» leaves the supplier prices untouched, adjustment included.
	 *
	 * @return void
	 */
	public function test_skip_price_updates_ignores_the_adjustment() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, $this->feed_xml( 80, 100 ) );

		$updater = new XML_Stock_Updater( $tmp, '', true, array( 'type' => 'markup', 'value' => 10 ) );
		$updates = F2000CS_Test_Reflection::invoke( $updater, 'extract_data_from_xml' );

		unlink( $tmp );

		$this->assertEqualsWithDelta( 80.0, $updates['1']['price'], 0.001 );
		$this->assertEqualsWithDelta( 100.0, $updates['1']['old_price'], 0.001 );
	}

	/**
	 * Extracted prices → WooCommerce meta.
	 *
	 * @return array<string,array{0:string,1:string|null,2:int|null,3:string,4:float,5:float,6:string,7:string,8:string,9:bool|null}>
	 */
	public static function product_price_calls(): array {
		return array(
			'B1 promotion starts'              => array( '100', null, null, '100.00', 80.0, 100.0, '100.00', '80.00', '80.00', true ),
			'B2 promotion ends'                => array( '100', '80', null, '80.00', 100.0, 0.0, '100.00', '', '100.00', true ),
			'B3 owner-scheduled sale is kept'  => array( '100', '80', DAY_IN_SECONDS, '80.00', 110.0, 0.0, '110.00', '80.00', '80.00', true ),
			'B4 unchanged without a sale'      => array( '100.00', null, null, '100.00', 100.0, 0.0, '100.00', '', '100.00', false ),
			'B5 old price not above the price' => array( '100', null, null, '100.00', 100.0, 90.0, '100.00', '', '100.00', null ),
		);
	}

	/**
	 * The WooCommerce meta a price/old-price pair leaves behind.
	 *
	 * @dataProvider product_price_calls
	 * @param string      $before_regular Before: regular price.
	 * @param string|null $before_sale    Before: sale price, null when none.
	 * @param int|null    $sale_dates_to  Before: sale end offset in seconds, null when none.
	 * @param string      $before_price   Before: active price.
	 * @param float       $price          Extracted price.
	 * @param float       $old_price      Extracted old price.
	 * @param string      $after_regular  Expected regular price.
	 * @param string      $after_sale     Expected sale price ('' when deleted).
	 * @param string      $after_price    Expected active price.
	 * @param bool|null   $expected_return Expected return, null when not asserted.
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'product_price_calls' )]
	public function test_product_price_matrix( string $before_regular, ?string $before_sale, ?int $sale_dates_to, string $before_price, float $price, float $old_price, string $after_regular, string $after_sale, string $after_price, ?bool $expected_return ) {
		$id = 201;

		update_post_meta( $id, '_regular_price', $before_regular );

		if ( null !== $before_sale ) {
			update_post_meta( $id, '_sale_price', $before_sale );
		}

		if ( null !== $sale_dates_to ) {
			update_post_meta( $id, '_sale_price_dates_to', (string) ( time() + $sale_dates_to ) );
		}

		update_post_meta( $id, '_price', $before_price );

		$updater = new XML_Stock_Updater( 'http://shop.test/feed.xml', 'SUP_' );
		$changed = F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $this->meta_priced_product( $id ), $price, $old_price ) );

		if ( null === $expected_return ) {
			$this->assertSame( '', (string) get_post_meta( $id, '_sale_price', true ), 'No sale is written when the old price is not above the price' );

			return;
		}

		$this->assertSame( $expected_return, $changed );
		$this->assertEqualsWithDelta( (float) $after_regular, (float) get_post_meta( $id, '_regular_price', true ), 0.001 );

		if ( '' === $after_sale ) {
			$this->assertSame( '', (string) get_post_meta( $id, '_sale_price', true ), 'The sale price must be deleted' );
		} else {
			$this->assertEqualsWithDelta( (float) $after_sale, (float) get_post_meta( $id, '_sale_price', true ), 0.001 );
		}

		$this->assertEqualsWithDelta( (float) $after_price, (float) get_post_meta( $id, '_price', true ), 0.001 );
	}

	/**
	 * A WC_Product stand-in whose prices are read from post meta, like WC does.
	 *
	 * @param int $id Product ID.
	 * @return object
	 */
	private function meta_priced_product( int $id ) {
		return new class( $id ) {
			/**
			 * Product ID.
			 *
			 * @var int
			 */
			private $id;

			/**
			 * @param int $id Product ID.
			 */
			public function __construct( $id ) {
				$this->id = $id;
			}

			/**
			 * @param string $type Type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return 'simple' === $type;
			}

			/**
			 * @return int
			 */
			public function get_parent_id() {
				return 0;
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

			/**
			 * @return string
			 */
			public function get_status() {
				return 'publish';
			}

			/**
			 * @return string
			 */
			public function get_stock_status() {
				return (string) get_post_meta( $this->id, '_stock_status', true );
			}

			/**
			 * @return string
			 */
			public function get_type() {
				return 'simple';
			}

			/**
			 * @return bool
			 */
			public function managing_stock() {
				return false;
			}
		};
	}

	/**
	 * Basic row of a simple product.
	 *
	 * @param array<string,string> $extra Overrides.
	 * @return array<string,string>
	 */
	private function simple_row( array $extra = array() ): array {
		return array_merge(
			array(
				'ID'            => '10',
				'Type'          => 'simple',
				'SKU'           => 'SKU-10',
				'Name'          => 'Куртка',
				'Published'     => '1',
				'Description'   => '<p>Тепла куртка</p>',
				'In stock?'     => '1',
				'Regular price' => '1000',
				'Sale price'    => '',
				'Categories'    => 'Одяг > Куртки',
				'Images'        => 'https://shop.test/a.jpg',
			),
			$extra
		);
	}

	/**
	 * Run one unit through the Prom feed.
	 *
	 * @param array $unit Unit.
	 * @return array
	 */
	private function process( array $unit ): array {
		return ( new Prom_Feed() )->process_unit( $unit, new Marketplace_Category_Tree(), $this->settings );
	}

	/**
	 * Site row → Prom offer prices.
	 *
	 * @return array<string,array{0:string,1:bool,2:string,3:string,4:string,5:string,6:string|null}>
	 */
	public static function prom_rows(): array {
		return array(
			'C1 no sale, real prices'                  => array( '', true, '0', '0', '', '1000', null ),
			'C2 sale price wins'                       => array( '800', true, '0', '0', '', '800', '1000' ),
			'C3 regular price only drops the sale'     => array( '800', true, '1', '0', '', '1000', null ),
			'C4 fake discount on a regular price'      => array( '', true, '1', '20', '', '1000', '1250' ),
			'C5 fake discount from the current sale'   => array( '800', true, '1', '20', '', '800', '1000' ),
			'C6 real sale, fake discount ignored'      => array( '800', true, '0', '20', '', '800', '1000' ),
			'C7 no regular-price-only, no fake'        => array( '', true, '0', '20', '', '1000', null ),
			'C8 no discount when not in stock'         => array( '', false, '1', '20', '', '1000', null ),
			'C9 no old price when not in stock'        => array( '800', false, '0', '0', '', '800', null ),
			'C10 fake discount 50 %'                   => array( '', true, '1', '50', '', '1000', '2000' ),
			'C11 fake discount clamped to 90 %'        => array( '', true, '1', '95', '', '1000', '10000' ),
			'C12 spread wins over the fixed percentage' => array( '', true, '1', '30', '20, 40', '1000', 'spread' ),
			'C13 spread accepts -25%'                  => array( '', true, '1', '0', '-25%', '1000', '1333.33' ),
		);
	}

	/**
	 * The Prom offer carries the price the matrix expects.
	 *
	 * @dataProvider prom_rows
	 * @param string      $sale          Sale price of the row.
	 * @param bool        $in_stock      Whether the row is in stock.
	 * @param string      $price_only    «Do not pass discounts — main price only».
	 * @param string      $fake_percent  Fake discount percentage.
	 * @param string      $fake_spread   Fake discount spread list.
	 * @param string      $expected_price Expected `<price>`.
	 * @param string|null $expected_old  Expected `<oldprice>`, null when absent, 'spread' for either spread value.
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'prom_rows' )]
	public function test_prom_feed_price_matrix( string $sale, bool $in_stock, string $price_only, string $fake_percent, string $fake_spread, string $expected_price, ?string $expected_old ) {
		$this->settings['regular_price_only']   = $price_only;
		$this->settings['fake_discount_percent'] = $fake_percent;
		$this->settings['fake_discount_spread']  = $fake_spread;

		$row = $this->simple_row(
			array(
				'Regular price' => '1000',
				'Sale price'    => $sale,
				'In stock?'     => $in_stock ? '1' : '0',
				'Stock'         => $in_stock ? '5' : '0',
			)
		);

		$res = $this->process( array( 'kind' => 'simple', 'row' => $row ) );

		$this->assertSame( 1, $res['exported'] );

		$xml   = simplexml_load_string( '<offers>' . $res['xml'] . '</offers>' );
		$offer = $xml->offer[0];

		$this->assertEqualsWithDelta( (float) $expected_price, (float) $offer->price, 0.001 );

		if ( null === $expected_old ) {
			$this->assertFalse( isset( $offer->oldprice ), 'No old price is written' );

			return;
		}

		if ( 'spread' === $expected_old ) {
			$allowed = array( 1250.0, 1666.67 );
			$first   = (float) $offer->oldprice;
			$matched = false;

			foreach ( $allowed as $value ) {
				if ( abs( $first - $value ) < 0.001 ) {
					$matched = true;
				}
			}

			$this->assertTrue( $matched, 'The spread list gives one of its percentages, not the fixed 30 %' );

			$again   = $this->process( array( 'kind' => 'simple', 'row' => $row ) );
			$again   = simplexml_load_string( '<offers>' . $again['xml'] . '</offers>' );
			$matched = false;

			foreach ( $allowed as $value ) {
				if ( abs( (float) $again->offer[0]->oldprice - $value ) < 0.001 ) {
					$matched = true;
				}
			}

			$this->assertTrue( $matched );
			$this->assertEqualsWithDelta( $first, (float) $again->offer[0]->oldprice, 0.001, 'The same product keeps its percentage on the next build' );

			return;
		}

		$this->assertEqualsWithDelta( (float) $expected_old, (float) $offer->oldprice, 0.001 );
	}

	/**
	 * The whole chain: supplier feed → WooCommerce meta → Prom offer.
	 *
	 * @return void
	 */
	public function test_whole_price_chain() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, $this->feed_xml( 80, 100 ) );

		$updater = new XML_Stock_Updater(
			$tmp,
			'',
			false,
			array( 'type' => 'fixed', 'direction' => 'subtract', 'value' => 3 ),
			false,
			false,
			true
		);
		$updates = F2000CS_Test_Reflection::invoke( $updater, 'extract_data_from_xml' );

		unlink( $tmp );

		$this->assertEqualsWithDelta( 97.0, $updates['1']['price'], 0.001 );
		$this->assertEqualsWithDelta( 0.0, $updates['1']['old_price'], 0.001 );

		$id = 301;
		update_post_meta( $id, '_regular_price', '100' );
		update_post_meta( $id, '_sale_price', '80' );
		update_post_meta( $id, '_price', '80.00' );

		F2000CS_Test_Reflection::invoke( $updater, 'update_product_price', array( $this->meta_priced_product( $id ), $updates['1']['price'], $updates['1']['old_price'] ) );

		$this->assertEqualsWithDelta( 97.0, (float) get_post_meta( $id, '_regular_price', true ), 0.001 );
		$this->assertSame( '', (string) get_post_meta( $id, '_sale_price', true ) );
		$this->assertEqualsWithDelta( 97.0, (float) get_post_meta( $id, '_price', true ), 0.001 );

		$this->settings['regular_price_only']    = '1';
		$this->settings['fake_discount_percent'] = '30';
		$this->settings['fake_discount_spread']  = '';

		$row = $this->simple_row(
			array(
				'Regular price' => '97',
				'Sale price'    => '',
			)
		);

		$res   = $this->process( array( 'kind' => 'simple', 'row' => $row ) );
		$xml   = simplexml_load_string( '<offers>' . $res['xml'] . '</offers>' );
		$offer = $xml->offer[0];

		$this->assertEqualsWithDelta( 97.0, (float) $offer->price, 0.001 );
		$this->assertEqualsWithDelta( 138.57, (float) $offer->oldprice, 0.001 );
	}
}
