<?php
defined( 'ABSPATH' ) || exit;

/**
 * Send notification to Telegram with retry and error handling
 *
 * @param string $message Message to send
 * @param int    $retry_count Number of retries (default: 2)
 * @return bool Success status
 */
function f2000cs_send_telegram_notification( $message, $retry_count = 2 ) {
	$token    = get_option( 'f2000cs_telegram_token_id', '' );
	$user_ids = get_option( 'f2000cs_telegram_user_ids', '' );

	if ( empty( $token ) || empty( $user_ids ) ) {
		return false;
	}

	$user_ids_array = array_map( 'trim', explode( ',', $user_ids ) );
	$success        = true;

	foreach ( $user_ids_array as $user_id ) {
		if ( empty( $user_id ) ) {
			continue;
		}

		// Limit message length to prevent API errors
		if ( strlen( $message ) > 4000 ) {
			$message = substr( $message, 0, 3997 ) . '...';
		}

		$url  = "https://api.telegram.org/bot{$token}/sendMessage";
		$args = array(
			'body'    => array(
				'chat_id'    => $user_id,
				'text'       => $message,
				'parse_mode' => 'HTML',
			),
			'timeout' => 30,
		);

		$try_count       = 0;
		$request_success = false;

		while ( ! $request_success && $try_count <= $retry_count ) {
			$response = wp_remote_post( $url, $args );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$request_success = true;
				break;
			}

			++$try_count;

			if ( $try_count <= $retry_count ) {
				// Wait before retrying (exponential backoff)
				$wait_time = pow( 2, $try_count - 1 ) * 500000; // 0.5s, 1s, 2s...
				usleep( $wait_time );
			}
		}

		if ( ! $request_success ) {
			$success = false;
		}
	}

	return $success;
}

/**
 * Log plugin activity with additional context
 *
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 * @return void
 */
