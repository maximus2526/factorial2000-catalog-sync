<?php
/**
 * Pro settings UI: supplier price adjustment and stock quantity fields, update
 * interval, variable low-instock rule, Import page stock quantity option and the
 * «Оновити окремі поля» tab with its AJAX handler.
 *
 * Premium-only file (listed in @fs_premium_only of the main plugin file). The
 * free build renders placeholders from admin/premium-upsell.php instead.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Pro options with the Settings API.
 *
 * @return void
 */
function f2000cs_register_pro_settings() {
	$flag_args                   = array(
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

	$slot_max = f2000cs_get_supplier_option_register_max();

	for ( $i = 1; $i <= $slot_max; $i++ ) {
		register_setting( 'f2000cs_settings', 'f2000cs_update_stock_qty_' . $i, $flag_args );
		register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_type_' . $i, $price_adjust_type_args );
		register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_direction_' . $i, $price_adjust_direction_args );
		register_setting( 'f2000cs_settings', 'f2000cs_price_adjust_value_' . $i, $price_adjust_value_args );
	}

	register_setting( 'f2000cs_settings', 'f2000cs_hide_variable_low_instock', $flag_args );
	register_setting(
		'f2000cs_settings',
		'f2000cs_variable_low_instock_max',
		array(
			'sanitize_callback' => function ( $value ) {
				// (int) and not absint(): a negative threshold must become 0, not its absolute value.
				return (string) max( 0, (int) $value );
			},
		)
	);
}
add_action( 'admin_init', 'f2000cs_register_pro_settings' );

/**
 * Variable low-instock rule fields in the «Розклад і правила» section.
 *
 * @return void
 */
function f2000cs_register_pro_schedule_fields() {
	$is_pro = f2000cs_is_pro();

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
}
add_action( 'f2000cs_register_schedule_fields', 'f2000cs_register_pro_schedule_fields' );

/**
 * «Оновлювати кількість товарів» checkbox of a supplier card.
 *
 * @param string $slot Slot number, or __INDEX__ for the JS template.
 * @return void
 */
function f2000cs_render_supplier_qty_field( $slot ) {
	$num        = absint( $slot );
	$update_qty = $num ? get_option( 'f2000cs_update_stock_qty_' . $num, '0' ) : '0';
	$qty_name   = 'f2000cs_update_stock_qty_' . $slot;
	$qty_id     = $qty_name;
	$is_pro     = f2000cs_is_pro();
	?>
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
	$type_id     = $type_name;
	$val_id      = $val_name;
	$currency    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
	$is_pro      = f2000cs_is_pro();
	$price_cls   = 'f2000cs-price-adjust' . ( $is_pro ? '' : ' f2000cs-pro-feature' );
	?>
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
	<?php
}

/**
 * Update interval select (Pro intervals; daily only without Pro).
 *
 * @return void
 */
function f2000cs_interval_render() {
	$interval = get_option( 'f2000cs_update_interval', 'hourly' );
	$is_pro   = f2000cs_is_pro();

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
			<option value="8_times_daily" <?php selected( $interval, '8_times_daily' ); ?>><?php esc_html_e( '8 разів на день', 'factorial2000-catalog-sync' ); ?></option>
			<option value="6_times_daily" <?php selected( $interval, '6_times_daily' ); ?>><?php esc_html_e( '6 разів на день', 'factorial2000-catalog-sync' ); ?></option>
			<option value="4_times_daily" <?php selected( $interval, '4_times_daily' ); ?>><?php esc_html_e( '4 рази на день', 'factorial2000-catalog-sync' ); ?></option>
			<option value="twicedaily" <?php selected( $interval, 'twicedaily' ); ?>><?php esc_html_e( 'Двічі на день', 'factorial2000-catalog-sync' ); ?></option>
		<?php endif; ?>
		<option value="daily" <?php selected( $interval, 'daily' ); ?>><?php esc_html_e( 'Щодня', 'factorial2000-catalog-sync' ); ?></option>
	</select>
	<?php if ( ! $is_pro ) : ?>
		<p class="description">
			<?php esc_html_e( 'Free: автоматичне оновлення — раз на добу. Частіші інтервали — у Pro.', 'factorial2000-catalog-sync' ); ?>
			<a href="<?php echo esc_url( f2000cs_get_upgrade_url() ); ?>"><?php esc_html_e( 'Оформити Pro', 'factorial2000-catalog-sync' ); ?></a>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Toggle for the variable low-instock rule.
 *
 * @return void
 */
function f2000cs_hide_variable_low_instock_render() {
	$val    = get_option( 'f2000cs_hide_variable_low_instock', '0' );
	$is_pro = f2000cs_is_pro();
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

/**
 * Threshold for the variable low-instock rule.
 *
 * @return void
 */
function f2000cs_variable_low_instock_max_render() {
	$max    = get_option( 'f2000cs_variable_low_instock_max', 2 );
	$is_pro = f2000cs_is_pro();
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
 * «Імпортувати кількість товару» option on the Import page.
 *
 * @param bool $is_pro Whether Pro is unlocked.
 * @return void
 */
function f2000cs_render_import_stock_qty_option( $is_pro ) {
	?>
	<div class="f2000cs-import-field">
		<label for="import_stock_qty">
			<input type="checkbox" name="import_stock_qty" id="import_stock_qty" value="1" <?php disabled( ! $is_pro ); ?>>
			<?php esc_html_e( 'Імпортувати кількість товару (Stock quantity) з XML', 'factorial2000-catalog-sync' ); ?>
			<?php if ( ! $is_pro ) : ?>
				<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
			<?php endif; ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Береться з тегів quantity, stock_quantity або qty_stock. Для товару вмикається керування запасами з цією кількістю. Статус «в наявності» визначається незалежно від цієї опції.', 'factorial2000-catalog-sync' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Field list and buttons of the «Оновити окремі поля» tab on the Import page.
 *
 * @param bool $is_pro Whether Pro is unlocked.
 * @return void
 */
function f2000cs_render_update_fields_panel( $is_pro ) {
	?>
	<div class="f2000cs-update-fields">
		<p class="f2000cs-update-fields__title">
			<strong><?php esc_html_e( 'Інформація, яку потрібно оновити', 'factorial2000-catalog-sync' ); ?></strong>
		</p>
		<p class="description f2000cs-update-fields__hint">
			<?php esc_html_e( 'Якщо за заданим SKU (з урахуванням Prefix) товар уже існує — у ньому буде оновлена / додана обрана нижче інформація з XML.', 'factorial2000-catalog-sync' ); ?>
		</p>
		<div
			class="f2000cs-update-fields__list<?php echo $is_pro ? '' : ' f2000cs-update-fields__list--locked'; ?>"
			<?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>
		>
			<label class="f2000cs-update-fields__option f2000cs-update-fields__option--all">
				<input type="checkbox" id="update_fields_select_all" value="all" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Вибрати всі', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="name" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Назва', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="description" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Опис', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="short_description" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Короткий опис', 'factorial2000-catalog-sync' ); ?>
				<span class="f2000cs-update-fields__note">
					<?php esc_html_e( '(тільки якщо в XML є тег short_description)', 'factorial2000-catalog-sync' ); ?>
				</span>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="tags" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Теги товару', 'factorial2000-catalog-sync' ); ?>
				<span class="f2000cs-update-fields__note">
					<?php esc_html_e( '(ключові запити з Prom)', 'factorial2000-catalog-sync' ); ?>
				</span>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="price" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Ціна', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="oldprice" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Стара ціна', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="status" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Статус', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="stock_quantity" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Кількість на складі', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="images" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Фото', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="attributes" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Атрибути / характеристики', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="categories" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Категорії', 'factorial2000-catalog-sync' ); ?>
			</label>
			<label class="f2000cs-update-fields__option">
				<input type="checkbox" name="update_fields[]" value="vendorCode" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
				<?php esc_html_e( 'Код постачальника (vendorCode)', 'factorial2000-catalog-sync' ); ?>
				<span class="f2000cs-update-fields__note">
					<?php esc_html_e( '(з XML; показується адміну на сторінці товару для копіювання)', 'factorial2000-catalog-sync' ); ?>
				</span>
			</label>
		</div>
	</div>

	<div class="f2000cs-import-actions">
		<button type="button" id="start-update-fields" class="button button-primary" <?php disabled( ! $is_pro ); ?>>
			<?php esc_html_e( 'Оновити', 'factorial2000-catalog-sync' ); ?>
			<?php if ( ! $is_pro ) : ?>
				<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
			<?php endif; ?>
		</button>
		<button type="button" id="stop-update-fields" class="button button-secondary is-hidden">
			<?php esc_html_e( 'Зупинити', 'factorial2000-catalog-sync' ); ?>
		</button>
	</div>
	<?php
}

/**
 * Handle the "Оновити окремі поля" AJAX action: for existing products matched
 * by SKU (with prefix), update only the fields the user selected, one XML
 * offer per request (mirrors the chunked import flow).
 *
 * @return void
 */
function f2000cs_handle_update_fields_action() {
	if ( ! isset( $_POST['f2000cs_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_import_nonce'] ) ), 'f2000cs_import_action' ) ) {
		wp_send_json_error( array( 'message' => __( 'Помилка перевірки безпеки (nonce). Оновіть сторінку.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( ! f2000cs_is_pro() ) {
		wp_send_json_error( array( 'message' => __( 'Оновлення окремих полів доступне лише у Pro версії.', 'factorial2000-catalog-sync' ) ) );
	}

	$resolved = f2000cs_resolve_import_xml_source();
	if ( is_wp_error( $resolved ) ) {
		wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
	}

	$file_path  = $resolved['path'];
	$offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
	$sku_prefix = isset( $_POST['sku_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_prefix'] ) ) : '';

	$fields = array();
	if ( isset( $_POST['update_fields'] ) && is_array( $_POST['update_fields'] ) ) {
		$fields = array_map( 'sanitize_text_field', wp_unslash( $_POST['update_fields'] ) );
	}

	$fields = array_values( array_intersect( \F2000CS\Fields_Updater::ALLOWED_FIELDS, $fields ) );

	if ( empty( $fields ) ) {
		if ( ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}
		wp_send_json_error( array( 'message' => __( 'Виберіть хоча б одне поле для оновлення.', 'factorial2000-catalog-sync' ) ) );
	}

	$updater = new \F2000CS\Fields_Updater( $file_path, $sku_prefix, $fields );

	try {
		$result = $updater->update_fields( $offset, 1 );

		if ( ! empty( $result['finished'] ) && ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}

		wp_send_json_success(
			array(
				'imported'       => $result['processed'] + $offset,
				'total'          => $result['total'],
				'finished'       => $result['finished'],
				'updated'        => $result['updated'],
				'not_found'      => $result['not_found'],
				'skipped'        => $result['skipped'],
				'import_session' => $resolved['session'],
			)
		);
	} catch ( Exception $e ) {
		if ( ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_f2000cs_update_fields_action', 'f2000cs_handle_update_fields_action' );
