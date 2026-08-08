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
}
