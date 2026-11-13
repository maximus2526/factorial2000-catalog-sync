<?php
/**
 * New offers of groups that are already on the site.
 *
 * - a variable product gets the new offers as variations (it used to skip the whole group);
 * - a group imported as separate simple products gets the new offers as simple products
 *   (variable mode used to add a duplicate parent, simple mode dropped the group);
 * - new products can be created with another status (cron imports them as private).
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Parser tests for missing offers.
 */
final class F2000CS_Unit_MissingOffersParserTest extends F2000CS_Unit_TestCase {

	/**
	 * @return F2000CS_MissingOffersSpyParser
	 */
	private function parser(): F2000CS_MissingOffersSpyParser {
		return new F2000CS_MissingOffersSpyParser( 'unused.xml', false, 'VT_' );
	}

	/**
	 * Offer data as extract_offer_data() returns it.
	 *
	 * @param string $sku   SKU without prefix.
	 * @param string $color Color value ('' = no attribute).
	 * @return array
	 */
	private function offer( string $sku, string $color = '' ): array {
		return array(
			'sku'        => $sku,
			'group_id'   => 'G',
			'title'      => 'Pouch ' . $color,
			'price'      => 600.0,
			'old_price'  => 0.0,
			'desc'       => '',
			'category'   => '',
			'available'  => 'instock',
			'vendor'     => '',
			'images'     => array(),
			'attributes' => '' === $color ? array() : array( 'Колір' => $color ),
			'weight'     => 0,
			'barcode'    => '',
			'dimensions' => '',
			'quantity'   => null,
		);
	}

	/**
	 * Parent 40 with the «Колір» variation attribute.
	 *
	 * @return void
	 */
	private function variable_parent(): void {
		update_post_meta(
			40,
			'_product_attributes',
			array(
				'pa_kolir' => array(
					'name'         => 'pa_kolir',
					'is_variation' => 1,
					'is_taxonomy'  => 1,
				),
			)
		);
	}

	public function test_group_with_a_parent_gets_variations(): void {
		$parser               = $this->parser();
		$parser->group_parent = 40;

		$plan = $parser->call( 'classify_group', array( 'G', array( $this->offer( '1' ) ), array() ) );

		$this->assertSame( array( 'mode' => 'variations', 'parent' => 40 ), $plan );
	}

	public function test_group_with_a_variation_on_the_site_uses_its_parent(): void {
		$parser                                 = $this->parser();
		$parser->site                           = array( '1' => 51 );
		F2000CS_Test_State::$post_types[51]     = 'product_variation';
		F2000CS_Test_State::$post_parents[51]   = 50;

		$offers   = array( $this->offer( '1' ), $this->offer( '2' ) );
		$existing = $parser->call( 'lookup_existing', array( array( '1', '2' ) ) );

		$this->assertSame( array( 'id' => 51, 'kind' => 'variation', 'parent' => 50 ), $existing['1'] );
		$this->assertSame( array( 'mode' => 'variations', 'parent' => 50 ), $parser->call( 'classify_group', array( 'G', $offers, $existing ) ) );
	}

	public function test_group_of_simple_products_stays_simple(): void {
		$parser       = $this->parser();
		$parser->site = array( '27150' => 98281 );

		$offers   = array( $this->offer( '27150', 'MultiCam' ), $this->offer( '50297', 'Black MultiCam' ) );
		$existing = $parser->call( 'lookup_existing', array( array( '27150', '50297' ) ) );

		$this->assertSame( 'simple', $existing['27150']['kind'] );
		$this->assertSame( array( 'mode' => 'simple', 'parent' => 0 ), $parser->call( 'classify_group', array( 'G', $offers, $existing ) ) );
	}

	public function test_unknown_group_is_new(): void {
		$plan = $this->parser()->call( 'classify_group', array( 'G', array( $this->offer( '1' ) ), array() ) );

		$this->assertSame( array( 'mode' => 'new', 'parent' => 0 ), $plan );
	}

