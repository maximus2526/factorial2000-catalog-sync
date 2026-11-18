<?php
/**
 * «Купити на Prom» button: links a WooCommerce product page to the same product on Prom.ua.
 *
 * Pro module (premium build only; listed in @fs_premium_only): an option toggle
 * (off by default), the Prom shop-domain setting, a per-product Prom ID meta box
 * and the shortcode.
 *
 * How it works:
 * 1) Set the shop domain (e.g. rechovyk.prom.ua) and enable the button in settings.
 * 2) Fill the Prom product ID in the product card («Prom.ua — кнопка» box).
 * 3) Place [f2000cs_buy_on_prom] into the single product layout.
 * Products without a Prom ID render nothing.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Buy on Prom button: settings, product meta and shortcode.
 */
class Buy_On_Prom {

	/**
	 * Option: '1' = button enabled. Off by default.
	 */
	const OPTION_ENABLED = 'f2000cs_buy_on_prom_enabled';

	/**
	 * Option: Prom shop domain, e.g. prom.ua.
	 */
	const OPTION_SHOP = 'f2000cs_buy_on_prom_shop';

	/**
	 * Product meta: numeric Prom product id.
	 */
	const META_PROM_ID = '_f2000cs_prom_id';

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'f2000cs_buy_on_prom';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save_meta_box' ) );
	}

	/**
	 * Whether the button is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '0' );
	}

	/**
	 * Configured shop domain, sanitized.
	 *
	 * @return string Empty when not configured.
	 */
	public static function get_shop(): string {
		return self::sanitize_shop( (string) get_option( self::OPTION_SHOP, '' ) );
	}

	/**
	 * Sanitize a shop domain: host only, no scheme/path/port.
	 *
	 * @param string $value Raw value.
	 * @return string Clean hostname or ''.
	 */
	public static function sanitize_shop( $value ): string {
		$value = strtolower( trim( (string) $value ) );
		$value = (string) preg_replace( '#^https?://#', '', $value );
		$value = (string) strtok( $value, '/' );
		$value = (string) strtok( $value, '?' );
		$value = (string) strtok( $value, '#' );
		$colon = strpos( $value, ':' );
		if ( false !== $colon ) {
			$value = substr( $value, 0, $colon );
		}
		$value = trim( $value, '.' );

		if ( '' === $value || false === strpos( $value, '.' ) ) {
			return '';
		}

		if ( 1 !== preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Sanitize a Prom product id: digits only.
	 *
	 * @param mixed $value Raw value.
	 * @return string Digits or ''.
	 */
	public static function sanitize_prom_id( $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value || 1 !== preg_match( '/^\d+$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Prom id stored on a product.
	 *
	 * @param int $product_id Product id.
	 * @return string Digits or ''.
	 */
	public static function get_product_prom_id( $product_id ): string {
		if ( (int) $product_id <= 0 ) {
			return '';
		}

		return self::sanitize_prom_id( get_post_meta( (int) $product_id, self::META_PROM_ID, true ) );
	}

	/**
	 * Build the Prom product URL: https://{shop}/ua/p{id}-{slug}.html.
	 *
	 * Always the Ukrainian version: the /ua/ prefix is used regardless of the
	 * site locale. Prom routes the page by id but requires the -{slug}.html
	 * shape: a bare /ua/p{id} answers 404. The slug text itself is decorative
	 * (any text works), so the WooCommerce product slug is used.
	 *
	 * @param string $prom_id Numeric Prom product id.
	 * @param string $shop    Shop domain.
	 * @param string $locale  Unused, kept for backward compatibility.
	 * @param string $slug    URL slug. 'tovar' when ''.
	 * @return string URL or ''.
	 */
	public static function build_url( $prom_id, $shop, $locale = '', $slug = '' ): string {
		$prom_id = self::sanitize_prom_id( $prom_id );
		$shop    = self::sanitize_shop( $shop );

		if ( '' === $prom_id || '' === $shop ) {
			return '';
		}

		$slug = self::sanitize_slug( $slug );

		return 'https://' . $shop . '/ua/p' . $prom_id . '-' . $slug . '.html';
	}

	/**
	 * Sanitize a URL slug: lowercase latin, digits, dash, underscore.
	 *
	 * @param mixed $value Raw value.
	 * @return string Clean slug or 'tovar'.
	 */
	public static function sanitize_slug( $value ): string {
		$value = strtolower( trim( (string) $value ) );
		$value = (string) preg_replace( '/[^a-z0-9-_]+/', '-', $value );
		$value = trim( $value, '-_' );

		if ( '' === $value ) {
			return 'tovar';
		}

		return $value;
	}

	/**
	 * Resolve the product id for the shortcode: explicit id attribute,
	 * current global product, or the queried object.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int Product id or 0.
	 */
	public static function resolve_product_id( $atts ): int {
		if ( is_array( $atts ) && isset( $atts['id'] ) && (int) $atts['id'] > 0 ) {
			return (int) $atts['id'];
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $product is the standard WooCommerce template global.
		global $product;
		if ( isset( $product ) && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return (int) $product->get_id();
		}

		if ( function_exists( 'get_queried_object_id' ) ) {
			return (int) get_queried_object_id();
		}

		return 0;
	}

	/**
	 * Render the [f2000cs_buy_on_prom] shortcode.
	 *
	 * Attributes: id (product id, default = current product), text_uk, text_ru, class (extra CSS classes).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Button HTML or ''.
	 */
	public static function shortcode( $atts ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'text_uk' => '',
				'text_ru' => '',
				'class'   => '',
			),
			$atts,
			self::SHORTCODE
		);

		$product_id = self::resolve_product_id( $atts );
		$prom_id    = self::get_product_prom_id( $product_id );
		$woo_slug   = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_name', $product_id ) : '';
		$url        = self::build_url( $prom_id, self::get_shop(), '', $woo_slug );

		if ( '' === $url ) {
			return '';
		}

		$locale  = function_exists( 'get_locale' ) ? get_locale() : 'uk';
		$is_uk   = 0 === strpos( strtolower( (string) $locale ), 'uk' );
		$default = $is_uk ? 'Купити на Prom' : 'Купить на Prom';
		$text    = $is_uk ? trim( (string) $atts['text_uk'] ) : trim( (string) $atts['text_ru'] );
		if ( '' === $text ) {
			$text = $default;
		}

		/**
		 * Filter the Prom product URL before rendering the button.
		 *
		 * @param string $url     Built URL.
		 * @param string $prom_id Prom product id.
		 * @param int    $product_id WooCommerce product id.
		 */
		$url = apply_filters( 'f2000cs_buy_on_prom_url', $url, $prom_id, $product_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$url = self::add_utm( $url );

		self::enqueue_assets();

		$class = trim( 'f2000cs-buy-on-prom ' . (string) $atts['class'] );

		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="nofollow sponsored noopener" data-product-id="' . esc_attr( (string) absint( $product_id ) ) . '" data-prom-id="' . esc_attr( $prom_id ) . '">'
			. '<span class="f2000cs-buy-on-prom__icon" aria-hidden="true">'
			. '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h15l-1.5 9h-12z"/><path d="M6 7l-1-4H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/></svg>'
			. '</span>'
			. '<span class="f2000cs-buy-on-prom__text">' . esc_html( $text ) . '</span>'
			. '</a>';
	}

	/**
	 * Append UTM tags so Prom analytics sees the site as the source.
	 *
	 * utm_source = this site's host, utm_medium = buy_on_prom.
	 *
	 * @param string $url Prom product URL.
	 * @return string URL with UTM tags or ''.
	 */
	public static function add_utm( $url ): string {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$args = array( 'utm_medium' => 'buy_on_prom' );

		if ( function_exists( 'get_site_url' ) ) {
			$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( (string) get_site_url(), PHP_URL_HOST ) : parse_url( (string) get_site_url(), PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback outside WordPress.
			if ( is_string( $host ) && '' !== $host ) {
				$args = array( 'utm_source' => $host ) + $args;
			}
		}

		if ( function_exists( 'add_query_arg' ) ) {
			$url = add_query_arg( $args, $url );
			return is_string( $url ) ? $url : '';
		}

		$sep = false === strpos( $url, '?' ) ? '?' : '&';

		return $url . $sep . http_build_query( $args );
	}

	/**
	 * Enqueue the button stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		wp_enqueue_style(
			'f2000cs-buy-on-prom',
			F2000CS_URL . 'assets/css/buy-on-prom.css',
			array(),
			F2000CS_VERSION
		);
	}

	/**
	 * Enqueue assets on product pages when the button is enabled.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_assets() {
		if ( ! self::is_enabled() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		self::enqueue_assets();
	}

	/**
	 * «Prom.ua — кнопка» box in the product editor.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'f2000cs-buy-on-prom',
			__( 'Prom.ua — кнопка', 'factorial2000-catalog-sync' ),
			array( __CLASS__, 'render_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Content of the product box.
	 *
	 * @param WP_Post $post Product post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'f2000cs_buy_on_prom_save', 'f2000cs_buy_on_prom_nonce' );
		$value = self::get_product_prom_id( $post->ID );
		?>
		<p>
			<label for="f2000cs-buy-on-prom-id"><strong><?php esc_html_e( 'ID товару на Prom', 'factorial2000-catalog-sync' ); ?></strong></label>
			<input type="text" id="f2000cs-buy-on-prom-id" name="f2000cs_buy_on_prom_id" value="<?php echo esc_attr( $value ); ?>" class="widefat" inputmode="numeric" placeholder="<?php esc_attr_e( 'напр. 3139502190', 'factorial2000-catalog-sync' ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Число з адреси картки на Prom: prom.ua/ua/p3139502190-…. Без нього кнопка [f2000cs_buy_on_prom] не показується.', 'factorial2000-catalog-sync' ); ?></p>
		<?php
	}

	/**
	 * Save the product box.
	 *
	 * @param int $post_id Product id.
	 * @return void
	 */
	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['f2000cs_buy_on_prom_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_buy_on_prom_nonce'] ) ), 'f2000cs_buy_on_prom_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['f2000cs_buy_on_prom_id'] ) ? self::sanitize_prom_id( sanitize_text_field( wp_unslash( $_POST['f2000cs_buy_on_prom_id'] ) ) ) : '';

		if ( '' === $value ) {
			delete_post_meta( $post_id, self::META_PROM_ID );
		} else {
			update_post_meta( $post_id, self::META_PROM_ID, $value );
		}
	}
}
