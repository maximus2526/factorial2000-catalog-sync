<?php
/**
 * WooCommerce → feed units.
 *
 * Products are read page by page (variable products first, then everything
 * else) and turned into "rows" shaped like the WooCommerce product CSV export,
 * which is what the feed builders consume. This keeps the feed logic independent
 * of WooCommerce internals and lets it be tested against real CSV exports.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce unit source.
 */
class Marketplace_WC_Source implements Marketplace_Unit_Source {

	/**
	 * Feed settings (include_unpublished, mpn_meta_key).
	 *
	 * @var array<string,mixed>
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Feed settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Passes of a build.
	 *
	 * @return array<int,string>
	 */
	public function phases(): array {
		return array( 'variable', 'simple' );
	}

	/**
	 * Post statuses to read.
	 *
	 * @return array<int,string>
	 */
	private function statuses(): array {
		return '1' === (string) ( $this->settings['include_unpublished'] ?? '0' )
			? array( 'publish', 'draft', 'pending' )
			: array( 'publish' );
	}

	/**
	 * Read one page of a pass.
	 *
	 * @param string $phase  "variable" or "simple".
	 * @param int    $offset Cursor: the last product id of the previous page (0 at the start).
	 * @param int    $limit  Page size.
	 * @return array{units:array<int,array>,next:int,done:bool}
	 */
	public function fetch( string $phase, int $offset, int $limit ): array {
		// Paging by id, not by offset: a stock sync that drafts products between two
		// steps would shift an offset and silently skip products.
		$after = static function ( $where ) use ( $offset ) {
			global $wpdb;

			return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $offset );
		};

		add_filter( 'posts_where', $after );

		try {
			$ids = (array) wc_get_products(
				array(
					'type'    => 'variable' === $phase ? array( 'variable' ) : array( 'simple', 'external' ),
					'status'  => $this->statuses(),
					'limit'   => $limit,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'return'  => 'ids',
				)
			);
		} finally {
			remove_filter( 'posts_where', $after );
		}

		$next = $ids ? max( array_map( 'intval', $ids ) ) : $offset;

