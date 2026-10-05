<?php
/**
 * Marketplaces admin page: export WooCommerce products to Prom.ua (and later other marketplaces).
 *
 * Premium-only file (see the @fs_premium_only header of the main plugin file).
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

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
 * Render one settings field of a feed schema.
 *
 * @param string              $key     Field key.
 * @param array<string,mixed> $field   Field schema.
 * @param mixed               $value   Current value.
 * @return void
 */
function f2000cs_marketplace_render_field( string $key, array $field, $value ): void {
	$type = $field['type'] ?? 'text';
	$name = 'mp[' . $key . ']';
	$id   = 'f2000cs-mp-' . $key;
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) ( $field['label'] ?? $key ) ); ?></label></th>
		<td>
			<?php if ( 'checkbox' === $type ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
				<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( '1', (string) $value ); ?>> <?php esc_html_e( 'Увімкнено', 'factorial2000-catalog-sync' ); ?></label>
			<?php elseif ( 'textarea' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" class="large-text code"><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php elseif ( 'number' === $type ) : ?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text" min="<?php echo esc_attr( (string) ( $field['min'] ?? 0 ) ); ?>" max="<?php echo esc_attr( (string) ( $field['max'] ?? '' ) ); ?>">
			<?php elseif ( 'select' === $type ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( (array) ( $field['options'] ?? array() ) as $option => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input type="<?php echo 'url' === $type ? 'url' : 'text'; ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text">
			<?php endif; ?>
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<p class="description"><?php echo esc_html( (string) $field['description'] ); ?></p>
			<?php endif; ?>
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

			<h2><?php esc_html_e( 'Налаштування', 'factorial2000-catalog-sync' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="f2000cs-mp-schedule"><?php esc_html_e( 'Автоматичне оновлення', 'factorial2000-catalog-sync' ); ?></label></th>
					<td>
						<select id="f2000cs-mp-schedule" name="mp[schedule]">
							<?php foreach ( $labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['schedule'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Файл перегенеровується у фоні через WP-Cron; поки триває генерація, маркетплейс бачить попередній файл.', 'factorial2000-catalog-sync' ); ?></p>
					</td>
				</tr>
				<?php foreach ( $feed->fields() as $key => $field ) : ?>
					<?php f2000cs_marketplace_render_field( (string) $key, $field, $settings[ $key ] ?? '' ); ?>
				<?php endforeach; ?>
			</table>

			<?php submit_button( __( 'Зберегти налаштування', 'factorial2000-catalog-sync' ) ); ?>
		</form>
	</div>
	<?php
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

	$id = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : '';

	if ( ! Marketplace_Registry::get( $id ) ) {
		wp_die( esc_html__( 'Невідомий маркетплейс.', 'factorial2000-catalog-sync' ) );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every field is sanitized by the feed schema in Marketplace_Registry::save_settings().
	$raw = isset( $_POST['mp'] ) && is_array( $_POST['mp'] ) ? wp_unslash( $_POST['mp'] ) : array();

	Marketplace_Registry::save_settings( $id, $raw );
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
