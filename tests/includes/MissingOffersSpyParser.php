<?php
/**
 * Parser spy for the «offers missing on the site» paths.
 *
 * SKU lookups come from a map, and the steps that would need WooCommerce
 * (variations, simple products) are recorded instead of run.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Spy parser with a fake catalog.
 */
class F2000CS_MissingOffersSpyParser extends F2000CS_ImportSpyParser {

	/**
	 * Site catalog: SKU without prefix => post ID.
	 *
	 * @var array<string, int>
	 */
	public $site = array();

	/**
	 * Parent ID find_group_parent() returns.
	 *
	 * @var int
	 */
	public $group_parent = 0;

	/**
	 * Calls of create_product_variations(): [parent_id, skus, variation_attributes].
	 *
	 * @var array<int, array>
	 */
	public $variation_calls = array();

	/**
	 * Run the real variable import instead of recording it.
	 *
	 * @var bool
	 */
	public $real_variable = false;

	/**
	 * Run the real simple import instead of recording it.
	 *
	 * @var bool
	 */
	public $real_simple = false;

	/**
	 * @param array $skus SKUs without prefix.
	 * @return array
	 */
	protected function get_product_ids_by_skus( array $skus ): array {
		return array_intersect_key( $this->site, array_flip( array_map( 'strval', $skus ) ) );
	}

	/**
	 * @param string $group_id        Group id.
	 * @param array  $variations_data Offers.
	 * @return int
	 */
	protected function find_group_parent( string $group_id, array $variations_data ): int {
		return $this->group_parent;
	}

	/**
	 * @param int   $parent_id            Parent.
	 * @param array $variations_data      Offers.
	 * @param array $variation_attributes Attributes.
	 * @return int
	 */
	protected function create_product_variations( int $parent_id, array $variations_data, array $variation_attributes ): int {
		$this->variation_calls[] = array( $parent_id, array_map( 'strval', array_column( $variations_data, 'sku' ) ), $variation_attributes );
		return count( $variations_data );
	}

	/**
	 * @param array $offer_data Offer.
	 * @return bool
	 */
	protected function import_simple_product( array $offer_data ): bool {
		if ( $this->real_simple ) {
			return XML_Parser_Real_Simple::call( $this, $offer_data );
		}

		return parent::import_simple_product( $offer_data );
	}

	/**
	 * @param string $group_id        Group id.
	 * @param array  $variations_data Offers.
	 * @return bool
	 */
	protected function import_variable_product( string $group_id, array $variations_data ): bool {
		if ( $this->real_variable ) {
			return \Closure::bind(
				function ( $g, $v ) {
					return \F2000CS\XML_Parser::import_variable_product( $g, $v );
				},
				$this,
				\F2000CS\XML_Parser::class
			)( $group_id, $variations_data );
		}

		return parent::import_variable_product( $group_id, $variations_data );
	}

	/**
	 * Public access to protected helpers for assertions.
	 *
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	public function call( string $method, array $args = array() ) {
		return $this->$method( ...$args );
	}
}

/**
 * Calls XML_Parser::import_simple_product() past the spy overrides.
 */
final class XML_Parser_Real_Simple {

	/**
	 * @param \F2000CS\XML_Parser $parser     Parser.
	 * @param array               $offer_data Offer.
	 * @return bool
	 */
	public static function call( $parser, array $offer_data ): bool {
		return \Closure::bind(
			function ( $o ) {
				return \F2000CS\XML_Parser::import_simple_product( $o );
			},
			$parser,
			\F2000CS\XML_Parser::class
		)( $offer_data );
	}
}
