<?php
/**
 * Prom variation param sets must be uniform inside a group.
 *
 * Regression test for the Prom import errors
 * "різновид не буде імпортовано: відсутні характеристики ..." (298 positions)
 * "різновиди побудовані на різних характеристиках" (457 positions):
 * the feed dropped variation params equal to the parent display values,
 * so sibling offers ended up with different characteristic name sets
 * ({Колір} vs {Колір, Розмір} vs {Розмір}) and Prom rejected whole groups.
 *
 * Fixture mirrors a real group (id 42963): variations carry only the colour
 * attribute, sizes are inferred by position, names carry no sizes.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Category_Tree;
use F2000CS\Marketplace_Registry;
use F2000CS\Prom_Feed;
use F2000CS\Prom_Variations;

/**
 * Variation param-set uniformity tests.
 */
final class F2000CS_Unit_PromVariationSetsTest extends F2000CS_Unit_TestCase {

	/**
	 * Settings used by the feed.
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
	 * Parent row with colour and size axes.
	 *
	 * @param array<string,string> $extra Overrides.
	 * @return array<string,string>
	 */
	private function parent_row( array $extra = array() ): array {
		return array_merge(
			array(
				'ID'                   => '42963',
				'Type'                 => 'variable',
				'SKU'                  => 'P-42963',
				'Name'                 => 'Комплект',
				'Published'            => '1',
				'Description'          => '<p>Комплект</p>',
				'In stock?'            => '1',
				'Categories'           => 'Одяг > Комплекти',
				'Images'               => 'https://shop.test/p.jpg',
				'Attribute 1 name'     => 'Колір',
				'Attribute 1 value(s)' => 'Чорний, Койот',
				'Attribute 1 visible'  => '1',
				'Attribute 2 name'     => 'Міжнародний розмір',
				'Attribute 2 value(s)' => 'S, M',
				'Attribute 2 visible'  => '1',
			),
			$extra
		);
	}

	/**
	 * Variation row. By default colour-only, like the live catalog.
	 *
	 * @param string      $id    Variation id.
	 * @param string      $color Colour term name.
	 * @param string|null $size  Size value or null (no size attribute).
	 * @return array<string,string>
	 */
	private function variation_row( string $id, string $color, ?string $size = null ): array {
		$row = array(
			'ID'                   => $id,
			'Type'                 => 'variation',
			'SKU'                  => 'P-' . $id,
			'Name'                 => 'Комплект - ' . $color,
			'Published'            => '1',
			'In stock?'            => '1',
			'Regular price'        => '300',
			'Attribute 1 name'     => 'Колір',
			'Attribute 1 value(s)' => $color,
			'Attribute 1 visible'  => '1',
		);

		if ( null !== $size ) {
			$row['Attribute 2 name']     = 'Міжнародний розмір';
			$row['Attribute 2 value(s)'] = $size;
			$row['Attribute 2 visible']  = '1';
		}

		return $row;
	}

	/**
	 * Run one group unit through the Prom feed.
	 *
	 * @param array $children Variation rows.
	 * @return array
	 */
	private function process_group( array $children ): array {
		$unit = array(
			'kind'     => 'group',
			'parent'   => $this->parent_row(),
			'children' => $children,
		);

		return ( new Prom_Feed() )->process_unit( $unit, new Marketplace_Category_Tree(), $this->settings );
	}

	/**
	 * Param name sets of the variation offers (main offer excluded).
	 *
	 * @param string $xml      Feed xml fragment.
	 * @param string $group_id Group id.
	 * @return array<int, array<int,string>>
	 */
	private function variation_param_sets( string $xml, string $group_id ): array {
		$offers = simplexml_load_string( '<offers>' . $xml . '</offers>' );
		$sets   = array();

		foreach ( $offers->offer as $offer ) {
			if ( (string) $offer['id'] === $group_id ) {
				continue;
			}

			$names = array();

			foreach ( $offer->param as $param ) {
				$names[] = (string) $param['name'];
			}

			sort( $names );
			$sets[] = $names;
		}

		return $sets;
	}

	/**
	 * Every variation offer carries the same characteristic names.
	 *
	 * @param array<int, array<int,string>> $sets Param name sets.
	 * @return void
	 */
	private function assert_uniform_sets( array $sets ): void {
		$this->assertNotEmpty( $sets, 'Group has variation offers' );

		$first = $sets[0];

		foreach ( $sets as $i => $names ) {
			$this->assertSame( $first, $names, "Variation #$i has a different characteristic set" );
		}
	}

	/**
	 * Colour-only variations (live catalog shape): every offer must still
	 * carry both characteristics.
	 *
	 * @return void
	 */
	public function test_multicolor_group_emits_uniform_param_sets() {
		$res = $this->process_group(
			array(
				$this->variation_row( '42971', 'Чорний' ),
				$this->variation_row( '42973', 'Чорний' ),
				$this->variation_row( '42975', 'Койот' ),
				$this->variation_row( '42977', 'Койот' ),
			)
		);

		$this->assertSame( array(), $res['excluded'], 'Group must not be skipped' );

		$sets = $this->variation_param_sets( $res['xml'], '42963' );

		$this->assertCount( 3, $sets, 'Main offer reuses one variation' );
		$this->assert_uniform_sets( $sets );
		$this->assertSame( array( 'Колір', 'Міжнародний розмір' ), $sets[0] );
	}

	/**
	 * Even with size attributes populated on the variations, the emitted
	 * sets must stay uniform (data fix alone must not be required).
	 *
	 * @return void
	 */
	public function test_group_with_size_attributes_stays_uniform() {
		$res = $this->process_group(
			array(
				$this->variation_row( '42971', 'Чорний', 'S' ),
				$this->variation_row( '42973', 'Чорний', 'M' ),
				$this->variation_row( '42975', 'Койот', 'S' ),
				$this->variation_row( '42977', 'Койот', 'M' ),
			)
		);

		$this->assertSame( array(), $res['excluded'], 'Group must not be skipped' );

		$sets = $this->variation_param_sets( $res['xml'], '42963' );

		$this->assertCount( 3, $sets, 'Main offer reuses one variation' );
		$this->assert_uniform_sets( $sets );
		$this->assertSame( array( 'Колір', 'Міжнародний розмір' ), $sets[0] );
	}

	/**
	 * Single-colour group keeps working: uniform sets, no skipped group.
	 *
	 * @return void
	 */
	public function test_single_color_group_stays_consistent() {
		$res = $this->process_group(
			array(
				$this->variation_row( '42971', 'Чорний' ),
				$this->variation_row( '42973', 'Чорний' ),
			)
		);

		$this->assertSame( array(), $res['excluded'], 'Group must not be skipped' );

		$sets = $this->variation_param_sets( $res['xml'], '42963' );

		$this->assertCount( 1, $sets, 'Main offer reuses one variation' );
		$this->assert_uniform_sets( $sets );
	}
}
