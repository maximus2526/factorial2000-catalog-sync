<?php
/**
 * Prom category list, the «Категорія Prom» picker and how the feed uses it.
 *
 * Prom: <portal_category_id> on an offer outranks the group's portal_id, so the
 * product-level field must win over the category rules and the default.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Category_Tree;
use F2000CS\Marketplace_WC_Source;
use F2000CS\Prom_Categories;
use F2000CS\Prom_Feed;

/**
 * Tests.
 */
final class F2000CS_Unit_PromCategoriesTest extends F2000CS_Unit_TestCase {

	/**
	 * Load the admin glue once.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/admin/prom-category-field.php';
		Prom_Categories::reset();
	}

	/**
	 * Back to the bundled list.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Prom_Categories::reset();
		parent::tearDown();
	}

	/**
	 * Feed settings with rules and a default.
	 *
	 * @return array<string,mixed>
	 */
	private function settings(): array {
		return array(
			'shop_name'                  => 'Shop',
			'company_name'               => 'Shop',
			'site_url'                   => 'https://shop.test',
			'currency'                   => 'UAH',
			'max_pictures'               => 10,
			'portal_category_rules'      => 'одяг=555',
			'default_portal_category_id' => '999',
		);
	}

	/**
	 * Simple product row.
	 *
	 * @param array<string,string> $extra Overrides.
	 * @return array<string,string>
	 */
	private function row( array $extra = array() ): array {
		return array_merge(
			array(
				'ID'            => '10',
				'SKU'           => 'S-10',
				'Name'          => 'Куртка',
				'Published'     => '1',
				'In stock?'     => '1',
				'Regular price' => '100',
				'Categories'    => 'Одяг > Куртки',
				'Images'        => 'https://shop.test/a.jpg',
			),
			$extra
		);
	}

	/**
	 * Portal category of each offer in the produced XML.
	 *
	 * @param array $unit Unit.
	 * @return array<string,string> offer id => portal_category_id ('' when absent).
	 */
	private function portal_ids( array $unit ): array {
		$res = ( new Prom_Feed() )->process_unit( $unit, new Marketplace_Category_Tree(), $this->settings() );
		$xml = simplexml_load_string( '<offers>' . $res['xml'] . '</offers>' );
		$out = array();

		foreach ( $xml->offer as $offer ) {
			$out[ (string) $offer['id'] ] = (string) $offer->portal_category_id;
		}

		return $out;
	}

	// --------------------------------------------------------- bundled list

	/**
	 * The shipped file is Prom's list: thousands of unique numeric ids with paths.
	 *
	 * @return void
	 */
	public function test_bundled_list_is_complete(): void {
		$all = Prom_Categories::all();

		$this->assertGreaterThan( 5000, count( $all ) );

		foreach ( $all as $id => $path ) {
			$this->assertMatchesRegularExpression( '/^\d+$/', (string) $id );
			$this->assertNotSame( '', $path );
		}

		$this->assertSame( 'Одяг та взуття > Жіночий одяг > Жіночий верхній одяг > Жіночі куртки', Prom_Categories::path( '31302' ) );
		$this->assertSame( 'Товари, загальне', Prom_Categories::path( 29 ) );
		$this->assertTrue( Prom_Categories::exists( '31302' ) );
		$this->assertFalse( Prom_Categories::exists( '1' ) );
		$this->assertSame( '', Prom_Categories::path( '' ) );
	}

	/**
	 * Words match in any order and case; the category's own name ranks first.
	 *
	 * @return void
	 */
	public function test_search_by_words(): void {
		$hits = Prom_Categories::search( 'КУРТКИ жіночі' );

		$this->assertNotEmpty( $hits );
		$this->assertSame( '31302', $hits[0]['id'] );

		foreach ( $hits as $hit ) {
			$lower = mb_strtolower( $hit['path'] );
			$this->assertStringContainsString( 'куртки', $lower );
			$this->assertStringContainsString( 'жіноч', $lower );
		}

		$this->assertCount( 3, Prom_Categories::search( 'одяг', 3 ), 'The limit is respected' );
		$this->assertSame( array(), Prom_Categories::search( '   ' ) );
		$this->assertSame( array(), Prom_Categories::search( 'zzzzнемаєтакого' ) );
	}

	/**
	 * A number finds categories by the beginning of their id, the exact one first.
	 *
	 * @return void
	 */
	public function test_search_by_id(): void {
		$hits = Prom_Categories::search( '29' );

		$this->assertSame( '29', $hits[0]['id'] );

		foreach ( $hits as $hit ) {
			$this->assertStringStartsWith( '29', $hit['id'] );
		}
	}

