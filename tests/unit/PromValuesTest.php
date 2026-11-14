<?php
/**
 * Bundled Prom characteristic values.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Prom_Values;

final class F2000CS_Unit_PromValuesTest extends F2000CS_Unit_TestCase {

	/**
	 * Values are grouped/searched by Prom category.
	 *
	 * @return void
	 */
	public function test_search_returns_values_for_one_category() {
		$values = Prom_Values::search( '30807', '', 5 );

		$this->assertNotEmpty( $values );
		$this->assertArrayHasKey( 'id', $values[0] );
		$this->assertArrayHasKey( 'value', $values[0] );
	}

	/**
	 * The query filters by value or attribute name.
	 *
	 * @return void
	 */
	public function test_search_filters_by_text() {
		$this->assertSame( array(), Prom_Values::search( '30807', 'zzzz-no-such-value' ) );
	}

	/**
	 * An invalid category returns no values.
	 *
	 * @return void
	 */
	public function test_invalid_category_is_empty() {
		$this->assertSame( array(), Prom_Values::search( '', 'black' ) );
	}

	/**
	 * has() answers whether a value is official for a category attribute.
	 *
	 * @return void
	 */
	public function test_is_official_checks_official_values() {
		$this->assertTrue( F2000CS\Prom_Values::is_official( '30807', 'Колір', 'Чорний' ) );
		$this->assertTrue( F2000CS\Prom_Values::is_official( '30807', 'Стать', 'Чоловіча' ) );
		$this->assertFalse( F2000CS\Prom_Values::is_official( '30807', 'Колір', 'Не-офіційне-значення' ) );
		$this->assertNull( F2000CS\Prom_Values::is_official( '30807', 'Невідомий атрибут', 'Чорний' ) );
		$this->assertNull( F2000CS\Prom_Values::is_official( '999999', 'Колір', 'Чорний' ) );
	}

	/**
	 * The bundled list must not push control characters into a feed.
	 *
	 * @return void
	 */
	public function test_bundled_values_have_no_control_characters() {
		$bad = array();

		foreach ( Prom_Values::all() as $category => $rows ) {
			foreach ( $rows as $row ) {
				foreach ( $row as $field => $value ) {
					if ( preg_match( '/[\x00-\x1F\x7F]/', (string) $value ) ) {
						$bad[] = $category . '/' . $row['value_id'] . '/' . $field;
					}
				}
			}
		}

		$this->assertSame( array(), $bad );
	}
}
