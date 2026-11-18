<?php
/**
 * Missing_Offers_Importer: adds the feed offers that are not on the site during the stock sync (Pro).
 *
 * Premium-only file (listed in @fs_premium_only of the main plugin file).
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

use XMLReader;

defined( 'ABSPATH' ) || exit;

/**
 * Imports the in-stock offers of a supplier feed that matched no product.
 *
 * - an offer of a group that already is a variable product → new variation of it;
 * - an offer of a group imported as separate simple products → new simple product;
 * - anything else → new product (variable for a group of 2+, simple otherwise).
 *
 * New products are created as private, so nothing appears in the shop before the
 * owner reviews it. A product this importer created and the owner later deleted or
 * trashed is never created again.
 */
class Missing_Offers_Importer extends XML_Parser {

	/**
	 * Option (+ slot) with the SKUs this importer created: SKU => time.
	 */
	const REGISTRY_OPTION = 'f2000cs_auto_imported_';

	/**
	 * Supplier slot.
	 *
	 * @var int
	 */
	private int $slot;

	/**
	 * Price adjustment of the slot (type, direction, value).
	 *
	 * @var array
	 */
	private array $price_adjust;

	/**
	 * Whether the slot keeps prices untouched.
	 *
	 * @var bool
	 */
	private bool $skip_price;

	/**
	 * Whether the feed's <oldprice> is used as the regular price (no sale).
	 *
	 * @var bool
	 */
	private bool $ignore_discounts;

	/**
	 * @param string $file_path        Local copy of the feed.
	 * @param string $sku_prefix       Slot SKU prefix.
	 * @param int    $slot             Supplier slot 1+.
	 * @param array  $price_adjust     Slot price adjustment.
	 * @param bool   $skip_price       «Do not change prices» of the slot.
	 * @param bool   $import_stock_qty Write quantities (the slot updates them).
	 * @param bool   $ignore_discounts Use <oldprice> as the regular price, create no sale.
	 */
	public function __construct( string $file_path, string $sku_prefix, int $slot, array $price_adjust = array(), bool $skip_price = false, bool $import_stock_qty = false, bool $ignore_discounts = false ) {
		parent::__construct( $file_path, false, $sku_prefix, false, $import_stock_qty, true );

		$this->slot             = $slot;
		$this->price_adjust     = $price_adjust;
		$this->skip_price       = $skip_price;
		$this->ignore_discounts = $ignore_discounts;

		$this->set_product_status( 'private' );
	}

	/**
	 * New products get the price the stock sync would write (slot price adjustment).
	 *
	 * @param array $offer_data Offer data.
	 * @return array
	 */
	protected function prepare_offer_data( array $offer_data ): array {
		if ( $this->ignore_discounts
			&& (float) ( $offer_data['price'] ?? 0 ) > 0
			&& (float) ( $offer_data['old_price'] ?? 0 ) > (float) $offer_data['price'] ) {
			$offer_data['price']     = (float) $offer_data['old_price'];
			$offer_data['old_price'] = 0;
		}

		if ( $this->skip_price || (float) ( $this->price_adjust['value'] ?? 0 ) <= 0 ) {
			return $offer_data;
		}

		list( $offer_data['price'], $offer_data['old_price'] ) = f2000cs_adjust_price_pair(
			(float) ( $offer_data['price'] ?? 0 ),
			(float) ( $offer_data['old_price'] ?? 0 ),
			$this->price_adjust
		);

		return $offer_data;
	}

