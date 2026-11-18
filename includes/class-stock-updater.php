<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy class name kept for backward compatibility with the loader in the main plugin file.

namespace F2000CS;

use Exception;
use XMLReader;

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Stock_Updater
 *
 * A class for parsing XML data and updating product stock status and prices in WooCommerce.
 * Optimized for weak hosting environments with large product catalogs.
 */
class XML_Stock_Updater {
	/**
	 * Post meta set on products the plugin drafted because they vanished from the feed.
	 */
	const AUTO_DRAFT_META = '_f2000cs_auto_drafted';

	/**
	 * Meta key with the status a product had before the plugin drafted it ('publish' or 'private').
	 */
	const AUTO_DRAFT_FROM_META = '_f2000cs_auto_drafted_from';

	/**
	 * URL for fetching XML data.
	 *
	 * @var string
	 */
	private $xml_url;

	/**
	 * Variable parents whose variations changed price in the current batch (id => true).
	 *
	 * @var array<int,bool>
	 */
	private $price_changed_parents = array();

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
	private $batch_size = 100;

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
	 * Whether the supplier's <oldprice> is ignored: it becomes the regular price, no sale is written.
	 *
	 * @var bool
	 */
	private $ignore_discounts = false;

	/**
	 * Whether to update stock quantity from XML (quantity / stock_quantity).
	 *
	 * @var bool
	 */
	private $update_stock_qty = false;

	/**
	 * Leave the stock status of products with managed stock untouched.
	 *
	 * @var bool
	 */
	private $keep_managed_status = false;

	/**
	 * Why the last parsed feed failed validation (empty when it is fine).
	 *
	 * @var string
	 */
	private $feed_error = '';

	/**
	 * Whether the feed could not be downloaded at all (as opposed to downloaded but invalid).
	 *
	 * @var bool
	 */
	private $feed_unreachable = false;

	/**
	 * Local file the feed is parsed from (the downloaded copy, or the local path itself).
	 *
	 * @var string
	 */
	private $feed_source = '';

	/**
	 * Downloaded copy of a remote feed, deleted at the end of the run.
	 *
	 * @var string
	 */
	private $feed_temp = '';

	/**
	 * Offer count of the previous run of this feed (0 = unknown).
	 *
	 * @var int
	 */
	private $previous_offer_count = 0;

	/**
	 * Data of the short Telegram report sent once at the end of a run.
	 *
	 * @var array<string,mixed>
	 */
	private $report = array();

	/**
	 * Currency rates parsed from XML <currencies> section.
	 *
	 * @var array<string,float>
	 */
	private $currencies = array();

	/**
	 * Price adjustment type: 'margin', 'markup' or 'fixed'.
	 *
	 * @var string
	 */
	private $price_adjust_type = 'markup';

	/**
	 * Price adjustment direction: 'add' or 'subtract'. Not used for 'margin'.
	 *
	 * @var string
	 */
	private $price_adjust_direction = 'add';

	/**
	 * Price adjustment magnitude. Percentage for 'margin'/'markup', currency amount for 'fixed'.
	 *
	 * @var float
	 */
	private $price_adjust_value = 0.0;

	/**
	 * Largest share of the price (in %) a fixed subtraction may take off.
	 *
	 * @var float
	 */
	private $price_adjust_max_share = 5.0;

	/**
	 * Token of the run lock this run holds ('' when none).
	 *
	 * @var string
	 */
	private $run_lock_token = '';

	/**
	 * Supplier slot of this feed (0 = a URL that matches no slot).
	 *
	 * @var int
	 */
	private $slot = 0;

	/**
	 * Feed SKUs (with prefix) that matched no product in this run.
	 *
	 * @var string[]
	 */
	private $not_found_skus = array();

	/**
	 * XML_Stock_Updater constructor.
	 *
	 * @param string $xml_url URL for fetching XML data.
	 * @param string $sku_prefix Prefix to add to SKU values.
	 * @param bool   $skip_price_updates Whether to skip price updates for this source.
	 * @param array  $price_adjust {
	 *     Optional. Price adjustment settings applied to the supplier price.
	 *
	 *     @type string $type      'margin', 'markup' or 'fixed'. Default 'markup'.
	 *     @type string $direction 'add' or 'subtract'. Ignored for 'margin'. Default 'add'.
	 *     @type float  $value     Percentage (margin/markup) or currency amount (fixed). Default 0.
	 *     @type float  $max_share A fixed subtraction takes at most this % of the price. Default 5.
	 * }
	 * @param bool   $update_stock_qty Whether to update stock quantity from XML.
	 * @param bool   $keep_managed_status Leave the stock status of products with managed
	 *                                    stock alone (their quantity is not updated either).
	 * @param bool   $ignore_discounts    Use <oldprice> as the regular price and create no sale.
	 */
	public function __construct( $xml_url, $sku_prefix = '', $skip_price_updates = false, $price_adjust = array(), $update_stock_qty = false, $keep_managed_status = false, $ignore_discounts = false ) {
		$this->xml_url            = $xml_url;
		$this->telegram_token_id  = get_option( 'f2000cs_telegram_token_id', '' );
		$this->telegram_user_ids  = array_map( 'trim', explode( ',', get_option( 'f2000cs_telegram_user_ids', '' ) ) );
		$this->sku_prefix         = $sku_prefix;
		$this->skip_price_updates = (bool) $skip_price_updates;
		$this->update_stock_qty   = (bool) $update_stock_qty;

		$this->keep_managed_status = (bool) $keep_managed_status;
		$this->ignore_discounts    = (bool) $ignore_discounts;

		$price_adjust = wp_parse_args(
			$price_adjust,
			array(
				'type'      => 'markup',
				'direction' => 'add',
				'value'     => 0,
				'max_share' => F2000CS_FIXED_SUBTRACT_MAX_SHARE,
			)
		);

		$this->price_adjust_type      = in_array( $price_adjust['type'], array( 'margin', 'markup', 'fixed' ), true ) ? $price_adjust['type'] : 'markup';
		$this->price_adjust_direction = 'subtract' === $price_adjust['direction'] ? 'subtract' : 'add';
		$this->price_adjust_value     = max( 0, (float) $price_adjust['value'] );
		$this->price_adjust_max_share = f2000cs_sanitize_max_share( $price_adjust['max_share'] );

		// Get the current PHP max execution time and set our limit slightly below it
		$current_limit            = ini_get( 'max_execution_time' );
		$this->max_execution_time = ( $current_limit > 0 ) ? $current_limit - 5 : 0;
	}

