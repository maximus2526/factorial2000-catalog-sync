<?php
/**
 * Marketplace_WC_Source: WooCommerce products → CSV-shaped rows / units.
 *
 * Uses small fakes of the WooCommerce product API; the real thing was verified
 * end to end on a WooCommerce 10.9 site (variable + simple products → Prom feed).
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_WC_Source;

if ( ! function_exists( 'get_ancestors' ) ) {
	/**
	 * @param int    $id       Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return array<int,int>
	 */
	function get_ancestors( $id, $taxonomy = '', $type = '' ) {
		return F2000CS_Fake_Wc::$ancestors[ $id ] ?? array();
	}
}
if ( ! function_exists( 'get_term' ) ) {
	/**
	 * @param int    $id       Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return object|null
	 */
	function get_term( $id, $taxonomy = '' ) {
		return isset( F2000CS_Fake_Wc::$terms[ $id ] ) ? (object) array( 'name' => F2000CS_Fake_Wc::$terms[ $id ] ) : null;
	}
}
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	/**
	 * @param int $id Attachment id.
	 * @return string|false
	 */
	function wp_get_attachment_url( $id ) {
		return 'https://shop.test/img-' . $id . '.jpg';
	}
}
if ( ! function_exists( 'wc_attribute_label' ) ) {
	/**
	 * @param string $name Attribute name.
	 * @return string
	 */
	function wc_attribute_label( $name ) {
		return ucfirst( preg_replace( '/^pa_/', '', (string) $name ) );
	}
}
if ( ! function_exists( 'wp_get_object_terms' ) ) {
	/**
	 * @return array
	 */
	function wp_get_object_terms() {
		return array();
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * @param string $title Title.
	 * @return string
	 */
	function sanitize_title( $title ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9\-]+/u', '-', (string) $title ) );
	}
}

/**
 * Shared fake data.
 */
final class F2000CS_Fake_Wc {

	/**
	 * Term id => ancestor ids (nearest first, like core).
	 *
	 * @var array<int, array<int,int>>
	 */
	public static $ancestors = array();

	/**
	 * Term id => name.
	 *
	 * @var array<int,string>
	 */
	public static $terms = array();
}

/**
 * Fake product / variation.
 */
final class F2000CS_Fake_Product {

	/**
	 * Field values.
	 *
	 * @var array<string,mixed>
	 */
	public $f;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $fields Fields.
	 */
	public function __construct( array $fields ) {
		$this->f = array_merge(
			array(
				'id'         => 1,
				'type'       => 'simple',
				'status'     => 'publish',
				'sku'        => '',
				'name'       => 'Product',
				'regular'    => '100',
				'sale'       => '',
				'on_sale'    => false,
				'in_stock'   => true,
				'managing'   => false,
				'qty'        => null,
				'backorders' => 'no',
				'cats'       => array(),
				'image'      => 0,
				'gallery'    => array(),
				'attributes' => array(),
				'meta'       => array(),
			),
			$fields
		);
	}

	public function get_id() {
		return $this->f['id'];
	}
	public function get_type() {
		return $this->f['type'];
	}
	public function get_status() {
		return $this->f['status'];
	}
	public function get_sku() {
		return $this->f['sku'];
	}
	public function get_name() {
		return $this->f['name'];
	}
	public function get_short_description() {
		return '';
	}
	public function get_description() {
		return '<p>Desc</p>';
	}
	public function is_in_stock() {
		return $this->f['in_stock'];
	}
	public function managing_stock() {
		return $this->f['managing'];
	}
	public function get_stock_quantity() {
		return $this->f['qty'];
	}
	public function get_backorders() {
		return $this->f['backorders'];
	}
	public function is_on_sale() {
		return $this->f['on_sale'];
	}
	public function get_sale_price() {
		return $this->f['sale'];
	}
	public function get_regular_price() {
		return $this->f['regular'];
	}
	public function get_category_ids() {
		return $this->f['cats'];
	}
	public function get_image_id() {
		return $this->f['image'];
	}
	public function get_gallery_image_ids() {
		return $this->f['gallery'];
	}
	public function get_attributes() {
		return $this->f['attributes'];
	}
	public function get_meta( $key ) {
		return $this->f['meta'][ $key ] ?? '';
	}
}

/**
 * Fake product attribute (custom, non-taxonomy).
 */
final class F2000CS_Fake_Attribute {

	/**
	 * Name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Options.
	 *
	 * @var array<int,string>
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param string             $name    Name.
	 * @param array<int,string> $options Options.
	 */
	public function __construct( string $name, array $options ) {
		$this->name    = $name;
		$this->options = $options;
	}

	public function is_taxonomy() {
		return false;
	}
	public function get_name() {
		return $this->name;
	}
	public function get_options() {
		return $this->options;
	}
	public function get_visible() {
		return true;
	}
}

/**
 * WC source tests.
 */