	/**
	 * Import the given offers (at most $limit groups / single offers per call).
	 *
	 * @param string[] $skus     Offer ids without prefix that matched no product.
	 * @param int      $limit    Max groups / single offers to handle in this call.
	 * @param float    $deadline Unix time to stop by (0 = no limit).
	 * @return array{products:int,variations:int,left:int}
	 */
	public function import_missing( array $skus, int $limit, float $deadline = 0 ): array {
		$result = array(
			'products'   => 0,
			'variations' => 0,
			'left'       => 0,
		);

		$wanted = array_flip( array_map( 'strval', $skus ) );
		$wanted = array_diff_key( $wanted, $this->rejected_skus( array_keys( $wanted ) ) );

		if ( empty( $wanted ) || $limit < 1 ) {
			return $result;
		}

		$this->ensure_categories_loaded();

		$units    = $this->collect_units( $wanted );
		$registry = (array) get_option( self::REGISTRY_OPTION . $this->slot, array() );

		foreach ( array_values( $units ) as $i => $unit ) {
			if ( $i >= $limit || ( $deadline > 0 && microtime( true ) >= $deadline ) ) {
				$result['left'] = count( $units ) - $i;
				break;
			}

			$new_parent = $this->import_unit( $unit );
			$created    = $this->created_ids( array_column( $unit['missing'], 'sku' ) );

			foreach ( $created as $sku => $id ) {
				$registry[ (string) $sku ] = time();

				if ( 'product_variation' === get_post_type( $id ) ) {
					++$result['variations'];
				} else {
					++$result['products'];
				}
			}

			if ( $new_parent && $created ) {
				++$result['products'];
			}
		}

		update_option( self::REGISTRY_OPTION . $this->slot, $registry, false );

		return $result;
	}

	/**
	 * Group the wanted in-stock offers into units: a feed group (2+ offers) or a single offer.
	 *
	 * Reads the feed twice: first only offer ids and group ids (cheap), then the full
	 * data of the offers that matter, so a 10k-offer feed is never held in memory.
	 *
	 * @param array<string,int> $wanted Offer ids (keys) to import.
	 * @return array<string,array{group:string,all:array,missing:array}>
	 */
	protected function collect_units( array $wanted ): array {
		$group_of = array();
		$sizes    = array();

		$this->each_offer(
			function ( XMLReader $reader ) use ( &$group_of, &$sizes ) {
				$group = trim( (string) $reader->getAttribute( 'group_id' ) );
				if ( '' !== $group ) {
					$group_of[ (string) $reader->getAttribute( 'id' ) ] = $group;
					$sizes[ $group ]                                    = ( $sizes[ $group ] ?? 0 ) + 1;
				}
				return false;
			}
		);

		$groups = array();
		foreach ( array_keys( $wanted ) as $sku ) {
			$group = $group_of[ (string) $sku ] ?? '';
			if ( '' !== $group && ( $sizes[ $group ] ?? 0 ) > 1 ) {
				$groups[ $group ] = true;
			}
		}

		$units = array();

		$this->each_offer(
			function ( XMLReader $reader ) use ( $wanted, $group_of, $groups ) {
				$sku = (string) $reader->getAttribute( 'id' );
				return isset( $wanted[ $sku ] ) || isset( $groups[ $group_of[ $sku ] ?? '' ] );
			},
			function ( array $offer ) use ( &$units, $wanted, $groups ) {
				$sku   = (string) $offer['sku'];
				$group = (string) $offer['group_id'];
				$key   = isset( $groups[ $group ] ) ? 'g:' . $group : 's:' . $sku;

				if ( ! isset( $units[ $key ] ) ) {
					$units[ $key ] = array(
						'group'   => isset( $groups[ $group ] ) ? $group : '',
						'all'     => array(),
						'missing' => array(),
					);
				}

				$units[ $key ]['all'][] = $offer;

				if ( isset( $wanted[ $sku ] ) && $this->offer_in_stock( $offer ) ) {
					$units[ $key ]['missing'][] = $offer;
				}
			}
		);

		return array_filter(
			$units,
			static function ( $unit ) {
				return ! empty( $unit['missing'] );
			}
		);
	}

