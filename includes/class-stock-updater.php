<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Stock_Updater
 *
 * A class for parsing XML data and updating product stock status in WooCommerce.
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
	 * XML_Parser constructor.
	 *
	 * @param string $xml_url URL for fetching XML data.
	 */
	public function __construct( $xml_url ) {
		$this->xml_url           = $xml_url;
		$this->telegram_token_id = get_option( 'telegram_token_id', '' );
		$this->telegram_user_ids = array_map( 'trim', explode( ',', get_option( 'telegram_user_ids', '' ) ) );
	}

	/**
	 * Update the stock status of products based on XML data.
	 *
	 * @return void
	 */
	public function update_products_stock_status() {
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		$xml_data = $this->fetch_xml_data();
		if ( ! $xml_data ) {
			return $this->send_telegram_message( 'Failed to retrieve XML data' );
		}

		try {
			$products = new XMLReader();
			$products->open( $this->xml_url );
			if ( $products->isEmpty ) {
				return $this->send_telegram_message( 'XML data is empty or not created' );
			}
		} catch ( Exception $e ) {
			return $this->send_telegram_message( 'XML parsing error: ' . $e->getMessage() );
		}

		$updates = array();
		while ( $products->read() ) {
			if ( $products->nodeType == XMLReader::ELEMENT && $products->localName == 'offer' ) {
				$sku             = (string) $products->getAttribute( 'id' );
				$available       = (string) $products->getAttribute( 'available' );
				$stock_status    = 'true' === $available ? 'instock' : 'outofstock';
				$updates[ $sku ] = $stock_status;
			}
		}
		$products->close();

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

		$this->send_telegram_message(
			sprintf(
				"Products found: %d\nUpdated to 'in stock': %d\nUpdated to 'out of stock': %d\nNot found: %d",
				count( $updates ),
				$updated_in_stock,
				$updated_out_of_stock,
				$not_found
			)
		);

		$this->log_memory_usage( $start_time, $start_memory, 'Stock status update completed' );
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
	 * Find the product IDs by SKUs.
	 *
	 * @param array $skus SKUs of the products.
	 * @return array Associative array of SKU and Product ID.
	 */
	private function get_product_ids_by_skus( $skus ) {
		global $wpdb;

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

	/**
	 * Update the stock status of a product.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $stock_status Stock status.
	 * @return void
	 */
	private function update_product_stock( $product, $stock_status ) {
		$product->set_stock_status( $stock_status );
		$product->save();
		wc_delete_product_transients( $product->get_id() );

		// Update variations if the product is a variable product.
		if ( 'variable' === $product->get_type() ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				$variation->set_stock_status( $stock_status );
				$variation->save();
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

		// // Format the log message
		// $log_message = sprintf(
		// "[%s] %s | Execution time: %.2f sec | Memory usage: %.2f MB\n",
		// date('Y-m-d H:i:s'), $message, $execution_time, $memory_usage
		// );

		// // Send log to Telegram
		// $this->send_telegram_message($log_message);
	}

	/**
	 * Send a message to Telegram.
	 *
	 * @param string $message Message to send.
	 * @return void
	 */
	private function send_telegram_message( $message ) {
		foreach ( $this->telegram_user_ids as $chat_id ) {
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
