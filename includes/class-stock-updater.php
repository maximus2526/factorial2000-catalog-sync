<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Stock_Updater
 *
 * A class for parsing XML data and updating product stock status and prices in WooCommerce.
 * Optimized for weak hosting environments with large product catalogs.
 */
class XML_Stock_Updater {
	/**
	 * URL for fetching XML data.
	 *
	 * @var string
	 */
	private $xml_url;

	/**
	 * Telegram bot token ID.
	 *
	 * @var string
	 */
	private $telegram_token_id;

	/**
	 * Array of Telegram user IDs to send messages to.
	 *
	 * @var array
	 */
	private $telegram_user_ids;

	/**
	 * Batch size for processing products.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Maximum execution time in seconds.
	 *
	 * @var int
	 */
	private $max_execution_time = 0;

	/**
	 * SKU prefix for this XML source.
	 *
	 * @var string
	 */
	private $sku_prefix = '';

	/**
	 * Whether to skip price updates for this XML source.
	 *
	 * @var bool
	 */
	private $skip_price_updates = false;

	/**
	 * XML_Stock_Updater constructor.
	 *
	 * @param string $xml_url URL for fetching XML data.
	 * @param string $sku_prefix Prefix to add to SKU values.
	 */
	public function __construct( $xml_url, $sku_prefix = '', $skip_price_updates = false ) {
		$this->xml_url           = $xml_url;
		$this->telegram_token_id = get_option( 'telegram_token_id', '' );
		$this->telegram_user_ids = array_map( 'trim', explode( ',', get_option( 'telegram_user_ids', '' ) ) );
		$this->sku_prefix        = $sku_prefix;
		$this->skip_price_updates = (bool) $skip_price_updates;

		// Get the current PHP max execution time and set our limit slightly below it
		$current_limit            = ini_get( 'max_execution_time' );
		$this->max_execution_time = ( $current_limit > 0 ) ? $current_limit - 5 : 0;

		// Set batch size based on available memory
		$memory_limit = $this->get_memory_limit_in_bytes();
		if ( $memory_limit < 64 * 1024 * 1024 ) { // Less than 64MB
			$this->batch_size = 20;
		} elseif ( $memory_limit < 128 * 1024 * 1024 ) { // Less than 128MB
			$this->batch_size = 50;
		} else {
			$this->batch_size = 100;
		}
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit_in_bytes() {
		$memory_limit = ini_get( 'memory_limit' );
		$unit         = strtolower( substr( $memory_limit, -1 ) );
		$value        = (int) substr( $memory_limit, 0, -1 );

		switch ( $unit ) {
			case 'g':
				$value *= 1024;
			case 'm':
				$value *= 1024;
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Update the stock status and prices of products based on XML data.
	 *
	 * @return void
	 */
	public function update_products_stock_status() {
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		// Check if we can proceed with the update
		if ( ! $this->xml_url ) {
			prom_log( 'No XML URL provided', 'error' );
			return;
		}

		// Set maximum execution time
		$this->set_max_execution_time();

		// Increase memory limit if possible
		$this->increase_memory_limit();

		prom_log( 'Starting stock and price update process', 'info' );
		$this->send_telegram_message( 'Starting stock and price update process for XML: ' . $this->xml_url . ( $this->skip_price_updates ? ' (ціни не оновлюються)' : '' ) );

		try {
			// Process XML in chunks to extract stock and price data
			$updates = $this->extract_data_from_xml();

			if ( empty( $updates ) ) {
				$this->send_telegram_message( 'No product data found in XML or XML could not be parsed' );
				prom_log( 'No product data found in XML', 'warning' );
				return;
			}

			$total_products = count( $updates );
			prom_log( "Found $total_products products in XML", 'info' );
			$this->send_telegram_message( "Found $total_products products to process" );

			// Process updates in batches
			$this->process_updates_in_batches( $updates );

			// Find and report missing products
			$this->find_missing_products_in_xml( $updates );

			$this->log_memory_usage( $start_time, $start_memory, 'Stock and price update completed' );

		} catch ( Exception $e ) {
			$error_message = 'Error updating products: ' . $e->getMessage();
			prom_log( $error_message, 'error' );
			$this->send_telegram_message( $error_message );
		} finally {
			// Clean up any resources
			$this->cleanup_resources();
		}
	}

	/**
	 * Set maximum execution time for long-running process
	 */
	private function set_max_execution_time() {
		// Only try to change if we can
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 600 ); // 10 minutes
		}
	}

	/**
	 * Increase memory limit if possible
	 */
	private function increase_memory_limit() {
		// Try to increase memory limit to 256MB if less than that
		$current_limit = $this->get_memory_limit_in_bytes();
		if ( $current_limit < 256 * 1024 * 1024 ) {
			@ini_set( 'memory_limit', '256M' );
		}
	}

	/**
	 * Clean up resources after processing
	 */
	private function cleanup_resources() {
		// Clear WP object cache and run garbage collection
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		gc_collect_cycles();

		// Clear WooCommerce transients to free memory
		prom_cleanup_wc_transients();
	}

	/**
	 * Extract stock and price data from XML file.
	 *
	 * @return array Array of [sku => ['stock_status' => status, 'price' => price, 'old_price' => old_price]]
	 */
	private function extract_data_from_xml() {
		$updates = array();
		$reader  = null;

		try {
			$reader = new XMLReader();

			// Try to open the XML file
			if ( ! $reader->open( $this->xml_url, null, LIBXML_NOERROR | LIBXML_NOWARNING ) ) {
				// If direct URL open fails, try to download the file first
				$xml_data = $this->fetch_xml_data();
				if ( ! $xml_data ) {
					throw new Exception( 'Failed to retrieve XML data' );
				}

				// Create a temporary file to store the XML
				$temp_file = wp_tempnam( 'prom_xml_' );
				if ( file_put_contents( $temp_file, $xml_data ) ) {
					$reader->open( $temp_file, null, LIBXML_NOERROR | LIBXML_NOWARNING );
				} else {
					throw new Exception( 'Failed to create temporary XML file' );
				}
			}

			// Check if we have a valid XMLReader object
			if ( ! $reader->read() ) {
				throw new Exception( 'Failed to read XML content' );
			}

			// Extract stock and price data
			while ( $reader->read() ) {
				if ( $reader->nodeType == XMLReader::ELEMENT && $reader->localName == 'offer' ) {
					$sku       = (string) $reader->getAttribute( 'id' );
					$available = (string) $reader->getAttribute( 'available' );
					$group_id  = (string) $reader->getAttribute( 'group_id' );

					// Get the offer node as SimpleXML to extract pricing
					$offer_xml = simplexml_load_string( $reader->readOuterXML() );

					// Extract prices
					$price     = isset( $offer_xml->price ) ? (float) $offer_xml->price : 0;
					$old_price = isset( $offer_xml->oldprice ) ? (float) $offer_xml->oldprice : 0;

					$stock_status = 'true' === $available ? 'instock' : 'outofstock';

					if ( ! empty( $sku ) ) {
						$updates[ $this->sku_prefix . $sku ] = array(
							'stock_status' => $stock_status,
							'price'        => $price,
							'old_price'    => $old_price,
							'group_id'     => $group_id,
						);
					}

					// Free memory to avoid memory leaks
					$reader->next();
				}

				// Free memory periodically
				if ( count( $updates ) % 500 === 0 ) {
					gc_collect_cycles();
				}
			}
		} catch ( Exception $e ) {
			prom_log( 'XML parsing error: ' . $e->getMessage(), 'error' );
			$this->send_telegram_message( 'XML parsing error: ' . $e->getMessage() );
		} finally {
			// Always close the reader to free resources
			if ( $reader !== null ) {
				$reader->close();
			}

			// Remove temporary file if it exists
			if ( isset( $temp_file ) && file_exists( $temp_file ) ) {
				@unlink( $temp_file );
			}
		}

		return $updates;
	}

	/**
	 * Process product updates in batches.
	 *
	 * @param array $updates Array of [sku => ['stock_status' => status, 'price' => price, 'old_price' => old_price]].
	 * @return void
	 */
	private function process_updates_in_batches( $updates ) {
		$total                = count( $updates );
		$processed            = 0;
		$updated_in_stock     = 0;
		$updated_out_of_stock = 0;
		$updated_price        = 0;
		$not_found            = 0;
		$skipped_unchanged    = 0; // Count of products where nothing changed

		// Process in batches to avoid memory issues
		$batches     = array_chunk( $updates, $this->batch_size, true );
		$batch_count = count( $batches );

		$this->send_telegram_message( "Processing $total products in $batch_count batches" );



		$process_start_time = microtime( true );

		foreach ( $batches as $batch_index => $batch ) {
			// Add timing checks to avoid timeouts
			if ( connection_aborted() ) {
				$this->send_telegram_message( "Connection aborted. Processed $processed/$total products." );
				prom_log( "Connection aborted after processing $processed products", 'warning' );
				break;
			}

			// Check if we're approaching the max execution time
			if ( $this->max_execution_time > 0 && ( microtime( true ) - $process_start_time ) > $this->max_execution_time ) {
				$this->send_telegram_message( "Execution time limit approaching. Processed $processed/$total products. Continuing in next run." );
				prom_log( "Execution time limit reached after processing $processed products", 'warning' );
				break;
			}

			// Get product IDs for this batch
			$skus        = array_keys( $batch );
			$product_ids = $this->get_product_ids_by_skus( $skus );

			// Update each product in the batch
			foreach ( $batch as $sku => $product_data ) {
				$product_id = $product_ids[ $sku ] ?? false;

				if ( ! $product_id ) {
					++$not_found;
					continue;
				}

				try {
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						++$not_found;
						continue;
					}

					$changes_made = false;

					// Update stock status if needed
					$stock_status_changed = $this->update_product_stock( $product, $product_data['stock_status'] );
					if ( $stock_status_changed ) {
						$product_data['stock_status'] === 'instock' ? ++$updated_in_stock : ++$updated_out_of_stock;
						$changes_made = true;
					}

					// Update prices if not skipped and needed
					if ( ! $this->skip_price_updates && $product_data['price'] > 0 ) {
						$price_changed = $this->update_product_price( $product, $product_data['price'], $product_data['old_price'] );
						if ( $price_changed ) {
							++$updated_price;
							$changes_made = true;
						}
					}

					if ( ! $changes_made ) {
						++$skipped_unchanged;
					}
				} catch ( Exception $e ) {
					prom_log( "Error updating product $sku: " . $e->getMessage(), 'error' );
				}

				++$processed;
			}



			// Free up memory more aggressively for production
			if ( $batch_index % 2 === 0 ) {
				wp_cache_flush();
				gc_collect_cycles();

				// Sleep briefly to prevent server overload
				usleep( 100000 ); // 100ms
			}
		}

		// Send final results with improved information
		$this->send_telegram_message(
			sprintf(
				"Результати оновлення товарів:\n" .
				"---------------------------\n" .
				"• Всього товарів в XML: %d\n" .
				"• Оброблено товарів: %d\n" .
				"• Оновлено статус \"В наявності\": %d\n" .
				"• Оновлено статус \"Немає в наявності\": %d\n" .
				"• Оновлено цін: %d\n" .
				"• Пропущено (без змін): %d\n" .
				'• Не знайдено товарів: %d',
				$total,
				$processed,
				$updated_in_stock,
				$updated_out_of_stock,
				$updated_price,
				$skipped_unchanged,
				$not_found
			)
		);

		// Log only important info to system log
		prom_log(
			sprintf(
				'Update completed. Total: %d, Stock changed: %d, Price changed: %d, Unchanged: %d, Not found: %d',
				$total,
				$updated_in_stock + $updated_out_of_stock,
				$updated_price,
				$skipped_unchanged,
				$not_found
			),
			'info'
		);

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
	}

