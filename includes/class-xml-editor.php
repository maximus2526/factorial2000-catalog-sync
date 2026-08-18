<?php

namespace F2000CS;

use SimpleXMLElement;

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Editor
 *
 * Loads a source YML/XML export and produces a filtered copy containing
 * only the categories and offers selected in the "Редактор вигрузок XML"
 * admin UI. Works purely on the XML feed — no product database access.
 *
 * Data model:
 * - categories: id => [ 'name', 'parent' ] (parent '' means root; YML
 *   parentId="0" and dangling parents are normalized to '').
 * - offers:     id => [ 'title', 'price', 'old_price', 'available',
 *   'category_id', 'group_id', 'vendor_code', 'image' ].
 *
 * All ids are handled as strings internally so that numeric XML ids (the
 * norm in Prom.ua feeds) survive the PHP array-key → JSON round-trip.
 */
class XML_Editor {

	use XML_Rebuilder;

	/**
	 * Synthetic category id for offers without a categoryId element.
	 */
	const UNCATEGORIZED_ID = '__none__';

	/**
	 * Source XML path (local file) or URL.
	 *
	 * @var string
	 */
	protected string $source_path;

	/**
	 * Category map: id => [ 'name', 'parent' ].
	 *
	 * @var array<string, array{name: string, parent: string}>
	 */
	protected array $categories = array();

	/**
	 * Offer map: id => offer data.
	 *
	 * @var array<string, array>
	 */
	protected array $offers = array();

	/**
	 * Whether the source was parsed successfully.
	 *
	 * @var bool
	 */
	protected bool $loaded = false;

	/**
	 * Constructor.
	 *
	 * @param string $source_path Path to a local XML file or a feed URL.
	 */
	public function __construct( string $source_path ) {
		$this->source_path = $source_path;
	}

	/**
	 * Whether the source has been parsed successfully.
	 *
	 * @return bool
	 */
	public function is_loaded(): bool {
		return $this->loaded;
	}

	/**
	 * Stream-parse the source XML into lightweight category and offer maps.
	 *
	 * Uses XMLReader with per-element readOuterXML() so the full feed is
	 * never loaded into memory at once — safe for feeds with 50k+ offers.
	 *
	 * @return bool Whether parsing succeeded.
	 */
	public function load(): bool {
		if ( '' === $this->source_path || ! is_file( $this->source_path ) ) {
			return false;
		}

		return $this->parse();
	}

	/**
	 * Total number of offers in the source.
	 *
	 * @return int
	 */
	public function get_total_offers(): int {
		return $this->loaded ? count( $this->offers ) : 0;
	}

	// ------------------------------------------------------------------
	// Parsing
	// ------------------------------------------------------------------

	/**
	 * Parse categories and offers via XMLReader (streaming, low memory).
	 *
	 * @return bool Whether parsing succeeded.
	 */
	protected function parse(): bool {
		$reader = new \XMLReader();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is caught via the return-value check below.
		if ( ! @$reader->open( $this->source_path ) ) {
			return false;
		}

		$this->categories = array();
		$this->offers     = array();

		try {
			while ( $reader->read() ) {
				if ( \XMLReader::ELEMENT !== $reader->nodeType ) {
					continue;
				}

				if ( 'category' === $reader->name ) {
					$this->parse_category_element( $reader );
				} elseif ( 'offer' === $reader->name ) {
					$this->parse_offer_element( $reader );
				}
			}
		} finally {
			$reader->close();
		}

		$this->normalize_category_parents();
		$this->loaded = true;

		return true;
	}

	/**
	 * Parse a single <category> element while XMLReader is positioned on it.
	 *
	 * @param \XMLReader $reader Positioned on the category element.
	 * @return void
	 */
	private function parse_category_element( \XMLReader $reader ): void {
		$id     = $this->reader_attribute( $reader, 'id' );
		$parent = $this->reader_attribute( $reader, 'parentId' );

		if ( null === $id || '' === $id ) {
			return;
		}

		$reader->read();
		$name = \XMLReader::TEXT === $reader->nodeType ? trim( $reader->value ) : '';

		$this->categories[ $id ] = array(
			'name'   => $name,
			'parent' => null !== $parent ? (string) $parent : '',
		);
	}

