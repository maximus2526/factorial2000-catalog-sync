<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class Frontend_Display
 *
 * Handles frontend display of vendor code for administrators only.
 */
class Frontend_Display {

	/**
	 * Initialize frontend display hooks.
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'display_vendor_code_footer' ), 10 );
	}

	/**
	 * Enqueue frontend vendor code assets.
	 */
	public static function enqueue_assets() {
		if ( ! is_product() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'prom-xml-frontend-vendor',
			PROM_XML_IMPORTER_URL . 'assets/css/frontend-vendor.css',
			array(),
			PROM_XML_IMPORTER_VERSION
		);

		wp_enqueue_script(
			'prom-xml-frontend-vendor',
			PROM_XML_IMPORTER_URL . 'assets/js/frontend-vendor.js',
			array(),
			PROM_XML_IMPORTER_VERSION,
			true
		);

		wp_localize_script(
			'prom-xml-frontend-vendor',
			'promXmlVendor',
			array(
				'copiedLabel' => __( '✓ Скопійовано!', 'prom-xml-importer' ),
			)
		);
	}

	/**
	 * Display vendor code in footer for administrators.
	 */
	public static function display_vendor_code_footer() {
		if ( ! is_product() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $product;

		if ( ! $product ) {
			return;
		}

		$product_id = $product->get_id();

		if ( $product->is_type( 'variable' ) ) {
			self::render_variable_vendor_footer( $product );
			return;
		}

		self::render_simple_vendor_footer( $product_id );
	}

	/**
	 * Render vendor codes for variable products.
	 *
	 * @param WC_Product $product Product object.
	 */
	private static function render_variable_vendor_footer( $product ) {
		$variation_ids = $product->get_children();

		if ( empty( $variation_ids ) ) {
			return;
		}

		$variations_with_vendor = array();

		foreach ( $variation_ids as $variation_id ) {
			$vendor_code = get_post_meta( $variation_id, 'prom-xml-updater-vendor', true );

			if ( empty( $vendor_code ) ) {
				continue;
			}

			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}

			$variations_with_vendor[] = array(
				'attributes'  => wc_get_formatted_variation( $variation, true ),
				'vendor_code' => $vendor_code,
			);
		}

		if ( empty( $variations_with_vendor ) ) {
			$parent_vendor = get_post_meta( $product->get_id(), 'prom-xml-updater-vendor', true );
			if ( empty( $parent_vendor ) ) {
				return;
			}

			$variations_with_vendor[] = array(
				'attributes'  => __( 'Only parent product without variations', 'prom-xml-importer' ),
				'vendor_code' => $parent_vendor,
			);
		}

		?>
		<div class="prom-vendor-code-footer prom-vendor-code-footer--variable">
			<div class="prom-vendor-code-footer__inner">
				<h4 class="prom-vendor-code-footer__title">
					<?php esc_html_e( 'Інформація для менеджерів (vendorCode) - клікніть для копіювання', 'prom-xml-importer' ); ?>
				</h4>
				<div class="prom-vendor-code-footer__list">
					<?php foreach ( $variations_with_vendor as $variation_info ) : ?>
						<div class="prom-vendor-code-footer__item">
							<strong><?php echo wp_kses_post( $variation_info['attributes'] ); ?>:</strong>
							<span
								class="vendor-code-copy vendor-code-copy--variation"
								data-code="<?php echo esc_attr( $variation_info['vendor_code'] ); ?>"
								title="<?php esc_attr_e( 'Клікніть для копіювання', 'prom-xml-importer' ); ?>"
							>
								<?php echo esc_html( $variation_info['vendor_code'] ); ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render vendor code for simple products.
	 *
	 * @param int $product_id Product ID.
	 */
	private static function render_simple_vendor_footer( $product_id ) {
		$vendor_code = get_post_meta( $product_id, 'prom-xml-updater-vendor', true );

		if ( empty( $vendor_code ) ) {
			return;
		}

		?>
		<div class="prom-vendor-code-footer prom-vendor-code-footer--simple">
			<div class="prom-vendor-code-footer__inner prom-vendor-code-footer__inner--simple">
				<strong class="prom-vendor-code-footer__title prom-vendor-code-footer__title--simple">
					<?php esc_html_e( 'Інформація для менеджерів - клікніть для копіювання', 'prom-xml-importer' ); ?>
				</strong>
				<span
					class="vendor-code-copy vendor-code-copy--simple"
					data-code="<?php echo esc_attr( $vendor_code ); ?>"
					title="<?php esc_attr_e( 'Клікніть для копіювання', 'prom-xml-importer' ); ?>"
				>
					<?php echo esc_html( 'Vendor Code: ' . $vendor_code ); ?>
				</span>
			</div>
		</div>
		<?php
	}
}