	/**
	 * Another list can be plugged in; comments, bad lines and blanks are ignored.
	 *
	 * @return void
	 */
	public function test_file_filter_and_parsing(): void {
		$file = tempnam( sys_get_temp_dir(), 'f2000cs' );
		file_put_contents( $file, "# comment\n10\tA > B\n\nbad line\nx1\tNot a number\n20\tC\n" );

		add_filter(
			'f2000cs_prom_categories_file',
			static function () use ( $file ) {
				return $file;
			}
		);
		Prom_Categories::reset();

		$this->assertSame( array( 10 => 'A > B', 20 => 'C' ), Prom_Categories::all() );

		unset( F2000CS_Test_State::$hooks['f2000cs_prom_categories_file'] );
		unlink( $file );
	}

	/**
	 * Only digits are kept.
	 *
	 * @return void
	 */
	public function test_sanitize_id(): void {
		$this->assertSame( '31302', Prom_Categories::sanitize_id( ' 31 302 ' ) );
		$this->assertSame( '', Prom_Categories::sanitize_id( 'Жіночі куртки' ) );
		$this->assertSame( '5', Prom_Categories::sanitize_id( '<b>5</b>' ) );
	}

	// ------------------------------------------------------------- feed

	/**
	 * Product field > category rule > default.
	 *
	 * @return void
	 */
	public function test_product_field_outranks_rules_and_default(): void {
		$own = $this->portal_ids(
			array(
				'kind' => 'simple',
				'row'  => $this->row( array( Marketplace_WC_Source::PROM_CATEGORY_COLUMN => '31302' ) ),
			)
		);
		$this->assertSame( array( '10' => '31302' ), $own );

		$rule = $this->portal_ids( array( 'kind' => 'simple', 'row' => $this->row() ) );
		$this->assertSame( array( '10' => '555' ), $rule );

		$default = $this->portal_ids( array( 'kind' => 'simple', 'row' => $this->row( array( 'Categories' => 'Інше' ) ) ) );
		$this->assertSame( array( '10' => '999' ), $default );
	}

	/**
	 * Every offer of a variable product (main one and variations) takes the parent's field.
	 *
	 * @return void
	 */
	public function test_variations_inherit_the_parent_field(): void {
		$parent = array(
			'ID'                                       => '100',
			'Type'                                     => 'variable',
			'SKU'                                      => 'P-100',
			'Name'                                     => 'Футболка',
			'Published'                                => '1',
			'In stock?'                                => '1',
			'Categories'                               => 'Одяг > Футболки',
			'Images'                                   => 'https://shop.test/p.jpg',
			'Attribute 1 name'                         => 'Розмір',
			'Attribute 1 value(s)'                     => 'S, M, L',
			'Attribute 1 visible'                      => '1',
			Marketplace_WC_Source::PROM_CATEGORY_COLUMN => '31302',
		);
		$children = array();

		foreach ( array( 'S', 'M', 'L' ) as $i => $size ) {
			$children[] = array(
				'ID'                                       => (string) ( 101 + $i ),
				'Type'                                     => 'variation',
				'SKU'                                      => 'P-100-' . $size,
				'Name'                                     => 'Футболка - ' . $size,
				'Published'                                => '1',
				'In stock?'                                => '1',
				'Regular price'                            => '300',
				'Parent'                                   => 'P-100',
				'Attribute 1 name'                         => 'Розмір',
				'Attribute 1 value(s)'                     => $size,
				'Attribute 1 visible'                      => '1',
				Marketplace_WC_Source::PROM_CATEGORY_COLUMN => '',
			);
		}

		$ids = $this->portal_ids(
			array(
				'kind'     => 'group',
				'parent'   => $parent,
				'children' => $children,
			)
		);

		$this->assertCount( 3, $ids );
		$this->assertSame( array( '31302' ), array_values( array_unique( $ids ) ), 'Main offer and variations use the parent field' );
	}

	/**
	 * A stray non-digit value never reaches the XML.
	 *
	 * @return void
	 */
	public function test_feed_keeps_only_digits(): void {
		$this->assertSame(
			'31302',
			Prom_Feed::portal_category_for_row( array( Marketplace_WC_Source::PROM_CATEGORY_COLUMN => ' 31302 ' ), null, array(), array() )
		);
		$this->assertSame(
			'',
			Prom_Feed::portal_category_for_row( array( Marketplace_WC_Source::PROM_CATEGORY_COLUMN => 'abc' ), null, array(), array() )
		);
	}