	/**
	 * Supplier slot this feed belongs to (enables per-slot options such as importing missing offers).
	 *
	 * @param int $slot Supplier slot 1+.
	 * @return void
	 */
	public function set_slot( $slot ) {
		$this->slot = absint( $slot );
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit_in_bytes() {
		$memory_limit = trim( (string) ini_get( 'memory_limit' ) );

		// -1 (WP-CLI, unlimited) has no ceiling at all; it used to read as 0 and get capped to 256M.
		if ( '' === $memory_limit || (int) $memory_limit < 0 ) {
			return PHP_INT_MAX;
		}

		$unit  = strtolower( substr( $memory_limit, -1 ) );
		$value = ctype_digit( $unit ) ? (int) $memory_limit : (int) substr( $memory_limit, 0, -1 );

		switch ( $unit ) {
			case 'g':
				$value *= 1024;
				// no break
			case 'm':
				$value *= 1024;
				// no break
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

		if ( ! $this->xml_url ) {
			return;
		}

		// Cron, the «Run update» button and a background event can start the same feed at
		// once: the second run would share the resume position and double every change.
		if ( ! $this->acquire_run_lock() ) {
			$this->report = array(
				'level'  => 'ok',
				'url'    => $this->xml_url,
				'prefix' => $this->sku_prefix,
			);
			$this->report_problem( 'warn', 'Оновлення цієї вигрузки вже виконується — цей запуск пропущено.' );
			$this->send_report( 0 );

			return;
		}

		$this->set_max_execution_time();
		$this->increase_memory_limit();

		$this->report = array(
			'level'  => 'ok',
			'url'    => $this->xml_url,
			'prefix' => $this->sku_prefix,
		);

		$this->send_telegram_message(
			'Старт оновлення наявності: ' . f2000cs_telegram_feed_label( $this->xml_url )
			. ( '' !== $this->sku_prefix ? ' · префікс ' . $this->sku_prefix : '' )
			. ( $this->skip_price_updates ? ' (ціни не оновлюються)' : '' )
			. ( $this->ignore_discounts ? ' (знижки постачальника не завантажуються)' : '' )
			. ( $this->update_stock_qty ? ' (кількість оновлюється)' : '' )
		);

		try {
			// The whole feed is downloaded to a local file first: a connection dropped half way
			// is then a download failure (the feed is unreachable, stock is kept for a grace
			// period), not a truncated document that would take every product off sale.
			if ( $this->prepare_feed_source() ) {
				// Parse currency rates for conversion.
				$this->currencies = $this->load_currencies_from_xml();

				// Process XML in chunks to extract stock and price data.
				$updates = $this->extract_data_from_xml();
			} else {
				$updates = array();
			}

			if ( empty( $updates ) && '' === $this->feed_error ) {
				$this->feed_error = 'у вигрузці немає жодної пропозиції (offer)';
			}

			// A feed that could not be downloaded says nothing about the stock: keep it and
			// alert, and take the products off sale only when the outage lasts too long.
			if ( $this->feed_unreachable ) {
				$this->handle_unreachable_feed();
				return;
			}

			delete_option( $this->outage_option() );

			// An invalid feed (broken XML, no offers): nothing in it can be trusted,
			// so every product of this supplier is taken off sale until a valid feed comes back.
			if ( '' !== $this->feed_error ) {
				$this->report_problem( 'error', 'Вигрузка некоректна: ' . $this->feed_error );
				$this->mark_all_out_of_stock();
				return;
			}

			$total_products        = count( $updates );
			$this->report['total'] = $total_products;
			$this->send_telegram_message( "Товарів у вигрузці: $total_products" );

			// Process updates in batches
			$this->process_updates_in_batches( $updates );

			// Find and report missing products. A feed that suddenly lists far fewer offers is
			// more likely cut on the supplier side than a real clear-out: the products it lacks
			// are left alone for this run.
			if ( $this->feed_shrank( $total_products ) ) {
				$this->report_problem( 'warn', 'Вигрузка різко зменшилась (' . $total_products . ' пропозицій проти ' . $this->previous_offer_count . ' минулого разу) — товари, яких у ній немає, цього разу не змінювались.' );
			} else {
				$this->find_missing_products_in_xml( $updates );
			}

			// Offers of the feed that are not on the site yet (Pro option per supplier).
			$this->import_missing_offers( $start_time );

			$this->log_memory_usage( $start_time, $start_memory, 'Оновлення завершено' );

		} catch ( Exception $e ) {
			$this->report_problem( 'error', 'Помилка оновлення: ' . $e->getMessage() );
		} finally {
			if ( '' !== $this->feed_temp && file_exists( $this->feed_temp ) ) {
				wp_delete_file( $this->feed_temp );
			}

			$this->feed_temp   = '';
			$this->feed_source = '';

			$this->cleanup_resources();
			$this->send_report( microtime( true ) - $start_time );
			$this->release_run_lock();
		}
	}

	/**
	 * Hand the offers that matched no product to the importer of missing offers.
	 *
	 * The free build has no importer: the filter returns null and nothing happens.
	 * The Pro build creates them (new products as private, new variations of
	 * existing products) when the supplier has the option on.
	 *
	 * @param float $start_time Run start (microtime).
	 * @return void
	 */
	private function import_missing_offers( $start_time ) {
		if ( $this->slot < 1 || empty( $this->not_found_skus ) ) {
			return;
		}

		$prefix_len = strlen( (string) $this->sku_prefix );
		$skus       = array();

		foreach ( $this->not_found_skus as $sku ) {
			$skus[] = $prefix_len ? (string) substr( $sku, $prefix_len ) : $sku;
		}

		/**
		 * Import the offers of a supplier feed that are not on the site.
		 *
		 * @param array|null $result Null when nothing imports them; otherwise counts:
		 *                           products, variations, left (offers waiting for later runs).
		 * @param array      $args {
		 *     @type int      $slot       Supplier slot.
		 *     @type string   $xml_url    Feed URL.
		 *     @type string   $sku_prefix SKU prefix.
		 *     @type string[] $skus       Feed offer ids (without prefix) that matched no product.
		 *     @type float    $deadline   Unix time to stop by (0 = no limit).
		 * }
		 */
		$result = apply_filters(
			'f2000cs_import_missing_offers',
			null,
			array(
				'slot'       => $this->slot,
				'xml_url'    => $this->xml_url,
				'sku_prefix' => (string) $this->sku_prefix,
				'skus'       => $skus,
				'deadline'   => $this->max_execution_time > 0 ? $start_time + $this->max_execution_time - 20 : 0,
			)
		);

		if ( ! is_array( $result ) ) {
			return;
		}

		$this->report['new_products']   = (int) ( $result['products'] ?? 0 );
		$this->report['new_variations'] = (int) ( $result['variations'] ?? 0 );
		$this->report['new_left']       = (int) ( $result['left'] ?? 0 );

		$this->send_telegram_message(
			sprintf(
				'Нові з вигрузки: товарів %d (приватні), варіацій %d, у черзі ще %d',
				$this->report['new_products'],
				$this->report['new_variations'],
				$this->report['new_left']
			)
		);
	}

	/**
	 * Record a problem for the short report; the worse level wins.
	 *
	 * @param string $level 'warn' or 'error'.
	 * @param string $note  What happened (plain text).
	 * @return void
	 */
	private function report_problem( $level, $note ) {
		$rank = array(
			'ok'    => 0,
			'warn'  => 1,
			'error' => 2,
		);

		if ( $rank[ $level ] >= $rank[ $this->report['level'] ?? 'ok' ] ) {
			$this->report['level'] = $level;
		}

		$this->report['note'] = trim( ( $this->report['note'] ?? '' ) . ' ' . $note );
		$this->send_telegram_message( $note );
	}

	/**
	 * Send the short report of this run (always, also without «Розширений дебаг»).
	 *
	 * @param float $seconds Run time.
	 * @return void
	 */
	private function send_report( $seconds ) {
		if ( empty( $this->telegram_token_id ) || empty( array_filter( $this->telegram_user_ids ) ) ) {
			return;
		}

		$this->report['seconds'] = $seconds;
		f2000cs_send_telegram_notification( f2000cs_telegram_stock_report( $this->report ), 2, true );
	}

	/**
	 * Option that remembers when this feed became unreachable.
	 *
	 * @return string
	 */
	private function outage_option() {
		return 'f2000cs_feed_down_' . md5( (string) $this->xml_url );
	}

	/**
	 * React to a feed that could not be downloaded.
	 *
	 * The first failure only starts the outage clock; the supplier's products are taken
	 * off sale once the feed has stayed unreachable for the grace period.
	 *
	 * @return void
	 */
	private function handle_unreachable_feed() {
		$option = $this->outage_option();
		$since  = (int) get_option( $option, 0 );

		if ( $since <= 0 ) {
			$since = time();
			update_option( $option, $since, false );
		}

		/**
		 * How long a supplier feed may stay unreachable before its products are taken off sale.
		 *
		 * @param int    $grace   Seconds. Default 12 hours.
		 * @param string $xml_url Feed URL.
		 */
		$grace = (int) apply_filters( 'f2000cs_unreachable_feed_grace', 12 * HOUR_IN_SECONDS, $this->xml_url );
		$down  = time() - $since;

		f2000cs_log( 'Feed unreachable for ' . $down . 's: ' . $this->feed_error, 'error' );

		if ( $down < $grace ) {
			$this->report_problem(
				'warn',
				'Вигрузка недоступна (' . $this->feed_error . '). Наявність не змінено; якщо вигрузка не з\'явиться до '
				. wp_date( 'd.m H:i', $since + $grace ) . ', товари постачальника буде знято з продажу.'
			);
			return;
		}

		$this->report_problem( 'error', 'Вигрузка недоступна понад ' . round( $grace / HOUR_IN_SECONDS, 1 ) . ' год: ' . $this->feed_error );
		$this->mark_all_out_of_stock();
	}

	/**
	 * Set maximum execution time for long-running process
	 */
	private function set_max_execution_time() {
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- Needed to avoid timeouts on large catalogs; failure is non-fatal.
			@set_time_limit( 600 );
		}

		// Re-read the limit after raising it: the constructor value may still
		// reflect the hosting default (often 30s), which would abort batch
		// processing long before the raised limit.
		$current_limit = ini_get( 'max_execution_time' );
		if ( false === $current_limit ) {
			$current_limit = 0;
		}
		$this->max_execution_time = ( (int) $current_limit > 0 ) ? (int) $current_limit - 5 : 0;
	}

	/**
	 * Increase memory limit if possible
	 */
	private function increase_memory_limit() {
		$current_limit = $this->get_memory_limit_in_bytes();
		// A full pass over a ~10k-offer feed peaks around 340 MB, so 256M was not enough.
		$target = (int) apply_filters( 'f2000cs_memory_limit_mb', 1024 );

		if ( $target > 0 && $current_limit < $target * 1024 * 1024 ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.IniSet.memory_limit_Disallowed, WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort memory bump for large imports; failure is non-fatal.
			@ini_set( 'memory_limit', $target . 'M' );
		}
	}

	/**
	 * Clean up resources after processing
	 */
	private function cleanup_resources() {
		f2000cs_flush_transients_cache();
		f2000cs_flush_runtime_cache();
		gc_collect_cycles();

		f2000cs_cleanup_wc_transients();
	}

	/**
	 * Parse <currencies> section from YML XML.
	 *
	 * @return array<string,float> Currency ID => rate mapping.
	 */
	private function load_currencies_from_xml(): array {
		$currencies = array();
		$reader     = new XMLReader();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An unreadable file returns an empty map; the offer pass reports the error.
		if ( '' === $this->feed_source || ! @$reader->open( $this->feed_source, null, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET ) ) {
			return $currencies;
		}

		try {
			while ( @$reader->read() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A broken tail is reported by the offer pass.
				if ( XMLReader::ELEMENT !== $reader->nodeType ) {
					continue;
				}

				// <currencies> comes before <offers>: no need to read the whole feed twice.
				if ( 'offer' === $reader->name ) {
					break;
				}

				if ( 'currency' === $reader->name ) {
					$currency_id = $reader->getAttribute( 'id' );
					$rate        = $reader->getAttribute( 'rate' );
					if ( $currency_id && is_numeric( $rate ) && (float) $rate > 0 ) {
						$currencies[ $currency_id ] = (float) $rate;
					}
				}
			}
		} finally {
			$reader->close();
		}

		return $currencies;
	}

	/**
	 * Make the feed available as a local file.
	 *
	 * A readable local path is used as is. Anything else is downloaded in full to a
	 * temp file (streamed to disk, so a 150 MB feed does not sit in memory). A failed
	 * or cut download marks the feed unreachable.
	 *
	 * @return bool Whether a local file is ready.
	 */
	private function prepare_feed_source() {
		$this->feed_source = '';
		$this->feed_temp   = '';

		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', (string) $this->xml_url ) && is_readable( $this->xml_url ) ) {
			$this->feed_source = (string) $this->xml_url;

			return true;
		}

		$temp = wp_tempnam( 'f2000cs_' );

		/**
		 * Seconds allowed for downloading a supplier feed.
		 *
		 * @param int    $timeout Timeout.
		 * @param string $url     Feed URL.
		 */
		$timeout  = (int) apply_filters( 'f2000cs_feed_download_timeout', 600, $this->xml_url );
		$response = wp_remote_get(
			$this->xml_url,
			array(
				'timeout'     => max( 30, $timeout ),
				'httpversion' => '1.1',
				'sslverify'   => f2000cs_ssl_verify_enabled(),
				'stream'      => true,
				'filename'    => $temp,
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || 200 !== $code || ! file_exists( $temp ) || 0 === (int) filesize( $temp ) ) {
			if ( file_exists( $temp ) ) {
				wp_delete_file( $temp );
			}

			$reason = is_wp_error( $response ) ? $response->get_error_message() : ( 200 !== $code ? 'HTTP ' . $code : 'порожня відповідь' );

			$this->feed_unreachable = true;
			$this->feed_error       = 'не вдалося завантажити вигрузку (' . $reason . ')';
			$this->send_telegram_message( 'Помилка завантаження XML: ' . $reason );

			return false;
		}

		// A connection that closes early can leave a cut file behind an HTTP 200 (non-cURL
		// transports do not report it). Compare with Content-Length when the body was not
		// compressed in transit, and treat a short file as an unreachable feed, not a broken one.
		$expected = (int) wp_remote_retrieve_header( $response, 'content-length' );
		$encoding = (string) wp_remote_retrieve_header( $response, 'content-encoding' );
		$received = (int) filesize( $temp );

		if ( $expected > 0 && ( '' === $encoding || 'identity' === strtolower( $encoding ) ) && $received < $expected ) {
			wp_delete_file( $temp );

			$reason = sprintf( 'обірване завантаження: %d з %d байт', $received, $expected );

			$this->feed_unreachable = true;
			$this->feed_error       = 'не вдалося завантажити вигрузку (' . $reason . ')';
			$this->send_telegram_message( 'Помилка завантаження XML: ' . $reason );

			return false;
		}

		$this->feed_source = $temp;
		$this->feed_temp   = $temp;

		return true;
	}

	/**
	 * Extract stock and price data from XML file.
	 *
	 * @return array Array of [sku => ['stock_status' => status, 'price' => price, 'old_price' => old_price]]
	 */
	private function extract_data_from_xml() {
		$updates                = array();
		$reader                 = null;
		$this->feed_error       = '';
		$this->feed_unreachable = false;
		$previous_errors        = libxml_use_internal_errors( true );
		libxml_clear_errors();

		// Called on its own (tests, tools): get the local copy first.
		if ( '' === $this->feed_source && ! $this->prepare_feed_source() ) {
			libxml_use_internal_errors( $previous_errors );

			return $updates;
		}

		try {
			$reader = new XMLReader();

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is reported below as an invalid feed.
			if ( ! @$reader->open( $this->feed_source, null, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET ) ) {
				throw new Exception( 'вигрузка не відкривається як XML' );
			}

			if ( ! $reader->read() ) {
				throw new Exception( 'вигрузка порожня або не читається як XML' );
			}

			$dom = new \DOMDocument();

			while ( $reader->read() ) {
				if ( XMLReader::ELEMENT === $reader->nodeType && 'offer' === $reader->localName ) {
					$sku            = (string) $reader->getAttribute( 'id' );
					$available_attr = $reader->getAttribute( 'available' );
					$group_id       = (string) $reader->getAttribute( 'group_id' );

					// Get the offer node as SimpleXML to extract pricing
					$offer_xml = f2000cs_reader_element( $reader, $dom );
					if ( null === $offer_xml ) {
						continue;
					}

					$price     = isset( $offer_xml->price ) ? f2000cs_parse_price( (string) $offer_xml->price ) : 0;
					$old_price = isset( $offer_xml->oldprice ) ? f2000cs_parse_price( (string) $offer_xml->oldprice ) : 0;

					// Apply currency conversion.
					$store_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'UAH';
					if ( isset( $offer_xml->currencyId ) ) {
						$offer_currency = (string) $offer_xml->currencyId;
						if ( $offer_currency !== $store_currency && isset( $this->currencies[ $offer_currency ] ) ) {
							$rate = $this->currencies[ $offer_currency ];
							if ( $rate > 0 && $rate !== 1.0 ) {
								$price     = round( $price * $rate, 2 );
								$old_price = round( $old_price * $rate, 2 );
							}
						}
					}

					// «Do not load supplier discounts»: the old price becomes the regular
					// one and no sale is written. Only a valid current price is collapsed,
					// so a malformed offer without <price> is left for the update to skip.
					if ( $this->ignore_discounts && $price > 0 && $old_price > $price ) {
						$price     = $old_price;
						$old_price = 0;
					}

					if ( ! $this->skip_price_updates && $this->price_adjust_value > 0 ) {
						list( $price, $old_price ) = f2000cs_adjust_price_pair( $price, $old_price, $this->price_adjust_rule() );
					}

					$vendor_code = isset( $offer_xml->vendorCode ) ? (string) $offer_xml->vendorCode : '';

					$feed_quantity = f2000cs_parse_offer_quantity( $offer_xml );
					$quantity      = $this->update_stock_qty ? $feed_quantity : null;

					// getAttribute() returns null when the attribute is absent.
					$stock_status = f2000cs_resolve_stock_status( $available_attr, $feed_quantity );

					if ( ! empty( $sku ) ) {
						$updates[ $this->sku_prefix . $sku ] = array(
							'stock_status' => $stock_status,
							'price'        => $price,
							'old_price'    => $old_price,
							'group_id'     => $group_id,
							'vendor_code'  => $vendor_code,
							'quantity'     => $quantity,
						);
					}
				}

				if ( count( $updates ) % 500 === 0 ) {
					gc_collect_cycles();
				}
			}

			// A truncated or malformed document ends read() early with a fatal libxml error.
			foreach ( libxml_get_errors() as $error ) {
				if ( LIBXML_ERR_FATAL === $error->level ) {
					throw new Exception( 'некоректний XML: ' . trim( $error->message ) );
				}
			}
		} catch ( Exception $e ) {
			$this->feed_error = $e->getMessage();
			$this->send_telegram_message( 'Помилка читання XML: ' . $e->getMessage() );
			$updates = array();
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_errors );

			if ( $reader !== null ) {
				$reader->close();
			}
		}

		return $updates;
	}

