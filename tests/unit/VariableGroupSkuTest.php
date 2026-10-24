<?php
/**
 * Variable parents must not share a SKU with one of their own offers.
 *
 * A group whose group_id equals an offer id (e.g. <offer id="9845" group_id="9845">)
 * used to give the parent that offer's SKU: the variation was never created and the
 * stock sync wrote that one size's status and price onto the parent.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Fields_Updater;
use F2000CS\XML_Stock_Updater;

/**
 * Group parent SKU tests.
 */
final class F2000CS_Unit_VariableGroupSkuTest extends F2000CS_Unit_TestCase {

	/**
	 * Parser with the P_ prefix.
	 *
	 * @return F2000CS_ImportSpyParser
	 */
	private function parser(): F2000CS_ImportSpyParser {
		return new F2000CS_ImportSpyParser( 'unused.xml', false, 'P_' );
	}

	/**
	 * A variable parent stand-in.
	 *
	 * @return object
	 */
	private function variable_parent() {
		return new class() {
			/**
			 * @param string $type Product type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return 'variable' === $type;
			}

			/**
			 * @return int
			 */
			public function get_id() {
				return 40;
			}

			/**
			 * @return string
			 */
			public function get_status() {
				return 'publish';
			}

			/**
			 * @return bool
			 */
			public function managing_stock() {
				return false;
			}

			/**
			 * @return string
			 */
			public function get_type() {
				return 'variable';
			}

			/**
			 * @return string
			 */
			public function get_regular_price() {
				return '';
			}

			/**
			 * @return string
			 */
			public function get_sale_price() {
				return '';
			}

			/**
			 * @return int
			 */
			public function get_parent_id() {
				return 0;
			}
		};
	}

	public function test_parent_keeps_the_group_id_sku_when_no_offer_uses_it(): void {
		$sku = F2000CS_Test_Reflection::invoke(
			$this->parser(),
			'group_parent_sku',
			array( 'G1', array( array( 'sku' => '101' ), array( 'sku' => '102' ) ) )
		);

		$this->assertSame( 'P_G1', $sku );
	}

	public function test_parent_gets_its_own_sku_when_an_offer_id_equals_the_group_id(): void {
		$sku = F2000CS_Test_Reflection::invoke(
			$this->parser(),
			'group_parent_sku',
			array( '9845', array( array( 'sku' => '9845' ), array( 'sku' => '9846' ) ) )
		);

		$this->assertSame( 'P_9845-group', $sku );
	}

	public function test_existing_parent_with_the_old_sku_is_still_found(): void {
		// Only the legacy SKU (prefix + group_id) exists, on a parent product.
		$GLOBALS['wpdb']->results_queue = array(
			array(),
			array( (object) array( 'sku' => 'P_9845', 'ID' => 40 ) ),
		);

		$found = F2000CS_Test_Reflection::invoke(
			$this->parser(),
			'find_group_parent',
			array( '9845', array( array( 'sku' => '9845' ), array( 'sku' => '9846' ) ) )
		);

		$this->assertSame( 40, $found, 'Re-import must not create a second parent' );
	}

	public function test_a_variation_with_the_group_id_sku_is_not_mistaken_for_the_parent(): void {
		F2000CS_Test_State::$post_types[41] = 'product_variation';
		$GLOBALS['wpdb']->results_queue     = array(
			array(),
			array( (object) array( 'sku' => 'P_9845', 'ID' => 41 ) ),
		);

		$found = F2000CS_Test_Reflection::invoke(
			$this->parser(),
			'find_group_parent',
			array( '9845', array( array( 'sku' => '9845' ), array( 'sku' => '9846' ) ) )
		);

		$this->assertSame( 0, $found );
	}

	public function test_stock_sync_does_not_write_an_offer_onto_a_variable_parent(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs_' );
		file_put_contents( $tmp, '<?xml version="1.0"?><yml_catalog><shop><offers><offer id="9845" available="false" group_id="9845"><price>50</price></offer></offers></shop></yml_catalog>' );

		F2000CS_Test_State::$products[40] = $this->variable_parent();
		update_post_meta( 40, '_stock_status', 'instock' );
		$GLOBALS['wpdb']->results_queue = array( array( (object) array( 'sku' => 'SUP_9845', 'ID' => 40 ) ) );

		( new XML_Stock_Updater( $tmp, 'SUP_' ) )->update_products_stock_status();
		unlink( $tmp );

		$this->assertSame( 'instock', get_post_meta( 40, '_stock_status', true ), 'A variable parent follows its variations, not one offer' );
		$this->assertSame( '', (string) get_post_meta( 40, '_price', true ) );
	}

	public function test_fields_update_skips_an_offer_matching_a_variable_parent(): void {
		F2000CS_Test_State::$products[40] = $this->variable_parent();
		$GLOBALS['wpdb']->results_queue   = array( array( (object) array( 'sku' => 'P_9845', 'ID' => 40 ) ) );

		$result = F2000CS_Test_Reflection::invoke(
			new Fields_Updater( 'x.xml', 'P_', array( 'name', 'attributes' ) ),
			'update_single_offer',
			array( simplexml_load_string( '<offer id="9845" group_id="9845"><name>Футболка M</name></offer>' ) )
		);

		$this->assertSame( 'skipped', $result );
	}
}