function f2000cs_log( $message, $level = 'info' ) {
	$debug_log = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	// Always persist errors so operators can see cron/stock failures without enabling WP_DEBUG.
	if ( ! $debug_log && 'error' !== $level ) {
		return;
	}

	$memory_usage      = round( memory_get_usage() / 1024 / 1024, 2 );
	$formatted_message = sprintf(
		'[%s] Factorial2000 Catalog Sync [%s] [Memory: %sMB]: %s',
		gmdate( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$memory_usage,
		$message
	);
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional plugin logging.
	error_log( $formatted_message );
}

/**
 * Parse the YML <offer available="..."> attribute into a WooCommerce stock status.
 *
 * YML allows: true, false, 1, 0, yes, no, y, n (case-insensitive).
 * Missing/empty attribute defaults to instock (common YML practice).
 *
 * @param string $available Raw available attribute value.
 * @return string 'instock' or 'outofstock'.
 */
function f2000cs_parse_available( $available ) {
	$available = trim( (string) $available );

	// Omitted attribute → treat as in stock.
	if ( '' === $available ) {
		return 'instock';
	}

	$lower  = strtolower( $available );
	$truthy = array( 'true', '1', 'yes', 'y' );
	$falsey = array( 'false', '0', 'no', 'n' );

	if ( in_array( $lower, $truthy, true ) ) {
		return 'instock';
	}

	if ( in_array( $lower, $falsey, true ) ) {
		return 'outofstock';
	}

	// Unknown non-empty value → out of stock (safe default).
	return 'outofstock';
}

/**
 * The element an XMLReader is positioned on, as SimpleXML.
 *
 * The node is expanded from the reader's own document instead of re-parsing
 * readOuterXML(): a fragment parsed on its own loses the DOCTYPE, so an entity
 * such as &nbsp; (common in shops.dtd feeds) fails it and leaves a fatal error
 * in the shared libxml buffer, where it reads as a broken feed.
 *
 * @param XMLReader   $reader Reader positioned on an element.
 * @param DOMDocument $dom    Document the expanded nodes belong to; reuse one per loop.
 * @return SimpleXMLElement|null Null when the element cannot be read.
 */
function f2000cs_reader_element( XMLReader $reader, DOMDocument $dom ) {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A malformed element returns false; the caller skips it.
	$node = @$reader->expand( $dom );
	if ( ! $node instanceof DOMElement ) {
		return null;
	}

	$element = simplexml_import_dom( $node );

	return $element instanceof SimpleXMLElement ? $element : null;
}

/**
 * Read the stock quantity of a YML offer.
 *
 * Supported tags (first non-empty wins): <quantity>, <stock_quantity>,
 * <qty_stock> (OneBox). Decimal notations such as "1.000" are read as 1.
 *
 * @param SimpleXMLElement $offer Offer element.
 * @return int|null Quantity, or null when the offer carries none.
 */
function f2000cs_parse_offer_quantity( $offer ) {
	foreach ( array( 'quantity', 'stock_quantity', 'qty_stock' ) as $tag ) {
		if ( ! isset( $offer->{$tag} ) ) {
			continue;
		}

		$raw = str_replace( ',', '.', trim( (string) $offer->{$tag} ) );

		if ( '' !== $raw && is_numeric( $raw ) ) {
			return max( 0, (int) floor( (float) $raw ) );
		}
	}

	return null;
}

/**
 * Resolve the WooCommerce stock status of an offer.
 *
 * An <offer available=""> attribute that is present is authoritative, even when
 * empty (OneBox writes available="" for unavailable items). Only when the
 * attribute is absent entirely do we fall back to the quantity (if any), and
 * finally to "instock" (common YML practice).
 *
 * @param string|null $available Raw attribute value, null when the attribute is absent.
 * @param int|null    $quantity  Parsed quantity, null when unknown.
 * @return string 'instock' or 'outofstock'.
 */
function f2000cs_resolve_stock_status( $available, $quantity = null ) {
	if ( null === $available ) {
		if ( null !== $quantity ) {
			return $quantity > 0 ? 'instock' : 'outofstock';
		}

		return 'instock';
	}

	if ( '' === trim( (string) $available ) ) {
		return 'outofstock';
	}

	return f2000cs_parse_available( $available );
}

/**
 * Safely parse a price string from XML into float.
 *
 * Handles:
 * - Plain: "1234.56"
 * - European: "1.234,56" / "99,99" / "1 500,00"
 * - US thousands: "1,234.56"
 * - Multi-dot thousands: "1.234.56"
 *
 * @param string $value Price string from XML.
 * @return float Parsed price.
 */
function f2000cs_parse_price( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0.0;
	}

	// NBSP / thin space / regular spaces as thousands separators.
	$value = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF", ' ' ), '', $value );
	// Drop currency symbols and other non-numeric junk (keep digits, separators, minus).
	$value = preg_replace( '/[^\d,.\-]/', '', $value );
	if ( null === $value || '' === $value || '-' === $value ) {
		return 0.0;
	}

	$last_dot   = strrpos( $value, '.' );
	$last_comma = strrpos( $value, ',' );

	if ( false !== $last_comma && false !== $last_dot ) {
		if ( $last_comma > $last_dot ) {
			// European: 1.234,56
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		} else {
			// US: 1,234.56
			$value = str_replace( ',', '', $value );
		}
	} elseif ( false !== $last_comma ) {
		// Only commas — last segment is decimal when 1–2 digits.
		$parts = explode( ',', $value );
		$frac  = (string) array_pop( $parts );
		if ( strlen( $frac ) <= 2 ) {
			$value = implode( '', $parts ) . '.' . $frac;
		} else {
			$value = implode( '', $parts ) . $frac;
		}
	} elseif ( false !== $last_dot ) {
		$parts = explode( '.', $value );
		if ( count( $parts ) > 2 ) {
			// Multi-dot: 1.234.56 → last segment decimal when 1–2 digits.
			$frac = (string) array_pop( $parts );
			if ( strlen( $frac ) <= 2 ) {
				$value = implode( '', $parts ) . '.' . $frac;
			} else {
				$value = implode( '', $parts ) . $frac;
			}
		}
	}

	return (float) $value;
}

/**
 * Whether SSL/TLS certificates must be verified on outbound requests.
 *
 * Verification is ON by default. Sites whose supplier servers use self-signed
 * or expired certificates can either enable the «Слабкі SSL-сертифікати»
 * setting on the update page or override this via:
 *     add_filter( 'f2000cs_ssl_verify', '__return_false' );
 *
 * @return bool
 */
function f2000cs_ssl_verify_enabled() {
	$default = '1' !== (string) get_option( 'f2000cs_allow_insecure_ssl', '0' );

	return (bool) apply_filters( 'f2000cs_ssl_verify', $default );
}

/**
 * Download a URL to a temporary file, with SSL verification enabled by default.
 *
 * Sites whose supplier servers use self-signed or expired certificates can
 * enable the «Weak SSL certificates» setting on the update page, or disable
 * verification in code with:
 *     add_filter( 'f2000cs_ssl_verify', '__return_false' );
 *
 * @param string $url     The URL to download.
 * @param int    $timeout Download timeout in seconds.
 * @return string|WP_Error Local temporary file path, or WP_Error on failure.
 */
function f2000cs_download_url( $url, $timeout = 300 ) {
	add_filter( 'http_request_args', 'f2000cs_disable_ssl_verify', 99, 1 );
	try {
		return download_url( $url, $timeout );
	} finally {
		remove_filter( 'http_request_args', 'f2000cs_disable_ssl_verify', 99 );
	}
}

/**
 * Filter callback: control SSL verification on HTTP requests.
 *
 * Verification is ON by default; the f2000cs_ssl_verify filter allows opting
 * out for suppliers that use self-signed or expired certificates.
 *
 * @param array $args Request arguments.
 * @return array Modified arguments.
 */
