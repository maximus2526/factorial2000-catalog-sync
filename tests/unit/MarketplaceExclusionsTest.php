<?php
/**
 * «Не вивантажувати» rules shared by every marketplace feed.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Google_Merchant_Feed;
use F2000CS\Marketplace_Feed;
use F2000CS\Prom_Feed;

/**
 * Exclusion rules tests.
 */
final class F2000CS_Unit_MarketplaceExclusionsTest extends F2000CS_Unit_TestCase {

	/**
	 * Product row.
	 *
	 * @param array<string,string> $extra Overrides.
	 * @return array<string,string>
	 */
	private function row( array $extra = array() ): array {
		return array_merge(
			array(
				'ID'                   => '10',
				'SKU'                  => 'VT_26169',
				'Name'                 => 'Аптечка MIL-TEC Mini Pack',
				'Categories'           => 'Медицина > Аптечки',
				'Attribute 1 name'     => 'Виробник',
				'Attribute 1 value(s)' => 'Mil-Tec',
				'Attribute 2 name'     => 'Колір',
				'Attribute 2 value(s)' => 'Червоний, Чорний',
			),
			$extra
		);
	}

	/**
	 * Rules are read in Ukrainian, Russian and English; bad lines are ignored.
	 *
	 * @return void
	 */
	public function test_parse_rules() {
		$rules = Marketplace_Feed::parse_exclusion_rules( "бренд: Mil-Tec\nКатегорія: Одяг > Куртки\nsku: AR_\nатрибут: Колір = Червоний\nназва: ЛЛ7192\nid: 5\nрозмір: XL\nбез двокрапки\nатрибут: без знака\nбренд:   " );

		$this->assertSame(
			array(
				array( 'type' => 'brand', 'value' => 'mil-tec' ),
				array( 'type' => 'category', 'value' => 'одяг>куртки' ),
				array( 'type' => 'sku', 'value' => 'ar_' ),
				array( 'type' => 'attribute', 'value' => 'червоний', 'attr' => 'колір' ),
				array( 'type' => 'name', 'value' => 'лл7192' ),
				array( 'type' => 'id', 'value' => '5' ),
			),
			$rules
		);
	}

	/**
	 * Each rule type matches what it should, case-insensitively, and nothing else.
	 *
	 * @return void
	 */
	public function test_row_rules() {
		$row = $this->row();
		$hit = static function ( string $rules, array $row, $parent = null ): bool {
			return Marketplace_Feed::row_excluded( Marketplace_Feed::parse_exclusion_rules( $rules ), $row, $parent );
		};

		$this->assertTrue( $hit( 'бренд: mil-tec', $row ), 'Brand from the «Виробник» attribute' );
		$this->assertTrue( $hit( 'бренд: Kiborg', $this->row( array( 'Brands' => 'Без бренду, Kiborg' ) ) ), 'One of combined brands' );
		$this->assertFalse( $hit( 'бренд: Mil', $row ), 'Whole brand only' );
		$this->assertTrue( $hit( 'категорія: медицина', $row ), 'A category takes its subcategories' );
		$this->assertTrue( $hit( 'категорія: Медицина>Аптечки', $row ) );
		$this->assertFalse( $hit( 'категорія: Мед', $row ), 'Whole segments only' );
		$this->assertTrue( $hit( 'артикул: vt_', $row ), 'SKU prefix' );
		$this->assertFalse( $hit( 'артикул: 26169', $row ), 'Prefix, not substring' );
		$this->assertTrue( $hit( 'атрибут: колір = чорний', $row ), 'One of the attribute values' );
		$this->assertFalse( $hit( 'атрибут: колір = синій', $row ) );
		$this->assertTrue( $hit( 'назва: mil-tec', $row ) );
		$this->assertTrue( $hit( 'id: 10', $row ) );
		$this->assertFalse( $hit( 'id: 1', $row ) );
	}

	/**
	 * A product that falls under a rule goes with its variations; a variation goes alone.
	 *
	 * @return void
	 */
	public function test_group_units() {
		$feed   = new Prom_Feed();
		$parent = $this->row( array( 'ID' => '100', 'SKU' => 'P-100' ) );
		$unit   = array(
			'kind'     => 'group',
			'parent'   => $parent,
			'children' => array(
				array( 'ID' => '101', 'SKU' => 'P-100-S', 'Attribute 1 name' => 'Розмір', 'Attribute 1 value(s)' => 'S' ),
				array( 'ID' => '102', 'SKU' => 'P-100-M', 'Attribute 1 name' => 'Розмір', 'Attribute 1 value(s)' => 'M' ),
			),
		);

		list( $left, $dropped, $why ) = $feed->apply_exclusions( $unit, array( 'exclude_rules' => 'бренд: Mil-Tec' ) );
		$this->assertNull( $left, 'The whole product goes' );
		$this->assertSame( 2, $dropped );
		$this->assertSame( array( 'P-100' ), $why['excluded']['examples'] );

		list( $left, $dropped ) = $feed->apply_exclusions( $unit, array( 'exclude_rules' => 'атрибут: Розмір = M' ) );
		$this->assertSame( 1, $dropped );
		$this->assertCount( 1, $left['children'] );
		$this->assertSame( '101', $left['children'][0]['ID'] );

		list( $left, $dropped ) = $feed->apply_exclusions( $unit, array( 'exclude_rules' => 'бренд: Kiborg' ) );
		$this->assertSame( $unit, $left, 'Nothing matches: the unit is untouched' );
		$this->assertSame( 0, $dropped );

		list( $left, $dropped ) = $feed->apply_exclusions( $unit, array( 'exclude_rules' => '' ) );
		$this->assertSame( $unit, $left );
	}

	/**
	 * Simple products and lone variations.
	 *
	 * @return void
	 */
	public function test_simple_and_lone_units() {
		$feed = new Google_Merchant_Feed();

		list( $left, $dropped ) = $feed->apply_exclusions( array( 'kind' => 'simple', 'row' => $this->row() ), array( 'exclude_rules' => 'бренд: Mil-Tec' ) );
		$this->assertNull( $left );
		$this->assertSame( 1, $dropped );

		$lone = array(
			'kind'   => 'lone',
			'row'    => array( 'ID' => '7', 'SKU' => 'X-7' ),
			'parent' => $this->row(),
		);
		list( $left ) = $feed->apply_exclusions( $lone, array( 'exclude_rules' => 'категорія: Медицина' ) );
		$this->assertNull( $left, 'A lone variation follows its product' );
	}

	/**
	 * Every feed has the setting, in its own section.
	 *
	 * @return void
	 */
	public function test_every_feed_has_the_setting() {
		foreach ( array( new Prom_Feed(), new Google_Merchant_Feed() ) as $feed ) {
			$fields = $feed->settings_fields();

			$this->assertArrayHasKey( 'exclude_rules', $fields );
			$this->assertArrayHasKey( 'exclude', $feed->settings_groups() );
			$this->assertSame( '', $feed->with_defaults( array() )['exclude_rules'] );
			$this->assertSame( "бренд: Mil-Tec", $feed->sanitize( array( 'exclude_rules' => "бренд: Mil-Tec" ) )['exclude_rules'] );
		}
	}
}
