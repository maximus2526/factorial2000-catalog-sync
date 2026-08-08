<?php
/**
 * Spy XML_Parser for offline import_products mode tests.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\XML_Parser;

/**
 * Records which import helpers were called without creating products.
 */
class F2000CS_ImportSpyParser extends XML_Parser {

	/**
	 * SKUs passed to import_simple_product().
	 *
	 * @var array<int, string>
	 */
	public $simple_skus = array();

	/**
	 * group_ids passed to import_variable_product().
	 *
	 * @var array<int, string>
	 */
	public $variable_groups = array();

	/**
	 * Return value for simple imports.
	 *
	 * @var bool
	 */
	public $simple_return = true;

	/**
	 * Return value for variable imports.
	 *
	 * @var bool
	 */
	public $variable_return = true;

	/**
	 * Skip category preload for offline fixtures.
	 *
	 * @return void
	 */
	protected function ensure_categories_loaded(): void {
		$this->categories = array();
		$this->currencies = array();
	}

	/**
	 * Record simple import attempts.
	 *
	 * @param array $offer_data Offer data.
	 * @return bool
	 */
	protected function import_simple_product( array $offer_data ): bool {
		$this->simple_skus[] = (string) ( $offer_data['sku'] ?? '' );
		return $this->simple_return;
	}

	/**
	 * Record variable import attempts.
	 *
	 * @param string $group_id        Group id.
	 * @param array  $variations_data Variations.
	 * @return bool
	 */
	protected function import_variable_product( string $group_id, array $variations_data ): bool {
		$this->variable_groups[] = $group_id;
		return $this->variable_return;
	}
}
