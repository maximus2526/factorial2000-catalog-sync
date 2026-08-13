<?php
/**
 * Freemius licensing + Pro feature gates.
 *
 * Replace F2000CS_FS_ID and F2000CS_FS_PUBLIC_KEY with values from your Freemius dashboard.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'F2000CS_FS_ID' ) ) {
	// Freemius plugin ID from the developer dashboard.
	define( 'F2000CS_FS_ID', '36366' );
}

if ( ! defined( 'F2000CS_FS_PUBLIC_KEY' ) ) {
	// Freemius public key from the developer dashboard.
	define( 'F2000CS_FS_PUBLIC_KEY', 'pk_fa191e8cb793a3bf2bdd41bc8fd33' );
}

/** Free plan: max number of supplier slots. */
define( 'F2000CS_FREE_MAX_SUPPLIERS', 1 );

/**
 * Whether Freemius credentials look configured (not placeholders).
 *
 * @return bool
 */
function f2000cs_fs_credentials_ready() {
	$id  = (string) F2000CS_FS_ID;
	$key = (string) F2000CS_FS_PUBLIC_KEY;

	return '' !== $id
		&& 'YOUR_PLUGIN_ID' !== $id
		&& ctype_digit( $id )
		&& '' !== $key
		&& 'YOUR_PUBLIC_KEY' !== $key
		&& 0 === strpos( $key, 'pk_' );
}

/**
 * Create a Freemius instance (or null when credentials are not ready).
 *
 * Freemius trial: 14 days, no credit card required.
 *
 * @return Freemius|null
 */
function f2000cs_fs() {
	global $f2000cs_fs;

	if ( ! f2000cs_fs_credentials_ready() ) {
		return null;
	}

	if ( ! isset( $f2000cs_fs ) ) {
		$start = dirname( __DIR__ ) . '/freemius/start.php';
		if ( ! file_exists( $start ) ) {
			return null;
		}

		require_once $start;

		$f2000cs_fs = fs_dynamic_init(
			array(
				'id'                  => F2000CS_FS_ID,
				'slug'                => 'factorial2000-catalog-sync-for-promua',
				'premium_slug'        => 'factorial2000-catalog-sync-for-promua-premium',
				'type'                => 'plugin',
				'public_key'          => F2000CS_FS_PUBLIC_KEY,
				// The wp.org build is the FREE version. Marking it as premium
				// forces the connect screen to demand a license key instead of
				// offering opt-in + free trial (manual installs were blocked).
				'is_premium'          => false,
				'premium_suffix'      => 'Pro',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => true,
				'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'trial'               => array(
					'days'               => 14,
					'is_require_payment' => false,
				),
				'menu'                => array(
					'slug' => 'f2000cs-update',
				),
			)
		);

		// Freemius forbids uninstall.php — cleanup runs after uninstall is reported.
		$f2000cs_fs->add_action( 'after_uninstall', 'f2000cs_uninstall_cleanup' );
	}

	return $f2000cs_fs;
}

/**
 * Alias matching Freemius integration examples.
 *
 * @return Freemius|null
 */
function factorial2000_catalog_sync() {
	return f2000cs_fs();
}

/**
 * Bootstrap Freemius as early as possible when credentials exist.
 *
 * @return void
 */
function f2000cs_fs_init() {
	f2000cs_fs();
	/**
	 * Fires after Freemius SDK was initiated for this plugin.
	 */
	do_action( 'f2000cs_fs_loaded' );
}
add_action( 'plugins_loaded', 'f2000cs_fs_init', 1 );

/**
 * Restore license-key activation UI on Freemius free builds.
 *
 * Freemius free zips omit has_premium_version; the SDK then defaults it to
 * has_paid_plans (true) and skips Activate License / AJAX handlers. Pro in this
 * plugin is unlocked by license/trial in the same codebase, so free sites still
 * need a key field without going through checkout.
 *
 * @return void
 */
