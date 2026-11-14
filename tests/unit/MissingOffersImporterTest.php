<?php
/**
 * Missing_Offers_Importer: the stock sync adds offers that are not on the site.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Missing_Offers_Importer;

/**
 * Importer spy: a fake catalog instead of the database, creation steps recorded.
 */
class F2000CS_MissingOffersImporterSpy extends Missing_Offers_Importer {

	/**
	 * Site catalog: offer id => post ID.
	 *
	 * @var array<string,int>
	 */
	public $site = array();

	/**
	 * Parent id per group for find_group_parent().
	 *
	 * @var array<string,int>
	 */
	public $parents = array();

	/**
	 * Offer ids in the trash.
	 *
	 * @var string[]
	 */
	public $trash = array();

	/**
	 * Recorded steps: [step, ids...].
	 *
	 * @var array<int,array>
	 */
	public $steps = array();

	/**
	 * Offer data passed to the simple import, by offer id.
	 *
	 * @var array<string,array>
	 */
	public $simple_data = array();

	/**
	 * Next post ID.
	 *
	 * @var int
	 */
	private $next_id = 500;

	protected function ensure_categories_loaded(): void {
		$this->categories = array( 'loaded' => true );
		$this->currencies = array();
	}

	protected function get_product_ids_by_skus( array $skus ): array {
		return array_intersect_key( $this->site, array_flip( array_map( 'strval', $skus ) ) );
	}

	protected function created_ids( array $skus ): array {
		return $this->get_product_ids_by_skus( $skus );
	}

	protected function trashed_skus( array $skus ): array {
		return array_values( array_intersect( $skus, $this->trash ) );
	}

	protected function find_group_parent( string $group_id, array $variations_data ): int {
		return $this->parents[ $group_id ] ?? 0;
	}

	private function add( string $sku, string $type = 'product' ): void {
		$id                                  = $this->next_id++;
		$this->site[ $sku ]                  = $id;
		F2000CS_Test_State::$post_types[ $id ] = $type;
	}

	protected function import_simple_product( array $offer_data ): bool {
		$sku = (string) $offer_data['sku'];
		if ( isset( $this->site[ $sku ] ) ) {
			return false;
		}
		$this->steps[]             = array( 'simple', $sku );
		$this->simple_data[ $sku ] = $offer_data;
		$this->add( $sku );
		return true;
	}

	protected function import_variable_product( string $group_id, array $variations_data ): bool {
		$skus          = array_map( 'strval', array_column( $variations_data, 'sku' ) );
		$this->steps[] = array( 'variable', $group_id, $skus );
		foreach ( $skus as $sku ) {
			$this->add( $sku, 'product_variation' );
		}
		return true;
	}

	protected function add_variations_to_parent( int $parent_id, array $offers ): int {
		$skus          = array_map( 'strval', array_column( $offers, 'sku' ) );
		$this->steps[] = array( 'variations', $parent_id, $skus );
		foreach ( $skus as $sku ) {
			$this->add( $sku, 'product_variation' );
		}
		return count( $skus );
	}
}

/**
 * Importer tests.
 */
final class F2000CS_Unit_MissingOffersImporterTest extends F2000CS_Unit_TestCase {

	/**
	 * Feed fixture path.
	 *
	 * @var string
	 */
	private $path = '';

	protected function setUp(): void {
		parent::setUp();

		$this->path = tempnam( sys_get_temp_dir(), 'f2000cs_mi_' );
		file_put_contents(
			$this->path,
			'<?xml version="1.0"?><yml_catalog><shop><offers>
			<offer id="27150" available="true" group_id="21461381"><name>Pouch MultiCam</name><price>600</price><param name="Колір">MultiCam</param></offer>
			<offer id="50297" available="true" group_id="21461381"><name>Pouch Black MultiCam</name><price>600</price><param name="Колір">Black MultiCam</param></offer>
			<offer id="V1" available="true" group_id="PANTS"><name>Pants 54</name><price>1822</price><param name="Розмір">54</param></offer>
			<offer id="V2" available="true" group_id="PANTS"><name>Pants 56</name><price>1822</price><param name="Розмір">56</param></offer>
			<offer id="N1" available="true" group_id="CAP"><name>Cap Olive</name><price>420</price><param name="Колір">Olive</param></offer>
			<offer id="N2" available="true" group_id="CAP"><name>Cap Coyote</name><price>420</price><param name="Колір">Coyote</param></offer>
			<offer id="N3" available="false" group_id="CAP"><name>Cap Black</name><price>420</price><param name="Колір">Black</param></offer>
			<offer id="S1" available="true"><name>Carabiner</name><price>230</price></offer>
			<offer id="O1" available="false"><name>Gone</name><price>100</price></offer>
			<offer id="R1" available="true"><name>Deleted by owner</name><price>100</price></offer>
			<offer id="T1" available="true"><name>In trash</name><price>100</price></offer>
			</offers></shop></yml_catalog>'
		);
	}