	/**
	 * Case-insensitive attribute read from XMLReader (parentID / parentId).
	 *
	 * @param \XMLReader $reader Positioned on the element.
	 * @param string     $name   Desired attribute name (case-insensitive).
	 * @return string|null
	 */
	private function reader_attribute( \XMLReader $reader, string $name ): ?string {
		$name = str_replace( array( '_', '-' ), '', $name );

		if ( $reader->moveToFirstAttribute() ) {
			do {
				if ( 0 === strcasecmp( str_replace( array( '_', '-' ), '', $reader->name ), $name ) ) {
					return $reader->value;
				}
			} while ( $reader->moveToNextAttribute() );
		}

		return null;
	}

	/**
	 * Parse a single <offer> element while XMLReader is positioned on it.
	 *
	 * Uses readOuterXML() → SimpleXML on ONE element (safe mem per offer).
	 *
	 * @param \XMLReader $reader Positioned on the offer element.
	 * @return void
	 */
	private function parse_offer_element( \XMLReader $reader ): void {
		$offer = simplexml_load_string( $reader->readOuterXML(), null, LIBXML_NONET );

		if ( false === $offer ) {
			return;
		}

		$id = (string) $offer['id'];

		if ( '' === $id ) {
			return;
		}

		$name        = $this->child( $offer, 'name' );
		$name_ua     = $this->child( $offer, 'name_ua' );
		$price       = $this->child( $offer, 'price' );
		$old_price   = $this->child( $offer, 'oldprice' );
		$category_id = $this->child( $offer, 'categoryId' );
		$vendor_code = $this->child( $offer, 'vendorCode' );

		$this->offers[ $id ] = array(
			'title'       => $this->offer_title( $name, $name_ua ),
			'price'       => $price ? (float) $price : 0.0,
			'old_price'   => $old_price ? (float) $old_price : 0.0,
			'available'   => $this->offer_is_available( $offer ),
			'category_id' => $category_id ? (string) $category_id : '',
			'group_id'    => (string) ( $this->attribute( $offer, 'group_id' ) ?? '' ),
			'vendor_code' => $vendor_code ? (string) $vendor_code : '',
			'image'       => $this->offer_first_image( $offer ),
		);
	}

	/**
	 * Normalize root categories: parentId="0" and references to unknown
	 * parents are treated as roots (the tree would otherwise fall apart).
	 *
	 * @return void
	 */
	private function normalize_category_parents(): void {
		foreach ( $this->categories as $id => $data ) {
			$parent = $data['parent'];

			if ( '' === $parent || '0' === $parent || ! isset( $this->categories[ $parent ] ) ) {
				$this->categories[ $id ]['parent'] = '';
			}
		}
	}