function f2000cs_enable_license_key_on_free_build() {
	$fs = f2000cs_fs();
	if ( ! $fs || ! method_exists( $fs, 'is_premium' ) || $fs->is_premium() ) {
		return;
	}

	if ( ! method_exists( $fs, 'has_paid_plan' ) || ! $fs->has_paid_plan() ) {
		return;
	}

	if ( ! method_exists( $fs, 'is_user_admin' ) || ! $fs->is_user_admin() ) {
		return;
	}

	// Freemius only attaches AJAX callbacks during the matching admin-ajax request.
	if ( method_exists( $fs, 'add_ajax_action' ) ) {
		$fs->add_ajax_action( 'activate_license', array( $fs, '_activate_license_ajax_action' ) );
		$fs->add_ajax_action( 'resend_license_key', array( $fs, '_resend_license_key_ajax_action' ) );
	}

	if (
		method_exists( 'Freemius', 'is_plugins_page' )
		&& Freemius::is_plugins_page()
		&& method_exists( $fs, '_add_license_action_link' )
	) {
		$fs->_add_license_action_link();
	}
}
add_action( 'admin_init', 'f2000cs_enable_license_key_on_free_build', 20 );

/**
 * Whether the site has an active Freemius paid license (not trial).
 *
 * @return bool
 */
function f2000cs_is_paying() {
	$fs = f2000cs_fs();

	return $fs && method_exists( $fs, 'is_paying' ) && $fs->is_paying();
}

/**
 * Whether Freemius trial is active.
 *
 * @return bool
 */
function f2000cs_is_fs_trial() {
	$fs = f2000cs_fs();

	return $fs && method_exists( $fs, 'is_trial' ) && $fs->is_trial();
}

/**
 * Freemius trial end timestamp, or 0.
 *
 * @return int
 */
function f2000cs_get_fs_trial_ends() {
	$fs = f2000cs_fs();
	if ( ! $fs || ! method_exists( $fs, 'is_trial' ) || ! $fs->is_trial() ) {
		return 0;
	}

	if ( ! method_exists( $fs, 'get_site' ) ) {
		return 0;
	}

	$site = $fs->get_site();
	if ( ! is_object( $site ) || empty( $site->trial_ends ) ) {
		return 0;
	}

	$ts = strtotime( (string) $site->trial_ends );

	return $ts ? (int) $ts : 0;
}

/**
 * Freemius trial end timestamp for countdown UI (0 when not on a trial / paying).
 *
 * @return int Unix timestamp or 0.
 */
function f2000cs_get_trial_ends_at() {
	if ( f2000cs_is_paying() ) {
		return 0;
	}

	$fs_ends = f2000cs_get_fs_trial_ends();

	return ( $fs_ends > time() ) ? $fs_ends : 0;
}

/**
 * Whether Pro features are unlocked (paid Freemius license or Freemius trial).
 * Active trial has the same feature access as Pro.
 *
 * @return bool
 */
function f2000cs_is_pro() {
	$is_pro = f2000cs_is_paying() || f2000cs_is_fs_trial();

	if ( defined( 'F2000CS_FORCE_PRO' ) && F2000CS_FORCE_PRO ) {
		$is_pro = true;
	}

	/**
	 * Filter Pro unlock status (trial included).
	 *
	 * @param bool $is_pro Current Pro status.
	 */
	return (bool) apply_filters( 'f2000cs_is_pro', $is_pro );
}

/**
 * Max new supplier slots for messaging / Free plan.
 *
 * Pro and trial are unlimited (soft-capped by F2000CS_SUPPLIER_SLOT_SCAN_MAX).
 *
 * @return int 0 means unlimited for Pro/trial.
 */
function f2000cs_get_max_suppliers() {
	return f2000cs_is_pro() ? 0 : F2000CS_FREE_MAX_SUPPLIERS;
}

/**
 * Whether a supplier slot may be synced / edited.
 *
 * Free: slot 1 always; any higher slot only if it already has saved data.
 * Pro/trial: any slot.
 *
 * @param int $index Supplier index.
 * @return bool
 */
function f2000cs_can_use_supplier( $index ) {
	$index = absint( $index );

	if ( $index < 1 ) {
		return false;
	}

	if ( 1 === $index || f2000cs_is_pro() ) {
		return true;
	}

	return function_exists( 'f2000cs_supplier_slot_has_saved_data' )
		&& f2000cs_supplier_slot_has_saved_data( $index );
}