	/**
	 * Fetch XML data from the specified URL.
	 *
	 * @return string|false XML data or false on failure.
	 */
	private function fetch_xml_data() {
		// First try to use WordPress HTTP API
		$response = wp_remote_get(
			$this->xml_url,
			array(
				'timeout'     => 60,
				'httpversion' => '1.1',
				'sslverify'   => false,
			)
		);

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			return wp_remote_retrieve_body( $response );
		}

		// Fallback to cURL if WordPress HTTP API fails
		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $this->xml_url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			$body = curl_exec( $ch );
			curl_close( $ch );

			return $body ?: false;
		}

		return false;
	}

	/**
	 * Find the product IDs by SKUs using optimized query.
	 *
	 * @param array $skus SKUs of the products.
	 * @return array Associative array of SKU and Product ID.
	 */
	private function get_product_ids_by_skus( $skus ) {
		global $wpdb;

		if ( empty( $skus ) ) {
			return array();
		}

		// Limit query size to prevent database overload
		if ( count( $skus ) > 500 ) {
			$chunks  = array_chunk( $skus, 500 );
			$results = array();

			foreach ( $chunks as $chunk ) {
				$chunk_results = $this->get_product_ids_by_skus( $chunk );
				$results       = array_merge( $results, $chunk_results );

				// Brief pause to prevent database overload
				usleep( 50000 ); // 50ms
			}

			return $results;
		}

		// Prepare IN clause with proper escaping
		$placeholders = implode( ',', array_fill( 0, count( $skus ), '%s' ) );

		// Use a direct query for better performance with indexes
		$sql = $wpdb->prepare(
			"SELECT pm.meta_value AS sku, p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'draft', 'private')
			AND pm.meta_key = '_sku'
			AND pm.meta_value IN ($placeholders)",
			$skus
		);

		$results = $wpdb->get_results( $sql );
		return array_column( $results, 'ID', 'sku' );
	}

	/**
	 * Update the stock status of a product with minimal operations.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $stock_status Stock status.
	 * @return bool Whether the stock status was changed
	 */
	private function update_product_stock( $product, $stock_status ) {
		// Check if stock status already matches to avoid unnecessary updates
		if ( $product->get_stock_status() === $stock_status ) {
			return false; // No change was made
		}

		// Update directly via database if possible for better performance
		if ( method_exists( $product, 'get_id' ) ) {
			$product_id = $product->get_id();
			update_post_meta( $product_id, '_stock_status', $stock_status );

			// Clear necessary transients only
			wc_delete_product_transients( $product_id );

			// Note: We do NOT automatically update all variations for variable products
			// Each variation should be updated individually based on its own SKU in the XML
		} else {
			// Fallback to standard WooCommerce API if needed
			$product->set_stock_status( $stock_status );
			$product->save();
		}

		return true; // Stock status was changed
	}

	/**
	 * Update stock status for product variations directly via database.
	 *
	 * @param WC_Product_Variable $product Variable product object.
	 * @param string              $stock_status Stock status.
	 * @return void
	 */
	private function update_variation_stock_statuses( $product, $stock_status ) {
		global $wpdb;

		// Get variation IDs directly from database for better performance
		$variation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
			WHERE post_parent = %d 
			AND post_type = 'product_variation' 
			AND post_status = 'publish'",
				$product->get_id()
			)
		);

		if ( empty( $variation_ids ) ) {
			return;
		}

		// Update only variations where status actually needs to change
		foreach ( $variation_ids as $variation_id ) {
			$current_status = get_post_meta( $variation_id, '_stock_status', true );

			// Only update if different
			if ( $current_status !== $stock_status ) {
				update_post_meta( $variation_id, '_stock_status', $stock_status );
				wc_delete_product_transients( $variation_id );
			}
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
		$peak_memory    = memory_get_peak_usage( true ) / 1048576; // Convert to megabytes

		// Format the log message
		$log_message = sprintf(
			'Процес завершено за %.1f сек | Використано пам\'яті: %.1f MB | Пікове використання: %.1f MB',
			$execution_time,
			$memory_usage,
			$peak_memory
		);

		// Send log to Telegram
		$this->send_telegram_message( $log_message );

		// Only log detailed memory usage in debug mode
		if ( WP_DEBUG ) {
			prom_log(
				sprintf(
					'%s | Execution time: %.2f sec | Memory usage: %.2f MB | Peak memory: %.2f MB',
					$message,
					$execution_time,
					$memory_usage,
					$peak_memory
				),
				'info'
			);
		}
	}

	/**
	 * Send a message to Telegram with error handling.
	 *
	 * @param string $message Message to send.
	 * @return void
	 */
	private function send_telegram_message( $message ) {
		if ( empty( $this->telegram_token_id ) || empty( $this->telegram_user_ids ) ) {
			return;
		}

		// Use the helper function from functions.php
		prom_send_telegram_notification( $message );
	}

	/**
	 * Update product prices if they've changed
	 *
	 * @param WC_Product $product Product object
	 * @param float      $price Regular or sale price from XML
	 * @param float      $old_price Old price from XML (if available)
	 * @return bool Whether prices were changed
	 */
	private function update_product_price( $product, $price, $old_price = 0 ) {
		$product_id = $product->get_id();
		$changed    = false;

		// Format prices to ensure consistent decimal places
		$price = number_format( (float) $price, 2, '.', '' );

		// Handle sale pricing if old_price is provided and greater than current price
		if ( $old_price > 0 && $old_price > $price ) {
			$old_price = number_format( (float) $old_price, 2, '.', '' );

			$current_regular_price = $product->get_regular_price();
			$current_sale_price    = $product->get_sale_price();

			// Only update if prices have changed
			if ( $current_regular_price !== $old_price || $current_sale_price !== $price ) {
				update_post_meta( $product_id, '_regular_price', $old_price );
				update_post_meta( $product_id, '_sale_price', $price );
				update_post_meta( $product_id, '_price', $price );
				$changed = true;
			}
		} else {
			// Just update regular price
			$current_regular_price = $product->get_regular_price();

			if ( $current_regular_price !== $price ) {
				update_post_meta( $product_id, '_regular_price', $price );
				update_post_meta( $product_id, '_price', $price );

				// Remove any sale price
				delete_post_meta( $product_id, '_sale_price' );
				$changed = true;
			}
		}

		// Note: We do NOT automatically update all variations for variable products
		// Each variation should be updated individually based on its own SKU in the XML

		// Clear product cache if prices were changed
		if ( $changed ) {
			wc_delete_product_transients( $product_id );
		}

		return $changed;
	}

	/**
	 * Update prices for product variations
	 *
	 * @param WC_Product_Variable $product Variable product object
	 * @param float               $price New price
	 * @param float               $old_price Old price for sales
	 */
	private function update_variation_prices( $product, $price, $old_price = 0 ) {
		global $wpdb;

		// Get variation IDs directly from database for better performance
		$variation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
				WHERE post_parent = %d 
				AND post_type = 'product_variation' 
				AND post_status = 'publish'",
				$product->get_id()
			)
		);

		if ( empty( $variation_ids ) ) {
			return;
		}

		// Update all variations with the same pricing as parent
		foreach ( $variation_ids as $variation_id ) {
			if ( $old_price > 0 && $old_price > $price ) {
				// Apply sale pricing
				update_post_meta( $variation_id, '_regular_price', number_format( (float) $old_price, 2, '.', '' ) );
				update_post_meta( $variation_id, '_sale_price', number_format( (float) $price, 2, '.', '' ) );
				update_post_meta( $variation_id, '_price', number_format( (float) $price, 2, '.', '' ) );
			} else {
				// Regular pricing only
				update_post_meta( $variation_id, '_regular_price', number_format( (float) $price, 2, '.', '' ) );
				update_post_meta( $variation_id, '_price', number_format( (float) $price, 2, '.', '' ) );
				delete_post_meta( $variation_id, '_sale_price' );
			}

			wc_delete_product_transients( $variation_id );
		}
	}

	/**
	 * Find products that exist in database but are missing from XML feed.
	 *
	 * @param array $xml_skus Array of SKUs from XML feed.
	 * @return void
	 */
	private function find_missing_products_in_xml( $xml_skus ) {
		global $wpdb;

		// Only check products with the same SKU prefix as current XML feed
		if ( empty( $this->sku_prefix ) ) {
			return; // Skip if no SKU prefix configured
		}

		// Get SKUs from database that match current feed's prefix
		$db_skus = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value AS sku
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status IN ('publish', 'draft', 'private')
				AND pm.meta_key = '_sku'
				AND pm.meta_value != ''
				AND pm.meta_value IS NOT NULL
				AND pm.meta_value LIKE %s",
				$this->sku_prefix . '%'
			)
		);

		if ( empty( $db_skus ) ) {
			return; // No products with this prefix found
		}

		// Get XML SKU keys
		$xml_sku_keys = array_keys( $xml_skus );

		// Find missing SKUs
		$missing_skus = array_diff( $db_skus, $xml_sku_keys );

		if ( empty( $missing_skus ) ) {
			$this->send_telegram_message( '✅ Всі товари з бази даних присутні у вигрузці XML' );
			return;
		}

		// Get product details for missing SKUs
		$missing_products = $this->get_missing_products_details( $missing_skus );

		// Send report to Telegram
		$this->send_missing_products_report( $missing_products, count( $missing_skus ) );
	}

	/**
	 * Get detailed information about missing products.
	 *
	 * @param array $missing_skus Array of missing SKUs.
	 * @return array Array of product details.
	 */
	private function get_missing_products_details( $missing_skus ) {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $missing_skus ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, pm.meta_value AS sku, p.post_status
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'draft', 'private')
			AND pm.meta_key = '_sku'
			AND pm.meta_value IN ($placeholders)
			ORDER BY p.post_title ASC",
			$missing_skus
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Send missing products report to Telegram.
	 *
	 * @param array $missing_products Array of missing product details.
	 * @param int   $total_missing Total count of missing products.
	 * @return void
	 */
	private function send_missing_products_report( $missing_products, $total_missing ) {
		$message = "⚠️ Товари, яких немає у вигрузці XML:\n\n";
		$message .= "📊 Всього відсутніх товарів: $total_missing\n";
		$message .= "🔗 URL вигрузки: $this->xml_url\n\n";

		// Group products by type (product vs variation)
		$products = array();
		$variations = array();

		foreach ( $missing_products as $product ) {
			$product_obj = wc_get_product( $product->ID );
			if ( $product_obj && $product_obj->get_type() === 'variation' ) {
				$variations[] = $product;
			} else {
				$products[] = $product;
			}
		}

		// Add main products
		if ( ! empty( $products ) ) {
			$message .= "📦 Основні товари (" . count( $products ) . "):\n";
			foreach ( array_slice( $products, 0, 20 ) as $product ) { // Limit to first 20
				$message .= "• {$product->post_title} (SKU: {$product->sku})\n";
			}
			if ( count( $products ) > 20 ) {
				$message .= "... та ще " . ( count( $products ) - 20 ) . " товарів\n";
			}
			$message .= "\n";
		}

		// Add variations
		if ( ! empty( $variations ) ) {
			$message .= "🔄 Варіації товарів (" . count( $variations ) . "):\n";
			foreach ( array_slice( $variations, 0, 20 ) as $variation ) { // Limit to first 20
				$message .= "• {$variation->post_title} (SKU: {$variation->sku})\n";
			}
			if ( count( $variations ) > 20 ) {
				$message .= "... та ще " . ( count( $variations ) - 20 ) . " варіацій\n";
			}
			$message .= "\n";
		}

		$message .= "💡 Рекомендація: Перевірте, чи ці товари дійсно відсутні у постачальника, або це помилка у вигрузці.";

		$this->send_telegram_message( $message );

		// Convert missing products to draft status
		$this->convert_missing_products_to_draft( $missing_products );
	}

	/**
	 * Convert missing products to draft status.
	 *
	 * @param array $missing_products Array of missing product details.
	 * @return void
	 */
	private function convert_missing_products_to_draft( $missing_products ) {
		$converted_count = 0;

		foreach ( $missing_products as $product ) {
			try {
				// Skip variable products (parent) and variations entirely
				$product_obj = wc_get_product( $product->ID );
				if ( $product_obj ) {
					$type = $product_obj->get_type();
					if ( $type === 'variable' || $type === 'variation' ) {
						continue;
					}
				}

				// Update post status to draft
				$result = wp_update_post(
					array(
						'ID'          => $product->ID,
						'post_status' => 'draft',
					),
					true // Return WP_Error on failure
				);

				if ( ! is_wp_error( $result ) ) {
					++$converted_count;
					
					// Clear product cache
					wc_delete_product_transients( $product->ID );
				} else {
					prom_log( "Failed to convert product {$product->sku} to draft: " . $result->get_error_message(), 'error' );
				}
			} catch ( Exception $e ) {
				prom_log( "Error converting product {$product->sku} to draft: " . $e->getMessage(), 'error' );
			}
		}

		if ( $converted_count > 0 ) {
			$this->send_telegram_message( "✅ Переведено в статус 'Чернетка': $converted_count товарів" );
			prom_log( "Converted $converted_count missing products to draft status", 'info' );
		}
	}
}
