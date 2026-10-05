<?php
/**
 * Chunked feed build.
 *
 * A catalog can have tens of thousands of products, so the build runs in steps
 * that fit a PHP time limit: every step appends offers to a working file and
 * stores its position (pass, offset, category tree, counters) in an option. The
 * admin screen, WP-Cron and WP-CLI all drive the same step() method. The finished
 * feed replaces the published file atomically, and only if it is non-empty and
 * well-formed, so Prom never sees a half-written or empty feed.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

// Feeds can be hundreds of MB: the build streams files with native functions on purpose (WP_Filesystem has no streaming API).
// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Build state machine.
 */
class Marketplace_Job {

	const LOCK_TTL   = 180;
	const PAGE_SIZE  = 20;
	const CRON_HOOK  = 'f2000cs_marketplace_build';
	const CONT_HOOK  = 'f2000cs_marketplace_continue';
	const MAX_REPORT = 50;

	/**
	 * Option holding the running build.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function state_option( string $id ): string {
		return 'f2000cs_mp_state_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $id ) );
	}

	/**
	 * Option holding the outcome of the last finished build.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function result_option( string $id ): string {
		return 'f2000cs_mp_result_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $id ) );
	}

	/**
	 * Outcome of the last build.
	 *
	 * @param string $id Feed id.
	 * @return array<string,mixed>
	 */
	public static function last_result( string $id ): array {
		$result = get_option( self::result_option( $id ), array() );

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Running build state, or an empty array.
	 *
	 * @param string $id Feed id.
	 * @return array<string,mixed>
	 */
	public static function state( string $id ): array {
		$state = get_option( self::state_option( $id ), array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Begin a fresh build (discards a running one).
	 *
	 * @param Marketplace_Feed $feed Feed.
	 * @return array<string,mixed> State.
	 */
	public static function start( Marketplace_Feed $feed ): array {
		$tmp = Marketplace_Storage::build_path( $feed );

		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Working file of the build, created empty.
		file_put_contents( $tmp, '' );

		$state = array(
			'phase'    => 0,
			'offset'   => 0,
			'tree'     => ( new Marketplace_Category_Tree() )->to_array(),
			'exported' => 0,
			'skipped'  => 0,
			'excluded' => array(),
			'started'  => time(),
		);

		update_option( self::state_option( $feed->id() ), $state, false );

		return $state;
	}

	/**
	 * Run one slice of the build.
	 *
	 * @param string                       $id      Feed id.
	 * @param int                          $budget  Seconds this call may spend.
	 * @param Marketplace_Unit_Source|null $source  Unit source (WooCommerce by default).
	 * @param bool                         $restart Start from scratch.
	 * @return array<string,mixed> Progress: status (running|done|error|locked), counters, message.
	 */
	public static function step( string $id, int $budget = 15, $source = null, bool $restart = false ): array {
		$feed = Marketplace_Registry::get( $id );

		if ( ! $feed ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Невідомий маркетплейс.', 'factorial2000-catalog-sync' ),
			);
		}

		$lock = 'f2000cs_mp_lock_' . $id;

		if ( get_transient( $lock ) ) {
			return array(
				'status'  => 'locked',
				'message' => __( 'Вигрузка вже формується.', 'factorial2000-catalog-sync' ),
			) + self::progress( self::state( $id ) );
		}

		set_transient( $lock, 1, self::LOCK_TTL );

		try {
			$settings = Marketplace_Registry::settings( $id );
			$source   = $source ? $source : new Marketplace_WC_Source( $settings );
			$state    = self::state( $id );

			if ( $restart || ! $state ) {
				$state = self::start( $feed );
			}

			$tree   = Marketplace_Category_Tree::from_array( (array) $state['tree'] );
			$phases = $source->phases();
			$tmp    = Marketplace_Storage::build_path( $feed );
			$out    = fopen( $tmp, 'ab' );

			if ( ! $out ) {
				throw new \RuntimeException( esc_html__( 'Не вдалося відкрити робочий файл вигрузки.', 'factorial2000-catalog-sync' ) );
			}

			$deadline = microtime( true ) + max( 1, $budget );
			$done     = false;

			while ( microtime( true ) < $deadline ) {
				$phase = $phases[ (int) $state['phase'] ] ?? null;

				if ( null === $phase ) {
					$done = true;
					break;
				}

				$page = $source->fetch( $phase, (int) $state['offset'], self::PAGE_SIZE );

				foreach ( $page['units'] as $unit ) {
					$res = $feed->process_unit( $unit, $tree, $settings );

					if ( '' !== $res['xml'] ) {
						fwrite( $out, $res['xml'] );
					}

					$state['exported'] += (int) $res['exported'];
					$state['skipped']  += (int) $res['skipped'];

					foreach ( $res['excluded'] as $sku ) {
						if ( count( $state['excluded'] ) < self::MAX_REPORT ) {
							$state['excluded'][] = $sku;
						}
					}
				}

				$state['offset'] += (int) $page['consumed'];

				if ( ! empty( $page['done'] ) ) {
					++$state['phase'];
					$state['offset'] = 0;
				}
			}

			fclose( $out );

			$state['tree'] = $tree->to_array();

			if ( $done ) {
				$result = self::finalize( $feed, $settings, $tree, $state );
				delete_option( self::state_option( $id ) );
				delete_transient( $lock );

				return $result;
			}

			update_option( self::state_option( $id ), $state, false );
			delete_transient( $lock );

			return array( 'status' => 'running' ) + self::progress( $state );
		} catch ( \Throwable $e ) {
			delete_transient( $lock );

			$result = array(
				'status'   => 'error',
				'message'  => $e->getMessage(),
				'finished' => time(),
			);

			update_option( self::result_option( $id ), $result, false );
			delete_option( self::state_option( $id ) );

			return $result;
		}
	}

	/**
	 * Run steps until the build finishes or the time budget is spent.
	 *
	 * @param string                       $id     Feed id.
	 * @param int                          $budget Seconds in total.
	 * @param Marketplace_Unit_Source|null $source Unit source.
	 * @param bool                         $restart Start from scratch.
	 * @return array<string,mixed> Last step result.
	 */
	public static function run( string $id, int $budget = 240, $source = null, bool $restart = true ): array {
		$deadline = time() + max( 5, $budget );
		$result   = array( 'status' => 'running' );

		do {
			$result  = self::step( $id, min( 15, max( 1, $deadline - time() ) ), $source, $restart );
			$restart = false;
		} while ( 'running' === $result['status'] && time() < $deadline );

		return $result;
	}

	/**
	 * Counters for the UI.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	public static function progress( array $state ): array {
		return array(
			'phase'    => (int) ( $state['phase'] ?? 0 ),
			'offset'   => (int) ( $state['offset'] ?? 0 ),
			'exported' => (int) ( $state['exported'] ?? 0 ),
			'skipped'  => (int) ( $state['skipped'] ?? 0 ),
		);
	}

	/**
	 * Assemble the final document and publish it.
	 *
	 * @param Marketplace_Feed          $feed     Feed.
	 * @param array<string,mixed>       $settings Settings.
	 * @param Marketplace_Category_Tree $tree     Category tree.
	 * @param array<string,mixed>       $state    State.
	 * @return array<string,mixed>
	 */
	private static function finalize( Marketplace_Feed $feed, array $settings, Marketplace_Category_Tree $tree, array $state ): array {
		$id     = $feed->id();
		$tmp    = Marketplace_Storage::build_path( $feed );
		$final  = Marketplace_Storage::path( $feed );
		$part   = $final . '.part';
		$result = array(
			'finished'   => time(),
			'duration'   => max( 0, time() - (int) ( $state['started'] ?? time() ) ),
			'exported'   => (int) $state['exported'],
			'skipped'    => (int) $state['skipped'],
			'categories' => $tree->count(),
			'excluded'   => array_values( (array) $state['excluded'] ),
		);

		if ( $result['exported'] < 1 ) {
			$result['status']  = 'error';
			$result['message'] = __( 'У вигрузці немає жодного товару — попередній файл залишено без змін. Перевірте, що є опубліковані товари з ціною, фото та категорією.', 'factorial2000-catalog-sync' );
			wp_delete_file( $tmp );
			update_option( self::result_option( $id ), $result, false );

			return $result;
		}

		$out = fopen( $part, 'wb' );
		$in  = fopen( $tmp, 'rb' );

		if ( ! $out || ! $in ) {
			throw new \RuntimeException( esc_html__( 'Не вдалося зібрати файл вигрузки.', 'factorial2000-catalog-sync' ) );
		}

		fwrite( $out, $feed->head( $settings ) );
		fwrite( $out, "    <categories>\n" . $tree->to_xml( '      ' ) . "    </categories>\n    <offers>\n" );
		stream_copy_to_stream( $in, $out );
		fwrite( $out, $feed->tail() );
		fclose( $in );
		fclose( $out );
		wp_delete_file( $tmp );

		if ( ! self::is_well_formed( $part ) ) {
			wp_delete_file( $part );
			$result['status']  = 'error';
			$result['message'] = __( 'Згенерований файл не є коректним XML — попередній файл залишено без змін.', 'factorial2000-catalog-sync' );
			update_option( self::result_option( $id ), $result, false );

			return $result;
		}

		if ( ! @rename( $part, $final ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Falls back to copy below.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Windows cannot rename over an open file.
			copy( $part, $final );
			wp_delete_file( $part );
		}

		$result['status'] = 'done';
		$result['size']   = is_file( $final ) ? (int) filesize( $final ) : 0;
		$result['url']    = Marketplace_Storage::url( $feed );

		update_option( self::result_option( $id ), $result, false );

		return $result;
	}

	/**
	 * Whether a file parses to the end as XML (streaming).
	 *
	 * @param string $path File.
	 * @return bool
	 */
	public static function is_well_formed( string $path ): bool {
		$reader = new \XMLReader();

		if ( ! @$reader->open( $path, null, LIBXML_NONET | LIBXML_PARSEHUGE ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Result is checked.
			return false;
		}

		$previous = libxml_use_internal_errors( true );
		$ok       = true;

		while ( @$reader->read() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Errors are collected through libxml.
			continue;
		}

		if ( libxml_get_errors() ) {
			$ok = false;
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$reader->close();

		return $ok;
	}

	// ------------------------------------------------------------------
	// Scheduling
	// ------------------------------------------------------------------

	/**
	 * (Re)create the recurring build event according to the saved schedule.
	 *
	 * @param string $id Feed id.
	 * @return void
	 */
	public static function reschedule( string $id ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $id ) );

		$settings = Marketplace_Registry::settings( $id );
		$schedule = (string) ( $settings['schedule'] ?? 'off' );

		if ( 'off' === $schedule ) {
			return;
		}

		wp_schedule_event( time() + 60, $schedule, self::CRON_HOOK, array( $id ) );
	}

	/**
	 * WP-Cron callback: build and, when unfinished, continue shortly.
	 *
	 * @param string $id Feed id.
	 * @return void
	 */
	public static function cron_build( $id ): void {
		self::cron_run( (string) $id, true );
	}

	/**
	 * WP-Cron callback for the continuation of a long build.
	 *
	 * @param string $id Feed id.
	 * @return void
	 */
	public static function cron_continue( $id ): void {
		self::cron_run( (string) $id, false );
	}

	/**
	 * Shared body of both cron callbacks.
	 *
	 * @param string $id      Feed id.
	 * @param bool   $restart Start from scratch.
	 * @return void
	 */
	private static function cron_run( string $id, bool $restart ): void {
		if ( ! function_exists( 'f2000cs_is_pro' ) || ! f2000cs_is_pro() ) {
			return;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- Best effort.
		}

		$result = self::run( $id, 120, null, $restart );

		if ( 'running' === $result['status'] && ! wp_next_scheduled( self::CONT_HOOK, array( $id ) ) ) {
			wp_schedule_single_event( time() + 30, self::CONT_HOOK, array( $id ) );
		}
	}
}

add_action( Marketplace_Job::CRON_HOOK, array( Marketplace_Job::class, 'cron_build' ) );
add_action( Marketplace_Job::CONT_HOOK, array( Marketplace_Job::class, 'cron_continue' ) );
