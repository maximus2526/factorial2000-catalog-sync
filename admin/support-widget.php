<?php

defined( 'ABSPATH' ) || exit;

/**
 * Render floating support widget markup.
 */
function f2cs_render_support_widget() {
	if ( ! f2cs_is_plugin_admin_screen() ) {
		return;
	}

	$card_number = '4874 1000 3871 2884';
	$monobank    = 'https://send.monobank.ua/jar/8CiFBAfJKK';
	$issues_url  = 'https://github.com/maximus2526/factorial2000-catalog-sync/issues';
	?>
	<div id="f2cs-support" class="f2cs-support">
		<div class="f2cs-support__panel" role="dialog" aria-labelledby="f2cs-support-title">
			<div class="f2cs-support__header">
				<h2 id="f2cs-support-title" class="f2cs-support__title">
					<?php esc_html_e( 'Підтримка проекту', 'factorial2000-catalog-sync' ); ?>
				</h2>
				<button type="button" class="f2cs-support__close" data-f2cs-support-close aria-label="<?php esc_attr_e( 'Закрити', 'factorial2000-catalog-sync' ); ?>">
					&times;
				</button>
			</div>

			<div class="f2cs-support__body">
				<p class="f2cs-support__text">
					<?php esc_html_e( 'Якщо цей інструмент заощадив ваші гроші на покупку дорогих модулів імпорту та допоміг вашому бізнесу, ви можете підтримати розробника.', 'factorial2000-catalog-sync' ); ?>
				</p>

				<div class="f2cs-support__card">
					<span class="f2cs-support__card-label"><?php esc_html_e( 'Номер картки', 'factorial2000-catalog-sync' ); ?></span>
					<div class="f2cs-support__card-value">
						<span class="f2cs-support__card-number"><?php echo esc_html( $card_number ); ?></span>
						<button type="button" class="f2cs-support__copy" data-f2cs-support-copy>
							<?php esc_html_e( 'Копіювати', 'factorial2000-catalog-sync' ); ?>
						</button>
					</div>
					<span class="f2cs-support__card-label"><?php esc_html_e( 'Максим Кляхін', 'factorial2000-catalog-sync' ); ?></span>
				</div>

				<div class="f2cs-support__actions">
					<a class="f2cs-support__btn f2cs-support__btn--primary" href="<?php echo esc_url( $monobank ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Банка Monobank', 'factorial2000-catalog-sync' ); ?>
					</a>
					<a class="f2cs-support__btn f2cs-support__btn--ghost" href="<?php echo esc_url( $issues_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Знайшли баг? Створіть Issue', 'factorial2000-catalog-sync' ); ?>
					</a>
				</div>
			</div>
		</div>

		<button type="button" class="f2cs-support__fab" data-f2cs-support-open>
			<span aria-hidden="true">☕</span>
			<?php esc_html_e( 'Підтримати', 'factorial2000-catalog-sync' ); ?>
		</button>
	</div>
	<?php
}
add_action( 'admin_footer', 'f2cs_render_support_widget' );
