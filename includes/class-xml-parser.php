<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Parser
 *
 * Imports products from an XML file into WooCommerce.
 */
class XML_Parser {
	private string $xml_url;
	private array $categories = array(); // Cache for categories
	private array $term_cache = array(); // Cache for terms
	private array $sku_cache  = array(); // Cache for product SKUs

	/**
	 * XML_Parser constructor.
	 *
	 * @param string $file_path Path to the XML file.
	 */
	public function __construct( string $file_path ) {
		$this->xml_url = $file_path;
	}

	/**
	 * Imports products from XML.
	 *
	 * @param int $offset Offset for pagination.
	 * @param int $limit Number of products to import.
	 *
	 * @return array Import results.
	 * @throws Exception If the XML file can't be opened.
	 */
	public function import_products( int $offset = 0, int $limit = 10 ): array {
		if ( empty( $this->categories ) ) {
			$this->categories = $this->load_categories_from_xml();
			$this->preload_category_terms(); // Preload category terms
		}

		$reader = new XMLReader();

		if ( ! $reader->open( $this->xml_url ) ) {
			throw new Exception( 'Failed to open XML file.' );
		}

		$total_products = 0;
		while ( $reader->read() ) {
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'offer' ) {
				++$total_products;
			}
		}

		$reader->close();
		$reader->open( $this->xml_url );

		$imported       = 0;
		$skipped        = 0;
		$current_offset = 0;

		while ( $reader->read() ) {
			if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'offer' ) {
				continue;
			}

			if ( $current_offset < $offset ) {
				++$current_offset;
				continue;
			}

			if ( $imported >= $limit ) {
				break;
			}

			$offer = simplexml_load_string( $reader->readOuterXML() );

			$sku       = (string) $offer['id'];
			$title     = (string) $offer->name;
			$price     = (float) $offer->price;
			$old_price = isset($offer->oldprice) ? (float) $offer->oldprice : 0;
			$desc      = (string) $offer->description;
			$category  = (string) $offer->categoryId;
			$available = (string) $offer['available'] === 'true' ? 'instock' : 'outofstock';
			$vendor    = isset($offer->vendor) ? (string) $offer->vendor : '';

			// Get all product images
			$images = array();
			foreach ( $offer->picture as $picture ) {
				$images[] = (string) $picture;
			}

			// Get all product attributes
			$attributes = array();
			if ( isset( $offer->param ) ) {
				foreach ( $offer->param as $param ) {
					$name  = (string) $param['name'];
					$value = (string) $param;
					if ( ! empty( $name ) && ! empty( $value ) ) {
						$attributes[ $name ] = $value;
					}
				}
			}

			if ( empty( $sku ) || empty( $title ) || $price <= 0 ) {
				++$skipped;
				continue;
			}

			if ( $this->get_product_ids_by_skus( array( $sku ) ) ) {
				++$skipped;
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content' => $desc,
					'post_status'  => 'publish',
					'post_type'    => 'product',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, '_sku', $sku );
			
			// If old price exists and is greater than current price, set it as regular price and current as sale price
			if ($old_price > 0 && $old_price > $price) {
				update_post_meta( $post_id, '_regular_price', number_format($old_price, 2, '.', '') );
				update_post_meta( $post_id, '_sale_price', number_format($price, 2, '.', '') );
				update_post_meta( $post_id, '_price', number_format($price, 2, '.', '') );
			} else {
				// Otherwise just set current price as regular price
				update_post_meta( $post_id, '_regular_price', number_format($price, 2, '.', '') );
				update_post_meta( $post_id, '_price', number_format($price, 2, '.', '') );
			}
			
			update_post_meta( $post_id, '_stock_status', $available );
			update_post_meta( $post_id, '_manage_stock', 'no' );

			// Set vendor as product attribute if available
			if (!empty($vendor)) {
				// Add vendor to attributes list if it's not already there
				if (!isset($attributes['Виробник'])) {
					$attributes['Виробник'] = $vendor;
				}
			}

			// Handle product attributes
			if ( ! empty( $attributes ) ) {
				$this->set_product_attributes( $post_id, $attributes );
			}

			// Handle product images (gallery)
			if ( ! empty( $images ) ) {
				$this->handle_product_images( $post_id, $images );
			} else {
				// Fallback to old method if no images array
				$this->handle_product_image( $post_id, $img_url );
			}

			$this->set_product_category( $post_id, $category );