/**
 * Whether the site may add a new empty supplier slot (Add button).
 * Pro and active trial both unlock this (trial === Pro features).
 *
 * @return bool
 */
function f2000cs_can_add_supplier_slot() {
	return f2000cs_is_pro();
}

/**
 * Configured XML URLs allowed by the current license (+ grandfathered Free slots).
 *
 * @return array<int, string> Slot index => URL.
 */
function f2000cs_get_active_supplier_urls() {
	$xml_urls = array();
	$highest  = function_exists( 'f2000cs_get_highest_saved_supplier_slot' )
		? f2000cs_get_highest_saved_supplier_slot()
		: 5;

	for ( $i = 1; $i <= $highest; $i++ ) {
		if ( ! f2000cs_can_use_supplier( $i ) ) {
			continue;
		}

		$url = function_exists( 'f2000cs_get_supplier_url' )
			? f2000cs_get_supplier_url( $i )
			: get_option( 'f2000cs_url' . ( 1 === $i ? '' : '_' . $i ), '' );

		if ( ! empty( $url ) ) {
			$xml_urls[ $i ] = $url;
		}
	}

	return $xml_urls;
}

/**
 * Render admin page H1 with optional green Pro badge (Pro license or active trial).
 *
 * @param string $title Page title text.
 * @return void
 */
function f2000cs_render_admin_page_title( $title ) {
	$show_pro = function_exists( 'f2000cs_is_pro' ) && f2000cs_is_pro();
	?>
	<h1 class="f2000cs-admin-title">
		<?php echo esc_html( $title ); ?>
		<?php if ( $show_pro ) : ?>
			<span class="f2000cs-title-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
		<?php endif; ?>
	</h1>
	<?php
}

/**
 * Upgrade / pricing URL when Freemius is available.
 *
 * @return string
 */
function f2000cs_get_upgrade_url() {
	$fs = f2000cs_fs();
	if ( $fs && method_exists( $fs, 'get_upgrade_url' ) ) {
		return (string) $fs->get_upgrade_url();
	}

	return admin_url( 'admin.php?page=f2000cs-update#f2000cs-pro-plans' );
}

/**
 * Approximate USD→UAH rate for display (not live FX).
 *
 * @return float
 */
function f2000cs_get_usd_uah_rate() {
	$default = defined( 'F2000CS_USD_UAH_RATE' ) ? (float) F2000CS_USD_UAH_RATE : 45.0;

	return (float) apply_filters( 'f2000cs_usd_uah_rate', $default );
}

/**
 * Format USD price with approximate UAH in parentheses.
 *
 * @param float $usd Amount in USD.
 * @return string e.g. "$7.99 (~360 грн)"
 */
function f2000cs_format_pro_price_usd( $usd ) {
	$usd     = (float) $usd;
	$uah     = (int) round( $usd * f2000cs_get_usd_uah_rate() );
	$uah_fmt = number_format( $uah, 0, ',', ' ' );

	return sprintf(
		/* translators: 1: USD amount like 7.99, 2: UAH amount like 360 */
		__( '$%1$s (~%2$s грн)', 'factorial2000-catalog-sync' ),
		number_format( $usd, 2, '.', '' ),
		$uah_fmt
	);
}

/**
 * Display price for monthly Pro plan (override via filter or constant F2000CS_PRO_PRICE_MONTHLY).
 *
 * @return string
 */
function f2000cs_get_pro_price_monthly() {
	$default = defined( 'F2000CS_PRO_PRICE_MONTHLY' )
		? (string) F2000CS_PRO_PRICE_MONTHLY
		: f2000cs_format_pro_price_usd( 7.99 );

	return (string) apply_filters( 'f2000cs_pro_price_monthly', $default );
}

/**
 * Display price for yearly Pro plan.
 *
 * @return string
 */
