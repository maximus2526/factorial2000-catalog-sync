<?php
/**
 * «Категорія Prom» picker: a Select2 (selectWoo) that searches the Prom category list by name, stores the id.
 *
 * Used on the Marketplaces page (default category, store category → Prom table)
 * and as the «Prom.ua» box in the product editor, whose value goes to the feed as
 * the offer's <portal_category_id> (Prom ranks it above the group's portal_id).
 *
 * Premium-only file (see the @fs_premium_only header of the main plugin file).
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

use F2000CS\Marketplace_WC_Source;
use F2000CS\Prom_Categories;

/**
 * Link to the official Prom category list (ids) for people who want to look a number up.
 *
 * @return string HTML paragraph.
 */
function f2000cs_prom_category_reference_link(): string {
	return '<a href="' . esc_url( 'https://my.prom.ua/cabinet/export_categories/xls' ) . '" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'Довідник категорій Prom з номерами (ID) — XLS', 'factorial2000-catalog-sync' )
		. '</a>';
}

/**
 * Select2 with the chosen Prom category (the id is the value).
 *
 * Typing words (or the start of an id) searches the bundled Prom list over AJAX;
 * the value stays a plain number, so it is saved like before. An id that is not
 * in the list stays selectable and is flagged, so nothing is lost silently.
 *
 * @param string $name  Field name.
 * @param string $value Stored id.
 * @param array  $args  Optional: id, class, placeholder.
 * @return void
 */
function f2000cs_prom_category_input( string $name, string $value, array $args = array() ): void {
	$value = Prom_Categories::sanitize_id( $value );
	$path  = '' !== $value ? Prom_Categories::path( $value ) : '';
	$id    = (string) ( $args['id'] ?? '' );
	$class = trim( 'f2000cs-prom-cat ' . (string) ( $args['class'] ?? '' ) );
	?>
	<span class="f2000cs-prom-cat-wrap">
		<select name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $class ); ?>" <?php echo '' !== $id ? 'id="' . esc_attr( $id ) . '"' : ''; ?> data-placeholder="<?php echo esc_attr( (string) ( $args['placeholder'] ?? __( 'Назва або номер категорії Prom', 'factorial2000-catalog-sync' ) ) ); ?>">
			<option value=""></option>
			<?php if ( '' !== $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected data-unknown="<?php echo '' === $path ? '1' : '0'; ?>"><?php echo esc_html( '' !== $path ? $path . ' (#' . $value . ')' : '#' . $value . ' — ' . __( 'немає в довіднику Prom', 'factorial2000-catalog-sync' ) ); ?></option>
			<?php endif; ?>
		</select>
	</span>
	<?php
}

/**
 * AJAX: search the Prom category list.
 *
 * @return void
 */
function f2000cs_prom_category_ajax_search() {
	check_ajax_referer( 'f2000cs_prom_category_search', 'nonce' );

	if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

	wp_send_json_success( Prom_Categories::search( $query, 30 ) );
}
add_action( 'wp_ajax_f2000cs_prom_category_search', 'f2000cs_prom_category_ajax_search' );

/**
 * «Prom.ua» box in the product editor.
 *
 * @return void
 */
