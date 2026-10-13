<?php
/**
 * Marketplace module tests: text helpers, category tree, Prom feed rules,
 * registry / settings, storage and the chunked build job.
 *
 * The Prom logic is a port of wc_to_prom_yml.py and was verified against the
 * original tool on a 13k-row catalog (tests/integration/prom-golden-compare.php);
 * these tests pin the individual rules.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Category_Tree;
use F2000CS\Marketplace_Feed;
use F2000CS\Marketplace_Form;
use F2000CS\Marketplace_Job;
use F2000CS\Marketplace_Registry;
use F2000CS\Marketplace_Storage;
use F2000CS\Marketplace_Text;
use F2000CS\Marketplace_Unit_Source;
use F2000CS\Prom_Feed;
use F2000CS\Prom_Variations;

/**
 * In-memory unit source.
 */
final class F2000CS_Fake_Unit_Source implements Marketplace_Unit_Source {

	/**
	 * Units per phase.
	 *
	 * @var array<string, array<int, array>>
	 */
	private $by_phase;

	/**
	 * Constructor.
	 *
	 * @param array<string, array<int, array>> $by_phase Units per phase.
	 */
	public function __construct( array $by_phase ) {
		$this->by_phase = $by_phase;
	}

	/**
	 * Phases.
	 *
	 * @return array<int,string>
	 */
	public function phases(): array {
		return array_keys( $this->by_phase );
	}

	/**
	 * Page of units.
	 *
	 * @param string $phase  Phase.
	 * @param int    $offset Cursor (row index here).
	 * @param int    $limit  Limit.
	 * @return array
	 */
	public function fetch( string $phase, int $offset, int $limit ): array {
		$slice = array_slice( $this->by_phase[ $phase ], $offset, $limit );

		return array(
			'units' => $slice,
			'next'  => $offset + count( $slice ),
			'done'  => count( $slice ) < $limit,
		);
	}
}

/**
 * Source paged by an id cursor; some rows vanish after the first page is read.
 */
final class F2000CS_Fake_Cursor_Source implements Marketplace_Unit_Source {

	/**
	 * Product ids.
	 *
	 * @var array<int,int>
	 */
	public $ids;

	/**
	 * Ids removed after the first fetch.
	 *
	 * @var array<int,int>
	 */
	private $vanish;

	/**
	 * Ids handed out so far.
	 *
	 * @var array<int,int>
	 */
	public $seen = array();

	/**
	 * Constructor.
	 *
	 * @param array<int,int> $ids    Ids.
	 * @param array<int,int> $vanish Ids that disappear after the first page.
	 */
	public function __construct( array $ids, array $vanish ) {
		$this->ids    = $ids;
		$this->vanish = $vanish;
	}

	/**
	 * Phases.
	 *
	 * @return array<int,string>
	 */
	public function phases(): array {
		return array( 'simple' );
	}

	/**
	 * Page after the cursor.
	 *
	 * @param string $phase  Phase.
	 * @param int    $offset Cursor.
	 * @param int    $limit  Limit.
	 * @return array
	 */
	public function fetch( string $phase, int $offset, int $limit ): array {
		$after = array();

		foreach ( $this->ids as $id ) {
			if ( $id > $offset ) {
				$after[] = $id;
			}
		}

		$page  = array_slice( $after, 0, $limit );
		$units = array();

		foreach ( $page as $id ) {
			$this->seen[] = $id;
			$units[]      = array(
				'kind' => 'simple',
				'row'  => array(
					'ID'            => (string) $id,
					'SKU'           => 'S' . $id,
					'Name'          => 'Товар ' . $id,
					'Published'     => '1',
					'In stock?'     => '1',
					'Regular price' => '10',
					'Categories'    => 'Одяг',
					'Images'        => 'https://shop.test/a.jpg',
				),
			);
		}

		$this->ids = array_values( array_diff( $this->ids, $this->vanish ) );

		return array(
			'units' => $units,
			'next'  => $page ? max( $page ) : $offset,
			'done'  => count( $page ) < $limit,
		);
	}
}

/**
 * Marketplace module tests.
 */
final class F2000CS_Unit_MarketplacePromFeedTest extends F2000CS_Unit_TestCase {

	/**
	 * Settings used by most tests.
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
			'max_pictures' => 10,
		);
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
				'Description'   => "<p>Тепла\nкуртка</p>",
				'In stock?'     => '1',
				'Regular price' => '1000',
				'Sale price'    => '',
				'Categories'    => 'Одяг > Куртки',
				'Images'        => 'https://shop.test/a.jpg, https://shop.test/b.jpg',
			),
			$extra
		);
	}

	/**
	 * Run one unit through the Prom feed.
	 *
	 * @param array                    $unit Unit.
	 * @param Marketplace_Category_Tree $tree Tree.
	 * @return array
	 */
	private function process( array $unit, ?Marketplace_Category_Tree $tree = null ): array {
		$tree = $tree ? $tree : new Marketplace_Category_Tree();

		return ( new Prom_Feed() )->process_unit( $unit, $tree, $this->settings );
	}

	// ---------------------------------------------------------------- text helpers