function f2000cs_get_pro_price_yearly() {
	$default = defined( 'F2000CS_PRO_PRICE_YEARLY' )
		? (string) F2000CS_PRO_PRICE_YEARLY
		: f2000cs_format_pro_price_usd( 71.88 );

	return (string) apply_filters( 'f2000cs_pro_price_yearly', $default );
}

/**
 * Display price for lifetime Pro plan.
 *
 * @return string
 */
function f2000cs_get_pro_price_lifetime() {
	$default = defined( 'F2000CS_PRO_PRICE_LIFETIME' )
		? (string) F2000CS_PRO_PRICE_LIFETIME
		: f2000cs_format_pro_price_usd( 219.99 );

	return (string) apply_filters( 'f2000cs_pro_price_lifetime', $default );
}

/**
 * Today's date key in site timezone (Y-m-d).
 *
 * @return string
 */
function f2000cs_get_today_key() {
	return wp_date( 'Y-m-d' );
}

/**
 * Free daily update limit.
 */
define( 'F2000CS_FREE_DAILY_UPDATES', 3 );

/**
 * Whether Free already exhausted today's stock updates.
 *
 * @return bool
 */
function f2000cs_free_update_used_today() {
	$today     = f2000cs_get_today_key();
	$last_day  = (string) get_option( 'f2000cs_free_update_day', '' );
	$run_count = (int) get_option( 'f2000cs_free_update_count', 0 );

	if ( $last_day !== $today ) {
		return false;
	}

	return $run_count >= F2000CS_FREE_DAILY_UPDATES;
}

/**
 * Count of remaining Free updates today.
 *
 * @return int
 */
function f2000cs_free_updates_remaining() {
	if ( f2000cs_is_pro() ) {
		return 999;
	}

	$today     = f2000cs_get_today_key();
	$last_day  = (string) get_option( 'f2000cs_free_update_day', '' );
	$run_count = (int) get_option( 'f2000cs_free_update_count', 0 );

	if ( $last_day !== $today ) {
		return F2000CS_FREE_DAILY_UPDATES;
	}

	return max( 0, F2000CS_FREE_DAILY_UPDATES - $run_count );
}

/**
 * Whether a stock update may run now (Pro/trial unlimited; Free = 3×/day).
 *
 * @return bool
 */
function f2000cs_can_run_stock_update() {
	if ( f2000cs_is_pro() ) {
		return true;
	}

	return ! f2000cs_free_update_used_today();
}

/**
 * Record that a stock update started (consumes Free daily quota).
 *
 * @return void
 */
function f2000cs_record_stock_update_run() {
	$today     = f2000cs_get_today_key();
	$last_day  = (string) get_option( 'f2000cs_free_update_day', '' );
	$run_count = (int) get_option( 'f2000cs_free_update_count', 0 );

	if ( $last_day !== $today ) {
		$run_count = 0;
	}

	update_option( 'f2000cs_free_update_day', $today, false );
	update_option( 'f2000cs_free_update_count', $run_count + 1, false );
}

/**
 * Human message when Free daily quota is exhausted.
 *
 * @return string
 */
function f2000cs_get_free_update_limit_message() {
	return __( 'У безкоштовній версії оновлення доступне 3 рази на добу. Ліміт вичерпано — наступне завтра, або оновіть до Pro для необмежених запусків.', 'factorial2000-catalog-sync' );
}

/**
 * Effective cron interval (Free is always forced to daily).
 *
 * @param string|null $interval Stored interval.
 * @return string
 */
function f2000cs_get_effective_update_interval( $interval = null ) {
	if ( null === $interval ) {
		$interval = get_option( 'f2000cs_update_interval', 'hourly' );
	}

	if ( ! f2000cs_is_pro() ) {
		return 'daily';
	}

	$allowed = array( '5_minute', '10_minute', '15_minute', '30_minute', 'hourly', 'twicedaily', 'daily' );

	return in_array( $interval, $allowed, true ) ? $interval : 'hourly';
}

/**
 * Admin body class for free/pro styling.
 *
 * @param string $classes Space-separated classes.
 * @return string
 */