function f2000cs_prom_category_add_meta_box() {
	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		return;
	}

	add_meta_box(
		'f2000cs-prom-category',
		__( 'Prom.ua', 'factorial2000-catalog-sync' ),
		'f2000cs_prom_category_render_meta_box',
		'product',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_product', 'f2000cs_prom_category_add_meta_box' );

/**
 * Content of the product box.
 *
 * @param WP_Post $post Product post.
 * @return void
 */
function f2000cs_prom_category_render_meta_box( $post ) {
	wp_nonce_field( 'f2000cs_prom_category_save', 'f2000cs_prom_category_nonce' );
	?>
	<p><label for="f2000cs-prom-category-id"><strong><?php esc_html_e( 'Категорія Prom', 'factorial2000-catalog-sync' ); ?></strong></label></p>
	<?php
	f2000cs_prom_category_input(
		'f2000cs_prom_category_id',
		(string) get_post_meta( $post->ID, Marketplace_WC_Source::PROM_CATEGORY_META, true ),
		array(
			'id'    => 'f2000cs-prom-category-id',
			'class' => 'widefat',
		)
	);
	?>
	<p class="description"><?php echo f2000cs_prom_category_reference_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?></p>
	<p class="description"><?php esc_html_e( 'Категорія маркетплейсу, у якій Prom опублікує цей товар (portal_category_id у вигрузці). Важливіша за правила категорій на сторінці «Маркетплейси»; різновиди беруть її з основного товару. Залиште порожнім, щоб діяли правила.', 'factorial2000-catalog-sync' ); ?></p>
	<?php
}

/**
 * Save the product box.
 *
 * @param int $post_id Product id.
 * @return void
 */
function f2000cs_prom_category_save( $post_id ) {
	if ( ! isset( $_POST['f2000cs_prom_category_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_prom_category_nonce'] ) ), 'f2000cs_prom_category_save' ) ) {
		return;
	}

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$value = isset( $_POST['f2000cs_prom_category_id'] ) ? Prom_Categories::sanitize_id( sanitize_text_field( wp_unslash( $_POST['f2000cs_prom_category_id'] ) ) ) : '';

	if ( '' === $value ) {
		delete_post_meta( $post_id, Marketplace_WC_Source::PROM_CATEGORY_META );
	} else {
		update_post_meta( $post_id, Marketplace_WC_Source::PROM_CATEGORY_META, $value );
	}
}
add_action( 'save_post_product', 'f2000cs_prom_category_save' );

/**
 * Picker script and styles on the Marketplaces page and the product editor.
 *
 * @param string $hook Admin page hook suffix.
 * @return void
 */
function f2000cs_prom_category_enqueue( $hook ) {
	$hook       = (string) $hook;
	$on_product = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && function_exists( 'get_current_screen' ) && get_current_screen() && 'product' === get_current_screen()->post_type;

	if ( false === strpos( $hook, 'f2000cs-marketplaces' ) && ! $on_product ) {
		return;
	}

	// selectWoo ships with WooCommerce; register it ourselves if its assets were not set up yet.
	if ( ! wp_script_is( 'selectWoo', 'registered' ) && defined( 'WC_PLUGIN_FILE' ) ) {
		wp_register_script( 'selectWoo', plugins_url( 'assets/js/selectWoo/selectWoo.full.min.js', WC_PLUGIN_FILE ), array( 'jquery' ), '1.0.6', true );
	}

	if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}

	wp_enqueue_script(
		'f2000cs-admin-prom-category',
		F2000CS_URL . 'assets/js/admin-prom-category.js',
		array( 'jquery', 'selectWoo' ),
		F2000CS_VERSION,
		true
	);

	wp_localize_script(
		'f2000cs-admin-prom-category',
		'f2000csPromCategory',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'f2000cs_prom_category_search' ),
			'i18n'    => array(
				'nothing'   => __( 'Нічого не знайдено', 'factorial2000-catalog-sync' ),
				'tooShort'  => __( 'Введіть хоча б 2 символи назви або початок номера', 'factorial2000-catalog-sync' ),
				'searching' => __( 'Шукаю…', 'factorial2000-catalog-sync' ),
			),
		)
	);

	wp_register_style( 'f2000cs-admin-prom-category', false, array(), F2000CS_VERSION );
	wp_enqueue_style( 'f2000cs-admin-prom-category' );
	wp_add_inline_style(
		'f2000cs-admin-prom-category',
		'.f2000cs-prom-cat-wrap{display:block;min-width:260px;max-width:100%}'
		. '#f2000cs-prom-category .f2000cs-prom-cat-wrap{min-width:0}'
		. '.f2000cs-prom-cat-wrap .select2-container{width:100%!important}'
		. '.f2000cs-prom-cat-opt small{opacity:.7;margin-left:6px}'
		. '.f2000cs-prom-cat-unknown{color:#b32d2e}'
	);
}
add_action( 'admin_enqueue_scripts', 'f2000cs_prom_category_enqueue', 20 );
