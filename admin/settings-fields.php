<?php
/**
 * Settings API registration and field render callbacks.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

function f2000cs_settings_init() {
	$url_args                    = array( 'sanitize_callback' => 'esc_url_raw' );
	$text_args                   = array( 'sanitize_callback' => 'sanitize_text_field' );
	$skip_price_args             = array(
		'sanitize_callback' => function ( $value ) {
			return ( $value === '1' || $value === 'yes' || $value === 'on' ) ? '1' : '0';
		},
	);
	$price_adjust_type_args      = array(
		'sanitize_callback' => function ( $value ) {
			$allowed = array( 'margin', 'markup', 'fixed' );
			return in_array( $value, $allowed, true ) ? $value : 'markup';
		},
	);
	$price_adjust_direction_args = array(
		'sanitize_callback' => function ( $value ) {
			return 'subtract' === $value ? 'subtract' : 'add';
		},
	);
	$price_adjust_value_args     = array(
		'sanitize_callback' => function ( $value ) {
			$value = is_numeric( $value ) ? (float) $value : 0;
			$value = max( 0, min( 1000000, $value ) );
			return (string) round( $value, 2 );
		},
	);

	register_setting( 'f2000cs_settings', 'f2000cs_url', $url_args );
	register_setting( 'f2000cs_settings', 'f2000cs_sku_prefix_1', $text_args );
	register_setting( 'f2000cs_settings', 'f2000cs_skip_price_1', $skip_price_args );
	register_setting( 'f2000cs_settings', 'f2000cs_update_stock_qty_1', $skip_price_args );
	register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_type_1', $price_adjust_type_args );
	register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_direction_1', $price_adjust_direction_args );
	register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_value_1', $price_adjust_value_args );

	$slot_max = function_exists( 'f2000cs_get_supplier_option_register_max' )
		? f2000cs_get_supplier_option_register_max()
		: 5;

	for ( $i = 2; $i <= $slot_max; $i++ ) {
		register_setting( 'f2000cs_settings', 'f2000cs_url_' . $i, $url_args );
		register_setting( 'f2000cs_settings', 'f2000cs_sku_prefix_' . $i, $text_args );
		register_setting( 'f2000cs_settings', 'f2000cs_skip_price_' . $i, $skip_price_args );
		register_setting( 'f2000cs_settings', 'f2000cs_update_stock_qty_' . $i, $skip_price_args );
		register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_type_' . $i, $price_adjust_type_args );
		register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_direction_' . $i, $price_adjust_direction_args );
		register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_value_' . $i, $price_adjust_value_args );
	}

	register_setting(
		'f2000cs_settings',
		'f2000cs_update_interval',
		array(
			'sanitize_callback' => function ( $value ) {
				$value = sanitize_text_field( (string) $value );
				if ( function_exists( 'f2000cs_get_effective_update_interval' ) ) {
					return f2000cs_get_effective_update_interval( $value );
				}
				$allowed = array( '5_minute', '10_minute', '15_minute', '30_minute', 'hourly', 'twicedaily', 'daily' );
				return in_array( $value, $allowed, true ) ? $value : 'hourly';
			},
		)
	);
	register_setting(
		'f2000cs_settings',
		'f2000cs_hide_variable_low_instock',
		array(
			'sanitize_callback' => function ( $value ) {
				return ( $value === '1' || $value === 'yes' || $value === 'on' ) ? '1' : '0';
			},
		)
	);
	register_setting(
		'f2000cs_settings',
		'f2000cs_variable_low_instock_max',
		array(
			'sanitize_callback' => function ( $value ) {
				return (string) max( 0, absint( $value ) );
			},
		)
	);
	register_setting(
		'f2000cs_settings',
		'f2000cs_show_vendor_code',
		array(
			'sanitize_callback' => function ( $value ) {
				return ( $value === '1' || $value === 'yes' || $value === 'on' ) ? '1' : '0';
			},
		)
	);
	// Image options are saved from the Import page (group f2000cs_import_images).
	register_setting(
		'f2000cs_import_images',
		\F2000CS\Image_Processor::OPTION_PNG_CONVERT,
		array(
			'sanitize_callback' => array( '\F2000CS\Image_Processor', 'sanitize_png_convert' ),
		)
	);
	register_setting(
		'f2000cs_import_images',
		\F2000CS\Image_Processor::OPTION_OPTIMIZE,
		array(
			'sanitize_callback' => array( '\F2000CS\Image_Processor', 'sanitize_optimize' ),
		)
	);
	register_setting(
		'f2000cs_import_images',
		\F2000CS\Image_Processor::OPTION_QUALITY,
		array(
			'sanitize_callback' => function ( $value ) {
				return (string) \F2000CS\Image_Processor::sanitize_quality( $value );
			},
		)
	);
	register_setting(
		'f2000cs_import_images',
		\F2000CS\Image_Processor::OPTION_MAX_DIMENSION,
		array(
			'sanitize_callback' => function ( $value ) {
				return (string) \F2000CS\Image_Processor::sanitize_max_dimension( $value );
			},
		)
	);
	register_setting( 'f2000cs_settings', 'f2000cs_telegram_user_ids', $text_args );
	register_setting( 'f2000cs_settings', 'f2000cs_telegram_token_id', $text_args );

	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;

	add_settings_section(
		'f2000cs_section_suppliers',
		__( 'Постачальники', 'factorial2000-catalog-sync' ),
		'f2000cs_section_suppliers_intro',
		'f2000cs'
	);
	add_settings_field( 'f2000cs_suppliers', '', 'f2000cs_suppliers_render', 'f2000cs', 'f2000cs_section_suppliers' );

	add_settings_section(
		'f2000cs_section_schedule',
		__( 'Розклад і правила', 'factorial2000-catalog-sync' ),
		'f2000cs_section_schedule_intro',
		'f2000cs'
	);
	add_settings_field(
		'f2000cs_update_interval',
		f2000cs_with_tip(
			__( 'Інтервал оновлення', 'factorial2000-catalog-sync' ),
			__( 'Як часто WordPress автоматично оновлюватиме наявність і ціни з XML-файлів постачальників.', 'factorial2000-catalog-sync' )
		),
		'f2000cs_interval_render',
		'f2000cs',
		'f2000cs_section_schedule'
	);
	add_settings_field(
		'f2000cs_hide_variable_low_instock',
		f2000cs_with_tip(
			__( 'Мала наявність варіацій', 'factorial2000-catalog-sync' ),
			__( 'Після sync ховає variable-товари, у яких лишилось замало варіацій «в наявності» (наприклад, майже порожня сітка розмірів). Корисно, щоб такі товари не крутились у рекламі.', 'factorial2000-catalog-sync' )
		) . ( $is_pro ? '' : ' (Pro)' ),
		'f2000cs_hide_variable_low_instock_render',
		'f2000cs',
		'f2000cs_section_schedule'
	);
	add_settings_field(
		'f2000cs_variable_low_instock_max',
		f2000cs_with_tip(
			__( 'Поріг варіацій', 'factorial2000-catalog-sync' ),
			__( 'Скільки варіацій «в наявності» ще вважається «мало». Наприклад 2: якщо лишилось 0–2 розміри — батьківський товар стає «немає в наявності».', 'factorial2000-catalog-sync' )
		) . ( $is_pro ? '' : ' (Pro)' ),
		'f2000cs_variable_low_instock_max_render',
		'f2000cs',
		'f2000cs_section_schedule'
	);
	add_settings_field(
		'f2000cs_show_vendor_code',
		f2000cs_with_tip(
			__( 'Vendor Code на товарі', 'factorial2000-catalog-sync' ),
			__( 'Показує адміністратору на сторінці товару блок vendorCode (з XML) для швидкого копіювання. Відвідувачі сайту його не бачать.', 'factorial2000-catalog-sync' )
		),
		'f2000cs_show_vendor_code_render',
		'f2000cs',
		'f2000cs_section_schedule'
	);

	add_settings_section(
		'f2000cs_section_telegram',
		__( 'Telegram-сповіщення', 'factorial2000-catalog-sync' ),
		'f2000cs_section_telegram_intro',
		'f2000cs'
	);
	add_settings_field(
		'f2000cs_telegram_user_ids',
		f2000cs_with_tip(
			__( 'ID користувачів', 'factorial2000-catalog-sync' ),
			__( 'Числові ID чатів Telegram, куди слати сповіщення. Свій ID можна дізнатись у @userinfobot; спершу напишіть боту /start.', 'factorial2000-catalog-sync' )
		),
		'f2000cs_telegram_user_ids_render',
		'f2000cs',
		'f2000cs_section_telegram'
	);
	add_settings_field(
		'f2000cs_telegram_token_id',
		f2000cs_with_tip(
			__( 'Токен бота', 'factorial2000-catalog-sync' ),
			__( 'Секретний ключ бота з @BotFather (вигляд 123456:ABC...). Без токена повідомлення не надсилаються.', 'factorial2000-catalog-sync' )
		),
		'f2000cs_telegram_token_id_render',
		'f2000cs',
		'f2000cs_section_telegram'
	);
}
add_action( 'admin_init', 'f2000cs_settings_init' );

/**
 * Label + CSS help tooltip (safe for Settings API field titles).
 *
 * @param string $label Plain label text.
 * @param string $tip   Tooltip description.
 * @return string HTML.
 */
