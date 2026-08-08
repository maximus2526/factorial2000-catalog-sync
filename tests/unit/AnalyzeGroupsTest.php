<?php
/**
 * Variable groups analysis tests (f2000cs_analyze_variable_groups).
 *
 * The function is defined in admin/page-import.php but uses only native
 * XMLReader/SimpleXML — testable offline with local fixture files.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Analyze variable groups tests.
 */
final class F2000CS_Unit_AnalyzeGroupsTest extends F2000CS_Unit_TestCase {

	/**
	 * Load the admin import module once.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/admin/page-import.php';
	}

	/**
	 * Create a temp XML file, run the analyzer, clean up.
	 *
	 * @param string $xml    XML content.
	 * @return array         Analyzed groups.
	 */
	private function analyze( string $xml ): array {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_ag_' );
		file_put_contents( $tmp, $xml );

		try {
			return f2000cs_analyze_variable_groups( $tmp );
		} finally {
			unlink( $tmp );
		}
	}

	// ----------------------------------------------------------------

	public function test_empty_xml_returns_empty(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers></offers></shop></yml_catalog>'
		);

		$this->assertSame( array(), $groups );
	}

	public function test_offers_without_group_id_are_skipped(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" available="true"><name>Простий</name><price>100</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$this->assertSame( array(), $groups );
	}

	public function test_group_with_one_variation_is_filtered_out(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>Єдиний</name><price>100</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$this->assertSame( array(), $groups,
			'Groups with less than 2 variations must be excluded' );
	}

	public function test_group_with_two_plus_variations_is_kept(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>Футболка M</name><price>100</price></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>Футболка L</name><price>120</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$this->assertCount( 1, $groups );
		$this->assertArrayHasKey( 'G1', $groups );

		$group = $groups['G1'];

		$this->assertSame( 'Футболка M', $group['name'] );
		$this->assertSame( 2, $group['variations_count'] );
	}

	public function test_picture_is_captured_from_first_variation(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>A</name><price>10</price><picture>http://img.test/a.jpg</picture></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>B</name><price>20</price><picture>http://img.test/b.jpg</picture></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$this->assertSame( 'http://img.test/a.jpg', $groups['G1']['image'] );
	}

	public function test_attributes_are_collected_and_varying_flag_is_set(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>A</name><price>10</price>' .
			'<param name="Колір">Чорний</param><param name="Розмір">M</param><param name="Матеріал">Бавовна</param></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>B</name><price>20</price>' .
			'<param name="Колір">Білий</param><param name="Розмір">L</param><param name="Матеріал">Бавовна</param></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$attrs = $groups['G1']['attributes'];

		$this->assertCount( 3, $attrs );

		// Find by name.
		$by_name = array();
		foreach ( $attrs as $attr ) {
			$by_name[ $attr['name'] ] = $attr;
		}

		$this->assertTrue( $by_name['Колір']['is_varying'], 'Колір has 2 values → varying' );
		$this->assertSame( array( 'Чорний', 'Білий' ), $by_name['Колір']['values'] );

		$this->assertTrue( $by_name['Розмір']['is_varying'] );
		$this->assertSame( array( 'M', 'L' ), $by_name['Розмір']['values'] );

		$this->assertFalse( $by_name['Матеріал']['is_varying'],
			'Матеріал has only 1 value → not varying' );
		$this->assertSame( array( 'Бавовна' ), $by_name['Матеріал']['values'] );
	}

	public function test_missing_file_throws(): void {
		$this->expectException( Exception::class );
		f2000cs_analyze_variable_groups( sys_get_temp_dir() . '/no-such-file-f2000cs.xml' );
	}

	public function test_multiple_groups_are_correctly_separated(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>Ф</name><price>100</price></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>Ф</name><price>120</price></offer>' .
			'<offer id="3" group_id="G2" available="true"><name>К</name><price>200</price></offer>' .
			'<offer id="4" group_id="G2" available="true"><name>К</name><price>250</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$this->assertCount( 2, $groups );
		$this->assertArrayHasKey( 'G1', $groups );
		$this->assertArrayHasKey( 'G2', $groups );
		$this->assertSame( 2, $groups['G1']['variations_count'] );
		$this->assertSame( 2, $groups['G2']['variations_count'] );
	}

	public function test_name_ua_has_priority_for_group_title(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>RU Name</name><name_ua>UA Name</name_ua><price>10</price></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>RU 2</name><price>20</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$this->assertSame( 'UA Name', $groups['G1']['name'] );
	}

	public function test_param_unit_is_appended_like_import(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>A</name><price>10</price>' .
			'<param name="Розмір" unit="EU">42</param></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>B</name><price>20</price>' .
			'<param name="Розмір" unit="EU">43</param></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$by_name = array();
		foreach ( $groups['G1']['attributes'] as $attr ) {
			$by_name[ $attr['name'] ] = $attr;
		}

		$this->assertSame( array( '42 EU', '43 EU' ), $by_name['Розмір']['values'] );
		$this->assertTrue( $by_name['Розмір']['is_varying'] );
	}

	public function test_vendor_becomes_vyrobnyk_attribute(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>A</name><price>10</price><vendor>Nike</vendor>' .
			'<param name="Розмір">M</param></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>B</name><price>20</price><vendor>Nike</vendor>' .
			'<param name="Розмір">L</param></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$by_name = array();
		foreach ( $groups['G1']['attributes'] as $attr ) {
			$by_name[ $attr['name'] ] = $attr;
		}

		$this->assertArrayHasKey( 'Виробник', $by_name );
		$this->assertSame( array( 'Nike' ), $by_name['Виробник']['values'] );
		$this->assertFalse( $by_name['Виробник']['is_varying'] );
	}

	public function test_vendor_does_not_override_existing_vyrobnyk_param(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="G1" available="true"><name>A</name><price>10</price><vendor>Nike</vendor>' .
			'<param name="Виробник">Adidas</param><param name="Розмір">M</param></offer>' .
			'<offer id="2" group_id="G1" available="true"><name>B</name><price>20</price><vendor>Nike</vendor>' .
			'<param name="Виробник">Adidas</param><param name="Розмір">L</param></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$by_name = array();
		foreach ( $groups['G1']['attributes'] as $attr ) {
			$by_name[ $attr['name'] ] = $attr;
		}

		$this->assertSame( array( 'Adidas' ), $by_name['Виробник']['values'] );
	}

	public function test_group_id_zero_is_kept_as_real_group(): void {
		$groups = $this->analyze(
			'<?xml version="1.0"?><yml_catalog><shop><offers>' .
			'<offer id="1" group_id="0" available="true"><name>A</name><price>10</price></offer>' .
			'<offer id="2" group_id="0" available="true"><name>B</name><price>20</price></offer>' .
			'</offers></shop></yml_catalog>'
		);

		$this->assertArrayHasKey( '0', $groups );
		$this->assertSame( 2, $groups['0']['variations_count'] );
	}
}
