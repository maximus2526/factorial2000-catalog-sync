<?php
/**
 * import_products() mode isolation and offset/finished tests.
 *
 * Uses a spy subclass so product creation side-effects are not required.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Import products modes / offset tests.
 */
final class F2000CS_Unit_ImportProductsModesTest extends F2000CS_Unit_TestCase {

	/**
	 * Mixed catalog fixture path.
	 *
	 * @var string
	 */
	private $xml_path = '';

	/**
	 * Build a mixed XML: 2 simples, 1 lone group_id, 1 multi group, group_id="0" multi.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$xml = '<?xml version="1.0"?>
			<yml_catalog>
				<shop>
					<offers>
						<offer id="S1" available="true"><name>Simple 1</name><price>10</price></offer>
						<offer id="S2" available="true"><name>Simple 2</name><price>20</price></offer>
						<offer id="L1" available="true" group_id="LONE"><name>Lone</name><price>30</price></offer>
						<offer id="V1" available="true" group_id="G1"><name>Var M</name><price>40</price>
							<param name="Розмір">M</param></offer>
						<offer id="V2" available="true" group_id="G1"><name>Var L</name><price>45</price>
							<param name="Розмір">L</param></offer>
						<offer id="Z1" available="true" group_id="0"><name>Zero A</name><price>50</price>
							<param name="Колір">Чорний</param></offer>
						<offer id="Z2" available="true" group_id="0"><name>Zero B</name><price>55</price>
							<param name="Колір">Білий</param></offer>
					</offers>
				</shop>
			</yml_catalog>';

		$this->xml_path = tempnam( sys_get_temp_dir(), 'f2000cs_imp_' );
		file_put_contents( $this->xml_path, $xml );
	}

	/**
	 * Remove fixture file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->xml_path && file_exists( $this->xml_path ) ) {
			unlink( $this->xml_path );
		}
		delete_transient( 'f2000cs_import_variations_temp' );
		parent::tearDown();
	}

	/**
	 * @param bool $variable Whether variable mode is enabled.
	 * @return F2000CS_ImportSpyParser
	 */
	private function parser( bool $variable ): F2000CS_ImportSpyParser {
		set_transient( 'f2000cs_import_variations_temp', $variable ? '1' : '0', HOUR_IN_SECONDS );
		return new F2000CS_ImportSpyParser( $this->xml_path, false, 'P_' );
	}

	public function test_simple_mode_imports_plain_and_lone_group_only(): void {
		$parser = $this->parser( false );
		$result = $parser->import_products( 0, 100 );

		$this->assertSame( array( 'S1', 'S2', 'L1' ), $parser->simple_skus );
		$this->assertSame( array(), $parser->variable_groups, 'Simple mode must not create variable parents' );
		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 3, $result['imported'] );
		$this->assertSame( 3, $result['processed'] );
		$this->assertTrue( $result['finished'] );
	}

	public function test_variable_mode_imports_multi_groups_including_group_id_zero(): void {
		$parser = $this->parser( true );
		$result = $parser->import_products( 0, 100 );

		$this->assertSame( array(), $parser->simple_skus, 'Variable mode must skip simples' );
		sort( $parser->variable_groups );
		$this->assertSame( array( '0', 'G1' ), $parser->variable_groups );
		$this->assertSame( 2, $result['total'] );
		$this->assertSame( 2, $result['imported'] );
		$this->assertTrue( $result['finished'] );
	}

	public function test_offset_and_limit_advance_with_processed_even_on_skip(): void {
		$parser                 = $this->parser( false );
		$parser->simple_return  = false; // every simple "exists" → skip

		$batch1 = $parser->import_products( 0, 1 );
		$this->assertSame( 0, $batch1['imported'] );
		$this->assertSame( 1, $batch1['skipped'] );
		$this->assertSame( 1, $batch1['processed'] );
		$this->assertFalse( $batch1['finished'] );
		$this->assertSame( array( 'S1' ), $parser->simple_skus );

		$batch2 = $parser->import_products( 1, 1 );
		$this->assertSame( 1, $batch2['processed'] );
		$this->assertFalse( $batch2['finished'] );
		$this->assertSame( array( 'S1', 'S2' ), $parser->simple_skus );

		$batch3 = $parser->import_products( 2, 1 );
		$this->assertSame( 1, $batch3['processed'] );
		$this->assertTrue( $batch3['finished'], 'Last item must finish even when skipped' );
		$this->assertSame( array( 'S1', 'S2', 'L1' ), $parser->simple_skus );
	}

	public function test_variable_mode_chunking_uses_group_count_as_total(): void {
		$parser = $this->parser( true );

		$first = $parser->import_products( 0, 1 );
		$this->assertSame( 2, $first['total'] );
		$this->assertSame( 1, $first['processed'] );
		$this->assertFalse( $first['finished'] );
		$this->assertCount( 1, $parser->variable_groups );

		$second = $parser->import_products( 1, 1 );
		$this->assertSame( 1, $second['processed'] );
		$this->assertTrue( $second['finished'] );
		$this->assertCount( 2, $parser->variable_groups );
	}
}
