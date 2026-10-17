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
	 * Upper bound of files per feed (a runaway build must not fill the disk with parts).
	 */
	const MAX_PARTS = 20;

	/**
	 * Directory under uploads (created on demand).
	 *
	 * @return string Absolute path without trailing slash.
	 */
	public static function dir(): string {
		$dir = self::base_dir();

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
	 * Directory under uploads, without creating it.
	 *
	 * @return string Absolute path without trailing slash.
	 */
	public static function base_dir(): string {
		$upload = wp_upload_dir();

		return untrailingslashit( (string) $upload['basedir'] ) . '/f2000cs-feeds';
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
	 * Absolute path of one file of the feed.
	 *
	 * A feed above the size limit is published as several files: the first keeps the
	 * plain name (the link people already gave the marketplace), the next ones carry
	 * a "-2", "-3"… suffix.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @param int              $n    File number, from 1.
	 * @return string
	 */
	public static function part_path( Marketplace_Feed $feed, int $n ): string {
		if ( $n <= 1 ) {
			return self::path( $feed );
		}

		return self::dir() . '/' . $feed->id() . '-' . self::token( $feed->id() ) . '-' . $n . '.' . $feed->extension();
	}

	/**
	 * Public URL of one file of the feed.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @param int              $n    File number, from 1.
	 * @return string
	 */
	public static function part_url( Marketplace_Feed $feed, int $n ): string {
		if ( $n <= 1 ) {
			return self::url( $feed );
		}

		$upload = wp_upload_dir();

		return trailingslashit( (string) $upload['baseurl'] ) . 'f2000cs-feeds/' . $feed->id() . '-' . self::token( $feed->id() ) . '-' . $n . '.' . $feed->extension();
	}

	/**
	 * Published files of the feed, in order.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return array<int, array{n:int,url:string,size:int,mtime:int}>
	 */
	public static function parts( Marketplace_Feed $feed ): array {
		$out = array();

		for ( $n = 1; $n <= self::MAX_PARTS; $n++ ) {
			$path = self::part_path( $feed, $n );

			if ( ! is_file( $path ) ) {
				break;
			}

			$out[] = array(
				'n'     => $n,
				'url'   => self::part_url( $feed, $n ),
				'size'  => (int) filesize( $path ),
				'mtime' => (int) filemtime( $path ),
			);
		}

		return $out;
	}

	/**
	 * Delete the numbered files from $from on (a smaller build leaves old extra files behind).
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @param int              $from First file number to delete (at least 2).
	 * @return void
	 */
	public static function delete_parts_from( Marketplace_Feed $feed, int $from ): void {
		for ( $n = max( 2, $from ); $n <= self::MAX_PARTS; $n++ ) {
			$path = self::part_path( $feed, $n );

			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
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
		// Lives in the public folder, so the name carries the secret token too.
		return self::dir() . '/.' . $feed->id() . '-' . self::token( $feed->id() ) . '-build.tmp';
	}

	/**
	 * Size and modification time of the published feed (all files together).
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return array{exists:bool,size:int,mtime:int,parts:array<int,array{n:int,url:string,size:int,mtime:int}>}
	 */
	public static function info( Marketplace_Feed $feed ): array {
		$parts = self::parts( $feed );

		if ( ! $parts ) {
			return array(
				'exists' => false,
				'size'   => 0,
				'mtime'  => 0,
				'parts'  => array(),
			);
		}

		return array(
			'exists' => true,
			'size'   => (int) array_sum( array_column( $parts, 'size' ) ),
			'mtime'  => (int) $parts[0]['mtime'],
			'parts'  => $parts,
		);
	}
}