function f2000cs_with_tip( $label, $tip ) {
	return sprintf(
		'<span class="f2000cs-label-with-tip">%1$s %2$s</span>',
		esc_html( $label ),
		f2000cs_tip_html( $tip )
	);
}

/**
 * Standalone tip control (pure CSS via data-tip).
 *
 * @param string $tip Tooltip text.
 * @return string HTML.
 */
function f2000cs_tip_html( $tip ) {
	$tip = (string) $tip;
	if ( '' === $tip ) {
		return '';
	}

	return sprintf(
		'<button type="button" class="f2000cs-tip" data-tip="%1$s" aria-label="%1$s"><span class="f2000cs-tip__mark" aria-hidden="true">?</span></button>',
		esc_attr( $tip )
	);
}

/**
 * Intro under «Постачальники».
 *
 * @return void
 */
function f2000cs_section_suppliers_intro() {
	echo '<p class="description">' . esc_html__( 'XML-посилання для оновлення наявності та цін. Кожен постачальник може мати свій SKU-префікс і правила цін.', 'factorial2000-catalog-sync' ) . '</p>';
}

/**
 * Intro under «Розклад і правила».
 *
 * @return void
 */
function f2000cs_section_schedule_intro() {
	echo '<p class="description">' . esc_html__( 'Автоматичне оновлення за cron та поведінка варіативних товарів після sync.', 'factorial2000-catalog-sync' ) . '</p>';
}

/**
 * Intro under Telegram section.
 *
 * @return void
 */
function f2000cs_section_telegram_intro() {
	echo '<p class="description">' . esc_html__( 'Отримуйте повідомлення про завершення оновлення наявності, імпорту та інші службові події.', 'factorial2000-catalog-sync' ) . '</p>';
}

/**
 * Render settings sections as styled panels (instead of bare WP h2 + table).
 *
 * @param string $page Settings page slug.
 * @return void
 */