function f2000cs_disable_ssl_verify( $args ) {
	$args['sslverify'] = f2000cs_ssl_verify_enabled();
	return $args;
}

/**
 * Get plugin settings URL

(Showing lines 90-133 of 837. Use offset=134 to continue.)
 *
 * @return string Admin URL for plugin settings
 */
function f2000cs_get_settings_url() {
	return admin_url( 'admin.php?page=f2000cs-update' );
}

/**
 * Saved price adjustment rule (type, direction, value) of a supplier slot.
 *
 * @param int $index Supplier slot index.
 * @return array{type: string, direction: string, value: float}
 */
function f2000cs_get_price_adjust_settings( $index ) {
	$index = absint( $index );

	return array(
		'type'      => (string) get_option( 'f2000cs_price_adjust_type_' . $index, 'markup' ),
		'direction' => (string) get_option( 'f2000cs_price_adjust_direction_' . $index, 'add' ),
		'value'     => (float) get_option( 'f2000cs_price_adjust_value_' . $index, '0' ),
	);
}

/**
 * Apply a price adjustment (margin, markup or fixed amount) to a supplier price.
 *
 * Margin is the profit as a percentage of the final selling price:
 *     sell_price = cost / (1 - margin / 100)
 * Markup is a percentage applied on top of the supplier cost:
 *     sell_price = cost * (1 + markup / 100)
 * Fixed adds/subtracts a flat currency amount.
 *
 * @param float $price  Original price from XML.
 * @param array $adjust Rule: type (margin|markup|fixed), direction (add|subtract), value.
 * @return float Adjusted price, rounded to 2 decimals.
 */
function f2000cs_adjust_price( $price, $adjust ) {
	$price = (float) $price;
	$value = isset( $adjust['value'] ) ? (float) $adjust['value'] : 0.0;

	if ( $price <= 0 || $value <= 0 ) {
		return $price;
	}

	$sign = ( isset( $adjust['direction'] ) && 'subtract' === $adjust['direction'] ) ? -1 : 1;

	switch ( $adjust['type'] ?? 'markup' ) {
		case 'margin':
			// Margin can only ever increase the price and must stay below 100%.
			$margin = min( 99.99, $value );
			$price  = $price / ( 1 - ( $margin / 100 ) );
			break;

		case 'fixed':
			$price = $price + ( $sign * $value );
			break;

		case 'markup':
		default:
			$price = $price * ( 1 + ( $sign * $value / 100 ) );
			break;
	}

	return round( max( 0, $price ), 2 );
}

/**
 * Whether a stored checkbox option is on ('1', 'yes' or 'on').
 *
 * @param string $option Option name.
 * @return bool
 */
function f2000cs_option_is_on( $option ) {
	return in_array( get_option( $option, '0' ), array( '1', 'yes', 'on' ), true );
}

/**
 * Whether «update stock quantity» is saved for a slot (it takes effect only with Pro).
 *
 * @param int $index Supplier slot 1+.
 * @return bool
 */
function f2000cs_supplier_has_saved_stock_qty( $index ) {
	return f2000cs_option_is_on( 'f2000cs_update_stock_qty_' . absint( $index ) );
}

/**
 * Whether the supplier's prices must be left alone on this sync («do not update prices»).
 *
 * @param int $index Supplier slot 1+.
 * @return bool
 */
function f2000cs_supplier_skip_price( $index ) {
	return f2000cs_option_is_on( 'f2000cs_skip_price_' . absint( $index ) );
}

/**
 * Whether the slot saved «update stock quantity» but, with a license or trial in force,
 * the quantities are not updated because the Pro build is not installed.
 *
 * Writing only the stock status of a product with managed stock would then contradict
 * its frozen quantity, so the status is left alone until the Pro build is installed.
 * Without a license or trial (expired, never had one) the plugin behaves as the free
 * version always did: the status is updated and the quantity stays as it was.
 *
 * @param int       $index      Supplier slot 1+.
 * @param bool|null $update_qty Whether quantities are updated (default: resolved for the slot).
 * @return bool
 */
function f2000cs_supplier_qty_frozen( $index, $update_qty = null ) {
	$update_qty = null === $update_qty ? f2000cs_supplier_updates_stock_qty( $index ) : (bool) $update_qty;

	if ( $update_qty || ! f2000cs_supplier_has_saved_stock_qty( $index ) || ! f2000cs_has_active_license() ) {
		return false;
	}

	f2000cs_warn_pro_settings_ignored();

	return true;
}

/**
 * Stock updater for one supplier slot, with every per-slot rule applied.
 *
 * The single place that decides prices and quantities for a sync, so no entry
 * point (cron, the update page, background events) can forget a guard.
 *
 * @param int         $index      Supplier slot 1+, or 0 for a URL that matches no slot (no rules).
 * @param string      $xml_url    Feed URL.
 * @param string|null $sku_prefix SKU prefix (default: the slot's saved one).
 * @return \F2000CS\XML_Stock_Updater
 */
