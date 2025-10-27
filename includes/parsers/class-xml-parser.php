<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Parser
 *
 * Imports products from an XML file into WooCommerce.
 * Optimized for weak hosting use.
 */
class XML_Parser {
	private string $xml_url;
	private array $categories = array(); // Cache for categories
	private array $term_cache = array(); // Cache for terms
	private array $sku_cache  = array(); // Cache for product SKUs
	private string $telegram_token_id;
	private array $telegram_user_ids;
	private bool $new_category = false;
	private string $sku_prefix = '';

	/**
	 * XML_Parser constructor.
	 *
	 * @param string $file_path Path to the XML file.
	 * @param bool   $new_category Whether to add new products to "New" category.
	 * @param string $sku_prefix Prefix to add to SKU values.
	 */
	public function __construct( string $file_path, bool $new_category, string $sku_prefix = '' ) {
		$this->xml_url           = $file_path;
		$this->telegram_token_id = get_option( 'telegram_token_id', '' );
		$this->telegram_user_ids = array_map( 'trim', explode( ',', get_option( 'telegram_user_ids', '' ) ) );
		$this->new_category      = $new_category;
		$this->sku_prefix        = $sku_prefix;
	}

	/**
	 * Sanitize string for taxonomy name.
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	private function sanitize_for_taxonomy( $text ) {
		// Транслітерація кирилиці в латиницю
		$translit = array(
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'ґ' => 'g',
			'д' => 'd',
			'е' => 'e',
			'є' => 'ie',
			'ж' => 'zh',
			'з' => 'z',
			'и' => 'y',
			'і' => 'i',
			'ї' => 'i',
			'й' => 'i',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'h',
			'ц' => 'ts',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'shch',
			'ь' => '',
			'ю' => 'iu',
			'я' => 'ia',
			'А' => 'A',
			'Б' => 'B',
			'В' => 'V',
			'Г' => 'G',
			'Ґ' => 'G',
			'Д' => 'D',
			'Е' => 'E',
			'Є' => 'Ie',
			'Ж' => 'Zh',
			'З' => 'Z',
			'И' => 'Y',
			'І' => 'I',
			'Ї' => 'I',
			'Й' => 'I',
			'К' => 'K',
			'Л' => 'L',
			'М' => 'M',
			'Н' => 'N',
			'О' => 'O',
			'П' => 'P',
			'Р' => 'R',
			'С' => 'S',
			'Т' => 'T',
			'У' => 'U',
			'Ф' => 'F',
			'Х' => 'H',
			'Ц' => 'Ts',
			'Ч' => 'Ch',
			'Ш' => 'Sh',
			'Щ' => 'Shch',
			'Ь' => '',
			'Ю' => 'Iu',
			'Я' => 'Ia',
			'ы' => 'y',
			'э' => 'e',
			'ъ' => '',
			'Ы' => 'Y',
			'Э' => 'E',
			'Ъ' => '',
		);

		$text = strtr( $text, $translit );
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9_\-]/', '_', $text );
		$text = preg_replace( '/_+/', '_', $text );
		$text = trim( $text, '_' );
		return substr( $text, 0, 28 );
	}

	/**
	 * Ensure WooCommerce global attribute exists in wc table so it appears in admin/UI.
	 * Falls back to direct DB insert if Woo helper is unavailable.
	 *
	 * @param string $attribute_label Human-readable label (e.g., "Колір").
	 * @param string $attribute_slug  Sanitized slug without 'pa_' prefix (e.g., "kolir").
	 * @return void
	 */
	private function ensure_global_attribute( string $attribute_label, string $attribute_slug ): void {
		global $wpdb;

		if ( empty( $attribute_slug ) ) {
			return;
		}

		// Trim to WC limits
		$attribute_slug = substr( $attribute_slug, 0, 28 );

		// Check if attribute already exists (by slug)
		$attr_tax_table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
		$existing       = $wpdb->get_var( $wpdb->prepare( "SELECT attribute_id FROM {$attr_tax_table} WHERE attribute_name = %s LIMIT 1", $attribute_slug ) );
		if ( $existing ) {
			return;
		}

		// Prefer Woo helper if available
		if ( function_exists( 'wc_create_attribute' ) ) {
			$args = array(
				'name'         => $attribute_label,
				'slug'         => $attribute_slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			);

			try {
				$attr_id = wc_create_attribute( $args );
				if ( is_wp_error( $attr_id ) ) {
					// Fallback to direct insert
					$wpdb->insert(
						$attr_tax_table,
						array(
							'attribute_label'   => $attribute_label,
							'attribute_name'    => $attribute_slug,
							'attribute_type'    => 'select',
							'attribute_orderby' => 'menu_order',
							'attribute_public'  => 0,
						)
					);
				}
			} catch ( Exception $e ) {
				// Fallback to direct insert on any exception
				$wpdb->insert(
					$attr_tax_table,
					array(
						'attribute_label'   => $attribute_label,
						'attribute_name'    => $attribute_slug,
						'attribute_type'    => 'select',
						'attribute_orderby' => 'menu_order',
						'attribute_public'  => 0,
					)
				);
			}
		} else {
			// No Woo helper: direct insert
			$wpdb->insert(
				$attr_tax_table,
				array(
					'attribute_label'   => $attribute_label,
					'attribute_name'    => $attribute_slug,
					'attribute_type'    => 'select',
					'attribute_orderby' => 'menu_order',
					'attribute_public'  => 0,
				)
			);
		}

		// Clear cached attribute taxonomies so WC registers taxonomy on next init
		delete_transient( 'wc_attribute_taxonomies' );
	}

