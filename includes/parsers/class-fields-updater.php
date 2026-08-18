<?php

namespace F2000CS;

use Exception;
use XMLReader;
use SimpleXMLElement;

defined( 'ABSPATH' ) || exit;

/**
 * Class Fields_Updater
 *
 * Updates individual, user-selected fields (name, description, price, images, etc.)
 * of already-imported WooCommerce products/variations, matching offers by SKU
 * (same SKU-with-prefix logic used by XML_Parser / XML_Stock_Updater).
 *
 * Extends XML_Parser to reuse its XML/category/attribute/image helpers instead
 * of duplicating them.
 */
class Fields_Updater extends XML_Parser {

	/**
	 * Whitelisted field keys that can be updated.
	 *
	 * @var string[]
	 */
	public const ALLOWED_FIELDS = array(
		'name',
		'description',
		'short_description',
		'tags',
		'price',
		'oldprice',
		'status',
		'stock_quantity',
		'images',
		'attributes',
		'categories',
		'vendorCode',
	);

	/**
	 * Sanitized list of fields to update for this run.
	 *
	 * @var string[]
	 */
	private array $fields = array();

	/**
	 * Fields_Updater constructor.
	 *
	 * @param string $file_path  Path to the XML file.
	 * @param string $sku_prefix Prefix to add to SKU values when matching products.
	 * @param array  $fields     Field keys to update (subset of self::ALLOWED_FIELDS).
	 */
	public function __construct( string $file_path, string $sku_prefix, array $fields ) {
		parent::__construct( $file_path, false, $sku_prefix );

		$this->fields = array_values( array_intersect( self::ALLOWED_FIELDS, $fields ) );
	}

	/**
	 * Process one chunk of offers, updating the selected fields for matching products.
	 *
	 * @param int $offset Number of offers already processed in previous chunks.
	 * @param int $limit  Number of offers to process in this chunk.
	 * @return array{processed:int,total:int,finished:bool,updated:int,not_found:int,skipped:int}
	 * @throws Exception If the XML file can't be opened.
	 */
	public function update_fields( int $offset = 0, int $limit = 1 ): array {
		if ( empty( $this->fields ) ) {
			return array(
				'processed' => 0,
				'total'     => 0,
				'finished'  => true,
				'updated'   => 0,
				'not_found' => 0,
				'skipped'   => 0,
			);
		}

		if ( in_array( 'categories', $this->fields, true ) ) {
			$this->ensure_categories_loaded();
		}

		$total = $this->count_total_offers();

		$reader = new XMLReader();
		if ( ! $reader->open( $this->xml_url ) ) {
			throw new Exception( 'Failed to open XML file.' );
		}

		$position  = 0;
		$processed = 0;
		$updated   = 0;
		$not_found = 0;
		$skipped   = 0;

		while ( $reader->read() ) {
			if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'offer' ) {
				continue;
			}

			if ( $position < $offset ) {
				++$position;
				continue;
			}

			if ( $processed >= $limit ) {
				break;
			}

			$offer  = simplexml_load_string( $reader->readOuterXML(), null, LIBXML_NONET );
			$result = $this->update_single_offer( $offer );

			switch ( $result ) {
				case 'updated':
					++$updated;
					break;
				case 'not_found':
					++$not_found;
					break;
				default:
					++$skipped;
					break;
			}

			++$position;
			++$processed;
		}

		$reader->close();