function f2000cs_make_stock_updater( $index, $xml_url, $sku_prefix = null ) {
	$index      = absint( $index );
	$sku_prefix = null === $sku_prefix ? (string) get_option( 'f2000cs_sku_prefix_' . $index, '' ) : (string) $sku_prefix;

	if ( $index < 1 ) {
		return new \F2000CS\XML_Stock_Updater( $xml_url, $sku_prefix );
	}

	$update_qty = f2000cs_supplier_updates_stock_qty( $index );

	return new \F2000CS\XML_Stock_Updater(
		$xml_url,
		$sku_prefix,
		f2000cs_supplier_skip_price( $index ),
		f2000cs_get_price_adjust_settings( $index ),
		$update_qty,
		f2000cs_supplier_qty_frozen( $index, $update_qty )
	);
}

/**
 * Tell the admin once a day that saved quantity updates are not applied (log + Telegram).
 *
 * @return void
 */
function f2000cs_warn_pro_settings_ignored() {
	if ( get_transient( 'f2000cs_pro_settings_ignored_warned' ) ) {
		return;
	}

	set_transient( 'f2000cs_pro_settings_ignored_warned', 1, DAY_IN_SECONDS );

	$msg = 'Збережено оновлення кількості (Pro), але Pro-збірка чи ліцензія неактивні. Залишки товарів із керованою кількістю не оновлюються, щоб не розійтися з кількістю. Встановіть Pro-версію з акаунта Freemius.';
	f2000cs_log( $msg, 'warning' );

	if ( function_exists( 'f2000cs_send_telegram_notification' ) ) {
		f2000cs_send_telegram_notification( '⚠️ ' . $msg );
	}
}

/**
 * Whether a paid license or a trial is in force (whatever build is installed).
 *
 * @return bool
 */
function f2000cs_has_active_license() {
	/**
	 * Filter whether a paid license or trial is in force.
	 *
	 * @param bool $active Freemius says a license or trial is active.
	 */
	return (bool) apply_filters( 'f2000cs_has_active_license', f2000cs_is_paying() || f2000cs_is_fs_trial() );
}

/**
 * Whether Pro-only settings are saved that need the Pro build to take effect.
 *
 * @return bool
 */