	/**
	 * Update the stock status of products based on XML data.
	 *
	 * @return void
	 */
	public function update_products_stock_status() {
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		// Set maximum execution time for production
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 600 ); // 10 minutes
		}

		$xml_data = $this->fetch_xml_data();
		if ( ! $xml_data ) {
			return $this->send_telegram_message( 'Failed to retrieve XML data' );
		}

		try {
			$products = new XMLReader();
			$products->open( $this->xml_url );

			// Check if XML is empty using proper XMLReader methods
			if ( ! $products->read() ) {
				return $this->send_telegram_message( 'XML data is empty or not created' );
			}

			// Reset the reader position
			$products->close();
			$products->open( $this->xml_url );
		} catch ( Exception $e ) {
			return $this->send_telegram_message( 'XML parsing error: ' . $e->getMessage() );
		}

		// Process in batches for production
		$batch_size  = 100;
		$updates     = array();
		$batch_count = 0;

		while ( $products->read() ) {
			if ( $products->nodeType == XMLReader::ELEMENT && $products->localName == 'offer' ) {
				$sku             = (string) $products->getAttribute( 'id' );
				$available       = (string) $products->getAttribute( 'available' );
				$stock_status    = 'true' === $available ? 'instock' : 'outofstock';
				$updates[ $sku ] = $stock_status;

				// Process in batches to avoid memory issues
				if ( count( $updates ) >= $batch_size ) {
					$this->process_stock_updates_batch( $updates );
					$updates = array();
					++$batch_count;

					// Free memory
					gc_collect_cycles();
				}
			}
		}

		// Process any remaining updates
		if ( ! empty( $updates ) ) {
			$this->process_stock_updates_batch( $updates );
			++$batch_count;
		}

		$products->close();

		$this->log_memory_usage( $start_time, $start_memory, "Stock status update completed ($batch_count batches)" );
	}

	/**
	 * Process a batch of stock updates
	 *
	 * @param array $updates Array of SKU => stock_status
	 */
	private function process_stock_updates_batch( $updates ) {
		$product_ids      = $this->get_product_ids_by_skus( array_keys( $updates ) );
		$updated_in_stock = $updated_out_of_stock = $not_found = 0;

		foreach ( $updates as $sku => $stock_status ) {
			$product_id = $product_ids[ $sku ] ?? false;
			if ( ! $product_id ) {
				++$not_found;
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				++$not_found;
				continue;
			}

			$this->update_product_stock( $product, $stock_status );
			$stock_status === 'instock' ? ++$updated_in_stock : ++$updated_out_of_stock;
		}

		// Batch processing completed
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

		// Check if variable products import is enabled (from transient during import session)
		$import_variations_transient = get_transient( 'prom_xml_import_variations_temp' );
		$import_variations           = $import_variations_transient === '1';

		// Import variations setting loaded

		$reader = new XMLReader();

		if ( ! $reader->open( $this->xml_url ) ) {
			throw new Exception( 'Failed to open XML file.' );
		}

		// First pass: Group products by group_id
		$simple_products         = array();
		$grouped_products        = array();
		$total_offers            = 0;
		$offers_with_group_id    = 0;
		$offers_without_group_id = 0;

		while ( $reader->read() ) {
			if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'offer' ) {
				continue;
			}

			$offer = simplexml_load_string( $reader->readOuterXML() );
			++$total_offers;

			$offer_data = $this->extract_offer_data( $offer );

			$has_group_id = ! empty( $offer_data['group_id'] );

			if ( $import_variations ) {
				// Режим варіативних: імпортуємо товари З group_id
				if ( $has_group_id ) {
					$grouped_products[ $offer_data['group_id'] ][] = $offer_data;
					++$offers_with_group_id;
				} else {
					++$offers_without_group_id; // Пропускаємо
				}
			} else {
				// Режим простих: імпортуємо товари БЕЗ group_id + товари з group_id але з 1 варіацією
				if ( ! $has_group_id ) {
					$simple_products[] = $offer_data;
					++$offers_without_group_id;
				} else {
					// Товари з group_id зберігаємо для подальшої перевірки
					$grouped_products[ $offer_data['group_id'] ][] = $offer_data;
					++$offers_with_group_id;
				}
			}
		}

		$reader->close();

		// Для режиму простих товарів: перевіряємо групи і переносимо одиночні товари
		if ( ! $import_variations ) {
			$single_variation_groups = 0;
			foreach ( $grouped_products as $group_id => $variations ) {
				if ( count( $variations ) === 1 ) {
					// Товар з group_id але тільки 1 варіація - імпортуємо як простий
					$simple_products[] = $variations[0];
					unset( $grouped_products[ $group_id ] );
					++$single_variation_groups;
				}
			}

			// Moved single-variation groups to simple products
		}

		// Підраховуємо скільки товарів буде оброблено
		$simple_count   = count( $simple_products );
		$variable_count = 0;
		foreach ( $grouped_products as $variations ) {
			if ( count( $variations ) >= 2 ) {
				$variable_count += count( $variations );
			}
		}

		// XML parsed and grouped

		// Second pass: Create products
		$imported       = 0;
		$skipped        = 0;
		$current_offset = 0;

		// Підраховуємо загальну кількість товарів для імпорту залежно від режиму
		if ( $import_variations ) {
			// В режимі варіативних - рахуємо тільки групи з 2+ варіаціями
			$total_products = 0;
			foreach ( $grouped_products as $variations ) {
				if ( count( $variations ) >= 2 ) {
					++$total_products;
				}
			}
			// Variable mode: importing variable products
		} else {
			// В режимі простих - рахуємо тільки прості товари
			$total_products = count( $simple_products );
			// Simple mode: importing simple products
		}

		// Import simple products
		foreach ( $simple_products as $offer_data ) {
			if ( $current_offset < $offset ) {
				++$current_offset;
				continue;
			}

			if ( $imported >= $limit ) {
				break;
			}

			$result = $this->import_simple_product( $offer_data );
			if ( $result ) {
				++$imported;
			} else {
				++$skipped;
			}

			++$current_offset;
		}

		// Import variable products
		foreach ( $grouped_products as $group_id => $variations_data ) {
			if ( $current_offset < $offset ) {
				++$current_offset;
				continue;
			}

			if ( $imported >= $limit ) {
				break;
			}

			// Пропускаємо групи з тільки 1 варіацією в режимі варіативних товарів
			if ( count( $variations_data ) === 1 ) {
				// Skipping group with only 1 variation in variable mode
				++$skipped;
				++$current_offset;
				continue;
			}

			$result = $this->import_variable_product( $group_id, $variations_data );
			if ( $result ) {
				++$imported;
			} else {
				++$skipped;
			}

			++$current_offset;
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'total'    => $total_products,
			'finished' => $offset + $imported >= $total_products,
		);
	}

	/**
	 * Extract offer data from SimpleXMLElement.
	 *
	 * @param SimpleXMLElement $offer Offer element.
	 * @return array Offer data.
	 */
	private function extract_offer_data( $offer ): array {
		$sku      = (string) $offer['id'];
		$group_id = isset( $offer['group_id'] ) ? (string) $offer['group_id'] : '';
		$title    = (string) $offer->name;

		if ( ! empty( $offer->name_ua ) ) {
			$title = (string) $offer->name_ua;
		}

		$price     = (float) $offer->price;
		$old_price = isset( $offer->oldprice ) ? (float) $offer->oldprice : 0;
		$desc      = (string) $offer->description;

		if ( ! empty( $offer->description_ua ) ) {
			$desc = (string) $offer->description_ua;
		}

		$category  = (string) $offer->categoryId;
		$available = (string) $offer['available'] === 'true' ? 'instock' : 'outofstock';
		$vendor    = isset( $offer->vendor ) ? (string) $offer->vendor : '';

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

		// Add vendor to attributes if available
		if ( ! empty( $vendor ) && ! isset( $attributes['Виробник'] ) ) {
			$attributes['Виробник'] = $vendor;
		}

		return array(
			'sku'        => $sku,
			'group_id'   => $group_id,
			'title'      => $title,
			'price'      => $price,
			'old_price'  => $old_price,
			'desc'       => $desc,
			'category'   => $category,
			'available'  => $available,
			'vendor'     => $vendor,
			'images'     => $images,
			'attributes' => $attributes,
		);
	}

	/**
	 * Import a simple product.
	 *
	 * @param array $offer_data Offer data.
	 * @return bool True if imported, false if skipped.
	 */
	private function import_simple_product( array $offer_data ): bool {
		$sku        = $offer_data['sku'];
		$title      = $offer_data['title'];
		$price      = $offer_data['price'];
		$old_price  = $offer_data['old_price'];
		$desc       = $offer_data['desc'];
		$category   = $offer_data['category'];
		$available  = $offer_data['available'];
		$images     = $offer_data['images'];
		$attributes = $offer_data['attributes'];

		if ( empty( $sku ) || empty( $title ) || $price <= 0 ) {
			return false;
		}

		// Check if product already exists (передаємо SKU без префіксу)
		$existing_product = $this->get_product_ids_by_skus( array( $sku ) );
		if ( ! empty( $existing_product ) && isset( $existing_product[ $sku ] ) ) {
			// Skipping simple product - already exists
			return false;
		}

		// Apply SKU prefix для створення
		$original_sku = $sku;
		if ( ! empty( $this->sku_prefix ) ) {
			$sku = $this->sku_prefix . $sku;
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
			return false;
		}

		update_post_meta( $post_id, '_sku', $sku );

		// Set prices
		if ( $old_price > 0 && $old_price > $price ) {
			update_post_meta( $post_id, '_regular_price', number_format( $old_price, 2, '.', '' ) );
			update_post_meta( $post_id, '_sale_price', number_format( $price, 2, '.', '' ) );
			update_post_meta( $post_id, '_price', number_format( $price, 2, '.', '' ) );
		} else {
			update_post_meta( $post_id, '_regular_price', number_format( $price, 2, '.', '' ) );
			update_post_meta( $post_id, '_price', number_format( $price, 2, '.', '' ) );
		}

		update_post_meta( $post_id, '_stock_status', $available );
		update_post_meta( $post_id, '_manage_stock', 'no' );

		// Handle product attributes
		if ( ! empty( $attributes ) ) {
			$this->set_product_attributes( $post_id, $attributes );
		}

		// Handle product images
		if ( ! empty( $images ) ) {
			$this->handle_product_images( $post_id, $images );
		}

		// Set category
		if ( ! $this->new_category ) {
			$this->set_product_category( $post_id, $category );
		}

		if ( $this->new_category ) {
			if ( term_exists( 'Новинки', 'product_cat' ) === 0 ) {
				wp_insert_term( 'Новинки', 'product_cat' );
			}
			wp_set_object_terms( $post_id, 'Новинки', 'product_cat', true );
		}

		return true;
	}

	/**
	 * Import a variable product with variations.
	 *
	 * @param string $group_id Group ID for the variable product.
	 * @param array  $variations_data Array of variation data.
	 * @return bool True if imported, false if skipped.
	 */
	private function import_variable_product( string $group_id, array $variations_data ): bool {
		if ( empty( $variations_data ) ) {
			return false;
		}

		// Check if parent product already exists (передаємо group_id без префіксу, функція поверне з ключем без префіксу)
		$existing_parent = $this->get_product_ids_by_skus( array( $group_id ) );
		if ( ! empty( $existing_parent ) && isset( $existing_parent[ $group_id ] ) ) {
			// Skipping group - parent product already exists
			return false; // Skip if already exists
		}

		// Apply SKU prefix to group_id для створення
		$parent_sku = ! empty( $this->sku_prefix ) ? $this->sku_prefix . $group_id : $group_id;

		// Use first variation as base for parent product
		$base_data = $variations_data[0];

		// Extract base product name (without size/color)
		$parent_name = $this->extract_base_product_name( array_column( $variations_data, 'title' ) );

		// Create parent variable product
		$parent_id = wp_insert_post(
			array(
				'post_title'   => $parent_name,
				'post_content' => $base_data['desc'],
				'post_status'  => 'publish',
				'post_type'    => 'product',
			)
		);

		if ( is_wp_error( $parent_id ) || ! $parent_id ) {
			return false;
		}

		// Set parent product as variable type
		wp_set_object_terms( $parent_id, 'variable', 'product_type' );
		update_post_meta( $parent_id, '_sku', $parent_sku );
		update_post_meta( $parent_id, '_stock_status', 'instock' );
		update_post_meta( $parent_id, '_manage_stock', 'no' );

		// Set category
		if ( ! $this->new_category ) {
			$this->set_product_category( $parent_id, $base_data['category'] );
		}

		if ( $this->new_category ) {
			if ( term_exists( 'Новинки', 'product_cat' ) === 0 ) {
				wp_insert_term( 'Новинки', 'product_cat' );
			}
			wp_set_object_terms( $parent_id, 'Новинки', 'product_cat', true );
		}

		// Handle parent product images (from first variation)
		if ( ! empty( $base_data['images'] ) ) {
			$this->handle_product_images( $parent_id, $base_data['images'] );
		}

		// Determine variation attributes
		$variation_attributes = $this->determine_variation_attributes( $variations_data, $group_id );

		if ( empty( $variation_attributes ) ) {
			// Cannot determine variation attributes, skipping variable product
			// Delete parent product since we can't create variations
			wp_delete_post( $parent_id, true );
			return false;
		}

		$attributes_info = array();
		foreach ( $variation_attributes as $attr_name => $attr_values ) {
			$attributes_info[] = $attr_name . ' (' . count( $attr_values ) . ' values)';
		}

		prom_log(
			sprintf(
				'Creating variable product: group_id=%s, parent_name="%s", variations_count=%d, attributes=%s',
				$group_id,
				$parent_name,
				count( $variations_data ),
				implode( ', ', $attributes_info )
			)
		);

		// Set product attributes for variations
		$this->set_variation_attributes_for_product( $parent_id, $variation_attributes, $variations_data );

		// Create variations
		$this->create_product_variations( $parent_id, $variations_data, $variation_attributes );

		return true;
	}

	/**
	 * Extract base product name without size/color specifications.
	 *
	 * @param array $variation_names Array of variation names.
	 * @return string Base product name.
	 */
	private function extract_base_product_name( array $variation_names ): string {
		if ( empty( $variation_names ) ) {
			return '';
		}

		// Take the first name as base
		$base_name = $variation_names[0];

		// Remove common size patterns
		$patterns = array(
			'/\s+розмір\s+[SMLX0-9]+/ui',
			'/\s+size\s+[SMLX0-9]+/i',
			'/\s+[SMLX]{1,4}$/i',
			'/\s+\d+[X]{0,3}L$/i',
			'/\s+[0-9]+-[0-9]+$/i', // Remove size ranges like 48-50
			'/\s+(Чорний|Білий|Синій|Червоний|Зелений|Жовтий|Сірий|Коричневий|Оливковий|Койот)$/ui',
		);

		foreach ( $patterns as $pattern ) {
			$base_name = preg_replace( $pattern, '', $base_name );
		}

		return trim( $base_name );
	}

	/**
	 * Determine variation attributes from variations data.
	 *
	 * @param array  $variations_data Array of variation data.
	 * @param string $group_id Group ID for manual attribute selection.
	 * @return array Array of variation attributes [name => [values]].
	 */
	private function determine_variation_attributes( array $variations_data, string $group_id = '' ): array {
		$all_attributes = array();

		// Collect all attributes from all variations
		foreach ( $variations_data as $variation ) {
			if ( ! empty( $variation['attributes'] ) ) {
				foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
					if ( ! isset( $all_attributes[ $attr_name ] ) ) {
						$all_attributes[ $attr_name ] = array();
					}
					if ( ! in_array( $attr_value, $all_attributes[ $attr_name ], true ) ) {
						$all_attributes[ $attr_name ][] = $attr_value;
					}
				}
			}
		}

		// Check for manually selected attributes
		$selected_attributes_map = get_transient( 'prom_xml_selected_attributes_temp' );
		if ( ! empty( $selected_attributes_map ) && isset( $selected_attributes_map[ $group_id ] ) ) {
			$selected_attrs = $selected_attributes_map[ $group_id ];

			// Підтримка масиву атрибутів (новий формат) або одного атрибута (старий формат)
			if ( ! is_array( $selected_attrs ) ) {
				$selected_attrs = array( $selected_attrs );
			}

			$variation_attributes = array();
			$skipped_attrs        = array();

			foreach ( $selected_attrs as $selected_attr ) {
				// Check if this attribute exists and has multiple values
				if ( isset( $all_attributes[ $selected_attr ] ) ) {
					if ( count( $all_attributes[ $selected_attr ] ) > 1 ) {
						$variation_attributes[ $selected_attr ] = $all_attributes[ $selected_attr ];
					} else {
						$skipped_attrs[] = $selected_attr . ' (тільки 1 значення)';
					}
				} else {
					$skipped_attrs[] = $selected_attr . ' (не знайдено)';
				}
			}

			if ( ! empty( $skipped_attrs ) ) {
				// Skipped non-varying attributes
			}

			if ( ! empty( $variation_attributes ) ) {
				// Using manually selected attributes
				return $variation_attributes;
			} else {
				// None of the manually selected attributes vary - will use auto-selection
			}
		}

		// Filter to find attributes that vary between products
		$variation_attributes = array();

		// Priority attributes for variations (в порядку пріоритету)
		$priority_attrs = array(
			// Розмірні атрибути (вищий пріоритет)
			'Міжнародний розмір',
			'Международный размер',
			'Розмір',
			'Розміри',
			'Розмір чоловічого одягу (UA)',
			'Розмір взуття',
			'Розмір одягу',
			// Кольорові атрибути (нижчий пріоритет)
			'Колір',
		);

		// First check priority attributes - беремо ТІЛЬКИ перший знайдений атрибут
		foreach ( $priority_attrs as $priority_attr ) {
			if ( isset( $all_attributes[ $priority_attr ] ) && count( $all_attributes[ $priority_attr ] ) > 1 ) {
				$variation_attributes[ $priority_attr ] = $all_attributes[ $priority_attr ];

				// Логуємо який атрибут обрано
				// Auto-selected variation attribute

				break; // Беремо тільки ОДИН атрибут!
			}
		}

		// If no priority attributes found, use any attribute that varies
		if ( empty( $variation_attributes ) ) {
			// No priority variation attributes found, searching for any varying attribute

			foreach ( $all_attributes as $attr_name => $attr_values ) {
				if ( count( $attr_values ) > 1 ) {
					$variation_attributes[ $attr_name ] = $attr_values;
					// Using non-priority variation attribute
					break; // Беремо тільки ОДИН атрибут!
				}
			}
		}

		return $variation_attributes;
	}

	/**
	 * Set variation attributes for parent product.
	 *
	 * @param int   $parent_id Parent product ID.
	 * @param array $variation_attributes Variation attributes.
	 * @param array $variations_data All variations data.
	 * @return void
	 */
	private function set_variation_attributes_for_product( int $parent_id, array $variation_attributes, array $variations_data ): void {
		$product_attributes = array();
		$position           = 0;

		foreach ( $variation_attributes as $attr_name => $attr_values ) {
			// Skip if no values
			if ( empty( $attr_values ) ) {
				// Skipping empty variation attribute
				continue;
			}

			// Create sanitized taxonomy name
			$taxonomy_name = $this->sanitize_for_taxonomy( $attr_name );
			$taxonomy      = 'pa_' . $taxonomy_name;

			// Ensure taxonomy length doesn't exceed 32 characters
			if ( strlen( $taxonomy ) > 32 ) {
				$taxonomy = substr( $taxonomy, 0, 32 );
			}

			// Register taxonomy if not exists
			if ( ! taxonomy_exists( $taxonomy ) ) {
				$this->ensure_global_attribute( $attr_name, $taxonomy_name );
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'labels'       => array( 'name' => $attr_name ),
						'hierarchical' => false,
						'show_ui'      => true,
						'query_var'    => true,
						'rewrite'      => false,
					)
				);
			}

			// Create terms and assign to product
			$term_ids = array();
			foreach ( $attr_values as $value ) {
				if ( empty( $value ) ) {
					continue;
				}

				$term = get_term_by( 'name', $value, $taxonomy );
				if ( ! $term ) {
					$term_info = wp_insert_term( $value, $taxonomy );
					if ( ! is_wp_error( $term_info ) ) {
						$term_ids[] = $term_info['term_id'];
					}
				} else {
					$term_ids[] = $term->term_id;
				}
			}

			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $parent_id, $term_ids, $taxonomy );

				$product_attributes[ $taxonomy ] = array(
					'name'         => $taxonomy,
					'value'        => '',
					'position'     => $position++,
					'is_visible'   => 1,
					'is_variation' => 1,
					'is_taxonomy'  => 1,
				);

				// Added variation attribute
			}
		}

		// Collect all non-variation attributes from ALL variations
		$non_variation_attributes = array();

		foreach ( $variations_data as $variation ) {
			if ( ! empty( $variation['attributes'] ) ) {
				foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
					// Skip empty values
					if ( empty( $attr_value ) ) {
						continue;
					}

					$taxonomy_name = $this->sanitize_for_taxonomy( $attr_name );
					$taxonomy      = 'pa_' . $taxonomy_name;

					// Skip if already added as variation attribute
					if ( isset( $product_attributes[ $taxonomy ] ) ) {
						continue;
					}

					if ( ! isset( $non_variation_attributes[ $attr_name ] ) ) {
						$non_variation_attributes[ $attr_name ] = array();
					}

					if ( ! in_array( $attr_value, $non_variation_attributes[ $attr_name ], true ) ) {
						$non_variation_attributes[ $attr_name ][] = $attr_value;
					}
				}
			}
		}

		// Collected non-variation attributes for parent product

		// Add collected non-variation attributes to product
		foreach ( $non_variation_attributes as $attr_name => $attr_values ) {
			$taxonomy_name = $this->sanitize_for_taxonomy( $attr_name );
			$taxonomy      = 'pa_' . $taxonomy_name;

			// Register taxonomy if not exists
			if ( ! taxonomy_exists( $taxonomy ) ) {
				// Ensure global attribute exists so it appears in admin UI
				$this->ensure_global_attribute( $attr_name, $taxonomy_name );

				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'labels'       => array( 'name' => $attr_name ),
						'hierarchical' => false,
						'show_ui'      => true,
						'query_var'    => true,
						'rewrite'      => false,
					)
				);
			}

			// Create all terms
			$term_ids = array();
			foreach ( $attr_values as $attr_value ) {
				if ( empty( $attr_value ) ) {
					continue;
				}

				$term = get_term_by( 'name', $attr_value, $taxonomy );
				if ( ! $term ) {
					$term_info = wp_insert_term( $attr_value, $taxonomy );
					if ( ! is_wp_error( $term_info ) ) {
						$term_ids[] = $term_info['term_id'];
					} else {
						// Error creating term
					}
				} else {
					$term_ids[] = $term->term_id;
				}
			}

			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $parent_id, $term_ids, $taxonomy );

				$product_attributes[ $taxonomy ] = array(
					'name'         => $taxonomy,
					'value'        => '',
					'position'     => $position++,
					'is_visible'   => 1,
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				);

				// Added non-variation attribute
			} else {
				// Skipped attribute - no term IDs created
			}
		}

			// Save attributes to product
		if ( ! empty( $product_attributes ) ) {
			update_post_meta( $parent_id, '_product_attributes', $product_attributes );
			// Clear product transients so attributes are visible in UI immediately
			wc_delete_product_transients( $parent_id );
			// Clear WooCommerce attribute taxonomies cache to ensure new attributes are visible
			delete_transient( 'wc_attribute_taxonomies' );

			$variation_count     = 0;
			$non_variation_count = 0;
			foreach ( $product_attributes as $attr ) {
				if ( $attr['is_variation'] ) {
					++$variation_count;
				} else {
					++$non_variation_count;
				}
			}

			prom_log(
				sprintf(
					'Set %d total attributes for variable product ID %d (variation: %d, non-variation: %d)',
					count( $product_attributes ),
					$parent_id,
					$variation_count,
					$non_variation_count
				)
			);
		} else {
			prom_log( sprintf( 'No attributes to set for variable product ID %d', $parent_id ) );
		}
	}

	/**
	 * Create product variations.
	 *
	 * @param int   $parent_id Parent product ID.
	 * @param array $variations_data Variations data.
	 * @param array $variation_attributes Variation attributes.
	 * @return void
	 */
	private function create_product_variations( int $parent_id, array $variations_data, array $variation_attributes ): void {
		foreach ( $variations_data as $variation_data ) {
			$original_sku = $variation_data['sku'];

			// Check if variation already exists (передаємо SKU без префіксу)
			$existing_variation = $this->get_product_ids_by_skus( array( $original_sku ) );
			if ( ! empty( $existing_variation ) && isset( $existing_variation[ $original_sku ] ) ) {
				prom_log( sprintf( 'Skipping variation with SKU %s - already exists (ID: %d)', $original_sku, $existing_variation[ $original_sku ] ) );
				continue; // Skip if exists
			}

			// Apply SKU prefix для створення
			$variation_sku = $original_sku;
			if ( ! empty( $this->sku_prefix ) ) {
				$variation_sku = $this->sku_prefix . $variation_sku;
			}

			// Create variation
			$variation_id = wp_insert_post(
				array(
					'post_title'  => $variation_data['title'],
					'post_status' => 'publish',
					'post_parent' => $parent_id,
					'post_type'   => 'product_variation',
				)
			);

			if ( is_wp_error( $variation_id ) || ! $variation_id ) {
				continue;
			}

			// Set variation SKU
			update_post_meta( $variation_id, '_sku', $variation_sku );

			// Set prices
			$price     = $variation_data['price'];
			$old_price = $variation_data['old_price'];

			if ( $old_price > 0 && $old_price > $price ) {
				update_post_meta( $variation_id, '_regular_price', number_format( $old_price, 2, '.', '' ) );
				update_post_meta( $variation_id, '_sale_price', number_format( $price, 2, '.', '' ) );
				update_post_meta( $variation_id, '_price', number_format( $price, 2, '.', '' ) );
			} else {
				update_post_meta( $variation_id, '_regular_price', number_format( $price, 2, '.', '' ) );
				update_post_meta( $variation_id, '_price', number_format( $price, 2, '.', '' ) );
			}

			// Set stock status
			update_post_meta( $variation_id, '_stock_status', $variation_data['available'] );
			update_post_meta( $variation_id, '_manage_stock', 'no' );

			// Set variation attributes
			foreach ( $variation_attributes as $attr_name => $attr_values ) {
				$taxonomy_name = $this->sanitize_for_taxonomy( $attr_name );
				$taxonomy      = 'pa_' . $taxonomy_name;

				if ( strlen( $taxonomy ) > 32 ) {
					$taxonomy = substr( $taxonomy, 0, 32 );
				}

				// Get the value for this variation
				if ( isset( $variation_data['attributes'][ $attr_name ] ) ) {
					$attr_value = $variation_data['attributes'][ $attr_name ];

					// Get or create term
					$term = get_term_by( 'name', $attr_value, $taxonomy );
					if ( $term ) {
						update_post_meta( $variation_id, 'attribute_' . $taxonomy, $term->slug );
					}
				}
			}

			// Handle variation images if they differ
			if ( ! empty( $variation_data['images'] ) ) {
				// Set first image as variation image
				$this->set_variation_image( $variation_id, $variation_data['images'][0] );
			}

			// Clear product cache
			wc_delete_product_transients( $parent_id );
			wc_delete_product_transients( $variation_id );
		}

		// Sync parent product after all variations created
		WC_Product_Variable::sync( $parent_id );
	}

	/**
	 * Set variation image.
	 *
	 * @param int    $variation_id Variation ID.
	 * @param string $image_url Image URL.
	 * @return void
	 */
	private function set_variation_image( int $variation_id, string $image_url ): void {
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			return;
		}

		$file_array = array(
			'name'     => basename( $image_url ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $variation_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return;
		}

		update_post_meta( $variation_id, '_thumbnail_id', $attachment_id );
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
		// Validate URL to prevent errors
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			prom_log( "Invalid image URL for product ID: $post_id", 'warning' );
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

		$position = 0;
		foreach ( $attributes as $name => $value ) {
			// Skip empty values
			if ( empty( $value ) ) {
				continue;
			}

			// Sanitize and create taxonomy name
			$taxonomy_name = $this->sanitize_for_taxonomy( $name );
			$taxonomy      = 'pa_' . $taxonomy_name;

			if ( strlen( $taxonomy ) > 32 ) {
				$taxonomy = substr( $taxonomy, 0, 32 );
			}

			// Register taxonomy if not exists
			if ( ! taxonomy_exists( $taxonomy ) ) {
				// Ensure global attribute exists so taxonomy is properly registered by WC
				$this->ensure_global_attribute( $name, $taxonomy_name );
					register_taxonomy(
						$taxonomy,
						array( 'product' ),
						array(
							'labels'       => array(
								'name' => $name,
							),
							'hierarchical' => false,
							'show_ui'      => true,
							'query_var'    => true,
							'rewrite'      => false,
						)
					);
			}

			// Either get or create the term
			$term_id = null;
			$term    = get_term_by( 'name', $value, $taxonomy );
			if ( ! $term ) {
				$term_info = wp_insert_term( $value, $taxonomy );
				if ( ! is_wp_error( $term_info ) ) {
					$term_id = $term_info['term_id'];
				}
			} else {
				$term_id = $term->term_id;
			}

			// Set the product attribute
			if ( $term_id ) {
				wp_set_object_terms( $post_id, array( $term_id ), $taxonomy );
				$product_attributes[ $taxonomy ] = array(
					'name'         => $taxonomy,
					'value'        => '',
					'position'     => $position++,
					'is_visible'   => 1,
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				);
			}
		}

		// Save the product attributes
		if ( ! empty( $product_attributes ) ) {
			update_post_meta( $post_id, '_product_attributes', $product_attributes );
			// Clear product transients to reflect attributes in UI
			wc_delete_product_transients( $post_id );
			prom_log( sprintf( 'Set %d attributes for product ID %d', count( $product_attributes ), $post_id ) );
		}
	}

	/**
	 * Downloads and attaches multiple product images (first as featured, others as gallery).
	 *
	 * @param int   $post_id Product ID.
	 * @param array $urls    Array of image URLs.
	 */
	private function handle_product_images( int $post_id, array $urls ): void {
		add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );

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

		remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
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

		// Add prefix to SKUs for database search if prefix is set
		$skus_with_prefix = array();
		foreach ( $skus as $sku ) {
			$sku_with_prefix    = ! empty( $this->sku_prefix ) ? $this->sku_prefix . $sku : $sku;
			$skus_with_prefix[] = $sku_with_prefix;
		}

		// Check cache first for each SKU with prefix
		$result        = array();
		$uncached_skus = array();

		foreach ( $skus_with_prefix as $sku_with_prefix ) {
			if ( isset( $this->sku_cache[ $sku_with_prefix ] ) ) {
				// Map back to original SKU without prefix
				$original_sku            = ! empty( $this->sku_prefix ) ? substr( $sku_with_prefix, strlen( $this->sku_prefix ) ) : $sku_with_prefix;
				$result[ $original_sku ] = $this->sku_cache[ $sku_with_prefix ];
			} else {
				$uncached_skus[] = $sku_with_prefix;
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

			// Add to cache and map back to original SKU
			foreach ( $db_results as $sku_with_prefix => $id ) {
				$this->sku_cache[ $sku_with_prefix ] = $id;
				$original_sku                        = ! empty( $this->sku_prefix ) ? substr( $sku_with_prefix, strlen( $this->sku_prefix ) ) : $sku_with_prefix;
				$result[ $original_sku ]             = $id;
			}
		}

		return $result;
	}

	/**
	 * Fetch XML data from the specified URL.
	 *
	 * @return string|false XML data or false on failure.
	 */
	private function fetch_xml_data() {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->xml_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
		$body = curl_exec( $ch );
		curl_close( $ch );

		return $body ?: false;
	}

	/**
	 * Update the stock status of a product with improved error handling.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $stock_status Stock status.
	 * @return void
	 */
	private function update_product_stock( $product, $stock_status ) {
		try {
			// Skip if the stock status is already set to the same value
			if ( $product->get_stock_status() === $stock_status ) {
				return;
			}

			$product->set_stock_status( $stock_status );
			$product->save();
			wc_delete_product_transients( $product->get_id() );

			// Update variations if the product is a variable product.
			if ( 'variable' === $product->get_type() ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation ) {
						$variation->set_stock_status( $stock_status );
						$variation->save();
						wc_delete_product_transients( $variation_id );
					}
				}
			}
		} catch ( Exception $e ) {
			prom_log( 'Error updating product #' . $product->get_id() . ': ' . $e->getMessage(), 'error' );
		}
	}

	/**
	 * Log memory usage and execution time.
	 *
	 * @param float  $start_time Start time of the process.
	 * @param int    $start_memory Start memory usage in bytes.
	 * @param string $message Log message.
	 * @return void
	 */
	private function log_memory_usage( $start_time, $start_memory, $message ) {
		$end_time   = microtime( true );
		$end_memory = memory_get_usage();

		$execution_time = $end_time - $start_time;
		$memory_usage   = ( $end_memory - $start_memory ) / 1048576; // Convert to megabytes

		// Format the log message
		$log_message = sprintf(
			"[%s] %s | Execution time: %.2f sec | Memory usage: %.2f MB\n",
			date( 'Y-m-d H:i:s' ),
			$message,
			$execution_time,
			$memory_usage
		);

		// Send log to Telegram
		$this->send_telegram_message( $log_message );
	}

	/**
	 * Send a message to Telegram.
	 *
	 * @param string $message Message to send.
	 * @return void
	 */
	private function send_telegram_message( $message ) {
		if ( empty( $this->telegram_token_id ) || empty( $this->telegram_user_ids ) ) {
			return;
		}

		foreach ( $this->telegram_user_ids as $chat_id ) {
			if ( empty( $chat_id ) ) {
				continue;
			}

			$url  = "https://api.telegram.org/bot{$this->telegram_token_id}/sendMessage";
			$data = array(
				'chat_id'    => trim( $chat_id ),
				'text'       => $message,
				'parse_mode' => 'HTML',
			);

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $data ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_exec( $ch );
			curl_close( $ch );
		}
	}
}
