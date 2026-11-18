<?php
/**
 * ROZETKA price list (includes/marketplace/class-rozetka-feed.php).
 *
 * Spec: https://sellerhelp.rozetka.com.ua/p185-pricelist-requirements.html
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Category_Tree;
use F2000CS\Marketplace_Registry;
use F2000CS\Rozetka_Feed;

/**
 * ROZETKA feed tests.
 */
final class F2000CS_Unit_MarketplaceRozetkaFeedTest extends F2000CS_Unit_TestCase {

	/**
	 * Feed settings.
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

		Marketplace_Registry::reset();

		$this->settings = array(
			'shop_name'    => 'Shop',
			'company_name' => 'Shop Ltd',
			'site_url'     => 'https://shop.test',
			'currency'     => 'UAH',
		);
	}

	/**
	 * Simple product row.
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
				'Stock'         => '',
				'Regular price' => '1000',
				'Sale price'    => '',
				'Brands'        => 'Kiborg',
				'Categories'    => 'Одяг > Куртки',
				'Images'        => 'https://shop.test/a.jpg, https://shop.test/b.jpg',
				'Permalink'     => 'https://shop.test/kurtka/',
			),
			$extra
		);
	}

	/**
	 * Variable product with sizes.
	 *
	 * @param array<string,string> $sizes Suffix => size.
	 * @return array
	 */
	private function group_unit( array $sizes ): array {
		$parent = array(
			'ID'                   => '100',
			'Type'                 => 'variable',
			'SKU'                  => 'P-100',
			'Name'                 => 'Футболка',
			'Published'            => '1',
			'Description'          => '<p>Футболка</p>',
			'In stock?'            => '1',
			'Brands'               => 'Kiborg',
			'Categories'           => 'Одяг > Футболки',
			'Images'               => 'https://shop.test/p.jpg',
			'Permalink'            => 'https://shop.test/futbolka/',
			'Attribute 1 name'     => 'Розмір',
			'Attribute 1 value(s)' => implode( ', ', array_values( $sizes ) ),
			'Attribute 1 visible'  => '1',
		);

		$children = array();
		$id       = 101;

		foreach ( $sizes as $suffix => $size ) {
			$children[] = array(
				'ID'                   => (string) $id++,
				'Type'                 => 'variation',
				'SKU'                  => 'P-100-' . $suffix,
				'Name'                 => 'Футболка - ' . $size,
				'Published'            => '1',
				'In stock?'            => '1',
				'Stock'                => '3',
				'Regular price'        => '300',
				'Parent'               => 'P-100',
				'Attribute 1 name'     => 'Розмір',
				'Attribute 1 value(s)' => $size,
				'Attribute 1 visible'  => '1',
			);
		}

		return array(
			'kind'     => 'group',
			'parent'   => $parent,
			'children' => $children,
		);
	}

	/**
	 * Run one unit.
	 *
	 * @param array                          $unit Unit.
	 * @param Marketplace_Category_Tree|null $tree Tree.
	 * @return array
	 */
	private function process( array $unit, ?Marketplace_Category_Tree $tree = null ): array {
		return ( new Rozetka_Feed() )->process_unit( $unit, $tree ? $tree : new Marketplace_Category_Tree(), $this->settings );
	}

	/**
	 * Parse the offers of a result.
	 *
	 * @param array $res Result.
	 * @return SimpleXMLElement
	 */
	private function offers( array $res ): SimpleXMLElement {
		$xml = simplexml_load_string( '<offers>' . $res['xml'] . '</offers>', 'SimpleXMLElement', LIBXML_NOCDATA );

		$this->assertNotFalse( $xml, 'The offers are well-formed XML' );

		return $xml;
	}

	/**
	 * ROZETKA is a built-in marketplace.
	 *
	 * @return void
	 */
	public function test_registered() {
		$feed = Marketplace_Registry::get( 'rozetka' );

		$this->assertInstanceOf( Rozetka_Feed::class, $feed );
		$this->assertSame( 'ROZETKA', $feed->label() );
	}

