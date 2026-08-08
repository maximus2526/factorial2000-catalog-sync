<?php
/**
 * XML_Parser tests (includes/parsers/class-xml-parser.php).
 *
 * Covers transliteration / taxonomy slug generation, offer data extraction,
 * base product name extraction, variation attribute selection and category
 * loading — the building blocks of the product importer.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\XML_Parser;

/**
 * XML parser tests.
 */
final class F2000CS_Unit_XmlParserTest extends F2000CS_Unit_TestCase {

	/**
	 * Parser instance under test.
	 *
	 * @var XML_Parser
	 */
	private $parser;

	/**
	 * Set up a parser and reset stub state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->parser = new XML_Parser( 'http://shop.test/import.xml', false, 'SUP_' );
	}

	/**
	 * Cyrillic attribute names transliterate to lowercase ASCII slugs.
	 *
	 * @return void
	 */
	public function test_sanitize_for_taxonomy_transliteration() {
		$sanitize = function ( $text ) {
			return F2000CS_Test_Reflection::invoke( $this->parser, 'sanitize_for_taxonomy', array( $text ) );
		};

		$this->assertSame( 'kolir', $sanitize( 'Колір' ) );
		$this->assertSame( 'rozmir_odiagu_ua', $sanitize( 'Розмір одягу (UA)' ) );
		$this->assertSame( 'pryvit_svit', $sanitize( 'Привіт світ!' ) );
		$this->assertSame( 'brend_test', $sanitize( 'Бренд: Тест' ) );
		$this->assertSame( 'rozmir_cholovichogo_odiagu_u', $sanitize( 'Розмір чоловічого одягу (UA) і ще довгий суфікс' ) );
	}

	/**
	 * Product/category slugs use the same transliteration with a longer max length.
	 *
	 * @return void
	 */
	public function test_sanitize_slug_for_products_and_categories() {
		$slug = function ( $text, $max = 200 ) {
			return F2000CS_Test_Reflection::invoke( $this->parser, 'sanitize_slug', array( $text, $max ) );
		};

		$this->assertSame( 'chorni_krosivky_nike', $slug( 'Чорні кросівки Nike' ) );
		$this->assertSame( 'novynky', $slug( 'Новинки' ) );
		$this->assertSame( 'vzuttia', $slug( 'Взуття' ) );
		$this->assertSame( 'chornyi', $slug( 'Чорний' ) );

		$long = $slug( 'Дуже довга назва категорії товарів для перевірки ліміту довжини слага і ще трохи тексту' );
		$this->assertLessThanOrEqual( 200, strlen( $long ) );
		$this->assertGreaterThan( 28, strlen( $long ) );
		$this->assertDoesNotMatchRegularExpression( '/[^\x00-\x7F]/', $long );
	}

	/**
	 * insert_term_with_slug passes an ASCII slug into wp_insert_term args.
	 *
	 * @return void
	 */
	public function test_insert_term_with_slug_sets_transliterated_slug() {
		$result = F2000CS_Test_Reflection::invoke(
			$this->parser,
			'insert_term_with_slug',
			array( 'Чорний', 'pa_kolir', array() )
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'term_id', $result );
		$this->assertSame(
			'chornyi',
			F2000CS_Test_State::$last_insert_term_args['slug'] ?? null
		);
	}

	/**
	 * Slugs are limited to 28 characters.
	 *
	 * @return void
	 */
	public function test_sanitize_for_taxonomy_max_length() {
		$slug = F2000CS_Test_Reflection::invoke( $this->parser, 'sanitize_for_taxonomy', array( 'Дуже довга назва атрибута для обмеження довжини' ) );

		$this->assertLessThanOrEqual( 28, strlen( $slug ) );
	}

