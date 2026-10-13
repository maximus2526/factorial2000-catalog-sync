<?php
/**
 * Settings API registration and field render callbacks.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

function f2000cs_settings_init() {
	$url_args        = array( 'sanitize_callback' => 'esc_url_raw' );
	$text_args       = array( 'sanitize_callback' => 'sanitize_text_field' );
	$skip_price_args = array(
		'sanitize_callback' => function ( $value ) {
			return ( $value === '1' || $value === 'yes' || $value === 'on' ) ? '1' : '0';
		},
	);

	$price_adjust_type_args      = array(
		'sanitize_callback' => function ( $value ) {
			return in_array( $value, array( 'margin', 'markup', 'fixed' ), true ) ? $value : 'markup';
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

	$slot_max = function_exists( 'f2000cs_get_supplier_option_register_max' )
		? f2000cs_get_supplier_option_register_max()
		: 5;

	for ( $i = 2; $i <= $slot_max; $i++ ) {
		register_setting( 'f2000cs_settings', 'f2000cs_url_' . $i, $url_args );
		register_setting( 'f2000cs_settings', 'f2000cs_sku_prefix_' . $i, $text_args );
		register_setting( 'f2000cs_settings', 'f2000cs_skip_price_' . $i, $skip_price_args );
	}

	for ( $i = 1; $i <= $slot_max; $i++ ) {
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

				// Without Pro the interval is not editable (the field is not posted): keep the saved one for a return to Pro.
				if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
					return (string) get_option( 'f2000cs_update_interval', 'hourly' );
				}

				if ( function_exists( 'f2000cs_get_effective_update_interval' ) ) {
					return f2000cs_get_effective_update_interval( $value );
				}
				$allowed = array( '5_minute', '10_minute', '15_minute', '30_minute', 'hourly', '8_times_daily', '6_times_daily', '4_times_daily', 'twicedaily', 'daily' );
				return in_array( $value, $allowed, true ) ? $value : 'hourly';
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
	register_setting(
		'f2000cs_settings',
		'f2000cs_allow_insecure_ssl',
		array(
			'sanitize_callback' => function ( $value ) {
				return ( $value === '1' || $value === 'yes' || $value === 'on' ) ? '1' : '0';
			},
		)
	);
	register_setting( 'f2000cs_settings', 'f2000cs_telegram_user_ids', $text_args );
	register_setting( 'f2000cs_settings', 'f2000cs_telegram_token_id', $text_args );

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

	/**
	 * Register extra fields of the «Розклад і правила» section (Pro build).
	 */
	do_action( 'f2000cs_register_schedule_fields' );

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
	add_settings_field(
		'f2000cs_allow_insecure_ssl',
		f2000cs_with_tip(
			__( 'Слабкі SSL-сертифікати', 'factorial2000-catalog-sync' ),
			__( 'Вимикає перевірку SSL-сертифікатів при завантаженні XML і зображень. Вмикайте ТІЛЬКИ якщо сервер постачальника використовує self-signed або прострочений сертифікат і завантаження не працює. За замовчуванням перевірку ввімкнено (безпечно).', 'factorial2000-catalog-sync' )
		),
		'f2000cs_allow_insecure_ssl_render',
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
		$url_name = 'f2000cs_url___INDEX__';
		$url_id   = 'f2000cs_url___INDEX__';
		$url      = '';
		$prefix   = '';
		$skip     = '0';
	} elseif ( $is_first ) {
		$url_name = 'f2000cs_url';
		$url_id   = 'f2000cs_url';
		$url      = get_option( 'f2000cs_url', '' );
		$prefix   = get_option( 'f2000cs_sku_prefix_1', '' );
		$skip     = get_option( 'f2000cs_skip_price_1', '0' );
	} else {
		$url_name = 'f2000cs_url_' . $num;
		$url_id   = 'f2000cs_url_' . $num;
		$url      = get_option( 'f2000cs_url_' . $num, '' );
		$prefix   = get_option( 'f2000cs_sku_prefix_' . $num, '' );
		$skip     = get_option( 'f2000cs_skip_price_' . $num, '0' );
	}

	$prefix_name = $is_tpl ? 'f2000cs_sku_prefix___INDEX__' : 'f2000cs_sku_prefix_' . $num;
	$prefix_id   = $prefix_name;
	$skip_name   = $is_tpl ? 'f2000cs_skip_price___INDEX__' : 'f2000cs_skip_price_' . $num;
	$skip_id     = $skip_name;

	$card_cls = 'f2000cs-supplier-card' . ( $is_first ? ' f2000cs-supplier-card--primary' : '' );
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

			<?php f2000cs_render_supplier_qty_field( $slot ); ?>
		</div>

		<?php f2000cs_render_supplier_price_adjust( $slot ); ?>
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
 * Toggle SSL certificate verification for supplier downloads.
 *
 * @return void
 */