	/**
	 * Case-insensitive child element lookup.
	 */
	private function child( SimpleXMLElement $element, string $name ): ?SimpleXMLElement {
		$name = str_replace( array( '_', '-' ), '', $name );
		foreach ( $element->children() as $child ) {
			if ( 0 === strcasecmp( str_replace( array( '_', '-' ), '', $child->getName() ), $name ) ) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * Case-insensitive attribute lookup.
	 */
	private function attribute( SimpleXMLElement $element, string $name ): ?string {
		$name = str_replace( array( '_', '-' ), '', $name );
		foreach ( $element->attributes() as $attr_name => $attr_value ) {
			if ( 0 === strcasecmp( str_replace( array( '_', '-' ), '', (string) $attr_name ), $name ) ) {
				return (string) $attr_value;
			}
		}
		return null;
	}

	/**
	 * Offer title with the name_ua override when present.
	 *
	 * @param SimpleXMLElement|null $name    name element.
	 * @param SimpleXMLElement|null $name_ua name_ua element.
	 * @return string
	 */
	private function offer_title( ?SimpleXMLElement $name, ?SimpleXMLElement $name_ua ): string {
		$title = $name ? (string) $name : '';

		if ( $name_ua && '' !== trim( (string) $name_ua ) ) {
			$title = (string) $name_ua;
		}

		return $title;
	}

	/**
	 * In-stock flag with tolerant parsing (feeds differ in case and format).
	 *
	 * @param SimpleXMLElement $offer Offer element.
	 * @return bool
	 */
	private function offer_is_available( SimpleXMLElement $offer ): bool {
		$available = strtolower( (string) ( $this->attribute( $offer, 'available' ) ?? '' ) );

		return in_array( $available, array( 'true', '1', 'yes' ), true );
	}

	/**
	 * First picture URL, or '' when the offer has no images.
	 *
	 * @param SimpleXMLElement $offer Offer element.
	 * @return string
	 */
	private function offer_first_image( SimpleXMLElement $offer ): string {
		foreach ( $offer->children() as $child ) {
			if ( 0 === strcasecmp( $child->getName(), 'picture' ) ) {
				$image = (string) $child;

				if ( '' !== $image ) {
					return $image;
				}
			}
		}

		return '';
	}

	// ------------------------------------------------------------------
	// Category tree
	// ------------------------------------------------------------------

	/**
	 * Map of parent category id => list of child category ids.
	 *
	 * @return array<string, array<string>>
	 */
	private function build_children_map(): array {
		$children = array();

		foreach ( $this->categories as $id => $data ) {
			$parent = $data['parent'];

			if ( '' !== $parent && isset( $this->categories[ $parent ] ) ) {
				$children[ $parent ][] = $id;
			}
		}

		return $children;
	}

	/**
	 * Flat category list with descendant offer counts, ready for the tree UI.
	 *
	 * Offers without a categoryId are grouped under the synthetic
	 * UNCATEGORIZED_ID entry so they can be selected too.
	 *
	 * @return array<int, array{id: string, name: string, parent: string, count: int, has_children: bool}>
	 */
	public function get_categories(): array {
		if ( ! $this->loaded ) {
			return array();
		}

		$children = $this->build_children_map();
		$counts   = $this->count_offers_per_category();
		$list     = array();

		foreach ( $this->categories as $id => $data ) {
			$list[] = array(
				// Cast to string: PHP turns numeric XML ids into int array
				// keys, and json_encode would emit numbers while parents stay
				// strings — breaking strict comparisons in the JS tree.
				'id'           => (string) $id,
				'name'         => $data['name'],
				'parent'       => $data['parent'],
				'count'        => $this->count_category_offers( (string) $id, $children, $counts ),
				'has_children' => ! empty( $children[ $id ] ),
			);
		}

		$uncategorized = $counts[ self::UNCATEGORIZED_ID ] ?? 0;

		if ( $uncategorized > 0 ) {
			$list[] = array(
				'id'           => self::UNCATEGORIZED_ID,
				'name'         => __( 'Без категорії', 'factorial2000-catalog-sync' ),
				'parent'       => '',
				'count'        => $uncategorized,
				'has_children' => false,
			);
		}

		return $list;
	}

	/**
	 * Direct offer count per category ('' mapped to UNCATEGORIZED_ID).
	 *
	 * @return array<string, int>
	 */
	private function count_offers_per_category(): array {
		$counts = array();

		foreach ( $this->offers as $data ) {
			$category            = $this->offer_category( $data );
			$counts[ $category ] = ( $counts[ $category ] ?? 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * Recursive offer count for a category including all descendants.
	 *
	 * @param string               $category_id Category id.
	 * @param array<string, array> $children    Map of parent => child ids.
	 * @param array<string, int>   $counts      Direct counts per category.
	 * @return int
	 */
	private function count_category_offers( string $category_id, array $children, array $counts ): int {
		$count = $counts[ $category_id ] ?? 0;

		foreach ( ( $children[ $category_id ] ?? array() ) as $child_id ) {
			$count += $this->count_category_offers( $child_id, $children, $counts );
		}

		return $count;
	}

	/**
	 * Effective category id of an offer ('' maps to the synthetic entry).
	 *
	 * @param array $data Offer data.
	 * @return string
	 */
	private function offer_category( array $data ): string {
		return '' === $data['category_id'] ? self::UNCATEGORIZED_ID : $data['category_id'];
	}

	// ------------------------------------------------------------------
	// Offer lookup
	// ------------------------------------------------------------------

	/**
	 * Offers matching the selected categories and conditions (paginated).
	 *
	 * @param array<string> $category_ids Category ids to include (empty = all).
	 * @param int           $limit        Max rows.
	 * @param int           $offset       Row offset.
	 * @param array         $conditions   Filter conditions (only_in_stock, min_price, max_price, search).
	 * @return array<int, array>
	 */
	public function get_offers( array $category_ids = array(), int $limit = 200, int $offset = 0, array $conditions = array() ): array {
		$rows  = array();
		$index = 0;

		foreach ( $this->offers as $id => $data ) {
			if ( ! $this->offer_matches( $id, $category_ids, $conditions ) ) {
				continue;
			}

			if ( $index < $offset ) {
				++$index;
				continue;
			}

			if ( count( $rows ) >= $limit ) {
				break;
			}

			++$index;

			$rows[] = array(
				'id'          => (string) $id,
				'title'       => $data['title'],
				'price'       => $data['price'],
				'old_price'   => $data['old_price'],
				'available'   => $data['available'],
				'category_id' => $data['category_id'],
				'group_id'    => $data['group_id'],
				'vendor_code' => $data['vendor_code'],
				'image'       => $data['image'],
			);
		}

		return $rows;
	}

	/**
	 * Count offers matching the selected categories and conditions.
	 *
	 * @param array<string> $category_ids Category ids to include (empty = all).
	 * @param array         $conditions   Filter conditions.
	 * @return int
	 */
	public function count_offers( array $category_ids = array(), array $conditions = array() ): int {
		$count = 0;

		foreach ( $this->offers as $id => $data ) {
			if ( $this->offer_matches( $id, $category_ids, $conditions ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Offer ids matching the selected categories and conditions.
	 *
	 * Used by the products "select all" toggle to deselect the whole set.
	 *
	 * @param array<string> $category_ids Category ids to include (empty = all).
	 * @param array         $conditions   Filter conditions.
	 * @return array<string>
	 */
	public function get_offer_ids( array $category_ids = array(), array $conditions = array() ): array {
		$ids = array();

		foreach ( $this->offers as $id => $data ) {
			if ( $this->offer_matches( $id, $category_ids, $conditions ) ) {
				$ids[] = (string) $id;
			}
		}

		return $ids;
	}

	/**
	 * Whether an offer matches the category filter and conditions.
	 *
	 * @param string        $offer_id     Offer id.
	 * @param array<string> $category_ids Category ids (empty = no category filter).
	 * @param array         $conditions   Filter conditions.
	 * @return bool
	 */
	private function offer_matches( string $offer_id, array $category_ids, array $conditions ): bool {
		if ( ! isset( $this->offers[ $offer_id ] ) ) {
			return false;
		}

		$data = $this->offers[ $offer_id ];

		if ( ! empty( $category_ids ) && ! in_array( $this->offer_category( $data ), $this->expand_category_ids( $category_ids ), true ) ) {
			return false;
		}

		return $this->matches_conditions( (string) $offer_id, $data, $conditions );
	}

	/**
	 * Whether offer data satisfies the filter conditions.
	 *
	 * @param string $offer_id   Offer id (used by the search condition).
	 * @param array  $data       Offer data.
	 * @param array  $conditions only_in_stock, min_price, max_price, search.
	 * @return bool
	 */
	private function matches_conditions( string $offer_id, array $data, array $conditions ): bool {
		if ( ! empty( $conditions['only_in_stock'] ) && ! $data['available'] ) {
			return false;
		}

		$min_price = isset( $conditions['min_price'] ) ? (float) $conditions['min_price'] : 0.0;
		if ( $min_price > 0 && $data['price'] < $min_price ) {
			return false;
		}

		$max_price = isset( $conditions['max_price'] ) ? (float) $conditions['max_price'] : 0.0;
		if ( $max_price > 0 && $data['price'] > $max_price ) {
			return false;
		}

		$search = isset( $conditions['search'] ) ? trim( (string) $conditions['search'] ) : '';

		if ( '' !== $search ) {
			// mb_strtolower handles Cyrillic case folding (stripos does not).
			$lower  = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
			$search = $lower( $search );
			$title  = $lower( $data['title'] );

			if ( false === strpos( $title, $search ) && false === strpos( $lower( $offer_id ), $search ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Expand selected category ids with all their descendants, so checking a
	 * parent category selects its whole subtree.
	 *
	 * @param array<string> $category_ids Selected category ids.
	 * @return array<string>
	 */
	private function expand_category_ids( array $category_ids ): array {
		if ( empty( $category_ids ) || empty( $this->categories ) ) {
			return $category_ids;
		}

		$children = $this->build_children_map();
		$expanded = array();

		foreach ( $category_ids as $category_id ) {
			$expanded[ $category_id ] = true;
			$this->collect_category_descendants( $category_id, $children, $expanded );
		}

		// Numeric XML ids become integer array keys in PHP; cast back to
		// strings so strict comparisons against parsed offer data work.
		$result = array();
		foreach ( array_keys( $expanded ) as $id ) {
			$result[] = (string) $id;
		}

		return $result;
	}

	/**
	 * Recursively collect descendant ids of a category.
	 *
	 * @param string               $category_id Category id.
	 * @param array<string, array> $children    Map of parent => child ids.
	 * @param array<string, bool>  $collected   Collected ids (by reference).
	 * @return void
	 */
	private function collect_category_descendants( string $category_id, array $children, array &$collected ): void {
		foreach ( ( $children[ $category_id ] ?? array() ) as $child_id ) {
			$collected[ $child_id ] = true;
			$this->collect_category_descendants( $child_id, $children, $collected );
		}
	}

	// ------------------------------------------------------------------
	// Filtered XML generation
	// ------------------------------------------------------------------

	/**
	 * Build the filtered XML string.
	 *
	 * Included offers: every offer of the selected categories (descendants
	 * included) plus explicitly checked offers, minus excluded ones, with the
	 * configured conditions applied. Keeps only the categories referenced by
	 * the kept offers (plus their ancestors) so the result stays a valid YML.
	 *
	 * @param array<string> $category_ids    Checked category ids.
	 * @param array<string> $extra_offer_ids Offers checked individually outside the checked categories.
	 * @param array<string> $excluded_ids    Offers unchecked within the checked categories.
	 * @param array         $options         only_in_stock, min_price, max_price, keep_oldprice, sku_prefix.
	 * @return array{xml: string, count: int}
	 */
	public function generate( array $category_ids = array(), array $extra_offer_ids = array(), array $excluded_ids = array(), array $options = array() ): array {
		if ( ! $this->loaded ) {
			return $this->empty_generation();
		}

		// Re-read the file only when generating (one-time operation).
		$xml = is_file( $this->source_path )
			? simplexml_load_file( $this->source_path, null, LIBXML_NONET )
			: simplexml_load_string( '', null, LIBXML_NONET );

		if ( false === $xml || ! isset( $xml->shop->offers ) ) {
			return $this->empty_generation();
		}

		$conditions = array(
			'only_in_stock' => ! empty( $options['only_in_stock'] ),
			'min_price'     => isset( $options['min_price'] ) ? (float) $options['min_price'] : 0.0,
			'max_price'     => isset( $options['max_price'] ) ? (float) $options['max_price'] : 0.0,
		);

		// Selecting a parent category includes its whole subtree.
		$category_ids = $this->expand_category_ids( $category_ids );

		$included = $this->collect_included_offers( $category_ids, $extra_offer_ids, $excluded_ids, $conditions );

		if ( empty( $included ) ) {
			return $this->empty_generation();
		}

		$keep_oldprice = ! empty( $options['keep_oldprice'] );
		$sku_prefix    = isset( $options['sku_prefix'] ) ? (string) $options['sku_prefix'] : '';

		$offers_data = $this->collect_offer_data( $xml, $included );

		unset( $xml->shop->offers->offer );

		$used_categories = $this->rebuild_offers( $xml, $offers_data, $keep_oldprice, $sku_prefix );

		$this->keep_referenced_categories( $xml, $used_categories );

		return array(
			'xml'   => $xml->asXML(),
			'count' => count( $included ),
		);
	}

	/**
	 * Empty generation result.
	 *
	 * @return array{xml: string, count: int}
	 */
	private function empty_generation(): array {
		return array(
			'xml'   => '',
			'count' => 0,
		);
	}

	/**
	 * Compute the set of offer ids to include in the output.
	 *
	 * @param array<string> $category_ids   Expanded category ids.
	 * @param array<string> $extra_offer_ids Individually checked offers.
	 * @param array<string> $excluded_ids    Offers to drop from the category set.
	 * @param array         $conditions      Filter conditions.
	 * @return array<string, bool>
	 */
	private function collect_included_offers( array $category_ids, array $extra_offer_ids, array $excluded_ids, array $conditions ): array {
		$included = array();

		foreach ( $this->offers as $id => $data ) {
			$in_category = ! empty( $category_ids ) && in_array( $this->offer_category( $data ), $category_ids, true );
			$in_extra    = in_array( (string) $id, $extra_offer_ids, true );

			if ( ( $in_category || $in_extra ) && $this->matches_conditions( (string) $id, $data, $conditions ) ) {
				$included[ (string) $id ] = true;
			}
		}

		foreach ( $excluded_ids as $excluded_id ) {
			unset( $included[ $excluded_id ] );
		}

		return $included;
	}

	/**
	 * Copy the kept offers from the parsed document into plain data arrays.
	 *
	 * @param SimpleXMLElement    $xml      Parsed YML document.
	 * @param array<string, bool> $included Included offer ids.
	 * @return array<int, array>
	 */
	private function collect_offer_data( SimpleXMLElement $xml, array $included ): array {
		$offers_data = array();

		foreach ( $xml->shop->offers->offer as $offer ) {
			$id = (string) $offer['id'];

			if ( ! isset( $included[ $id ] ) ) {
				continue;
			}

			$offers_data[] = $this->copy_offer_data( $offer );
		}

		return $offers_data;
	}

	/**
	 * Re-create the kept offers inside the document.
	 *
	 * @param SimpleXMLElement  $xml           Parsed YML document.
	 * @param array<int, array> $offers_data   Copied offer data.
	 * @param bool              $keep_oldprice Whether oldprice elements survive.
	 * @param string            $sku_prefix    Prefix applied to id/group_id.
	 * @return array<string, bool> Category ids referenced by the kept offers.
	 */
	private function rebuild_offers( SimpleXMLElement $xml, array $offers_data, bool $keep_oldprice, string $sku_prefix ): array {
		$used_categories = array();

		foreach ( $offers_data as $offer_data ) {
			$new_offer = $xml->shop->offers->addChild( 'offer' );
			$new_offer->addAttribute( 'id', $sku_prefix . $offer_data['id'] );

			if ( '' !== $offer_data['group_id'] ) {
				$new_offer->addAttribute( 'group_id', $sku_prefix . $offer_data['group_id'] );
			}

			if ( null !== $offer_data['available'] ) {
				$new_offer->addAttribute( 'available', $offer_data['available'] );
			}

			foreach ( $offer_data['children'] as $child ) {
				if ( ! $keep_oldprice && 'oldprice' === $child['name'] ) {
					continue;
				}

				$this->data_to_xml( $child, $new_offer );
			}

			if ( '' !== $offer_data['category_id'] ) {
				$used_categories[ $offer_data['category_id'] ] = true;
			}
		}

		return $used_categories;
	}

	/**
	 * Strip categories from the output XML that are not referenced by the
	 * kept offers; ancestors of referenced categories are preserved.
	 *
	 * @param SimpleXMLElement    $xml             Parsed YML document.
	 * @param array<string, bool> $used_categories Referenced category ids.
	 * @return void
	 */
	private function keep_referenced_categories( SimpleXMLElement $xml, array $used_categories ): void {
		if ( empty( $used_categories ) ) {
			return;
		}

		if ( ! isset( $xml->shop->categories ) || ! isset( $xml->shop->categories->category ) ) {
			return;
		}

		$keep = array();
		foreach ( array_keys( $used_categories ) as $category_id ) {
			$keep[ $category_id ] = true;

			$parent = isset( $this->categories[ $category_id ] ) ? $this->categories[ $category_id ]['parent'] : '';
			while ( '' !== $parent && isset( $this->categories[ $parent ] ) ) {
				$keep[ $parent ] = true;
				$parent          = $this->categories[ $parent ]['parent'];
			}
		}

		$categories_data = array();
		foreach ( $xml->shop->categories->category as $category ) {
			$id = (string) $category['id'];

			if ( ! isset( $keep[ $id ] ) ) {
				continue;
			}

			$categories_data[] = array(
				'id'        => $id,
				'parent_id' => (string) ( $this->attribute( $category, 'parentId' ) ?? '' ),
				'name'      => (string) $category,
			);
		}

		unset( $xml->shop->categories->category );

		foreach ( $categories_data as $category_data ) {
			$new_category = $xml->shop->categories->addChild( 'category', htmlspecialchars( $category_data['name'], ENT_QUOTES ) );
			$new_category->addAttribute( 'id', $category_data['id'] );

			if ( '' !== $category_data['parent_id'] ) {
				$new_category->addAttribute( 'parentId', $category_data['parent_id'] );
			}
		}
	}

	/**
	 * Copy an offer element into a plain data array (attributes + children).
	 *
	 * @param SimpleXMLElement $offer Offer element.
	 * @return array
	 */
	private function copy_offer_data( SimpleXMLElement $offer ): array {
		$children = array();
		foreach ( $offer->children() as $child ) {
			$children[] = $this->copy_element_data( $child );
		}

		$category = $this->child( $offer, 'categoryId' );

		return array(
			'id'          => (string) $offer['id'],
			'group_id'    => (string) ( $this->attribute( $offer, 'group_id' ) ?? '' ),
			'available'   => $this->attribute( $offer, 'available' ),
			'category_id' => $category ? (string) $category : '',
			'children'    => $children,
		);
	}

	/**
	 * Recursively copy an XML element into a data array.
	 *
	 * @param SimpleXMLElement $element Element.
	 * @return array
	 */
	private function copy_element_data( SimpleXMLElement $element ): array {
		$data = array(
			'name'       => $element->getName(),
			'value'      => (string) $element,
			'attributes' => array(),
			'children'   => array(),
		);

		foreach ( $element->attributes() as $attr_name => $attr_value ) {
			$data['attributes'][ (string) $attr_name ] = (string) $attr_value;
		}

		foreach ( $element->children() as $child ) {
			$data['children'][] = $this->copy_element_data( $child );
		}

		return $data;
	}

	// ------------------------------------------------------------------
	// Output storage
	// ------------------------------------------------------------------

	/**
	 * Save the generated XML into the plugin exports folder.
	 *
	 * @param string $xml_content Generated XML contents.
	 * @return array{success: bool, path: string, url: string}
	 */
	public function save( string $xml_content ): array {
		$export_dir = function_exists( 'f2000cs_ensure_exports_dir' )
			? f2000cs_ensure_exports_dir()
			: ( wp_upload_dir()['basedir'] . '/f2000cs-exports' );

		if ( ! wp_mkdir_p( $export_dir ) && ! is_dir( $export_dir ) ) {
			return array(
				'success' => false,
				'path'    => '',
				'url'     => '',
			);
		}

		$filename  = 'editor-xml-' . gmdate( 'Y-m-d-H-i-s' ) . '-' . wp_generate_password( 8, false, false ) . '.xml';
		$file_path = $export_dir . '/' . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Export file is written on demand; WP_Filesystem credentials are not guaranteed in this context.
		$result = file_put_contents( $file_path, $xml_content );

		if ( false === $result ) {
			return array(
				'success' => false,
				'path'    => '',
				'url'     => '',
			);
		}

		$url = function_exists( 'f2000cs_get_export_download_url' )
			? f2000cs_get_export_download_url( $filename )
			: '';

		if ( '' === $url ) {
			$upload_dir    = wp_upload_dir();
			$relative_path = str_replace( $upload_dir['basedir'], '', $file_path );
			$url           = $upload_dir['baseurl'] . $relative_path;
		}

		return array(
			'success' => true,
			'path'    => $file_path,
			'url'     => $url,
		);
	}
}