			++$imported;
		}

		$reader->close();

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'total'    => $total_products,
			'finished' => $offset + $imported >= $total_products,
		);
	}

	/**
	 * Preload existing category terms to avoid redundant database queries
	 */
	private function preload_category_terms(): void {
		// Get all existing product categories
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'all',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// Create a map of term names to term IDs
		$term_names = array();
		foreach ( $terms as $term ) {
			$term_names[ $term->name ] = $term->term_id;
		}

		// Map XML category IDs to WP term IDs where possible
		foreach ( $this->categories as $category_id => $category_data ) {
			$name = $category_data['name'];
			if ( isset( $term_names[ $name ] ) ) {
				$this->term_cache[ $category_id ] = $term_names[ $name ];
			}
		}
	}

	/**
	 * Loads categories from XML and builds a hierarchy.
	 *
	 * @return array Array of category data [id => ['name' => ..., 'parent' => ...]].
	 */
	private function load_categories_from_xml(): array {
		$categories = array();
		$reader     = new XMLReader();

		if ( ! $reader->open( $this->xml_url ) ) {
			return $categories;
		}

		while ( $reader->read() ) {
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'category' ) {
				$category_id = $reader->getAttribute( 'id' );
				$parent_id   = $reader->getAttribute( 'parentId' );
				$reader->read();
				$category_name = trim( $reader->value );

				if ( $category_id && $category_name ) {
					$categories[ $category_id ] = array(
						'name'   => $category_name,
						'parent' => $parent_id ?: null,
					);
				}
			}
		}

		$reader->close();
		return $categories;
	}

	/**
	 * Assigns a product to the correct category by its XML category ID.
	 *
	 * @param int    $post_id     Product ID.
	 * @param string $category_id XML category ID.
	 */
	private function set_product_category( int $post_id, string $category_id ): void {
		if ( ! isset( $this->categories[ $category_id ] ) ) {
			return;
		}

		$term_id = $this->ensure_category_term( $category_id, $this->categories, $this->term_cache );
		if ( $term_id ) {
			wp_set_object_terms( $post_id, array( (int) $term_id ), 'product_cat' );
		}
	}

	/**
	 * Recursively ensures a product category term exists and returns its term ID.
	 *
	 * @param string $category_id Category ID from XML.
	 * @param array  $categories  Full list of categories from XML.
	 * @param array  $cache       Reference to already created term cache.
	 *
	 * @return int|null The term ID or null on failure.
	 */
	private function ensure_category_term( string $category_id, array $categories, array &$cache ): ?int {
		if ( isset( $cache[ $category_id ] ) ) {
			return $cache[ $category_id ];
		}

		if ( ! isset( $categories[ $category_id ] ) ) {
			return null;
		}

		$name      = $categories[ $category_id ]['name'];
		$parent_id = $categories[ $category_id ]['parent'];

		$parent_term_id = null;
		if ( $parent_id ) {
			$parent_term_id = $this->ensure_category_term( $parent_id, $categories, $cache );
		}

		// First check if we already have a term with this name
		$term = get_term_by( 'name', $name, 'product_cat' );
		if ( ! $term ) {
			$term = wp_insert_term(
				$name,
				'product_cat',
				array(
					'parent' => $parent_term_id ?? 0,
				)
			);

			if ( is_wp_error( $term ) ) {
				return null;
			}

			$term_id = $term['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		$cache[ $category_id ] = $term_id;
		return $term_id;
	}

	/**
	 * Downloads and attaches the product image.
	 *
	 * @param int    $post_id Product ID.
	 * @param string $url     Image URL.
	 */
	private function handle_product_image( int $post_id, string $url ): void {
		if ( empty( $url ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return;
		}

		$file_array = array(
			'name'     => basename( $url ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return;
		}

		set_post_thumbnail( $post_id, $attachment_id );
	}

	/**
	 * Sets product attributes.
	 *
	 * @param int   $post_id    Product ID.
	 * @param array $attributes Array of attributes [name => value].
	 */
	private function set_product_attributes( int $post_id, array $attributes ): void {
		if ( empty( $attributes ) ) {
			return;
		}

		$product_attributes = array();

		foreach ( $attributes as $name => $value ) {
			// Ensure the attribute name is not too long
			$attr_name = substr( $name, 0, 28 ); // 28 to allow for "pa_" prefix

			// Create a sanitized version of the name for the attribute taxonomy
			$taxonomy_name = wc_sanitize_taxonomy_name( $attr_name ); // Use WooCommerce's method
			$taxonomy      = 'pa_' . $taxonomy_name; // Add standard WooCommerce prefix

			if ( strlen( $taxonomy ) > 32 ) {
				// If still too long, truncate further
				$taxonomy = substr( $taxonomy, 0, 32 );
			}

			// Check if this attribute taxonomy exists
			$attribute_id = wc_attribute_taxonomy_id_by_name( $taxonomy_name );

			if ( ! $attribute_id ) {
				// Create the attribute if it doesn't exist
				wc_create_attribute(
					array(
						'name'         => $attr_name,
						'slug'         => $taxonomy_name,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);

				// Register the taxonomy
				$taxonomy_register_name = wc_attribute_taxonomy_name( $taxonomy_name );

				// Make sure we're not exceeding length limit
				if ( strlen( $taxonomy_register_name ) <= 32 ) {
					register_taxonomy(
						$taxonomy_register_name,
						array( 'product' ),
						array(
							'labels'       => array(
								'name' => $attr_name,
							),
							'hierarchical' => false,
							'show_ui'      => true,
							'query_var'    => true,
							'rewrite'      => false,
						)
					);
				}
			}

			// Either get or create the term
			$term = get_term_by( 'name', $value, $taxonomy );
			if ( ! $term ) {
				$term_info = wp_insert_term( $value, $taxonomy );
				if ( ! is_wp_error( $term_info ) ) {
					$term_id = $term_info['term_id'];
				}
			} else {
				$term_id = $term->term_id;
			}

			// Set the product attribute
			if ( isset( $term_id ) ) {
				wp_set_object_terms( $post_id, array( $term_id ), $taxonomy );
			}

			// Add to product_attributes array
			$product_attributes[ $taxonomy ] = array(
				'name'         => $taxonomy,
				'value'        => '',
				'position'     => 0,
				'is_visible'   => 1,
				'is_variation' => 0,
				'is_taxonomy'  => 1,
			);
		}

		// Save the product attributes
		if ( ! empty( $product_attributes ) ) {
			update_post_meta( $post_id, '_product_attributes', $product_attributes );
		}
	}

	/**
	 * Downloads and attaches multiple product images (first as featured, others as gallery).
	 *
	 * @param int   $post_id Product ID.
	 * @param array $urls    Array of image URLs.
	 */
	private function handle_product_images( int $post_id, array $urls ): void {
		if ( empty( $urls ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids     = array();
		$featured_image_set = false;

		foreach ( $urls as $index => $url ) {
			$tmp = download_url( $url );

			if ( is_wp_error( $tmp ) ) {
				continue;
			}

			$file_array = array(
				'name'     => basename( $url ),
				'tmp_name' => $tmp,
			);

			$attachment_id = media_handle_sideload( $file_array, $post_id );

			if ( is_wp_error( $attachment_id ) ) {
				@unlink( $tmp );
				continue;
			}

			$attachment_ids[] = $attachment_id;

			// Set the first image as featured image
			if ( $index === 0 && ! $featured_image_set ) {
				set_post_thumbnail( $post_id, $attachment_id );
				$featured_image_set = true;
			}
		}

		// Save additional images as product gallery
		if ( count( $attachment_ids ) > 1 ) {
			// Remove the featured image from gallery array (it's already set as featured)
			$gallery_attachment_ids = array_slice( $attachment_ids, 1 );

			if ( ! empty( $gallery_attachment_ids ) ) {
				update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery_attachment_ids ) );
			}
		}
	}

	/**
	 * Retrieves product IDs by their SKUs.
	 *
	 * @param array $skus Array of SKUs.
	 *
	 * @return array Array of [sku => product ID].
	 */
	private function get_product_ids_by_skus( array $skus ): array {
		global $wpdb;

		if ( empty( $skus ) ) {
			return array();
		}

		// Check cache first for each SKU
		$result        = array();
		$uncached_skus = array();

		foreach ( $skus as $sku ) {
			if ( isset( $this->sku_cache[ $sku ] ) ) {
				$result[ $sku ] = $this->sku_cache[ $sku ];
			} else {
				$uncached_skus[] = $sku;
			}
		}

		// Only query the database for SKUs not in cache
		if ( ! empty( $uncached_skus ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $uncached_skus ), '%s' ) );
			$sql          = $wpdb->prepare(
				"SELECT pm.meta_value AS sku, p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('product', 'product_variation')
				AND pm.meta_key = '_sku'
				AND pm.meta_value IN ($placeholders)",
				$uncached_skus
			);

			$db_results = $wpdb->get_results( $sql );
			$db_results = array_column( $db_results, 'ID', 'sku' );

			// Add to cache
			foreach ( $db_results as $sku => $id ) {
				$this->sku_cache[ $sku ] = $id;
			}

			$result = array_merge( $result, $db_results );
		}

		return $result;
	}
}