	/**
	 * Apply the configured price adjustment (margin, markup or fixed amount) to one price.
	 *
	 * @param float $price Original price from XML.
	 * @return float Adjusted price.
	 */
	private function apply_price_adjustment( $price ) {
		if ( $price <= 0 || $this->price_adjust_value <= 0 ) {
			return $price;
		}

		return f2000cs_adjust_price( $price, $this->price_adjust_rule() );
	}

	/**
	 * The supplier's price adjustment rule in the form f2000cs_adjust_price() takes.
	 *
	 * @return array{type: string, direction: string, value: float, max_share: float}
	 */
	private function price_adjust_rule() {
		return array(
			'type'      => $this->price_adjust_type,
			'direction' => $this->price_adjust_direction,
			'value'     => $this->price_adjust_value,
			'max_share' => $this->price_adjust_max_share,
		);
	}

	/**
	 * Option that holds the run lock of this feed (the time the run started).
	 *
	 * @return string
	 */
	private function run_lock_option() {
		return 'f2000cs_sync_lock_' . md5( (string) $this->xml_url );
	}

	/**
	 * Read the run lock straight from the database (not from this process' option cache),
	 * so a lock written by another process is seen.
	 *
	 * @return string Stored value («started|token»), '' when there is none.
	 */
	private function read_run_lock() {
		$option = $this->run_lock_option();

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $option, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}

