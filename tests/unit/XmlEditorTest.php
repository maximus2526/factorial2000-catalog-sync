<?php
/**
 * XML_Editor tests (includes/class-xml-editor.php).
 *
 * The editor works purely on the XML feed: category tree building, offer
 * listing with conditions and filtered XML generation are all testable
 * offline with local fixture files.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\XML_Editor;

/**
 * XML editor tests.
 */
final class F2000CS_Unit_XmlEditorTest extends F2000CS_Unit_TestCase {

	/**
	 * Fixture XML contents.
	 *
	 * @var string
	 */
	private $fixture;

	/**
	 * Temp files created by make_editor() — cleaned up in tearDown().
	 *
	 * @var array<string>
	 */
	private $fixture_files = array();

	/**
	 * Set up the fixture feed.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->fixture_files = array();

		$this->fixture = '<?xml version="1.0" encoding="utf-8"?>' .
			'<yml_catalog date="2024-01-01"><shop><name>Supplier</name><categories>' .
			'<category id="1">Одяг</category>' .
			'<category id="11" parentId="1">Футболки</category>' .
			'<category id="12" parentId="1">Куртки</category>' .
			'<category id="2">Взуття</category>' .
			'</categories><offers>' .
			'<offer id="A1" available="true"><name>Футболка базова</name><name_ua>Футболка базова UA</name_ua><price>200</price><oldprice>250</oldprice><categoryId>11</categoryId><vendorCode>BrandA</vendorCode><picture>http://img.test/a1.jpg</picture><param name="Колір">Чорний</param></offer>' .
			'<offer id="A2" available="false"><name>Куртка зимова</name><price>1500</price><oldprice>1800</oldprice><categoryId>12</categoryId><vendorCode>V2</vendorCode></offer>' .
			'<offer id="B1" available="true"><name>Черевики</name><price>900</price><categoryId>2</categoryId></offer>' .
			'<offer id="C1" available="true"><name>Без категорії</name><price>100</price></offer>' .
			'</offers></shop></yml_catalog>';
	}

	protected function tearDown(): void {
		foreach ( $this->fixture_files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		parent::tearDown();
	}

	/**
	 * Write the fixture to a temp file and return an editor instance.
	 *
	 * @return XML_Editor
	 */
	private function make_editor(): XML_Editor {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $this->fixture );

		$editor = new XML_Editor( $tmp );
		$this->assertTrue( $editor->load(), 'Fixture XML must parse' );

		// Generate() re-reads from disk — keep the fixture alive.
		// Individual tests that create their own files clean up via unlink().
		$this->fixture_files[] = $tmp;

