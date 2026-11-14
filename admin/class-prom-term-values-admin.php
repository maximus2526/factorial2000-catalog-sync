<?php
/**
 * Term edit fields for per-category Prom values.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a small editor to product-attribute term screens.
 */
class Prom_Term_Values_Admin {

	/**
	 * Nonce action.
	 */
	const NONCE = 'f2000cs_prom_term_values';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_fields' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_ajax_f2000cs_prom_value_search', array( __CLASS__, 'ajax_search' ) );
	}

	/**
	 * Register fields for every Product Attribute taxonomy.
	 *
	 * @return void
	 */
	public static function register_fields(): void {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		add_action( 'edited_term', array( __CLASS__, 'save_term' ), 10, 3 );
		add_action( 'created_term', array( __CLASS__, 'save_term' ), 10, 3 );

		foreach ( (array) wc_get_attribute_taxonomies() as $taxonomy ) {
			if ( ! is_object( $taxonomy ) || empty( $taxonomy->attribute_name ) ) {
				continue;
			}

			$name = 'pa_' . (string) $taxonomy->attribute_name;

			add_action( $name . '_edit_form_fields', array( __CLASS__, 'render_edit_field' ) );
			add_action( $name . '_add_form_fields', array( __CLASS__, 'render_add_field' ) );
		}
	}

	/**
	 * Existing term screen.
	 *
	 * @param \WP_Term $term Term.
	 * @return void
	 */
	public static function render_edit_field( $term ): void {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		self::render_textarea( (int) $term->term_id, Prom_Term_Values::format_for_editor( (int) $term->term_id ) );
	}

	/**
	 * New term screen.
	 *
	 * @return void
	 */
	public static function render_add_field(): void {
		self::render_textarea( 0, '' );
	}

	/**
	 * Render the field.
	 *
	 * @param int    $term_id Term id.
	 * @param string $value   Current value.
	 * @return void
	 */
	private static function render_textarea( $term_id, $value ): void {
		unset( $term_id );
		wp_nonce_field( self::NONCE, 'f2000cs_prom_term_values_nonce' );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="f2000cs_prom_value_map"><?php esc_html_e( 'Значення для Prom за категорією', 'factorial2000-catalog-sync' ); ?></label>
			</th>
			<td>
				<div class="f2000cs-prom-term-map-add">
					<div class="f2000cs-prom-term-map-field">
						<label for="f2000cs-prom-term-category"><?php esc_html_e( 'Категорія Prom', 'factorial2000-catalog-sync' ); ?></label>
						<?php f2000cs_prom_category_input( 'f2000cs_prom_term_category', '', array( 'id' => 'f2000cs-prom-term-category' ) ); ?>
					</div>
					<div class="f2000cs-prom-term-map-field">
						<label for="f2000cs-prom-term-value"><?php esc_html_e( 'Офіційне значення', 'factorial2000-catalog-sync' ); ?></label>
						<select id="f2000cs-prom-term-value" class="f2000cs-prom-value"></select>
					</div>
					<button type="button" class="button f2000cs-prom-term-add"><?php esc_html_e( 'Додати', 'factorial2000-catalog-sync' ); ?></button>
				</div>
				<textarea name="f2000cs_prom_value_map" id="f2000cs_prom_value_map" rows="4" class="large-text code" placeholder="30807=Чорний"><?php echo esc_textarea( $value ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Один рядок: ID категорії Prom=офіційне значення. Порожньо — у фід іде поточна назва терміна.', 'factorial2000-catalog-sync' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save a term map.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function save_term( $term_id, $tt_id, $taxonomy ): void {
		unset( $tt_id );

		if ( 0 !== strpos( (string) $taxonomy, 'pa_' ) || ! isset( $_POST['f2000cs_prom_value_map'] ) ) {
			return;
		}

		if ( ! isset( $_POST['f2000cs_prom_term_values_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_prom_term_values_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', (int) $term_id ) ) {
			return;
		}

		$map = sanitize_textarea_field( wp_unslash( $_POST['f2000cs_prom_value_map'] ) );
		Prom_Term_Values::save_for_term( (int) $term_id, $map );
	}

	/**
	 * Enqueue the term-value picker on product attribute screens.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public static function enqueue( $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! in_array( (string) $hook, array( 'term.php', 'edit-tags.php' ), true ) || ! $screen || 0 !== strpos( (string) $screen->taxonomy, 'pa_' ) ) {
			return;
		}

		if ( ! wp_script_is( 'selectWoo', 'registered' ) && defined( 'WC_PLUGIN_FILE' ) ) {
			wp_register_script( 'selectWoo', plugins_url( 'assets/js/selectWoo/selectWoo.full.min.js', WC_PLUGIN_FILE ), array( 'jquery' ), '1.0.6', true );
		}

		wp_enqueue_script(
			'f2000cs-admin-prom-term-values',
			F2000CS_URL . 'assets/js/admin-prom-term-values.js',
			array( 'jquery', 'selectWoo' ),
			f2000cs_asset_version( 'assets/js/admin-prom-term-values.js' ),
			true
		);

		wp_localize_script(
			'f2000cs-admin-prom-term-values',
			'f2000csPromTermValues',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'f2000cs_prom_value_search' ),
				'i18n'    => array(
					'nothing'   => __( 'Нічого не знайдено', 'factorial2000-catalog-sync' ),
					'searching' => __( 'Шукаю…', 'factorial2000-catalog-sync' ),
				),
			)
		);
	}

	/**
	 * AJAX: official values of one Prom category.
	 *
	 * @return void
	 */
	public static function ajax_search(): void {
		check_ajax_referer( 'f2000cs_prom_value_search', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
		}

		$category = isset( $_GET['category'] ) ? Prom_Categories::sanitize_id( sanitize_text_field( wp_unslash( $_GET['category'] ) ) ) : '';
		$query    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		wp_send_json_success( Prom_Values::search( $category, $query, 30 ) );
	}
}
