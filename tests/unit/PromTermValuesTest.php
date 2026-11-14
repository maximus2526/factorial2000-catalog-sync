<?php
/**
 * Per-term Prom value mapping.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Prom_Recommendations;
use F2000CS\Prom_Term_Values;

if ( ! function_exists( 'get_terms' ) ) {
	/**
	 * Minimal taxonomy term lookup for the tests.
	 *
	 * @param array $args Query args.
	 * @return array<int, object>
	 */
	function get_terms( $args = array() ) {
		if ( isset( $GLOBALS['f2000cs_test_terms'] ) && is_array( $GLOBALS['f2000cs_test_terms'] ) ) {
			return $GLOBALS['f2000cs_test_terms'];
		}

		return array(
			(object) array(
				'term_id' => 99,
				'name'    => 'Чорний',
				'slug'    => 'black',
			),
		);
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	/**
	 * Term meta fixture.
	 *
	 * @param int    $term_id  Term id.
	 * @param string $key      Meta key.
	 * @param bool   $single   Single value.
	 * @return mixed
	 */
	function get_term_meta( $term_id, $key, $single = false ) {
		if ( 99 === (int) $term_id && '_f2000cs_prom_value_map' === $key ) {
			if ( isset( $GLOBALS['f2000cs_test_map'] ) && is_array( $GLOBALS['f2000cs_test_map'] ) ) {
				return $GLOBALS['f2000cs_test_map'];
			}

			return array( '888888' => 'Чорний офіційний', '777777' => 'Black official' );
		}

		return '';
	}
}

final class F2000CS_Unit_PromTermValuesTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset caches.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['f2000cs_test_terms'] = array(
			(object) array(
				'term_id' => 99,
				'name'    => 'Чорний',
				'slug'    => 'black',
			),
		);
		Prom_Term_Values::reset();
		Prom_Recommendations::reset();
		add_filter(
			'f2000cs_prom_site_attributes',
			static function ( $attributes ) {
				$attributes['pa_color'] = array(
					'key'    => 'pa_color',
					'label'  => 'Колір',
					'source' => 'global',
					'type'   => 'select',
				);

				return $attributes;
			}
		);
	}

	/**
	 * A category-specific term value replaces the site value in the feed.
	 *
	 * @return void
	 */
	public function test_term_value_wins_for_matching_prom_category() {
		$settings = array(
			'prom_attribute_map' => json_encode( array( '888888' => array( 'Колір' => 'pa_color' ) ) ),
		);

		$this->assertSame(
			array( array( 'Колір', 'Чорний офіційний' ) ),
			Prom_Recommendations::apply_params(
				array( array( 'Колір', 'Чорний' ) ),
				'888888',
				$settings
			)
		);
	}

	/**
	 * A different Prom category gets its own term value.
	 *
	 * @return void
	 */
	public function test_term_value_is_category_specific() {
		$settings = array(
			'prom_attribute_map' => json_encode( array( '777777' => array( 'Колір' => 'pa_color' ) ) ),
		);

		$this->assertSame(
			array( array( 'Колір', 'Black official' ) ),
			Prom_Recommendations::apply_params(
				array( array( 'Колір', 'Чорний' ) ),
				'777777',
				$settings
			)
		);
	}

	/**
	 * The feed uses the term id from the row, not a duplicate display name.
	 *
	 * @return void
	 */
	public function test_term_id_from_row_wins_over_name_lookup() {
		$GLOBALS['f2000cs_test_map'] = array( '888888' => 'Чорний офіційний' );
		Prom_Term_Values::reset();

		$settings = array(
			'prom_attribute_map' => json_encode( array( '888888' => array( 'Колір' => 'pa_color' ) ) ),
		);
		$row      = array(
			'Attribute 1 name'     => 'Колір',
			'Attribute 1 value(s)' => 'Чорний',
			'Attribute 1 term_ids' => '99',
		);

		$this->assertSame(
			array( array( 'Колір', 'Чорний офіційний' ) ),
			Prom_Recommendations::apply_params( array( array( 'Колір', 'Чорний' ) ), '888888', $settings, $row )
		);
	}

	/**
	 * Translator tags are split into language names, not concatenated.
	 *
	 * @return void
	 */
	public function test_tagged_term_name_is_matched_by_language() {
		$GLOBALS['f2000cs_test_terms'] = array(
			(object) array(
				'term_id' => 99,
				'name'    => '[:uk]Чорний[:ru]Чёрный[:en]Black[:]',
				'slug'    => 'black',
			),
		);
		Prom_Term_Values::reset();

		$settings = array(
			'prom_attribute_map' => json_encode( array( '888888' => array( 'Колір' => 'pa_color' ) ) ),
		);

		$this->assertSame(
			array( array( 'Колір', 'Чорний офіційний' ) ),
			Prom_Recommendations::apply_params( array( array( 'Колір', 'Чорний' ) ), '888888', $settings )
		);
	}

	/**
	 * Duplicate term names are ambiguous: do not apply a map to the wrong term.
	 *
	 * @return void
	 */
	public function test_duplicate_term_name_is_not_mapped() {
		$GLOBALS['f2000cs_test_terms'] = array(
			(object) array( 'term_id' => 99, 'name' => 'Чорний', 'slug' => 'black' ),
			(object) array( 'term_id' => 100, 'name' => 'Чорний', 'slug' => 'black-2' ),
		);
		$GLOBALS['f2000cs_test_map'] = array( '888888' => 'Чорний офіційний' );
		Prom_Term_Values::reset();

		$settings = array(
			'prom_attribute_map' => json_encode( array( '888888' => array( 'Колір' => 'pa_color' ) ) ),
		);

		$this->assertSame(
			array( array( 'Колір', 'Чорний' ) ),
			Prom_Recommendations::apply_params( array( array( 'Колір', 'Чорний' ) ), '888888', $settings )
		);
	}

	/**
	 * A mapped value that is not official for the target attribute is ignored.
	 *
	 * @return void
	 */
	public function test_non_official_mapped_value_falls_back() {
		$GLOBALS['f2000cs_test_terms'] = array(
			(object) array( 'term_id' => 99, 'name' => 'Чорний', 'slug' => 'black' ),
		);
		$GLOBALS['f2000cs_test_map'] = array( '30807' => 'Не-офіційне-значення' );
		Prom_Term_Values::reset();

		$settings = array(
			'prom_attribute_map' => json_encode( array( '30807' => array( 'Колір' => 'pa_color' ) ) ),
		);

		$this->assertSame(
			array( array( 'Колір', 'Чорний' ) ),
			Prom_Recommendations::apply_params( array( array( 'Колір', 'Чорний' ) ), '30807', $settings )
		);
	}

	/**
	 * No term map keeps the original site value.
	 *
	 * @return void
	 */
	public function test_without_term_map_value_is_unchanged() {
		$settings = array(
			'prom_attribute_map' => json_encode( array( '999999' => array( 'Колір' => 'pa_color' ) ) ),
		);

		$this->assertSame(
			array( array( 'Колір', 'Чорний' ) ),
			Prom_Recommendations::apply_params(
				array( array( 'Колір', 'Чорний' ) ),
				'999999',
				$settings
			)
		);
	}
}
