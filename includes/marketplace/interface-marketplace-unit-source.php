<?php
/**
 * Source of feed units (see Marketplace_Feed for the unit shapes).
 *
 * The WooCommerce implementation reads products in pages; tests plug in arrays.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Paged unit provider.
 */
interface Marketplace_Unit_Source {

	/**
	 * Ordered names of the passes a build goes through ("variable", "simple").
	 *
	 * @return array<int, string>
	 */
	public function phases(): array;

	/**
	 * Read one page of a pass.
	 *
	 * @param string $phase  Pass name.
	 * @param int    $offset Number of entries already consumed in this pass.
	 * @param int    $limit  Page size.
	 * @return array{units:array<int,array>,consumed:int,done:bool}
	 */
	public function fetch( string $phase, int $offset, int $limit ): array;
}