	/**
	 * Offer data extraction handles UA overrides, prices, images and params.
	 *
	 * @return void
	 */
	public function test_extract_offer_data() {
		$offer = simplexml_load_string(
			'<offer id="123" available="true" group_id="G1">' .
			'<name>Товар</name><name_ua>Товар UA</name_ua>' .
			'<price>100.50</price><oldprice>120</oldprice>' .
			'<description>Опис</description><description_ua>Опис UA</description_ua>' .
			'<categoryId>5</categoryId><vendor>VendorX</vendor>' .
			'<picture>http://img1.test/a.jpg</picture><picture>http://img2.test/b.jpg</picture>' .
			'<param name="Колір">Чорний</param><param name="Розмір">L</param>' .
			'</offer>'
		);

		$data = F2000CS_Test_Reflection::invoke( $this->parser, 'extract_offer_data', array( $offer ) );

		$this->assertSame( '123', $data['sku'] );
		$this->assertSame( 'G1', $data['group_id'] );
		$this->assertSame( 'Товар UA', $data['title'] );
		$this->assertSame( 100.5, $data['price'] );
		$this->assertSame( 120.0, $data['old_price'] );
		$this->assertSame( 'Опис UA', $data['desc'] );
		$this->assertSame( '5', $data['category'] );
		$this->assertSame( 'instock', $data['available'] );
		$this->assertSame( 'VendorX', $data['vendor'] );
		$this->assertSame( array( 'http://img1.test/a.jpg', 'http://img2.test/b.jpg' ), $data['images'] );
		$this->assertSame( 'Чорний', $data['attributes']['Колір'] );
		$this->assertSame( 'L', $data['attributes']['Розмір'] );
		$this->assertSame( 'VendorX', $data['attributes']['Виробник'], 'Vendor must be merged into attributes' );
	}

	/**
	 * Offers without attributes/images get safe defaults.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_defaults() {
		$offer = simplexml_load_string( '<offer id="1" available="false"><name>X</name><price>5</price></offer>' );

		$data = F2000CS_Test_Reflection::invoke( $this->parser, 'extract_offer_data', array( $offer ) );

		$this->assertSame( 'outofstock', $data['available'] );
		$this->assertSame( 0, $data['old_price'] );
		$this->assertSame( array(), $data['images'] );
		$this->assertSame( array(), $data['attributes'] );
	}

	/**
	 * Base product name strips sizes, ranges and colors from variation titles.
	 *
	 * @return void
	 */
	public function test_extract_base_product_name() {
		$extract = function ( array $names ) {
			return F2000CS_Test_Reflection::invoke( $this->parser, 'extract_base_product_name', array( $names ) );
		};

		$this->assertSame( 'Футболка', $extract( array( 'Футболка розмір M' ) ) );
		$this->assertSame( 'Футболка', $extract( array( 'Футболка Розмір L' ) ) );
		$this->assertSame( 'Кросівки', $extract( array( 'Кросівки 48-50' ) ) );
		$this->assertSame( 'Куртка', $extract( array( 'Куртка Чорний' ) ) );
		$this->assertSame( 'Ботинки', $extract( array( 'Ботинки' ) ) );
	}

	/**
	 * Empty variation names yield an empty base name.
	 *
	 * @return void
	 */
	public function test_extract_base_product_name_empty() {
		$result = F2000CS_Test_Reflection::invoke( $this->parser, 'extract_base_product_name', array( array() ) );

		$this->assertSame( '', $result );
	}

	/**
	 * Priority attributes (size before color) win for variation selection.
	 *
	 * @return void
	 */
	public function test_determine_variation_attributes_priority() {
		$variations = array(
			array(
				'attributes' => array(
					'Розмір' => 'M',
					'Колір'  => 'Чорний',
				),
			),
			array(
				'attributes' => array(
					'Розмір' => 'L',
					'Колір'  => 'Чорний',
				),
			),
			array(
				'attributes' => array(
					'Розмір' => 'XL',
					'Колір'  => 'Білий',
				),
			),
		);

		$attributes = F2000CS_Test_Reflection::invoke( $this->parser, 'determine_variation_attributes', array( $variations, 'G1' ) );

		$this->assertSame( array( 'Розмір' => array( 'M', 'L', 'XL' ) ), $attributes );
	}