function f2000cs_has_saved_pro_config() {
	if ( f2000cs_option_is_on( 'f2000cs_hide_variable_low_instock' ) ) {
		return true;
	}

	$highest = f2000cs_get_highest_saved_supplier_slot();

	for ( $i = 1; $i <= $highest; $i++ ) {
		if ( f2000cs_supplier_has_saved_stock_qty( $i ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether quantity updates are enabled for a supplier slot (default off).
 *
 * The Pro build turns it on per slot through the `f2000cs_supplier_updates_stock_qty` filter.
 *
 * @param int $index Supplier slot 1+.
 * @return bool
 */
function f2000cs_supplier_updates_stock_qty( $index ) {
	return (bool) apply_filters( 'f2000cs_supplier_updates_stock_qty', false, absint( $index ) );
}

/**
 * Soft ceiling when scanning/registering dynamic supplier option keys.
 * Pro/trial can add as many as needed up to this safety cap.
 */
define( 'F2000CS_SUPPLIER_SLOT_SCAN_MAX', 200 );

/**
 * Option name for a supplier XML URL (slot 1 keeps legacy key f2000cs_url).
 *
 * @param int $index Supplier slot 1+.
 * @return string
 */
function f2000cs_get_supplier_url_option_key( $index ) {
	$index = absint( $index );

	return 1 === $index ? 'f2000cs_url' : 'f2000cs_url_' . $index;
}

/**
 * Saved XML URL for a supplier slot.
 *
 * @param int $index Supplier slot 1+.
 * @return string
 */
function f2000cs_get_supplier_url( $index ) {
	return trim( (string) get_option( f2000cs_get_supplier_url_option_key( $index ), '' ) );
}

/**
 * Whether an extra supplier slot (2+) already has saved configuration.
 *
 * Used to grandfather Free users who configured multiple suppliers before Pro.
 *
 * @param int $index Supplier slot.
 * @return bool
 */
function f2000cs_supplier_slot_has_saved_data( $index ) {
	$index = absint( $index );

	if ( $index < 2 ) {
		return false;
	}

	if ( '' !== f2000cs_get_supplier_url( $index ) ) {
		return true;
	}

	if ( '' !== trim( (string) get_option( 'f2000cs_sku_prefix_' . $index, '' ) ) ) {
		return true;
	}

	return false;
}

/**
 * Non-empty SKU prefixes of all saved supplier slots.
 *
 * @return array<int,string> Unique prefixes.
 */
function f2000cs_get_supplier_sku_prefixes() {
	$prefixes = array();

	for ( $i = 1, $max = f2000cs_get_highest_saved_supplier_slot(); $i <= $max; $i++ ) {
		$prefix = trim( (string) get_option( 'f2000cs_sku_prefix_' . $i, '' ) );
		if ( '' !== $prefix ) {
			$prefixes[] = $prefix;
		}
	}

	return array_values( array_unique( $prefixes ) );
}

/**
 * Highest supplier slot index that has saved data (at least 1).
 *
 * @return int
 */
function f2000cs_get_highest_saved_supplier_slot() {
	$highest = 1;

	for ( $i = 2; $i <= F2000CS_SUPPLIER_SLOT_SCAN_MAX; $i++ ) {
		if ( f2000cs_supplier_slot_has_saved_data( $i ) ) {
			$highest = $i;
		}
	}

	return $highest;
}

/**
 * Max slot index to register with the Settings API for the current request.
 *
 * Always covers legacy 2–5, any saved higher slots, and slots present in POST (new Add).
 *
 * @return int
 */
function f2000cs_get_supplier_option_register_max() {
	$max = max( 5, f2000cs_get_highest_saved_supplier_slot() );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only inspecting keys to register settings before save.
	if ( ! empty( $_POST ) && is_array( $_POST ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only inspecting keys to register settings before save.
		foreach ( array_keys( $_POST ) as $key ) {
			if ( preg_match( '/^f2000cs_(?:url|sku_prefix|skip_price|update_stock_qty|price_adjust_(?:type|direction|value))_(\d+)$/', (string) $key, $m ) ) {
				$max = max( $max, absint( $m[1] ) );
			}
		}
	}

	return min( F2000CS_SUPPLIER_SLOT_SCAN_MAX, $max );
}

/**
 * All extra supplier slot indexes (2+) that currently have saved data.
 *
 * @return int[]
 */
function f2000cs_get_saved_extra_supplier_slots() {
	$slots = array();
	$max   = f2000cs_get_highest_saved_supplier_slot();

	for ( $i = 2; $i <= $max; $i++ ) {
		if ( f2000cs_supplier_slot_has_saved_data( $i ) ) {
			$slots[] = $i;
		}
	}

	return $slots;
}

/**
 * Supplier slots visible in the repeater: always 1, plus extras that already have saved data.
 *
 * @return int[]
 */
function f2000cs_get_visible_supplier_slots() {
	return array_merge( array( 1 ), f2000cs_get_saved_extra_supplier_slots() );
}

/**
 * Check if all required settings are configured
 *
 * @return bool True if configured, false otherwise
 */
function f2000cs_is_configured() {
	if ( '' !== f2000cs_get_supplier_url( 1 ) ) {
		return true;
	}

	return ! empty( f2000cs_get_saved_extra_supplier_slots() );
}

/**
 * Flush the transients cache group only.
 *
 * The plugin clears its own and WooCommerce's transients during long runs. A global
 * cache flush would also drop the plugin's other entries — e.g. the URL import
 * session cache — mid-import, which used to re-download the feed into a new temp
 * file on every request. On WordPress without group support (before 6.1) the flush
 * is skipped on purpose: a global flush is exactly what this avoids.
 *
 * @return void
 */
function f2000cs_flush_transients_cache() {
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'transients' );
	}
}

/**
 * Free the in-process (runtime) object cache only.
 *
 * Long stock runs keep every loaded product and its meta in the runtime
 * cache until the end of the request. The old global object-cache flush
 * also cleared the persistent layer, which dropped the import session
 * mid-run; wp_cache_flush_runtime() (WordPress 6.0+) frees the memory
 * without touching the persistent layer. On older WordPress it is skipped:
 * a global flush is exactly what this avoids.
 *
 * @return void
 */
function f2000cs_flush_runtime_cache() {
	if ( function_exists( 'wp_cache_flush_runtime' ) ) {
		wp_cache_flush_runtime();
	}
}

/**
 * Clean up WooCommerce transients to free memory
 *
 * @param bool $aggressive Whether to perform aggressive cleanup
 * @return void
 */
function f2000cs_cleanup_wc_transients( $aggressive = false ) {
	global $wpdb;

	// Delete specific WooCommerce transients that might be using memory
	if ( $aggressive ) {
		// More aggressive cleanup for production environments.
		// Prefix-scoped so unrelated plugins' transients are never touched.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup with static query; caching not applicable.
		$wpdb->query(
			"
            DELETE FROM $wpdb->options 
            WHERE option_name LIKE '_transient_wc_%' 
            OR option_name LIKE '_transient_timeout_wc_%'
            OR option_name LIKE '_transient_product_%'
            OR option_name LIKE '_transient_timeout_product_%'
        "
		);
	} else {
		// Standard cleanup - only product specific transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup with static query; caching not applicable.
		$wpdb->query(
			"
            DELETE FROM $wpdb->options 
            WHERE option_name LIKE '%_transient_wc_product_%' 
            OR option_name LIKE '%_transient_timeout_wc_product_%'
        "
		);
	}

	f2000cs_flush_transients_cache();
}

/**
 * Check server resources availability for XML processing
 *
 * @return array Status information
 */
function f2000cs_check_server_resources() {
	$memory_limit        = ini_get( 'memory_limit' );
	$max_execution_time  = ini_get( 'max_execution_time' );
	$post_max_size       = ini_get( 'post_max_size' );
	$upload_max_filesize = ini_get( 'upload_max_filesize' );

	return array(
		'memory_limit'        => $memory_limit,
		'max_execution_time'  => $max_execution_time,
		'post_max_size'       => $post_max_size,
		'upload_max_filesize' => $upload_max_filesize,
	);
}

/**
 * Bulk-update stock_status in wc_product_meta_lookup (fast path, no full product save).
 *
 * @param array  $product_ids  Product or variation IDs.
 * @param string $stock_status Stock status value.
 * @return void
 */
function f2000cs_bulk_sync_lookup_stock_status( array $product_ids, $stock_status = 'outofstock' ) {
	global $wpdb;

	if ( empty( $product_ids ) || empty( $wpdb->wc_product_meta_lookup ) ) {
		return;
	}

	$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );

	if ( empty( $product_ids ) ) {
		return;
	}

	$stock_status = wc_clean( $stock_status );
	$table        = $wpdb->wc_product_meta_lookup;

	foreach ( array_chunk( $product_ids, 500 ) as $chunk ) {
		$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$table} is an internal WC lookup table name and $placeholders is a generated %d list; all values passed to prepare().
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET stock_status = %s
				WHERE product_id IN ($placeholders)
				  AND stock_status != %s",
				array_merge( array( $stock_status ), $chunk, array( $stock_status ) )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}
}

/**
 * HPOS-safe stock status update: writes both postmeta and the lookup table.
 *
 * @param int    $product_id   Product or variation ID.
 * @param string $stock_status 'instock' or 'outofstock'.
 * @return void
 */
function f2000cs_update_stock_status( $product_id, $stock_status ) {
	update_post_meta( $product_id, '_stock_status', $stock_status );
	f2000cs_sync_single_lookup_stock( $product_id, $stock_status );
}

/**
 * Sync a single product's stock status into the HPOS-compatible lookup table.
 *
 * @param int    $product_id   Product or variation ID.
 * @param string $stock_status 'instock' or 'outofstock'.
 * @return void
 */
function f2000cs_sync_single_lookup_stock( $product_id, $stock_status ) {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return;
	}

	$product_id   = absint( $product_id );
	$stock_status = wc_clean( $stock_status );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time HPOS sync; cache would be stale.
	$updated = $wpdb->update(
		$wpdb->wc_product_meta_lookup,
		array( 'stock_status' => $stock_status ),
		array( 'product_id' => $product_id )
	);

	// If the row doesn't exist yet (freshly imported product), insert it.
	if ( 0 === (int) $updated ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$wpdb->wc_product_meta_lookup,
			array(
				'product_id'   => $product_id,
				'stock_status' => $stock_status,
			)
		);
	}
}

