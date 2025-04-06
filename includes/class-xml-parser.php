<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Parser
 *
 * Imports products from an XML file into WooCommerce.
 */
class XML_Parser {
	private string $xml_url;

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
	 * Assigns a product category.
	 *
	 * @param int    $post_id     Product ID.
	 * @param string $category_id Category ID from the XML.
	 */
	private function set_product_category( int $post_id, string $category_id ): void {
		$category_name = $this->get_category_name_by_id( $category_id );

		if ( ! $category_name ) {
			return;
		}

		$term = term_exists( $category_name, 'product_cat' );

		if ( ! $term ) {
			$term = wp_insert_term( $category_name, 'product_cat' );
			if ( is_wp_error( $term ) ) {
				return;
			}
		}

		$term_id = is_array( $term ) ? $term['term_id'] : $term;
		wp_set_object_terms( $post_id, array( $term_id ), 'product_cat' );
	}

	/**
	 * Retrieves the category name by its ID.
	 *
	 * @param string $category_id Category ID from the XML.
	 *
	 * @return string|null Category name or null if not found.
	 */
	private function get_category_name_by_id( string $category_id ): ?string {
		static $category_mapping = null;

		if ( $category_mapping === null ) {
			$category_mapping = $this->load_categories_from_xml();
		}

		return $category_mapping[ $category_id ] ?? null;
	}

	/**
	 * Loads all categories from the XML file.
	 *
	 * @return array Array of [id => name].
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
				$reader->read();
				$category_name = trim( $reader->value );

				if ( $category_id && $category_name ) {
					$categories[ $category_id ] = $category_name;
				}
			}
		}

		$reader->close();
		return $categories;
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

		$placeholders = implode( ',', array_fill( 0, count( $skus ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT pm.meta_value AS sku, p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ('product', 'product_variation')
			AND pm.meta_key = '_sku'
			AND pm.meta_value IN ($placeholders)",
			$skus
		);

		$results = $wpdb->get_results( $sql );
		return array_column( $results, 'ID', 'sku' );
	}
}