	/**
	 * The default category setting is a picker and keeps digits only.
	 *
	 * @return void
	 */
	public function test_default_category_setting_is_sanitized(): void {
		$feed  = new Prom_Feed();
		$saved = $feed->sanitize( array( 'default_portal_category_id' => '31302 Жіночі куртки' ) );

		$this->assertSame( 'prom_category', $feed->fields()['default_portal_category_id']['type'] );
		$this->assertSame( '31302', $saved['default_portal_category_id'] );
	}

	// ------------------------------------------------------------ admin

	/**
	 * The picker shows the stored id and its Prom path, or a warning for an unknown id.
	 *
	 * @return void
	 */
	public function test_picker_renders_path_and_unknown_warning(): void {
		$known = $this->capture(
			static function () {
				f2000cs_prom_category_input( 'mp[x]', '31302' );
			}
		);

		$this->assertStringContainsString( '<select name="mp[x]" class="f2000cs-prom-cat"', $known );
		$this->assertStringContainsString( '<option value="31302" selected data-unknown="0">', $known );
		$this->assertStringContainsString( 'Жіночі куртки', $known );
		$this->assertStringContainsString( '(#31302)', $known );

		$unknown = $this->capture(
			static function () {
				f2000cs_prom_category_input( 'mp[x]', '1' );
			}
		);

		$this->assertStringContainsString( 'data-unknown="1"', $unknown );
		$this->assertStringContainsString( '#1', $unknown );

		$empty = $this->capture(
			static function () {
				f2000cs_prom_category_input( 'mp[x]', '' );
			}
		);

		$this->assertStringContainsString( '<option value=""></option>', $empty );
		$this->assertStringNotContainsString( 'selected', $empty );
	}

	/**
	 * The category table on the Marketplaces page uses the picker and keeps the posted names.
	 *
	 * @return void
	 */
	public function test_category_table_uses_the_picker(): void {
		require_once dirname( __DIR__, 2 ) . '/admin/page-marketplaces.php';

		$html = $this->capture(
			static function () {
				f2000cs_marketplace_render_field( 'default_portal_category_id', ( new Prom_Feed() )->fields()['default_portal_category_id'], '31302' );
			}
		);

		$this->assertStringContainsString( 'name="mp[default_portal_category_id]"', $html );
		$this->assertStringContainsString( 'f2000cs-prom-cat', $html );
		$this->assertStringContainsString( 'Жіночі куртки', $html );
	}

	/**
	 * AJAX search returns matches; a bad nonce is refused.
	 *
	 * @return void
	 */
	public function test_ajax_search(): void {
		$_GET['q'] = 'жіночі куртки';

		try {
			f2000cs_prom_category_ajax_search();
			$this->fail( 'A JSON response was expected' );
		} catch ( F2000CS_JsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertSame( '31302', $e->data[0]['id'] );
		}

		F2000CS_Test_State::$can_manage_options = false;

		try {
			f2000cs_prom_category_ajax_search();
			$this->fail( 'A JSON response was expected' );
		} catch ( F2000CS_JsonResponseException $e ) {
			$this->assertFalse( $e->success, 'Users who cannot edit products get nothing' );
		}
	}

	/**
	 * The product box saves digits, clears an empty value and ignores requests without a nonce.
	 *
	 * @return void
	 */
	public function test_product_box_save(): void {
		$key = Marketplace_WC_Source::PROM_CATEGORY_META;

		$_POST = array(
			'f2000cs_prom_category_nonce' => 'n',
			'f2000cs_prom_category_id'    => ' 31302 ',
		);
		f2000cs_prom_category_save( 7 );
		$this->assertSame( '31302', get_post_meta( 7, $key, true ) );

		$_POST['f2000cs_prom_category_id'] = '';
		f2000cs_prom_category_save( 7 );
		$this->assertSame( '', (string) get_post_meta( 7, $key, true ) );

		$_POST = array( 'f2000cs_prom_category_id' => '29' );
		f2000cs_prom_category_save( 7 );
		$this->assertSame( '', (string) get_post_meta( 7, $key, true ), 'No nonce, no save (e.g. quick edit)' );

		F2000CS_Test_State::$nonce_valid = false;
		$_POST                           = array(
			'f2000cs_prom_category_nonce' => 'n',
			'f2000cs_prom_category_id'    => '29',
		);
		f2000cs_prom_category_save( 7 );
		$this->assertSame( '', (string) get_post_meta( 7, $key, true ) );
	}
}
