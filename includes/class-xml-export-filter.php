<?php

namespace F2CS;

use Exception;
use SimpleXMLElement;

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Export_Filter
 *
 * Filters XML export to remove products that already exist on the site.
 */
class XML_Export_Filter {
	private string $xml_url;
	private string $sku_prefix;
	private float $min_price;
	private array $site_skus = array();
	private int $removed_count = 0;
	private string $filtered_xml_path = '';

	/**
	 * XML_Export_Filter constructor.
	 *
	 * @param string $xml_path Path to the XML file (URL or local file path).
	 * @param string $sku_prefix SKU prefix for site products.
	 * @param float $min_price Minimum price for filtering products.
	 */
	public function __construct( string $xml_path, string $sku_prefix = 'NEW_', float $min_price = 0 ) {
		$this->xml_url = $xml_path;
		$this->sku_prefix = $sku_prefix;
		$this->min_price = $min_price;
	}

	/**
	 * Create filtered XML file.
	 *
	 * @return array Result array with success status, download URL, and removed count.
	 */
	public function create_filtered_xml(): array {
		try {
			// Step 1: Get site products SKUs
			$this->get_site_products_skus();
			
			if ( empty( $this->site_skus ) ) {
				return array(
					'success' => false,
					'error' => 'На сайті не знайдено товарів з вказаним SKU префіксом.',
					'removed_count' => 0,
					'download_url' => ''
				);
			}

			// Step 2: Download and parse XML
			$xml_content = $this->fetch_xml_content();
			if ( ! $xml_content ) {
				return array(
					'success' => false,
					'error' => 'Не вдалося завантажити XML файл.',
					'removed_count' => 0,
					'download_url' => ''
				);
			}

			// Step 3: Filter XML content
			$filtered_content = $this->filter_xml_content( $xml_content );

			// Step 4: Save filtered XML
			$saved_path = $this->save_filtered_xml( $filtered_content );
			if ( ! $saved_path ) {
				return array(
					'success' => false,
					'error' => 'Не вдалося зберегти очищений XML файл.',
					'removed_count' => 0,
					'download_url' => ''
				);
			}

			// Step 5: Create download URL
			$download_url = $this->create_download_url( $saved_path );

			// Log the process
			$this->log_export_process();

			return array(
				'success' => true,
				'error' => '',
				'removed_count' => $this->removed_count,
				'download_url' => $download_url
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error' => 'Помилка: ' . $e->getMessage(),
				'removed_count' => 0,
				'download_url' => ''
			);
		}
	}

	/**
	 * Get SKUs of products from the site.
	 *
	 * @return void
	 */
	private function get_site_products_skus(): void {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value AS sku
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'draft', 'private')
			AND pm.meta_key = '_sku'
			AND pm.meta_value != ''
			AND pm.meta_value IS NOT NULL
			AND pm.meta_value LIKE %s",
			$wpdb->esc_like( $this->sku_prefix ) . '%'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql is already prepared above; live data required.
		$results = $wpdb->get_col( $sql );
		
		// Remove prefix from SKUs for comparison with XML
		foreach ( $results as $sku ) {
			$original_sku = ! empty( $this->sku_prefix ) ? substr( $sku, strlen( $this->sku_prefix ) ) : $sku;
			$this->site_skus[] = $original_sku;
		}