function f2000cs_allow_insecure_ssl_render() {
	$val = get_option( 'f2000cs_allow_insecure_ssl', '0' );
	?>
	<input type="hidden" name="f2000cs_allow_insecure_ssl" value="0">
	<label>
		<input type="checkbox" name="f2000cs_allow_insecure_ssl" value="1" <?php checked( $val, '1' ); ?>>
		<?php esc_html_e( 'Не перевіряти SSL-сертифікати при завантаженні XML і зображень', 'factorial2000-catalog-sync' ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'За замовчуванням вимкнено: сертифікати перевіряються. Увімкніть лише якщо сервер постачальника має self-signed або прострочений сертифікат і завантаження повертає помилку SSL.', 'factorial2000-catalog-sync' ); ?></p>
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

/**
 * «Коригування ціни» block of a supplier card.
 *
 * @param string $slot Slot number, or __INDEX__ for the JS template.
 * @return void
 */
function f2000cs_render_supplier_price_adjust( $slot ) {
	$num         = absint( $slot );
	$type        = $num ? get_option( 'f2000cs_price_adjust_type_' . $num, 'markup' ) : 'markup';
	$direction   = $num ? get_option( 'f2000cs_price_adjust_direction_' . $num, 'add' ) : 'add';
	$value       = $num ? get_option( 'f2000cs_price_adjust_value_' . $num, '0' ) : '0';
	$type_name   = 'f2000cs_price_adjust_type_' . $slot;
	$dir_name    = 'f2000cs_price_adjust_direction_' . $slot;
	$val_name    = 'f2000cs_price_adjust_value_' . $slot;
	$wrap_id     = 'f2000cs_price_adjust_' . $slot . '_wrap';
	$dir_wrap_id = 'f2000cs_price_adjust_direction_' . $slot . '_wrap';
	$unit_id     = 'f2000cs_price_adjust_unit_' . $slot;
	$currency    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
	?>
	<div class="f2000cs-supplier-card__field f2000cs-supplier-card__price">
		<label>
			<?php esc_html_e( 'Коригування ціни', 'factorial2000-catalog-sync' ); ?>
			<?php
			echo f2000cs_tip_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
				__( 'Націнка — % від ціни постачальника. Маржа — бажаний прибуток у % від кінцевої ціни. Фіксована сума — додати або відняти грн/валюту.', 'factorial2000-catalog-sync' )
			);
			?>
		</label>
		<div id="<?php echo esc_attr( $wrap_id ); ?>" class="f2000cs-price-adjust" data-percent-unit="%" data-currency-unit="<?php echo esc_attr( $currency ); ?>">
			<select name="<?php echo esc_attr( $type_name ); ?>" id="<?php echo esc_attr( $type_name ); ?>" class="f2000cs-price-adjust__type">
				<option value="markup" <?php selected( $type, 'markup' ); ?>><?php esc_html_e( 'Націнка (%)', 'factorial2000-catalog-sync' ); ?></option>
				<option value="margin" <?php selected( $type, 'margin' ); ?>><?php esc_html_e( 'Маржа (%)', 'factorial2000-catalog-sync' ); ?></option>
				<option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Фіксована сума', 'factorial2000-catalog-sync' ); ?></option>
			</select>
			<span class="f2000cs-price-adjust__direction" id="<?php echo esc_attr( $dir_wrap_id ); ?>">
				<label>
					<input type="radio" name="<?php echo esc_attr( $dir_name ); ?>" value="add" <?php checked( $direction, 'add' ); ?>>
					<?php esc_html_e( 'Додати', 'factorial2000-catalog-sync' ); ?>
				</label>
				<label>
					<input type="radio" name="<?php echo esc_attr( $dir_name ); ?>" value="subtract" <?php checked( $direction, 'subtract' ); ?>>
					<?php esc_html_e( 'Відняти', 'factorial2000-catalog-sync' ); ?>
				</label>
			</span>
			<input type="number" name="<?php echo esc_attr( $val_name ); ?>" id="<?php echo esc_attr( $val_name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" step="0.01" style="width: 100px;">
			<span class="f2000cs-price-adjust__unit" id="<?php echo esc_attr( $unit_id ); ?>"><?php echo esc_html( 'fixed' === $type ? $currency : '%' ); ?></span>
		</div>
	</div>
	<?php
}
