<?php
/**
 * Product filter rules shared by every marketplace feed.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Google_Merchant_Feed;
use F2000CS\Marketplace_Feed;
use F2000CS\Prom_Feed;

/**
 * Product filter tests.
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
				'Tags'                 => 'тактика, медицина',
				'In stock?'            => '1',
				'Regular price'        => '500',
				'Sale price'           => '',
				'Attribute 1 name'     => 'Виробник',
				'Attribute 1 value(s)' => 'Mil-Tec',
				'Attribute 2 name'     => 'Колір',
				'Attribute 2 value(s)' => 'Червоний, Чорний',
			),
			$extra
		);
	}

	/**
	 * One rule.
	 *
	 * @param string $field  Field.
	 * @param string $op     Condition.
	 * @param string $value  Value.
	 * @param string $action Action.
	 * @param string $attr   Attribute name.
	 * @return array
	 */
	private function rule( string $field, string $op, string $value, string $action = 'exclude', string $attr = '' ): array {
		return array(
			'action' => $action,
			'field'  => $field,
			'attr'   => $attr,
			'op'     => $op,
			'value'  => $value,
		);
	}

	/**
	 * Conditions per field.
	 *
	 * @return array<string,array{0:array,1:bool}>
	 */
	public static function conditions(): array {
		return array(
			'brand is (from «Виробник»)'     => array( array( 'brand', 'is', 'mil-tec' ), true ),
			'brand is one of a list'          => array( array( 'brand', 'is', 'Kiborg, Mil-Tec' ), true ),
			'brand is: whole brand only'      => array( array( 'brand', 'is', 'Mil' ), false ),
			'brand is not'                    => array( array( 'brand', 'is_not', 'Mil-Tec' ), false ),
			'brand contains'                  => array( array( 'brand', 'contains', 'tec' ), true ),
			'category is (with subcats)'      => array( array( 'category', 'is', 'Медицина' ), true ),
			'category is: exact path'         => array( array( 'category', 'is', 'медицина > аптечки' ), true ),
			'category is: whole segments'     => array( array( 'category', 'is', 'Мед' ), false ),
			'category is not'                 => array( array( 'category', 'is_not', 'Одяг' ), true ),
			'sku starts with'                 => array( array( 'sku', 'starts_with', 'vt_' ), true ),
			'sku starts with: not substring'  => array( array( 'sku', 'starts_with', '26169' ), false ),
			'sku contains'                    => array( array( 'sku', 'contains', '2616' ), true ),
			'name contains'                   => array( array( 'name', 'contains', 'mil-tec' ), true ),
			'name not contains'               => array( array( 'name', 'not_contains', 'mil-tec' ), false ),
			'name contains a comma phrase'    => array( array( 'name', 'contains', 'Mini Pack' ), true ),
			'tag is'                          => array( array( 'tag', 'is', 'тактика' ), true ),
			'price greater'                   => array( array( 'price', 'gt', '499.99' ), true ),
			'price less'                      => array( array( 'price', 'lt', '500' ), false ),
			'stock is out'                    => array( array( 'stock', 'is', 'outofstock' ), false ),
			'stock is in'                     => array( array( 'stock', 'is', 'instock' ), true ),
			'id is'                           => array( array( 'id', 'is', '10' ), true ),
			'id is: not a prefix'             => array( array( 'id', 'is', '1' ), false ),
		);
	}

	/**
	 * Every field and condition.
	 *
	 * @dataProvider conditions
	 * @param array $spec    Field, condition, value.
	 * @param bool  $matches Expected.
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'conditions' )]
	public function test_conditions( array $spec, bool $matches ) {
		$rule = Marketplace_Feed::normalize_filter_rule( $this->rule( $spec[0], $spec[1], $spec[2] ) );

		$this->assertNotNull( $rule );
		$this->assertSame( $matches, Marketplace_Feed::filter_rule_matches( $rule, $this->row() ) );
	}

	/**
	 * Attribute rules: one of the values, in the named attribute only.
	 *
	 * @return void
	 */
	public function test_attribute_rules() {
		$hit = function ( string $attr, string $op, string $value ): bool {
			return Marketplace_Feed::filter_rule_matches( Marketplace_Feed::normalize_filter_rule( $this->rule( 'attribute', $op, $value, 'exclude', $attr ) ), $this->row() );
		};

		$this->assertTrue( $hit( 'колір', 'is', 'чорний' ) );
		$this->assertFalse( $hit( 'Колір', 'is', 'синій' ) );
		$this->assertFalse( $hit( 'Розмір', 'is', 'чорний' ), 'Another attribute' );
		$this->assertTrue( $hit( 'Колір', 'is_not', 'синій' ) );
	}

	/**
	 * Incomplete or impossible rules are dropped.
	 *
	 * @return void
	 */
	public function test_invalid_rules_are_dropped() {
		$this->assertNull( Marketplace_Feed::normalize_filter_rule( $this->rule( 'brand', 'is', '' ) ) );
		$this->assertNull( Marketplace_Feed::normalize_filter_rule( $this->rule( 'colour', 'is', 'x' ) ) );
		$this->assertNull( Marketplace_Feed::normalize_filter_rule( $this->rule( 'brand', 'like', 'x' ) ) );
		$this->assertNull( Marketplace_Feed::normalize_filter_rule( $this->rule( 'attribute', 'is', 'x' ) ), 'Attribute without a name' );
		$this->assertNull( Marketplace_Feed::normalize_filter_rule( $this->rule( 'brand', 'gt', '5' ) ), 'Greater than only for the price' );
		$this->assertNull( Marketplace_Feed::normalize_filter_rule( $this->rule( 'price', 'gt', 'abc' ) ) );
		$this->assertSame( 'instock', Marketplace_Feed::normalize_filter_rule( array( 'field' => 'stock', 'op' => 'is', 'value' => '', 'stock' => 'instock' ) )['value'], 'The stock select' );
	}

	/**
	 * «Не вивантажувати» wins; with «Вивантажувати лише» only matching products stay.
	 *
	 * @return void
	 */
	public function test_include_and_exclude() {
		$row  = $this->row();
		$norm = static function ( array $rules ): array {
			return array_map( array( Marketplace_Feed::class, 'normalize_filter_rule' ), $rules );
		};

		$this->assertTrue( Marketplace_Feed::row_excluded( $norm( array( $this->rule( 'brand', 'is', 'Mil-Tec' ) ) ), $row ) );
		$this->assertFalse( Marketplace_Feed::row_excluded( $norm( array( $this->rule( 'brand', 'is', 'Kiborg' ) ) ), $row ) );
		$this->assertTrue( Marketplace_Feed::row_excluded( $norm( array( $this->rule( 'category', 'is', 'Одяг', 'include' ) ) ), $row ), 'Not in the only allowed category' );
		$this->assertFalse(
			Marketplace_Feed::row_excluded(
				$norm(
					array(
						$this->rule( 'category', 'is', 'Одяг', 'include' ),
						$this->rule( 'category', 'is', 'Медицина', 'include' ),
					)
				),
				$row
			),
			'Any include rule is enough'
		);
		$this->assertTrue(
			Marketplace_Feed::row_excluded(
				$norm(
					array(
						$this->rule( 'category', 'is', 'Медицина', 'include' ),
						$this->rule( 'brand', 'is', 'Mil-Tec' ),
					)
				),
				$row
			),
			'An exclude rule wins over include'
		);
	}

	/**
	 * Form rows become JSON; older «type: value» text still works.
	 *
	 * @return void
	 */
	public function test_storage() {
		$json = Marketplace_Feed::filter_rows_to_json(
			array(
				$this->rule( 'brand', 'is', 'Mil-Tec' ),
				$this->rule( 'brand', 'is', '' ),
				array( 'field' => 'stock', 'op' => 'is', 'value' => '', 'stock' => 'outofstock', 'action' => 'exclude' ),
			)
		);

		$this->assertCount( 2, Marketplace_Feed::parse_filter_rules( $json ), 'The empty row is dropped' );
		$this->assertStringContainsString( 'Mil-Tec', $json, 'Unicode and case kept' );

		$legacy = Marketplace_Feed::parse_filter_rules( "бренд: Mil-Tec\nартикул: AR_\nатрибут: Колір = Червоний\nназва: ЛЛ7192\nсміття" );
		$this->assertSame( array( 'brand', 'sku', 'attribute', 'name' ), array_column( $legacy, 'field' ) );
		$this->assertSame( 'starts_with', $legacy[1]['op'] );
		$this->assertSame( 'Колір', $legacy[2]['attr'] );
		$this->assertSame( 'contains', $legacy[3]['op'] );

		$this->assertSame( array(), Marketplace_Feed::parse_filter_rules( '' ) );
	}

	/**
	 * A product rule takes the variations with it; a variation rule drops only that one.
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
				array( 'ID' => '101', 'SKU' => 'P-100-S', 'In stock?' => '1', 'Regular price' => '500', 'Attribute 1 name' => 'Розмір', 'Attribute 1 value(s)' => 'S' ),
				array( 'ID' => '102', 'SKU' => 'P-100-M', 'In stock?' => '0', 'Stock' => '0', 'Regular price' => '900', 'Attribute 1 name' => 'Розмір', 'Attribute 1 value(s)' => 'M' ),
			),
		);
		$json   = static function ( array $rules ): array {
			return array( 'exclude_rules' => Marketplace_Feed::filter_rows_to_json( $rules ) );
		};

		list( $left, $dropped, $why ) = $feed->apply_exclusions( $unit, $json( array( $this->rule( 'brand', 'is', 'Mil-Tec' ) ) ) );
		$this->assertNull( $left, 'Brand of the product: the whole product goes' );
		$this->assertSame( 2, $dropped );
		$this->assertSame( array( 'P-100' ), $why['excluded']['examples'] );

		list( $left, $dropped ) = $feed->apply_exclusions( $unit, $json( array( $this->rule( 'attribute', 'is', 'M', 'exclude', 'Розмір' ) ) ) );
		$this->assertSame( 1, $dropped );
		$this->assertSame( array( '101' ), array_column( $left['children'], 'ID' ) );

		list( $left, $dropped ) = $feed->apply_exclusions( $unit, $json( array( array( 'action' => 'exclude', 'field' => 'stock', 'op' => 'is', 'stock' => 'outofstock', 'value' => '' ) ) ) );
		$this->assertSame( array( '101' ), array_column( $left['children'], 'ID' ), 'Out-of-stock variation only' );

		list( $left, $dropped ) = $feed->apply_exclusions( $unit, $json( array( $this->rule( 'price', 'lt', '600', 'include' ) ) ) );
		$this->assertSame( array( '101' ), array_column( $left['children'], 'ID' ), 'Only the cheap variation is allowed' );

		list( $left, $dropped ) = $feed->apply_exclusions( $unit, $json( array( $this->rule( 'brand', 'is', 'Kiborg' ) ) ) );
		$this->assertSame( $unit, $left, 'Nothing matches: untouched' );
		$this->assertSame( 0, $dropped );
	}

	/**
	 * Simple products and lone variations.
	 *
	 * @return void
	 */
	public function test_simple_and_lone_units() {
		$feed  = new Google_Merchant_Feed();
		$rules = array( 'exclude_rules' => Marketplace_Feed::filter_rows_to_json( array( $this->rule( 'brand', 'is', 'Mil-Tec' ) ) ) );

		list( $left, $dropped ) = $feed->apply_exclusions( array( 'kind' => 'simple', 'row' => $this->row() ), $rules );
		$this->assertNull( $left );
		$this->assertSame( 1, $dropped );

		$lone = array(
			'kind'   => 'lone',
			'row'    => array( 'ID' => '7', 'SKU' => 'X-7' ),
			'parent' => $this->row(),
		);
		list( $left ) = $feed->apply_exclusions( $lone, $rules );
		$this->assertNull( $left, 'A lone variation follows its product' );
	}

	/**
	 * Every feed has the setting, and the form rows are saved as rules.
	 *
	 * @return void
	 */
	public function test_every_feed_has_the_setting() {
		foreach ( array( new Prom_Feed(), new Google_Merchant_Feed() ) as $feed ) {
			$this->assertArrayHasKey( 'exclude_rules', $feed->settings_fields() );
			$this->assertSame( 'filter_rules', $feed->settings_fields()['exclude_rules']['type'] );
			$this->assertArrayHasKey( 'exclude', $feed->settings_groups() );
			$this->assertSame( '', $feed->with_defaults( array() )['exclude_rules'] );

			$saved = $feed->sanitize( array( '__filters' => array( 'exclude_rules' => array( $this->rule( 'brand', 'is', 'Mil-Tec' ) ) ) ) )['exclude_rules'];
			$this->assertSame( 'Mil-Tec', Marketplace_Feed::parse_filter_rules( $saved )[0]['value'] );
		}
	}
}