		// Also add group_id SKUs for variable products
		$this->add_variable_product_group_ids();
	}

	/**
	 * Add group IDs for variable products.
	 *
	 * @return void
	 */
	private function add_variable_product_group_ids(): void {
		global $wpdb;

		$sql = "SELECT pm.meta_value AS group_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND p.post_status IN ('publish', 'draft', 'private')
			AND pm.meta_key = '_group_id'
			AND pm.meta_value != ''
			AND pm.meta_value IS NOT NULL";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Static query with no user input; live data required.
		$group_ids = $wpdb->get_col( $sql );
		
		foreach ( $group_ids as $group_id ) {
			$original_group_id = ! empty( $this->sku_prefix ) ? substr( $group_id, strlen( $this->sku_prefix ) ) : $group_id;
			$this->site_skus[] = $original_group_id;
		}
	}

	/**
	 * Fetch XML content from file path or URL.
	 *
	 * @return string|false XML content or false on failure.
	 */
	private function fetch_xml_content() {
		if ( file_exists( $this->xml_url ) ) {
			global $wp_filesystem;

			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( $wp_filesystem ) {
				$content = $wp_filesystem->get_contents( $this->xml_url );
				if ( false !== $content ) {
					return $content;
				}
			}

			return false;
		}

		if ( filter_var( $this->xml_url, FILTER_VALIDATE_URL ) ) {
			$response = wp_remote_get(
				$this->xml_url,
				array(
					'timeout'    => 30,
					'user-agent' => 'WordPress XML Importer',
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			return wp_remote_retrieve_body( $response );
		}

		return false;
	}

	/**
	 * Filter XML content to remove existing products.
	 *
	 * @param string $xml_content Original XML content.
	 * @return string Filtered XML content.
	 */
	private function filter_xml_content( string $xml_content ): string {
		$xml = simplexml_load_string( $xml_content );
		if ( ! $xml ) {
			throw new Exception( 'Не вдалося розпарсити XML файл.' );
		}

		$removed_count = 0;
		$total_offers = count( $xml->shop->offers->offer );

		// Store original offers data in array to avoid iteration issues
		$offers_to_process = array();
		foreach ( $xml->shop->offers->offer as $offer ) {
			$children_data = array();
			foreach ( $offer->children() as $child ) {
				$children_data[] = array(
					'name' => $child->getName(),
					'value' => (string) $child,
					'attributes' => array(),
					'children' => array()
				);
				
				foreach ( $child->attributes() as $attr_name => $attr_value ) {
					$children_data[count($children_data)-1]['attributes'][$attr_name] = (string) $attr_value;
				}
				
				foreach ( $child->children() as $grandchild ) {
					$children_data[count($children_data)-1]['children'][] = array(
						'name' => $grandchild->getName(),
						'value' => (string) $grandchild,
						'attributes' => array()
					);
					
					foreach ( $grandchild->attributes() as $attr_name => $attr_value ) {
						$children_data[count($children_data)-1]['children'][count($children_data[count($children_data)-1]['children'])-1]['attributes'][$attr_name] = (string) $attr_value;
					}
				}
			}
			
			$offer_data = array(
				'id' => (string) $offer['id'],
				'group_id' => isset( $offer['group_id'] ) ? (string) $offer['group_id'] : '',
				'available' => isset( $offer['available'] ) ? (string) $offer['available'] : '',
				'children' => $children_data
			);
			$offers_to_process[] = $offer_data;
		}

		// Clear existing offers
		unset( $xml->shop->offers->offer );

		foreach ( $offers_to_process as $offer_data ) {
			$offer_id = $offer_data['id'];
			$group_id = $offer_data['group_id'];

			$should_remove = false;

			if ( in_array( $offer_id, $this->site_skus, true ) ) {
				$should_remove = true;
			}

			// Check by group_id for variable products (only if group_id exists and is not empty)
			if ( ! empty( $group_id ) && in_array( $group_id, $this->site_skus, true ) ) {
				$should_remove = true;
			}

			// Check by minimum price (only if min_price > 0)
			if ( ! $should_remove && $this->min_price > 0 ) {
				$offer_price = $this->get_offer_price( $offer_data );
				if ( $offer_price < $this->min_price ) {
					$should_remove = true;
				}
			}

			if ( ! $should_remove ) {
				$new_offer = $xml->shop->offers->addChild( 'offer' );
				$new_offer->addAttribute( 'id', $offer_id );
				if ( ! empty( $group_id ) ) {
					$new_offer->addAttribute( 'group_id', $group_id );
				}
				if ( ! empty( $offer_data['available'] ) ) {
					$new_offer->addAttribute( 'available', $offer_data['available'] );
				}
				
				foreach ( $offer_data['children'] as $child_data ) {
					$this->copy_xml_element_from_data( $child_data, $new_offer );
				}
			} else {
				$removed_count++;
			}
		}

		$this->removed_count = $removed_count;

		$remaining_count = $total_offers - $removed_count;

		return $xml->asXML();
	}

	/**
	 * Get offer price from offer data.
	 *
	 * @param array $offer_data Offer data array.
	 * @return float Offer price or 0 if not found.
	 */
	private function get_offer_price( array $offer_data ): float {
		foreach ( $offer_data['children'] as $child_data ) {
			if ( $child_data['name'] === 'price' ) {
				return floatval( $child_data['value'] );
			}
		}
		return 0.0;
	}

	/**
	 * Copy XML element and its children recursively.
	 *
	 * @param SimpleXMLElement $source Source element.
	 * @param SimpleXMLElement $target Target element.
	 * @return void
	 */
	private function copy_xml_element( SimpleXMLElement $source, SimpleXMLElement $target ): void {
		$new_element = $target->addChild( $source->getName(), htmlspecialchars( (string) $source ) );
		
		foreach ( $source->attributes() as $name => $value ) {
			$new_element->addAttribute( $name, (string) $value );
		}
		
		foreach ( $source->children() as $child ) {
			$this->copy_xml_element( $child, $new_element );
		}
	}

	/**
	 * Copy XML element from stored data.
	 *
	 * @param array $element_data Element data array.
	 * @param SimpleXMLElement $target Target element.
	 * @return void
	 */
	private function copy_xml_element_from_data( array $element_data, SimpleXMLElement $target ): void {
		$new_element = $target->addChild( $element_data['name'], htmlspecialchars( $element_data['value'] ) );
		
		foreach ( $element_data['attributes'] as $name => $value ) {
			$new_element->addAttribute( $name, $value );
		}
		
		foreach ( $element_data['children'] as $child_data ) {
			$this->copy_xml_element_from_data( $child_data, $new_element );
		}
	}

	/**
	 * Save filtered XML to file.
	 *
	 * @param string $filtered_content Filtered XML content.
	 * @return string|false File path or false on failure.
	 */
	private function save_filtered_xml( string $filtered_content ) {
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/f2cs-exports';
		
		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$filename = 'filtered-xml-' . gmdate( 'Y-m-d-H-i-s' ) . '.xml';
		$file_path = $export_dir . '/' . $filename;

		$result = file_put_contents( $file_path, $filtered_content );
		
		if ( $result === false ) {
			return false;
		}

		$this->filtered_xml_path = $file_path;
		return $file_path;
	}

	/**
	 * Create download URL for the filtered XML.
	 *
	 * @param string $file_path Path to the filtered XML file.
	 * @return string Download URL.
	 */
	private function create_download_url( string $file_path ): string {
		$upload_dir = wp_upload_dir();
		$relative_path = str_replace( $upload_dir['basedir'], '', $file_path );
		return $upload_dir['baseurl'] . $relative_path;
	}

	/**
	 * Log the export process.
	 *
	 * @return void
	 */
	private function log_export_process(): void {
		$remaining_count = count( $this->site_skus ) - $this->removed_count;
		
		$message = sprintf(
			'🔍 XML фільтрація завершена' .
			'📊 Видалено: %d товарів' .
			'📁 Файл: %s',
			$this->removed_count,
			basename( $this->filtered_xml_path )
		);

		$telegram_token = get_option( 'f2cs_telegram_token_id', '' );
		$telegram_users = get_option( 'f2cs_telegram_user_ids', '' );
		
		if ( ! empty( $telegram_token ) && ! empty( $telegram_users ) ) {
			$user_ids = array_map( 'trim', explode( ',', $telegram_users ) );
			foreach ( $user_ids as $user_id ) {
				wp_remote_post( "https://api.telegram.org/bot{$telegram_token}/sendMessage", array(
					'body' => array(
						'chat_id' => $user_id,
						'text' => $message,
						'parse_mode' => 'HTML'
					)
				) );
			}
		}

		if ( $this->removed_count > 0 ) {
			f2cs_log( $message );
		}
	}
}
