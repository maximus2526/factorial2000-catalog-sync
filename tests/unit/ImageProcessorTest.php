<?php
/**
 * Image_Processor tests (includes/class-image-processor.php).
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Image_Processor;

/**
 * Image processor unit tests.
 */
final class F2000CS_Unit_ImageProcessorTest extends F2000CS_Unit_TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Unlock Pro for the current test.
	 *
	 * @return void
	 */
	private function unlock_pro(): void {
		$this->enable_pro();
	}

	/**
	 * Free plan always returns disabled image settings.
	 *
	 * @return void
	 */
	public function test_get_settings_disabled_on_free() {
		update_option( Image_Processor::OPTION_PNG_CONVERT, 'webp' );
		update_option( Image_Processor::OPTION_OPTIMIZE, '1' );
		update_option( Image_Processor::OPTION_QUALITY, '70' );
		update_option( Image_Processor::OPTION_MAX_DIMENSION, '1600' );

		$settings = Image_Processor::get_settings();

		$this->assertSame( 'off', $settings['png_convert'] );
		$this->assertFalse( $settings['optimize'] );
		$this->assertSame( 0, $settings['max_dimension'] );
		$this->assertFalse( Image_Processor::is_enabled( $settings ) );
	}

	/**
	 * Pro reads and sanitizes stored options.
	 *
	 * @return void
	 */
	public function test_get_settings_on_pro() {
		$this->unlock_pro();
		update_option( Image_Processor::OPTION_PNG_CONVERT, 'jpg' );
		update_option( Image_Processor::OPTION_OPTIMIZE, '1' );
		update_option( Image_Processor::OPTION_QUALITY, '95' );
		update_option( Image_Processor::OPTION_MAX_DIMENSION, '2000' );

		$settings = Image_Processor::get_settings();

		$this->assertSame( 'jpg', $settings['png_convert'] );
		$this->assertTrue( $settings['optimize'] );
		$this->assertSame( 95, $settings['quality'] );
		$this->assertSame( 2000, $settings['max_dimension'] );
		$this->assertTrue( Image_Processor::is_enabled( $settings ) );
	}

	/**
	 * Sanitize helpers clamp / whitelist values.
	 *
	 * @return void
	 */
	public function test_sanitize_helpers() {
		$this->assertSame( 'off', Image_Processor::sanitize_png_convert( 'gif' ) );
		$this->assertSame( 'webp', Image_Processor::sanitize_png_convert( 'webp' ) );
		$this->assertSame( '1', Image_Processor::sanitize_optimize( 'on' ) );
		$this->assertSame( '0', Image_Processor::sanitize_optimize( 'no' ) );
		$this->assertSame( 40, Image_Processor::sanitize_quality( 10 ) );
		$this->assertSame( 100, Image_Processor::sanitize_quality( 200 ) );
		$this->assertSame( 0, Image_Processor::sanitize_max_dimension( 0 ) );
		$this->assertSame( 8000, Image_Processor::sanitize_max_dimension( 99999 ) );
	}

	/**
	 * Host capability probe returns the expected boolean flags.
	 *
	 * @return void
	 */
	public function test_get_host_capabilities_shape() {
		$caps = Image_Processor::get_host_capabilities();

		foreach ( array( 'gd', 'imagick', 'editor', 'jpeg', 'webp', 'resize' ) as $key ) {
			$this->assertArrayHasKey( $key, $caps );
			$this->assertIsBool( $caps[ $key ] );
		}
	}

	/**
	 * Fit-size math preserves aspect ratio.
	 *
	 * @return void
	 */
	public function test_compute_fit_size() {
		$this->assertNull( Image_Processor::compute_fit_size( 800, 600, 0 ) );
		$this->assertNull( Image_Processor::compute_fit_size( 800, 600, 1000 ) );

		$fit = Image_Processor::compute_fit_size( 4000, 2000, 1000 );
		$this->assertSame( array( 1000, 500 ), $fit );

		$fit_tall = Image_Processor::compute_fit_size( 1000, 4000, 1000 );
		$this->assertSame( array( 250, 1000 ), $fit_tall );
	}

	/**
	 * PNG conversion changes the sideload filename extension.
	 *
	 * @return void
	 */
	public function test_build_filename_conversion() {
		$url = 'https://cdn.example/path/photo.PNG';

		$this->assertSame( 'photo.PNG', Image_Processor::build_filename( $url, 'off', true ) );
		$this->assertSame( 'photo.webp', Image_Processor::build_filename( $url, 'webp', true ) );
		$this->assertSame( 'photo.jpg', Image_Processor::build_filename( $url, 'jpg', true ) );
		$this->assertSame( 'photo.PNG', Image_Processor::build_filename( $url, 'webp', false ) );
	}

	/**
	 * Extension sniff for PNG URLs.
	 *
	 * @return void
	 */
	public function test_looks_like_png() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs' );
		file_put_contents( $tmp, 'x' );

		$this->assertTrue( Image_Processor::looks_like_png( $tmp, 'https://x.test/a.png' ) );
		$this->assertFalse( Image_Processor::looks_like_png( $tmp, 'https://x.test/a.jpg' ) );

		wp_delete_file( $tmp );
	}

	/**
	 * prepare_sideload is a passthrough when processing is off.
	 *
	 * @return void
	 */
	public function test_prepare_sideload_passthrough_when_disabled() {
		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs' );
		file_put_contents( $tmp, 'raw' );

		$result = Image_Processor::prepare_sideload( $tmp, 'https://cdn.example/img.jpg' );

		$this->assertSame( $tmp, $result['tmp_name'] );
		$this->assertSame( 'img.jpg', $result['name'] );

		wp_delete_file( $tmp );
	}

	/**
	 * Pro + max dimension triggers resize via the fake image editor.
	 *
	 * @return void
	 */
	public function test_prepare_sideload_resizes_when_over_max() {
		$this->unlock_pro();
		update_option( Image_Processor::OPTION_MAX_DIMENSION, '1000' );
		update_option( Image_Processor::OPTION_OPTIMIZE, '0' );
		update_option( Image_Processor::OPTION_PNG_CONVERT, 'off' );

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs' );
		file_put_contents( $tmp, 'img' );

		$editor = new F2000CS_Fake_Image_Editor( $tmp, 4000, 2000 );
		F2000CS_Test_State::$image_editor_factory = static function () use ( $editor ) {
			return $editor;
		};

		$result = Image_Processor::prepare_sideload( $tmp, 'https://cdn.example/big.jpg' );

		$this->assertSame( 1000, $editor->width );
		$this->assertSame( 500, $editor->height );
		$this->assertSame( Image_Processor::DEFAULT_QUALITY, $editor->quality );
		$this->assertSame( 'big.jpg', $result['name'] );
		$this->assertFileExists( $result['tmp_name'] );

		wp_delete_file( $result['tmp_name'] );
	}

	/**
	 * PNG → WebP conversion sets mime and renames the file.
	 *
	 * @return void
	 */
	public function test_prepare_sideload_converts_png_to_webp() {
		$this->unlock_pro();
		update_option( Image_Processor::OPTION_PNG_CONVERT, 'webp' );
		update_option( Image_Processor::OPTION_QUALITY, '75' );
		update_option( Image_Processor::OPTION_OPTIMIZE, '0' );

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs' );
		// Force .png basename via URL; tempnam has no extension.
		file_put_contents( $tmp, 'pngdata' );

		$editor = new F2000CS_Fake_Image_Editor( $tmp, 200, 200 );
		F2000CS_Test_State::$image_editor_factory = static function () use ( $editor ) {
			return $editor;
		};

		$result = Image_Processor::prepare_sideload( $tmp, 'https://cdn.example/shot.png' );

		$this->assertSame( 'image/webp', $editor->last_mime );
		$this->assertSame( 75, $editor->quality );
		$this->assertStringEndsWith( '.webp', $result['name'] );
		$this->assertFileExists( $result['tmp_name'] );
		$this->assertNotSame( $tmp, $result['tmp_name'] );

		wp_delete_file( $result['tmp_name'] );
	}

	/**
	 * Optimize re-encodes with the configured quality.
	 *
	 * @return void
	 */
	public function test_prepare_sideload_optimize_sets_quality() {
		$this->unlock_pro();
		update_option( Image_Processor::OPTION_OPTIMIZE, '1' );
		update_option( Image_Processor::OPTION_QUALITY, '60' );
		update_option( Image_Processor::OPTION_PNG_CONVERT, 'off' );

		$tmp = tempnam( sys_get_temp_dir(), 'f2000cs' );
		file_put_contents( $tmp, 'jpg' );

		$editor = new F2000CS_Fake_Image_Editor( $tmp, 640, 480 );
		F2000CS_Test_State::$image_editor_factory = static function () use ( $editor ) {
			return $editor;
		};

		$result = Image_Processor::prepare_sideload( $tmp, 'https://cdn.example/a.jpg' );

		$this->assertSame( 60, $editor->quality );
		$this->assertSame( 'a.jpg', $result['name'] );
		$this->assertFileExists( $result['tmp_name'] );

		wp_delete_file( $result['tmp_name'] );
	}
}
