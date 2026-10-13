<?php
/**
 * Placeholders shown in the free build where a Pro module lives.
 *
 * The Pro modules (sync rules, image processing, fields updater, XML export filter
 * and editor, marketplaces) are premium-only files that Freemius removes from the
 * free (wp.org) build. Each placeholder is declared only when the real
 * implementation is missing, so the Pro build is unaffected.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'f2000cs_render_import_images_panel' ) ) {
	/**
	 * Import-page image processing panel (Pro placeholder).
	 *
	 * @return void
	 */
	function f2000cs_render_import_images_panel() {
		?>
		<section id="f2000cs_images_section" class="f2000cs-settings-panel f2000cs-import-images">
			<header class="f2000cs-settings-panel__head">
				<h2 class="f2000cs-settings-panel__title">
					<?php esc_html_e( 'Зображення при імпорті', 'factorial2000-catalog-sync' ); ?>
					<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
				</h2>
			</header>
			<div class="f2000cs-settings-panel__body">
				<p class="description"><?php esc_html_e( 'У Pro-версії: конвертація фото у WebP, AVIF або JPG, оптимізація якості та зменшення розміру під час імпорту.', 'factorial2000-catalog-sync' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( function_exists( 'f2000cs_get_upgrade_url' ) ? f2000cs_get_upgrade_url() : '#' ); ?>"><?php esc_html_e( 'Дізнатися про Pro', 'factorial2000-catalog-sync' ); ?></a></p>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'f2000cs_export_page' ) ) {
	/**
	 * Export settings page (Pro placeholder): XML export filter + XML editor.
	 *
	 * @return void
	 */
	function f2000cs_export_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		f2000cs_render_admin_page_title( __( 'Налаштування вигрузки', 'factorial2000-catalog-sync' ) );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Фільтр XML-вигрузки та редактор вигрузок XML входять до Pro-версії.', 'factorial2000-catalog-sync' );
		echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Оновити до Pro', 'factorial2000-catalog-sync' ) . '</a>';
		echo '</p></div></div>';
	}
}

if ( ! function_exists( 'f2000cs_marketplaces_page' ) ) {
	/**
	 * Marketplaces page (Pro placeholder): export to Prom.ua and other marketplaces.
	 *
	 * @return void
	 */
	function f2000cs_marketplaces_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		f2000cs_render_admin_page_title( __( 'Маркетплейси', 'factorial2000-catalog-sync' ) );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Вигрузка товарів WooCommerce на Prom.ua (далі — Rozetka, Hotline) входить до Pro-версії.', 'factorial2000-catalog-sync' );
		echo ' <a href="' . esc_url( f2000cs_get_upgrade_url() ) . '">' . esc_html__( 'Оновити до Pro', 'factorial2000-catalog-sync' ) . '</a>';
		echo '</p></div></div>';
	}
}

if ( ! function_exists( 'f2000cs_render_supplier_qty_field' ) ) {
	/**
	 * Supplier card stock quantity option (Pro only, nothing in the free build).
	 *
	 * @param string $slot Slot number or __INDEX__.
	 * @return void
	 */
	function f2000cs_render_supplier_qty_field( $slot ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Same signature as the Pro version.
}

if ( ! function_exists( 'f2000cs_interval_render' ) ) {
	/**
	 * Update interval (free build: once a day).
	 *
	 * @return void
	 */
	function f2000cs_interval_render() {
		?>
		<strong><?php esc_html_e( 'Щодня', 'factorial2000-catalog-sync' ); ?></strong>
		<p class="description">
			<?php esc_html_e( 'Автоматичне оновлення — раз на добу. Частіші інтервали (аж до що 5 хвилин), оновлення кількості та правило для варіативних товарів — у Pro.', 'factorial2000-catalog-sync' ); ?>
			<a href="<?php echo esc_url( f2000cs_get_upgrade_url() ); ?>"><?php esc_html_e( 'Дізнатися про Pro', 'factorial2000-catalog-sync' ); ?></a>
		</p>
		<?php
	}
}

if ( ! function_exists( 'f2000cs_render_import_stock_qty_option' ) ) {
	/**
	 * Import page stock quantity option (Pro only, nothing in the free build).
	 *
	 * @param bool $is_pro Whether Pro is unlocked.
	 * @return void
	 */
	function f2000cs_render_import_stock_qty_option( $is_pro ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Same signature as the Pro version.
}

if ( ! function_exists( 'f2000cs_render_update_fields_panel' ) ) {
	/**
	 * «Оновити окремі поля» tab (Pro placeholder).
	 *
	 * @param bool $is_pro Whether Pro is unlocked.
	 * @return void
	 */
	function f2000cs_render_update_fields_panel( $is_pro ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Same signature as the Pro version.
		?>
		<div class="f2000cs-update-fields">
			<p class="description">
				<?php esc_html_e( 'У Pro-версії: оновлення окремих полів уже імпортованих товарів (назва, опис, ціни, фото, атрибути, категорії, теги, vendorCode) за SKU з XML.', 'factorial2000-catalog-sync' ); ?>
			</p>
			<p><a class="button" href="<?php echo esc_url( f2000cs_get_upgrade_url() ); ?>"><?php esc_html_e( 'Дізнатися про Pro', 'factorial2000-catalog-sync' ); ?></a></p>
		</div>
		<?php
	}
}
