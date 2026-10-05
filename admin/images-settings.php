<?php
/**
 * Pro image-processing settings (import page panel + option registration).
 *
 * Premium-only file: listed in the @fs_premium_only header of the main plugin
 * file, so Freemius removes it from the free (wp.org) build. The free build
 * gets a locked placeholder from admin/premium-upsell.php instead.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the image-processing options (saved from the Import page).
 *
 * @return void
 */
function f2000cs_register_image_settings() {
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
}
add_action( 'admin_init', 'f2000cs_register_image_settings' );

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
			'tip'      => __( 'Конвертує фото з тегів picture у PNG, JPG, WebP або AVIF у вибраний формат перед збереженням у медіатеку. GIF і BMP не змінюються. JPG сумісніший; WebP/AVIF легші, але потрібна підтримка на хостингу. Увага: кожне фото обробляється окремо — це навантажує сервер, і процес імпорту може йти довше. За замовчуванням — «Не конвертувати».', 'factorial2000-catalog-sync' ),
			'callback' => 'f2000cs_img_png_convert_render',
		),
		array(
			'label'    => __( 'Оптимізація', 'factorial2000-catalog-sync' ),
			'tip'      => __( 'Перезберігає JPG/WebP/AVIF/PNG з обраною якістю під час імпорту, щоб зменшити вагу файлів. Увага: кожне фото обробляється окремо — це навантажує сервер, і процес імпорту може йти довше. За замовчуванням вимкнено.', 'factorial2000-catalog-sync' ),
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
		<p class="description"><?php esc_html_e( 'Фото з picture у PNG, JPG, WebP або AVIF конвертуються у вибраний формат перед збереженням у медіатеку. GIF і BMP не змінюються. Працює через Imagick/GD WordPress: JPG майже всюди; WebP/AVIF — лише якщо хостинг їх підтримує. Якщо конвертація не вдалась — лишається оригінал. Увага: кожне фото обробляється окремо — це навантажує сервер, і процес імпорту може йти довше. За замовчуванням — «Не конвертувати».', 'factorial2000-catalog-sync' ); ?></p>
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
			<?php esc_html_e( 'Оптимізація зображень (Imagick / GD)', 'factorial2000-catalog-sync' ); ?>
			<?php if ( ! $is_pro ) : ?>
				<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
			<?php endif; ?>
		</label>
		<p class="description"><?php esc_html_e( 'Перезберігає JPG/WebP/AVIF/PNG з обраною якістю під час імпорту. Увага: кожне фото обробляється окремо — це навантажує сервер, і процес імпорту може йти довше. За замовчуванням вимкнено.', 'factorial2000-catalog-sync' ); ?></p>
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
