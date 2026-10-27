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
	const SYNC_HOOK  = 'f2000cs_marketplace_after_sync';
	const MAX_REPORT = 50;

	/**
	 * A new feed with fewer offers than this share of the last published one is
	 * not published: a broken supplier feed must not wipe the catalog on the
	 * marketplace. The admin can publish it anyway (accept_drop_option()).
	 */
	const MIN_RATIO = 0.5;

	/**
	 * How long «the shrink is expected» stays valid for the next build.
	 */
	const ACCEPT_DROP_TTL = 3600;

	/**
	 * Format of the stored build state. A state of another format (left by an
	 * older version mid-build, e.g. with a row offset instead of an id cursor)
	 * is restarted instead of being continued.
	 */
	const STATE_VERSION = 2;

	/**
	 * A running build older than this is treated as dead and may be restarted.
	 */
	const STATE_MAX_AGE = 21600;

	/**
	 * Without Pro the feed is no longer rebuilt; after this many seconds the
	 * published file is withdrawn so the marketplace stops importing frozen
	 * prices and stock. The schedule stays, so a renewed license resumes it.
	 */
	const STALE_AFTER = 259200;

	/**
	 * A feed above this size (bytes, decimal: the stricter reading of Prom's «180 МБ») is split
	 * into several files, each about the same size and below PART_BYTES.
	 */
	const MAX_FILE_BYTES = 180000000;

	/**
	 * Ceiling of one file of a split feed (bytes).
	 */
	const PART_BYTES = 170000000;

	/**
	 * Name of a per-feed option: f2000cs_mp_<what>_<feed id>.
	 *
	 * @param string $what Kind of option.
	 * @param string $id   Feed id.
	 * @return string
	 */
	private static function option_key( string $what, string $id ): string {
		return 'f2000cs_mp_' . $what . '_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $id ) );
	}

	/**
	 * Option holding the running build.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function state_option( string $id ): string {
		return self::option_key( 'state', $id );
	}

	/**
	 * Option holding the outcome of the last finished build.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function result_option( string $id ): string {
		return self::option_key( 'result', $id );
	}

	/**
	 * Option holding the offer count of the last published feed.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function published_option( string $id ): string {
		return self::option_key( 'published', $id );
	}

	/**
	 * Option allowing the next build to be published even if it shrank a lot.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function accept_drop_option( string $id ): string {
		return self::option_key( 'accept_drop', $id );
	}

	/**
	 * Option flagging that data changed while a build was running.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	public static function dirty_option( string $id ): string {
		return self::option_key( 'dirty', $id );
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

		// Also the guessable working file of 0.7.0 builds before the token was added.
		foreach ( array( $tmp, Marketplace_Storage::dir() . '/.' . $feed->id() . '-build.tmp' ) as $old ) {
			if ( file_exists( $old ) ) {
				wp_delete_file( $old );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Working file of the build, created empty.
		file_put_contents( $tmp, '' );

		$state = array(
			'version'  => self::STATE_VERSION,
			'phase'    => 0,
			'offset'   => 0,
			'tree'     => ( new Marketplace_Category_Tree() )->to_array(),
			'exported' => 0,
			'skipped'  => 0,
			'excluded' => array(),
			'reasons'  => array(),
			'started'  => time(),
		);

		update_option( self::state_option( $feed->id() ), $state, false );
		delete_option( self::dirty_option( $feed->id() ) );

		return $state;
	}

	/**
	 * Run one slice of the build.
	 *
	 * @param string                       $id      Feed id.
	 * @param int                          $budget  Seconds this call may spend.
	 * @param Marketplace_Unit_Source|null $source  Unit source (WooCommerce by default).
	 * @param bool                         $restart Start from scratch.
	 * @param bool                         $resume_only Only continue a running build: when none is
	 *                                                  left (another process finished it), report the
	 *                                                  last result instead of starting a new one.
	 * @return array<string,mixed> Progress: status (running|done|error|locked), counters, message.
	 */
	public static function step( string $id, int $budget = 15, $source = null, bool $restart = false, bool $resume_only = false ): array {
		$feed = Marketplace_Registry::get( $id );

		if ( ! $feed ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Невідомий маркетплейс.', 'factorial2000-catalog-sync' ),
			);
		}

		if ( ! self::acquire_lock( $id ) ) {
			return array(
				'status'  => 'locked',
				'message' => __( 'Вигрузка вже формується.', 'factorial2000-catalog-sync' ),
			) + self::progress( self::state( $id ) );
		}

		try {
			$settings = Marketplace_Registry::settings( $id );
			$state    = self::state( $id );

			if ( $resume_only && ! $restart && ! $state ) {
				self::release_lock( $id );

				$last = self::last_result( $id );

				return $last ? $last : array(
					'status'  => 'error',
					'message' => __( 'Генерацію не знайдено — запустіть її ще раз.', 'factorial2000-catalog-sync' ),
				);
			}

			$source = $source ? $source : new Marketplace_WC_Source( $settings );

			if ( $restart || ! $state || self::STATE_VERSION !== (int) ( $state['version'] ?? 0 ) ) {
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
			$touched  = time();

			while ( microtime( true ) < $deadline ) {
				$phase = $phases[ (int) $state['phase'] ] ?? null;

				if ( null === $phase ) {
					$done = true;
					break;
				}

				// A long step must not let the lock expire under a running build.
				if ( time() - $touched > self::LOCK_TTL / 3 ) {
					self::touch_lock( $id );
					$touched = time();
				}

				$page = $source->fetch( $phase, (int) $state['offset'], self::PAGE_SIZE );

				foreach ( $page['units'] as $unit ) {
					$res = $feed->process_unit( $unit, $tree, $settings );

					if ( '' !== $res['xml'] ) {
						fwrite( $out, $res['xml'] );
					}

					$state['exported'] += (int) $res['exported'];
					$state['skipped']  += (int) $res['skipped'];
					$state['reasons']   = Marketplace_Feed::merge_reasons( (array) $state['reasons'], (array) ( $res['reasons'] ?? array() ) );

					foreach ( $res['excluded'] as $sku ) {
						if ( count( $state['excluded'] ) < self::MAX_REPORT ) {
							$state['excluded'][] = $sku;
						}
					}
				}

				// A cursor (last id seen), not a row offset: it survives products
				// appearing or disappearing between steps.
				$state['offset'] = (int) $page['next'];

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
				self::release_lock( $id );

				// Data changed while this build ran: queue one more, it starts fresh.
				if ( get_option( self::dirty_option( $id ) ) && ! wp_next_scheduled( self::SYNC_HOOK, array( $id ) ) ) {
					wp_schedule_single_event( time() + 15, self::SYNC_HOOK, array( $id ) );
				}

				return $result;
			}

			update_option( self::state_option( $id ), $state, false );
			self::release_lock( $id );

			return array( 'status' => 'running' ) + self::progress( $state );
		} catch ( \Throwable $e ) {
			self::release_lock( $id );
			delete_option( self::accept_drop_option( $id ) );

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

	// ------------------------------------------------------------------
	// Lock
	// ------------------------------------------------------------------

	/**
	 * Name of the lock row.
	 *
	 * @param string $id Feed id.
	 * @return string
	 */
	private static function lock_name( string $id ): string {
		return self::option_key( 'lock', $id );
	}

	/**
	 * Lock values held by this request (feed id => value), so it only ever
	 * extends or releases its own lock, never one taken over by another request.
	 *
	 * @var array<string,string>
	 */
	private static $held_locks = array();

	/**
	 * Whether the lock can live in its own options row (a real database).
	 *
	 * An INSERT IGNORE is atomic, unlike get_transient() + set_transient(), which
	 * two requests can both pass at once. Without a real wpdb (unit tests) the
	 * transient is used.
	 *
	 * @return bool
	 */
	private static function db_lock(): bool {
		global $wpdb;

		return class_exists( '\wpdb' ) && $wpdb instanceof \wpdb;
	}

	/**
	 * Take the build lock; a lock older than LOCK_TTL is taken over.
	 *
	 * @param string $id Feed id.
	 * @return bool Whether this request now holds the lock.
	 */
	private static function acquire_lock( string $id ): bool {
		$name = self::lock_name( $id );

		if ( ! self::db_lock() ) {
			if ( get_transient( $name ) ) {
				return false;
			}

			set_transient( $name, 1, self::LOCK_TTL );

			return true;
		}

		global $wpdb;

		$value = self::new_lock_value();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; must bypass the options cache.
		$taken = (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $value ) );

		if ( ! $taken ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
			$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

			if ( '' !== $current && time() - (int) $current <= self::LOCK_TTL ) {
				return false;
			}

			// A dead request left the lock behind: take it over, but only if nobody else did meanwhile.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
			$taken = (bool) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $name, $current ) );
		}

		if ( $taken ) {
			self::$held_locks[ $id ] = $value;
		}

		return $taken;
	}

	/**
	 * Lock value: the time it was taken or extended (read back as an int) plus a token of this request.
	 *
	 * @return string
	 */
	private static function new_lock_value(): string {
		return time() . ':' . wp_generate_password( 12, false, false );
	}

	/**
	 * Extend the lock while the build is still working.
	 *
	 * @param string $id Feed id.
	 * @return void
	 */
	private static function touch_lock( string $id ): void {
		$name = self::lock_name( $id );

		if ( ! self::db_lock() ) {
			set_transient( $name, 1, self::LOCK_TTL );

			return;
		}

		global $wpdb;

		$held  = self::$held_locks[ $id ] ?? '';
		$value = self::new_lock_value();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See acquire_lock().
		if ( '' !== $held && $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $name, $held ) ) ) {
			self::$held_locks[ $id ] = $value;
		}
	}

	/**
	 * Release the lock.
	 *
	 * @param string $id Feed id.
	 * @return void
	 */
	private static function release_lock( string $id ): void {
		$name = self::lock_name( $id );

		if ( ! self::db_lock() ) {
			delete_transient( $name );

			return;
		}

		global $wpdb;

		$held = self::$held_locks[ $id ] ?? '';
		unset( self::$held_locks[ $id ] );

		if ( '' !== $held ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See acquire_lock().
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $held ) );
		}
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
		$result = array(
			'finished'   => time(),
			'duration'   => max( 0, time() - (int) ( $state['started'] ?? time() ) ),
			'exported'   => (int) $state['exported'],
			'skipped'    => (int) $state['skipped'],
			'categories' => $tree->count(),
			'excluded'   => array_values( (array) $state['excluded'] ),
			'reasons'    => (array) ( $state['reasons'] ?? array() ),
		);

		// The admin's «the shrink is expected» covers this one build only, and only shortly after the click.
		$accepted_at = (int) get_option( self::accept_drop_option( $id ), 0 );
		$accepted    = $accepted_at > 0 && time() - $accepted_at <= self::ACCEPT_DROP_TTL;
		delete_option( self::accept_drop_option( $id ) );

		if ( $result['exported'] < 1 ) {
			$result['status']  = 'error';
			$result['message'] = __( 'У вигрузці немає жодного товару — попередній файл залишено без змін. Перевірте, що є опубліковані товари з ціною, фото та категорією.', 'factorial2000-catalog-sync' );
			wp_delete_file( $tmp );
			update_option( self::result_option( $id ), $result, false );

			return $result;
		}

		$previous = (int) get_option( self::published_option( $id ), 0 );

		if ( ! $accepted && $previous > 0 && $result['exported'] < $previous * self::MIN_RATIO ) {
			$result['status']  = 'error';
			$result['drop']    = true;
			$result['message'] = sprintf(
				/* translators: 1: new offer count, 2: previous offer count */
				__( 'У новій вигрузці лише %1$d товарів проти %2$d у попередній — схоже на збій даних. Файл не оновлено, Prom бачить попередню версію. Якщо скорочення очікуване, натисніть «Скорочення очікуване — опублікувати».', 'factorial2000-catalog-sync' ),
				$result['exported'],
				$previous
			);
			wp_delete_file( $tmp );
			update_option( self::result_option( $id ), $result, false );

			if ( function_exists( 'f2000cs_log' ) ) {
				f2000cs_log( 'Marketplace feed "' . $id . '": ' . $result['message'], 'warning' );
			}

			return $result;
		}

		$parts = self::assemble( $feed, $settings, $tree, $tmp );
		wp_delete_file( $tmp );

		foreach ( $parts as $file ) {
			if ( ! self::is_well_formed( $file ) ) {
				foreach ( $parts as $leftover ) {
					wp_delete_file( $leftover );
				}

				$result['status']  = 'error';
				$result['message'] = __( 'Згенерований файл не є коректним XML — попередній файл залишено без змін.', 'factorial2000-catalog-sync' );
				update_option( self::result_option( $id ), $result, false );

				return $result;
			}
		}

		foreach ( $parts as $i => $file ) {
			if ( ! self::publish_file( $file, Marketplace_Storage::part_path( $feed, $i + 1 ) ) ) {
				foreach ( $parts as $leftover ) {
					wp_delete_file( $leftover );
				}

				$result['status']  = 'error';
				$result['message'] = __( 'Не вдалося опублікувати файл вигрузки — попередній файл залишено без змін.', 'factorial2000-catalog-sync' );
				update_option( self::result_option( $id ), $result, false );

				return $result;
			}
		}

		// A smaller build than the last one: its extra numbered files are gone from the feed.
		Marketplace_Storage::delete_parts_from( $feed, count( $parts ) + 1 );

		update_option( self::published_option( $id ), (int) $result['exported'], false );

		$published = Marketplace_Storage::parts( $feed );

		$result['status'] = 'done';
		$result['size']   = (int) array_sum( array_column( $published, 'size' ) );
		$result['url']    = Marketplace_Storage::url( $feed );
		$result['files']  = count( $published );

		update_option( self::result_option( $id ), $result, false );

		return $result;
	}

	/**
	 * Write the feed document(s) from the working file of offers.
	 *
	 * Normally one file. When the whole document would exceed MAX_FILE_BYTES it is cut into
	 * files of about equal size: every file is a complete YML with the same shop head and
	 * category list, and the variations of one group (same group_id) never end up in
	 * different files. Returned paths still carry the ".part" suffix.
	 *
	 * @param Marketplace_Feed          $feed     Feed.
	 * @param array<string,mixed>       $settings Settings.
	 * @param Marketplace_Category_Tree $tree     Category tree.
	 * @param string                    $tmp      Working file with the offers.
	 * @return array<int,string> Paths of the files to publish, in order.
	 * @throws \RuntimeException When a file cannot be written.
	 */
	private static function assemble( Marketplace_Feed $feed, array $settings, Marketplace_Category_Tree $tree, string $tmp ): array {
		$head     = $feed->document_head( $settings, $tree );
		$tail     = $feed->tail();
		$overhead = strlen( $head ) + strlen( $tail );
		$total    = $overhead + (int) filesize( $tmp );

		/**
		 * Filters the size above which the feed is split into several files (bytes).
		 *
		 * @param int $bytes Default 180 000 000.
		 */
		$max_file = max( 1, (int) apply_filters( 'f2000cs_marketplace_max_file_bytes', self::MAX_FILE_BYTES ) );

		/**
		 * Filters the ceiling of one file of a split feed (bytes).
		 *
		 * @param int $bytes Default 170 000 000.
		 */
		$part_max = max( 1, (int) apply_filters( 'f2000cs_marketplace_part_bytes', self::PART_BYTES ) );
		$count    = $total > $max_file ? min( Marketplace_Storage::MAX_PARTS, (int) ceil( $total / $part_max ) ) : 1;
		$target   = (int) ceil( ( $total - $overhead ) / $count );
		$in       = fopen( $tmp, 'rb' );

		if ( ! $in ) {
			throw new \RuntimeException( esc_html__( 'Не вдалося зібрати файл вигрузки.', 'factorial2000-catalog-sync' ) );
		}

		$paths   = array();
		$out     = null;
		$written = 0;
		$group   = null;
		$block   = '';

		$open = static function () use ( &$out, &$paths, &$written, $feed, $head ) {
			$path = Marketplace_Storage::part_path( $feed, count( $paths ) + 1 ) . '.part';
			$out  = fopen( $path, 'wb' );

			if ( ! $out ) {
				throw new \RuntimeException( esc_html__( 'Не вдалося зібрати файл вигрузки.', 'factorial2000-catalog-sync' ) );
			}

			fwrite( $out, $head );
			$paths[] = $path;
			$written = 0;
		};

		$close = static function () use ( &$out, $tail ) {
			if ( $out ) {
				fwrite( $out, $tail );
				fclose( $out );
				$out = null;
			}
		};

		// An offer block starts at its opening tag line and runs to the next one.
		$flush = static function () use ( &$block, &$out, &$written, &$group, &$paths, $count, $target, $open, $close, $feed ) {
			if ( '' === $block ) {
				return;
			}

			$this_group = $feed->offer_group_id( $block );

			if ( ! $out ) {
				$open();
			} elseif ( $written >= $target && count( $paths ) < $count && ( '' === $this_group || $this_group !== $group ) ) {
				// A new file starts only between groups.
				$close();
				$open();
			}

			fwrite( $out, $block );
			$written += strlen( $block );
			$group    = $this_group;
			$block    = '';
		};

		$offer_prefix = $feed->offer_line_prefix();
		$line         = fgets( $in );

		while ( false !== $line ) {
			if ( 0 === strpos( $line, $offer_prefix ) ) {
				$flush();
			}

			$block .= $line;
			$line   = fgets( $in );
		}

		$flush();

		fclose( $in );

		if ( ! $out ) {
			$open();
		}

		$close();

		return $paths;
	}

	/**
	 * Move a finished ".part" file over the published one.
	 *
	 * @param string $from Source.
	 * @param string $to   Destination.
	 * @return bool
	 */
	private static function publish_file( string $from, string $to ): bool {
		if ( @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Falls back to copy below.
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Windows cannot rename over an open file.
		$copied = copy( $from, $to );
		wp_delete_file( $from );

		return $copied;
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
	 * Recreate missing recurring events (e.g. after the plugin was deactivated
	 * and activated again, which clears them).
	 *
	 * @return void
	 */
	public static function ensure_schedules(): void {
		foreach ( array_keys( Marketplace_Registry::all() ) as $id ) {
			$id = (string) $id;

			if ( 'off' !== Marketplace_Registry::settings( $id )['schedule'] && ! wp_next_scheduled( self::CRON_HOOK, array( $id ) ) ) {
				self::reschedule( $id );
			}
		}
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
			self::withdraw_stale_feed( $id );
			return;
		}

		// A build is in progress: restarting would reset its progress, and with a
		// sync more frequent than a build it would never finish. Flag it instead;
		// a fresh build is queued when this one is done.
		if ( $restart ) {
			$running = self::state( $id );

			if ( $running && time() - (int) ( $running['started'] ?? 0 ) < self::STATE_MAX_AGE ) {
				update_option( self::dirty_option( $id ), time(), false );

				if ( ! wp_next_scheduled( self::CONT_HOOK, array( $id ) ) ) {
					wp_schedule_single_event( time() + 30, self::CONT_HOOK, array( $id ) );
				}

				return;
			}
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- Best effort.
		}

		$result = self::run( $id, 120, null, $restart );

		// Another build holds the lock: a requested fresh build must not be lost
		// (it may carry the prices of a stock sync that finished after that build began).
		if ( 'locked' === $result['status'] ) {
			if ( $restart && ! wp_next_scheduled( self::SYNC_HOOK, array( $id ) ) ) {
				wp_schedule_single_event( time() + 60, self::SYNC_HOOK, array( $id ) );
			}

			return;
		}

		if ( 'running' === $result['status'] && ! wp_next_scheduled( self::CONT_HOOK, array( $id ) ) ) {
			wp_schedule_single_event( time() + 30, self::CONT_HOOK, array( $id ) );
		}
	}

	/**
	 * After a stock update cycle, queue a rebuild of every feed that asks for it.
	 *
	 * The build runs in its own WP-Cron event shortly after, so the stock sync is
	 * not slowed down by it; the marketplace keeps reading the previous file
	 * until the new one is published. Without Pro nothing is rebuilt, and an
	 * outdated feed is withdrawn here too (a feed without a schedule has no
	 * cron event of its own that would do it).
	 *
	 * @return void
	 */
	public static function queue_after_stock_update(): void {
		if ( ! function_exists( 'f2000cs_is_pro' ) || ! f2000cs_is_pro() ) {
			foreach ( array_keys( Marketplace_Registry::all() ) as $id ) {
				self::withdraw_stale_feed( (string) $id );
			}

			return;
		}

		foreach ( array_keys( Marketplace_Registry::all() ) as $id ) {
			$id = (string) $id;

			if ( empty( Marketplace_Registry::settings( $id )['after_sync'] ) ) {
				continue;
			}

			if ( ! wp_next_scheduled( self::SYNC_HOOK, array( $id ) ) ) {
				wp_schedule_single_event( time() + 15, self::SYNC_HOOK, array( $id ) );
			}
		}
	}

	/**
	 * Delete the published feed once it is older than STALE_AFTER.
	 *
	 * @param string $id Feed id.
	 * @return bool Whether the file was deleted.
	 */
	public static function withdraw_stale_feed( string $id ): bool {
		$feed = Marketplace_Registry::get( $id );

		// No feed was ever built: do not create the folder or the file token.
		if ( ! $feed || ! is_dir( Marketplace_Storage::base_dir() ) ) {
			return false;
		}

		$path = Marketplace_Storage::path( $feed );

		if ( ! is_file( $path ) || time() - (int) filemtime( $path ) <= self::STALE_AFTER ) {
			return false;
		}

		wp_delete_file( $path );
		Marketplace_Storage::delete_parts_from( $feed, 2 );

		if ( function_exists( 'f2000cs_log' ) ) {
			f2000cs_log( sprintf( 'Marketplace feed "%s" withdrawn: Pro is not active and the file was no longer updated.', $id ), 'warning' );
		}

		return true;
	}
}

add_action( Marketplace_Job::CRON_HOOK, array( Marketplace_Job::class, 'cron_build' ) );
add_action( Marketplace_Job::CONT_HOOK, array( Marketplace_Job::class, 'cron_continue' ) );
add_action( Marketplace_Job::SYNC_HOOK, array( Marketplace_Job::class, 'cron_build' ) );
add_action( 'f2000cs_stock_update_complete', array( Marketplace_Job::class, 'queue_after_stock_update' ) );
add_action(
	'admin_init',
	static function () {
		// Only on the plugin's own screens; activation restores the events as well.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 0 === strpos( $page, 'f2000cs' ) ) {
			Marketplace_Job::ensure_schedules();
		}
	}
);
