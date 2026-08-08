<?php
/**
 * Update (stock sync) admin page and admin-post handler.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render Update XML admin page.
 *
 * @return void
 */
function f2000cs_update_page() {
	$can_update = function_exists( 'f2000cs_can_run_stock_update' ) ? f2000cs_can_run_stock_update() : true;
	$is_pro     = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	$next_run   = wp_next_scheduled( 'f2000cs_update_stock_cron' );
	$interval   = function_exists( 'f2000cs_get_effective_update_interval' )
		? f2000cs_get_effective_update_interval()
		: get_option( 'f2000cs_update_interval', 'hourly' );
	$bg_pending = f2000cs_get_next_background_event();

	$interval_labels = array(
		'5_minute'   => __( 'Що 5 хв', 'factorial2000-catalog-sync' ),
		'10_minute'  => __( 'Що 10 хв', 'factorial2000-catalog-sync' ),
		'15_minute'  => __( 'Що 15 хв', 'factorial2000-catalog-sync' ),
		'30_minute'  => __( 'Що 30 хв', 'factorial2000-catalog-sync' ),
		'hourly'     => __( 'Щогодини', 'factorial2000-catalog-sync' ),
		'twicedaily' => __( 'Двічі на день', 'factorial2000-catalog-sync' ),
		'daily'      => __( 'Щодня', 'factorial2000-catalog-sync' ),
	);
	$interval_label  = isset( $interval_labels[ $interval ] ) ? $interval_labels[ $interval ] : $interval;
	?>
	<div class="wrap f2000cs-update-page">
		<?php f2000cs_render_admin_page_title( __( 'Factorial2000 Catalog Sync – Оновлення', 'factorial2000-catalog-sync' ) ); ?>

		<?php
		if ( function_exists( 'f2000cs_render_trial_countdown' ) ) {
			f2000cs_render_trial_countdown();
		}
		?>

		<?php settings_errors(); ?>

		<div class="f2000cs-update-page__layout">
			<div class="f2000cs-update-page__main">
				<form method="post" action="options.php" class="f2000cs-update-page__settings-form">
					<?php
					settings_fields( 'f2000cs_settings' );
					f2000cs_do_settings_panels( 'f2000cs' );
					?>
					<p class="f2000cs-update-page__save">
						<?php submit_button( __( 'Зберегти налаштування', 'factorial2000-catalog-sync' ), 'primary', 'submit', false ); ?>
					</p>
				</form>
			</div>

			<aside class="f2000cs-update-page__aside">
				<section class="f2000cs-settings-panel f2000cs-update-run">
					<header class="f2000cs-settings-panel__head">
						<h2 class="f2000cs-settings-panel__title"><?php esc_html_e( 'Запуск оновлення', 'factorial2000-catalog-sync' ); ?></h2>
					</header>
					<div class="f2000cs-settings-panel__intro">
						<p class="description"><?php esc_html_e( 'Оновити наявність і ціни за збереженими XML постачальників.', 'factorial2000-catalog-sync' ); ?></p>
					</div>
					<div class="f2000cs-settings-panel__body">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="f2000cs-update-run__form">
							<?php wp_nonce_field( 'f2000cs_action', 'f2000cs_nonce' ); ?>
							<input type="hidden" name="action" value="f2000cs_action">

							<fieldset class="f2000cs-update-run__mode" <?php disabled( ! $can_update ); ?>>
								<legend><?php esc_html_e( 'Режим', 'factorial2000-catalog-sync' ); ?></legend>
								<label class="f2000cs-update-run__option">
									<input type="radio" name="use_background" value="yes" checked <?php disabled( ! $can_update ); ?>>
									<span>
										<strong><?php esc_html_e( 'Фоновий', 'factorial2000-catalog-sync' ); ?></strong>
										<small><?php esc_html_e( 'Через WP-Cron, не блокує адмінку', 'factorial2000-catalog-sync' ); ?></small>
									</span>
								</label>
								<label class="f2000cs-update-run__option">
									<input type="radio" name="use_background" value="no" <?php disabled( ! $can_update ); ?>>
									<span>
										<strong><?php esc_html_e( 'Одразу', 'factorial2000-catalog-sync' ); ?></strong>
										<small><?php esc_html_e( 'Синхронно в цьому запиті', 'factorial2000-catalog-sync' ); ?></small>
									</span>
								</label>
							</fieldset>

						<p class="description f2000cs-update-run__sync-warning" id="f2000cs-sync-warning" hidden>
							<?php esc_html_e( 'Увага: в режимі «Одразу» сторінка буде заблокована до завершення оновлення всіх постачальників. Для великих каталогів рекомендується «Фоновий» режим.', 'factorial2000-catalog-sync' ); ?>
						</p>

							<?php if ( ! $is_pro ) : ?>
								<p class="description f2000cs-update-run__limit">
									<?php
									if ( $can_update ) {
										esc_html_e( 'Free: сьогодні ще можна запустити 1 оновлення. У Pro — без ліміту.', 'factorial2000-catalog-sync' );
									} else {
										echo esc_html( f2000cs_get_free_update_limit_message() );
										echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Оформити Pro', 'factorial2000-catalog-sync' ) . '</a>';
									}
									?>
								</p>
							<?php endif; ?>

							<div class="f2000cs-update-run__actions">
								<input type="submit" name="run_script" class="button button-primary button-hero" value="<?php esc_attr_e( 'Оновити наявність', 'factorial2000-catalog-sync' ); ?>" <?php disabled( ! $can_update ); ?>>
								<input type="submit" name="f2000cs_stop" class="button button-secondary" value="<?php esc_attr_e( 'Зупинити cron', 'factorial2000-catalog-sync' ); ?>">
							</div>
						</form>
					</div>
				</section>

				<section class="f2000cs-settings-panel f2000cs-status">
					<header class="f2000cs-settings-panel__head">
						<h2 class="f2000cs-settings-panel__title"><?php esc_html_e( 'Статус', 'factorial2000-catalog-sync' ); ?></h2>
					</header>
					<div class="f2000cs-settings-panel__body">
						<ul class="f2000cs-status__list">
							<?php if ( ! $is_pro ) : ?>
								<li>
									<span class="f2000cs-status__label"><?php esc_html_e( 'Ліміт Free сьогодні', 'factorial2000-catalog-sync' ); ?></span>
									<?php if ( $can_update ) : ?>
										<span class="f2000cs-status__badge is-active"><?php esc_html_e( '1 оновлення доступне', 'factorial2000-catalog-sync' ); ?></span>
									<?php else : ?>
										<span class="f2000cs-status__badge is-inactive"><?php esc_html_e( 'вже використано', 'factorial2000-catalog-sync' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endif; ?>

							<li>
								<span class="f2000cs-status__label"><?php esc_html_e( 'Автооновлення', 'factorial2000-catalog-sync' ); ?></span>
								<?php if ( $next_run ) : ?>
									<span class="f2000cs-status__badge is-active"><?php esc_html_e( 'Активне', 'factorial2000-catalog-sync' ); ?></span>
								<?php else : ?>
									<span class="f2000cs-status__badge is-inactive"><?php esc_html_e( 'Неактивне', 'factorial2000-catalog-sync' ); ?></span>
								<?php endif; ?>
							</li>

							<?php if ( $next_run ) : ?>
								<li>
									<span class="f2000cs-status__label"><?php esc_html_e( 'Наступний запуск', 'factorial2000-catalog-sync' ); ?></span>
									<span class="f2000cs-status__value"><?php echo esc_html( date_i18n( 'j M Y, H:i', $next_run ) ); ?></span>
								</li>
								<li>
									<span class="f2000cs-status__label"><?php esc_html_e( 'Інтервал', 'factorial2000-catalog-sync' ); ?></span>
									<span class="f2000cs-status__value"><?php echo esc_html( $interval_label ); ?></span>
								</li>
							<?php endif; ?>

							<?php if ( $bg_pending ) : ?>
								<li>
									<span class="f2000cs-status__label"><?php esc_html_e( 'Фонове оновлення', 'factorial2000-catalog-sync' ); ?></span>
									<span class="f2000cs-status__badge is-pending"><?php esc_html_e( 'В черзі', 'factorial2000-catalog-sync' ); ?></span>
								</li>
								<li>
									<span class="f2000cs-status__label"><?php esc_html_e( 'Заплановано на', 'factorial2000-catalog-sync' ); ?></span>
									<span class="f2000cs-status__value"><?php echo esc_html( date_i18n( 'j M Y, H:i', $bg_pending ) ); ?></span>
								</li>
							<?php endif; ?>
						</ul>
					</div>
				</section>
			</aside>
		</div>
	</div>
	<?php
}

/**
 * Handles the admin post actions for running the script and stopping cron jobs.
 *
 * @return void
 */
function f2000cs_handle_action() {
	if ( ! isset( $_POST['f2000cs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_nonce'] ) ), 'f2000cs_action' ) ) {
		wp_die( esc_html__( 'Помилка перевірки безпеки (nonce). Оновіть сторінку.', 'factorial2000-catalog-sync' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) );
	}

	if ( isset( $_POST['run_script'] ) ) {
		if ( function_exists( 'f2000cs_can_run_stock_update' ) && ! f2000cs_can_run_stock_update() ) {
			add_settings_error(
				'f2000cs_settings',
				'free_update_limit',
				function_exists( 'f2000cs_get_free_update_limit_message' )
					? f2000cs_get_free_update_limit_message()
					: __( 'Досягнуто денного ліміту оновлень.', 'factorial2000-catalog-sync' ),
				'error'
			);
			wp_safe_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
			exit;
		}

		$xml_urls = function_exists( 'f2000cs_get_active_supplier_urls' )
			? f2000cs_get_active_supplier_urls()
			: array();

		if ( empty( $xml_urls ) ) {
			$highest = function_exists( 'f2000cs_get_highest_saved_supplier_slot' )
				? f2000cs_get_highest_saved_supplier_slot()
				: 5;
			for ( $i = 1; $i <= $highest; $i++ ) {
				$url = get_option( 'f2000cs_url' . ( $i === 1 ? '' : '_' . $i ), '' );
				if ( ! empty( $url ) ) {
					$xml_urls[ $i ] = $url;
				}
			}
		}

		if ( ! empty( $xml_urls ) ) {
			if ( function_exists( 'f2000cs_record_stock_update_run' ) ) {
				f2000cs_record_stock_update_run();
			}

			$bg_option = isset( $_POST['use_background'] ) ? sanitize_text_field( wp_unslash( $_POST['use_background'] ) ) : 'no';

			if ( $bg_option === 'yes' ) {
				$started = false;
				foreach ( $xml_urls as $index => $xml_url ) {
					$sku_prefix = get_option( 'f2000cs_sku_prefix_' . $index, '' );
					if ( f2000cs_trigger_background_sync( $xml_url, $sku_prefix ) ) {
						$started = true;
					}
				}

				if ( $started ) {
					add_settings_error(
						'f2000cs_settings',
						'background_sync_started',
						__( 'Оновлення наявності заплановано у фоновому режимі.', 'factorial2000-catalog-sync' ),
						'updated'
					);
				}

				// Ensure cron is active for future scheduled runs
				if ( ! wp_next_scheduled( 'f2000cs_update_stock_cron' ) ) {
					\F2000CS\Cron_Job::deactivate();
					\F2000CS\Cron_Job::activate();
				}
			} else {
				// Run immediately without scheduling cron jobs.
				// Cloudflare / CDN proxies drop idle connections after ~100 s;
				// ignore_user_abort ensures the server still processes all
				// suppliers even when the browser connection is cut.
				ignore_user_abort( true );
				if ( function_exists( 'set_time_limit' ) ) {
					// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- Needed to avoid timeouts on large catalogs; failure is non-fatal.
					@set_time_limit( 600 );
				}

				try {

					$success_count = 0;
					$total_count   = count( $xml_urls );

					foreach ( $xml_urls as $index => $xml_url ) {
						try {
							$sku_prefix   = get_option( 'f2000cs_sku_prefix_' . $index, '' );
							$skip_price   = get_option( 'f2000cs_skip_price_' . $index, '0' );
							$price_adjust = f2000cs_get_price_adjust_settings( $index );
							$update_qty   = function_exists( 'f2000cs_supplier_updates_stock_qty' )
								? f2000cs_supplier_updates_stock_qty( $index )
								: false;
							$updater      = new \F2000CS\XML_Stock_Updater(
								$xml_url,
								$sku_prefix,
								( $skip_price === '1' || $skip_price === 'yes' || $skip_price === 'on' ),
								$price_adjust,
								$update_qty
							);
							$updater->update_products_stock_status();
							++$success_count;
						} catch ( Exception $e ) {
							$msg = sprintf(
								/* translators: 1: supplier slot number, 2: error message */
								__( 'Помилка оновлення постачальника #%1$d: %2$s', 'factorial2000-catalog-sync' ),
								(int) $index,
								$e->getMessage()
							);
							if ( function_exists( 'f2000cs_log' ) ) {
								f2000cs_log( $msg, 'error' );
							}
							if ( function_exists( 'f2000cs_send_telegram_notification' ) ) {
								f2000cs_send_telegram_notification( '❌ ' . $msg );
							}
							add_settings_error( 'f2000cs_settings', 'stock_update_error_' . (int) $index, $msg, 'error' );
						}
					}

					f2000cs_after_stock_update_complete();

					if ( $success_count > 0 ) {
						add_settings_error(
							'f2000cs_settings',
							'settings_updated',
							/* translators: 1: number of successfully updated XML files, 2: total number of XML files. */
							sprintf( __( 'Оновлення наявності завершено успішно для %1$d з %2$d XML-файлів.', 'factorial2000-catalog-sync' ), $success_count, $total_count ),
							'updated'
						);
					} else {
						add_settings_error(
							'f2000cs_settings',
							'update_error',
							__( 'Не вдалося оновити наявність для жодного XML-файлу.', 'factorial2000-catalog-sync' ),
							'error'
						);
					}
				} catch ( Exception $e ) {
					add_settings_error(
						'f2000cs_settings',
						'update_error',
						__( 'Помилка оновлення наявності: ', 'factorial2000-catalog-sync' ) . $e->getMessage(),
						'error'
					);
				}

				// Do NOT schedule any cron tasks here - we want to run just once
			}
		} else {
			add_settings_error(
				'f2000cs_settings',
				'missing_url',
				__( 'Спочатку вкажіть хоча б один URL XML.', 'factorial2000-catalog-sync' ),
				'error'
			);
		}
	}

	if ( isset( $_POST['f2000cs_stop'] ) ) {
		wp_clear_scheduled_hook( 'f2000cs_update_stock_cron' );
		// wp_clear_scheduled_hook() alone would not remove background events, since they
		// are always scheduled with non-empty (url, sku_prefix) args; this also resets
		// the pending-batch counter so a stray leftover value doesn't block the next run.
		f2000cs_clear_all_background_events();

		add_settings_error(
			'f2000cs_settings',
			'settings_updated',
			__( 'Cron-завдання зупинено.', 'factorial2000-catalog-sync' ),
			'updated'
		);
	}

	wp_safe_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
	exit;
}

add_action( 'admin_post_f2000cs_action', 'f2000cs_handle_action' );
