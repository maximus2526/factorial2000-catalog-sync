<?php
/**
 * Marketplaces admin page: export WooCommerce products to Prom.ua (and later other marketplaces).
 *
 * Premium-only file (see the @fs_premium_only header of the main plugin file).
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

use F2000CS\Marketplace_Form;
use F2000CS\Marketplace_Job;
use F2000CS\Marketplace_Registry;
use F2000CS\Marketplace_Storage;

/**
 * Human readable schedule names.
 *
 * @return array<string,string>
 */
function f2000cs_marketplace_schedule_labels(): array {
	return array(
		'off'           => __( 'Вручну (без розкладу)', 'factorial2000-catalog-sync' ),
		'hourly'        => __( 'Щогодини', 'factorial2000-catalog-sync' ),
		'6_times_daily' => __( '6 разів на день', 'factorial2000-catalog-sync' ),
		'4_times_daily' => __( '4 рази на день', 'factorial2000-catalog-sync' ),
		'twicedaily'    => __( 'Двічі на день', 'factorial2000-catalog-sync' ),
		'daily'         => __( 'Щодня', 'factorial2000-catalog-sync' ),
	);
}

/**
 * Description paragraph under a field.
 *
 * @param array<string,mixed> $field Field schema.
 * @return void
 */
function f2000cs_marketplace_field_description( array $field ): void {
	if ( ! empty( $field['description'] ) ) {
		echo '<p class="description">' . esc_html( (string) $field['description'] ) . '</p>';
	}
}

/**
 * Select with presets plus an optional "custom" choice that reveals a free input.
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current stored value.
 * @return void
 */
function f2000cs_marketplace_render_preset( string $key, array $field, $value ): void {
	$options = (array) ( $field['options'] ?? array() );
	$custom  = $field['custom'] ?? null;
	$current = Marketplace_Form::selected_preset( (string) $value, $options );

	// Without a custom option an unknown stored value falls back to the first preset.
	if ( ! $custom && Marketplace_Form::CUSTOM === $current ) {
		$current = (string) array_key_first( $options );
	}

	$id          = 'f2000cs-mp-' . $key;
	$custom_type = 'number' === ( $custom['type'] ?? 'text' ) ? 'number' : 'text';
	?>
	<select id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" class="f2000cs-mp-preset" data-custom-input="<?php echo esc_attr( $id ); ?>-custom">
		<?php foreach ( $options as $option => $label ) : ?>
			<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $current, (string) $option ); ?>><?php echo esc_html( (string) $label ); ?></option>
		<?php endforeach; ?>
		<?php if ( $custom ) : ?>
			<option value="<?php echo esc_attr( Marketplace_Form::CUSTOM ); ?>" <?php selected( $current, Marketplace_Form::CUSTOM ); ?>><?php echo esc_html( (string) ( $custom['label'] ?? __( 'Своє значення…', 'factorial2000-catalog-sync' ) ) ); ?></option>
		<?php endif; ?>
	</select>
	<?php if ( $custom ) : ?>
		<input type="<?php echo esc_attr( $custom_type ); ?>" id="<?php echo esc_attr( $id ); ?>-custom" name="mp_custom[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( Marketplace_Form::CUSTOM === $current ? (string) $value : '' ); ?>" class="regular-text f2000cs-mp-custom" <?php echo 'number' === $custom_type ? 'min="' . esc_attr( (string) ( $custom['min'] ?? 0 ) ) . '" max="' . esc_attr( (string) ( $custom['max'] ?? '' ) ) . '"' : ''; ?> <?php echo Marketplace_Form::CUSTOM === $current ? '' : 'hidden'; ?>>
	<?php endif; ?>
	<?php
}

/**
 * Repeater of "left → right" rows (stored as left=right lines).
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current stored value.
 * @return void
 */