function f2000cs_do_settings_panels( $page ) {
	global $wp_settings_sections, $wp_settings_fields;

	if ( empty( $wp_settings_sections[ $page ] ) ) {
		return;
	}

	foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
		$section_id = isset( $section['id'] ) ? (string) $section['id'] : '';
		echo '<section class="f2000cs-settings-panel" id="' . esc_attr( $section_id ) . '">';

		if ( ! empty( $section['title'] ) ) {
			echo '<header class="f2000cs-settings-panel__head"><h2 class="f2000cs-settings-panel__title">' . esc_html( $section['title'] ) . '</h2></header>';
		}

		if ( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
			echo '<div class="f2000cs-settings-panel__intro">';
			call_user_func( $section['callback'], $section );
			echo '</div>';
		}

		if ( ! empty( $wp_settings_fields[ $page ][ $section_id ] ) ) {
			$fullbleed = ( 'f2000cs_section_suppliers' === $section_id ) ? ' f2000cs-settings-panel__body--full' : '';
			echo '<div class="f2000cs-settings-panel__body' . esc_attr( $fullbleed ) . '">';
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( $page, $section_id );
			echo '</table></div>';
		}

		echo '</section>';
	}
}

/**
 * Repeater for suppliers. Slot 1 uses f2000cs_url; extras use f2000cs_url_N (N >= 2).
 * Pro/trial: unlimited Add. Free: slot 1 + already-saved extras only.
 *
 * @return void
 */