	/**
	 * A simple product carries every element the spec marks required.
	 *
	 * @return void
	 */
	public function test_simple_offer_has_required_elements() {
		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row() ) ) )->offer[0];

		$this->assertSame( '10', (string) $offer['id'] );
		$this->assertSame( 'true', (string) $offer['available'] );
		$this->assertSame( '1000', (string) $offer->price );
		$this->assertSame( 'UAH', (string) $offer->currencyId );
		$this->assertNotSame( '', (string) $offer->categoryId );
		$this->assertCount( 2, $offer->picture );
		$this->assertSame( 'Kiborg', (string) $offer->vendor );
		$this->assertSame( 'SKU-10', (string) $offer->article );
		$this->assertSame( '10', (string) $offer->stock_quantity, 'In stock without a count: the default quantity' );
		$this->assertSame( 'Куртка', (string) $offer->name );
		$this->assertSame( 'Куртка', (string) $offer->name_ua );
		$this->assertStringContainsString( 'Тепла куртка', (string) $offer->description );
		$this->assertStringContainsString( 'Тепла куртка', (string) $offer->description_ua );
		$this->assertSame( 'https://shop.test/kurtka/', (string) $offer->url );
		$this->assertFalse( isset( $offer->price_old ), 'No discount, no old price' );
		$this->assertFalse( isset( $offer['group_id'] ), 'ROZETKA has no groups' );
	}

	/**
	 * Descriptions are HTML inside CDATA.
	 *
	 * @return void
	 */
	public function test_description_is_cdata() {
		$res = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row() ) );

		$this->assertStringContainsString( '<description><![CDATA[<p>Тепла куртка</p>]]></description>', $res['xml'] );
	}

	/**
	 * A sale gives the regular price as <price_old>; «regular price only» drops it.
	 *
	 * @return void
	 */
	public function test_sale_price_and_regular_price_only() {
		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Sale price' => '800' ) ) ) ) )->offer[0];

		$this->assertSame( '800', (string) $offer->price );
		$this->assertSame( '1000', (string) $offer->price_old );

		$this->settings['regular_price_only'] = '1';
		$offer                                 = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Sale price' => '800' ) ) ) ) )->offer[0];

		$this->assertSame( '1000', (string) $offer->price );
		$this->assertFalse( isset( $offer->price_old ) );
	}

	/**
	 * Stock: the count when kept, 0 and available="false" when out of stock.
	 *
	 * @return void
	 */
	public function test_stock_quantity() {
		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Stock' => '7' ) ) ) ) )->offer[0];
		$this->assertSame( '7', (string) $offer->stock_quantity );

		$offer = $this->offers(
			$this->process(
				array(
					'kind' => 'simple',
					'row'  => $this->simple_row(
						array(
							'In stock?' => '0',
							'Stock'     => '0',
						)
					),
				)
			)
		)->offer[0];

		$this->assertSame( '0', (string) $offer->stock_quantity );
		$this->assertSame( 'false', (string) $offer['available'] );

		$this->settings['default_stock_quantity'] = '3';
		$offer                                     = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row() ) ) )->offer[0];
		$this->assertSame( '3', (string) $offer->stock_quantity, 'The configured default' );
	}

	/**
	 * <vendor> is required: the default brand fills in, otherwise the product is skipped.
	 *
	 * @return void
	 */
	public function test_vendor_required() {
		$res = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Brands' => '' ) ) ) );

		$this->assertSame( 0, $res['exported'] );
		$this->assertSame( 1, $res['reasons']['no_vendor']['count'] ?? 0 );

		$this->settings['default_brand'] = 'Rechovyk';
		$offer                           = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Brands' => '' ) ) ) ) )->offer[0];

		$this->assertSame( 'Rechovyk', (string) $offer->vendor );
	}

	/**
	 * A combined brand is reduced to one, like in the Prom feed.
	 *
	 * @return void
	 */
	public function test_combined_vendor_is_one_brand() {
		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Brands' => 'Без бренду, Kiborg' ) ) ) ) )->offer[0];

		$this->assertSame( 'Kiborg', (string) $offer->vendor );
	}

	/**
	 * «Article in name» appends the SKU to both names.
	 *
	 * @return void
	 */
	public function test_article_in_name() {
		$this->settings['article_in_name'] = '1';

		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row() ) ) )->offer[0];

		$this->assertSame( 'Куртка (SKU-10)', (string) $offer->name );
		$this->assertSame( 'Куртка (SKU-10)', (string) $offer->name_ua );

		$long  = str_repeat( 'Д', 300 );
		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Name' => $long ) ) ) ) )->offer[0];

		$this->assertSame( 255, mb_strlen( (string) $offer->name ), 'Still within 255 characters' );
		$this->assertStringEndsWith( ' (SKU-10)', (string) $offer->name, 'The article survives the cut' );
	}

	/**
	 * The offer id keeps Latin letters and digits only, with the prefix.
	 *
	 * @return void
	 */
	public function test_offer_id_is_latin_and_digits() {
		$this->settings['id_prefix'] = 'rz-1_';

		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row() ) ) )->offer[0];

		$this->assertSame( 'rz110', (string) $offer['id'] );
	}

	/**
	 * Pictures: https only (http is upgraded), Cyrillic encoded, at most 15.
	 *
	 * @return void
	 */
	public function test_pictures() {
		$list = array( 'http://shop.test/old.jpg', 'ftp://shop.test/x.jpg', 'https://shop.test/фото 1.jpg' );

		for ( $i = 0; $i < 20; $i++ ) {
			$list[] = 'https://shop.test/p' . $i . '.jpg';
		}

		$pictures = Rozetka_Feed::https_pictures( implode( ', ', $list ) );

		$this->assertCount( 15, $pictures );
		$this->assertSame( 'https://shop.test/old.jpg', $pictures[0] );
		$this->assertStringStartsWith( 'https://shop.test/%D1%84', $pictures[1] );
		$this->assertNotContains( 'ftp://shop.test/x.jpg', $pictures );

		$res = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Images' => 'ftp://shop.test/x.jpg' ) ) ) );
		$this->assertSame( 1, $res['reasons']['no_pictures']['count'] ?? 0, 'No usable picture: skipped' );
	}

	/**
	 * Variations: one offer each, no group, the size in the name and as a param.
	 *
	 * @return void
	 */
	public function test_variations_are_separate_offers() {
		$res    = $this->process( $this->group_unit( array( 'S' => 'S', 'M' => 'M' ) ) );
		$offers = $this->offers( $res );

		$this->assertSame( 2, $res['exported'] );
		$this->assertCount( 2, $offers->offer );

		$names = array();
		foreach ( $offers->offer as $offer ) {
			$this->assertFalse( isset( $offer['group_id'] ) );
			$this->assertSame( '3', (string) $offer->stock_quantity );
			$this->assertSame( 'Kiborg', (string) $offer->vendor, 'The brand comes from the parent' );
			$names[] = (string) $offer->name;

			$sizes = array();
			foreach ( $offer->param as $param ) {
				if ( 'Розмір' === (string) $param['name'] ) {
					$sizes[] = (string) $param;
				}
			}
			$this->assertCount( 1, $sizes, 'One size per variation, not the whole list' );
		}

		$this->assertCount( 2, array_unique( $names ), 'Variation names differ' );
	}

	/**
	 * Variations with the same name get their own attribute values, and as a last resort
	 * their offer id, so every name is unique.
	 *
	 * @return void
	 */
	public function test_variation_names_are_unique() {
		$unit = $this->group_unit( array( 'L' => 'L', 'S' => 'S', 'S2' => 'S' ) );

		foreach ( $unit['children'] as $i => $child ) {
			$unit['children'][ $i ]['Name'] = 'Футболка';
		}

		$names = array();
		foreach ( $this->offers( $this->process( $unit ) )->offer as $offer ) {
			$names[] = (string) $offer->name;
		}

		$this->assertCount( 3, $names );
		$this->assertCount( 3, array_unique( $names ), implode( ' | ', $names ) );
		$this->assertContains( 'Футболка L', $names );

		$unit = $this->group_unit( array( 'L' => 'L', 'S' => 'S' ) );
		foreach ( $unit['children'] as $i => $child ) {
			$unit['children'][ $i ]['Name'] = 'Viscose turtleneck';
		}
		$names = array();
		foreach ( $this->offers( $this->process( $unit ) )->offer as $offer ) {
			$names[] = (string) $offer->name;
		}
		$this->assertSame( array( 'Viscose turtleneck L', 'Viscose turtleneck S' ), $names, 'A letter inside a word is not the size' );
	}

	/**
	 * The variations of an unpublished product are not offered.
	 *
	 * @return void
	 */
	public function test_unpublished_parent_hides_variations() {
		$unit                        = $this->group_unit( array( 'S' => 'S' ) );
		$unit['parent']['Published'] = '0';

		$this->assertSame( 0, $this->process( $unit )['exported'] );
	}

	/**
	 * Document: yml_catalog date, currency, categories with rz_id from the rules.
	 *
	 * @return void
	 */
	public function test_document_head_with_rz_id() {
		$this->settings['rz_category_rules'] = "одяг > куртки = 4637799\nфутболки=111";

		$feed = new Rozetka_Feed();
		$tree = new Marketplace_Category_Tree();
		$feed->process_unit( array( 'kind' => 'simple', 'row' => $this->simple_row() ), $tree, $this->settings );

		$head = $feed->document_head( $this->settings, $tree );

		$this->assertMatchesRegularExpression( '/<yml_catalog date="\d{4}-\d{2}-\d{2} \d{2}:\d{2}">/', $head );
		$this->assertStringContainsString( '<currency id="UAH" rate="1"/>', $head );
		$this->assertMatchesRegularExpression( '/<category id="\d+" parentId="\d+" rz_id="4637799">Куртки<\/category>/', $head );
		$this->assertDoesNotMatchRegularExpression( '/rz_id="[^"]*">Одяг</', $head, 'The parent category gets no rz_id from a deeper rule' );

		$xml = $head . $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row() ), $tree )['xml'] . $feed->tail();
		$this->assertNotFalse( simplexml_load_string( $xml ), 'The whole document is well-formed' );
	}

	/**
	 * A skipped product adds no category to the list.
	 *
	 * @return void
	 */
	public function test_skipped_product_adds_no_category() {
		$tree = new Marketplace_Category_Tree();

		$this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Brands' => '' ) ) ), $tree );

		$this->assertSame( 0, $tree->count() );
	}

	/**
	 * Rules match the end of a category path.
	 *
	 * @return void
	 */
	public function test_rz_id_rules() {
		$rules = Rozetka_Feed::parse_rules( "Одяг>Куртки=5\nкуртки=6\nbad line\nвзуття=x" );

		$this->assertSame( array( array( 'одяг>куртки', '5' ), array( 'куртки', '6' ) ), $rules );
		$this->assertSame( '5', Rozetka_Feed::rz_id_for_path( 'Одяг > Куртки', $rules ) );
		$this->assertSame( '6', Rozetka_Feed::rz_id_for_path( 'Дитяче > Куртки', $rules ) );
		$this->assertSame( '', Rozetka_Feed::rz_id_for_path( 'Одяг', $rules ) );
		$this->assertSame( '', Rozetka_Feed::rz_id_for_path( 'Одяг > Мікрокуртки', $rules ), 'Whole segments only' );
	}

	/**
	 * Bilingual names from the translator markup.
	 *
	 * @return void
	 */
	public function test_bilingual_names() {
		$row   = $this->simple_row( array( 'Name' => '[:ru]Куртка теплая[:ua]Куртка тепла[:]' ) );
		$offer = $this->offers( $this->process( array( 'kind' => 'simple', 'row' => $row ) ) )->offer[0];

		$this->assertNotSame( '', (string) $offer->name );
		$this->assertNotSame( '', (string) $offer->name_ua );
	}
}
