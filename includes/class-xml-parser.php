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
	private array $sku_cache = array(); // Cache for product SKUs

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
			$desc      = (string) $offer->description;
			$img_url   = (string) $offer->picture;
			$category  = (string) $offer->categoryId;
			$available = (string) $offer['available'] === 'true' ? 'instock' : 'outofstock';

			if ( empty( $sku ) || empty( $title ) || $price <= 0 || $available === 'outofstock' ) {
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
			update_post_meta( $post_id, '_regular_price', $price );
			update_post_meta( $post_id, '_price', $price );
			update_post_meta( $post_id, '_stock_status', $available );
			update_post_meta( $post_id, '_manage_stock', 'no' );

			$this->handle_product_image( $post_id, $img_url );
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
		$terms = get_terms([
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
			'fields' => 'all'
		]);

		if (is_wp_error($terms) || empty($terms)) {
			return;
		}

		// Create a map of term names to term IDs
		$term_names = [];
		foreach ($terms as $term) {
			$term_names[$term->name] = $term->term_id;
		}

		// Map XML category IDs to WP term IDs where possible
		foreach ($this->categories as $category_id => $category_data) {
			$name = $category_data['name'];
			if (isset($term_names[$name])) {
				$this->term_cache[$category_id] = $term_names[$name];
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
		$result = [];
		$uncached_skus = [];
		
		foreach ($skus as $sku) {
			if (isset($this->sku_cache[$sku])) {
				$result[$sku] = $this->sku_cache[$sku];
			} else {
				$uncached_skus[] = $sku;
			}
		}
		
		// Only query the database for SKUs not in cache
		if (!empty($uncached_skus)) {
			$placeholders = implode( ',', array_fill( 0, count( $uncached_skus ), '%s' ) );
			$sql = $wpdb->prepare(
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
			foreach ($db_results as $sku => $id) {
				$this->sku_cache[$sku] = $id;
			}
			
			$result = array_merge($result, $db_results);
		}

		return $result;
	}
}