	/**
	 * Python-compatible helpers.
	 *
	 * @return void
	 */
	public function test_text_helpers() {
		$this->assertSame( 'a b', Marketplace_Text::clean( "\xC2\xA0 a b \t" ), 'NBSP is trimmed like Python str.strip()' );
		$this->assertSame( 1.5, Marketplace_Text::parse_float( '1,5' ) );
		$this->assertNull( Marketplace_Text::parse_float( 'abc' ) );
		$this->assertTrue( Marketplace_Text::parse_bool( 'TRUE' ) );
		$this->assertFalse( Marketplace_Text::parse_bool( '-1' ) );
		$this->assertSame( array( 'a,b', 'c' ), Marketplace_Text::split_escaped( 'a\\,b, c' ) );
		$this->assertSame( array( array( 'A', 'B' ), array( 'C' ) ), Marketplace_Text::parse_category_paths( 'A > B, C' ) );
		$this->assertSame( '120', Marketplace_Text::money( 120.0 ) );
		$this->assertSame( '120.50', Marketplace_Text::money( 120.5 ) );
		$this->assertSame( 'a &amp; b &lt;c&gt;', Marketplace_Text::xml_text( 'a & b <c>' ) );
		$this->assertSame( 'ab', Marketplace_Text::xml_safe( "a\x01b" ), 'Illegal XML control characters are dropped' );
		$this->assertSame( 'Tom&#x27;s', Marketplace_Text::html_escape( "Tom's" ) );
	}

	/**
	 * Sorting is stable and compares keys like Python tuples.
	 *
	 * @return void
	 */
	public function test_sorted_by_is_stable() {
		$items  = array( array( 'b', 1 ), array( 'a', 1 ), array( 'c', 0 ) );
		$sorted = Marketplace_Text::sorted_by(
			$items,
			static function ( $i ) {
				return array( $i[1] );
			}
		);

		$this->assertSame( array( array( 'c', 0 ), array( 'b', 1 ), array( 'a', 1 ) ), $sorted );
	}

	/**
	 * Size tokens sort like sizes, not alphabetically.
	 *
	 * @return void
	 */
	public function test_size_tokens_order() {
		$this->assertSame(
			array( '2XL', '3XL', 'S', 'M', 'L', 'XL' ),
			Prom_Variations::sorted_size_tokens( array( 'XL', 'S', '3XL', 'M', '2XL', 'L' ) ),
			'Numeric-prefixed XL sizes come first, then S < M < L < XL (rule of the Python tool)'
		);
		$this->assertSame( array( '36', '38', '40' ), Prom_Variations::sorted_size_tokens( array( '40', '36', '38' ) ) );
	}

	// ---------------------------------------------------------------- category tree

	/**
	 * Sequential ids, parents and a state round trip.
	 *
	 * @return void
	 */
	public function test_category_tree() {
		$tree = new Marketplace_Category_Tree();

		$this->assertSame( 2, $tree->add_paths( array( array( 'Одяг', 'Куртки' ) ) ) );
		$this->assertSame( 3, $tree->add_paths( array( array( 'Взуття' ) ) ) );
		$this->assertSame( 2, $tree->add_paths( array( array( 'Одяг', 'Куртки' ) ) ), 'Existing path reuses its id' );
		$this->assertNull( $tree->add_paths( array() ) );

		$restored = Marketplace_Category_Tree::from_array( $tree->to_array() );

		$this->assertSame( 3, $restored->count() );
		$this->assertSame( 0, $restored->broken_parents() );
		$this->assertSame( 4, $restored->add_paths( array( array( 'Одяг', 'Штани' ) ) ), 'Sequence continues after restore' );
		$this->assertStringContainsString( '<category id="2" parentId="1">Куртки</category>', $restored->to_xml() );
		$this->assertStringContainsString( '<category id="1">Одяг</category>', $restored->to_xml() );
	}

	// ---------------------------------------------------------------- simple offers

	/**
	 * A simple product becomes a complete Prom offer.
	 *
	 * @return void
	 */
	public function test_simple_offer_xml() {
		$res = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Brands' => 'Acme', 'GTIN, UPC, EAN, or ISBN' => '123', 'Meta: mpn' => 'SUP-1', 'Attribute 1 name' => 'Матеріал', 'Attribute 1 value(s)' => 'Бавовна', 'Attribute 1 visible' => '1' ) ) ) );

		$this->assertSame( 1, $res['exported'] );
		$this->assertSame( 0, $res['skipped'] );

		$xml = $res['xml'];

		$this->assertStringContainsString( '<offer id="10" available="true">', $xml );
		$this->assertStringContainsString( '<price>1000</price>', $xml );
		$this->assertStringContainsString( '<categoryId>2</categoryId>', $xml );
		$this->assertStringContainsString( '<vendorCode>SKU-10</vendorCode>', $xml );
		$this->assertStringContainsString( '<vendor>Acme</vendor>', $xml );
		$this->assertStringContainsString( '<mpn>SUP-1</mpn>', $xml );
		$this->assertStringContainsString( '<barcode>123</barcode>', $xml );
		$this->assertStringContainsString( '<picture>https://shop.test/b.jpg</picture>', $xml );
		$this->assertStringContainsString( '<param name="Матеріал">Бавовна</param>', $xml );
		$this->assertStringContainsString( '&lt;p&gt;Тепла куртка&lt;/p&gt;', $xml, 'Raw line breaks are folded out of the HTML description' );
		$this->assertStringNotContainsString( 'group_id', $xml );
		$this->assertStringNotContainsString( 'oldprice', $xml );

		$this->assertTrue( simplexml_load_string( '<offers>' . $xml . '</offers>' ) instanceof SimpleXMLElement, 'Offer XML is well-formed' );
	}