		$value = get_option( $option, '' );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Take the run lock of this feed. A lock older than the TTL belongs to a run that died
	 * (fatal error, killed process) and is taken over.
	 *
	 * The lock holds «start time|token». add_option() is not atomic (two runs can both
	 * write it), so after writing, the lock is read back: only the run whose token is
	 * stored goes on.
	 *
	 * @return bool Whether this run may go on.
	 */
	private function acquire_run_lock() {
		$option = $this->run_lock_option();
		$token  = time() . '|' . wp_generate_password( 12, false );

		if ( ! add_option( $option, $token, '', false ) ) {
			/**
			 * Seconds after which a run lock is considered stale.
			 *
			 * @param int    $ttl Lock lifetime.
			 * @param string $url Feed URL.
			 */
			$ttl     = (int) apply_filters( 'f2000cs_sync_lock_ttl', 2 * HOUR_IN_SECONDS, $this->xml_url );
			$started = (int) $this->read_run_lock();

			if ( $started > 0 && time() - $started < $ttl ) {
				return false;
			}

			delete_option( $option );

			if ( ! add_option( $option, $token, '', false ) ) {
				return false;
			}
		}

		if ( $this->read_run_lock() !== $token ) {
			return false;
		}

		$this->run_lock_token = $token;

		return true;
	}