final class F2000CS_Unit_MarketplaceWcSourceTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset fake data.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		F2000CS_Fake_Wc::$ancestors = array();
		F2000CS_Fake_Wc::$terms     = array();
	}

	/**
	 * Core fields, deepest category last, picture escaping, MPN, stock mapping.
	 *
	 * @return void
	 */
	public function test_simple_product_row() {
		F2000CS_Fake_Wc::$terms     = array( 1 => 'Одяг', 2 => 'Куртки, зимові' );
		F2000CS_Fake_Wc::$ancestors = array( 2 => array( 1 ) );

		$product = new F2000CS_Fake_Product(
			array(
				'id'         => 7,
				'sku'        => 'SKU-7',
				'name'       => 'Куртка',
				'regular'    => '1000',
				'sale'       => '800',
				'on_sale'    => true,
				'managing'   => true,
				'qty'        => 4,
				'backorders' => 'notify',
				'cats'       => array( 2, 1 ),
				'image'      => 11,
				'gallery'    => array( 12 ),
				'meta'       => array( 'f2000cs-updater-vendor' => 'SUP-9' ),
			)
		);

		$row = ( new Marketplace_WC_Source( array() ) )->row( $product );

		$this->assertSame( '7', $row['ID'] );
		$this->assertSame( '1', $row['Published'] );
		$this->assertSame( '800', $row['Sale price'] );
		$this->assertSame( '1000', $row['Regular price'] );
		$this->assertSame( '4', $row['Stock'] );
		$this->assertSame( 'notify', $row['Backorders allowed?'] );
		$this->assertSame( 'SUP-9', $row['Meta: mpn'] );
		$this->assertSame( 'https://shop.test/img-11.jpg, https://shop.test/img-12.jpg', $row['Images'] );
		$this->assertSame( 'Одяг, Одяг > Куртки\\, зимові', $row['Categories'], 'Shallow first so the product lands in its deepest category; commas in names are escaped' );
	}

	/**
	 * Sale price is only reported while the product is actually on sale.
	 *
	 * @return void
	 */
	public function test_sale_price_is_empty_when_not_on_sale() {
		$row = ( new Marketplace_WC_Source( array() ) )->row( new F2000CS_Fake_Product( array( 'sale' => '50', 'on_sale' => false ) ) );

		$this->assertSame( '', $row['Sale price'] );
	}

	/**
	 * A variation of an unpublished parent is not published; attributes are read from the variation.
	 *
	 * @return void
	 */
	public function test_variation_row() {
		$parent    = new F2000CS_Fake_Product(
			array(
				'id'         => 20,
				'type'       => 'variable',
				'status'     => 'draft',
				'sku'        => '',
				'attributes' => array( new F2000CS_Fake_Attribute( 'Розмір', array( 'S', 'M' ) ) ),
				'meta'       => array( 'f2000cs-updater-vendor' => 'PARENT-CODE' ),
			)
		);
		$variation = new F2000CS_Fake_Product(
			array(
				'id'         => 21,
				'type'       => 'variation',
				'attributes' => array( sanitize_title( 'Розмір' ) => 'M' ),
			)
		);

		$source     = new Marketplace_WC_Source( array() );
		$parent_row = $source->row( $parent );
		$row        = $source->row( $variation, $parent, $parent_row );

		$this->assertSame( '0', $parent_row['Published'] );
		$this->assertSame( '0', $row['Published'], 'Variation of a draft product is not published' );
		$this->assertSame( 'id:20', $row['Parent'], 'Parent without SKU is referenced as id:N' );
		$this->assertSame( 'S, M', $parent_row['Attribute 1 value(s)'] );
		$this->assertSame( 'M', $row['Attribute 1 value(s)'] );
		$this->assertSame( 'PARENT-CODE', $row['Meta: mpn'], 'Variation falls back to the parent supplier code' );
		$this->assertSame( '', $row['Categories'], 'Variations carry no categories of their own' );
	}

	/**
	 * The «Категорія Prom» meta of a product reaches the row; variations leave it to the parent.
	 *
	 * @return void
	 */
	public function test_prom_category_column() {
		$source  = new Marketplace_WC_Source( array() );
		$product = new F2000CS_Fake_Product( array( 'meta' => array( Marketplace_WC_Source::PROM_CATEGORY_META => '31302' ) ) );

		$this->assertSame( '31302', $source->row( $product )[ Marketplace_WC_Source::PROM_CATEGORY_COLUMN ] );
		$this->assertSame( '', $source->row( new F2000CS_Fake_Product( array() ) )[ Marketplace_WC_Source::PROM_CATEGORY_COLUMN ] );

		$parent    = new F2000CS_Fake_Product(
			array(
				'id'   => 20,
				'type' => 'variable',
				'meta' => array( Marketplace_WC_Source::PROM_CATEGORY_META => '31302' ),
			)
		);
		$variation = new F2000CS_Fake_Product(
			array(
				'id'   => 21,
				'type' => 'variation',
				'meta' => array( Marketplace_WC_Source::PROM_CATEGORY_META => '29' ),
			)
		);

		$this->assertSame( '', $source->row( $variation, $parent, $source->row( $parent ) )[ Marketplace_WC_Source::PROM_CATEGORY_COLUMN ] );
	}
}