function f2000cs_admin_body_class( $classes ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || false === strpos( (string) $screen->id, 'f2000cs-' ) ) {
		return $classes;
	}

	$classes .= f2000cs_is_pro() ? ' f2000cs-is-pro' : ' f2000cs-is-free';

	return $classes;
}
add_filter( 'admin_body_class', 'f2000cs_admin_body_class' );

/**
 * Block saving Pro-only options on the free plan.
 *
 * Extra supplier slots that already have saved data stay editable (grandfathered).
 * New data on empty slots is blocked without Pro. Price adjust / variable-low remain Pro-only.
 * Trial unlocks the same features as Pro (see f2000cs_is_pro()).
 *
 * @return void
 */
function f2000cs_register_pro_option_guards() {
	// Preserve existing values when a registered option is absent from POST (WP passes null).
	f2000cs_register_missing_post_preservers();

	if ( f2000cs_is_pro() ) {
		return;
	}

	$max = function_exists( 'f2000cs_get_supplier_option_register_max' )
		? f2000cs_get_supplier_option_register_max()
		: 5;

	for ( $i = 2; $i <= $max; $i++ ) {
		$index = $i;
		add_filter(
			'pre_update_option_f2000cs_url_' . $i,
			function ( $value, $old_value ) use ( $index ) {
				return f2000cs_guard_extra_supplier_option( $value, $old_value, $index, 'url' );
			},
			10,
			2
		);
		add_filter(
			'pre_update_option_f2000cs_sku_prefix_' . $i,
			function ( $value, $old_value ) use ( $index ) {
				return f2000cs_guard_extra_supplier_option( $value, $old_value, $index, 'sku' );
			},
			10,
			2
		);
		add_filter(
			'pre_update_option_f2000cs_skip_price_' . $i,
			function ( $value, $old_value ) use ( $index ) {
				return f2000cs_guard_extra_supplier_option( $value, $old_value, $index, 'skip' );
			},
			10,
			2
		);
	}

	for ( $i = 1; $i <= $max; $i++ ) {
		add_filter( 'pre_update_option_f2000cs_price_adjust_type_' . $i, 'f2000cs_block_pro_option_update', 10, 2 );
		add_filter( 'pre_update_option_f2000cs_price_adjust_direction_' . $i, 'f2000cs_block_pro_option_update', 10, 2 );
		add_filter( 'pre_update_option_f2000cs_price_adjust_value_' . $i, 'f2000cs_block_pro_option_update', 10, 2 );
		add_filter( 'pre_update_option_f2000cs_update_stock_qty_' . $i, 'f2000cs_block_pro_option_update', 10, 2 );
	}

	add_filter( 'pre_update_option_f2000cs_hide_variable_low_instock', 'f2000cs_block_pro_option_update', 10, 2 );
	add_filter( 'pre_update_option_f2000cs_variable_low_instock_max', 'f2000cs_block_pro_option_update', 10, 2 );
	add_filter( 'pre_update_option_f2000cs_img_png_convert', 'f2000cs_block_pro_option_update', 10, 2 );
	add_filter( 'pre_update_option_f2000cs_img_optimize', 'f2000cs_block_pro_option_update', 10, 2 );
	add_filter( 'pre_update_option_f2000cs_img_quality', 'f2000cs_block_pro_option_update', 10, 2 );
	add_filter( 'pre_update_option_f2000cs_img_max_dimension', 'f2000cs_block_pro_option_update', 10, 2 );
}
add_action( 'admin_init', 'f2000cs_register_pro_option_guards', 1 );

/**
 * Prevent options.php from wiping registered fields that are not in the current form POST.
 *
 * @return void
 */