/**
 * Sync a single product's prices into the HPOS-compatible lookup table.
 *
 * Reads _price, _regular_price, _sale_price from postmeta and updates
 * wc_product_meta_lookup accordingly.
 *
 * @param int $product_id Product or variation ID.
 * @return void
 */
function f2000cs_sync_price_lookup( $product_id ) {
	global $wpdb;

	if ( empty( $wpdb->wc_product_meta_lookup ) ) {
		return;
	}

	$product_id = absint( $product_id );

	$price         = get_post_meta( $product_id, '_price', true );
	$regular_price = get_post_meta( $product_id, '_regular_price', true );
	$sale_price    = get_post_meta( $product_id, '_sale_price', true );

	$price         = '' !== $price ? (float) $price : null;
	$regular_price = '' !== $regular_price ? (float) $regular_price : null;
	$sale_price    = '' !== $sale_price ? (float) $sale_price : null;

	$min_price = $sale_price > 0 ? $sale_price : $price;
	$max_price = $regular_price > 0 ? $regular_price : $price;

	if ( null === $min_price && null === $max_price ) {
		return;
	}

	$data = array(
		'min_price' => $min_price,
		'max_price' => $max_price,
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT product_id FROM {$wpdb->wc_product_meta_lookup} WHERE product_id = %d LIMIT 1",
			$product_id
		)
	);

	if ( $exists ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->wc_product_meta_lookup,
			$data,
			array( 'product_id' => $product_id )
		);
		return;
	}

	$data['product_id'] = $product_id;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->insert( $wpdb->wc_product_meta_lookup, $data );
}

/**
 * Look up WooCommerce product/variation IDs by their SKU values.
 *
 * Used by the XML import and stock sync flows.  Chunked for large sets
 * (max 500 SKUs per query).
 *
 * @param array<string> $skus SKU values to look up.
 * @return array<string, int> Map of sku => product_id.
 */