		return array(
			'processed' => $processed,
			'total'     => $total,
			'finished'  => ( 0 === $processed ) || ( ( $offset + $processed ) >= $total ),
			'updated'   => $updated,
			'not_found' => $not_found,
			'skipped'   => $skipped,
		);
	}

	/**
	 * Count total <offer> elements in the XML (cheap pass, no SimpleXML parsing).
	 *
	 * @return int
	 */
	private function count_total_offers(): int {
		$count  = 0;
		$reader = new XMLReader();

		if ( ! $reader->open( $this->xml_url ) ) {
			return 0;
		}

		while ( $reader->read() ) {
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'offer' ) {
				++$count;
			}
		}

		$reader->close();

		return $count;
	}

	/**
	 * Apply the selected field updates to the product matching this offer's SKU.
	 *
	 * @param SimpleXMLElement $offer Offer element.
	 * @return string 'updated', 'not_found' or 'skipped'.
	 */
	private function update_single_offer( SimpleXMLElement $offer ): string {
		$sku = (string) $offer['id'];

		if ( '' === $sku ) {
			return 'skipped';
		}

		$product_ids = $this->get_product_ids_by_skus( array( $sku ) );
		$product_id  = $product_ids[ $sku ] ?? 0;

		if ( ! $product_id ) {
			return 'not_found';
		}

		$post_type    = get_post_type( $product_id );
		$is_variation = ( 'product_variation' === $post_type );
		$changed      = false;

		if ( in_array( 'name', $this->fields, true ) ) {
			$changed = $this->update_title( $product_id, $offer ) || $changed;
		}

		if ( in_array( 'description', $this->fields, true ) ) {
			$changed = $this->update_description( $product_id, $offer ) || $changed;
		}

		if ( in_array( 'short_description', $this->fields, true ) ) {
			$changed = $this->update_short_description( $product_id, $offer ) || $changed;
		}

		if ( in_array( 'tags', $this->fields, true ) ) {
			$changed = $this->update_tags( $product_id, $offer ) || $changed;
		}

		if ( in_array( 'price', $this->fields, true ) || in_array( 'oldprice', $this->fields, true ) ) {
			$changed = $this->update_price_fields( $product_id, $offer ) || $changed;
		}

		if ( in_array( 'status', $this->fields, true ) ) {
			$changed = $this->update_status( $product_id, $offer, $is_variation ) || $changed;
		}

		if ( in_array( 'stock_quantity', $this->fields, true ) ) {
			$changed = $this->update_stock_quantity( $product_id, $offer ) || $changed;
		}

		if ( in_array( 'images', $this->fields, true ) ) {
			$changed = $this->update_images( $product_id, $offer, $is_variation ) || $changed;
		}

		if ( in_array( 'attributes', $this->fields, true ) ) {
			$changed = $this->update_attributes( $product_id, $offer, $is_variation ) || $changed;
		}

		if ( in_array( 'categories', $this->fields, true ) && ! $is_variation ) {
			$changed = $this->update_categories( $product_id, $offer ) || $changed;
		}

		if ( in_array( 'vendorCode', $this->fields, true ) ) {
			$changed = $this->update_vendor_code( $product_id, $offer ) || $changed;
		}

		if ( $changed ) {
			wc_delete_product_transients( $product_id );
		}

		return $changed ? 'updated' : 'skipped';
	}

	/**
	 * @param int              $product_id Product/variation post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether the title changed.
	 */
	private function update_title( int $product_id, SimpleXMLElement $offer ): bool {
		$title = (string) $offer->name;

		if ( ! empty( $offer->name_ua ) ) {
			$title = (string) $offer->name_ua;
		}

		$title = trim( wp_strip_all_tags( $title ) );

		if ( '' === $title || get_the_title( $product_id ) === $title ) {
			return false;
		}

		$result = wp_update_post(
			array(
				'ID'         => $product_id,
				'post_title' => $title,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			f2000cs_log( sprintf( 'Failed to update title for product #%d: %s', $product_id, $result->get_error_message() ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * @param int              $product_id Product post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether the description changed.
	 */
	private function update_description( int $product_id, SimpleXMLElement $offer ): bool {
		$desc = (string) $offer->description;

		if ( ! empty( $offer->description_ua ) ) {
			$desc = (string) $offer->description_ua;
		}

		$desc = wp_kses_post( $desc );

		if ( '' === trim( $desc ) ) {
			return false;
		}

		if ( get_post_field( 'post_content', $product_id ) === $desc ) {
			return false;
		}

		$result = wp_update_post(
			array(
				'ID'           => $product_id,
				'post_content' => $desc,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			f2000cs_log( sprintf( 'Failed to update description for product #%d: %s', $product_id, $result->get_error_message() ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Only applies if the XML explicitly provides a short_description(_ua) tag,
	 * since this isn't part of the standard YML/Prom offer format.
	 *
	 * @param int              $product_id Product post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether the short description changed.
	 */
	private function update_short_description( int $product_id, SimpleXMLElement $offer ): bool {
		$short_desc = '';

		if ( isset( $offer->short_description ) ) {
			$short_desc = (string) $offer->short_description;
		}

		if ( isset( $offer->short_description_ua ) && '' !== trim( (string) $offer->short_description_ua ) ) {
			$short_desc = (string) $offer->short_description_ua;
		}

		$short_desc = wp_kses_post( $short_desc );

		if ( '' === trim( $short_desc ) ) {
			return false;
		}

		if ( get_post_field( 'post_excerpt', $product_id ) === $short_desc ) {
			return false;
		}

		$result = wp_update_post(
			array(
				'ID'           => $product_id,
				'post_excerpt' => $short_desc,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			f2000cs_log( sprintf( 'Failed to update short description for product #%d: %s', $product_id, $result->get_error_message() ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Reads <keywords> (Prom.ua "ключові запити") and assigns them as product tags.
	 *
	 * @param int              $product_id Product post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether the tags changed.
	 */
	private function update_tags( int $product_id, SimpleXMLElement $offer ): bool {
		if ( ! isset( $offer->keywords ) ) {
			return false;
		}

		$raw = (string) $offer->keywords;

		if ( '' === trim( $raw ) ) {
			return false;
		}

		$tags = array_filter( array_map( 'trim', preg_split( '/[,;]+/', $raw ) ) );

		if ( empty( $tags ) ) {
			return false;
		}

		$existing = wp_get_object_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
		$existing = is_wp_error( $existing ) ? array() : $existing;

		sort( $tags );
		$existing_sorted = $existing;
		sort( $existing_sorted );

		if ( $tags === $existing_sorted ) {
			return false;
		}

		wp_set_object_terms( $product_id, $tags, 'product_tag', false );

		return true;
	}

	/**
	 * Updates regular/sale price based on which of 'price' / 'oldprice' fields were selected.
	 *
	 * @param int              $product_id Product/variation post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether a price changed.
	 */
	private function update_price_fields( int $product_id, SimpleXMLElement $offer ): bool {
		$want_price    = in_array( 'price', $this->fields, true );
		$want_oldprice = in_array( 'oldprice', $this->fields, true );

		$price     = isset( $offer->price ) ? (float) $offer->price : 0;
		$old_price = isset( $offer->oldprice ) ? (float) $offer->oldprice : 0;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$changed = false;

		if ( $want_price && $want_oldprice && $price > 0 ) {
			if ( $old_price > 0 && $old_price > $price ) {
				$regular = number_format( $old_price, 2, '.', '' );
				$sale    = number_format( $price, 2, '.', '' );

				if ( $product->get_regular_price() !== $regular || $product->get_sale_price() !== $sale ) {
					update_post_meta( $product_id, '_regular_price', $regular );
					update_post_meta( $product_id, '_sale_price', $sale );
					update_post_meta( $product_id, '_price', $sale );
					$changed = true;
				}
			} else {
				$regular = number_format( $price, 2, '.', '' );

				if ( $product->get_regular_price() !== $regular || '' !== $product->get_sale_price() ) {
					update_post_meta( $product_id, '_regular_price', $regular );
					update_post_meta( $product_id, '_price', $regular );
					delete_post_meta( $product_id, '_sale_price' );
					$changed = true;
				}
			}
		} elseif ( $want_price && $price > 0 ) {
			$regular = number_format( $price, 2, '.', '' );

			if ( $product->get_regular_price() !== $regular ) {
				update_post_meta( $product_id, '_regular_price', $regular );

				if ( '' === $product->get_sale_price() ) {
					update_post_meta( $product_id, '_price', $regular );
				}

				$changed = true;
			}
		} elseif ( $want_oldprice && $old_price > 0 ) {
			$regular = number_format( $old_price, 2, '.', '' );

			if ( $product->get_regular_price() !== $regular ) {
				update_post_meta( $product_id, '_regular_price', $regular );
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * @param int              $product_id   Product/variation post ID.
	 * @param SimpleXMLElement $offer        Offer element.
	 * @param bool             $is_variation Whether the matched post is a variation.
	 * @return bool Whether the stock status changed.
	 */
	private function update_status( int $product_id, SimpleXMLElement $offer, bool $is_variation ): bool {
		$stock_status = f2000cs_parse_available( (string) $offer['available'] );

		if ( get_post_meta( $product_id, '_stock_status', true ) === $stock_status ) {
			return false;
		}

		f2000cs_update_stock_status( $product_id, $stock_status );

		if ( $is_variation ) {
			$parent_id = (int) wp_get_post_parent_id( $product_id );
			if ( $parent_id ) {
				$this->sync_parent_stock_status( $parent_id );
			}
		}

		return true;
	}

	/**
	 * Recompute a variable parent's aggregate stock status from its variations.
	 *
	 * @param int $parent_id Parent product ID.
	 * @return void
	 */
	private function sync_parent_stock_status( int $parent_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time variation stock count; cache would return stale data.
		$instock_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_parent = %d
				  AND p.post_type = 'product_variation'
				  AND p.post_status IN ('publish','private')
				  AND pm.meta_key = '_stock_status'
				  AND pm.meta_value = 'instock'",
				$parent_id
			)
		);

		$status = $instock_count > 0 ? 'instock' : 'outofstock';
		f2000cs_update_stock_status( $parent_id, $status );
		wc_delete_product_transients( $parent_id );
	}

	/**
	 * @param int              $product_id Product/variation post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether the stock quantity changed.
	 */
	private function update_stock_quantity( int $product_id, SimpleXMLElement $offer ): bool {
		$quantity = null;

		if ( isset( $offer->quantity ) && '' !== trim( (string) $offer->quantity ) ) {
			$quantity = max( 0, (int) $offer->quantity );
		} elseif ( isset( $offer->stock_quantity ) && '' !== trim( (string) $offer->stock_quantity ) ) {
			$quantity = max( 0, (int) $offer->stock_quantity );
		}

		if ( null === $quantity ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		if ( $product->get_manage_stock() && (int) $product->get_stock_quantity() === $quantity ) {
			return false;
		}

		$product->set_manage_stock( true );
		$product->set_stock_quantity( $quantity );
		$product->save();

		return true;
	}

	/**
	 * @param int              $product_id   Product/variation post ID.
	 * @param SimpleXMLElement $offer        Offer element.
	 * @param bool             $is_variation Whether the matched post is a variation.
	 * @return bool Whether images were (re)downloaded and attached.
	 */
	private function update_images( int $product_id, SimpleXMLElement $offer, bool $is_variation ): bool {
		$images = array();

		foreach ( $offer->picture as $picture ) {
			$url = (string) $picture;
			if ( '' !== $url ) {
				$images[] = $url;
			}
		}

		if ( empty( $images ) ) {
			return false;
		}

		if ( $is_variation ) {
			$this->set_variation_image( $product_id, $images[0] );
		} else {
			$this->handle_product_images( $product_id, $images );
		}

		return true;
	}

	/**
	 * Extract <param name="…"> attributes (plus vendor) from the offer.
	 *
	 * @param SimpleXMLElement $offer Offer element.
	 * @return array<string,string> Attribute name => value.
	 */
	private function extract_attributes( SimpleXMLElement $offer ): array {
		$attributes = array();

		if ( isset( $offer->param ) ) {
			foreach ( $offer->param as $param ) {
				$name  = (string) $param['name'];
				$value = (string) $param;

				if ( '' !== $name && '' !== $value ) {
					$attributes[ $name ] = $value;
				}
			}
		}

		if ( isset( $offer->vendor ) ) {
			$vendor = (string) $offer->vendor;
			if ( '' !== $vendor && ! isset( $attributes['Виробник'] ) ) {
				$attributes['Виробник'] = $vendor;
			}
		}

		return $attributes;
	}

	/**
	 * @param int              $product_id   Product/variation post ID.
	 * @param SimpleXMLElement $offer        Offer element.
	 * @param bool             $is_variation Whether the matched post is a variation.
	 * @return bool Whether attributes changed.
	 */
	private function update_attributes( int $product_id, SimpleXMLElement $offer, bool $is_variation ): bool {
		$attributes = $this->extract_attributes( $offer );

		if ( empty( $attributes ) ) {
			return false;
		}

		if ( $is_variation ) {
			return $this->update_variation_attributes( $product_id, $attributes );
		}

		$this->set_product_attributes( $product_id, $attributes );

		return true;
	}

	/**
	 * Update per-variation attribute meta (attribute_pa_xxx), creating missing
	 * taxonomy terms as needed, without touching the parent's attribute definitions.
	 *
	 * @param int   $variation_id Variation post ID.
	 * @param array $attributes   Attribute name => value pairs.
	 * @return bool Whether any attribute meta changed.
	 */
	private function update_variation_attributes( int $variation_id, array $attributes ): bool {
		$changed = false;

		foreach ( $attributes as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}

			$taxonomy_name = $this->sanitize_for_taxonomy( $name );
			$taxonomy      = 'pa_' . $taxonomy_name;

			if ( strlen( $taxonomy ) > 32 ) {
				$taxonomy = substr( $taxonomy, 0, 32 );
			}

			if ( ! taxonomy_exists( $taxonomy ) ) {
				$this->ensure_global_attribute( $name, $taxonomy_name );
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'labels'       => array( 'name' => $name ),
						'hierarchical' => false,
						'show_ui'      => true,
						'query_var'    => true,
						'rewrite'      => false,
					)
				);
			}

			$term = get_term_by( 'name', $value, $taxonomy );
			if ( ! $term ) {
				$term_info = $this->insert_term_with_slug( $value, $taxonomy );
				if ( is_wp_error( $term_info ) ) {
					continue;
				}
				$slug = get_term( $term_info['term_id'], $taxonomy )->slug;
			} else {
				$slug = $term->slug;
			}

			$meta_key = 'attribute_' . $taxonomy;
			if ( get_post_meta( $variation_id, $meta_key, true ) !== $slug ) {
				update_post_meta( $variation_id, $meta_key, $slug );
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * @param int              $product_id Product post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether the category assignment changed.
	 */
	private function update_categories( int $product_id, SimpleXMLElement $offer ): bool {
		$category_id = isset( $offer->categoryId ) ? (string) $offer->categoryId : '';

		if ( '' === $category_id ) {
			return false;
		}

		$before = wp_get_object_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$before = is_wp_error( $before ) ? array() : $before;

		$this->set_product_category( $product_id, $category_id );

		$after = wp_get_object_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		$after = is_wp_error( $after ) ? array() : $after;

		sort( $before );
		sort( $after );

		return $before !== $after;
	}

	/**
	 * @param int              $product_id Product/variation post ID.
	 * @param SimpleXMLElement $offer      Offer element.
	 * @return bool Whether the vendor code meta changed.
	 */
	private function update_vendor_code( int $product_id, SimpleXMLElement $offer ): bool {
		$vendor_code = isset( $offer->vendorCode ) ? (string) $offer->vendorCode : '';

		if ( '' === $vendor_code ) {
			return false;
		}

		if ( get_post_meta( $product_id, 'f2000cs-updater-vendor', true ) === $vendor_code ) {
			return false;
		}

		update_post_meta( $product_id, 'f2000cs-updater-vendor', $vendor_code );

		return true;
	}
}