function f2000cs_register_missing_post_preservers() {
	$keys = array(
		'f2000cs_url',
		'f2000cs_sku_prefix_1',
		'f2000cs_skip_price_1',
		'f2000cs_update_stock_qty_1',
		'f2000cs_price_adjust_type_1',
		'f2000cs_price_adjust_direction_1',
		'f2000cs_price_adjust_value_1',
		'f2000cs_img_png_convert',
		'f2000cs_img_optimize',
		'f2000cs_img_quality',
		'f2000cs_img_max_dimension',
	);

	$max = function_exists( 'f2000cs_get_supplier_option_register_max' )
		? f2000cs_get_supplier_option_register_max()
		: 5;

	for ( $i = 2; $i <= $max; $i++ ) {
		$keys[] = 'f2000cs_url_' . $i;
		$keys[] = 'f2000cs_sku_prefix_' . $i;
		$keys[] = 'f2000cs_skip_price_' . $i;
		$keys[] = 'f2000cs_update_stock_qty_' . $i;
		$keys[] = 'f2000cs_price_adjust_type_' . $i;
		$keys[] = 'f2000cs_price_adjust_direction_' . $i;
		$keys[] = 'f2000cs_price_adjust_value_' . $i;
	}

	foreach ( $keys as $key ) {
		add_filter( 'pre_update_option_' . $key, 'f2000cs_preserve_option_if_missing_from_post', 5, 2 );
	}
}

/**
 * If options.php passes null (field not in POST), keep the previous option value.
 *
 * @param mixed $value     Incoming value.
 * @param mixed $old_value Existing value.
 * @return mixed
 */
function f2000cs_preserve_option_if_missing_from_post( $value, $old_value ) {
	return null === $value ? $old_value : $value;
}

/**
 * Guard Free updates for extra supplier option keys.
 *
 * @param mixed  $value     New value.
 * @param mixed  $old_value Old value.
 * @param int    $index     Supplier slot 2–5.
 * @param string $kind      url|sku|skip.
 * @return mixed
 */
function f2000cs_guard_extra_supplier_option( $value, $old_value, $index, $kind ) {
	$index = absint( $index );

	/*
	 * WordPress options.php calls update_option( $key, null ) when the field is
	 * absent from POST. Never treat that as an intentional clear — it would wipe
	 * grandfathered supplier data for slots not currently rendered.
	 */
	if ( null === $value ) {
		return $old_value;
	}

	// Always allow clearing a field that was actually submitted empty.
	if ( 'skip' === $kind ) {
		$is_empty = ( '0' === (string) $value || '' === (string) $value );
	} else {
		$is_empty = ( '' === trim( (string) $value ) );
	}

	if ( $is_empty ) {
		return $value;
	}

	// Slot already had data (or this field already had a value) — keep editing.
	$had_field = ( 'skip' === $kind )
		? ( '1' === (string) $old_value )
		: ( '' !== trim( (string) $old_value ) );

	if ( $had_field || ( function_exists( 'f2000cs_supplier_slot_has_saved_data' ) && f2000cs_supplier_slot_has_saved_data( $index ) ) ) {
		return $value;
	}

	// Brand-new data on an empty Free slot — Pro only.
	return $old_value;
}

/**
 * Keep the previous option value when the feature is Pro-only.
 *
 * Also preserves the old value when options.php passes null (field absent from POST).
 *
 * @param mixed $value     New value.
 * @param mixed $old_value Old value.
 * @return mixed
 */
function f2000cs_block_pro_option_update( $value, $old_value ) {
	return $old_value;
}

/**
 * Render trial countdown banner on the main settings page.
 *
 * @return void
 */
