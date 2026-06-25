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
					<?php esc_html_e( 'Підтримка проекту', 'xml-prom' ); ?>
				</h2>
				<button type="button" class="prom-xml-support__close" data-prom-support-close aria-label="<?php esc_attr_e( 'Закрити', 'xml-prom' ); ?>">
					&times;
				</button>
			</div>

			<div class="prom-xml-support__body">
				<p class="prom-xml-support__text">
					<?php esc_html_e( 'Якщо цей інструмент заощадив ваші гроші на покупку дорогих модулів імпорту та допоміг вашому бізнесу, ви можете підтримати розробника.', 'xml-prom' ); ?>
				</p>

				<div class="prom-xml-support__card">
					<span class="prom-xml-support__card-label"><?php esc_html_e( 'Номер картки', 'xml-prom' ); ?></span>
					<div class="prom-xml-support__card-value">
						<span class="prom-xml-support__card-number"><?php echo esc_html( $card_number ); ?></span>
						<button type="button" class="prom-xml-support__copy" data-prom-support-copy>
							<?php esc_html_e( 'Копіювати', 'xml-prom' ); ?>
						</button>
					</div>
					<span class="prom-xml-support__card-label"><?php esc_html_e( 'Максим К.', 'xml-prom' ); ?></span>
				</div>

				<div class="prom-xml-support__actions">
					<a class="prom-xml-support__btn prom-xml-support__btn--primary" href="<?php echo esc_url( $monobank ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Банка Monobank', 'xml-prom' ); ?>
					</a>
					<a class="prom-xml-support__btn prom-xml-support__btn--ghost" href="<?php echo esc_url( $issues_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Знайшли баг? Створіть Issue', 'xml-prom' ); ?>
					</a>
				</div>
			</div>
		</div>

		<button type="button" class="prom-xml-support__fab" data-prom-support-open>
			<span aria-hidden="true">☕</span>
			<?php esc_html_e( 'Підтримати', 'xml-prom' ); ?>
		</button>
	</div>
	<?php
}
add_action( 'admin_footer', 'prom_xml_importer_render_support_widget' );