function f2000cs_suppliers_render() {
	$is_pro        = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	$can_add       = function_exists( 'f2000cs_can_add_supplier_slot' ) ? f2000cs_can_add_supplier_slot() : $is_pro;
	$visible_slots = function_exists( 'f2000cs_get_visible_supplier_slots' ) ? f2000cs_get_visible_supplier_slots() : array( 1 );
	$currency      = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
	$scan_max      = defined( 'F2000CS_SUPPLIER_SLOT_SCAN_MAX' ) ? (int) F2000CS_SUPPLIER_SLOT_SCAN_MAX : 200;
	?>
	<div
		id="f2000cs-suppliers"
		class="f2000cs-extra-suppliers f2000cs-suppliers"
		data-is-pro="<?php echo $is_pro ? '1' : '0'; ?>"
		data-can-add="<?php echo $can_add ? '1' : '0'; ?>"
		data-scan-max="<?php echo esc_attr( (string) $scan_max ); ?>"
		data-currency="<?php echo esc_attr( $currency ); ?>"
		<?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>
	>
		<p class="description f2000cs-extra-suppliers__intro">
			<?php if ( ! $can_add ) : ?>
				<?php esc_html_e( 'Щоб додати ще постачальників — потрібна Pro-версія.', 'factorial2000-catalog-sync' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Можна додати кілька XML-джерел. Слот 1 — основний постачальник.', 'factorial2000-catalog-sync' ); ?>
			<?php endif; ?>
		</p>

		<div class="f2000cs-extra-suppliers__list" id="f2000cs-suppliers-list">
			<?php foreach ( $visible_slots as $slot ) : ?>
				<?php f2000cs_render_supplier_card( (int) $slot ); ?>
			<?php endforeach; ?>
		</div>

		<p class="f2000cs-extra-suppliers__actions">
			<?php if ( $can_add ) : ?>
				<button type="button" class="button f2000cs-extra-suppliers__add" id="f2000cs-add-supplier">
					<?php esc_html_e( 'Додати постачальника', 'factorial2000-catalog-sync' ); ?>
				</button>
			<?php else : ?>
				<span
					class="f2000cs-add-supplier-lock"
					data-pro-tip="<?php echo esc_attr__( 'Доступно в Pro', 'factorial2000-catalog-sync' ); ?>"
				>
					<button
						type="button"
						class="button f2000cs-extra-suppliers__add"
						id="f2000cs-add-supplier"
						disabled
						aria-disabled="true"
					>
						<?php esc_html_e( 'Додати постачальника', 'factorial2000-catalog-sync' ); ?>
						<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
					</button>
				</span>
			<?php endif; ?>
		</p>

		<template id="f2000cs-supplier-card-template">
			<?php f2000cs_render_supplier_card( 0, true ); ?>
		</template>
	</div>
	<?php
}

/**
 * Render one supplier card. Slot 1 uses legacy option name f2000cs_url and has no Remove.
 *
 * @param int  $index       Slot index 1–5, or 0 for JS template placeholders (slots 2–5 only).
 * @param bool $as_template When true, use __INDEX__ placeholders in names/ids.
 * @return void
 */
function f2000cs_render_supplier_card( $index, $as_template = false ) {
	$is_tpl   = (bool) $as_template;
	$num      = $is_tpl ? 0 : absint( $index );
	$is_first = ( ! $is_tpl && 1 === $num );
	$slot     = $is_tpl ? '__INDEX__' : (string) $num;

	if ( $is_tpl ) {
		$url_name   = 'f2000cs_url___INDEX__';
		$url_id     = 'f2000cs_url___INDEX__';
		$url        = '';
		$prefix     = '';
		$skip       = '0';
		$update_qty = '0';
		$type       = 'markup';
		$direction  = 'add';
		$value      = '0';
	} elseif ( $is_first ) {
		$url_name   = 'f2000cs_url';
		$url_id     = 'f2000cs_url';
		$url        = get_option( 'f2000cs_url', '' );
		$prefix     = get_option( 'f2000cs_sku_prefix_1', '' );
		$skip       = get_option( 'f2000cs_skip_price_1', '0' );
		$update_qty = get_option( 'f2000cs_update_stock_qty_1', '0' );
		$type       = get_option( 'f2000cs_price_adjust_type_1', 'markup' );
		$direction  = get_option( 'f2000cs_price_adjust_direction_1', 'add' );
		$value      = get_option( 'f2000cs_price_adjust_value_1', '0' );
	} else {
		$url_name   = 'f2000cs_url_' . $num;
		$url_id     = 'f2000cs_url_' . $num;
		$url        = get_option( 'f2000cs_url_' . $num, '' );
		$prefix     = get_option( 'f2000cs_sku_prefix_' . $num, '' );
		$skip       = get_option( 'f2000cs_skip_price_' . $num, '0' );
		$update_qty = get_option( 'f2000cs_update_stock_qty_' . $num, '0' );
		$type       = get_option( 'f2000cs_price_adjust_type_' . $num, 'markup' );
		$direction  = get_option( 'f2000cs_price_adjust_direction_' . $num, 'add' );
		$value      = get_option( 'f2000cs_price_adjust_value_' . $num, '0' );
	}

	$prefix_name = $is_tpl ? 'f2000cs_sku_prefix___INDEX__' : 'f2000cs_sku_prefix_' . $num;
	$prefix_id   = $prefix_name;
	$skip_name   = $is_tpl ? 'f2000cs_skip_price___INDEX__' : 'f2000cs_skip_price_' . $num;
	$skip_id     = $skip_name;
	$qty_name    = $is_tpl ? 'f2000cs_update_stock_qty___INDEX__' : 'f2000cs_update_stock_qty_' . $num;
	$qty_id      = $qty_name;
	$type_name   = $is_tpl ? 'f2000cs_price_adjust_type___INDEX__' : 'f2000cs_price_adjust_type_' . $num;
	$dir_name    = $is_tpl ? 'f2000cs_price_adjust_direction___INDEX__' : 'f2000cs_price_adjust_direction_' . $num;
	$val_name    = $is_tpl ? 'f2000cs_price_adjust_value___INDEX__' : 'f2000cs_price_adjust_value_' . $num;
	$wrap_id     = $is_tpl ? 'f2000cs_price_adjust___INDEX___wrap' : 'f2000cs_price_adjust_' . $num . '_wrap';
	$dir_wrap_id = $is_tpl ? 'f2000cs_price_adjust_direction___INDEX___wrap' : 'f2000cs_price_adjust_direction_' . $num . '_wrap';
	$unit_id     = $is_tpl ? 'f2000cs_price_adjust_unit___INDEX__' : 'f2000cs_price_adjust_unit_' . $num;
	$type_id     = $type_name;
	$val_id      = $val_name;

	$currency  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
	$is_pro    = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	$price_cls = 'f2000cs-price-adjust' . ( $is_pro ? '' : ' f2000cs-pro-feature' );
	$card_cls  = 'f2000cs-supplier-card' . ( $is_first ? ' f2000cs-supplier-card--primary' : '' );
	?>
	<div class="<?php echo esc_attr( $card_cls ); ?>" data-slot="<?php echo esc_attr( $slot ); ?>" data-removable="<?php echo ( $is_tpl || ! $is_first ) ? '1' : '0'; ?>">
		<div class="f2000cs-supplier-card__header">
			<strong class="f2000cs-supplier-card__title">
				<?php
				printf(
					/* translators: %s: supplier slot number */
					esc_html__( 'Постачальник %s', 'factorial2000-catalog-sync' ),
					esc_html( $is_tpl ? '__INDEX__' : (string) $num )
				);
				?>
			</strong>
			<?php if ( $is_tpl || ! $is_first ) : ?>
				<button type="button" class="button-link-delete f2000cs-supplier-card__remove">
					<?php esc_html_e( 'Видалити', 'factorial2000-catalog-sync' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<div class="f2000cs-supplier-card__grid">
			<div class="f2000cs-supplier-card__field">
				<label for="<?php echo esc_attr( $url_id ); ?>"><?php esc_html_e( 'URL XML файлу', 'factorial2000-catalog-sync' ); ?></label>
				<input type="text" name="<?php echo esc_attr( $url_name ); ?>" id="<?php echo esc_attr( $url_id ); ?>" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/products.xml', 'factorial2000-catalog-sync' ); ?>">
			</div>

			<div class="f2000cs-supplier-card__field">
				<label for="<?php echo esc_attr( $prefix_id ); ?>">
					<?php esc_html_e( 'SKU Prefix', 'factorial2000-catalog-sync' ); ?>
					<?php
					echo f2000cs_tip_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
						__( 'Префікс додається до артикулів з XML, щоб товари різних постачальників не змішувались (наприклад NEW_123).', 'factorial2000-catalog-sync' )
					);
					?>
				</label>
				<input type="text" name="<?php echo esc_attr( $prefix_name ); ?>" id="<?php echo esc_attr( $prefix_id ); ?>" value="<?php echo esc_attr( $prefix ); ?>" placeholder="<?php echo esc_attr( $is_tpl ? 'XML__INDEX__' : ( 'XML' . $num . '_' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Префікс для SKU товарів з цього XML файлу.', 'factorial2000-catalog-sync' ); ?></p>
			</div>
		</div>

		<div class="f2000cs-supplier-card__checks">
			<div class="f2000cs-supplier-card__field">
				<input type="hidden" name="<?php echo esc_attr( $skip_name ); ?>" value="0">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $skip_name ); ?>" id="<?php echo esc_attr( $skip_id ); ?>" value="1" <?php checked( $skip, '1' ); ?>>
					<?php esc_html_e( 'Не змінювати ціни при оновленні цього постачальника', 'factorial2000-catalog-sync' ); ?>
				</label>
			</div>

			<div class="f2000cs-supplier-card__field<?php echo $is_pro ? '' : ' f2000cs-pro-feature'; ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
				<input type="hidden" name="<?php echo esc_attr( $qty_name ); ?>" value="0">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $qty_name ); ?>" id="<?php echo esc_attr( $qty_id ); ?>" value="1" <?php checked( $update_qty, '1' ); ?> <?php disabled( ! $is_pro ); ?>>
					<?php esc_html_e( 'Оновлювати кількість товарів', 'factorial2000-catalog-sync' ); ?>
					<?php
					echo f2000cs_tip_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
						__( 'Якщо в XML є quantity / stock_quantity — записує залишок у WooCommerce. Без цієї опції оновлюється лише «є / немає в наявності».', 'factorial2000-catalog-sync' )
					);
					?>
					<?php if ( ! $is_pro ) : ?>
						<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
					<?php endif; ?>
				</label>
				<p class="description"><?php esc_html_e( 'Якщо в XML є quantity / stock_quantity — записати її в WooCommerce.', 'factorial2000-catalog-sync' ); ?></p>
			</div>
		</div>

		<div class="f2000cs-supplier-card__field f2000cs-supplier-card__price">
			<label>
				<?php esc_html_e( 'Коригування ціни', 'factorial2000-catalog-sync' ); ?>
				<?php
				echo f2000cs_tip_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
					__( 'Націнка — % від ціни постачальника. Маржа — бажаний прибуток у % від кінцевої ціни. Фіксована сума — додати або відняти грн/валюту.', 'factorial2000-catalog-sync' )
				);
				?>
				<?php echo $is_pro ? '' : ' <span class="f2000cs-pro-badge">Pro</span>'; ?>
			</label>
			<div id="<?php echo esc_attr( $wrap_id ); ?>" class="<?php echo esc_attr( $price_cls ); ?>" data-percent-unit="%" data-currency-unit="<?php echo esc_attr( $currency ); ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
				<select name="<?php echo esc_attr( $type_name ); ?>" id="<?php echo esc_attr( $type_id ); ?>" class="f2000cs-price-adjust__type" <?php disabled( ! $is_pro ); ?>>
					<option value="markup" <?php selected( $type, 'markup' ); ?>><?php esc_html_e( 'Націнка (%)', 'factorial2000-catalog-sync' ); ?></option>
					<option value="margin" <?php selected( $type, 'margin' ); ?>><?php esc_html_e( 'Маржа (%)', 'factorial2000-catalog-sync' ); ?></option>
					<option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Фіксована сума', 'factorial2000-catalog-sync' ); ?></option>
				</select>
				<span class="f2000cs-price-adjust__direction" id="<?php echo esc_attr( $dir_wrap_id ); ?>">
					<label>
						<input type="radio" name="<?php echo esc_attr( $dir_name ); ?>" value="add" <?php checked( $direction, 'add' ); ?> <?php disabled( ! $is_pro ); ?>>
						<?php esc_html_e( 'Додати', 'factorial2000-catalog-sync' ); ?>
					</label>
					<label>
						<input type="radio" name="<?php echo esc_attr( $dir_name ); ?>" value="subtract" <?php checked( $direction, 'subtract' ); ?> <?php disabled( ! $is_pro ); ?>>
						<?php esc_html_e( 'Відняти', 'factorial2000-catalog-sync' ); ?>
					</label>
				</span>
				<input type="number" name="<?php echo esc_attr( $val_name ); ?>" id="<?php echo esc_attr( $val_id ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" step="0.01" style="width: 100px;" <?php disabled( ! $is_pro ); ?>>
				<span class="f2000cs-price-adjust__unit" id="<?php echo esc_attr( $unit_id ); ?>"><?php echo esc_html( 'fixed' === $type ? $currency : '%' ); ?></span>
			</div>
		</div>
	</div>
	<?php
}