		return $editor;
	}

	/**
	 * load() fails on a missing file.
	 *
	 * @return void
	 */
	public function test_load_fails_on_missing_file() {
		$editor = new XML_Editor( sys_get_temp_dir() . '/does-not-exist-f2000cs.xml' );

		$this->assertFalse( $editor->load() );
		$this->assertFalse( $editor->is_loaded() );
	}

	/**
	 * Categories parse into a flat list with descendant counts.
	 *
	 * @return void
	 */
	public function test_categories_with_counts() {
		$categories = $this->make_editor()->get_categories();
		$by_id      = array();

		foreach ( $categories as $category ) {
			$by_id[ $category['id'] ] = $category;
		}

		$this->assertSame( 'Одяг', $by_id['1']['name'] );
		$this->assertSame( 'Футболки', $by_id['11']['name'] );
		$this->assertSame( '1', $by_id['11']['parent'] );
		$this->assertTrue( $by_id['1']['has_children'] );
		$this->assertFalse( $by_id['11']['has_children'] );

		// Parent category counts direct + descendant offers.
		$this->assertSame( 1, $by_id['11']['count'] );
		$this->assertSame( 2, $by_id['1']['count'] );
		$this->assertSame( 1, $by_id['2']['count'] );
	}

	/**
	 * Offers are listed with title/price/stock/vendor/image metadata.
	 *
	 * @return void
	 */
	public function test_get_offers_metadata() {
		$offers = $this->make_editor()->get_offers( array( '11' ) );

		$this->assertCount( 1, $offers );

		$offer = $offers[0];

		$this->assertSame( 'A1', $offer['id'] );
		$this->assertSame( 'Футболка базова UA', $offer['title'], 'name_ua must take priority' );
		$this->assertSame( 200.0, $offer['price'] );
		$this->assertSame( 250.0, $offer['old_price'] );
		$this->assertTrue( $offer['available'] );
		$this->assertSame( '11', $offer['category_id'] );
		$this->assertSame( 'BrandA', $offer['vendor_code'] );
		$this->assertSame( 'http://img.test/a1.jpg', $offer['image'] );
	}

	/**
	 * Offer list is paginated and ordered by source position.
	 *
	 * @return void
	 */
	public function test_get_offers_pagination() {
		$editor = $this->make_editor();

		$page1 = $editor->get_offers( array( '1', '11', '12' ), 1, 0 );
		$page2 = $editor->get_offers( array( '1', '11', '12' ), 1, 1 );

		$this->assertCount( 1, $page1 );
		$this->assertCount( 1, $page2 );
		$this->assertSame( 'A1', $page1[0]['id'] );
		$this->assertSame( 'A2', $page2[0]['id'] );
	}

	/**
	 * Conditions filter by stock and price.
	 *
	 * @return void
	 */
	public function test_offer_conditions() {
		$editor = $this->make_editor();

		$this->assertSame( 4, $editor->count_offers() );

		$this->assertSame(
			3,
			$editor->count_offers( array(), array( 'only_in_stock' => true ) )
		);

		$this->assertSame(
			1,
			$editor->count_offers( array(), array( 'min_price' => 1000 ) )
		);

		$this->assertSame(
			1,
			$editor->count_offers( array(), array( 'max_price' => 150 ) )
		);
	}

	/**
	 * Generated XML keeps only the selected offers and their categories.
	 *
	 * @return void
	 */
	public function test_generate_filters_by_category() {
		$result = $this->make_editor()->generate(
			array( '12' ),
			array(),
			array(),
			array()
		);

		$xml = simplexml_load_string( $result['xml'] );

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 'A2', (string) $xml->shop->offers->offer[0]['id'] );
		$this->assertSame( 2, count( $xml->shop->categories->category ), 'Parent category must be kept' );
		$this->assertSame( '1500', (string) $xml->shop->offers->offer[0]->price );
		$this->assertSame( 'V2', (string) $xml->shop->offers->offer[0]->vendorCode );
	}

	/**
	 * Parent category selection includes descendant offers.
	 *
	 * @return void
	 */
	public function test_generate_includes_descendants() {
		$result = $this->make_editor()->generate( array( '1' ), array(), array(), array() );

		$xml = simplexml_load_string( $result['xml'] );

		$this->assertSame( 2, $result['count'] );
		$this->assertCount( 2, $xml->shop->offers->offer );
	}

	/**
	 * Individually checked offers are added, excluded ones removed.
	 *
	 * @return void
	 */
	public function test_generate_extra_and_excluded_offers() {
		$editor = $this->make_editor();

		$result = $editor->generate(
			array( '12' ),
			array( 'C1' ),
			array( 'A2' ),
			array()
		);

		$xml = simplexml_load_string( $result['xml'] );

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 'C1', (string) $xml->shop->offers->offer[0]['id'] );
	}

	/**
	 * Conditions apply to the generated output.
	 *
	 * @return void
	 */
	public function test_generate_applies_conditions() {
		$editor = $this->make_editor();

		$result = $editor->generate( array( '1', '2' ), array(), array(), array( 'only_in_stock' => true ) );

		$this->assertSame( 2, $result['count'] );

		$result = $editor->generate( array( '1', '2' ), array(), array(), array( 'min_price' => 1000 ) );

		$this->assertSame( 1, $result['count'] );
	}

	/**
	 * oldprice is stripped when keep_oldprice is disabled.
	 *
	 * @return void
	 */
	public function test_generate_removes_oldprice_when_disabled() {
		$result = $this->make_editor()->generate( array( '11' ), array(), array(), array( 'keep_oldprice' => false ) );

		$xml = simplexml_load_string( $result['xml'] );

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 0, count( $xml->shop->offers->offer[0]->oldprice ) );
	}

	/**
	 * SKU prefix is applied to offer ids and group ids.
	 *
	 * @return void
	 */
	public function test_generate_applies_sku_prefix() {
		$fixture = '<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="X1" group_id="G1" available="true"><name>T</name><price>10</price></offer>' .
			'<offer id="G1" available="true"><name>P</name><price>20</price></offer>' .
			'</offers></shop></yml_catalog>';

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $fixture );

		$editor = new XML_Editor( $tmp );
		$editor->load();

		$result = $editor->generate( array(), array( 'X1', 'G1' ), array(), array( 'sku_prefix' => 'NEW_' ) );

		$xml = simplexml_load_string( $result['xml'] );

		$this->assertSame( 'NEW_X1', (string) $xml->shop->offers->offer[0]['id'] );
		$this->assertSame( 'NEW_G1', (string) $xml->shop->offers->offer[0]['group_id'] );

		unlink( $tmp );
	}

	/**
	 * generate() with an empty selection returns an empty result.
	 *
	 * @return void
	 */
	public function test_generate_with_empty_selection() {
		$result = $this->make_editor()->generate( array(), array(), array(), array() );

		$this->assertSame( '', $result['xml'] );
		$this->assertSame( 0, $result['count'] );
	}

	/**
	 * YML root categories use parentId="0"; they must be treated as roots.
	 *
	 * @return void
	 */
	public function test_parent_id_zero_normalized_to_root() {
		$fixture = '<?xml version="1.0"?><yml_catalog><shop><categories>' .
			'<category id="1" parentId="0">Одяг</category>' .
			'<category id="11" parentId="1">Футболки</category>' .
			'</categories><offers>' .
			'<offer id="A1" available="true"><name>Футболка</name><price>100</price><categoryId>11</categoryId></offer>' .
			'</offers></shop></yml_catalog>';

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $fixture );

		$editor = new XML_Editor( $tmp );
		$this->assertTrue( $editor->load() );

		$by_id = array();
		foreach ( $editor->get_categories() as $category ) {
			$by_id[ $category['id'] ] = $category;
		}

		$this->assertSame( '', $by_id['1']['parent'], 'parentId="0" must be normalized to root' );
		$this->assertSame( '1', $by_id['11']['parent'] );
		$this->assertSame( 1, $by_id['1']['count'], 'Parent must still count descendants' );

		// Selecting the root must include the descendant offer.
		$this->assertSame( 1, $editor->count_offers( array( '1' ) ) );

		unlink( $tmp );
	}

	/**
	 * Categories pointing to a missing parent are treated as roots.
	 *
	 * @return void
	 */
	public function test_dangling_parent_normalized_to_root() {
		$fixture = '<?xml version="1.0"?><yml_catalog><shop><categories>' .
			'<category id="9" parentId="999">Сирота</category>' .
			'</categories><offers>' .
			'<offer id="O1" available="true"><name>Товар</name><price>10</price><categoryId>9</categoryId></offer>' .
			'</offers></shop></yml_catalog>';

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $fixture );

		$editor = new XML_Editor( $tmp );
		$editor->load();

		$categories = $editor->get_categories();

		$this->assertCount( 1, $categories );
		$this->assertSame( '', $categories[0]['parent'] );

		unlink( $tmp );
	}

	/**
	 * Search condition matches by title (case-insensitive) or offer id.
	 *
	 * @return void
	 */
	public function test_search_condition() {
		$editor = $this->make_editor();

		$this->assertSame(
			1,
			$editor->count_offers( array(), array( 'search' => 'куртка' ) ),
			'Search must match titles case-insensitively'
		);

		$offers = $editor->get_offers( array(), 20, 0, array( 'search' => 'A2' ) );

		$this->assertCount( 1, $offers );
		$this->assertSame( 'A2', $offers[0]['id'] );

		$this->assertSame(
			0,
			$editor->count_offers( array(), array( 'search' => 'нічого такого немає' ) )
		);
	}

	/**
	 * get_offer_ids returns every matching id (used by select-all toggle).
	 *
	 * @return void
	 */
	public function test_get_offer_ids() {
		$editor = $this->make_editor();

		$ids = $editor->get_offer_ids( array( '11' ) );
		$this->assertSame( array( 'A1' ), $ids );

		$ids = $editor->get_offer_ids( array( '1' ) );
		$this->assertSame( array( 'A1', 'A2' ), $ids, 'Parent selection must include descendants' );

		$ids = $editor->get_offer_ids( array(), array( 'only_in_stock' => true ) );
		$this->assertSame( array( 'A1', 'B1', 'C1' ), $ids );
	}

	/**
	 * Offers without a categoryId are grouped under a synthetic category so
	 * they can still be selected and exported.
	 *
	 * @return void
	 */
	public function test_uncategorized_offers_have_synthetic_category() {
		$fixture = '<?xml version="1.0"?><yml_catalog><shop><categories>' .
			'<category id="1">Одяг</category>' .
			'</categories><offers>' .
			'<offer id="A1" available="true"><name>Товар А</name><price>100</price><categoryId>1</categoryId></offer>' .
			'<offer id="U1" available="true"><name>Без категорії</name><price>50</price></offer>' .
			'</offers></shop></yml_catalog>';

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $fixture );

		$editor = new XML_Editor( $tmp );
		$this->assertTrue( $editor->load() );

		$by_id = array();
		foreach ( $editor->get_categories() as $category ) {
			$by_id[ $category['id'] ] = $category;
		}

		$this->assertArrayHasKey( '1', $by_id );
		$this->assertArrayHasKey( '__none__', $by_id, 'Synthetic uncategorized entry must exist' );
		$this->assertSame( 1, $by_id['__none__']['count'] );

		$offers = $editor->get_offers( array( '__none__' ) );
		$this->assertCount( 1, $offers );
		$this->assertSame( 'U1', $offers[0]['id'] );

		$result = $editor->generate( array( '__none__' ), array(), array(), array() );
		$this->assertSame( 1, $result['count'] );

		$xml = simplexml_load_string( $result['xml'] );
		$this->assertSame( 'U1', (string) $xml->shop->offers->offer[0]['id'] );

		unlink( $tmp );
	}

	/**
	 * available attribute is parsed tolerantly (case and 1/yes variants).
	 *
	 * @return void
	 */
	public function test_available_attribute_tolerance() {
		$fixture = '<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" available="TRUE"><name>А</name><price>10</price></offer>' .
			'<offer id="2" available="1"><name>Б</name><price>10</price></offer>' .
			'<offer id="3" available="yes"><name>В</name><price>10</price></offer>' .
			'<offer id="4" available="false"><name>Г</name><price>10</price></offer>' .
			'</offers></shop></yml_catalog>';

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $fixture );

		$editor = new XML_Editor( $tmp );
		$this->assertTrue( $editor->load() );

		$this->assertSame(
			3,
			$editor->count_offers( array(), array( 'only_in_stock' => true ) ),
			'TRUE / 1 / yes must count as in stock'
		);

		unlink( $tmp );
	}

	/**
	 * Feeds vary in element/attribute case (categoryID, group_id, OLDPRICE);
	 * parsing must be case-insensitive or products never match categories.
	 *
	 * @return void
	 */
	public function test_case_insensitive_element_and_attribute_names() {
		$fixture = '<?xml version="1.0"?><yml_catalog><shop><categories>' .
			'<category id="1" parentID="0">Одяг</category>' .
			'<category id="11" parentID="1">Футболки</category>' .
			'</categories><offers>' .
			'<offer id="5001" AVAILABLE="TRUE" GROUP_ID="77"><NAME>Сорочка</NAME><PRICE>300</PRICE><OLDPRICE>400</OLDPRICE><CATEGORYID>11</CATEGORYID><VENDORCODE>VX</VENDORCODE><PICTURE>http://img.test/a.jpg</PICTURE></offer>' .
			'</offers></shop></yml_catalog>';

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $fixture );

		$editor = new XML_Editor( $tmp );
		$this->assertTrue( $editor->load() );

		$categories = $editor->get_categories();
		$this->assertCount( 2, $categories, 'parentID must be recognized' );
		$this->assertSame( '', $categories[0]['parent'] );
		$this->assertSame( '1', $categories[1]['parent'] );

		// Uppercase CATEGORYID must still match the category filter.
		$this->assertSame( 1, $editor->count_offers( array( '11' ) ), 'CATEGORYID must map to category 11' );

		$offers = $editor->get_offers( array( '11' ) );
		$this->assertSame( 'Сорочка', $offers[0]['title'] );
		$this->assertSame( 400.0, $offers[0]['old_price'], 'OLDPRICE must be parsed' );
		$this->assertSame( 'VX', $offers[0]['vendor_code'] );
		$this->assertSame( 'http://img.test/a.jpg', $offers[0]['image'] );
		$this->assertTrue( $offers[0]['available'], 'AVAILABLE="TRUE" must count as in stock' );

		// group_id survives into the generated output.
		$result = $editor->generate( array( '11' ), array(), array(), array() );
		$xml    = simplexml_load_string( $result['xml'] );
		$this->assertSame( '77', (string) $xml->shop->offers->offer[0]['group_id'] );

		unlink( $tmp );
	}

	/**
	 * Numeric XML ids (real Prom.ua feeds) must round-trip as strings, or the
	 * JS tree breaks and extra/excluded offer matching silently fails.
	 *
	 * @return void
	 */
	public function test_numeric_ids_round_trip_as_strings() {
		$fixture = '<?xml version="1.0"?><yml_catalog><shop><categories>' .
			'<category id="1" parentId="0">Одяг</category><category id="11" parentId="1">Футболки</category>' .
			'</categories><offers>' .
			'<offer id="1001" available="true"><name>Сорочка</name><price>300</price><categoryId>11</categoryId></offer>' .
			'<offer id="1002" available="true"><name>Штани</name><price>500</price><categoryId>1</categoryId></offer>' .
			'</offers></shop></yml_catalog>';

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_editor_' );
		file_put_contents( $tmp, $fixture );

		$editor = new XML_Editor( $tmp );
		$this->assertTrue( $editor->load() );

		$categories = $editor->get_categories();
		$this->assertIsString( $categories[0]['id'], 'Category ids must stay strings for the JSON tree' );
		$this->assertSame( '1', $categories[0]['id'] );
		$this->assertSame( '11', $categories[1]['id'] );
		$this->assertSame( '1', $categories[1]['parent'] );

		$offers = $editor->get_offers( array( '1' ) );
		$this->assertIsString( $offers[0]['id'], 'Offer ids must stay strings' );
		$this->assertSame( array( '1001', '1002' ), array_column( $offers, 'id' ) );

		// Extra/excluded must match numeric ids posted as strings.
		$result = $editor->generate(
			array( '11' ),
			array( '1002' ),
			array( '1001' ),
			array()
		);
		$this->assertSame( 1, $result['count'] );

		$xml = simplexml_load_string( $result['xml'] );
		$this->assertSame( '1002', (string) $xml->shop->offers->offer[0]['id'] );

		unlink( $tmp );
	}
}