	/**
	 * Release the run lock, but only if it is still this run's: a run that outlived the
	 * TTL must not delete the lock a newer run has taken over.
	 *
	 * @return void
	 */
	private function release_run_lock() {
		if ( '' === $this->run_lock_token ) {
			return;
		}

		if ( $this->read_run_lock() === $this->run_lock_token ) {
			delete_option( $this->run_lock_option() );
		}

		$this->run_lock_token = '';
	}

	/**
	 * Whether the feed lists far fewer offers than the previous run (and remember this count).
	 *
	 * The count is stored every time, so a shrink that is real is trusted on the next run.
	 * Feeds under 50 offers are not guarded.
	 *
	 * @param int $count Offers in this run.
	 * @return bool
	 */
	private function feed_shrank( $count ) {
		$option   = 'f2000cs_feed_count_' . md5( (string) $this->xml_url );
		$previous = (int) get_option( $option, 0 );

		$this->previous_offer_count = $previous;
		update_option( $option, (int) $count, false );

		/**
		 * Share of the previous offer count below which a feed counts as cut (0.5 = half).
		 *
		 * @param float  $ratio Ratio, 0 turns the guard off.
		 * @param string $url   Feed URL.
		 */
		$ratio = (float) apply_filters( 'f2000cs_feed_shrink_ratio', 0.5, $this->xml_url );

		return $ratio > 0 && $previous >= 50 && $count < $previous * $ratio;
	}

	/**
	 * An offer that matches a variable parent is never written onto it.
	 *
	 * Older imports gave the parent the SKU of an offer whose id equals its group_id
	 * (BM sends such a group offer with available="false"). The parent follows its
	 * variations instead: its stock status is recounted from them, which also repairs a
	 * status left stale by an earlier version. Written directly, without a product save
	 * (a save fires indexing APIs and feed plugins for ~1 400 parents on every run).
	 *
	 * @param \WC_Product $product Matched product.
	 * @return bool Whether the product is a variable parent (and was skipped).
	 */
	private function skip_variable_parent( $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return false;
		}

		$parent_id = (int) $product->get_id();
		$expected  = $this->parent_stock_status_from_children( $parent_id );

		if ( (string) get_post_meta( $parent_id, '_stock_status', true ) !== $expected ) {
			f2000cs_update_stock_status( $parent_id, $expected );
			wc_delete_product_transients( $parent_id );
		}

