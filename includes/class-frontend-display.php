<?php

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Frontend_Display
 *
 * Handles frontend display of vendor code for administrators only.
 */
class Frontend_Display {

	const OPTION_SHOW_VENDOR_CODE = 'f2000cs_show_vendor_code';

	/**
	 * Whether the admin vendor-code panel is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_SHOW_VENDOR_CODE, '1' );
	}

	/**
	 * Initialize frontend display hooks.
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'display_vendor_code_footer' ), 10 );
	}

	/**
	 * Enqueue frontend vendor code assets.
	 */
	public static function enqueue_assets() {
		if ( ! self::is_enabled() || ! is_product() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'f2000cs-frontend-vendor',
			F2000CS_URL . 'assets/css/frontend-vendor.css',
			array(),
			F2000CS_VERSION
		);

		wp_enqueue_script(
			'f2000cs-frontend-vendor',
			F2000CS_URL . 'assets/js/frontend-vendor.js',
			array(),
			F2000CS_VERSION,
			true
		);

		wp_localize_script(
			'f2000cs-frontend-vendor',
			'f2000csVendor',
			array(
				'copiedLabel'   => __( '✓ Скопійовано!', 'factorial2000-catalog-sync' ),
				'collapseLabel' => __( 'Згорнути', 'factorial2000-catalog-sync' ),
				'expandLabel'   => __( 'Розгорнути', 'factorial2000-catalog-sync' ),
				'closeLabel'    => __( 'Закрити', 'factorial2000-catalog-sync' ),
			)
		);
	}

	/**
	 * Display vendor code in footer for administrators.
	 */
	public static function display_vendor_code_footer() {
		if ( ! self::is_enabled() || ! is_product() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $product is the standard WooCommerce template global.
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $product is the standard WooCommerce template global.
			$product = wc_get_product( get_queried_object_id() );
		}

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
	 * Render header toolbar with collapse / close controls.
	 *
	 * @param string $title Panel title.
	 * @return void
	 */
	private static function render_panel_header( $title ) {
		?>
		<div class="f2000cs-vendor-code-footer__header">
			<strong class="f2000cs-vendor-code-footer__title"><?php echo esc_html( $title ); ?></strong>
			<div class="f2000cs-vendor-code-footer__actions">
				<button
					type="button"
					class="f2000cs-vendor-code-footer__btn f2000cs-vendor-code-footer__btn--collapse"
					aria-expanded="true"
					title="<?php esc_attr_e( 'Згорнути', 'factorial2000-catalog-sync' ); ?>"
				>
					<?php esc_html_e( 'Згорнути', 'factorial2000-catalog-sync' ); ?>
				</button>
				<button
					type="button"
					class="f2000cs-vendor-code-footer__btn f2000cs-vendor-code-footer__btn--close"
					title="<?php esc_attr_e( 'Закрити', 'factorial2000-catalog-sync' ); ?>"
				>
					<?php esc_html_e( 'Закрити', 'factorial2000-catalog-sync' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render vendor codes for variable products.
	 *
	 * @param \WC_Product $product Product object.
	 */
	private static function render_variable_vendor_footer( $product ) {
		$variation_ids = $product->get_children();

		if ( empty( $variation_ids ) ) {
			return;
		}

		$variations_with_vendor = array();

		foreach ( $variation_ids as $variation_id ) {
			$vendor_code = get_post_meta( $variation_id, 'f2000cs-updater-vendor', true );

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
			$parent_vendor = get_post_meta( $product->get_id(), 'f2000cs-updater-vendor', true );
			if ( empty( $parent_vendor ) ) {
				return;
			}

			$variations_with_vendor[] = array(
				'attributes'  => __( 'Лише батьківський товар без варіацій', 'factorial2000-catalog-sync' ),
				'vendor_code' => $parent_vendor,
			);
		}

		?>
		<div
			class="f2000cs-vendor-code-footer f2000cs-vendor-code-footer--variable"
			data-f2000cs-vendor-panel
			data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
		>
			<div class="f2000cs-vendor-code-footer__inner">
				<?php
				self::render_panel_header(
					__( 'Інформація для менеджерів (vendorCode) - клікніть для копіювання', 'factorial2000-catalog-sync' )
				);
				?>
				<div class="f2000cs-vendor-code-footer__body">
					<div class="f2000cs-vendor-code-footer__list">
						<?php foreach ( $variations_with_vendor as $variation_info ) : ?>
							<div class="f2000cs-vendor-code-footer__item">
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
		</div>
		<?php
	}

	/**
	 * Render vendor code for simple products.
	 *
	 * @param int $product_id Product ID.
	 */
	private static function render_simple_vendor_footer( $product_id ) {
		$vendor_code = get_post_meta( $product_id, 'f2000cs-updater-vendor', true );

		if ( empty( $vendor_code ) ) {
			return;
		}

		?>
		<div
			class="f2000cs-vendor-code-footer f2000cs-vendor-code-footer--simple"
			data-f2000cs-vendor-panel
			data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
		>
			<div class="f2000cs-vendor-code-footer__inner">
				<?php
				self::render_panel_header(
					__( 'Інформація для менеджерів - клікніть для копіювання', 'factorial2000-catalog-sync' )
				);
				?>
				<div class="f2000cs-vendor-code-footer__body f2000cs-vendor-code-footer__body--simple">
					<span
						class="vendor-code-copy vendor-code-copy--simple"
						data-code="<?php echo esc_attr( $vendor_code ); ?>"
						title="<?php esc_attr_e( 'Клікніть для копіювання', 'factorial2000-catalog-sync' ); ?>"
					>
						<?php echo esc_html( 'Vendor Code: ' . $vendor_code ); ?>
					</span>
				</div>
			</div>
		</div>
		<?php
	}
}