function f2000cs_render_trial_countdown() {
	if ( f2000cs_is_paying() ) {
		return;
	}

	$ends = f2000cs_get_trial_ends_at();
	if ( $ends <= time() ) {
		if ( ! f2000cs_is_pro() ) {
			echo '<div class="notice notice-info f2000cs-pro-notice"><p>';
			echo esc_html__( 'Зараз у вас безкоштовна версія: 1 постачальник (плюс уже збережені під час тріалу) і 3 оновлення наявності на добу.', 'factorial2000-catalog-sync' );
			echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Дивитись Pro', 'factorial2000-catalog-sync' ) . '</a>';
			echo '</p></div>';
			f2000cs_render_pro_plans_panel();
		}
		return;
	}

	$remaining = $ends - time();
	$days      = (int) floor( $remaining / DAY_IN_SECONDS );
	$hours     = (int) floor( ( $remaining % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
	$minutes   = (int) floor( ( $remaining % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

	echo '<div class="notice notice-warning f2000cs-trial-countdown" data-ends="' . esc_attr( (string) $ends ) . '">';
	echo '<p>';
	echo '<strong>' . esc_html__( 'Pro-тріал активний', 'factorial2000-catalog-sync' ) . ':</strong> ';
	echo '<span class="f2000cs-trial-countdown__label">';
	echo esc_html(
		sprintf(
			/* translators: 1: days, 2: hours, 3: minutes */
			__( 'залишилось %1$d дн. %2$d год. %3$d хв.', 'factorial2000-catalog-sync' ),
			(int) $days,
			(int) $hours,
			(int) $minutes
		)
	);
	echo '</span>';
	echo ' — ' . esc_html__( 'повний доступ як у Pro. Після тріалу обмеження Free повернуться.', 'factorial2000-catalog-sync' );
	echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Оформити Pro', 'factorial2000-catalog-sync' ) . '</a>';
	echo '</p>';
	echo '</div>';

	f2000cs_render_pro_plans_panel( true );
}

/**
 * Visible Free vs Pro pricing / plans block.
 *
 * @param bool $compact When true (during trial), slightly shorter CTA copy.
 * @return void
 */
function f2000cs_render_pro_plans_panel( $compact = false ) {
	if ( f2000cs_is_paying() ) {
		return;
	}

	$monthly  = f2000cs_get_pro_price_monthly();
	$yearly   = f2000cs_get_pro_price_yearly();
	$lifetime = f2000cs_get_pro_price_lifetime();
	$url      = f2000cs_get_upgrade_url();
	$on_trial = f2000cs_is_pro();

	$cta_monthly  = $on_trial
		? __( 'Залишитись · місяць', 'factorial2000-catalog-sync' )
		: __( 'Оформити на місяць', 'factorial2000-catalog-sync' );
	$cta_yearly   = $on_trial
		? __( 'Залишитись · рік', 'factorial2000-catalog-sync' )
		: __( 'Оформити на рік', 'factorial2000-catalog-sync' );
	$cta_lifetime = $on_trial
		? __( 'Залишитись · назавжди', 'factorial2000-catalog-sync' )
		: __( 'Купити назавжди', 'factorial2000-catalog-sync' );

	$pro_features = array(
		__( 'Необмежено постачальників', 'factorial2000-catalog-sync' ),
		__( 'Оновлення хоч що 5 хвилин', 'factorial2000-catalog-sync' ),
		__( 'Коригування цін (націнка / маржа)', 'factorial2000-catalog-sync' ),
		__( 'Налаштування вигрузки + variable з малою наявністю', 'factorial2000-catalog-sync' ),
		__( 'і не тільки...', 'factorial2000-catalog-sync' ),
	);
	?>
	<div id="f2000cs-pro-plans" class="f2000cs-pro-plans">
		<div class="f2000cs-pro-plans__intro">
			<strong><?php esc_html_e( 'Тарифи', 'factorial2000-catalog-sync' ); ?></strong>
			<?php if ( ! $compact ) : ?>
				<span><?php esc_html_e( 'Оберіть план під свій каталог. Pro можна спробувати 14 днів без карти.', 'factorial2000-catalog-sync' ); ?></span>
			<?php else : ?>
				<span><?php esc_html_e( 'Тріал 14 днів без карти — повний Pro.', 'factorial2000-catalog-sync' ); ?></span>
			<?php endif; ?>
		</div>
		<div class="f2000cs-pro-plans__grid f2000cs-pro-plans__grid--4">
			<div class="f2000cs-pro-plan f2000cs-pro-plan--free">
				<div class="f2000cs-pro-plan__name"><?php esc_html_e( 'Free', 'factorial2000-catalog-sync' ); ?></div>
				<div class="f2000cs-pro-plan__price">$0</div>
				<ul class="f2000cs-pro-plan__features">
					<li><?php esc_html_e( '1 постачальник (+ уже збережені під час тріалу)', 'factorial2000-catalog-sync' ); ?></li>
					<li><?php esc_html_e( 'Оновлення наявності 3 рази на добу', 'factorial2000-catalog-sync' ); ?></li>
					<li class="is-muted"><?php esc_html_e( 'Без коригування цін', 'factorial2000-catalog-sync' ); ?></li>
					<li class="is-muted"><?php esc_html_e( 'Без налаштування вигрузки', 'factorial2000-catalog-sync' ); ?></li>
				</ul>
				<span class="button disabled f2000cs-pro-plan__cta">
					<?php echo $on_trial ? esc_html__( 'Після тріалу', 'factorial2000-catalog-sync' ) : esc_html__( 'Поточний план', 'factorial2000-catalog-sync' ); ?>
				</span>
			</div>

			<div class="f2000cs-pro-plan f2000cs-pro-plan--pro">
				<div class="f2000cs-pro-plan__name"><?php esc_html_e( 'Місячний', 'factorial2000-catalog-sync' ); ?></div>
				<div class="f2000cs-pro-plan__price">
					<span class="f2000cs-pro-plan__amount"><?php echo esc_html( $monthly ); ?></span>
					<span class="f2000cs-pro-plan__period"><?php esc_html_e( '/ міс', 'factorial2000-catalog-sync' ); ?></span>
				</div>
				<ul class="f2000cs-pro-plan__features">
					<?php foreach ( $pro_features as $feature ) : ?>
						<li><?php echo esc_html( $feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<a class="button button-primary f2000cs-pro-plan__cta" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta_monthly ); ?></a>
			</div>

			<div class="f2000cs-pro-plan f2000cs-pro-plan--pro f2000cs-pro-plan--featured">
				<div class="f2000cs-pro-plan__badge"><?php esc_html_e( 'Рекомендуємо', 'factorial2000-catalog-sync' ); ?></div>
				<div class="f2000cs-pro-plan__name"><?php esc_html_e( 'Річний', 'factorial2000-catalog-sync' ); ?></div>
				<div class="f2000cs-pro-plan__price">
					<span class="f2000cs-pro-plan__amount"><?php echo esc_html( $yearly ); ?></span>
					<span class="f2000cs-pro-plan__period"><?php esc_html_e( '/ рік', 'factorial2000-catalog-sync' ); ?></span>
				</div>
				<ul class="f2000cs-pro-plan__features">
					<?php foreach ( $pro_features as $feature ) : ?>
						<li><?php echo esc_html( $feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<a class="button button-primary f2000cs-pro-plan__cta" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta_yearly ); ?></a>
			</div>

			<div class="f2000cs-pro-plan f2000cs-pro-plan--pro">
				<div class="f2000cs-pro-plan__name"><?php esc_html_e( 'Назавжди', 'factorial2000-catalog-sync' ); ?></div>
				<div class="f2000cs-pro-plan__price">
					<span class="f2000cs-pro-plan__amount"><?php echo esc_html( $lifetime ); ?></span>
					<span class="f2000cs-pro-plan__period"><?php esc_html_e( 'один раз', 'factorial2000-catalog-sync' ); ?></span>
				</div>
				<ul class="f2000cs-pro-plan__features">
					<?php foreach ( $pro_features as $feature ) : ?>
						<li><?php echo esc_html( $feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<a class="button button-primary f2000cs-pro-plan__cta" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta_lifetime ); ?></a>
			</div>
		</div>
		<p class="f2000cs-pro-plans__refund">
			<strong><?php esc_html_e( 'Гарантія повернення 7 днів', 'factorial2000-catalog-sync' ); ?></strong>
			—
			<?php esc_html_e( 'гнучка «подвійна гарантія»: без ризику, без зайвих питань.', 'factorial2000-catalog-sync' ); ?>
		</p>
		<p class="f2000cs-pro-plans__upgrade">
			<strong><?php esc_html_e( 'Апгрейд без втрати грошей', 'factorial2000-catalog-sync' ); ?></strong>
			—
			<?php esc_html_e( 'при переході на вищий тариф уже сплачене зараховується — доплачуєте лише різницю.', 'factorial2000-catalog-sync' ); ?>
		</p>
	</div>
	<?php
}
