<?php

namespace F2000CS;

use Exception;
use XMLReader;
use SimpleXMLElement;
use WC_Product_Variable;

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Parser
 *
 * Imports products from an XML file into WooCommerce.
 * Optimized for weak hosting use.
 */
class XML_Parser {
	protected string $xml_url;
	protected array $categories  = array(); // Cache for categories
	private array $term_cache    = array(); // Cache for terms
	private array $sku_cache     = array(); // Cache for product SKUs
	private bool $new_category   = false;
	protected string $sku_prefix = '';
	protected array $currencies  = array(); // Cache for currency rates (id => rate)

	/**
	 * XML_Parser constructor.
	 *
	 * @param string $file_path Path to the XML file.
	 * @param bool   $new_category Whether to add new products to "New" category.
	 * @param string $sku_prefix Prefix to add to SKU values.
	 */
	public function __construct( string $file_path, bool $new_category, string $sku_prefix = '' ) {
		$this->xml_url      = $file_path;
		$this->new_category = $new_category;
		$this->sku_prefix   = $sku_prefix;
	}

	/**
	 * Transliterate Cyrillic (and similar) text to a URL-safe ASCII slug.
	 *
	 * Used for product post_name, category/attribute term slugs, and (with a
	 * shorter max length) WooCommerce attribute taxonomy names.
	 *
	 * @param string $text       Text to sanitize.
	 * @param int    $max_length Max slug length (WC attribute names need <=28).
	 * @return string Sanitized slug.
	 */
	protected function sanitize_slug( $text, int $max_length = 200 ) {
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

		$text = strtr( (string) $text, $translit );
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9_\-]/', '_', $text );
		$text = preg_replace( '/_+/', '_', $text );
		$text = trim( (string) $text, '_' );

		$max_length = max( 1, $max_length );