	public function test_existing_parent_gets_only_the_new_offer_as_variation(): void {
		$this->variable_parent();
		$parser                = $this->parser();
		$parser->group_parent  = 40;
		$parser->real_variable = true;
		$parser->site          = array( '1' => 41 );
		F2000CS_Test_State::$post_types[41]   = 'product_variation';
		F2000CS_Test_State::$post_parents[41] = 40;

		$imported = $parser->call( 'import_variable_product', array( 'G', array( $this->offer( '1', 'Olive' ), $this->offer( '2', 'Black MultiCam' ) ) ) );

		$this->assertTrue( $imported );
		$this->assertSame( array( array( 40, array( '2' ), array( 'Колір' => array( 'Black MultiCam' ) ) ) ), $parser->variation_calls );
		$this->assertSame( array(), F2000CS_Test_State::$inserted_posts, 'No second parent is created' );

		$appended = array_filter(
			F2000CS_Test_State::$object_terms,
			static function ( $call ) {
				return 40 === $call[0] && 'pa_kolir' === $call[2] && true === $call[3];
			}
		);
		$this->assertCount( 1, $appended, 'The new color is appended to the parent attribute, not replacing it' );
	}

	public function test_value_another_variation_has_is_skipped(): void {
		global $wpdb;

		$this->variable_parent();
		F2000CS_Test_State::$terms['pa_kolir']['Olive'] = (object) array( 'term_id' => 7, 'slug' => 'olive' );
		update_post_meta( 41, 'attribute_pa_kolir', 'olive' );
		$wpdb->col_queue[] = array( 41 );

		$created = $this->parser()->call( 'add_variations_to_parent', array( 40, array( $this->offer( '3', 'Olive' ) ) ) );

		$this->assertSame( 0, $created );
	}

	public function test_offer_without_the_variation_attribute_is_skipped(): void {
		$this->variable_parent();
		$parser = $this->parser();

		$this->assertSame( 0, $parser->call( 'add_variations_to_parent', array( 40, array( $this->offer( '3' ) ) ) ) );
		$this->assertSame( array(), $parser->variation_calls );
	}

	public function test_group_of_simple_products_imports_new_offers_as_simple(): void {
		$parser                = $this->parser();
		$parser->real_variable = true;
		$parser->site          = array( '27150' => 98281 );

		$imported = $parser->call( 'import_variable_product', array( 'G', array( $this->offer( '27150', 'MultiCam' ), $this->offer( '50297', 'Black MultiCam' ) ) ) );

		$this->assertTrue( $imported );
		$this->assertSame( array( '27150', '50297' ), $parser->simple_skus, 'Each offer goes through the simple import (it skips existing SKUs)' );
		$this->assertSame( array(), F2000CS_Test_State::$inserted_posts, 'No variable parent next to the simple products' );
	}

	public function test_simple_mode_picks_up_groups_imported_as_simple_products(): void {
		$xml  = '<?xml version="1.0"?><yml_catalog><shop><offers>
			<offer id="27150" available="true" group_id="21461381"><name>MultiCam</name><price>600</price><param name="Колір">MultiCam</param></offer>
			<offer id="50297" available="true" group_id="21461381"><name>Black MultiCam</name><price>600</price><param name="Колір">Black MultiCam</param></offer>
			<offer id="V1" available="true" group_id="NEW"><name>New M</name><price>40</price><param name="Розмір">M</param></offer>
			<offer id="V2" available="true" group_id="NEW"><name>New L</name><price>40</price><param name="Розмір">L</param></offer>
		</offers></shop></yml_catalog>';
		$path = tempnam( sys_get_temp_dir(), 'f2000cs_mo_' );
		file_put_contents( $path, $xml );
		set_transient( 'f2000cs_import_variations_temp', '0', HOUR_IN_SECONDS );

		$parser       = new F2000CS_MissingOffersSpyParser( $path, false, 'VT_' );
		$parser->site = array( '27150' => 98281 );
		$result       = $parser->import_products( 0, 100 );
		unlink( $path );

		$this->assertSame( array( '27150', '50297' ), $parser->simple_skus, 'The new color of a «simple» group is imported; a brand-new group is left to variable mode' );
		$this->assertSame( 2, $result['total'] );
	}

	public function test_new_products_get_the_configured_status(): void {
		$parser = $this->parser();
		$parser->set_product_status( 'private' );
		$parser->real_simple = true;

		$this->assertTrue( $parser->call( 'import_simple_product', array( $this->offer( '50297' ) ) ) );
		$this->assertSame( 'private', F2000CS_Test_State::$inserted_posts[0]['post_status'] );
		$this->assertSame( 'VT_50297', get_post_meta( 1001, '_sku', true ) );
	}

	public function test_unknown_status_falls_back_to_publish(): void {
		$parser = $this->parser();
		$parser->set_product_status( 'trash' );

		$this->assertSame( 'publish', F2000CS_Test_Reflection::get( $parser, 'product_status' ) );
	}
}
