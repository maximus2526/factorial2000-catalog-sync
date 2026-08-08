<?php
/**
 * XML_Export_Filter tests (includes/class-xml-export-filter.php).
 *
 * The filtering logic is pure SimpleXML — fully testable offline:
 * removal by existing SKU, removal by group_id, minimum price filtering
 * and attribute preservation.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\XML_Export_Filter;

/**
 * Export filter tests.
 */
final class F2000CS_Unit_XmlExportFilterTest extends F2000CS_Unit_TestCase {

	/**
	 * Sample YML feed used across tests.
	 *
	 * @var string
	 */
	private $feed;

	/**
	 * Set up the sample feed.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->feed = '<?xml version="1.0" encoding="utf-8"?>' .
			'<yml_catalog date="2024-01-01 10:00"><shop><name>Shop</name><offers>' .
			'<offer id="A1" available="true"><price>100</price><name>Товар A</name><param name="Колір">Чорний</param></offer>' .
			'<offer id="A2" available="true" group_id="G2"><price>50</price></offer>' .
			'<offer id="A3" available="false"><price>30</price></offer>' .
			'</offers></shop></yml_catalog>';
	}

	/**
	 * Offers already on the site (by SKU or group_id) and offers below the
	 * minimum price are removed.
	 *
	 * @return void
	 */
	public function test_filter_removes_existing_and_cheap_offers() {
		$filter = new XML_Export_Filter( 'http://shop.test/feed.xml', 'NEW_', 40 );
		F2000CS_Test_Reflection::set( $filter, 'site_skus', array( 'A1', 'G2' ) );

		$result = F2000CS_Test_Reflection::invoke( $filter, 'filter_xml_content', array( $this->feed ) );
		$xml    = simplexml_load_string( $result );

		$remaining = $xml->shop->offers->offer;
		$this->assertCount( 0, $remaining );
		$this->assertSame( 3, F2000CS_Test_Reflection::get( $filter, 'removed_count' ) );
	}

	/**
	 * Without a minimum price, only existing SKUs / group ids are removed.
	 *
	 * @return void
	 */
	public function test_filter_keeps_new_offers() {
		$filter = new XML_Export_Filter( 'http://shop.test/feed.xml', 'NEW_', 0 );
		F2000CS_Test_Reflection::set( $filter, 'site_skus', array( 'A1' ) );

		$result = F2000CS_Test_Reflection::invoke( $filter, 'filter_xml_content', array( $this->feed ) );
		$xml    = simplexml_load_string( $result );

		$remaining = $xml->shop->offers->offer;

		$this->assertCount( 2, $remaining );
		$this->assertSame( 'A2', (string) $remaining[0]['id'] );
		$this->assertSame( 'A3', (string) $remaining[1]['id'] );
		$this->assertSame( 1, F2000CS_Test_Reflection::get( $filter, 'removed_count' ) );
	}

	/**
	 * Filtered offers keep their attributes, group ids and child elements.
	 *
	 * @return void
	 */
	public function test_filter_preserves_offer_structure() {
		$filter = new XML_Export_Filter( 'http://shop.test/feed.xml', 'NEW_', 0 );
		F2000CS_Test_Reflection::set( $filter, 'site_skus', array( 'A1' ) );

		$result = F2000CS_Test_Reflection::invoke( $filter, 'filter_xml_content', array( $this->feed ) );
		$xml    = simplexml_load_string( $result );

		$offer = $xml->shop->offers->offer[0];

		$this->assertSame( 'true', (string) $offer['available'] );
		$this->assertSame( 'G2', (string) $offer['group_id'] );
		$this->assertSame( '50', (string) $offer->price );
	}

	/**
	 * The <price> child of an offer is read for min-price filtering.
	 *
	 * @return void
	 */
	public function test_get_offer_price() {
		$filter = new XML_Export_Filter( 'http://shop.test/feed.xml', 'NEW_', 40 );

		$offer_data = array(
			'id'       => 'X',
			'group_id' => '',
			'children' => array(
				array(
					'name'       => 'price',
					'value'      => '99.90',
					'attributes' => array(),
					'children'   => array(),
				),
			),
		);

		$this->assertSame( 99.9, F2000CS_Test_Reflection::invoke( $filter, 'get_offer_price', array( $offer_data ) ) );

		$no_price = $offer_data;
		$no_price['children'] = array();
		$this->assertSame( 0.0, F2000CS_Test_Reflection::invoke( $filter, 'get_offer_price', array( $no_price ) ) );
	}

	/**
	 * Broken XML throws an exception.
	 *
	 * @return void
	 */
	public function test_filter_invalid_xml_throws() {
		$filter = new XML_Export_Filter( 'http://shop.test/feed.xml', 'NEW_', 0 );

		$previous = error_reporting( 0 );
		try {
			$this->expectException( Exception::class );
			F2000CS_Test_Reflection::invoke( $filter, 'filter_xml_content', array( 'not xml at all' ) );
		} finally {
			error_reporting( $previous );
		}
	}

	/**
	 * create_filtered_xml fails gracefully when no site SKUs match the prefix.
	 *
	 * @return void
	 */
	public function test_create_filtered_xml_no_site_skus() {
		$filter = new XML_Export_Filter( 'http://shop.test/feed.xml', 'NEW_', 0 );

		$result = $filter->create_filtered_xml();

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'не знайдено товарів', mb_strtolower( $result['error'] ) );
	}
}