		return substr( (string) $text, 0, $max_length );
	}

	/**
	 * Sanitize string for WooCommerce attribute taxonomy name (max 28 chars).
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	protected function sanitize_for_taxonomy( $text ) {
		return $this->sanitize_slug( $text, 28 );
	}

	/**
	 * Insert a taxonomy term with an explicit transliterated slug.
	 *
	 * @param string               $name     Term name (kept as Cyrillic for display).
	 * @param string               $taxonomy Taxonomy.
	 * @param array<string, mixed> $args     Extra wp_insert_term args (parent, etc.).
	 * @return array|\WP_Error
	 */
	protected function insert_term_with_slug( string $name, string $taxonomy, array $args = array() ) {
		$slug = $this->sanitize_slug( $name );
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}

		return wp_insert_term( $name, $taxonomy, $args );
	}

	/**
	 * Ensure WooCommerce global attribute exists in wc table so it appears in admin/UI.
	 * Falls back to direct DB insert if Woo helper is unavailable.
	 *
	 * @param string $attribute_label Human-readable label (e.g., "Колір").
	 * @param string $attribute_slug  Sanitized slug without 'pa_' prefix (e.g., "kolir").
	 * @return void
	 */
	protected function ensure_global_attribute( string $attribute_label, string $attribute_slug ): void {
		global $wpdb;

		if ( empty( $attribute_slug ) ) {
			return;
		}

		// Trim to WC limits
		$attribute_slug = substr( $attribute_slug, 0, 28 );

		$attr_tax_table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $attr_tax_table is the internal WC attribute table name (built from $wpdb->prefix), value is bound via prepare().
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT attribute_id FROM {$attr_tax_table} WHERE attribute_name = %s LIMIT 1", $attribute_slug ) );
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
					// Fallback to direct insert.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert into internal WC attribute table; no caching applicable.
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
				// Fallback to direct insert on any exception.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert into internal WC attribute table; no caching applicable.
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
			// No Woo helper: direct insert.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert into internal WC attribute table; no caching applicable.
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
	 * Imports products from XML.
	 *
	 * @param int $offset Offset for pagination.
	 * @param int $limit Number of products to import.
	 *
	 * @return array Import results.
	 * @throws Exception If the XML file can't be opened.
	 */
	public function import_products( int $offset = 0, int $limit = 10 ): array {
		$this->ensure_categories_loaded();

		// Check if variable products import is enabled (from transient during import session)
		$import_variations_transient = get_transient( 'f2000cs_import_variations_temp' );
		$import_variations           = $import_variations_transient === '1';

		$reader = new XMLReader();

		if ( ! $reader->open( $this->xml_url, null, LIBXML_NONET ) ) {
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

			$offer = simplexml_load_string( $reader->readOuterXML(), null, LIBXML_NONET );
			++$total_offers;

			$offer_data = $this->extract_offer_data( $offer );

			// empty( '0' ) === true in PHP — treat any non-empty string as a real group_id.
			$has_group_id = '' !== trim( (string) $offer_data['group_id'] );

			if ( $import_variations ) {
				// Variable mode: only offers with group_id.
				if ( $has_group_id ) {
					$grouped_products[ $offer_data['group_id'] ][] = $offer_data;
					++$offers_with_group_id;
				} else {
					++$offers_without_group_id;
				}
			} elseif ( ! $has_group_id ) {
				// Simple mode: offers without group_id.
				$simple_products[] = $offer_data;
				++$offers_without_group_id;
			} else {
				// Simple mode: keep grouped offers only to promote single-offer groups to simple.
				$grouped_products[ $offer_data['group_id'] ][] = $offer_data;
				++$offers_with_group_id;
			}
		}

		$reader->close();

		if ( ! $import_variations ) {
			// Promote lone group_id offers to simple; never create variable products in this mode.
			foreach ( $grouped_products as $group_id => $variations ) {
				if ( 1 === count( $variations ) ) {
					$simple_products[] = $variations[0];
				}
			}
			$grouped_products = array();
		} else {
			// Variable mode: analyze/import only groups with 2+ offers.
			foreach ( $grouped_products as $group_id => $variations ) {
				if ( count( $variations ) < 2 ) {
					unset( $grouped_products[ $group_id ] );
				}
			}
		}

		// Second pass: Create products.
		$imported       = 0;
		$skipped        = 0;
		$processed      = 0;
		$current_offset = 0;

		if ( $import_variations ) {
			$total_products      = count( $grouped_products );
			$items_to_process    = $grouped_products;
			$process_as_variable = true;
		} else {
			$total_products      = count( $simple_products );
			$items_to_process    = $simple_products;
			$process_as_variable = false;
		}

		if ( $process_as_variable ) {
			foreach ( $items_to_process as $group_id => $variations_data ) {
				if ( $current_offset < $offset ) {
					++$current_offset;
					continue;
				}

				if ( $processed >= $limit ) {
					break;
				}

				$result = $this->import_variable_product( (string) $group_id, $variations_data );
				if ( $result ) {
					++$imported;
				} else {
					++$skipped;
				}

				++$processed;
				++$current_offset;
			}
		} else {
			foreach ( $items_to_process as $offer_data ) {
				if ( $current_offset < $offset ) {
					++$current_offset;
					continue;
				}

				if ( $processed >= $limit ) {
					break;
				}

				$result = $this->import_simple_product( $offer_data );
				if ( $result ) {
					++$imported;
				} else {
					++$skipped;
				}

				++$processed;
				++$current_offset;
			}
		}

		return array(
			'imported'  => $imported,
			'skipped'   => $skipped,
			'processed' => $processed,
			'total'     => $total_products,
			'finished'  => ( $offset + $processed ) >= $total_products,
		);
	}

	/**
	 * Load categories from the XML (once) and preload matching term cache.
	 *
	 * @return void
	 */
	protected function ensure_categories_loaded(): void {
		if ( ! empty( $this->categories ) ) {
			return;
		}

		$this->categories = $this->load_categories_from_xml();
		$this->preload_category_terms();

		if ( empty( $this->currencies ) ) {
			$this->currencies = $this->load_currencies_from_xml();
		}
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

		$price     = f2000cs_parse_price( (string) $offer->price );
		$old_price = isset( $offer->oldprice ) ? f2000cs_parse_price( (string) $offer->oldprice ) : 0;

		// Apply currency conversion if the offer uses a different currency.
		$store_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'UAH';
		if ( isset( $offer->currencyId ) ) {
			$offer_currency = (string) $offer->currencyId;
			if ( $offer_currency !== $store_currency && isset( $this->currencies[ $offer_currency ] ) ) {
				$rate = $this->currencies[ $offer_currency ];
				if ( $rate > 0 && $rate !== 1.0 ) {
					$price     = round( $price * $rate, 2 );
					$old_price = round( $old_price * $rate, 2 );
				}
			}
		}
		$desc = (string) $offer->description;

		if ( ! empty( $offer->description_ua ) ) {
			$desc = (string) $offer->description_ua;
		}

		// Support multiple <categoryId> elements (YML allows several per offer).
		$categories = array();
		if ( isset( $offer->categoryId ) ) {
			foreach ( $offer->categoryId as $cid ) {
				$cid_str = trim( (string) $cid );
				if ( '' !== $cid_str ) {
					$categories[] = $cid_str;
				}
			}
		}
		$category  = ! empty( $categories ) ? implode( ',', $categories ) : '';
		$available = f2000cs_parse_available( (string) $offer['available'] );
		$vendor    = isset( $offer->vendor ) ? (string) $offer->vendor : '';

		// Get all product images
		$images = array();
		foreach ( $offer->picture as $picture ) {
			$images[] = (string) $picture;
		}

		// Get all product attributes (supports multiple values per name and unit attribute).
		$attributes = array();
		if ( isset( $offer->param ) ) {
			foreach ( $offer->param as $param ) {
				$name  = (string) $param['name'];
				$value = (string) $param;
				if ( ! empty( $name ) && '' !== $value ) {
					$unit       = isset( $param['unit'] ) ? ' ' . trim( (string) $param['unit'] ) : '';
					$full_value = $value . $unit;

					if ( isset( $attributes[ $name ] ) ) {
						$attributes[ $name ] .= '; ' . $full_value;
					} else {
						$attributes[ $name ] = $full_value;
					}
				}
			}
		}

		// Add vendor to attributes if available
		if ( ! empty( $vendor ) && ! isset( $attributes['Виробник'] ) ) {
			$attributes['Виробник'] = $vendor;
		}

		// Physical product fields.
		$weight     = isset( $offer->weight ) ? f2000cs_parse_price( (string) $offer->weight ) : 0;
		$barcode    = isset( $offer->barcode ) ? (string) $offer->barcode : '';
		$dimensions = isset( $offer->dimensions ) ? (string) $offer->dimensions : '';

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
			'weight'     => $weight,
			'barcode'    => $barcode,
			'dimensions' => $dimensions,
		);
	}

	/**
	 * Import a simple product.
	 *
	 * @param array $offer_data Offer data.
	 * @return bool True if imported, false if skipped.
	 */
	protected function import_simple_product( array $offer_data ): bool {
		$sku        = $offer_data['sku'];
		$title      = $offer_data['title'];
		$price      = $offer_data['price'];
		$old_price  = $offer_data['old_price'];
		$desc       = $offer_data['desc'];
		$category   = $offer_data['category'];
		$available  = $offer_data['available'];
		$images     = $offer_data['images'];
		$attributes = $offer_data['attributes'];
		$weight     = isset( $offer_data['weight'] ) ? (float) $offer_data['weight'] : 0;
		$barcode    = isset( $offer_data['barcode'] ) ? (string) $offer_data['barcode'] : '';
		$dimensions = isset( $offer_data['dimensions'] ) ? (string) $offer_data['dimensions'] : '';

		if ( empty( $sku ) || empty( $title ) || $price <= 0 ) {
			return false;
		}

		// Feed content is third-party: strip unsafe tags from titles and
		// sanitize descriptions against the post_content allowed set.
		$title = wp_strip_all_tags( (string) $title );
		$desc  = wp_kses_post( (string) $desc );

		// Check if product already exists (передаємо SKU без префіксу)
		$existing_product = $this->get_product_ids_by_skus( array( $sku ) );
		if ( ! empty( $existing_product ) && isset( $existing_product[ $sku ] ) ) {
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
				'post_name'    => $this->sanitize_slug( $title ),
				'post_content' => $desc,
				'post_status'  => 'publish',
				'post_type'    => 'product',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, '_sku', $sku );

		if ( $old_price > 0 && $old_price > $price ) {
			update_post_meta( $post_id, '_regular_price', number_format( $old_price, 2, '.', '' ) );
			update_post_meta( $post_id, '_sale_price', number_format( $price, 2, '.', '' ) );
			update_post_meta( $post_id, '_price', number_format( $price, 2, '.', '' ) );
		} else {
			update_post_meta( $post_id, '_regular_price', number_format( $price, 2, '.', '' ) );
			update_post_meta( $post_id, '_price', number_format( $price, 2, '.', '' ) );
		}

		f2000cs_update_stock_status( $post_id, $available );
		update_post_meta( $post_id, '_manage_stock', 'no' );

		if ( $weight > 0 ) {
			update_post_meta( $post_id, '_weight', $weight );
		}
		if ( '' !== $barcode ) {
			update_post_meta( $post_id, '_barcode', $barcode );
		}
		if ( '' !== $dimensions ) {
			update_post_meta( $post_id, '_dimensions', $dimensions );
		}

		// Handle product attributes
		if ( ! empty( $attributes ) ) {
			$this->set_product_attributes( $post_id, $attributes );
		}

		// Handle product images
		if ( ! empty( $images ) ) {
			$this->handle_product_images( $post_id, $images );
		}

		if ( ! $this->new_category ) {
			$this->set_product_category( $post_id, $category );
		}

		if ( $this->new_category ) {
			if ( term_exists( 'Новинки', 'product_cat' ) === 0 ) {
				$this->insert_term_with_slug( 'Новинки', 'product_cat' );
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
	protected function import_variable_product( string $group_id, array $variations_data ): bool {
		if ( empty( $variations_data ) ) {
			return false;
		}

		// Check if parent product already exists (передаємо group_id без префіксу, функція поверне з ключем без префіксу)
		$existing_parent = $this->get_product_ids_by_skus( array( $group_id ) );
		if ( ! empty( $existing_parent ) && isset( $existing_parent[ $group_id ] ) ) {
			return false;
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
				'post_title'   => wp_strip_all_tags( (string) $parent_name ),
				'post_name'    => $this->sanitize_slug( $parent_name ),
				'post_content' => wp_kses_post( (string) $base_data['desc'] ),
				'post_status'  => 'publish',
				'post_type'    => 'product',
			)
		);

		if ( is_wp_error( $parent_id ) || ! $parent_id ) {
			return false;
		}

		wp_set_object_terms( $parent_id, 'variable', 'product_type' );
		update_post_meta( $parent_id, '_sku', $parent_sku );
		f2000cs_update_stock_status( $parent_id, 'instock' );
		update_post_meta( $parent_id, '_manage_stock', 'no' );

		if ( ! empty( $base_data['weight'] ) && (float) $base_data['weight'] > 0 ) {
			update_post_meta( $parent_id, '_weight', (float) $base_data['weight'] );
		}
		if ( ! empty( $base_data['barcode'] ) ) {
			update_post_meta( $parent_id, '_barcode', (string) $base_data['barcode'] );
		}
		if ( ! empty( $base_data['dimensions'] ) ) {
			update_post_meta( $parent_id, '_dimensions', (string) $base_data['dimensions'] );
		}

		if ( ! $this->new_category ) {
			$this->set_product_category( $parent_id, $base_data['category'] );
		}

		if ( $this->new_category ) {
			if ( term_exists( 'Новинки', 'product_cat' ) === 0 ) {
				$this->insert_term_with_slug( 'Новинки', 'product_cat' );
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
			// Delete parent product since we can't create variations
			wp_delete_post( $parent_id, true );
			return false;
		}

		$attributes_info = array();
		foreach ( $variation_attributes as $attr_name => $attr_values ) {
			$attributes_info[] = $attr_name . ' (' . count( $attr_values ) . ' values)';
		}

		f2000cs_log(
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

		$created = $this->create_product_variations( $parent_id, $variations_data, $variation_attributes );
		if ( $created < 1 ) {
			wp_delete_post( $parent_id, true );
			return false;
		}

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
		$selected_attributes_map = get_transient( 'f2000cs_selected_attributes_temp' );
		if ( ! empty( $selected_attributes_map ) && isset( $selected_attributes_map[ $group_id ] ) ) {
			$selected_attrs = $selected_attributes_map[ $group_id ];

			// Підтримка масиву атрибутів (новий формат) або одного атрибута (старий формат)
			if ( ! is_array( $selected_attrs ) ) {
				$selected_attrs = array( $selected_attrs );
			}

			$variation_attributes = array();
			$skipped_attrs        = array();

			foreach ( $selected_attrs as $selected_attr ) {
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

			if ( ! empty( $variation_attributes ) ) {
				return $variation_attributes;
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

				break; // Беремо тільки ОДИН атрибут!
			}
		}

		// If no priority attributes found, use any attribute that varies
		if ( empty( $variation_attributes ) ) {
			foreach ( $all_attributes as $attr_name => $attr_values ) {
				if ( count( $attr_values ) > 1 ) {
					$variation_attributes[ $attr_name ] = $attr_values;
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
			if ( empty( $attr_values ) ) {
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
					$term_info = $this->insert_term_with_slug( $value, $taxonomy );
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

					foreach ( $this->split_attribute_values( (string) $attr_value ) as $single_value ) {
						if ( ! in_array( $single_value, $non_variation_attributes[ $attr_name ], true ) ) {
							$non_variation_attributes[ $attr_name ][] = $single_value;
						}
					}
				}
			}
		}

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
					$term_info = $this->insert_term_with_slug( $attr_value, $taxonomy );
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
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				);
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

			f2000cs_log(
				sprintf(
					'Set %d total attributes for variable product ID %d (variation: %d, non-variation: %d)',
					count( $product_attributes ),
					$parent_id,
					$variation_count,
					$non_variation_count
				)
			);
		} else {
			f2000cs_log( sprintf( 'No attributes to set for variable product ID %d', $parent_id ) );
		}
	}

	/**
	 * Create product variations.
	 *
	 * @param int   $parent_id Parent product ID.
	 * @param array $variations_data Variations data.
	 * @param array $variation_attributes Variation attributes.
	 * @return int Number of variations created.
	 */
	private function create_product_variations( int $parent_id, array $variations_data, array $variation_attributes ): int {
		$created = 0;

		foreach ( $variations_data as $variation_data ) {
			$original_sku = isset( $variation_data['sku'] ) ? (string) $variation_data['sku'] : '';
			$title        = isset( $variation_data['title'] ) ? wp_strip_all_tags( (string) $variation_data['title'] ) : '';
			$price        = isset( $variation_data['price'] ) ? (float) $variation_data['price'] : 0;

			if ( '' === $original_sku || '' === $title || $price <= 0 ) {
				f2000cs_log( sprintf( 'Skipping variation with invalid sku/title/price (sku=%s)', $original_sku ) );
				continue;
			}

			// Check if variation already exists (передаємо SKU без префіксу)
			$existing_variation = $this->get_product_ids_by_skus( array( $original_sku ) );
			if ( ! empty( $existing_variation ) && isset( $existing_variation[ $original_sku ] ) ) {
				f2000cs_log( sprintf( 'Skipping variation with SKU %s - already exists (ID: %d)', $original_sku, $existing_variation[ $original_sku ] ) );
				continue; // Skip if exists
			}

			// Apply SKU prefix для створення
			$variation_sku = $original_sku;
			if ( ! empty( $this->sku_prefix ) ) {
				$variation_sku = $this->sku_prefix . $variation_sku;
			}

			$variation_id = wp_insert_post(
				array(
					'post_title'  => $title,
					'post_name'   => $this->sanitize_slug( $title ),
					'post_status' => 'publish',
					'post_parent' => $parent_id,
					'post_type'   => 'product_variation',
				)
			);

			if ( is_wp_error( $variation_id ) || ! $variation_id ) {
				continue;
			}

			update_post_meta( $variation_id, '_sku', $variation_sku );

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

			f2000cs_update_stock_status( $variation_id, $variation_data['available'] );
			update_post_meta( $variation_id, '_manage_stock', 'no' );

			if ( ! empty( $variation_data['weight'] ) && (float) $variation_data['weight'] > 0 ) {
				update_post_meta( $variation_id, '_weight', (float) $variation_data['weight'] );
			}
			if ( ! empty( $variation_data['barcode'] ) ) {
				update_post_meta( $variation_id, '_barcode', (string) $variation_data['barcode'] );
			}
			if ( ! empty( $variation_data['dimensions'] ) ) {
				update_post_meta( $variation_id, '_dimensions', (string) $variation_data['dimensions'] );
			}

			foreach ( $variation_attributes as $attr_name => $attr_values ) {
				$taxonomy_name = $this->sanitize_for_taxonomy( $attr_name );
				$taxonomy      = 'pa_' . $taxonomy_name;

				if ( strlen( $taxonomy ) > 32 ) {
					$taxonomy = substr( $taxonomy, 0, 32 );
				}

				if ( isset( $variation_data['attributes'][ $attr_name ] ) ) {
					$attr_value = $variation_data['attributes'][ $attr_name ];

					$term = get_term_by( 'name', $attr_value, $taxonomy );
					if ( $term ) {
						update_post_meta( $variation_id, 'attribute_' . $taxonomy, $term->slug );
					}
				}
			}

			// Handle variation images if they differ
			if ( ! empty( $variation_data['images'] ) ) {
				$this->set_variation_image( $variation_id, $variation_data['images'][0] );
			}

			++$created;

			// Clear product cache
			wc_delete_product_transients( $parent_id );
			wc_delete_product_transients( $variation_id );
		}

		if ( $created > 0 ) {
			WC_Product_Variable::sync( $parent_id );
		}

		return $created;
	}

	/**
	 * Set variation image.
	 *
	 * @param int    $variation_id Variation ID.
	 * @param string $image_url Image URL.
	 * @return void
	 */
	protected function set_variation_image( int $variation_id, string $image_url ): void {
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = f2000cs_download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			return;
		}

		$file_array = class_exists( __NAMESPACE__ . '\\Image_Processor' )
			? Image_Processor::prepare_sideload( $tmp, $image_url )
			: array(
				'name'     => basename( $image_url ),
				'tmp_name' => $tmp,
			);

		$attachment_id = media_handle_sideload( $file_array, $variation_id );
		if ( is_wp_error( $attachment_id ) ) {
			if ( ! empty( $file_array['tmp_name'] ) ) {
				wp_delete_file( $file_array['tmp_name'] );
			}
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

		if ( ! $reader->open( $this->xml_url, null, LIBXML_NONET ) ) {
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
						'parent' => $parent_id ? $parent_id : null,
					);
				}
			}
		}

		$reader->close();
		return $categories;
	}

	/**
	 * Parse <currencies> section from YML XML.
	 *
	 * @return array<string,float> Currency ID => rate mapping.
	 */
	private function load_currencies_from_xml(): array {
		$currencies = array();
		$reader     = new XMLReader();
		$temp_file  = null;

		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Fallback path catches open failure (same as stock updater).
			if ( ! @$reader->open( $this->xml_url, null, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET ) ) {
				$xml_data = $this->fetch_xml_data();
				if ( ! $xml_data ) {
					return $currencies;
				}

				$temp_file = wp_tempnam( 'f2000cs_curr_' );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temp file for XMLReader.
				if ( ! file_put_contents( $temp_file, $xml_data ) ) {
					return $currencies;
				}

				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Local temp open failure returns empty map.
				if ( ! @$reader->open( $temp_file, null, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET ) ) {
					return $currencies;
				}
			}

			while ( $reader->read() ) {
				if ( XMLReader::ELEMENT === $reader->nodeType && 'currency' === $reader->name ) {
					$currency_id = $reader->getAttribute( 'id' );
					$rate        = $reader->getAttribute( 'rate' );
					if ( $currency_id && is_numeric( $rate ) && (float) $rate > 0 ) {
						$currencies[ $currency_id ] = (float) $rate;
					}
				}
			}
		} finally {
			$reader->close();

			if ( $temp_file !== null && file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
		}

		return $currencies;
	}

	/**
	 * Assigns a product to the correct category by its XML category ID.
	 *
	 * @param int    $post_id      Product ID.
	 * @param string $category_ids Comma-separated XML category IDs.
	 */
	protected function set_product_category( int $post_id, string $category_ids ): void {
		// Support comma-separated list of category IDs (YML allows multiple <categoryId>).
		$ids = array_filter( array_map( 'trim', explode( ',', $category_ids ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		$term_ids = array();
		foreach ( $ids as $category_id ) {
			if ( ! isset( $this->categories[ $category_id ] ) ) {
				continue;
			}
			$term_id = $this->ensure_category_term( $category_id, $this->categories, $this->term_cache );
			if ( $term_id ) {
				$term_ids[] = (int) $term_id;
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, 'product_cat' );
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

		$term = get_term_by( 'name', $name, 'product_cat' );
		if ( ! $term ) {
			$term = $this->insert_term_with_slug(
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
	 * Split multi-value attribute strings (joined with "; " during XML extract).
	 *
	 * @param string $value Raw attribute value.
	 * @return array<int, string>
	 */
	protected function split_attribute_values( string $value ): array {
		$parts = array_map( 'trim', explode( ';', $value ) );

		return array_values(
			array_filter(
				$parts,
				static function ( $part ) {
					return '' !== $part;
				}
			)
		);
	}

	/**
	 * Sets product attributes.
	 *
	 * @param int   $post_id    Product ID.
	 * @param array $attributes Array of attributes [name => value].
	 */
	protected function set_product_attributes( int $post_id, array $attributes ): void {
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

			$term_ids = array();
			foreach ( $this->split_attribute_values( (string) $value ) as $single_value ) {
				$term = get_term_by( 'name', $single_value, $taxonomy );
				if ( ! $term ) {
					$term_info = $this->insert_term_with_slug( $single_value, $taxonomy );
					if ( ! is_wp_error( $term_info ) ) {
						$term_ids[] = (int) $term_info['term_id'];
					}
				} else {
					$term_ids[] = (int) $term->term_id;
				}
			}

			$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, $term_ids, $taxonomy );
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
			f2000cs_log( sprintf( 'Set %d attributes for product ID %d', count( $product_attributes ), $post_id ) );
		}
	}

	/**
	 * Downloads and attaches multiple product images (first as featured, others as gallery).
	 *
	 * @param int   $post_id Product ID.
	 * @param array $urls    Array of image URLs.
	 */
	protected function handle_product_images( int $post_id, array $urls ): void {
		if ( empty( $urls ) ) {
			return;
		}

		add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids     = array();
		$featured_image_set = false;

		foreach ( $urls as $index => $url ) {
			$tmp = f2000cs_download_url( $url );

			if ( is_wp_error( $tmp ) ) {
				continue;
			}

			$file_array = class_exists( __NAMESPACE__ . '\\Image_Processor' )
				? Image_Processor::prepare_sideload( $tmp, $url )
				: array(
					'name'     => basename( $url ),
					'tmp_name' => $tmp,
				);

			$attachment_id = media_handle_sideload( $file_array, $post_id );

			if ( is_wp_error( $attachment_id ) ) {
				if ( ! empty( $file_array['tmp_name'] ) ) {
					wp_delete_file( $file_array['tmp_name'] );
				}
				continue;
			}

			$attachment_ids[] = $attachment_id;

			if ( 0 === (int) $index && ! $featured_image_set ) {
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
	protected function get_product_ids_by_skus( array $skus ): array {
		if ( empty( $skus ) ) {
			return array();
		}

		// Add prefix to SKUs for database search if prefix is set
		$skus_with_prefix = array();
		foreach ( $skus as $sku ) {
			$skus_with_prefix[] = ! empty( $this->sku_prefix ) ? $this->sku_prefix . $sku : $sku;
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
			$db_results = f2000cs_get_product_ids_by_skus( $uncached_skus );

			// Add to cache and map back to original SKU (without prefix).
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
		$response = wp_remote_get(
			$this->xml_url,
			array(
				'timeout'   => 90,  // below Cloudflare's 100 s proxy timeout
				'sslverify' => f2000cs_ssl_verify_enabled(),
			)
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			if ( ! empty( $body ) ) {
				return $body;
			}
		}

		return false;
	}
}