function f2000cs_interval_render() {
	$interval = get_option( 'f2000cs_update_interval', 'hourly' );
	$is_pro   = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;

	if ( ! $is_pro ) {
		$interval = 'daily';
	}
	?>
	<select name="f2000cs_update_interval" <?php disabled( ! $is_pro ); ?>>
		<?php if ( $is_pro ) : ?>
			<option value="5_minute" <?php selected( $interval, '5_minute' ); ?>><?php esc_html_e( 'Що 5 хв', 'factorial2000-catalog-sync' ); ?></option>
			<option value="10_minute" <?php selected( $interval, '10_minute' ); ?>><?php esc_html_e( 'Що 10 хв', 'factorial2000-catalog-sync' ); ?></option>
			<option value="15_minute" <?php selected( $interval, '15_minute' ); ?>><?php esc_html_e( 'Що 15 хв', 'factorial2000-catalog-sync' ); ?></option>
			<option value="30_minute" <?php selected( $interval, '30_minute' ); ?>><?php esc_html_e( 'Що 30 хв', 'factorial2000-catalog-sync' ); ?></option>
			<option value="hourly" <?php selected( $interval, 'hourly' ); ?>><?php esc_html_e( 'Щогодини', 'factorial2000-catalog-sync' ); ?></option>
			<option value="twicedaily" <?php selected( $interval, 'twicedaily' ); ?>><?php esc_html_e( 'Двічі на день', 'factorial2000-catalog-sync' ); ?></option>
		<?php endif; ?>
		<option value="daily" <?php selected( $interval, 'daily' ); ?>><?php esc_html_e( 'Щодня', 'factorial2000-catalog-sync' ); ?></option>
	</select>
	<?php if ( ! $is_pro ) : ?>
		<input type="hidden" name="f2000cs_update_interval" value="daily">
		<p class="description">
			<?php esc_html_e( 'Free: автоматичне оновлення — раз на добу. Частіші інтервали — у Pro.', 'factorial2000-catalog-sync' ); ?>
			<a href="<?php echo esc_url( f2000cs_get_upgrade_url() ); ?>"><?php esc_html_e( 'Оформити Pro', 'factorial2000-catalog-sync' ); ?></a>
		</p>
	<?php endif; ?>
	<?php
}