	/**
	 * Walk the offers of the feed.
	 *
	 * @param callable      $select  fn( XMLReader ): bool — whether to read the offer in full.
	 * @param callable|null $collect fn( array $offer_data ) for selected offers.
	 * @return void
	 */
	protected function each_offer( callable $select, ?callable $collect = null ): void {
		$reader = new XMLReader();

		if ( ! $reader->open( $this->xml_url, null, LIBXML_NONET ) ) {
			return;
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();

		while ( $reader->read() ) {
			if ( XMLReader::ELEMENT !== $reader->nodeType || 'offer' !== $reader->name ) {
				continue;
			}

			if ( ! $select( $reader ) || null === $collect ) {
				continue;
			}

			$offer = f2000cs_reader_element( $reader, $dom );
			if ( null !== $offer ) {
				$collect( $this->prepare_offer_data( $this->extract_offer_data( $offer ) ) );
			}
		}

		$reader->close();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
	}

	/**
	 * Create what a unit needs.
	 *
	 * @param array $unit Unit from collect_units().
	 * @return bool Whether a new variable parent was created.
	 */
	protected function import_unit( array $unit ): bool {
		if ( '' === $unit['group'] ) {
			foreach ( $unit['missing'] as $offer ) {
				$this->import_simple_product( $offer );
			}
			return false;
		}

		$plan = $this->classify_group( $unit['group'], $unit['all'], $this->lookup_existing( array_column( $unit['all'], 'sku' ) ) );

		if ( 'variations' === $plan['mode'] ) {
			$this->add_variations_to_parent( $plan['parent'], $unit['missing'] );
			return false;
		}

		if ( 'simple' === $plan['mode'] ) {
			foreach ( $unit['missing'] as $offer ) {
				$this->import_simple_product( $offer );
			}
			return false;
		}

		if ( count( $unit['missing'] ) > 1 && $this->import_variable_product( $unit['group'], $unit['missing'] ) ) {
			return true;
		}

		// One offer in stock, or offers that do not differ in any attribute: simple products.
		foreach ( $unit['missing'] as $offer ) {
			$this->import_simple_product( $offer );
		}

		return false;
	}

	/**
	 * Offers that must not be created again: this importer made them before and the
	 * owner deleted them, or they sit in the trash.
	 *
	 * @param string[] $skus Offer ids without prefix (none of them is on the site).
	 * @return array<string,bool>
	 */
	protected function rejected_skus( array $skus ): array {
		$registry = (array) get_option( self::REGISTRY_OPTION . $this->slot, array() );
		$rejected = array_intersect_key( array_fill_keys( $skus, true ), $registry );

		foreach ( $this->trashed_skus( $skus ) as $sku ) {
			$rejected[ (string) $sku ] = true;
		}

		return $rejected;
	}

	/**
	 * Offer ids (without prefix) whose product or variation is in the trash.
	 *
	 * @param string[] $skus Offer ids without prefix.
	 * @return string[]
	 */
	protected function trashed_skus( array $skus ): array {
		global $wpdb;

		$prefix  = (string) $this->sku_prefix;
		$trashed = array();

		foreach ( array_chunk( $skus, 500 ) as $chunk ) {
			$prefixed     = array_map(
				static function ( $sku ) use ( $prefix ) {
					return $prefix . $sku;
				},
				$chunk
			);
			$placeholders = implode( ',', array_fill( 0, count( $prefixed ), '%s' ) );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pm.meta_value FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
					WHERE p.post_type IN ('product', 'product_variation') AND p.post_status = 'trash'
					AND pm.meta_value IN ($placeholders)",
					$prefixed
				)
			);
			// phpcs:enable

			foreach ( (array) $found as $sku ) {
				$trashed[] = '' !== $prefix ? (string) substr( (string) $sku, strlen( $prefix ) ) : (string) $sku;
			}
		}

		return $trashed;
	}

	/**
	 * Which of the offers exist on the site now (fresh lookup, no cache).
	 *
	 * @param string[] $skus Offer ids without prefix.
	 * @return array<string,int> Offer id => post ID.
	 */
	protected function created_ids( array $skus ): array {
		$prefix = (string) $this->sku_prefix;
		$found  = f2000cs_get_product_ids_by_skus(
			array_map(
				static function ( $sku ) use ( $prefix ) {
					return $prefix . $sku;
				},
				array_map( 'strval', $skus )
			)
		);

		$created = array();
		foreach ( $found as $sku => $id ) {
			$created[ '' !== $prefix ? (string) substr( (string) $sku, strlen( $prefix ) ) : (string) $sku ] = (int) $id;
		}

		return $created;
	}
}
