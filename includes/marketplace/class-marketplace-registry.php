<?php
/**
 * Registry of marketplace feeds.
 *
 * Adding a marketplace later (Rozetka, Hotline …) means writing a Marketplace_Feed
 * subclass and adding it here or through the f2000cs_marketplace_feeds filter.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Feed registry + per-feed settings storage.
 */
class Marketplace_Registry {

	/**
	 * Feed instances by id.
	 *
	 * @var array<string, Marketplace_Feed>|null
	 */
	private static $feeds = null;

	/**
	 * Register a feed instance.
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return void
	 */
	public static function register( Marketplace_Feed $feed ): void {
		self::boot();
		self::$feeds[ $feed->id() ] = $feed;
	}

	/**
	 * All feeds.
	 *
	 * @return array<string, Marketplace_Feed>
	 */
	public static function all(): array {
		self::boot();

		return self::$feeds;
	}

	/**
	 * One feed by id.
	 *
	 * @param string $id Feed id.
	 * @return Marketplace_Feed|null
	 */
	public static function get( string $id ) {
		self::boot();

		return self::$feeds[ $id ] ?? null;
	}

	/**
	 * Forget everything (tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$feeds = null;
	}

	/**
	 * Load the built-in feeds and let others add theirs.
	 *
	 * @return void
	 */
	private static function boot(): void {
		if ( null !== self::$feeds ) {
			return;
		}

		self::$feeds = array();

		$prom                       = new Prom_Feed();
		self::$feeds[ $prom->id() ] = $prom;

		/**
		 * Add marketplace feeds.
		 *
		 * @param array<string, Marketplace_Feed> $feeds Registered feeds keyed by id.
		 */
		$feeds = apply_filters( 'f2000cs_marketplace_feeds', self::$feeds );

		self::$feeds = array();

		foreach ( (array) $feeds as $feed ) {
			if ( $feed instanceof Marketplace_Feed ) {
				self::$feeds[ $feed->id() ] = $feed;
			}
		}
	}

	// ------------------------------------------------------------------
	// Settings
	// ------------------------------------------------------------------

	/**
	 * Option name of a feed's settings.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function option_name( string $id ): string {
		return 'f2000cs_mp_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $id ) );
	}

	/**
	 * Saved settings of a feed merged with defaults (plus 'schedule' and 'after_sync').
	 *
	 * @param string $id Feed id.
	 * @return array<string,mixed>
	 */
	public static function settings( string $id ): array {
		$feed = self::get( $id );

		if ( ! $feed ) {
			return array();
		}

		$saved = get_option( self::option_name( $id ), array() );
		$saved = is_array( $saved ) ? $saved : array();

		$settings             = $feed->with_defaults( $saved );
		$schedule             = (string) ( $saved['schedule'] ?? 'off' );
		$settings['schedule'] = in_array( $schedule, self::schedules(), true ) ? $schedule : 'off';

		$settings['after_sync'] = ! empty( $saved['after_sync'] );

		return $settings;
	}

	/**
	 * Sanitize and store the settings of a feed.
	 *
	 * @param string              $id  Feed id.
	 * @param array<string,mixed> $raw Raw input.
	 * @return array<string,mixed> Stored settings.
	 */
	public static function save_settings( string $id, array $raw ): array {
		$feed = self::get( $id );

		if ( ! $feed ) {
			return array();
		}

		$settings             = $feed->sanitize( $raw );
		$schedule             = isset( $raw['schedule'] ) ? (string) $raw['schedule'] : 'off';
		$settings['schedule'] = in_array( $schedule, self::schedules(), true ) ? $schedule : 'off';

		$settings['after_sync'] = ! empty( $raw['after_sync'] ) && '0' !== (string) $raw['after_sync'];

		// autoload=false: only read when building / on the admin page.
		update_option( self::option_name( $id ), $settings, false );

		return $settings;
	}

	/**
	 * Allowed regeneration schedules (WP-Cron recurrences).
	 *
	 * @return array<int, string>
	 */
	public static function schedules(): array {
		return array( 'off', 'hourly', '6_times_daily', '4_times_daily', 'twicedaily', 'daily' );
	}
}