		return true;
	}

	/**
	 * Stock status a variable parent should have: in stock when any variation is.
	 *
	 * @param int $parent_id Parent ID.
	 * @return string
	 */
	private function parent_stock_status_from_children( $parent_id ) {
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

		return $instock_count > 0 ? 'instock' : 'outofstock';
	}

	private function sync_parent_stock_status( $parent_id ) {
		f2000cs_update_stock_status( $parent_id, $this->parent_stock_status_from_children( $parent_id ) );
		wc_delete_product_transients( $parent_id );
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
		$updated_qty          = 0;
		$not_found            = 0;
		$skipped_unchanged    = 0; // Count of products where nothing changed.
		$republished          = 0; // Products the plugin drafted earlier that are back in the feed.

		// A run cut short by the time limit continues where it stopped: the feed is
		// rotated so the unprocessed tail goes first, then the head again.
		$resume_key = 'f2000cs_resume_' . md5( (string) $this->xml_url );
		$start      = (int) get_option( $resume_key, 0 );

		if ( $start > 0 && $start < $total ) {
			$updates = array_slice( $updates, $start, null, true ) + array_slice( $updates, 0, $start, true );
			$this->send_telegram_message( "Продовження з позиції $start з $total" );
		} else {
			$start = 0;
		}

		$batches     = array_chunk( $updates, $this->batch_size, true );
		$batch_count = count( $batches );
		$finished    = true;

		$this->send_telegram_message( "Обробка: $total товарів, пакетів: $batch_count" );

		$process_start_time = microtime( true );

		foreach ( $batches as $batch_index => $batch ) {
			// Add timing checks to avoid timeouts.
			if ( connection_aborted() ) {
				$this->report_problem( 'warn', "З'єднання перервано: оброблено $processed з $total." );
				$finished = false;
				break;
			}

			if ( $this->max_execution_time > 0 && ( microtime( true ) - $process_start_time ) > $this->max_execution_time ) {
				$this->report_problem( 'warn', "Не вистачило часу виконання: оброблено $processed з $total, решта — у наступному прогоні." );
				$finished = false;
				break;
			}

			// Position in the original feed order of the first offer not yet done.
			update_option( $resume_key, ( $start + $batch_index * $this->batch_size ) % $total, false );

			$skus        = array_keys( $batch );
			$product_ids = $this->get_product_ids_by_skus( $skus );

			foreach ( $batch as $sku => $product_data ) {
				$product_id = $product_ids[ $sku ] ?? false;

				if ( ! $product_id ) {
					++$not_found;
					$this->not_found_skus[] = (string) $sku;
					continue;
				}

				try {
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						++$not_found;
						continue;
					}

					if ( $this->skip_variable_parent( $product ) ) {
						++$skipped_unchanged;
						continue;
					}

					$changes_made = false;

					// A product this plugin drafted because it vanished from the feed comes back
					// as soon as the feed lists it again (before any stock/price write below).
					if ( $this->maybe_republish_product( $product ) ) {
						++$republished;
						$changes_made = true;
					}

					// Quantity first, then the stock status from the same offer.
					if ( $this->update_stock_qty && isset( $product_data['quantity'] ) && null !== $product_data['quantity'] ) {
						$qty_changed = $this->update_product_quantity( $product, (int) $product_data['quantity'] );
						if ( $qty_changed ) {
							++$updated_qty;
							$changes_made = true;
						}
					}

					// Without Pro the stored quantity is not refreshed; writing only a status for a
					// product with managed stock would contradict its quantity.
					$keep_status          = $this->keep_managed_status && $product->managing_stock();
					$stock_status_changed = $keep_status ? false : $this->update_product_stock( $product, $product_data['stock_status'] );
					if ( $stock_status_changed ) {
						$product_data['stock_status'] === 'instock' ? ++$updated_in_stock : ++$updated_out_of_stock;
						$changes_made = true;
					}

					if ( ! $this->skip_price_updates && $product_data['price'] > 0 ) {
						$price_changed = $this->update_product_price( $product, $product_data['price'], $product_data['old_price'] );
						if ( $price_changed ) {
							++$updated_price;
							$changes_made = true;
						}
					}

					if ( ! empty( $product_data['vendor_code'] ) ) {
						$current_vendor = get_post_meta( $product_id, 'f2000cs-updater-vendor', true );
						if ( empty( $current_vendor ) ) {
							update_post_meta( $product_id, 'f2000cs-updater-vendor', $product_data['vendor_code'] );
						}
					}

					if ( ! $changes_made ) {
						++$skipped_unchanged;
					}
				} catch ( Exception $e ) {
					// Cap the log volume: a systematic failure must not flood
					// the error log with one line per product.
					static $logged_errors = 0;
					++$logged_errors;

					if ( $logged_errors <= 10 ) {
						f2000cs_log( sprintf( 'Product update error (SKU %s): %s', $sku, $e->getMessage() ), 'error' );
					} elseif ( 11 === $logged_errors ) {
						f2000cs_log( 'Product update error: further per-product errors suppressed for this run.', 'error' );
					}
				}

				++$processed;
			}

			$this->sync_price_changed_parents();

			if ( $batch_index % 10 === 0 ) {
				f2000cs_flush_transients_cache();
				f2000cs_flush_runtime_cache();
				gc_collect_cycles();
			}
		}

		if ( $finished ) {
			delete_option( $resume_key );
		}

		$this->send_telegram_message(
			sprintf(
				"Результати оновлення товарів:\n" .
				"---------------------------\n" .
				"• Всього товарів в XML: %d\n" .
				"• Оброблено товарів: %d\n" .
				"• Оновлено статус \"В наявності\": %d\n" .
				"• Оновлено статус \"Немає в наявності\": %d\n" .
				"• Оновлено цін: %d\n" .
				"• Оновлено кількість: %d\n" .
				"• Пропущено (без змін): %d\n" .
				"• Не знайдено товарів: %d\n" .
				'• Повернуто в публікацію: %d',
				$total,
				$processed,
				$updated_in_stock,
				$updated_out_of_stock,
				$updated_price,
				$updated_qty,
				$skipped_unchanged,
				$not_found,
				$republished
			)
		);

		$this->report['in_stock']     = $updated_in_stock;
		$this->report['out_of_stock'] = $updated_out_of_stock;
		$this->report['prices']       = $updated_price;
		$this->report['quantities']   = $updated_qty;
		$this->report['not_found']    = $not_found;
		$this->report['republished']  = $republished;

		if ( ( $updated_in_stock + $updated_out_of_stock ) > 0 || $updated_price > 0 || $updated_qty > 0 ) {
			f2000cs_log(
				sprintf(
					'Update completed. Total: %d, Stock changed: %d, Price changed: %d, Qty changed: %d, Unchanged: %d, Not found: %d',
					$total,
					$updated_in_stock + $updated_out_of_stock,
					$updated_price,
					$updated_qty,
					$skipped_unchanged,
					$not_found
				),
				'info'
			);
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			\rocket_clean_domain();
		}
	}

	/**
	 * Find the product IDs by SKUs using optimized query.
	 *
	 * @param array $skus SKUs of the products.
	 * @return array Associative array of SKU and Product ID.
	 */
	private function get_product_ids_by_skus( $skus ) {
		return f2000cs_get_product_ids_by_skus( (array) $skus );
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
			$product_id   = $product->get_id();
			$product_type = $product->get_type();

			// HPOS-compatible: write both postmeta and the lookup table.
			f2000cs_update_stock_status( $product_id, $stock_status );

			wc_delete_product_transients( $product_id );

			if ( $product_type === 'variation' ) {
				$parent_id = $product->get_parent_id();
				if ( $parent_id ) {
					$this->sync_parent_stock_status( $parent_id );
				}
			}

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
	 * Update managed stock quantity from XML.
	 *
	 * @param \WC_Product $product  Product object.
	 * @param int         $quantity Stock quantity.
	 * @return bool Whether quantity was changed.
	 */
	private function update_product_quantity( $product, $quantity ) {
		$quantity = max( 0, (int) $quantity );

		$manage_stock = $product->get_manage_stock();
		$current_qty  = $product->get_stock_quantity();

		if ( $manage_stock && null !== $current_qty && (int) $current_qty === $quantity ) {
			return false;
		}

		// Written straight to the stock meta, like the status: WC_Product::save() would fire
		// every product hook on the site (indexing APIs, Merchant Center sync, feed plugins)
		// for thousands of products per run.
		f2000cs_update_stock_quantity( $product->get_id(), $quantity );
		wc_delete_product_transients( $product->get_id() );

		return true;
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
			'%s: Процес завершено за %.1f сек | Використано пам\'яті: %.1f MB | Пікове використання: %.1f MB',
			$message,
			$execution_time,
			$memory_usage,
			$peak_memory
		);

		$this->send_telegram_message( $log_message );
	}

	/**
	 * Send a step-by-step service message (only with «Розширений дебаг»).
	 *
	 * @param string $message Message to send.
	 * @return void
	 */
	private function send_telegram_message( $message ) {
		if ( empty( $this->telegram_token_id ) || empty( $this->telegram_user_ids ) ) {
			return;
		}

		// Step-by-step details go out only with «Розширений дебаг»; every run ends with
		// one short report (send_report()) either way.
		f2000cs_telegram_debug( $message );
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

		$price = number_format( (float) $price, 2, '.', '' );

		if ( $old_price > 0 && $old_price > $price ) {
			$old_price = number_format( (float) $old_price, 2, '.', '' );

			$current_regular_price = $product->get_regular_price();
			$current_sale_price    = $product->get_sale_price();

			if ( $current_regular_price !== $old_price || $current_sale_price !== $price ) {
				update_post_meta( $product_id, '_regular_price', $old_price );
				update_post_meta( $product_id, '_sale_price', $price );
				update_post_meta( $product_id, '_price', $price );
				$changed = true;
			}
		} else {
			$current_regular_price = $product->get_regular_price();

			// A sale left from an earlier run ends too: suppliers end a promotion by
			// dropping <oldprice> and restoring the price, often to the same regular price.
			// Only a sale the owner scheduled with an end date ahead is kept.
			$has_sale  = '' !== (string) $product->get_sale_price( 'edit' );
			$scheduled = $has_sale && f2000cs_has_scheduled_sale( $product_id );

			if ( $scheduled ) {
				if ( $current_regular_price !== $price ) {
					update_post_meta( $product_id, '_regular_price', $price );
					$changed = true;
				}
			} elseif ( $current_regular_price !== $price || $has_sale ) {
				f2000cs_end_sale( $product_id, $price );
				$changed = true;
			}
		}

		// Note: We do NOT automatically update all variations for variable products
		// Each variation should be updated individually based on its own SKU in the XML

		if ( $changed ) {
			wc_delete_product_transients( $product_id );
			f2000cs_sync_price_lookup( $product_id );

			// The parent's price range, «sale» badge and lookup row follow its variations.
			if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
				$this->price_changed_parents[ $product->get_parent_id() ] = true;
			}
		}

		return $changed;
	}

	/**
	 * Recalculate prices of the variable parents whose variations changed price.
	 *
	 * @return void
	 */
	private function sync_price_changed_parents() {
		if ( ! class_exists( 'WC_Product_Variable' ) ) {
			$this->price_changed_parents = array();
			return;
		}

		foreach ( array_keys( $this->price_changed_parents ) as $parent_id ) {
			\WC_Product_Variable::sync( $parent_id );
			wc_delete_product_transients( $parent_id );
		}

		$this->price_changed_parents = array();
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live SKU list needed during sync; cache would be stale.
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
				$wpdb->esc_like( $this->sku_prefix ) . '%'
			)
		);

		if ( empty( $db_skus ) ) {
			return; // No products with this prefix found
		}

		$xml_sku_keys = array_keys( $xml_skus );

		$missing_skus = array_diff( $db_skus, $xml_sku_keys );

		if ( empty( $missing_skus ) ) {
			$this->send_telegram_message( 'Усі товари з сайту є у вигрузці' );
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $missing_skus ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated %s list; values passed to prepare().
		$missing_variation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_type = 'product_variation'
					  AND p.post_status IN ('publish','draft','private')
					  AND pm.meta_key = '_sku'
					  AND pm.meta_value IN ($placeholders)",
				$missing_skus
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		foreach ( $missing_variation_ids as $vid ) {
			f2000cs_update_stock_status( $vid, 'outofstock' );
			wc_delete_product_transients( $vid );

			$parent_id = (int) get_post_field( 'post_parent', $vid );
			if ( $parent_id ) {
				$this->sync_parent_stock_status( $parent_id );
			}
		}

		$variations_out_count = count( $missing_variation_ids );
		if ( $variations_out_count > 0 ) {
			$this->send_telegram_message(
				"🔻 Варіацій переведено в 'Немає в наявності': {$variations_out_count}"
			);
		}

		$missing_products = $this->get_missing_products_details( $missing_skus );

		$this->send_missing_products_report( $missing_products, count( $missing_skus ) );
	}

	/**
	 * Put every product and variation of this supplier (by SKU prefix) out of stock.
	 *
	 * Used when the feed failed validation. The next valid feed restores the
	 * statuses, since the regular update sets them from the XML. Without a SKU
	 * prefix nothing is touched: it would hit the whole shop.
	 *
	 * @return int Number of products and variations set out of stock.
	 */
	private function mark_all_out_of_stock() {
		global $wpdb;

		if ( empty( $this->sku_prefix ) ) {
			$this->report_problem( 'warn', 'SKU-префікс не задано — товари не змінювалися.' );
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live list needed during sync; cache would be stale.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_type AS type, p.post_parent AS parent
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status IN ('publish', 'draft', 'private')
				AND pm.meta_key = '_sku'
				AND pm.meta_value LIKE %s",
				$wpdb->esc_like( $this->sku_prefix ) . '%'
			)
		);

		$count = 0;

		foreach ( (array) $rows as $row ) {
			// Same rule as a valid run: while quantities are frozen, a product with managed
			// stock follows its quantity — later runs would never bring its status back.
			if ( $this->keep_managed_status ) {
				$product = wc_get_product( (int) $row->id );
				if ( $product && $product->managing_stock() ) {
					continue;
				}
			}

			f2000cs_update_stock_status( (int) $row->id, 'outofstock' );
			wc_delete_product_transients( (int) $row->id );
			++$count;
		}

		// Variable parents without their own prefixed SKU follow their variations.
		$parents = array();

		foreach ( (array) $rows as $row ) {
			if ( 'product_variation' === $row->type && (int) $row->parent > 0 ) {
				$parents[ (int) $row->parent ] = true;
			}
		}

		foreach ( array_keys( $parents ) as $parent_id ) {
			$this->sync_parent_stock_status( $parent_id );
		}

		$this->report['off_sale'] = $count;
		$this->send_telegram_message( "Переведено в «Немає в наявності»: {$count} товарів/варіацій" );

		return $count;
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated %s list; values passed to prepare().
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
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql is already prepared above; live data required.
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
		$this->report['missing'] = (int) $total_missing;

		$message  = "Товари, яких немає у вигрузці:\n\n";
		$message .= "📊 Усього: $total_missing\n";
		$message .= '🏬 Вигрузка: ' . f2000cs_telegram_feed_label( $this->xml_url ) . "\n\n";

		$products   = array();
		$variations = array();

		foreach ( $missing_products as $product ) {
			$product_obj = wc_get_product( $product->ID );
			if ( $product_obj && $product_obj->get_type() === 'variation' ) {
				$variations[] = $product;
			} else {
				$products[] = $product;
			}
		}

		if ( ! empty( $products ) ) {
			$message .= '📦 Основні товари (' . count( $products ) . "):\n";
			foreach ( array_slice( $products, 0, 20 ) as $product ) {
				$message .= "• {$product->post_title} (SKU: {$product->sku})\n";
			}
			if ( count( $products ) > 20 ) {
				$message .= '... та ще ' . ( count( $products ) - 20 ) . " товарів\n";
			}
			$message .= "\n";
		}

		if ( ! empty( $variations ) ) {
			$message .= '🔄 Варіації товарів (' . count( $variations ) . "):\n";
			foreach ( array_slice( $variations, 0, 20 ) as $variation ) {
				$message .= "• {$variation->post_title} (SKU: {$variation->sku})\n";
			}
			if ( count( $variations ) > 20 ) {
				$message .= '... та ще ' . ( count( $variations ) - 20 ) . " варіацій\n";
			}
			$message .= "\n";
		}

		$message .= '💡 Рекомендація: Перевірте, чи ці товари дійсно відсутні у постачальника, або це помилка у вигрузці.';

		$this->send_telegram_message( $message );

		$this->convert_missing_products_to_draft( $missing_products );
	}

	/**
	 * Publish a product again when it returns to the feed after the plugin drafted it.
	 *
	 * Only products carrying the AUTO_DRAFT_META flag are touched, so products an
	 * administrator drafted on purpose stay drafts.
	 *
	 * @param \WC_Product $product Product found in the feed.
	 * @return bool Whether the product was published again.
	 */
	private function maybe_republish_product( $product ) {
		if ( 'draft' !== $product->get_status() ) {
			return false;
		}

		$product_id = (int) $product->get_id();

		if ( ! get_post_meta( $product_id, self::AUTO_DRAFT_META, true ) ) {
			return false;
		}

		/**
		 * Whether products drafted by the plugin come back when the feed lists them again.
		 *
		 * @param bool        $republish  Default true.
		 * @param \WC_Product $product    Product.
		 */
		if ( ! apply_filters( 'f2000cs_republish_returned_products', true, $product ) ) {
			return false;
		}

		// Back to the status it had: a private product (e.g. imported for review) stays private.
		$status = 'private' === get_post_meta( $product_id, self::AUTO_DRAFT_FROM_META, true ) ? 'private' : 'publish';

		$result = wp_update_post(
			array(
				'ID'          => $product_id,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			f2000cs_log( sprintf( 'Failed to republish product #%d: %s', $product_id, $result->get_error_message() ), 'error' );
			return false;
		}

		delete_post_meta( $product_id, self::AUTO_DRAFT_META );
		delete_post_meta( $product_id, self::AUTO_DRAFT_FROM_META );
		wc_delete_product_transients( $product_id );

		return true;
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
				$previous_status = 'publish';

				// Skip variable products (parent) and variations entirely
				$product_obj = wc_get_product( $product->ID );
				if ( $product_obj ) {
					$type = $product_obj->get_type();
					if ( $type === 'variable' || $type === 'variation' ) {
						continue;
					}

					// Already a draft (drafted earlier, or by the admin): leave it alone and
					// do not re-count it on every run.
					if ( 'draft' === $product_obj->get_status() ) {
						continue;
					}

					$previous_status = (string) $product_obj->get_status();
				}

				$result = wp_update_post(
					array(
						'ID'          => $product->ID,
						'post_status' => 'draft',
					),
					true // Return WP_Error on failure
				);

				if ( ! is_wp_error( $result ) ) {
					++$converted_count;

					// Remember that the plugin (not the admin) drafted it, so it can be
					// published again when the product returns to the feed.
					update_post_meta( $product->ID, self::AUTO_DRAFT_META, time() );
					update_post_meta( $product->ID, self::AUTO_DRAFT_FROM_META, $previous_status );

					wc_delete_product_transients( $product->ID );
				} else {
					f2000cs_log(
						sprintf( 'Failed to draft missing product #%d: %s', (int) $product->ID, $result->get_error_message() ),
						'error'
					);
				}
			} catch ( Exception $e ) {
				f2000cs_log(
					sprintf( 'Exception drafting missing product #%d: %s', (int) $product->ID, $e->getMessage() ),
					'error'
				);
			}
		}

		if ( $converted_count > 0 ) {
			$this->report['drafted'] = $converted_count;
			$this->send_telegram_message( "Переведено в «Чернетку»: $converted_count товарів" );
		}
	}
}
