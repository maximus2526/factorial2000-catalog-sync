<?php
/**
 * Pro image processing for product gallery/featured sideloads during import.
 *
 * Applies only to downloaded <picture> files — never to HTML in descriptions.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Convert / optimize / resize product images before media_handle_sideload.
 */
class Image_Processor {

	const OPTION_PNG_CONVERT   = 'f2000cs_img_png_convert';
	const OPTION_OPTIMIZE      = 'f2000cs_img_optimize';
	const OPTION_QUALITY       = 'f2000cs_img_quality';
	const OPTION_MAX_DIMENSION = 'f2000cs_img_max_dimension';

	const DEFAULT_QUALITY   = 82;
	const MIN_QUALITY       = 40;
	const MAX_QUALITY       = 100;
	const MIN_MAX_DIMENSION = 0;
	const MAX_MAX_DIMENSION = 8000;

	/**
	 * Settings with Free plan always disabled.
	 *
	 * @return array{png_convert:string,optimize:bool,quality:int,max_dimension:int}
	 */
	public static function get_settings(): array {
		if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
			return self::disabled_settings();
		}

		return array(
			'png_convert'   => self::sanitize_png_convert( get_option( self::OPTION_PNG_CONVERT, 'off' ) ),
			'optimize'      => ( '1' === (string) get_option( self::OPTION_OPTIMIZE, '0' ) ),
			'quality'       => self::sanitize_quality( get_option( self::OPTION_QUALITY, self::DEFAULT_QUALITY ) ),
			'max_dimension' => self::sanitize_max_dimension( get_option( self::OPTION_MAX_DIMENSION, 0 ) ),
		);
	}

	/**
	 * @return array{png_convert:string,optimize:bool,quality:int,max_dimension:int}
	 */
	public static function disabled_settings(): array {
		return array(
			'png_convert'   => 'off',
			'optimize'      => false,
			'quality'       => self::DEFAULT_QUALITY,
			'max_dimension' => 0,
		);
	}

	/**
	 * @param mixed $value Raw option.
	 * @return string off|webp|jpg
	 */
	public static function sanitize_png_convert( $value ): string {
		$value   = sanitize_text_field( (string) $value );
		$allowed = array( 'off', 'webp', 'jpg' );

		return in_array( $value, $allowed, true ) ? $value : 'off';
	}

	/**
	 * @param mixed $value Raw option.
	 * @return string 0|1
	 */
	public static function sanitize_optimize( $value ): string {
		return ( '1' === $value || 'yes' === $value || 'on' === $value || true === $value ) ? '1' : '0';
	}

	/**
	 * @param mixed $value Raw option.
	 * @return int
	 */
	public static function sanitize_quality( $value ): int {
		$q = absint( $value );
		if ( $q < self::MIN_QUALITY ) {
			$q = self::MIN_QUALITY;
		}
		if ( $q > self::MAX_QUALITY ) {
			$q = self::MAX_QUALITY;
		}

		return $q;
	}

	/**
	 * @param mixed $value Raw option.
	 * @return int 0 = disabled
	 */
	public static function sanitize_max_dimension( $value ): int {
		$d = absint( $value );
		if ( $d < 1 ) {
			return 0;
		}
		if ( $d > self::MAX_MAX_DIMENSION ) {
			$d = self::MAX_MAX_DIMENSION;
		}

		return $d;
	}

	/**
	 * Whether any processing step is active.
	 *
	 * @param array $settings Settings from get_settings().
	 * @return bool
	 */
	public static function is_enabled( array $settings ): bool {
		return ( 'off' !== $settings['png_convert'] )
			|| ! empty( $settings['optimize'] )
			|| (int) $settings['max_dimension'] > 0;
	}

	/**
	 * Detect image-processing capabilities available on this host.
	 *
	 * @return array{
	 *     gd:bool,
	 *     imagick:bool,
	 *     editor:bool,
	 *     jpeg:bool,
	 *     webp:bool,
	 *     resize:bool
	 * }
	 */
	public static function get_host_capabilities(): array {
		self::ensure_wp_image_api();

		$gd      = extension_loaded( 'gd' );
		$imagick = extension_loaded( 'imagick' ) && class_exists( '\Imagick', false );

		$jpeg   = false;
		$webp   = false;
		$resize = false;

		if ( function_exists( 'wp_image_editor_supports' ) ) {
			$jpeg   = (bool) wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) );
			$webp   = (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
			$resize = (bool) wp_image_editor_supports( array( 'methods' => array( 'resize' ) ) );
		} else {
			$jpeg   = $gd || $imagick;
			$webp   = ( $gd && function_exists( 'imagewebp' ) ) || self::imagick_supports_webp();
			$resize = $gd || $imagick;
		}

		$editor = function_exists( 'wp_get_image_editor' ) && ( $jpeg || $webp || $resize || $gd || $imagick );

		return array(
			'gd'      => $gd,
			'imagick' => $imagick,
			'editor'  => $editor,
			'jpeg'    => $jpeg,
			'webp'    => $webp,
			'resize'  => $resize,
		);
	}

	/**
	 * @return void
	 */
	private static function ensure_wp_image_api(): void {
		if ( function_exists( 'wp_get_image_editor' ) && function_exists( 'wp_image_editor_supports' ) ) {
			return;
		}

		$image_inc = ABSPATH . 'wp-admin/includes/image.php';
		if ( is_readable( $image_inc ) ) {
			require_once $image_inc;
		}
	}

	/**
	 * @return bool
	 */
	private static function imagick_supports_webp(): bool {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( '\Imagick', false ) ) {
			return false;
		}

		try {
			$formats = \Imagick::queryFormats( 'WEBP' );
			if ( is_array( $formats ) && ! empty( $formats ) ) {
				return true;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- capability probe.
			// Fall through.
		}

		return false;
	}

	/**
	 * Target pixel size after "fit inside max" resize (aspect preserved).
	 *
	 * @param int $width  Current width.
	 * @param int $height Current height.
	 * @param int $max    Max edge length (0 = no resize).
	 * @return array{0:int,1:int}|null Null when no resize needed.
	 */
	public static function compute_fit_size( int $width, int $height, int $max ): ?array {
		if ( $max < 1 || $width < 1 || $height < 1 ) {
			return null;
		}
		if ( $width <= $max && $height <= $max ) {
			return null;
		}

		$ratio = min( $max / $width, $max / $height );

		return array(
			(int) max( 1, (int) round( $width * $ratio ) ),
			(int) max( 1, (int) round( $height * $ratio ) ),
		);
	}

	/**
	 * Build sideload filename, optionally changing extension for PNG conversion.
	 *
	 * @param string $source_url Original remote URL.
	 * @param string $png_convert off|webp|jpg
	 * @param bool   $is_png      Whether source is PNG.
	 * @return string
	 */
	public static function build_filename( string $source_url, string $png_convert, bool $is_png ): string {
		$path = self::url_path( $source_url );
		$name = $path ? basename( $path ) : 'image';
		$name = self::safe_filename( $name );
		if ( '' === $name || '.' === $name || '..' === $name ) {
			$name = 'image';
		}

		if ( $is_png && 'webp' === $png_convert ) {
			return (string) preg_replace( '/\.[^.]+$/', '', $name ) . '.webp';
		}
		if ( $is_png && 'jpg' === $png_convert ) {
			return (string) preg_replace( '/\.[^.]+$/', '', $name ) . '.jpg';
		}

		return $name;
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function url_path( string $url ): string {
		if ( function_exists( 'wp_parse_url' ) ) {
			return (string) wp_parse_url( $url, PHP_URL_PATH );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Fallback for hosts without wp_parse_url().
		$path = parse_url( $url, PHP_URL_PATH );

		return is_string( $path ) ? $path : '';
	}

	/**
	 * @param string $name Filename.
	 * @return string
	 */
	private static function safe_filename( string $name ): string {
		if ( function_exists( 'sanitize_file_name' ) ) {
			return sanitize_file_name( $name );
		}

		$name = preg_replace( '/[^A-Za-z0-9._-]/', '-', $name );

		return is_string( $name ) ? $name : 'image';
	}

	/**
	 * Detect PNG from path / URL extension (fast path before opening editor).
	 *
	 * @param string $tmp_path Temp file.
	 * @param string $source_url Source URL.
	 * @return bool
	 */
	public static function looks_like_png( string $tmp_path, string $source_url ): bool {
		$candidates = array( $tmp_path, self::url_path( $source_url ) );
		foreach ( $candidates as $candidate ) {
			$ext = strtolower( pathinfo( (string) $candidate, PATHINFO_EXTENSION ) );
			if ( 'png' === $ext ) {
				return true;
			}
		}

		if ( function_exists( 'wp_getimagesize' ) ) {
			$info = @wp_getimagesize( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $info ) && ! empty( $info['mime'] ) && 'image/png' === $info['mime'] ) {
				return true;
			}
		} elseif ( function_exists( 'getimagesize' ) ) {
			$info = @getimagesize( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $info ) && ! empty( $info['mime'] ) && 'image/png' === $info['mime'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * After download_url(): optionally convert/optimize/resize before sideload.
	 *
	 * @param string $tmp_path   Local temp path from download_url().
	 * @param string $source_url Original remote URL (for filename / mime hints).
	 * @return array{tmp_name:string,name:string} Ready for media_handle_sideload $file_array.
	 */
	public static function prepare_sideload( string $tmp_path, string $source_url ): array {
		$settings = self::get_settings();
		$fallback = array(
			'tmp_name' => $tmp_path,
			'name'     => self::build_filename( $source_url, 'off', false ),
		);

		if ( ! self::is_enabled( $settings ) || ! file_exists( $tmp_path ) ) {
			return $fallback;
		}

		$is_png = self::looks_like_png( $tmp_path, $source_url );
		$name   = self::build_filename( $source_url, $settings['png_convert'], $is_png );

		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			$image_inc = ABSPATH . 'wp-admin/includes/image.php';
			if ( is_readable( $image_inc ) ) {
				require_once $image_inc;
			}
		}

		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return $fallback;
		}

		$editor = wp_get_image_editor( $tmp_path );
		if ( is_wp_error( $editor ) ) {
			return $fallback;
		}

		$size   = $editor->get_size();
		$width  = isset( $size['width'] ) ? (int) $size['width'] : 0;
		$height = isset( $size['height'] ) ? (int) $size['height'] : 0;

		$fit        = self::compute_fit_size( $width, $height, (int) $settings['max_dimension'] );
		$did_resize = false;
		if ( null !== $fit ) {
			$resized = $editor->resize( $fit[0], $fit[1], false );
			if ( ! is_wp_error( $resized ) ) {
				$did_resize = true;
			}
		}

		$do_convert  = $is_png && 'off' !== $settings['png_convert'];
		$do_reencode = $do_convert || $settings['optimize'] || $did_resize;

		if ( ! $do_reencode ) {
			return array(
				'tmp_name' => $tmp_path,
				'name'     => $name,
			);
		}

		$editor->set_quality( (int) $settings['quality'] );

		$mime = null;
		if ( $do_convert && 'webp' === $settings['png_convert'] ) {
			$mime = 'image/webp';
		} elseif ( $do_convert && 'jpg' === $settings['png_convert'] ) {
			$mime = 'image/jpeg';
		}

		$dest = $tmp_path;
		if ( $do_convert ) {
			$dest = self::sibling_temp_path( $tmp_path, $name );
		}

		$saved = $mime
			? $editor->save( $dest, $mime )
			: $editor->save( $dest );

		if ( is_wp_error( $saved ) ) {
			if ( $dest !== $tmp_path && file_exists( $dest ) ) {
				wp_delete_file( $dest );
			}
			return array(
				'tmp_name' => $tmp_path,
				'name'     => self::build_filename( $source_url, 'off', $is_png ),
			);
		}

		$out_path = is_array( $saved ) && ! empty( $saved['path'] ) ? (string) $saved['path'] : $dest;

		if ( $out_path !== $tmp_path && file_exists( $tmp_path ) ) {
			wp_delete_file( $tmp_path );
		}

		return array(
			'tmp_name' => $out_path,
			'name'     => $name,
		);
	}

	/**
	 * @param string $tmp_path Original temp path.
	 * @param string $name     Desired basename.
	 * @return string
	 */
	private static function sibling_temp_path( string $tmp_path, string $name ): string {
		$dir       = dirname( $tmp_path );
		$base      = pathinfo( $name, PATHINFO_FILENAME );
		$ext       = pathinfo( $name, PATHINFO_EXTENSION );
		$base      = $base ? $base : 'image';
		$candidate = $dir . '/' . $base . '-' . uniqid( 'f2000cs-', true ) . ( $ext ? '.' . $ext : '' );

		return $candidate;
	}
}
