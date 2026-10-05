<?php
/**
 * Where marketplace feeds live on disk and how they are addressed publicly.
 *
 * A marketplace fetches the feed by URL, so unlike the private export / session
 * folders this one must stay reachable. Files carry an unguessable token in their
 * name and the folder has an index.php so it cannot be listed.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Feed file storage.
 */
class Marketplace_Storage {

	/**
	 * Directory under uploads (created on demand).
	 *
	 * @return string Absolute path without trailing slash.
	 */
	public static function dir(): string {
		$upload = wp_upload_dir();
		$dir    = untrailingslashit( (string) $upload['basedir'] ) . '/f2000cs-feeds';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$index = $dir . '/index.php';

		if ( is_dir( $dir ) && ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Silence directory listing.
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		return $dir;
	}

	/**
	 * Secret part of the public file name (created once).
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function token( string $id ): string {
		$option = 'f2000cs_mp_token_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $id ) );
		$token  = get_option( $option, '' );

		if ( ! is_string( $token ) || ! preg_match( '/^[a-z0-9]{20,}$/', $token ) ) {
			$token = strtolower( wp_generate_password( 24, false, false ) );
			update_option( $option, $token, false );
		}

		return $token;
	}

	/**
	 * Public file name.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return string
	 */
	public static function file_name( Marketplace_Feed $feed ): string {
		return $feed->id() . '-' . self::token( $feed->id() ) . '.' . $feed->extension();
	}

	/**
	 * Absolute path of the published feed.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return string
	 */
	public static function path( Marketplace_Feed $feed ): string {
		return self::dir() . '/' . self::file_name( $feed );
	}

	/**
	 * Public URL of the feed.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return string
	 */
	public static function url( Marketplace_Feed $feed ): string {
		$upload = wp_upload_dir();

		return trailingslashit( (string) $upload['baseurl'] ) . 'f2000cs-feeds/' . self::file_name( $feed );
	}

	/**
	 * Working file the offers are appended to during a build.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return string
	 */
	public static function build_path( Marketplace_Feed $feed ): string {
		return self::dir() . '/.' . $feed->id() . '-build.tmp';
	}

	/**
	 * Size and modification time of the published feed.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return array{exists:bool,size:int,mtime:int}
	 */
	public static function info( Marketplace_Feed $feed ): array {
		$path = self::path( $feed );

		if ( ! is_file( $path ) ) {
			return array(
				'exists' => false,
				'size'   => 0,
				'mtime'  => 0,
			);
		}

		return array(
			'exists' => true,
			'size'   => (int) filesize( $path ),
			'mtime'  => (int) filemtime( $path ),
		);
	}
}