	/**
	 * Sale price becomes price + oldprice, but only for purchasable items.
	 *
	 * @return void
	 */
	public function test_sale_price_and_availability() {
		$sale = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Sale price' => '800' ) ) ) );

		$this->assertStringContainsString( '<price>800</price>', $sale['xml'] );
		$this->assertStringContainsString( '<oldprice>1000</oldprice>', $sale['xml'] );

		$out = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Sale price' => '800', 'In stock?' => '0', 'Stock' => '0' ) ) ) );

		$this->assertStringContainsString( 'available="false"', $out['xml'] );
		$this->assertStringNotContainsString( 'oldprice', $out['xml'], 'Prom rejects a discount on unavailable items' );

		$back = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'In stock?' => '0', 'Backorders allowed?' => 'notify' ) ) ) );

		$this->assertStringContainsString( 'available="true"', $back['xml'], 'Backorders count as available' );
	}

	/**
	 * Offers Prom cannot accept are skipped and counted.
	 *
	 * @return void
	 */
	public function test_skipped_rows() {
		foreach ( array(
			'no price'    => array( 'Regular price' => '' ),
			'no images'   => array( 'Images' => '' ),
			'no category' => array( 'Categories' => '' ),
			'no name'     => array( 'Name' => '' ),
			'unpublished' => array( 'Published' => '0' ),
		) as $label => $override ) {
			$res = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( $override ) ) );

			$this->assertSame( 0, $res['exported'], $label );
			$this->assertSame( 1, $res['skipped'], $label );
			$this->assertSame( '', $res['xml'], $label );
		}

		$this->settings['include_unpublished'] = '1';
		$res                                   = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Published' => '0' ) ) ) );

		$this->assertSame( 1, $res['exported'], 'include_unpublished exports drafts' );
	}

	/**
	 * Description fallback, vendor aliases / default and portal category rules.
	 *
	 * @return void
	 */
	public function test_description_vendor_and_portal_rules() {
		$this->settings['description_fallback_snippet'] = "Деталі в характеристиках";
		$this->settings['default_vendor']               = 'Власне виробництво';
		$this->settings['vendor_aliases']               = "acme=ACME Ukraine";
		$this->settings['portal_category_rules']        = "одяг>куртки=555\nвзуття=777";
		$this->settings['default_portal_category_id']   = '999';

		$res = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Description' => '<p> </p>', 'Brands' => 'Acme' ) ) ) );

		$this->assertStringContainsString( '&lt;p&gt;Куртка. Деталі в характеристиках&lt;/p&gt;', $res['xml'] );
		$this->assertStringContainsString( '<vendor>ACME Ukraine</vendor>', $res['xml'], 'Aliases match case-insensitively' );
		$this->assertStringContainsString( '<portal_category_id>555</portal_category_id>', $res['xml'] );

		$noVendor = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Categories' => 'Інше' ) ) ) );

		$this->assertStringContainsString( '<vendor>Власне виробництво</vendor>', $noVendor['xml'] );
		$this->assertStringContainsString( '<portal_category_id>999</portal_category_id>', $noVendor['xml'] );
	}

	// ---------------------------------------------------------------- variable products

	/**
	 * A variable product with size variations.
	 *
	 * @param array<string,string> $sizes SKU suffix => size.
	 * @return array<string,mixed>
	 */
	private function group_unit( array $sizes ): array {
		$parent = array(
			'ID'                  => '100',
			'Type'                => 'variable',
			'SKU'                 => 'P-100',
			'Name'                => 'Футболка',
			'Published'           => '1',
			'Description'         => '<p>Футболка</p>',
			'In stock?'           => '1',
			'Categories'          => 'Одяг > Футболки',
			'Images'              => 'https://shop.test/p.jpg',
			'Attribute 1 name'    => 'Розмір',
			'Attribute 1 value(s)' => implode( ', ', array_values( $sizes ) ),
			'Attribute 1 visible' => '1',
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
	 * Main offer + one offer per variation, all in one group.
	 *
	 * @return void
	 */
	public function test_variable_product_group() {
		$res = $this->process( $this->group_unit( array( 'S' => 'S', 'M' => 'M', 'L' => 'L' ) ) );

		$this->assertSame( 3, $res['exported'], 'The main offer is one of the variations, not an extra offer' );
		$this->assertSame( array(), $res['excluded'] );

		$xml = simplexml_load_string( '<offers>' . $res['xml'] . '</offers>' );

		$this->assertCount( 3, $xml->offer );

		$sizes = array();

		foreach ( $xml->offer as $offer ) {
			$this->assertSame( '100', (string) $offer['group_id'] );
			$this->assertSame( 1, count( $offer->param ), 'One size param per offer' );
			$sizes[] = (string) $offer->param;
		}

		sort( $sizes );
		$this->assertSame( array( 'L', 'M', 'S' ), $sizes, 'Every size appears exactly once' );
		$this->assertSame( '100', (string) $xml->offer[0]['id'], 'The group main offer carries the parent id' );
		$this->assertSame( '300', (string) $xml->offer[0]->price );
	}

	/**
	 * Prom rejects groups with identical characteristics; those are left out.
	 *
	 * @return void
	 */
	public function test_group_with_identical_variations_is_excluded() {
		$unit = $this->group_unit( array( 'A' => 'M', 'B' => 'M' ) );

		// Parent offers a single size so no axis can tell the two variations apart.
		$unit['parent']['Attribute 1 value(s)'] = 'M';

		$res = $this->process( $unit );

		$this->assertSame( 0, $res['exported'] );
		$this->assertSame( array( 'P-100' ), $res['excluded'] );
		$this->assertGreaterThan( 0, $res['skipped'] );
	}

	/**
	 * Unpublished variations are not exported.
	 *
	 * @return void
	 */
	public function test_unpublished_variations_are_skipped() {
		$unit                          = $this->group_unit( array( 'S' => 'S', 'M' => 'M', 'L' => 'L' ) );
		$unit['children'][1]['Published'] = '0';

		$res = $this->process( $unit );

		$this->assertSame( 2, $res['exported'] );
		$this->assertStringNotContainsString( '>M</param>', $res['xml'] );
	}

	// ---------------------------------------------------------------- registry / settings

	/**
	 * Prom is registered; other feeds can be added through the filter.
	 *
	 * @return void
	 */
	public function test_registry_and_extension_point() {
		$this->assertArrayHasKey( 'prom', Marketplace_Registry::all() );
		$this->assertInstanceOf( Prom_Feed::class, Marketplace_Registry::get( 'prom' ) );
		$this->assertNull( Marketplace_Registry::get( 'rozetka' ) );

		$rozetka = new class() extends Marketplace_Feed {
			public function id(): string {
				return 'rozetka';
			}
			public function label(): string {
				return 'Rozetka';
			}
			public function fields(): array {
				return array( 'shop_name' => array( 'type' => 'text', 'default' => 'X' ) );
			}
			public function head( array $settings ): string {
				return '';
			}
			public function tail(): string {
				return '';
			}
			public function process_unit( array $unit, \F2000CS\Marketplace_Category_Tree $tree, array $settings ): array {
				return array( 'xml' => '', 'exported' => 0, 'skipped' => 0, 'excluded' => array(), 'used_ids' => array() );
			}
		};

		Marketplace_Registry::reset();
		add_filter(
			'f2000cs_marketplace_feeds',
			static function ( $feeds ) use ( $rozetka ) {
				$feeds[ $rozetka->id() ] = $rozetka;

				return $feeds;
			}
		);

		$this->assertSame( array( 'prom', 'rozetka' ), array_keys( Marketplace_Registry::all() ) );
	}

	/**
	 * Settings are sanitized by the schema and stored without autoload.
	 *
	 * @return void
	 */
	public function test_settings_are_sanitized_and_defaults_applied() {
		$saved = Marketplace_Registry::save_settings(
			'prom',
			array(
				'shop_name'           => Marketplace_Form::CUSTOM,
				'max_pictures'        => Marketplace_Form::CUSTOM,
				'__custom'            => array( 'shop_name' => 'Shop', 'max_pictures' => '99' ),
				'include_unpublished' => '1',
				'vendor_aliases'      => "a=b\n\n  c=d  ",
				'schedule'            => 'every_second',
			)
		);

		$this->assertSame( 'Shop', $saved['shop_name'] );
		$this->assertSame( '10', $saved['max_pictures'], 'Capped to the schema maximum' );
		$this->assertSame( '1', $saved['include_unpublished'] );
		$this->assertSame( "a=b\nc=d", $saved['vendor_aliases'] );
		$this->assertSame( 'off', $saved['schedule'], 'Unknown schedules fall back to off' );

		$loaded = Marketplace_Registry::settings( 'prom' );

		$this->assertSame( 'Shop', $loaded['shop_name'] );
		$this->assertSame( 'f2000cs-updater-vendor', $loaded['mpn_meta_key'], 'Missing keys get defaults' );
	}

	// ---------------------------------------------------------------- storage

	/**
	 * Public file name carries a stable secret token.
	 *
	 * @return void
	 */
	public function test_storage_token_is_stable_and_in_the_url() {
		$feed = Marketplace_Registry::get( 'prom' );

		$token = Marketplace_Storage::token( 'prom' );

		$this->assertMatchesRegularExpression( '/^[a-z0-9]{24}$/', $token );
		$this->assertSame( $token, Marketplace_Storage::token( 'prom' ) );
		$this->assertStringEndsWith( "/f2000cs-feeds/prom-{$token}.xml", Marketplace_Storage::url( $feed ) );
	}

	/**
	 * Without Pro an outdated feed file is withdrawn, a fresh one is kept.
	 *
	 * @return void
	 */
	public function test_stale_feed_is_withdrawn_without_pro() {
		$path = Marketplace_Storage::path( Marketplace_Registry::get( 'prom' ) );

		file_put_contents( $path, '<yml_catalog/>' );
		$this->assertFalse( Marketplace_Job::withdraw_stale_feed( 'prom' ), 'A fresh feed must stay' );
		$this->assertFileExists( $path );

		touch( $path, time() - Marketplace_Job::STALE_AFTER - 60 );
		clearstatcache();
		$this->assertTrue( Marketplace_Job::withdraw_stale_feed( 'prom' ) );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Without Pro a stock update withdraws an outdated feed even when it has no schedule.
	 *
	 * @return void
	 */
	public function test_stock_update_withdraws_stale_feed_without_pro() {
		Marketplace_Registry::save_settings( 'prom', array( 'after_sync' => '1' ) );
		$path = Marketplace_Storage::path( Marketplace_Registry::get( 'prom' ) );
		file_put_contents( $path, '<yml_catalog/>' );
		touch( $path, time() - Marketplace_Job::STALE_AFTER - 60 );
		clearstatcache();

		do_action( 'f2000cs_stock_update_complete' );

		$this->assertFileDoesNotExist( $path );
		$this->assertFalse( wp_next_scheduled( Marketplace_Job::SYNC_HOOK, array( 'prom' ) ) );
	}

	/**
	 * Deactivation / uninstall clear marketplace events whatever their feed argument.
	 *
	 * @return void
	 */
	public function test_marketplace_cron_is_cleared() {
		wp_schedule_event( time(), 'daily', Marketplace_Job::CRON_HOOK, array( 'prom' ) );
		wp_schedule_single_event( time(), Marketplace_Job::CONT_HOOK, array( 'prom' ) );
		$this->assertTrue( f2000cs_has_marketplace_events() );

		f2000cs_clear_marketplace_cron();

		$this->assertFalse( f2000cs_has_marketplace_events() );
	}

	/**
	 * «Після оновлення наявності» is off by default and saved as a boolean.
	 *
	 * @return void
	 */
	public function test_after_sync_setting() {
		$this->assertFalse( Marketplace_Registry::settings( 'prom' )['after_sync'] );

		Marketplace_Registry::save_settings( 'prom', array( 'after_sync' => '1' ) );
		$this->assertTrue( Marketplace_Registry::settings( 'prom' )['after_sync'] );

		Marketplace_Registry::save_settings( 'prom', array( 'after_sync' => '0' ) );
		$this->assertFalse( Marketplace_Registry::settings( 'prom' )['after_sync'] );
	}

	/**
	 * A finished stock update queues one rebuild of the feeds that ask for it (Pro only).
	 *
	 * @return void
	 */
	public function test_stock_update_queues_feed_rebuild() {
		Marketplace_Registry::save_settings( 'prom', array( 'after_sync' => '1' ) );

		do_action( 'f2000cs_stock_update_complete' );
		$this->assertFalse( wp_next_scheduled( Marketplace_Job::SYNC_HOOK, array( 'prom' ) ), 'Free plan: no rebuild' );

		$this->enable_pro();
		do_action( 'f2000cs_stock_update_complete' );
		do_action( 'f2000cs_stock_update_complete' );

		$this->assertNotFalse( wp_next_scheduled( Marketplace_Job::SYNC_HOOK, array( 'prom' ) ) );
		$this->assertCount(
			1,
			array_filter(
				F2000CS_Test_State::$cron_events,
				static function ( $event ) {
					return Marketplace_Job::SYNC_HOOK === $event['hook'];
				}
			),
			'Repeated syncs must not stack rebuilds'
		);
		$this->assertTrue( has_filter( Marketplace_Job::SYNC_HOOK ) );
	}

	/**
	 * A requested rebuild that hits a running build is retried, not dropped.
	 *
	 * @return void
	 */
	public function test_locked_rebuild_is_retried() {
		$this->enable_pro();
		set_transient( 'f2000cs_mp_lock_prom', 1, 60 );

		Marketplace_Job::cron_build( 'prom' );

		$this->assertNotFalse( wp_next_scheduled( Marketplace_Job::SYNC_HOOK, array( 'prom' ) ) );
		delete_transient( 'f2000cs_mp_lock_prom' );
	}

	/**
	 * A locked continuation is not turned into a fresh rebuild, and a pending
	 * retry is not duplicated.
	 *
	 * @return void
	 */
	public function test_locked_retry_boundaries() {
		$this->enable_pro();
		set_transient( 'f2000cs_mp_lock_prom', 1, 60 );

		Marketplace_Job::cron_continue( 'prom' );
		$this->assertFalse( wp_next_scheduled( Marketplace_Job::SYNC_HOOK, array( 'prom' ) ), 'Continuation: the running build continues itself' );

		Marketplace_Job::cron_build( 'prom' );
		Marketplace_Job::cron_build( 'prom' );
		$retries = array_filter(
			F2000CS_Test_State::$cron_events,
			static function ( $event ) {
				return Marketplace_Job::SYNC_HOOK === $event['hook'];
			}
		);
		$this->assertCount( 1, $retries );

		delete_transient( 'f2000cs_mp_lock_prom' );
	}

	/**
	 * A category rule is a prefix of the path made of whole segments, not a substring.
	 *
	 * @return void
	 */
	public function test_portal_rule_matches_path_prefix_not_substring() {
		$settings = array(
			'portal_category_rules'      => "одяг=100\nодяг>куртки=200",
			'default_portal_category_id' => '999',
		);

		$this->assertSame( '100', Prom_Feed::portal_category_for_paths( array( array( 'Одяг' ) ), $settings ) );
		$this->assertSame( '100', Prom_Feed::portal_category_for_paths( array( array( 'Одяг', 'Штани' ) ), $settings ) );
		$this->assertSame( '999', Prom_Feed::portal_category_for_paths( array( array( 'Дитячий одяг' ) ), $settings ) );
		$this->assertSame( '999', Prom_Feed::portal_category_for_paths( array( array( 'Спорт', 'Спортивний одяг' ) ), $settings ) );
		$this->assertSame( '999', Prom_Feed::portal_category_for_paths( array( array( 'Одяг для дому' ) ), $settings ) );
	}

	/**
	 * Cyrillic and spaces in picture URLs are percent-encoded; existing escapes stay.
	 *
	 * @return void
	 */
	public function test_picture_urls_are_encoded() {
		$this->assertSame(
			'https://shop.test/wp-content/uploads/%D0%BA%D1%83%D1%80%D1%82%D0%BA%D0%B0%20a.jpg?x=1&y=%20',
			Marketplace_Text::encode_url( 'https://shop.test/wp-content/uploads/куртка a.jpg?x=1&y=%20' )
		);
		$this->assertSame( 'https://shop.test/a.jpg', Marketplace_Text::encode_url( 'https://shop.test/a.jpg' ) );
	}

	/**
	 * Fake source producing a fixed number of simple offers.
	 *
	 * @param int $count Offers.
	 * @return F2000CS_Fake_Unit_Source
	 */
	private function source_with_offers( int $count ): F2000CS_Fake_Unit_Source {
		$units = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$units[] = array(
				'kind' => 'simple',
				'row'  => $this->simple_row(
					array(
						'ID'  => (string) $i,
						'SKU' => 'S' . $i,
					)
				),
			);
		}

		return new F2000CS_Fake_Unit_Source( array( 'simple' => $units ) );
	}

	/**
	 * A build that suddenly has far fewer offers does not replace the published feed.
	 *
	 * @return void
	 */
	public function test_collapsed_build_does_not_replace_feed() {
		$this->enable_pro();
		$path = Marketplace_Storage::path( Marketplace_Registry::get( 'prom' ) );

		$ok = Marketplace_Job::run( 'prom', 30, $this->source_with_offers( 10 ) );
		$this->assertSame( 'done', $ok['status'] );
		$good = file_get_contents( $path );

		$bad = Marketplace_Job::run( 'prom', 30, $this->source_with_offers( 2 ) );
		$this->assertSame( 'error', $bad['status'] );
		$this->assertSame( $good, file_get_contents( $path ), 'Previous feed is kept' );
		$this->assertSame( 10, (int) get_option( Marketplace_Job::published_option( 'prom' ) ), 'Baseline is not lowered by a rejected build' );

		$smaller = Marketplace_Job::run( 'prom', 30, $this->source_with_offers( 6 ) );
		$this->assertSame( 'done', $smaller['status'], 'A moderate shrink is accepted' );
	}

	/**
	 * A sync during a running build flags it as dirty instead of restarting it.
	 *
	 * @return void
	 */
	public function test_rebuild_request_during_build_sets_dirty_flag() {
		$this->enable_pro();

		Marketplace_Job::start( Marketplace_Registry::get( 'prom' ) );
		$state           = Marketplace_Job::state( 'prom' );
		$state['offset'] = 40;
		update_option( Marketplace_Job::state_option( 'prom' ), $state, false );

		Marketplace_Job::cron_build( 'prom' );

		$this->assertSame( 40, (int) Marketplace_Job::state( 'prom' )['offset'], 'Progress is not reset' );
		$this->assertNotEmpty( get_option( Marketplace_Job::dirty_option( 'prom' ) ) );
		$this->assertNotFalse( wp_next_scheduled( Marketplace_Job::CONT_HOOK, array( 'prom' ) ) );
	}

	/**
	 * When a dirty build finishes, one more build is queued and a fresh start clears the flag.
	 *
	 * @return void
	 */
	public function test_dirty_build_queues_a_fresh_one() {
		$this->enable_pro();
		$feed = Marketplace_Registry::get( 'prom' );

		Marketplace_Job::start( $feed );
		update_option( Marketplace_Job::dirty_option( 'prom' ), time(), false );

		$res = Marketplace_Job::step( 'prom', 30, $this->source_with_offers( 3 ) );

		$this->assertSame( 'done', $res['status'] );
		$this->assertNotFalse( wp_next_scheduled( Marketplace_Job::SYNC_HOOK, array( 'prom' ) ) );

		Marketplace_Job::start( $feed );
		$this->assertFalse( get_option( Marketplace_Job::dirty_option( 'prom' ), false ) );
	}

	/**
	 * A source that returns a cursor is paged by it, so rows vanishing between steps skip nothing.
	 *
	 * @return void
	 */
	public function test_cursor_paging_survives_removed_rows() {
		$this->enable_pro();

		$source = new F2000CS_Fake_Cursor_Source( array( 3, 5, 8, 9, 12, 15, 21, 22, 30, 31, 40, 41, 50, 51, 52, 60, 61, 70, 71, 72, 80, 81, 90 ), array( 3, 5, 8 ) );

		$res = Marketplace_Job::run( 'prom', 30, $source );

		$this->assertSame( 'done', $res['status'] );
		$this->assertSame( array( 80, 81, 90 ), array_slice( $source->seen, -3 ), 'Nothing after the removed rows is skipped' );
		$this->assertCount( 23, $source->seen );
	}

	/**
	 * Shortcodes and block comments are dropped; plain-text line breaks survive as paragraphs.
	 *
	 * @return void
	 */
	public function test_description_cleanup() {
		$this->assertSame(
			'<p>Один [XL]</p><p>Два<br /> Три</p>',
			Prom_Feed::normalize_description_html( "[gallery ids=\"1,2\"]Один [XL]\n\nДва\nТри" )
		);
		$this->assertSame(
			'<p>Текст</p>',
			Prom_Feed::normalize_description_html( "<!-- wp:paragraph -->\n<p>Текст</p>\n<!-- /wp:paragraph -->" )
		);
	}

	/**
	 * A variation without a size gets the one size nobody else has.
	 *
	 * @return void
	 */
	public function test_size_hint_fills_the_single_free_size() {
		$unit = $this->group_unit( array( 'S' => 'S', 'M' => 'M', 'L' => 'L' ) );

		// The middle variation is "any size" and its title says nothing.
		$unit['children'][1]['Attribute 1 value(s)'] = '';
		$unit['children'][1]['Name']                 = 'Футболка';

		$hints = Prom_Variations::infer_size_hints( $unit['parent'], $unit['children'] );

		$this->assertSame( 'M', $hints['102'][1] );
		$this->assertArrayNotHasKey( '101', $hints, 'Own sizes need no hint' );
	}

	/**
	 * When the free sizes cannot be matched one to one, nothing is guessed.
	 *
	 * @return void
	 */
	public function test_size_hint_is_not_guessed_when_ambiguous() {
		$unit = $this->group_unit( array( 'S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL' ) );

		// Parent offers four sizes, one variation has S, two have none: three free sizes for two variations.
		foreach ( array( 1, 2 ) as $i ) {
			$unit['children'][ $i ]['Attribute 1 value(s)'] = '';
			$unit['children'][ $i ]['Name']                 = 'Футболка';
		}
		unset( $unit['children'][3] );

		$hints = Prom_Variations::infer_size_hints( $unit['parent'], array_values( $unit['children'] ) );

		$this->assertArrayNotHasKey( '102', $hints );
		$this->assertArrayNotHasKey( '103', $hints );
	}

	/**
	 * Skipped offers are counted by reason with example SKUs, and the totals reach the build result.
	 *
	 * @return void
	 */
	public function test_skip_reasons_are_reported() {
		$res = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Images' => '' ) ) ) );

		$this->assertSame( 1, $res['skipped'] );
		$this->assertSame( 1, $res['reasons']['no_pictures']['count'] );
		$this->assertSame( array( 'SKU-10' ), $res['reasons']['no_pictures']['examples'] );

		$next = $this->process( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Regular price' => '' ) ) ) );
		$this->assertSame( array( 'no_price' ), array_keys( $next['reasons'] ), 'Reasons do not leak between units' );

		$this->enable_pro();
		$units = array(
			array( 'kind' => 'simple', 'row' => $this->simple_row() ),
			array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'ID' => '11', 'SKU' => 'S-11', 'Categories' => '' ) ) ),
			array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'ID' => '12', 'SKU' => 'S-12', 'Categories' => '' ) ) ),
		);
		$built = Marketplace_Job::run( 'prom', 30, new F2000CS_Fake_Unit_Source( array( 'simple' => $units ) ) );

		$this->assertSame( 'done', $built['status'] );
		$this->assertSame( 2, $built['reasons']['no_category']['count'] );
		$this->assertSame( array( 'S-11', 'S-12' ), $built['reasons']['no_category']['examples'] );
		$this->assertArrayHasKey( 'no_category', ( new Prom_Feed() )->skip_reason_labels() );
	}

	/**
	 * Waiting for another process's build never starts a new one.
	 *
	 * @return void
	 */
	public function test_resume_only_reports_the_finished_build() {
		$this->enable_pro();

		$done = Marketplace_Job::run( 'prom', 30, $this->source_with_offers( 3 ) );
		$this->assertSame( 'done', $done['status'] );

		$res = Marketplace_Job::step( 'prom', 5, $this->source_with_offers( 1 ), false, true );

		$this->assertSame( 'done', $res['status'] );
		$this->assertSame( 3, $res['exported'], 'The result of the finished build, not a new build' );
		$this->assertSame( array(), Marketplace_Job::state( 'prom' ) );
	}

	/**
	 * «The shrink is expected» publishes the next build once, then the check is back.
	 *
	 * @return void
	 */
	public function test_accepted_drop_is_published_once() {
		$this->enable_pro();

		Marketplace_Job::run( 'prom', 30, $this->source_with_offers( 10 ) );
		$held = Marketplace_Job::run( 'prom', 30, $this->source_with_offers( 2 ) );
		$this->assertTrue( $held['drop'] );

		update_option( Marketplace_Job::accept_drop_option( 'prom' ), time(), false );
		$this->assertSame( 'done', Marketplace_Job::run( 'prom', 30, $this->source_with_offers( 2 ) )['status'] );
		$this->assertFalse( get_option( Marketplace_Job::accept_drop_option( 'prom' ), false ), 'One-off' );
		$this->assertSame( 2, (int) get_option( Marketplace_Job::published_option( 'prom' ) ) );
	}

	/**
	 * The working file is not guessable from the feed id.
	 *
	 * @return void
	 */
	public function test_build_file_name_carries_the_token() {
		$feed = Marketplace_Registry::get( 'prom' );

		$this->assertStringContainsString( Marketplace_Storage::token( 'prom' ), basename( Marketplace_Storage::build_path( $feed ) ) );
	}

	/**
	 * Withdrawal never creates the feeds folder or a token when no feed was built.
	 *
	 * @return void
	 */
	public function test_withdraw_without_feeds_folder_creates_nothing() {
		$dir = wp_upload_dir()['basedir'] . '/f2000cs-feeds';
		foreach ( (array) glob( $dir . '/{,.}*', GLOB_BRACE ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir );
		}

		$this->assertFalse( Marketplace_Job::withdraw_stale_feed( 'prom' ) );
		$this->assertFalse( Marketplace_Job::withdraw_stale_feed( 'no-such-feed' ) );
		$this->assertDirectoryDoesNotExist( $dir );
		$this->assertFalse( get_option( 'f2000cs_mp_token_prom', false ), 'No token created' );
	}

	/**
	 * Exactly at the limit the feed stays; one second later it goes.
	 *
	 * @return void
	 */
	public function test_withdraw_age_boundary() {
		$path = Marketplace_Storage::path( Marketplace_Registry::get( 'prom' ) );
		file_put_contents( $path, '<yml_catalog/>' );

		touch( $path, time() - Marketplace_Job::STALE_AFTER + 5 );
		clearstatcache();
		$this->assertFalse( Marketplace_Job::withdraw_stale_feed( 'prom' ) );

		touch( $path, time() - Marketplace_Job::STALE_AFTER - 1 );
		clearstatcache();
		$this->assertTrue( Marketplace_Job::withdraw_stale_feed( 'prom' ) );
	}

	/**
	 * A recurring build lost on deactivation is recreated from the saved schedule.
	 *
	 * @return void
	 */
	public function test_missing_schedule_is_recreated() {
		Marketplace_Registry::save_settings( 'prom', array( 'schedule' => 'daily' ) );
		$this->assertFalse( wp_next_scheduled( Marketplace_Job::CRON_HOOK, array( 'prom' ) ) );

		Marketplace_Job::ensure_schedules();
		$this->assertNotFalse( wp_next_scheduled( Marketplace_Job::CRON_HOOK, array( 'prom' ) ) );

		Marketplace_Registry::save_settings( 'prom', array( 'schedule' => 'off' ) );
		Marketplace_Job::reschedule( 'prom' );
		Marketplace_Job::ensure_schedules();
		$this->assertFalse( wp_next_scheduled( Marketplace_Job::CRON_HOOK, array( 'prom' ) ), 'Off stays off' );
	}

	/**
	 * Feeds without the option are not rebuilt after a stock update.
	 *
	 * @return void
	 */
	public function test_stock_update_skips_feeds_without_option() {
		$this->enable_pro();
		Marketplace_Registry::save_settings( 'prom', array( 'after_sync' => '0' ) );

		do_action( 'f2000cs_stock_update_complete' );

		$this->assertFalse( wp_next_scheduled( Marketplace_Job::SYNC_HOOK, array( 'prom' ) ) );
	}

	// ---------------------------------------------------------------- build job

	/**
	 * A chunked build produces one well-formed feed with its categories.
	 *
	 * @return void
	 */
	public function test_job_builds_a_complete_feed() {
		$feed = Marketplace_Registry::get( 'prom' );
		@unlink( Marketplace_Storage::path( $feed ) );

		Marketplace_Registry::save_settings(
			'prom',
			array(
				'shop_name' => Marketplace_Form::CUSTOM,
				'site_url'  => Marketplace_Form::CUSTOM,
				'currency'  => 'UAH',
				'__custom'  => array(
					'shop_name' => 'Test shop',
					'site_url'  => 'https://shop.test',
				),
			)
		);

		$source = new F2000CS_Fake_Unit_Source(
			array(
				'variable' => array( $this->group_unit( array( 'S' => 'S', 'M' => 'M' ) ) ),
				'simple'   => array(
					array( 'kind' => 'simple', 'row' => $this->simple_row() ),
					array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'ID' => '11', 'SKU' => 'SKU-11', 'Categories' => 'Взуття' ) ) ),
					array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'ID' => '12', 'Regular price' => '' ) ) ),
				),
			)
		);

		$result = Marketplace_Job::run( 'prom', 30, $source, true );

		$this->assertSame( 'done', $result['status'], (string) ( $result['message'] ?? '' ) );
		$this->assertSame( 4, $result['exported'] );
		$this->assertSame( 1, $result['skipped'] );

		$path = Marketplace_Storage::path( $feed );

		$this->assertFileExists( $path );
		$this->assertTrue( Marketplace_Job::is_well_formed( $path ) );
		$this->assertSame( array(), Marketplace_Job::state( 'prom' ), 'State is cleared after a build' );

		$doc = simplexml_load_file( $path );

		$this->assertSame( 'Test shop', (string) $doc->shop->name );
		$this->assertCount( 4, $doc->shop->offers->offer );
		$this->assertGreaterThanOrEqual( 4, count( $doc->shop->categories->category ) );

		$category_ids = array();
		foreach ( $doc->shop->categories->category as $cat ) {
			$category_ids[] = (string) $cat['id'];
		}
		foreach ( $doc->shop->offers->offer as $offer ) {
			$this->assertContains( (string) $offer->categoryId, $category_ids, 'Every offer points to an existing category' );
		}

		$this->assertSame( 4, Marketplace_Job::last_result( 'prom' )['exported'] );
	}

	/**
	 * An empty result never replaces the published feed.
	 *
	 * @return void
	 */
	public function test_empty_build_keeps_the_previous_feed() {
		$feed = Marketplace_Registry::get( 'prom' );
		$path = Marketplace_Storage::path( $feed );

		file_put_contents( $path, '<previous/>' );

		$source = new F2000CS_Fake_Unit_Source( array( 'simple' => array( array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'Regular price' => '' ) ) ) ) ) );
		$result = Marketplace_Job::run( 'prom', 30, $source, true );

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( '<previous/>', file_get_contents( $path ) );
	}

	/**
	 * A second step while one is running reports "locked".
	 *
	 * @return void
	 */
	public function test_concurrent_step_is_locked_out() {
		set_transient( 'f2000cs_mp_lock_prom', 1, 60 );

		$result = Marketplace_Job::step( 'prom', 5, new F2000CS_Fake_Unit_Source( array( 'simple' => array() ) ) );

		$this->assertSame( 'locked', $result['status'] );

		delete_transient( 'f2000cs_mp_lock_prom' );
	}

	/**
	 * A slice with a tiny budget leaves resumable state.
	 *
	 * @return void
	 */
	public function test_build_is_resumable() {
		$units = array();

		for ( $i = 1; $i <= 45; $i++ ) {
			$units[] = array( 'kind' => 'simple', 'row' => $this->simple_row( array( 'ID' => (string) ( 1000 + $i ), 'SKU' => 'S' . $i ) ) );
		}

		$source = new F2000CS_Fake_Unit_Source( array( 'simple' => $units ) );

		// Budget 0 → the loop exits after the first page is not guaranteed; run steps until done.
		$result = Marketplace_Job::step( 'prom', 1, $source, true );
		$steps  = 1;

		while ( 'running' === $result['status'] && $steps < 50 ) {
			$result = Marketplace_Job::step( 'prom', 1, $source );
			++$steps;
		}

		$this->assertSame( 'done', $result['status'] );
		$this->assertSame( 45, $result['exported'] );
	}
}