function f2000cs_hide_variable_low_instock_render() {
	$val    = get_option( 'f2000cs_hide_variable_low_instock', '0' );
	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	?>
	<div class="<?php echo $is_pro ? '' : 'f2000cs-pro-feature'; ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
		<input type="hidden" name="f2000cs_hide_variable_low_instock" value="0">
		<label>
			<input type="checkbox" name="f2000cs_hide_variable_low_instock" value="1" <?php checked( $val, '1' ); ?> <?php disabled( ! $is_pro ); ?>>
			<?php esc_html_e( 'Після оновлення ставити variable-товари в «немає в наявності», якщо варіацій в наявності недостатньо', 'factorial2000-catalog-sync' ); ?>
			<?php if ( ! $is_pro ) : ?>
				<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
			<?php endif; ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Застосовується після завершення stock update. Корисна фіча для реклами: товари з малою кількістю розмірів у варіаціях не показуються як доступні, тож у рекламі не крутяться оголошення з майже порожньою розмірною сіткою.', 'factorial2000-catalog-sync' ); ?>
		</p>
	</div>
	<?php
}

function f2000cs_variable_low_instock_max_render() {
	$max    = get_option( 'f2000cs_variable_low_instock_max', 2 );
	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	?>
	<div class="<?php echo $is_pro ? '' : 'f2000cs-pro-feature'; ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
		<input type="number" name="f2000cs_variable_low_instock_max" value="<?php echo esc_attr( $max ); ?>" min="0" step="1" style="width: 80px;" <?php disabled( ! $is_pro ); ?>>
		<?php if ( ! $is_pro ) : ?>
			<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Максимальна кількість варіацій «в наявності» (включно), при якій батьківський товар буде позначено як «немає в наявності». За замовчуванням: 2. Допомагає прибрати з реклами variable-товари, у яких залишилось замало розмірів.', 'factorial2000-catalog-sync' ); ?></p>
	</div>
	<?php
}

/**
 * Toggle admin vendorCode panel on the single product page.
 *
 * @return void
 */
function f2000cs_show_vendor_code_render() {
	$val = get_option( 'f2000cs_show_vendor_code', '1' );
	?>
	<input type="hidden" name="f2000cs_show_vendor_code" value="0">
	<label>
		<input type="checkbox" name="f2000cs_show_vendor_code" value="1" <?php checked( $val, '1' ); ?>>
		<?php esc_html_e( 'Показувати vendorCode адміну на сторінці товару для копіювання', 'factorial2000-catalog-sync' ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'Якщо увімкнено — адміністратор (manage_options) бачить у підвалі сторінки товару код постачальника з XML і може скопіювати його кліком. Покупцям і звичайним користувачам блок не показується. За замовчуванням увімкнено.', 'factorial2000-catalog-sync' ); ?></p>
	<?php
}

/**
 * Import-page panel: image processing options + host capability check.
 *
 * @return void
 */