	protected function tearDown(): void {
		if ( $this->path && file_exists( $this->path ) ) {
			unlink( $this->path );
		}
		parent::tearDown();
	}

	/**
	 * Importer for slot 3 over the fixture with the site state of the rechovyk case.
	 *
	 * @param array $price_adjust Price adjustment.
	 * @return F2000CS_MissingOffersImporterSpy
	 */
	private function importer( array $price_adjust = array() ): F2000CS_MissingOffersImporterSpy {
		$importer          = new F2000CS_MissingOffersImporterSpy( $this->path, 'VT_', 3, $price_adjust );
		$importer->site    = array(
			'27150' => 98281,
			'V1'    => 41,
		);
		$importer->parents = array( 'PANTS' => 40 );
		$importer->trash   = array( 'T1' );
		update_option( Missing_Offers_Importer::REGISTRY_OPTION . 3, array( 'R1' => 1700000000 ) );

		return $importer;
	}

	/**
	 * Offer ids the stock sync reports as not found.
	 *
	 * @return string[]
	 */
	private function missing(): array {
		return array( '50297', 'V2', 'N1', 'N2', 'N3', 'S1', 'O1', 'R1', 'T1' );
	}

	public function test_each_kind_of_missing_offer_is_added_the_right_way(): void {
		$importer = $this->importer();
		$result   = $importer->import_missing( $this->missing(), 50 );

		$this->assertSame(
			array(
				array( 'simple', '50297' ),
				array( 'variations', 40, array( 'V2' ) ),
				array( 'variable', 'CAP', array( 'N1', 'N2' ) ),
				array( 'simple', 'S1' ),
			),
			$importer->steps,
			'Black MultiCam joins its simple siblings, the new size joins its parent, a new group becomes a product, out-of-stock / deleted / trashed offers are left out'
		);
		$this->assertSame(
			array(
				'products'   => 3,
				'variations' => 3,
				'left'       => 0,
			),
			$result,
			'Products: Black MultiCam, the cap parent, the carabiner; variations: pants 56, cap Olive and Coyote'
		);
	}

	public function test_created_offers_are_remembered_and_new_products_are_private(): void {
		$importer = $this->importer();
		$importer->import_missing( $this->missing(), 50 );

		$registry = get_option( Missing_Offers_Importer::REGISTRY_OPTION . 3 );
		foreach ( array( 'R1', '50297', 'V2', 'N1', 'N2', 'S1' ) as $sku ) {
			$this->assertArrayHasKey( $sku, $registry );
		}
		$this->assertArrayNotHasKey( 'O1', $registry );
		$this->assertSame( 'private', F2000CS_Test_Reflection::get( $importer, 'product_status' ) );
	}

	public function test_limit_leaves_the_rest_for_later_runs(): void {
		$importer = $this->importer();
		$result   = $importer->import_missing( $this->missing(), 1 );

		$this->assertSame( array( array( 'simple', '50297' ) ), $importer->steps );
		$this->assertSame( 3, $result['left'] );
	}

	public function test_prices_get_the_supplier_adjustment(): void {
		$importer = $this->importer(
			array(
				'type'      => 'fixed',
				'direction' => 'subtract',
				'value'     => 3,
			)
		);
		$importer->import_missing( array( '50297' ), 50 );

		$this->assertEqualsWithDelta( 597.0, $importer->simple_data['50297']['price'], 0.001 );
	}

	public function test_nothing_to_do_without_offers(): void {
		$importer = $this->importer();

		$this->assertSame(
			array(
				'products'   => 0,
				'variations' => 0,
				'left'       => 0,
			),
			$importer->import_missing( array( 'R1', 'T1' ), 50 )
		);
		$this->assertSame( array(), $importer->steps );
	}

	public function test_pro_handler_needs_the_supplier_option(): void {
		$args = array(
			'slot'       => 3,
			'xml_url'    => 'http://example.com/feed.xml',
			'sku_prefix' => 'VT_',
			'skus'       => array( '50297' ),
			'deadline'   => 0,
		);

		update_option( 'f2000cs_import_missing_3', '1' );
		$this->assertNull( f2000cs_pro_import_missing_offers( null, $args ), 'Free: never imports' );

		$this->enable_pro();
		update_option( 'f2000cs_import_missing_3', '0' );
		$this->assertNull( f2000cs_pro_import_missing_offers( null, $args ), 'Pro, option off: nothing is imported' );

		update_option( 'f2000cs_import_missing_3', '1' );
		F2000CS_Test_State::$http_get_responses['http://example.com/feed.xml'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<?xml version="1.0"?><yml_catalog><shop><offers><offer id="50297" available="true"><name>X</name><price>600</price></offer></offers></shop></yml_catalog>',
		);
		$this->assertNull( f2000cs_pro_import_missing_offers( null, $args ), 'Pro, option on, but the stream download left an empty file: skipped safely' );
	}
}