function f2000cs_marketplace_render_pairs( string $key, array $field, $value ): void {
	$rows   = Marketplace_Form::text_to_rows( (string) $value );
	$rows[] = array( '', '' );
	$list   = 'brands' === ( $field['left_suggest'] ?? '' ) ? 'f2000cs-mp-brands' : '';

	if ( $list ) {
		echo '<datalist id="' . esc_attr( $list ) . '">';
		foreach ( Marketplace_Form::load_brands() as $brand ) {
			echo '<option value="' . esc_attr( $brand ) . '">';
		}
		echo '</datalist>';
	}
	?>
	<table class="widefat striped f2000cs-mp-pairs" data-key="<?php echo esc_attr( $key ); ?>" style="max-width:720px">
		<thead>
			<tr>
				<th><?php echo esc_html( (string) ( $field['left_label'] ?? '' ) ); ?></th>
				<th><?php echo esc_html( (string) ( $field['right_label'] ?? '' ) ); ?></th>
				<th style="width:40px"></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $i => $row ) : ?>
				<tr>
					<td><input type="text" class="regular-text" name="mp_rows[<?php echo esc_attr( $key ); ?>][<?php echo (int) $i; ?>][l]" value="<?php echo esc_attr( $row[0] ); ?>" <?php echo $list ? 'list="' . esc_attr( $list ) . '"' : ''; ?>></td>
					<td><input type="text" class="regular-text" name="mp_rows[<?php echo esc_attr( $key ); ?>][<?php echo (int) $i; ?>][r]" value="<?php echo esc_attr( $row[1] ); ?>"></td>
					<td><button type="button" class="button-link f2000cs-mp-row-remove" aria-label="<?php esc_attr_e( 'Видалити рядок', 'factorial2000-catalog-sync' ); ?>">✕</button></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p><button type="button" class="button f2000cs-mp-row-add" data-target="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '+ Додати відповідність', 'factorial2000-catalog-sync' ); ?></button></p>
	<?php
}

/**
 * Table "store category → Prom portal category id" plus a box for custom rules.
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current stored rules.
 * @return void
 */
