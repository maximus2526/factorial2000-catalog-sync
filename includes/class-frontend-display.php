<?php

namespace F2CS;

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
			'f2cs-frontend-vendor',
			F2CS_URL . 'assets/css/frontend-vendor.css',
			array(),
			F2CS_VERSION
		);

		wp_enqueue_script(
			'f2cs-frontend-vendor',
			F2CS_URL . 'assets/js/frontend-vendor.js',
			array(),
			F2CS_VERSION,
			true
		);

		wp_localize_script(
			'f2cs-frontend-vendor',
			'f2csVendor',
			array(
				'copiedLabel' => __( '✓ Скопійовано!', 'factorial2000-catalog-sync' ),
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
			$vendor_code = get_post_meta( $variation_id, 'f2cs-updater-vendor', true );

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
			$parent_vendor = get_post_meta( $product->get_id(), 'f2cs-updater-vendor', true );
			if ( empty( $parent_vendor ) ) {
				return;
			}

			$variations_with_vendor[] = array(
				'attributes'  => __( 'Only parent product without variations', 'factorial2000-catalog-sync' ),
				'vendor_code' => $parent_vendor,
			);
		}

		?>
		<div class="f2cs-vendor-code-footer f2cs-vendor-code-footer--variable">
			<div class="f2cs-vendor-code-footer__inner">
				<h4 class="f2cs-vendor-code-footer__title">
					<?php esc_html_e( 'Інформація для менеджерів (vendorCode) - клікніть для копіювання', 'factorial2000-catalog-sync' ); ?>
				</h4>
				<div class="f2cs-vendor-code-footer__list">
					<?php foreach ( $variations_with_vendor as $variation_info ) : ?>
						<div class="f2cs-vendor-code-footer__item">
							<strong><?php echo wp_kses_post( $variation_info['attributes'] ); ?>:</strong>
							<span
								class="vendor-code-copy vendor-code-copy--variation"
								data-code="<?php echo esc_attr( $variation_info['vendor_code'] ); ?>"
								title="<?php esc_attr_e( 'Клікніть для копіювання', 'factorial2000-catalog-sync' ); ?>"
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
		$vendor_code = get_post_meta( $product_id, 'f2cs-updater-vendor', true );

		if ( empty( $vendor_code ) ) {
			return;
		}

		?>
		<div class="f2cs-vendor-code-footer f2cs-vendor-code-footer--simple">
			<div class="f2cs-vendor-code-footer__inner f2cs-vendor-code-footer__inner--simple">
				<strong class="f2cs-vendor-code-footer__title f2cs-vendor-code-footer__title--simple">
					<?php esc_html_e( 'Інформація для менеджерів - клікніть для копіювання', 'factorial2000-catalog-sync' ); ?>
				</strong>
				<span
					class="vendor-code-copy vendor-code-copy--simple"
					data-code="<?php echo esc_attr( $vendor_code ); ?>"
					title="<?php esc_attr_e( 'Клікніть для копіювання', 'factorial2000-catalog-sync' ); ?>"
				>
					<?php echo esc_html( 'Vendor Code: ' . $vendor_code ); ?>
				</span>
			</div>
		</div>
		<?php
	}
}