	/**
	 * Attributes with a single value are never used as variation attributes.
	 *
	 * @return void
	 */
	public function test_determine_variation_attributes_single_value_ignored() {
		$variations = array(
			array( 'attributes' => array( 'Колір' => 'Чорний', 'Матеріал' => 'Бавовна' ) ),
			array( 'attributes' => array( 'Колір' => 'Білий', 'Матеріал' => 'Бавовна' ) ),
		);

		$attributes = F2000CS_Test_Reflection::invoke( $this->parser, 'determine_variation_attributes', array( $variations, 'G1' ) );

		$this->assertSame( array( 'Колір' => array( 'Чорний', 'Білий' ) ), $attributes );
	}

	/**
	 * Manually selected attributes (transient) override auto-detection.
	 *
	 * @return void
	 */
	public function test_determine_variation_attributes_manual_selection() {
		$variations = array(
			array( 'attributes' => array( 'Розмір' => 'M', 'Колір' => 'Чорний' ) ),
			array( 'attributes' => array( 'Розмір' => 'L', 'Колір' => 'Білий' ) ),
		);

		set_transient( 'f2000cs_selected_attributes_temp', array( 'G1' => 'Колір' ) );

		$attributes = F2000CS_Test_Reflection::invoke( $this->parser, 'determine_variation_attributes', array( $variations, 'G1' ) );

		$this->assertSame( array( 'Колір' => array( 'Чорний', 'Білий' ) ), $attributes );
	}

	/**
	 * Categories load from the XML <category> elements into an id => data map.
	 *
	 * @return void
	 */
	public function test_load_categories_from_xml() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents(
			$tmp,
			'<?xml version="1.0"?><yml_catalog><shop><categories>' .
			'<category id="1" parentId="0">Книги</category>' .
			'<category id="2" parentId="1">Детективи</category>' .
			'</categories></shop></yml_catalog>'
		);

		$parser = new XML_Parser( $tmp, false );
		$categories = F2000CS_Test_Reflection::invoke( $parser, 'load_categories_from_xml' );

		$this->assertSame( 'Книги', $categories['1']['name'] );
		$this->assertNull( $categories['1']['parent'] );
		$this->assertSame( 'Детективи', $categories['2']['name'] );
		$this->assertSame( '1', $categories['2']['parent'] );