function f2000cs_marketplace_render_category_map( string $key, array $field, $value ): void {
	$categories = Marketplace_Form::load_categories();
	$split      = Marketplace_Form::split_rules( (string) $value, $categories );

	if ( ! $categories ) {
		echo '<em>' . esc_html__( 'У магазині ще немає категорій товарів.', 'factorial2000-catalog-sync' ) . '</em>';
	} else {
		?>
		<p><input type="search" class="regular-text f2000cs-mp-cat-filter" placeholder="<?php esc_attr_e( 'Пошук категорії…', 'factorial2000-catalog-sync' ); ?>"></p>
		<div style="max-height:360px;overflow:auto;max-width:720px;border:1px solid #c3c4c7">
			<table class="widefat striped f2000cs-mp-cat-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Категорія магазину', 'factorial2000-catalog-sync' ); ?></th>
						<th style="width:70px"><?php esc_html_e( 'Товарів', 'factorial2000-catalog-sync' ); ?></th>
						<th style="width:170px"><?php esc_html_e( 'Номер категорії Prom', 'factorial2000-catalog-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $categories as $cat ) : ?>
						<tr data-name="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $cat['path'], 'UTF-8' ) : strtolower( $cat['path'] ) ); ?>">
							<td style="padding-left:<?php echo (int) ( 10 + 16 * $cat['depth'] ); ?>px"><?php echo esc_html( basename( str_replace( ' > ', '/', $cat['path'] ) ) ); ?></td>
							<td><?php echo (int) $cat['count']; ?></td>
							<td><input type="text" inputmode="numeric" pattern="[0-9]*" class="small-text" name="mp_map[<?php echo esc_attr( $key ); ?>][<?php echo (int) $cat['id']; ?>]" value="<?php echo esc_attr( $split['by_id'][ $cat['id'] ] ?? '' ); ?>" placeholder="—"></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	?>
	<details style="margin-top:8px">
		<summary><?php esc_html_e( 'Свої правила (для досвідчених)', 'factorial2000-catalog-sync' ); ?></summary>
		<textarea name="mp_extra[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text code" placeholder="кросівки=1234567"><?php echo esc_textarea( $split['extra'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'По одному правилу в рядку: частина шляху категорії=номер Prom, наприклад «одяг>куртки=1234567». Спрацьовує перше правило, що збіглося.', 'factorial2000-catalog-sync' ); ?></p>
	</details>
	<?php
}

/**
 * Render one settings field row.
 *
 * @param string              $key   Field key.
 * @param array<string,mixed> $field Field schema.
 * @param mixed               $value Current value.
 * @return void
 */
function f2000cs_marketplace_render_field( string $key, array $field, $value ): void {
	$type = $field['type'] ?? 'text';
	$id   = 'f2000cs-mp-' . $key;
	?>
	<tr>
		<th scope="row"><?php echo in_array( $type, array( 'pairs', 'category_map' ), true ) ? esc_html( (string) ( $field['label'] ?? $key ) ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $field['label'] ?? $key ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></th>
		<td>
			<?php
			if ( 'preset' === $type ) {
				f2000cs_marketplace_render_preset( $key, $field, $value );
			} elseif ( 'pairs' === $type ) {
				f2000cs_marketplace_render_pairs( $key, $field, $value );
			} elseif ( 'category_map' === $type ) {
				f2000cs_marketplace_render_category_map( $key, $field, $value );
			} elseif ( 'checkbox' === $type ) {
				?>
				<input type="hidden" name="mp[<?php echo esc_attr( $key ); ?>]" value="0">
				<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( '1', (string) $value ); ?>> <?php echo esc_html( (string) ( $field['checkbox'] ?? __( 'Увімкнено', 'factorial2000-catalog-sync' ) ) ); ?></label>
				<?php
			} elseif ( 'textarea' === $type ) {
				?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" rows="4" class="large-text code"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
			} elseif ( 'number' === $type ) {
				?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text" min="<?php echo esc_attr( (string) ( $field['min'] ?? 0 ) ); ?>" max="<?php echo esc_attr( (string) ( $field['max'] ?? '' ) ); ?>">
				<?php
			} else {
				?>
				<input type="<?php echo 'url' === $type ? 'url' : 'text'; ?>" id="<?php echo esc_attr( $id ); ?>" name="mp[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text">
				<?php
			}

			f2000cs_marketplace_field_description( $field );
			?>
		</td>
	</tr>
	<?php
}

/**
 * Marketplaces page.
 *
 * @return void
 */
function f2000cs_marketplaces_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		echo '<div class="wrap">';
		f2000cs_render_admin_page_title( __( 'Маркетплейси', 'factorial2000-catalog-sync' ) );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Вигрузка товарів на маркетплейси доступна лише у Pro версії.', 'factorial2000-catalog-sync' );
		echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Оновити до Pro', 'factorial2000-catalog-sync' ) . '</a>';
		echo '</p></div></div>';
		return;
	}

	$feeds = Marketplace_Registry::all();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$id   = isset( $feeds[ $tab ] ) ? $tab : (string) array_key_first( $feeds );
	$feed = $feeds[ $id ] ?? null;

	?>
	<div class="wrap f2000cs-marketplaces">
		<?php f2000cs_render_admin_page_title( __( 'Маркетплейси', 'factorial2000-catalog-sync' ) ); ?>

		<?php settings_errors( 'f2000cs_marketplace' ); ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $feeds as $feed_id => $item ) : ?>
				<a class="nav-tab <?php echo $feed_id === $id ? 'nav-tab-active' : ''; ?>" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => 'f2000cs-marketplaces',
							'tab'  => $feed_id,
						),
						admin_url( 'admin.php' )
					)
				);
				?>
									"><?php echo esc_html( $item->label() ); ?></a>
			<?php endforeach; ?>
			<span class="nav-tab" style="opacity:.55;cursor:default"><?php esc_html_e( 'Rozetka — незабаром', 'factorial2000-catalog-sync' ); ?></span>
			<span class="nav-tab" style="opacity:.55;cursor:default"><?php esc_html_e( 'Hotline — незабаром', 'factorial2000-catalog-sync' ); ?></span>
		</h2>

		<?php
		if ( ! $feed ) {
			echo '</div>';
			return;
		}

		$settings = Marketplace_Registry::settings( $id );
		$info     = Marketplace_Storage::info( $feed );
		$result   = Marketplace_Job::last_result( $id );
		$running  = Marketplace_Job::state( $id );
		$labels   = f2000cs_marketplace_schedule_labels();
		$fields   = $feed->fields();
		?>

		<div class="card" style="max-width:none">
			<h2><?php echo esc_html( $feed->label() ); ?></h2>
			<p class="description"><?php echo esc_html( $feed->description() ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Посилання на вигрузку', 'factorial2000-catalog-sync' ); ?></th>
					<td>
						<?php if ( $info['exists'] ) : ?>
							<input type="text" class="large-text code" readonly value="<?php echo esc_attr( Marketplace_Storage::url( $feed ) ); ?>" onclick="this.select()">
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: date and time, 2: file size in MB */
										__( 'Оновлено: %1$s · %2$s МБ. Вкажіть це посилання у кабінеті маркетплейсу як джерело імпорту.', 'factorial2000-catalog-sync' ),
										wp_date( 'd.m.Y H:i', $info['mtime'] ),
										number_format_i18n( $info['size'] / 1048576, 1 )
									)
								);
								?>
							</p>
						<?php else : ?>
							<em><?php esc_html_e( 'Вигрузку ще не згенеровано.', 'factorial2000-catalog-sync' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Остання генерація', 'factorial2000-catalog-sync' ); ?></th>
					<td>
						<?php if ( ! empty( $result['status'] ) ) : ?>
							<?php if ( 'done' === $result['status'] ) : ?>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: offers, 2: categories, 3: skipped, 4: seconds */
										__( 'Товарів у вигрузці: %1$d · категорій: %2$d · пропущено: %3$d · %4$d с', 'factorial2000-catalog-sync' ),
										(int) ( $result['exported'] ?? 0 ),
										(int) ( $result['categories'] ?? 0 ),
										(int) ( $result['skipped'] ?? 0 ),
										(int) ( $result['duration'] ?? 0 )
									)
								);
								?>
								<?php if ( ! empty( $result['excluded'] ) ) : ?>
									<p class="description">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: comma separated SKUs */
												__( 'Не вивантажено групи, у яких Prom не прийняв би різновиди з однаковими характеристиками (SKU батьківських товарів): %s', 'factorial2000-catalog-sync' ),
												implode( ', ', array_map( 'strval', (array) $result['excluded'] ) )
											)
										);
										?>
									</p>
								<?php endif; ?>
							<?php else : ?>
								<span style="color:#b32d2e"><?php echo esc_html( (string) ( $result['message'] ?? '' ) ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" class="button button-primary f2000cs-mp-build" data-feed="<?php echo esc_attr( $id ); ?>" data-resume="<?php echo $running ? '1' : '0'; ?>">
					<?php echo $running ? esc_html__( 'Продовжити генерацію', 'factorial2000-catalog-sync' ) : esc_html__( 'Згенерувати зараз', 'factorial2000-catalog-sync' ); ?>
				</button>
				<span class="f2000cs-mp-progress" aria-live="polite" style="margin-left:10px"></span>
			</p>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="f2000cs_marketplace_save">
			<input type="hidden" name="feed" value="<?php echo esc_attr( $id ); ?>">
			<?php wp_nonce_field( 'f2000cs_marketplace_save', 'f2000cs_marketplace_nonce' ); ?>

			<h2><?php esc_html_e( 'Оновлення вигрузки', 'factorial2000-catalog-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="f2000cs-mp-schedule"><?php esc_html_e( 'Автоматично', 'factorial2000-catalog-sync' ); ?></label></th>
					<td>
						<select id="f2000cs-mp-schedule" name="mp[schedule]">
							<?php foreach ( $labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['schedule'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Файл перегенеровується у фоні через WP-Cron; поки триває генерація, маркетплейс бачить попередній файл.', 'factorial2000-catalog-sync' ); ?></p>
					</td>
				</tr>
			</table>

			<?php foreach ( $feed->groups() as $group => $title ) : ?>
				<?php
				$in_group = array_filter(
					$fields,
					static function ( $field ) use ( $group ) {
						return ( $field['group'] ?? 'basic' ) === $group;
					}
				);

				if ( ! $in_group ) {
					continue;
				}
				?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $in_group as $key => $field ) : ?>
						<?php f2000cs_marketplace_render_field( (string) $key, $field, $settings[ $key ] ?? '' ); ?>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>

			<?php submit_button( __( 'Зберегти налаштування', 'factorial2000-catalog-sync' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Collect the posted form into the raw array the feed sanitizes.
 *
 * Preset fields post a select and an optional custom text; repeaters post rows;
 * the category table posts one id per category plus a box of custom rules.
 *
 * @param \F2000CS\Marketplace_Feed $feed Feed.
 * @return array<string,mixed>
 */
function f2000cs_marketplace_collect_post( $feed ): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by the caller; every value is sanitized by the feed schema (Marketplace_Registry::save_settings()).
	$raw             = isset( $_POST['mp'] ) && is_array( $_POST['mp'] ) ? wp_unslash( $_POST['mp'] ) : array();
	$raw['__custom'] = isset( $_POST['mp_custom'] ) && is_array( $_POST['mp_custom'] ) ? wp_unslash( $_POST['mp_custom'] ) : array();
	$raw['__rows']   = isset( $_POST['mp_rows'] ) && is_array( $_POST['mp_rows'] ) ? wp_unslash( $_POST['mp_rows'] ) : array();
	$map             = isset( $_POST['mp_map'] ) && is_array( $_POST['mp_map'] ) ? wp_unslash( $_POST['mp_map'] ) : array();
	$extra           = isset( $_POST['mp_extra'] ) && is_array( $_POST['mp_extra'] ) ? wp_unslash( $_POST['mp_extra'] ) : array();
	// phpcs:enable

	foreach ( $feed->fields() as $key => $field ) {
		if ( 'category_map' !== ( $field['type'] ?? '' ) ) {
			continue;
		}

		$raw[ $key ] = Marketplace_Form::build_rules(
			isset( $map[ $key ] ) && is_array( $map[ $key ] ) ? array_map( 'strval', $map[ $key ] ) : array(),
			Marketplace_Form::load_categories(),
			isset( $extra[ $key ] ) ? (string) $extra[ $key ] : ''
		);
	}

	return $raw;
}

/**
 * Save the settings of a feed.
 *
 * @return void
 */
function f2000cs_marketplace_handle_save() {
	if ( ! isset( $_POST['f2000cs_marketplace_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_marketplace_nonce'] ) ), 'f2000cs_marketplace_save' ) ) {
		wp_die( esc_html__( 'Помилка перевірки безпеки (nonce). Оновіть сторінку.', 'factorial2000-catalog-sync' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) );
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		wp_die( esc_html__( 'Вигрузка на маркетплейси доступна лише у Pro версії.', 'factorial2000-catalog-sync' ) );
	}

	$id   = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : '';
	$feed = Marketplace_Registry::get( $id );

	if ( ! $feed ) {
		wp_die( esc_html__( 'Невідомий маркетплейс.', 'factorial2000-catalog-sync' ) );
	}

	Marketplace_Registry::save_settings( $id, f2000cs_marketplace_collect_post( $feed ) );
	Marketplace_Job::reschedule( $id );

	add_settings_error( 'f2000cs_marketplace', 'saved', __( 'Налаштування збережено.', 'factorial2000-catalog-sync' ), 'updated' );
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'             => 'f2000cs-marketplaces',
				'tab'              => $id,
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_f2000cs_marketplace_save', 'f2000cs_marketplace_handle_save' );

/**
 * AJAX: run one slice of a feed build.
 *
 * @return void
 */
function f2000cs_marketplace_ajax_step() {
	check_ajax_referer( 'f2000cs_marketplace_step', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		wp_send_json_error( array( 'message' => __( 'Вигрузка на маркетплейси доступна лише у Pro версії.', 'factorial2000-catalog-sync' ) ) );
	}

	$id = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : '';

	if ( ! Marketplace_Registry::get( $id ) ) {
		wp_send_json_error( array( 'message' => __( 'Невідомий маркетплейс.', 'factorial2000-catalog-sync' ) ) );
	}

	$restart = isset( $_POST['restart'] ) && '1' === $_POST['restart'];

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- Best effort.
	}

	$result = Marketplace_Job::step( $id, 20, null, $restart );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_f2000cs_marketplace_step', 'f2000cs_marketplace_ajax_step' );

/**
 * Load the page script.
 *
 * @param string $hook Admin page hook suffix.
 * @return void
 */
function f2000cs_marketplace_enqueue( $hook ) {
	if ( false === strpos( (string) $hook, 'f2000cs-marketplaces' ) ) {
		return;
	}

	// Nested tables live inside .form-table cells, whose th/td rules (200px headers, big padding) would otherwise leak into them.
	wp_register_style( 'f2000cs-admin-marketplaces', false, array(), F2000CS_VERSION );
	wp_enqueue_style( 'f2000cs-admin-marketplaces' );
	wp_add_inline_style(
		'f2000cs-admin-marketplaces',
		'.f2000cs-marketplaces .form-table td table{margin:0}'
		. '.f2000cs-marketplaces .form-table td table th,.f2000cs-marketplaces .form-table td table td{width:auto;padding:8px 10px;vertical-align:middle;line-height:1.4}'
		. '.f2000cs-marketplaces .form-table td table input[type=text]{width:100%;max-width:none}'
		. '.f2000cs-marketplaces .form-table td .f2000cs-mp-cat-table input[type=text]{width:120px}'
		. '.f2000cs-marketplaces .f2000cs-mp-pairs,.f2000cs-marketplaces .f2000cs-mp-cat-table{table-layout:auto}'
		. '.f2000cs-marketplaces .f2000cs-mp-custom{margin-left:6px;vertical-align:middle}'
		. '.f2000cs-marketplaces .f2000cs-mp-custom[hidden]{display:none}'
		. '.f2000cs-marketplaces .form-table td select{max-width:100%}'
		. '.f2000cs-marketplaces .form-table td details textarea{margin-top:6px}'
		. '.f2000cs-marketplaces .card{padding:0.7em 2em 1em}'
	);

	wp_enqueue_script(
		'f2000cs-admin-marketplaces',
		F2000CS_URL . 'assets/js/admin-marketplaces.js',
		array( 'jquery' ),
		F2000CS_VERSION,
		true
	);

	wp_localize_script(
		'f2000cs-admin-marketplaces',
		'f2000csMarketplaces',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'f2000cs_marketplace_step' ),
			'i18n'    => array(
				'running' => __( 'Формуємо вигрузку…', 'factorial2000-catalog-sync' ),
				'offers'  => __( 'товарів', 'factorial2000-catalog-sync' ),
				'done'    => __( 'Готово!', 'factorial2000-catalog-sync' ),
				'waiting' => __( 'Генерація вже триває, чекаємо…', 'factorial2000-catalog-sync' ),
				'failed'  => __( 'Помилка. Спробуйте ще раз.', 'factorial2000-catalog-sync' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'f2000cs_marketplace_enqueue' );