function f2000cs_get_product_ids_by_skus( array $skus ): array {
	global $wpdb;

	if ( empty( $skus ) ) {
		return array();
	}

	$results = array();

	foreach ( array_chunk( $skus, 500 ) as $chunk ) {
		$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT pm.meta_value AS sku, p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status IN ('publish', 'draft', 'private')
			AND pm.meta_key = '_sku'
			AND pm.meta_value IN ($placeholders)",
			$chunk
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		foreach ( (array) $rows as $row ) {
			$results[ (string) $row->sku ] = (int) $row->ID;
		}
	}

	return $results;
}

/**
 * Run post-processing steps after a stock update cycle completes.
 *
 * @return void
 */
function f2000cs_after_stock_update_complete() {
	/**
	 * Fires once a whole stock update cycle (cron or background batch) is finished.
	 */
	do_action( 'f2000cs_stock_update_complete' );
}

/**
 * Run product synchronization via a background process
 *
 * @param string $xml_url URL of the XML file
 * @param string $sku_prefix SKU prefix for this XML source
 * @return bool Whether sync was started
 */
function f2000cs_trigger_background_sync( $xml_url, $sku_prefix = '' ) {
	if ( empty( $xml_url ) ) {
		return false;
	}

	if ( ! wp_next_scheduled( 'f2000cs_update_stock_cron' ) ) {
		\F2000CS\Cron_Job::activate();
	}

	// Schedule the update to happen in the background in 30 seconds
	if ( ! wp_next_scheduled( 'f2000cs_single_update_event', array( $xml_url, $sku_prefix ) ) ) {
		// One more job added to the pending batch; only increment once this event is
		// actually newly scheduled, so the counter always matches the number of events
		// that will really fire.
		f2000cs_increment_background_batch_counter( 1 );
		wp_schedule_single_event( time() + 30, 'f2000cs_single_update_event', array( $xml_url, $sku_prefix ) );
		return true;
	}

	return false;
}

/**
 * Track how many background sync jobs are still pending in the current batch,
 * so post-processing (low-instock rule, Telegram summary) runs only once,
 * after the last job in the batch finishes rather than after every single job.
 *
 * @param int $count Number of jobs to add to the pending counter.
 * @return void
 */
function f2000cs_increment_background_batch_counter( $count ) {
	$remaining = (int) get_transient( 'f2000cs_bg_batch_remaining' );
	set_transient( 'f2000cs_bg_batch_remaining', $remaining + $count, HOUR_IN_SECONDS );
}

/**
 * Decrement the pending background batch counter.
 *
 * @return int Remaining jobs after decrementing (0 or less means the batch is done).
 */
function f2000cs_decrement_background_batch_counter() {
	$remaining = (int) get_transient( 'f2000cs_bg_batch_remaining' ) - 1;

	if ( $remaining > 0 ) {
		set_transient( 'f2000cs_bg_batch_remaining', $remaining, HOUR_IN_SECONDS );
	} else {
		delete_transient( 'f2000cs_bg_batch_remaining' );
	}

	return $remaining;
}

/**
 * Find the next pending 'f2000cs_single_update_event' regardless of its scheduled arguments.
 *
 * wp_next_scheduled() only matches events scheduled with the exact same arguments, but our
 * background events are always scheduled with a unique (url, sku_prefix) pair, so checking
 * with no arguments would never find them. This scans the cron array directly instead.
 *
 * @return int|false Timestamp of the next pending event, or false if none is scheduled.
 */
function f2000cs_get_next_background_event() {
	$crons = _get_cron_array();

	if ( empty( $crons ) ) {
		return false;
	}

	foreach ( $crons as $timestamp => $hooks ) {
		// The cron array also carries a non-numeric 'version' key alongside timestamp keys; skip it.
		if ( ! is_array( $hooks ) ) {
			continue;
		}

		if ( ! empty( $hooks['f2000cs_single_update_event'] ) ) {
			return (int) $timestamp;
		}
	}

	return false;
}

/**
 * Clear all pending 'f2000cs_single_update_event' cron events, regardless of their
 * scheduled arguments, and reset the background batch counter.
 *
 * wp_clear_scheduled_hook( 'f2000cs_single_update_event' ) without an $args parameter only
 * removes events scheduled with an *empty* args array. Since our background events are
 * always scheduled with a unique (url, sku_prefix) pair, that call is a no-op and leaves
 * the events (and the eventual duplicate execution) in place. This walks the cron array
 * directly and unschedules every matching entry regardless of its arguments.
 *
 * @return void
 */
function f2000cs_clear_all_background_events() {
	$crons = _get_cron_array();

	if ( ! empty( $crons ) ) {
		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) || empty( $hooks['f2000cs_single_update_event'] ) ) {
				continue;
			}

			foreach ( $hooks['f2000cs_single_update_event'] as $event ) {
				$args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
				wp_unschedule_event( $timestamp, 'f2000cs_single_update_event', $args );
			}
		}
	}

	delete_transient( 'f2000cs_bg_batch_remaining' );
}

