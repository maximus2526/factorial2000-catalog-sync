<?php
/**
 * Prom recommended attributes and site-attribute mapping.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Prom_Recommendations;

if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
	/**
	 * Small site attribute catalogue for the tests.
	 *
	 * @return array<int, object>
	 */
	function wc_get_attribute_taxonomies() {
		return array(
			(object) array(
				'attribute_name'  => 'gender',
				'attribute_label' => 'Стать',
				'attribute_type'  => 'select',
			),
			(object) array(
				'attribute_name'  => 'season',
				'attribute_label' => 'Сезон',
				'attribute_type'  => 'select',
			),
			(object) array(
				'attribute_name'  => 'color',
				'attribute_label' => 'Колір',
				'attribute_type'  => 'select',
			),
			(object) array(
				'attribute_name'  => 'product_kind',
				'attribute_label' => 'Вид виробу',
				'attribute_type'  => 'select',
			),
		);
	}
}

final class F2000CS_Unit_PromRecommendationsTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset the loader between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Prom_Recommendations::reset();
	}

	/**
	 * The bundled file is the full Prom list.
	 *
	 * @return void
	 */
	public function test_bundled_data_loads() {
		$all = Prom_Recommendations::all();

		$this->assertCount( 3123, $all );
		$this->assertArrayHasKey( '30807', $all );
		$this->assertSame( array( 'Стать', 'Сезон', 'Вид виробу' ), $all['30807']['attrs_uk'] );
		$this->assertSame( array( 'Пол', 'Сезон', 'Вид изделия' ), $all['30807']['attrs_ru'] );
	}

	/**
	 * Selected categories keep their Prom paths and up to four attributes.
	 *
	 * @return void
	 */
	public function test_selected_categories_have_recommended_attributes() {
		$categories = Prom_Recommendations::categories( array( '30807', '999999' ) );

		$this->assertArrayHasKey( '30807', $categories );
		$this->assertCount( 3, $categories['30807']['attrs'] );
		$this->assertSame( 'Стать', $categories['30807']['attrs'][0]['name'] );
		$this->assertSame( 'Пол', $categories['30807']['attrs'][0]['name_ru'] );
		$this->assertArrayHasKey( '999999', $categories );
		$this->assertSame( array(), $categories['999999']['attrs'] );
	}

	/**
	 * Exact names match, including the Russian name as a fallback.
	 *
	 * @return void
	 */
	public function test_exact_name_matching() {
		$this->assertSame( 'pa_gender', Prom_Recommendations::match( 'Стать', 'Пол' ) );
		$this->assertSame( 'pa_season', Prom_Recommendations::match( 'Сезон', 'Сезон' ) );
		$this->assertSame( 'pa_product_kind', Prom_Recommendations::match( 'Вид виробу', 'Вид изделия' ) );
		$this->assertSame( '', Prom_Recommendations::match( 'Невідома характеристика', 'Неизвестная' ) );
	}

	/**
	 * A mapping is stored as compact UTF-8 JSON without empty rows.
	 *
	 * @return void
	 */
	public function test_mapping_is_sanitized_to_json() {
		$map = Prom_Recommendations::sanitize_map_array(
			array(
				'30807' => array(
					'Стать' => 'pa_gender',
					''      => 'pa_color',
				),
				'abc'   => array( 'Сезон' => 'pa_season' ),
			)
		);

		$this->assertSame( array( '30807' => array( 'Стать' => 'pa_gender' ) ), $map );
		$this->assertSame( '{"30807":{"Стать":"pa_gender"}}', Prom_Recommendations::sanitize_json( $map ) );
	}

	/**
	 * The feed schema stores the mapping as sanitized JSON.
	 *
	 * @return void
	 */
	public function test_feed_schema_sanitizes_the_mapping() {
		$feed = new \F2000CS\Prom_Feed();
		$out  = $feed->sanitize( array( 'prom_attribute_map' => '{"30807":{"Стать":"pa_gender","":"pa_color"}}' ) );

		$this->assertSame( '{"30807":{"Стать":"pa_gender"}}', $out['prom_attribute_map'] );
	}

	/**
	 * Mapped site attributes are renamed to the Prom characteristic, others stay.
	 *
	 * @return void
	 */
	public function test_feed_params_use_prom_names() {
		$settings = array(
			'prom_attribute_map' => json_encode(
				array(
					'30807' => array(
						'Стать'  => 'pa_gender',
						'Сезон'  => 'pa_season',
					),
				)
			),
		);

		$out = Prom_Recommendations::apply_params(
			array(
				array( 'Стать', 'Чоловіча' ),
				array( 'Стать', 'Чоловіча' ),
				array( 'Сезон', 'Літо' ),
				array( 'Матеріал', 'Бавовна' ),
			),
			'30807',
			$settings
		);

		$this->assertSame(
			array(
				array( 'Стать', 'Чоловіча' ),
				array( 'Сезон', 'Літо' ),
				array( 'Матеріал', 'Бавовна' ),
			),
			$out
		);
	}

	/**
	 * A different site label is renamed to the Prom name.
	 *
	 * @return void
	 */
	public function test_feed_renames_an_alias_to_the_prom_name() {
		add_filter(
			'f2000cs_prom_site_attributes',
			static function ( $attributes ) {
				$attributes['pa_gender']['label'] = 'Gender';

				return $attributes;
			}
		);

		$settings = array(
			'prom_attribute_map' => json_encode( array( '30807' => array( 'Стать' => 'pa_gender' ) ) ),
		);

		$this->assertSame(
			array( array( 'Стать', 'Чоловіча' ) ),
			Prom_Recommendations::apply_params( array( array( 'Gender', 'Чоловіча' ) ), '30807', $settings )
		);

		remove_filter( 'f2000cs_prom_site_attributes' );
	}

	/**
	 * Feed keeps all attributes when no category mapping exists.
	 *
	 * @return void
	 */
	public function test_without_mapping_params_are_untouched() {
		$params = array(
			array( 'Стать', 'Чоловіча' ),
			array( 'Сезон', 'Літо' ),
		);

		$this->assertSame( $params, Prom_Recommendations::apply_params( $params, '30807', array() ) );
	}
}
