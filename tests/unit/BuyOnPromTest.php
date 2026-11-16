<?php
/**
 * «Купити на Prom» button: URL building and shortcode rendering.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Buy_On_Prom;

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Merge shortcode attributes with their defaults.
	 *
	 * @param array  $pairs     Default attributes.
	 * @param array  $atts      Given attributes.
	 * @param string $shortcode Shortcode tag.
	 * @return array
	 */
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = array();

		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}

		return $out;
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	/**
	 * Site locale fixture.
	 *
	 * @return string
	 */
	function get_locale() {
		return 'uk_UA';
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	/**
	 * Product slug fixture.
	 *
	 * @param string $field   Field name.
	 * @param int    $post_id Post id.
	 * @return string
	 */
	function get_post_field( $field, $post_id ) {
		return 'post_name' === $field ? 'shtani-travel-defence' : '';
	}
}

if ( ! function_exists( 'get_site_url' ) ) {
	/**
	 * Site URL fixture.
	 *
	 * @return string
	 */
	function get_site_url() {
		return 'https://rechovyk.com.ua';
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Append query arguments to a URL.
	 *
	 * @param array  $args Arguments.
	 * @param string $url  Base URL.
	 * @return string
	 */
	function add_query_arg( $args, $url ) {
		$parts = wp_parse_url_stub( $url );
		$query = array();

		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		$query = array_merge( $query, (array) $args );
		$base  = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' )
			. ( isset( $parts['host'] ) ? $parts['host'] : '' )
			. ( isset( $parts['path'] ) ? $parts['path'] : '' );

		return $base . '?' . http_build_query( $query );
	}
}

if ( ! function_exists( 'wp_parse_url_stub' ) ) {
	/**
	 * parse_url() wrapper that keeps the test file self-contained.
	 *
	 * @param string $url URL.
	 * @return array
	 */
	function wp_parse_url_stub( $url ) {
		$parts = parse_url( $url );

		return is_array( $parts ) ? $parts : array();
	}
}

final class F2000CS_Unit_BuyOnPromTest extends F2000CS_Unit_TestCase {

	/**
	 * Shop domain keeps the host only.
	 *
	 * @return void
	 */
	public function test_sanitize_shop_keeps_host_only(): void {
		$this->assertSame( 'rechovyk.prom.ua', Buy_On_Prom::sanitize_shop( 'https://rechovyk.prom.ua/' ) );
		$this->assertSame( 'shop.prom.ua', Buy_On_Prom::sanitize_shop( 'HTTP://Shop.Prom.UA/path?x=1' ) );
		$this->assertSame( 'shop.prom.ua', Buy_On_Prom::sanitize_shop( 'shop.prom.ua:8080' ) );
	}

	/**
	 * Values without a dot are not host names.
	 *
	 * @return void
	 */
	public function test_sanitize_shop_rejects_non_hosts(): void {
		$this->assertSame( '', Buy_On_Prom::sanitize_shop( 'localhost' ) );
		$this->assertSame( '', Buy_On_Prom::sanitize_shop( 'bad host.com' ) );
		$this->assertSame( '', Buy_On_Prom::sanitize_shop( '' ) );
	}

	/**
	 * Prom id accepts digits only.
	 *
	 * @return void
	 */
	public function test_sanitize_prom_id_accepts_digits_only(): void {
		$this->assertSame( '3108376854', Buy_On_Prom::sanitize_prom_id( ' 3108376854 ' ) );
		$this->assertSame( '', Buy_On_Prom::sanitize_prom_id( '12a' ) );
		$this->assertSame( '', Buy_On_Prom::sanitize_prom_id( '' ) );
	}

	/**
	 * URL shape is https://{shop}/ua/p{id}-{slug}.html.
	 *
	 * @return void
	 */
	public function test_build_url_uses_prom_shape(): void {
		$this->assertSame(
			'https://rechovyk.prom.ua/ua/p3108376854-shtani-travel-defence.html',
			Buy_On_Prom::build_url( '3108376854', 'rechovyk.prom.ua', '', 'shtani-travel-defence' )
		);
		$this->assertSame(
			'https://rechovyk.prom.ua/ua/p3108376854-tovar.html',
			Buy_On_Prom::build_url( '3108376854', 'rechovyk.prom.ua' )
		);
	}

	/**
	 * Missing id or shop yields no URL.
	 *
	 * @return void
	 */
	public function test_build_url_requires_id_and_shop(): void {
		$this->assertSame( '', Buy_On_Prom::build_url( '', 'rechovyk.prom.ua' ) );
		$this->assertSame( '', Buy_On_Prom::build_url( '3108376854', '' ) );
	}

	/**
	 * Disabled button renders nothing.
	 *
	 * @return void
	 */
	public function test_shortcode_renders_nothing_when_disabled(): void {
		F2000CS_Test_State::$options['f2000cs_buy_on_prom_shop']           = 'rechovyk.prom.ua';
		F2000CS_Test_State::$post_meta['41670:_f2000cs_prom_id']           = '3108376854';

		$this->assertSame( '', Buy_On_Prom::shortcode( array( 'id' => 41670 ) ) );
	}

	/**
	 * Enabled button links to the Prom product and carries data attributes.
	 *
	 * @return void
	 */
	public function test_shortcode_renders_prom_link(): void {
		F2000CS_Test_State::$options['f2000cs_buy_on_prom_enabled'] = '1';
		F2000CS_Test_State::$options['f2000cs_buy_on_prom_shop']    = 'rechovyk.prom.ua';
		F2000CS_Test_State::$post_meta['41670:_f2000cs_prom_id']    = '3108376854';

		$html = Buy_On_Prom::shortcode( array( 'id' => 41670 ) );

		$this->assertStringContainsString( 'class="f2000cs-buy-on-prom', $html );
		$this->assertStringContainsString( 'https://rechovyk.prom.ua/ua/p3108376854-shtani-travel-defence.html', $html );
		$this->assertStringContainsString( 'data-prom-id="3108376854"', $html );
		$this->assertStringContainsString( 'data-product-id="41670"', $html );
		$this->assertStringContainsString( 'utm_medium=buy_on_prom', $html );
		$this->assertStringContainsString( 'Купити на Prom', $html );
	}

	/**
	 * Product without a Prom id renders nothing even when enabled.
	 *
	 * @return void
	 */
	public function test_shortcode_hides_button_without_prom_id(): void {
		F2000CS_Test_State::$options['f2000cs_buy_on_prom_enabled'] = '1';
		F2000CS_Test_State::$options['f2000cs_buy_on_prom_shop']    = 'rechovyk.prom.ua';

		$this->assertSame( '', Buy_On_Prom::shortcode( array( 'id' => 41670 ) ) );
	}
}