add_action(
	'f2000cs_single_update_event',
	function ( $xml_url, $sku_prefix = '' ) {
		if ( ! empty( $xml_url ) ) {
			f2000cs_cleanup_wc_transients();
			// The slot this URL belongs to decides the price and quantity rules (0: none).
			$slot    = 0;
			$highest = f2000cs_get_highest_saved_supplier_slot();

			for ( $i = 1; $i <= $highest; $i++ ) {
				if ( f2000cs_get_supplier_url( $i ) === $xml_url ) {
					$slot = $i;
					break;
				}
			}

			$updater = f2000cs_make_stock_updater( $slot, $xml_url, $sku_prefix );
			$updater->update_products_stock_status();

			// Only run post-processing (low-instock rule, Telegram summary) once the whole batch is done.
			if ( f2000cs_decrement_background_batch_counter() <= 0 ) {
				f2000cs_after_stock_update_complete();
			}

			f2000cs_cleanup_wc_transients( true );
		}
	},
	10,
	2
);

/**
 * Run database migrations when the stored version is older.
 *
 * Runs on plugins_loaded (so cron sees migrated options), admin_init, and activation.
 *
 * @return void
 */
function f2000cs_maybe_run_migrations() {
	$stored = (int) get_option( 'f2000cs_db_version', 0 );

	if ( $stored >= F2000CS_DB_VERSION ) {
		return;
	}

	// Migration 1: rename f2000cs_sku_prefix → f2000cs_sku_prefix_1 (slot-1 key).
	if ( $stored < 1 ) {
		f2000cs_migrate_sku_prefix_slot_1();
	}

	update_option( 'f2000cs_db_version', F2000CS_DB_VERSION, false );
}

/**
 * Migrate old f2000cs_sku_prefix to f2000cs_sku_prefix_1.
 *
 * In versions before the slot system, slot 1 used the unprefixed key.
 * After renaming, existing installs lose their prefix unless migrated.
 *
 * @return void
 */
function f2000cs_migrate_sku_prefix_slot_1() {
	$old = get_option( 'f2000cs_sku_prefix', null );

	if ( null === $old ) {
		return;
	}

	if ( '' === trim( (string) $old ) ) {
		delete_option( 'f2000cs_sku_prefix' );
		return;
	}

	// Only migrate if the new key is not already set (don't overwrite).
	$current = get_option( 'f2000cs_sku_prefix_1', null );
	if ( null === $current || '' === trim( (string) $current ) ) {
		update_option( 'f2000cs_sku_prefix_1', $old, false );
	}

	delete_option( 'f2000cs_sku_prefix' );
}

/**
 * Absolute path to the plugin exports directory under uploads.
 *
 * @return string
 */
function f2000cs_get_exports_dir() {
	$upload_dir = wp_upload_dir();

	return trailingslashit( $upload_dir['basedir'] ) . 'f2000cs-exports';
}

/**
 * Ensure an uploads subdirectory exists and is not web-listable (Apache).
 *
 * @param string $dir Absolute directory path.
 * @return string Same directory path.
 */
function f2000cs_protect_uploads_subdir( $dir ) {
	$dir = untrailingslashit( (string) $dir );

	if ( '' === $dir ) {
		return '';
	}

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		$rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Protection file written once beside private uploads.
		file_put_contents( $htaccess, $rules );
	}

	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Silence directory listing.
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	return $dir;
}

/**
 * Ensure the exports directory exists and is not listable via the web server.
 *
 * @return string Absolute directory path.
 */
function f2000cs_ensure_exports_dir() {
	return f2000cs_protect_uploads_subdir( f2000cs_get_exports_dir() );
}

/**
 * Absolute path to XML editor session storage under uploads.
 *
 * @return string
 */
function f2000cs_get_editor_sessions_dir() {
	$upload_dir = wp_upload_dir();

	return trailingslashit( $upload_dir['basedir'] ) . 'f2000cs-editor-sessions';
}

/**
 * Ensure editor session directory exists and is not web-listable.
 *
 * @return string Absolute directory path.
 */
function f2000cs_ensure_editor_sessions_dir() {
	return f2000cs_protect_uploads_subdir( f2000cs_get_editor_sessions_dir() );
}

/**
 * Recursively delete a directory under wp uploads (best-effort).
 *
 * @param string $dir Absolute directory path.
 * @return void
 */
function f2000cs_delete_uploads_subdir( $dir ) {
	$dir = untrailingslashit( (string) $dir );
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$basedir    = isset( $upload_dir['basedir'] ) ? untrailingslashit( (string) $upload_dir['basedir'] ) : '';
	if ( '' === $basedir || 0 !== strpos( $dir, $basedir ) ) {
		return;
	}

	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			f2000cs_delete_uploads_subdir( $path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Uninstall cleanup of plugin-owned temp files.
			unlink( $path );
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Uninstall cleanup.
	rmdir( $dir );
}