		unlink( $tmp );
	}

	// ---- extract_offer_data: new YML fields ---------------------------------

	/**
	 * Helper: invoke extract_offer_data on a SimpleXMLElement parsed from XML string.
	 *
	 * @param string $xml Offer XML snippet.
	 * @return array
	 */
	private function extract_offer( string $xml ): array {
		$offer_element = simplexml_load_string( $xml );
		return F2000CS_Test_Reflection::invoke( $this->parser, 'extract_offer_data', array( $offer_element ) );
	}

	/**
	 * Multiple <categoryId> elements are comma-joined.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_multiple_category_ids() {
		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>10</price><categoryId>1</categoryId><categoryId>2</categoryId><categoryId>3</categoryId></offer>'
		);
		$this->assertSame( '1,2,3', $data['category'] );
	}

	/**
	 * Single <categoryId> works as before.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_single_category_id() {
		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>10</price><categoryId>42</categoryId></offer>'
		);
		$this->assertSame( '42', $data['category'] );
	}

	/**
	 * <param> unit attribute is appended to the value.
	 *
	 * @return void
	 */
	public function test_extract_param_unit_appended() {
		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>10</price><param name="Вага" unit="кг">5</param></offer>'
		);
		$this->assertSame( '5 кг', $data['attributes']['Вага'] );
	}

	/**
	 * <param> without unit works normally.
	 *
	 * @return void
	 */
	public function test_extract_param_no_unit() {
		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>10</price><param name="Колір">Червоний</param></offer>'
		);
		$this->assertSame( 'Червоний', $data['attributes']['Колір'] );
	}

	/**
	 * Multiple <param> with the same name are concatenated with "; ".
	 *
	 * @return void
	 */
	public function test_extract_param_same_name_concatenated() {
		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>10</price><param name="Колір">Червоний</param><param name="Колір">Синій</param></offer>'
		);
		$this->assertSame( 'Червоний; Синій', $data['attributes']['Колір'] );
	}

	/**
	 * Weight, barcode and dimensions are parsed from YML.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_weight_barcode_dimensions() {
		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>10</price><weight>0.75</weight><barcode>4820123456789</barcode><dimensions>10/20/30</dimensions></offer>'
		);
		$this->assertSame( 0.75, $data['weight'] );
		$this->assertSame( '4820123456789', $data['barcode'] );
		$this->assertSame( '10/20/30', $data['dimensions'] );
	}

	/**
	 * Currency conversion is applied when offer currencyId differs from store currency.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_currency_conversion() {
		F2000CS_Test_Reflection::set( $this->parser, 'currencies', array( 'USD' => 40.0 ) );

		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>25</price><oldprice>30</oldprice><currencyId>USD</currencyId></offer>'
		);

		$this->assertSame( 1000.0, $data['price'] );
		$this->assertSame( 1200.0, $data['old_price'] );
	}

	/**
	 * When currencyId matches the store currency, no conversion happens.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_same_currency_no_conversion() {
		F2000CS_Test_Reflection::set( $this->parser, 'currencies', array( 'UAH' => 1.0 ) );

		$data = $this->extract_offer(
			'<offer id="X" available="true"><price>100</price><currencyId>UAH</currencyId></offer>'
		);

		$this->assertSame( 100.0, $data['price'] );
	}

	/**
	 * available="1" is correctly treated as instock.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_available_as_1() {
		$data = $this->extract_offer(
			'<offer id="X" available="1"><price>10</price></offer>'
		);
		$this->assertSame( 'instock', $data['available'] );
	}

	/**
	 * available="yes" is correctly treated as instock.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_available_as_yes() {
		$data = $this->extract_offer(
			'<offer id="X" available="yes"><price>10</price></offer>'
		);
		$this->assertSame( 'instock', $data['available'] );
	}

	/**
	 * available="false" is correctly treated as outofstock.
	 *
	 * @return void
	 */
	public function test_extract_offer_data_available_false() {
		$data = $this->extract_offer(
			'<offer id="X" available="false"><price>10</price></offer>'
		);
		$this->assertSame( 'outofstock', $data['available'] );
	}

	/**
	 * Multi-value params (joined with "; ") split into separate taxonomy terms.
	 *
	 * @return void
	 */
	public function test_split_attribute_values() {
		$parser = new XML_Parser( 'http://stub.test/x.xml', false );
		$parts  = F2000CS_Test_Reflection::invoke( $parser, 'split_attribute_values', array( 'Червоний; Синій; ' ) );

		$this->assertSame( array( 'Червоний', 'Синій' ), $parts );
	}

	/**
	 * Simple product attributes assign all values from a concatenated param.
	 *
	 * @return void
	 */
	public function test_set_product_attributes_splits_multi_values() {
		$parser = new XML_Parser( 'http://stub.test/x.xml', false );

		F2000CS_Test_Reflection::invoke(
			$parser,
			'set_product_attributes',
			array(
				55,
				array( 'Колір' => 'Червоний; Синій' ),
			)
		);

		$attrs = get_post_meta( 55, '_product_attributes', true );
		$this->assertIsArray( $attrs );
		$this->assertNotEmpty( $attrs );

		$taxonomy = array_key_first( $attrs );
		$this->assertStringStartsWith( 'pa_', (string) $taxonomy );
		$this->assertSame( 0, (int) $attrs[ $taxonomy ]['is_variation'] );
	}

	/**
	 * Legacy stock updater on XML_Parser was removed — use XML_Stock_Updater.
	 *
	 * @return void
	 */
	public function test_parser_has_no_legacy_stock_updater() {
		$this->assertFalse(
			method_exists( XML_Parser::class, 'update_products_stock_status' ),
			'Stock sync must go through XML_Stock_Updater only'
		);
	}
}
