<?php

defined( 'ABSPATH' ) || exit;

/**
 * Render floating support widget markup.
 */
function prom_xml_importer_render_support_widget() {
	if ( ! prom_xml_importer_is_plugin_admin_screen() ) {
		return;
	}

	$card_number = '4874 1000 3871 2884';
	$monobank    = 'https://send.monobank.ua/jar/8CiFBAfJKK';
	$issues_url  = 'https://github.com/maximus2526/prom-xml-importer/issues';
	?>
	<div id="prom-xml-support" class="prom-xml-support">
		<div class="prom-xml-support__panel" role="dialog" aria-labelledby="prom-xml-support-title">
			<div class="prom-xml-support__header">
				<h2 id="prom-xml-support-title" class="prom-xml-support__title">
					<?php esc_html_e( 'Підтримка проекту', 'prom-xml-importer' ); ?>
				</h2>
				<button type="button" class="prom-xml-support__close" data-prom-support-close aria-label="<?php esc_attr_e( 'Закрити', 'prom-xml-importer' ); ?>">
					&times;
				</button>
			</div>

			<div class="prom-xml-support__body">
				<p class="prom-xml-support__text">
					<?php esc_html_e( 'Якщо цей інструмент заощадив ваші гроші на покупку дорогих модулів імпорту та допоміг вашому бізнесу, ви можете підтримати розробника.', 'prom-xml-importer' ); ?>
				</p>

				<div class="prom-xml-support__card">
					<span class="prom-xml-support__card-label"><?php esc_html_e( 'Номер картки', 'prom-xml-importer' ); ?></span>
					<div class="prom-xml-support__card-value">
						<span class="prom-xml-support__card-number"><?php echo esc_html( $card_number ); ?></span>
						<button type="button" class="prom-xml-support__copy" data-prom-support-copy>
							<?php esc_html_e( 'Копіювати', 'prom-xml-importer' ); ?>
						</button>
					</div>
					<span class="prom-xml-support__card-label"><?php esc_html_e( 'Максим Кляхін', 'prom-xml-importer' ); ?></span>
				</div>

				<div class="prom-xml-support__actions">
					<a class="prom-xml-support__btn prom-xml-support__btn--primary" href="<?php echo esc_url( $monobank ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Банка Monobank', 'prom-xml-importer' ); ?>
					</a>
					<a class="prom-xml-support__btn prom-xml-support__btn--ghost" href="<?php echo esc_url( $issues_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Знайшли баг? Створіть Issue', 'prom-xml-importer' ); ?>
					</a>
				</div>
			</div>
		</div>

		<button type="button" class="prom-xml-support__fab" data-prom-support-open>
			<span aria-hidden="true">☕</span>
			<?php esc_html_e( 'Підтримати', 'prom-xml-importer' ); ?>
		</button>
	</div>
	<?php
}
add_action( 'admin_footer', 'prom_xml_importer_render_support_widget' );