		$units = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );

			if ( ! $product ) {
				continue;
			}

			$unit = 'variable' === $phase ? $this->group_unit( $product ) : array(
				'kind' => 'simple',
				'row'  => $this->row( $product ),
			);

			if ( $unit ) {
				$units[] = $unit;
			}
		}

		return array(
			'units' => $units,
			'next'  => $next,
			'done'  => count( $ids ) < $limit,
		);
	}

	/**
	 * A variable product with its variations.
	 *
	 * @param \WC_Product $parent_product Variable product.
	 * @return array|null
	 */
	private function group_unit( $parent_product ) {
		$parent_row = $this->row( $parent_product );
		$children   = array();

		foreach ( $this->child_ids( $parent_product ) as $child_id ) {
			$variation = wc_get_product( (int) $child_id );

			if ( ! $variation || 'variation' !== $variation->get_type() ) {
				continue;
			}

			$children[] = $this->row( $variation, $parent_product, $parent_row );
		}

		if ( ! $children ) {
			return null;
		}

		return array(
			'kind'     => 'group',
			'parent'   => $parent_row,
			'children' => $children,
		);
	}

	/**
	 * Variation ids; with unpublished products allowed, draft variations too
	 * (get_children() lists only published and private ones).
	 *
	 * @param \WC_Product $parent_product Variable product.
	 * @return array<int,int>
	 */
	private function child_ids( $parent_product ): array {
		if ( '1' !== (string) ( $this->settings['include_unpublished'] ?? '0' ) ) {
			return array_map( 'intval', (array) $parent_product->get_children() );
		}

		return array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'        => 'product_variation',
					'post_parent'      => $parent_product->get_id(),
					'post_status'      => array( 'publish', 'draft', 'pending' ),
					'orderby'          => array(
						'menu_order' => 'ASC',
						'ID'         => 'ASC',
					),
					'fields'           => 'ids',
					'numberposts'      => -1,
					'suppress_filters' => true,
				)
			)
		);
	}

	// ------------------------------------------------------------------
	// Rows
	// ------------------------------------------------------------------

	/**
	 * Row for a product or variation.
	 *
	 * @param \WC_Product      $product    Product.
	 * @param \WC_Product|null $parent_product     Parent (for variations).
	 * @param array|null       $parent_row Parent row (for variations).
	 * @return array<string,string>
	 */
	public function row( $product, $parent_product = null, $parent_row = null ): array {
		$is_variation = null !== $parent_product;
		$status       = (string) $product->get_status();

		// A variation of an unpublished product is not published either.
		$published = 'publish' === $status && ( ! $is_variation || 'publish' === (string) $parent_product->get_status() );

		$backorders = (string) $product->get_backorders();
		$stock      = $product->managing_stock() ? $product->get_stock_quantity() : null;

		$on_sale = (bool) $product->is_on_sale();

		$row = array(
			'ID'                      => (string) $product->get_id(),
			'Type'                    => (string) $product->get_type(),
			'SKU'                     => (string) $product->get_sku(),
			'GTIN, UPC, EAN, or ISBN' => $this->gtin( $product ),
			'Name'                    => (string) $product->get_name(),
			'Published'               => $published ? '1' : '0',
			'Short description'       => (string) $product->get_short_description( 'edit' ),
			'Description'             => (string) $product->get_description( 'edit' ),
			'In stock?'               => $product->is_in_stock() ? '1' : '0',
			'Stock'                   => null === $stock ? '' : (string) $stock,
			'Backorders allowed?'     => 'yes' === $backorders ? '1' : ( 'notify' === $backorders ? 'notify' : '0' ),
			'Sale price'              => $on_sale ? (string) $product->get_sale_price( 'edit' ) : '',
			'Regular price'           => (string) $product->get_regular_price( 'edit' ),
			'Categories'              => $is_variation ? '' : $this->categories( $product ),
			'Images'                  => $this->images( $product ),
			'Parent'                  => $is_variation ? $this->parent_ref( $parent_product ) : '',
			'Brands'                  => $is_variation ? '' : $this->brands( $product ),
			'Meta: mpn'               => $this->mpn( $product, $is_variation ? $parent_product : null ),
		);

		$attributes = $is_variation ? $this->variation_attributes( $product, $parent_product ) : $this->product_attributes( $product );
		$i          = 0;

		foreach ( $attributes as $attr ) {
			if ( ++$i > 13 ) {
				break;
			}

			$row[ "Attribute {$i} name" ]     = $attr['name'];
			$row[ "Attribute {$i} value(s)" ] = $attr['value'];
			$row[ "Attribute {$i} visible" ]  = $attr['visible'];
		}

		return $row;
	}

	/**
	 * GTIN / EAN (WooCommerce 9.2+ field, else the legacy meta).
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function gtin( $product ): string {
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			return (string) $product->get_global_unique_id( 'edit' );
		}

		return (string) $product->get_meta( '_global_unique_id', true );
	}

	/**
	 * Parent reference like the WooCommerce CSV ("SKU" or "id:123").
	 *
	 * @param \WC_Product $parent_product Parent product.
	 * @return string
	 */
	private function parent_ref( $parent_product ): string {
		$sku = (string) $parent_product->get_sku();

		return '' !== $sku ? $sku : 'id:' . $parent_product->get_id();
	}

	/**
	 * Escape commas inside a value that will be joined with ", ".
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function esc( string $value ): string {
		return str_replace( ',', '\\,', $value );
	}

	/**
	 * Category paths "Parent > Child", deepest last (a product gets its deepest category).
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function categories( $product ): string {
		$paths = array();

		foreach ( (array) $product->get_category_ids() as $term_id ) {
			$term_id = (int) $term_id;
			$names   = array();

			foreach ( array_reverse( (array) get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( (int) $ancestor_id, 'product_cat' );

				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$names[] = $this->esc( (string) $ancestor->name );
				}
			}

			$term = get_term( $term_id, 'product_cat' );

			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $this->esc( (string) $term->name );
			}

			if ( $names ) {
				$paths[] = $names;
			}
		}

		// Stable sort by depth: shallow first, deepest last.
		$paths = Marketplace_Text::sorted_by(
			$paths,
			static function ( $p ) {
				return array( count( $p ) );
			}
		);

		return implode(
			', ',
			array_map(
				static function ( $p ) {
					return implode( ' > ', $p );
				},
				$paths
			)
		);
	}

	/**
	 * Picture URLs: the main image, then the gallery.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function images( $product ): string {
		$ids = array();

		if ( $product->get_image_id() ) {
			$ids[] = (int) $product->get_image_id();
		}

		foreach ( (array) $product->get_gallery_image_ids() as $gallery_id ) {
			$ids[] = (int) $gallery_id;
		}

		$urls = array();

		foreach ( array_unique( $ids ) as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );

			if ( $url ) {
				$urls[] = $this->esc( Marketplace_Text::encode_url( (string) $url ) );
			}
		}

		return implode( ', ', $urls );
	}

	/**
	 * Brand names from the first brand taxonomy that has terms for the product.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function brands( $product ): string {
		/**
		 * Brand taxonomies to look at, in order.
		 *
		 * @param array<int,string> $taxonomies Taxonomy slugs.
		 */
		$taxonomies = (array) apply_filters( 'f2000cs_marketplace_brand_taxonomies', array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand', 'brand' ) );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = wp_get_object_terms( $product->get_id(), $taxonomy, array( 'fields' => 'names' ) );

			if ( is_array( $terms ) && $terms ) {
				return implode( ', ', array_map( array( $this, 'esc' ), array_map( 'strval', $terms ) ) );
			}
		}

		return '';
	}

	/**
	 * Supplier code (MPN) from the configured meta key(s), variation → parent.
	 *
	 * @param \WC_Product      $product Product.
	 * @param \WC_Product|null $parent_product  Parent product.
	 * @return string
	 */
	private function mpn( $product, $parent_product ): string {
		// An empty setting means "do not send an MPN".
		$chosen = (string) ( $this->settings['mpn_meta_key'] ?? 'f2000cs-updater-vendor' );

		if ( '' === $chosen ) {
			return '';
		}

		$keys = array( $chosen );

		foreach ( array_filter( array( $product, $parent_product ) ) as $source ) {
			foreach ( $keys as $key ) {
				$value = $source->get_meta( $key, true );

				if ( is_scalar( $value ) && '' !== (string) $value ) {
					return (string) $value;
				}
			}
		}

		return '';
	}

	/**
	 * Attributes of a parent product: name, all options, visible flag.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<int, array{name:string,value:string,visible:string}>
	 */
	private function product_attributes( $product ): array {
		$out = array();

		foreach ( (array) $product->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'is_taxonomy' ) ) {
				continue;
			}

			$values = array();

			if ( $attribute->is_taxonomy() ) {
				$name = (string) wc_attribute_label( $attribute->get_name() );

				foreach ( (array) $attribute->get_terms() as $term ) {
					$values[] = (string) $term->name;
				}
			} else {
				$name = (string) $attribute->get_name();

				foreach ( (array) $attribute->get_options() as $option ) {
					$values[] = (string) $option;
				}
			}

			$out[] = array(
				'name'    => $name,
				'value'   => implode( ', ', $values ),
				'visible' => $attribute->get_visible() ? '1' : '0',
			);
		}

		return $out;
	}

	/**
	 * Chosen attribute values of a variation (empty values, "any", are skipped).
	 *
	 * @param \WC_Product $variation Variation.
	 * @param \WC_Product $parent_product    Parent product.
	 * @return array<int, array{name:string,value:string,visible:string}>
	 */
	private function variation_attributes( $variation, $parent_product ): array {
		// Original names of custom attributes by their sanitized key.
		$custom = array();

		foreach ( (array) $parent_product->get_attributes() as $attribute ) {
			if ( is_object( $attribute ) && method_exists( $attribute, 'is_taxonomy' ) && ! $attribute->is_taxonomy() ) {
				$custom[ sanitize_title( (string) $attribute->get_name() ) ] = (string) $attribute->get_name();
			}
		}

		$out = array();

		foreach ( (array) $variation->get_attributes() as $key => $value ) {
			$value = (string) $value;

			if ( '' === $value ) {
				continue;
			}

			if ( taxonomy_exists( (string) $key ) ) {
				$name = (string) wc_attribute_label( (string) $key );
				$term = get_term_by( 'slug', $value, (string) $key );

				if ( $term && ! is_wp_error( $term ) ) {
					$value = (string) $term->name;
				}
			} else {
				$name = $custom[ (string) $key ] ?? (string) $key;
			}

			$out[] = array(
				'name'    => $name,
				'value'   => $value,
				'visible' => '1',
			);
		}

		return $out;
	}
}
