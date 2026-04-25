<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class Frontend_Display
 * 
 * Handles frontend display of vendor code for administrators only
 */
class Frontend_Display {

	/**
	 * Initialize frontend display hooks
	 */
	public static function init() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Display vendor code in footer (only on product pages)
		add_action( 'wp_footer', array( __CLASS__, 'display_vendor_code_footer' ), 10 );
	}

	/**
	 * Display vendor code in footer for administrators
	 */
	public static function display_vendor_code_footer() {
		// Only on single product pages
		if ( ! is_product() ) {
			return;
		}

		// Only show to logged-in administrators
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $product;

		if ( ! $product ) {
			return;
		}

		$product_id = $product->get_id();

		// Handle variable products with variations
		if ( $product->is_type( 'variable' ) ) {
			$variation_ids = $product->get_children();

			if ( empty( $variation_ids ) ) {
				return;
			}

			$variations_with_vendor = array();

			foreach ( $variation_ids as $variation_id ) {
				$vendor_code = get_post_meta( $variation_id, 'prom-xml-updater-vendor', true );

				if ( ! empty( $vendor_code ) ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation ) {
						$attribute_string = wc_get_formatted_variation( $variation, true );

						$variations_with_vendor[] = array(
							'id'          => $variation_id,
							'attributes'  => $attribute_string,
							'vendor_code' => $vendor_code,
						);
					}
				}
			}

			if ( empty( $variations_with_vendor ) ) {
				$variations_with_vendor[] = array(
					'id'          => $product_id,
					'attributes'  => 'Only parent product without variations',
					'vendor_code' => get_post_meta( $product_id, 'prom-xml-updater-vendor', true ),
				);
			}

			if ( empty( $variations_with_vendor ) ) {
				return;
			}

			?>
			<div class="prom-vendor-code-footer" style="position: fixed; bottom: 0; left: 0; right: 0; background-color: #f9f9f9; border-top: 3px solid #0073aa; padding: 15px 20px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 9999; max-height: 200px; overflow-y: auto;">
				<div style="max-width: 1200px; margin: 0 auto;">
					<h4 style="margin: 0 0 10px 0; font-size: 14px; color: #333; font-weight: 600;">
						🔒 Інформація для менеджерів (vendorCode) - клікніть для копіювання
					</h4>
					<div style="display: flex; gap: 15px; flex-wrap: wrap;">
						<?php foreach ( $variations_with_vendor as $variation_info ) : ?>
							<div style="background-color: #fff; padding: 8px 12px; border-radius: 3px; border-left: 3px solid #0073aa; font-size: 12px;">
								<strong><?php echo wp_kses_post( $variation_info['attributes'] ); ?>:</strong>
								<span class="vendor-code-copy vendor-code-variation" 
									  data-code="<?php echo esc_attr( $variation_info['vendor_code'] ); ?>"
									  data-bg="transparent"
									  style="font-family: monospace; margin-left: 5px; color: #0073aa; cursor: pointer; padding: 4px 8px; border: 2px dashed #0073aa; border-radius: 3px; display: inline-block; transition: all 0.2s; background-color: transparent;"
									  title="Клікніть для копіювання">
									<?php echo esc_html( $variation_info['vendor_code'] ); ?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<script>
			(function() {
				document.addEventListener('DOMContentLoaded', function() {
					var vendorCodes = document.querySelectorAll('.vendor-code-copy');
					
					vendorCodes.forEach(function(element) {
						var isAnimating = false;
						var originalBg = element.getAttribute('data-bg');
						var isVariation = element.classList.contains('vendor-code-variation');
						var isSimple = element.classList.contains('vendor-code-simple');
						
						// Hover effects
						element.addEventListener('mouseenter', function() {
							if (!isAnimating) {
								if (isVariation) {
									this.style.backgroundColor = '#e3f2fd';
								} else if (isSimple) {
									this.style.backgroundColor = '#fffacd';
								}
								this.style.borderStyle = 'solid';
							}
						});
						
						element.addEventListener('mouseleave', function() {
							if (!isAnimating) {
								this.style.backgroundColor = originalBg;
								this.style.borderStyle = 'dashed';
							}
						});
						
						// Click to copy
						element.addEventListener('click', function() {
							var code = this.getAttribute('data-code');
							isAnimating = true;
							
							// Copy to clipboard
							if (navigator.clipboard && navigator.clipboard.writeText) {
								navigator.clipboard.writeText(code).then(function() {
									showCopyFeedback(element);
								});
							} else {
								// Fallback for older browsers
								var textarea = document.createElement('textarea');
								textarea.value = code;
								textarea.style.position = 'fixed';
								textarea.style.opacity = '0';
								document.body.appendChild(textarea);
								textarea.select();
								document.execCommand('copy');
								document.body.removeChild(textarea);
								showCopyFeedback(element);
							}
						});
						
						function showCopyFeedback(elem) {
							var originalText = elem.textContent;
							var originalColor = isVariation ? '#0073aa' : '#333';
							var originalBorderColor = isVariation ? '#0073aa' : '#ffc107';
							
							elem.textContent = '✓ Скопійовано!';
							elem.style.backgroundColor = '#4caf50';
							elem.style.color = '#fff';
							elem.style.borderColor = '#4caf50';
							elem.style.borderStyle = 'solid';
							
							setTimeout(function() {
								elem.textContent = originalText;
								elem.style.backgroundColor = originalBg;
								elem.style.color = originalColor;
								elem.style.borderColor = originalBorderColor;
								elem.style.borderStyle = 'dashed';
								isAnimating = false;
							}, 1500);
						}
					});
				});
			})();
			</script>
			<?php
		} else {
			// Handle simple products
			$vendor_code = get_post_meta( $product_id, 'prom-xml-updater-vendor', true );

			if ( empty( $vendor_code ) ) {
				return;
			}

			?>
			<div class="prom-vendor-code-footer" style="position: fixed; bottom: 0; left: 0; right: 0; background-color: #fff3cd; border-top: 3px solid #ffc107; padding: 12px 20px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 9999;">
				<div style="max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
					<strong style="color: #856404; font-size: 13px;">🔒 Інформація для менеджерів - клікніть для копіювання</strong>
					<span class="vendor-code-copy vendor-code-simple" 
						  data-code="<?php echo esc_attr( $vendor_code ); ?>"
						  data-bg="#fff"
						  style="font-family: monospace; background-color: #fff; padding: 8px 14px; border-radius: 3px; font-size: 14px; border: 2px dashed #ffc107; cursor: pointer; transition: all 0.2s;"
						  title="Клікніть для копіювання">
						Vendor Code: <?php echo esc_html( $vendor_code ); ?>
					</span>
				</div>
			</div>
			<script>
			(function() {
				document.addEventListener('DOMContentLoaded', function() {
					var vendorCodes = document.querySelectorAll('.vendor-code-copy');
					vendorCodes.forEach(function(element) {
						element.addEventListener('click', function() {
							var code = this.getAttribute('data-code');
							
							// Copy to clipboard
							if (navigator.clipboard && navigator.clipboard.writeText) {
								navigator.clipboard.writeText(code).then(function() {
									showCopyFeedback(element);
								});
							} else {
								// Fallback for older browsers
								var textarea = document.createElement('textarea');
								textarea.value = code;
								textarea.style.position = 'fixed';
								textarea.style.opacity = '0';
								document.body.appendChild(textarea);
								textarea.select();
								document.execCommand('copy');
								document.body.removeChild(textarea);
								showCopyFeedback(element);
							}
						});
					});
					
					function showCopyFeedback(element) {
						var originalText = element.textContent;
						element.textContent = '✓ Скопійовано!';
						element.style.backgroundColor = '#4caf50';
						element.style.color = '#fff';
						element.style.borderColor = '#4caf50';
						element.style.borderStyle = 'solid';
						
						setTimeout(function() {
							element.textContent = originalText;
							element.style.backgroundColor = '#fff';
							element.style.color = '#333';
							element.style.borderColor = '#ffc107';
							element.style.borderStyle = 'dashed';
						}, 1500);
					}
				});
			})();
			</script>
			<?php
		}
	}
}