function f2000cs_render_import_images_panel() {
	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	$title  = __( 'Зображення при імпорті', 'factorial2000-catalog-sync' ) . ( $is_pro ? '' : ' (Pro)' );

	$rows = array(
		array(
			'label'    => __( 'Формат зображень', 'factorial2000-catalog-sync' ),
			'tip'      => __( 'Конвертує всі фото з тегів picture (PNG, JPG, WebP, AVIF, GIF) у вибраний формат перед збереженням у медіатеку. JPG сумісніший; WebP/AVIF легші, але потрібна підтримка на хостингу.', 'factorial2000-catalog-sync' ),
			'callback' => 'f2000cs_img_png_convert_render',
		),
		array(
			'label'    => __( 'Оптимізація', 'factorial2000-catalog-sync' ),
			'tip'      => __( 'Перезберігає JPG/WebP/AVIF/PNG з обраною якістю під час імпорту, щоб зменшити вагу файлів.', 'factorial2000-catalog-sync' ),
			'callback' => 'f2000cs_img_optimize_render',
		),
		array(
			'label'    => __( 'Якість', 'factorial2000-catalog-sync' ),
			'tip'      => __( 'Стиснення при оптимізації та конвертації зображень. Менше значення — легші файли, але гірша якість картинки.', 'factorial2000-catalog-sync' ),
			'callback' => 'f2000cs_img_quality_render',
		),
		array(
			'label'    => __( 'Макс. сторона', 'factorial2000-catalog-sync' ),
			'tip'      => __( 'Якщо ширина або висота фото більша за ліміт — зображення зменшується зі збереженням пропорцій. 0 = без обмеження.', 'factorial2000-catalog-sync' ),
			'callback' => 'f2000cs_img_max_dimension_render',
		),
		array(
			'label'    => __( 'Цей хостинг', 'factorial2000-catalog-sync' ),
			'tip'      => __( 'Що реально вміє ваш сервер для обробки зображень (розширення PHP Imagick/GD і підтримка форматів).', 'factorial2000-catalog-sync' ),
			'callback' => 'f2000cs_img_host_caps_render',
		),
	);
	?>
	<section id="f2000cs_images_section" class="f2000cs-settings-panel f2000cs-import-images">
		<header class="f2000cs-settings-panel__head">
			<h2 class="f2000cs-settings-panel__title"><?php echo esc_html( $title ); ?></h2>
		</header>
		<div class="f2000cs-settings-panel__intro">
			<p class="description"><?php esc_html_e( 'Застосовується лише до фото товарів із тегів picture під час імпорту / оновлення поля «Фото». Посилання на зображення в описі товарів не змінюються.', 'factorial2000-catalog-sync' ); ?></p>
		</div>
		<div class="f2000cs-settings-panel__body">
			<form method="post" action="options.php" class="f2000cs-import-images__form">
				<?php settings_fields( 'f2000cs_import_images' ); ?>
				<table class="form-table f2000cs-import-images__table" role="presentation">
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<th scope="row">
								<?php echo f2000cs_with_tip( $row['label'], $row['tip'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
							</th>
							<td>
								<?php
								if ( is_callable( $row['callback'] ) ) {
									call_user_func( $row['callback'] );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Зберегти налаштування зображень', 'factorial2000-catalog-sync' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	</section>
	<?php
}

/**
 * @return void
 */
function f2000cs_img_png_convert_render() {
	$val    = class_exists( '\F2000CS\Image_Processor' )
		? \F2000CS\Image_Processor::sanitize_png_convert( get_option( \F2000CS\Image_Processor::OPTION_PNG_CONVERT, 'off' ) )
		: 'off';
	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	?>
	<div id="f2000cs_img_png_convert" class="<?php echo $is_pro ? '' : 'f2000cs-pro-feature'; ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
		<select name="f2000cs_img_png_convert" <?php disabled( ! $is_pro ); ?>>
			<option value="off" <?php selected( $val, 'off' ); ?>><?php esc_html_e( 'Не конвертувати', 'factorial2000-catalog-sync' ); ?></option>
			<option value="webp" <?php selected( $val, 'webp' ); ?>><?php esc_html_e( 'У WebP', 'factorial2000-catalog-sync' ); ?></option>
			<option value="avif" <?php selected( $val, 'avif' ); ?>><?php esc_html_e( 'У AVIF', 'factorial2000-catalog-sync' ); ?></option>
			<option value="jpg" <?php selected( $val, 'jpg' ); ?>><?php esc_html_e( 'У JPG', 'factorial2000-catalog-sync' ); ?></option>
		</select>
		<?php if ( ! $is_pro ) : ?>
			<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Усі фото з picture (PNG, JPG, WebP, AVIF, GIF) конвертуються у вибраний формат перед збереженням у медіатеку. Працює через Imagick/GD WordPress: JPG майже всюди; WebP/AVIF — лише якщо хостинг їх підтримує. Якщо конвертація не вдалась — лишається оригінал.', 'factorial2000-catalog-sync' ); ?></p>
	</div>
	<?php
}

/**
 * @return void
 */
function f2000cs_img_optimize_render() {
	$val    = get_option( \F2000CS\Image_Processor::OPTION_OPTIMIZE, '0' );
	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	?>
	<div id="f2000cs_img_optimize" class="<?php echo $is_pro ? '' : 'f2000cs-pro-feature'; ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
		<input type="hidden" name="f2000cs_img_optimize" value="0">
		<label>
			<input type="checkbox" name="f2000cs_img_optimize" value="1" <?php checked( $val, '1' ); ?> <?php disabled( ! $is_pro ); ?>>
			<?php esc_html_e( 'Оптимізувати зображення через редактор WordPress (Imagick / GD)', 'factorial2000-catalog-sync' ); ?>
			<?php if ( ! $is_pro ) : ?>
				<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
			<?php endif; ?>
		</label>
		<p class="description"><?php esc_html_e( 'Перезберігає JPG/WebP/AVIF/PNG з обраною якістю під час імпорту.', 'factorial2000-catalog-sync' ); ?></p>
	</div>
	<?php
}

/**
 * @return void
 */
function f2000cs_img_quality_render() {
	$val    = class_exists( '\F2000CS\Image_Processor' )
		? \F2000CS\Image_Processor::sanitize_quality( get_option( \F2000CS\Image_Processor::OPTION_QUALITY, \F2000CS\Image_Processor::DEFAULT_QUALITY ) )
		: 82;
	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	?>
	<div id="f2000cs_img_quality" class="<?php echo $is_pro ? '' : 'f2000cs-pro-feature'; ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
		<label class="f2000cs-img-quality">
			<input
				type="range"
				name="f2000cs_img_quality"
				id="f2000cs_img_quality_range"
				min="<?php echo esc_attr( (string) \F2000CS\Image_Processor::MIN_QUALITY ); ?>"
				max="<?php echo esc_attr( (string) \F2000CS\Image_Processor::MAX_QUALITY ); ?>"
				step="1"
				value="<?php echo esc_attr( (string) $val ); ?>"
				<?php disabled( ! $is_pro ); ?>
			>
			<span id="f2000cs_img_quality_value" class="f2000cs-img-quality__value"><?php echo esc_html( (string) $val ); ?></span>
		</label>
		<?php if ( ! $is_pro ) : ?>
			<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Якість для оптимізації та конвертації зображень (40–100).', 'factorial2000-catalog-sync' ); ?></p>
	</div>
	<?php
}

/**
 * @return void
 */
function f2000cs_img_max_dimension_render() {
	$val    = class_exists( '\F2000CS\Image_Processor' )
		? \F2000CS\Image_Processor::sanitize_max_dimension( get_option( \F2000CS\Image_Processor::OPTION_MAX_DIMENSION, 0 ) )
		: 0;
	$is_pro = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	$min    = (int) \F2000CS\Image_Processor::MIN_MAX_DIMENSION;
	$max    = (int) \F2000CS\Image_Processor::MAX_MAX_DIMENSION;
	?>
	<div id="f2000cs_img_max_dimension" class="<?php echo $is_pro ? '' : 'f2000cs-pro-feature'; ?>" <?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>>
		<div class="f2000cs-img-dimension">
			<input
				type="range"
				id="f2000cs_img_max_dimension_range"
				min="<?php echo esc_attr( (string) $min ); ?>"
				max="<?php echo esc_attr( (string) $max ); ?>"
				step="50"
				value="<?php echo esc_attr( (string) $val ); ?>"
				<?php disabled( ! $is_pro ); ?>
			>
			<span class="f2000cs-img-dimension__value">
				<input
					type="number"
					name="f2000cs_img_max_dimension"
					id="f2000cs_img_max_dimension_number"
					value="<?php echo esc_attr( (string) $val ); ?>"
					min="<?php echo esc_attr( (string) $min ); ?>"
					max="<?php echo esc_attr( (string) $max ); ?>"
					step="1"
					class="small-text"
					<?php disabled( ! $is_pro ); ?>
				>
				<span class="f2000cs-img-dimension__unit">px</span>
			</span>
		</div>
		<?php if ( ! $is_pro ) : ?>
			<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Якщо ширина або висота більша за ліміт — фото зменшується зі збереженням пропорцій. 0 = без обмеження.', 'factorial2000-catalog-sync' ); ?></p>
	</div>
	<?php
}

/**
 * Show which image features the current host can actually run.
 *
 * @return void
 */
function f2000cs_img_host_caps_render() {
	if ( ! class_exists( '\F2000CS\Image_Processor' ) ) {
		echo '<p class="description">' . esc_html__( 'Перевірка недоступна.', 'factorial2000-catalog-sync' ) . '</p>';
		return;
	}

	$caps = \F2000CS\Image_Processor::get_host_capabilities();

	$rows = array(
		array(
			'label' => 'Imagick',
			'tip'   => __( 'PHP-розширення ImageMagick. Зазвичай краще справляється з великими фото та WebP.', 'factorial2000-catalog-sync' ),
			'ok'    => ! empty( $caps['imagick'] ),
		),
		array(
			'label' => 'GD',
			'tip'   => __( 'Базове PHP-розширення для картинок. Є майже на всіх хостингах; WebP залежить від збірки PHP.', 'factorial2000-catalog-sync' ),
			'ok'    => ! empty( $caps['gd'] ),
		),
		array(
			'label' => __( 'Запис у JPG', 'factorial2000-catalog-sync' ),
			'tip'   => __( 'Чи вміє сервер зберігати зображення у JPEG через редактор WordPress.', 'factorial2000-catalog-sync' ),
			'ok'    => ! empty( $caps['jpeg'] ),
		),
		array(
			'label' => __( 'Запис у WebP', 'factorial2000-catalog-sync' ),
			'tip'   => __( 'Чи вміє сервер зберігати WebP. Якщо ні — оберіть JPG або AVIF (якщо доступний).', 'factorial2000-catalog-sync' ),
			'ok'    => ! empty( $caps['webp'] ),
		),
		array(
			'label' => __( 'Запис у AVIF', 'factorial2000-catalog-sync' ),
			'tip'   => __( 'Чи вміє сервер зберігати AVIF (зазвичай через Imagick / GD з libavif). Якщо ні — оберіть WebP або JPG.', 'factorial2000-catalog-sync' ),
			'ok'    => ! empty( $caps['avif'] ),
		),
		array(
			'label' => __( 'Зменшення / оптимізація', 'factorial2000-catalog-sync' ),
			'tip'   => __( 'Чи можна зменшувати розмір і перезберігати фото з обраною якістю.', 'factorial2000-catalog-sync' ),
			'ok'    => ! empty( $caps['resize'] ),
		),
	);
	?>
	<div class="f2000cs-img-host-caps">
		<p class="f2000cs-img-host-caps__title"><?php esc_html_e( 'Доступно на цьому хостингу', 'factorial2000-catalog-sync' ); ?></p>
		<ul class="f2000cs-img-host-caps__list">
			<?php foreach ( $rows as $row ) : ?>
				<li class="f2000cs-img-host-caps__item <?php echo ! empty( $row['ok'] ) ? 'is-ok' : 'is-fail'; ?>">
					<span class="f2000cs-img-host-caps__label">
						<?php echo esc_html( $row['label'] ); ?>
						<?php echo f2000cs_tip_html( $row['tip'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
					</span>
					<span class="f2000cs-img-host-caps__badge">
						<?php echo esc_html( ! empty( $row['ok'] ) ? __( 'так', 'factorial2000-catalog-sync' ) : __( 'ні', 'factorial2000-catalog-sync' ) ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( empty( $caps['imagick'] ) && empty( $caps['gd'] ) ) : ?>
			<p class="f2000cs-img-host-caps__note"><?php esc_html_e( 'Без Imagick або GD на сервері конвертація й оптимізація під час імпорту не працюватимуть.', 'factorial2000-catalog-sync' ); ?></p>
		<?php elseif ( empty( $caps['webp'] ) && empty( $caps['avif'] ) ) : ?>
			<p class="f2000cs-img-host-caps__note"><?php esc_html_e( 'WebP і AVIF на цьому сервері недоступні — для сумісності оберіть JPG.', 'factorial2000-catalog-sync' ); ?></p>
		<?php elseif ( empty( $caps['avif'] ) ) : ?>
			<p class="f2000cs-img-host-caps__note"><?php esc_html_e( 'AVIF на цьому сервері недоступний — оберіть WebP або JPG.', 'factorial2000-catalog-sync' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function f2000cs_telegram_user_ids_render() {
	$user_ids = get_option( 'f2000cs_telegram_user_ids', '' );
	?>
	<input type="text" name="f2000cs_telegram_user_ids" value="<?php echo esc_attr( $user_ids ); ?>" placeholder="<?php esc_attr_e( '123456789, 987654321', 'factorial2000-catalog-sync' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'ID чатів, куди надсилати сповіщення (через кому для кількох). Свій числовий ID дізнаєтесь у боті @userinfobot. Спершу напишіть своєму боту /start, інакше повідомлення не дійде.', 'factorial2000-catalog-sync' ); ?></p>
	<?php
}

function f2000cs_telegram_token_id_render() {
	$token_id = get_option( 'f2000cs_telegram_token_id', '' );
	?>
	<input type="text" name="f2000cs_telegram_token_id" value="<?php echo esc_attr( $token_id ); ?>" placeholder="<?php esc_attr_e( '1234567890:ABCdefGHIjklMNOpqrsTUVwxyz', 'factorial2000-catalog-sync' ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Токен бота отримаєте у @BotFather: надішліть /newbot, задайте ім\'я — і скопіюйте рядок виду 1234567890:ABCdef... Без токена Telegram-сповіщення не надсилаються.', 'factorial2000-catalog-sync' ); ?></p>
	<?php
}
