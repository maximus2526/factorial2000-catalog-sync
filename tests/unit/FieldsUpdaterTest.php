<?php
/**
 * Fields_Updater tests (includes/parsers/class-fields-updater.php).
 *
 * Covers the field whitelist, constructor sanitization and per-offer update
 * dispatch decisions.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Fields_Updater;

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	/**
	 * Stub: no terms in the database.
	 *
	 * @return array
	 */
	function wp_get_object_terms() {
		return array();
	}
}

/**
 * Fields updater tests.
 */
final class F2000CS_Unit_FieldsUpdaterTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset stub state before every test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * The whitelist covers every field exposed in the admin UI.
	 *
	 * @return void
	 */
	public function test_allowed_fields_whitelist() {
		$this->assertSame(
			array(
				'name',
				'description',
				'short_description',
				'tags',
				'price',
				'oldprice',
				'status',
				'stock_quantity',
				'images',
				'attributes',
				'categories',
				'vendorCode',
			),
			Fields_Updater::ALLOWED_FIELDS
		);
	}

	/**
	 * Constructor keeps only whitelisted fields.
	 *
	 * @return void
	 */
	public function test_constructor_filters_fields() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'name', 'price', 'hack', '' ) );

		$this->assertSame( array( 'name', 'price' ), F2000CS_Test_Reflection::get( $updater, 'fields' ) );
	}

	/**
	 * Duplicate and out-of-order whitelist entries are normalized.
	 *
	 * @return void
	 */
	public function test_constructor_deduplicates_fields() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'price', 'price', 'status' ) );

		$this->assertSame( array( 'price', 'status' ), F2000CS_Test_Reflection::get( $updater, 'fields' ) );
	}

	/**
	 * update_fields with an empty field list finishes immediately.
	 *
	 * @return void
	 */
	public function test_update_fields_empty_fields_finishes() {
		$updater = new Fields_Updater( 'missing-file.xml', 'P_', array() );

		$result = $updater->update_fields( 0, 10 );

		$this->assertTrue( $result['finished'] );
		$this->assertSame( 0, $result['processed'] );
		$this->assertSame( 0, $result['updated'] );
	}

	/**
	 * Offers without an id are skipped.
	 *
	 * @return void
	 */
	public function test_update_single_offer_skips_missing_id() {
		$updater  = new Fields_Updater( 'x.xml', 'P_', array( 'name' ) );
		$offer    = simplexml_load_string( '<offer available="true"><name>X</name></offer>' );

		$result = F2000CS_Test_Reflection::invoke( $updater, 'update_single_offer', array( $offer ) );

		$this->assertSame( 'skipped', $result );
	}

	/**
	 * Offers whose SKU does not exist in the database are marked not found.
	 *
	 * @return void
	 */
	public function test_update_single_offer_not_found() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'name' ) );
		$offer   = simplexml_load_string( '<offer id="NOPE" available="true"><name>X</name></offer>' );

		$result = F2000CS_Test_Reflection::invoke( $updater, 'update_single_offer', array( $offer ) );

		$this->assertSame( 'not_found', $result );
	}

	/**
	 * Attribute extraction collects <param> elements and vendor fallback.
	 *
	 * @return void
	 */
	public function test_extract_attributes() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'attributes' ) );
		$offer   = simplexml_load_string(
			'<offer id="1"><param name="Колір">Синій</param><param name="Розмір">42</param><vendor>Acme</vendor></offer>'
		);

		$attributes = F2000CS_Test_Reflection::invoke( $updater, 'extract_attributes', array( $offer ) );

		$this->assertSame( 'Синій', $attributes['Колір'] );
		$this->assertSame( '42', $attributes['Розмір'] );
		$this->assertSame( 'Acme', $attributes['Виробник'] );
	}

	/**
	 * Empty params and vendor are skipped.
	 *
	 * @return void
	 */
	public function test_extract_attributes_skips_empty() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'attributes' ) );
		$offer   = simplexml_load_string( '<offer id="1"><param name=""></param><param name="X"></param></offer>' );

		$attributes = F2000CS_Test_Reflection::invoke( $updater, 'extract_attributes', array( $offer ) );

		$this->assertSame( array(), $attributes );
	}

	/**
	 * update_tags announces the offer's Prom.ua keywords by language, trimmed.
	 *
	 * @return void
	 */
	public function test_update_tags_announces_keywords_by_language() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'tags' ) );
		$offer   = simplexml_load_string( '<offer id="1"><keywords>  куртка, зима  </keywords><keywords_ua>куртка, зима</keywords_ua></offer>' );

		$seen     = array();
		$listener = static function ( $product_id, $by_lang ) use ( &$seen ) {
			$seen[] = array( $product_id, $by_lang );
		};
		add_action( 'f2000cs_offer_keywords', $listener, 10, 2 );

		F2000CS_Test_Reflection::invoke( $updater, 'update_tags', array( 42, $offer ) );

		remove_filter( 'f2000cs_offer_keywords', $listener, 10 );

		$this->assertSame(
			array(
				array( 42, array( 'ru' => 'куртка, зима', 'uk' => 'куртка, зима' ) ),
			),
			$seen
		);
	}

	/**
	 * A language without keywords is left out of the announcement.
	 *
	 * @return void
	 */
	public function test_update_tags_announces_only_present_languages() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'tags' ) );
		$offer   = simplexml_load_string( '<offer id="1"><keywords>a, b</keywords><keywords_ua> </keywords_ua></offer>' );

		$seen     = array();
		$listener = static function ( $product_id, $by_lang ) use ( &$seen ) {
			$seen[] = array( $product_id, $by_lang );
		};
		add_action( 'f2000cs_offer_keywords', $listener, 10, 2 );

		F2000CS_Test_Reflection::invoke( $updater, 'update_tags', array( 7, $offer ) );

		remove_filter( 'f2000cs_offer_keywords', $listener, 10 );

		$this->assertSame(
			array(
				array( 7, array( 'ru' => 'a, b' ) ),
			),
			$seen
		);
	}

	/**
	 * An offer without any keywords never announces the action.
	 *
	 * @return void
	 */
	public function test_update_tags_does_not_announce_empty_keywords() {
		$updater = new Fields_Updater( 'x.xml', 'P_', array( 'tags' ) );
		$offer   = simplexml_load_string( '<offer id="1"><keywords> </keywords><keywords_ua></keywords_ua></offer>' );

		$seen     = array();
		$listener = static function ( $product_id, $by_lang ) use ( &$seen ) {
			$seen[] = array( $product_id, $by_lang );
		};
		add_action( 'f2000cs_offer_keywords', $listener, 10, 2 );

		F2000CS_Test_Reflection::invoke( $updater, 'update_tags', array( 1, $offer ) );

		remove_filter( 'f2000cs_offer_keywords', $listener, 10 );

		$this->assertSame( array(), $seen );
	}

	/**
	 * The currencies fallback temp file is removed by the finally block.
	 *
	 * @return void
	 */
	public function test_load_currencies_leaves_no_temp_file_behind() {
		$before = (array) glob( get_temp_dir() . 'f2000cs_*' );

		$missing = get_temp_dir() . 'f2000cs_missing-' . uniqid() . '.xml';
		F2000CS_Test_State::$http_get_responses[ $missing ] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<yml_catalog><shop><currencies><currency id="USD" rate="41.5"/></currencies></shop></yml_catalog>',
		);

		$updater    = new Fields_Updater( $missing, 'P_', array() );
		$currencies = F2000CS_Test_Reflection::invoke( $updater, 'load_currencies_from_xml' );

		$this->assertSame( 41.5, $currencies['USD'] ?? null );
		$this->assertSame(
			array(),
			array_values( array_diff( (array) glob( get_temp_dir() . 'f2000cs_*' ), $before ) ),
			'wp_tempnam() files must be deleted in finally'
		);
	}
}
