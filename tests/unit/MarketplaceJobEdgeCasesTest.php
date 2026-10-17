<?php
/**
 * Marketplace job / storage edge cases and the uninstall cleanup.
 *
 * Complements MarketplacePromFeedTest: well-formedness check, storage helpers,
 * failed builds that must leave the published feed untouched, and what an
 * uninstall removes.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Category_Tree;
use F2000CS\Marketplace_Feed;
use F2000CS\Marketplace_Job;
use F2000CS\Marketplace_Registry;
use F2000CS\Marketplace_Storage;
use F2000CS\Marketplace_Unit_Source;

/**
 * Minimal feed whose output can be made malformed.
 */
final class F2000CS_Edge_Feed extends Marketplace_Feed {

	/**
	 * Closing markup of the document.
	 *
	 * @var string
	 */
	public static $tail = "    </offers>\n  </shop>\n</root>\n";

	/**
	 * Offers per group_id (0 = no groups).
	 *
	 * @var int
	 */
	public static $group_size = 0;

	/**
	 * @return string
	 */
	public function id(): string {
		return 'edge';
	}

	/**
	 * @return string
	 */
	public function label(): string {
		return 'Edge';
	}

	/**
	 * @return array<string,array>
	 */
	public function fields(): array {
		return array();
	}

	/**
	 * @param array $settings Settings.
	 * @return string
	 */
	public function head( array $settings ): string {
		return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<root>\n  <shop>\n";
	}

	/**
	 * @return string
	 */
	public function tail(): string {
		return self::$tail;
	}

	/**
	 * @param array                     $unit     Unit.
	 * @param Marketplace_Category_Tree $tree     Tree.
	 * @param array                     $settings Settings.
	 * @return array
	 */
	public function process_unit( array $unit, Marketplace_Category_Tree $tree, array $settings ): array {
		$group = self::$group_size > 0 ? ' group_id="g' . ( intdiv( (int) $unit['id'] - 1, self::$group_size ) + 1 ) . '"' : '';

		return array(
			'xml'      => '      <offer id="' . (int) $unit['id'] . '"' . $group . "/>\n",
			'exported' => 1,
			'skipped'  => 0,
			'excluded' => array(),
		);
	}
}

/**
 * Source with N units, optionally failing on fetch.
 */
final class F2000CS_Edge_Source implements Marketplace_Unit_Source {

	/**
	 * @var int
	 */
	private $count;

	/**
	 * @var bool
	 */
	private $fail;

	/**
	 * @param int  $count Number of units.
	 * @param bool $fail  Throw on fetch.
	 */
	public function __construct( int $count, bool $fail = false ) {
		$this->count = $count;
		$this->fail  = $fail;
	}

	/**
	 * @return array<int,string>
	 */
	public function phases(): array {
		return array( 'simple' );
	}

	/**
	 * @param string $phase  Phase.
	 * @param int    $offset Cursor.
	 * @param int    $limit  Limit.
	 * @return array
	 */
	public function fetch( string $phase, int $offset, int $limit ): array {
		if ( $this->fail ) {
			throw new RuntimeException( 'source exploded' );
		}

		$units = array();

		for ( $i = $offset + 1; $i <= min( $this->count, $offset + $limit ); $i++ ) {
			$units[] = array( 'id' => $i );
		}

		return array(
			'units' => $units,
			'next'  => $offset + count( $units ),
			'done'  => count( $units ) < $limit,
		);
	}
}

/**
 * Tests.
 */
final class F2000CS_Unit_MarketplaceJobEdgeCasesTest extends F2000CS_Unit_TestCase {

	/**
	 * Fresh registry with the edge feed and clean upload folder.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		F2000CS_Edge_Feed::$tail       = "    </offers>\n  </shop>\n</root>\n";
		F2000CS_Edge_Feed::$group_size = 0;
		self::clear_limit_filters();

		Marketplace_Registry::reset();
		Marketplace_Registry::register( new F2000CS_Edge_Feed() );
		$this->remove_dir( Marketplace_Storage::base_dir() );
	}

	/**
	 * Leave no feed folder behind.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		self::clear_limit_filters();
		$this->remove_dir( Marketplace_Storage::base_dir() );
		Marketplace_Registry::reset();

		parent::tearDown();
	}

	/**
	 * Recursive delete.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			is_dir( "$dir/$item" ) ? $this->remove_dir( "$dir/$item" ) : unlink( "$dir/$item" );
		}

		rmdir( $dir );
	}

	/**
	 * Temp file with contents.
	 *
	 * @param string $contents File contents.
	 * @return string Path.
	 */
	private function temp_file( string $contents ): string {
		$path = tempnam( sys_get_temp_dir(), 'f2000cs' );
		file_put_contents( $path, $contents );

		return $path;
	}

	// ------------------------------------------------------- is_well_formed

	/**
	 * Valid, truncated, mismatched and missing files.
	 *
	 * @return void
	 */
	public function test_is_well_formed(): void {
		$good      = $this->temp_file( '<a><b>1</b></a>' );
		$truncated = $this->temp_file( '<a><b>1</b>' );
		$mismatch  = $this->temp_file( '<a><b>1</a></b>' );
		$garbage   = $this->temp_file( 'not xml at all' );

		$this->assertTrue( Marketplace_Job::is_well_formed( $good ) );
		$this->assertFalse( Marketplace_Job::is_well_formed( $truncated ) );
		$this->assertFalse( Marketplace_Job::is_well_formed( $mismatch ) );
		$this->assertFalse( Marketplace_Job::is_well_formed( $garbage ) );
		$this->assertFalse( Marketplace_Job::is_well_formed( sys_get_temp_dir() . '/f2000cs-does-not-exist.xml' ) );

		array_map( 'unlink', array( $good, $truncated, $mismatch, $garbage ) );
	}

	/**
	 * Errors from one check do not leak into the next (libxml state is restored).
	 *
	 * @return void
	 */
	public function test_is_well_formed_does_not_leak_libxml_errors(): void {
		$bad  = $this->temp_file( '<a>' );
		$good = $this->temp_file( '<a/>' );

		$before = libxml_use_internal_errors();
		$this->assertFalse( Marketplace_Job::is_well_formed( $bad ) );
		$this->assertTrue( Marketplace_Job::is_well_formed( $good ), 'A failed check must not poison the next one' );
		$this->assertSame( $before, libxml_use_internal_errors(), 'The caller\'s libxml mode is restored' );

		unlink( $bad );
		unlink( $good );
	}

	// -------------------------------------------------------------- storage

	/**
	 * base_dir() only computes the path; dir() creates the folder with an index.php.
	 *
	 * @return void
	 */
	public function test_storage_dir_is_created_on_demand_with_an_index(): void {
		$base = Marketplace_Storage::base_dir();

		$this->assertDirectoryDoesNotExist( $base, 'base_dir() must not create anything' );

		$this->assertSame( $base, Marketplace_Storage::dir() );
		$this->assertDirectoryExists( $base );
		$this->assertFileExists( $base . '/index.php', 'Directory listing is silenced' );
	}

	/**
	 * info() of a missing and an existing feed file.
	 *
	 * @return void
	 */
	public function test_storage_info(): void {
		$feed = Marketplace_Registry::get( 'edge' );

		$this->assertSame(
			array(
				'exists' => false,
				'size'   => 0,
				'mtime'  => 0,
				'parts'  => array(),
			),
			Marketplace_Storage::info( $feed )
		);

		file_put_contents( Marketplace_Storage::path( $feed ), '<root/>' );
		touch( Marketplace_Storage::path( $feed ), 1700000000 );
		clearstatcache();

		$this->assertSame(
			array(
				'exists' => true,
				'size'   => 7,
				'mtime'  => 1700000000,
				'parts'  => array(
					array(
						'n'     => 1,
						'url'   => Marketplace_Storage::url( $feed ),
						'size'  => 7,
						'mtime' => 1700000000,
					),
				),
			),
			Marketplace_Storage::info( $feed )
		);
	}

	/**
	 * Token option name ignores odd characters and tokens are per feed.
	 *
	 * @return void
	 */
	public function test_storage_token_is_per_feed_and_regenerated_when_invalid(): void {
		$a = Marketplace_Storage::token( 'edge' );
		$b = Marketplace_Storage::token( 'other' );

		$this->assertNotSame( $a, $b );

		update_option( 'f2000cs_mp_token_edge', 'short', false );
		$fresh = Marketplace_Storage::token( 'edge' );

		$this->assertMatchesRegularExpression( '/^[a-z0-9]{20,}$/', $fresh );
		$this->assertNotSame( 'short', $fresh );
		$this->assertSame( $fresh, Marketplace_Storage::token( 'edge' ) );
	}

	// ----------------------------------------------------------- job: errors

	/**
	 * Unknown feed id.
	 *
	 * @return void
	 */
	public function test_step_of_unknown_feed_is_an_error(): void {
		$result = Marketplace_Job::step( 'nope', 5, new F2000CS_Edge_Source( 1 ) );

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( array(), Marketplace_Job::state( 'nope' ) );
	}

	/**
	 * A source that throws ends the build with an error and frees the lock.
	 *
	 * @return void
	 */
	public function test_failing_source_reports_error_and_releases_lock(): void {
		$result = Marketplace_Job::step( 'edge', 5, new F2000CS_Edge_Source( 3, true ), true );

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 'source exploded', $result['message'] );
		$this->assertSame( array(), Marketplace_Job::state( 'edge' ), 'No half-done state is left' );
		$this->assertSame( 'error', Marketplace_Job::last_result( 'edge' )['status'] );

		$next = Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 3 ) );
		$this->assertSame( 'done', $next['status'], 'The lock was released, a new build can run' );
	}

	/**
	 * A malformed document is never published and the old file stays.
	 *
	 * @return void
	 */
	public function test_malformed_output_keeps_previous_feed(): void {
		$feed = Marketplace_Registry::get( 'edge' );
		$path = Marketplace_Storage::path( $feed );

		$ok = Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 3 ) );
		$this->assertSame( 'done', $ok['status'] );
		$good = file_get_contents( $path );
		$this->assertStringContainsString( '<offer id="3"/>', $good );

		F2000CS_Edge_Feed::$tail = "    </offers>\n  </shop>\n"; // Root never closed.
		$bad                     = Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 3 ) );

		$this->assertSame( 'error', $bad['status'] );
		$this->assertSame( $good, file_get_contents( $path ), 'Previous feed is kept' );
		$this->assertFileDoesNotExist( $path . '.part' );
		$this->assertFileDoesNotExist( Marketplace_Storage::build_path( $feed ) );
		$this->assertSame( 3, (int) get_option( Marketplace_Job::published_option( 'edge' ) ) );
	}

	/**
	 * When the file cannot be moved into place the build is reported as failed,
	 * not as done.
	 *
	 * @return void
	 */
	public function test_unpublishable_file_is_an_error_not_done(): void {
		$feed  = Marketplace_Registry::get( 'edge' );
		$final = Marketplace_Storage::path( $feed );

		// A non-empty directory in place of the feed file: neither rename() nor copy() can replace it.
		mkdir( $final, 0777, true );
		file_put_contents( $final . '/keep.txt', 'x' );

		$result = @Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 3 ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- rename()/copy() warn.

		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 0, (int) get_option( Marketplace_Job::published_option( 'edge' ), 0 ), 'Nothing counts as published' );
		$this->assertFileDoesNotExist( $final . '.part' );
	}

	/**
	 * A stored state of another format is restarted, not continued.
	 *
	 * @return void
	 */
	public function test_state_of_another_version_is_restarted(): void {
		update_option(
			Marketplace_Job::state_option( 'edge' ),
			array(
				'version'  => 1,
				'phase'    => 0,
				'offset'   => 999,
				'tree'     => array(),
				'exported' => 500,
				'skipped'  => 0,
				'excluded' => array(),
				'reasons'  => array(),
				'started'  => time(),
			),
			false
		);

		$result = Marketplace_Job::step( 'edge', 10, new F2000CS_Edge_Source( 3 ) );

		$this->assertSame( 'done', $result['status'] );
		$this->assertSame( 3, $result['exported'], 'The old counters were discarded' );
	}

	/**
	 * Without a license the lock-free WP-Cron path withdraws instead of building.
	 *
	 * @return void
	 */
	public function test_cron_without_pro_does_not_build(): void {
		Marketplace_Job::cron_build( 'edge' );

		$this->assertSame( array(), Marketplace_Job::state( 'edge' ) );
		$this->assertSame( array(), Marketplace_Job::last_result( 'edge' ) );
	}

	/**
	 * Schedules: 'off' clears, anything else creates exactly one event.
	 *
	 * @return void
	 */
	public function test_reschedule_replaces_the_event(): void {
		Marketplace_Registry::save_settings( 'edge', array( 'schedule' => 'daily' ) );
		Marketplace_Job::reschedule( 'edge' );
		Marketplace_Job::reschedule( 'edge' );

		$events = array_filter(
			F2000CS_Test_State::$cron_events,
			static function ( $event ) {
				return Marketplace_Job::CRON_HOOK === $event['hook'];
			}
		);
		$this->assertCount( 1, $events );

		Marketplace_Registry::save_settings( 'edge', array( 'schedule' => 'off' ) );
		Marketplace_Job::reschedule( 'edge' );
		$this->assertFalse( wp_next_scheduled( Marketplace_Job::CRON_HOOK, array( 'edge' ) ) );
	}

	// ------------------------------------------------------------ uninstall

	/**
	 * Uninstall removes options, per-feed rows' cron events and the private folders.
	 *
	 * @return void
	 */
	public function test_uninstall_cleanup_removes_options_cron_and_folders(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/uninstall-cleanup.php';

		update_option( 'f2000cs_url', 'https://x.test/feed.xml' );
		update_option( 'f2000cs_url_2', 'https://y.test/feed.xml' );
		update_option( 'f2000cs_sku_prefix_2', 'B_' );
		update_option( 'f2000cs_update_interval', 'hourly' );
		update_option( 'f2000cs_telegram_token_id', 'secret' );
		update_option( 'unrelated_option', 'stay' );
		set_transient( 'f2000cs_activated', 1, 60 );

		wp_schedule_event( time() + 60, 'daily', Marketplace_Job::CRON_HOOK, array( 'edge' ) );
		wp_schedule_single_event( time() + 60, Marketplace_Job::CONT_HOOK, array( 'edge' ) );
		wp_schedule_single_event( time() + 60, 'f2000cs_single_update_event', array( 'https://x.test/feed.xml', 'A_' ) );

		$feeds = Marketplace_Storage::dir();
		file_put_contents( $feeds . '/edge-token.xml', '<root/>' );

		$upload = wp_upload_dir();
		mkdir( $upload['basedir'] . '/f2000cs-exports', 0777, true );
		file_put_contents( $upload['basedir'] . '/f2000cs-exports/x.xml', '<root/>' );

		f2000cs_uninstall_cleanup();

		foreach ( array( 'f2000cs_url', 'f2000cs_url_2', 'f2000cs_sku_prefix_2', 'f2000cs_update_interval', 'f2000cs_telegram_token_id' ) as $name ) {
			$this->assertFalse( get_option( $name, false ), "$name is removed" );
		}

		$this->assertSame( 'stay', get_option( 'unrelated_option' ), 'Foreign options are untouched' );
		$this->assertFalse( get_transient( 'f2000cs_activated' ) );
		$this->assertSame( array(), F2000CS_Test_State::$cron_events, 'All plugin events are gone' );
		$this->assertDirectoryDoesNotExist( $feeds );
		$this->assertDirectoryDoesNotExist( $upload['basedir'] . '/f2000cs-exports' );
	}

	/**
	 * Recursive folder delete copes with nesting and a missing path.
	 *
	 * @return void
	 */
	public function test_uninstall_delete_dir_is_recursive_and_tolerant(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/uninstall-cleanup.php';

		$root = sys_get_temp_dir() . '/f2000cs-del-' . getmypid();
		mkdir( $root . '/a/b', 0777, true );
		file_put_contents( $root . '/a/b/f.txt', 'x' );
		file_put_contents( $root . '/top.txt', 'x' );

		f2000cs_uninstall_delete_dir( $root );
		$this->assertDirectoryDoesNotExist( $root );

		f2000cs_uninstall_delete_dir( $root ); // Already gone: no error.
		f2000cs_uninstall_delete_dir( '' );
		$this->addToAssertionCount( 1 );
	}

	// ------------------------------------------------------------- splitting

	/**
	 * Remove only the split-limit filters (other hooks belong to other tests).
	 *
	 * @return void
	 */
	private static function clear_limit_filters(): void {
		unset(
			F2000CS_Test_State::$hooks['f2000cs_marketplace_max_file_bytes'],
			F2000CS_Test_State::$hooks['f2000cs_marketplace_part_bytes']
		);
	}

	/**
	 * Make the split limits tiny for a test.
	 *
	 * @param int $max  Size above which the feed is split.
	 * @param int $part Ceiling of one file.
	 * @return void
	 */
	private function tiny_limits( int $max, int $part ): void {
		self::clear_limit_filters();

		add_filter(
			'f2000cs_marketplace_max_file_bytes',
			static function () use ( $max ) {
				return $max;
			}
		);
		add_filter(
			'f2000cs_marketplace_part_bytes',
			static function () use ( $part ) {
				return $part;
			}
		);
	}

	/**
	 * Read the offer ids of a published file.
	 *
	 * @param string $path File.
	 * @return array<int,int>
	 */
	private function offer_ids( string $path ): array {
		preg_match_all( '/<offer id="(\d+)"/', (string) file_get_contents( $path ), $m );

		return array_map( 'intval', $m[1] );
	}

	/**
	 * Small feed stays one file at the old link.
	 *
	 * @return void
	 */
	public function test_small_feed_is_one_file(): void {
		$result = Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 5 ) );
		$feed   = Marketplace_Registry::get( 'edge' );

		$this->assertSame( 'done', $result['status'] );
		$this->assertSame( 1, $result['files'] );
		$this->assertCount( 1, Marketplace_Storage::parts( $feed ) );
		$this->assertSame( Marketplace_Storage::url( $feed ), Marketplace_Storage::part_url( $feed, 1 ) );
		$this->assertSame( array( 1, 2, 3, 4, 5 ), $this->offer_ids( Marketplace_Storage::path( $feed ) ) );
	}

	/**
	 * Above the limit the feed is cut into complete files; every offer lands in exactly one.
	 *
	 * @return void
	 */
	public function test_big_feed_is_split_into_complete_files(): void {
		$this->tiny_limits( 300, 200 );

		$result = Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 20 ) );
		$feed   = Marketplace_Registry::get( 'edge' );
		$parts  = Marketplace_Storage::parts( $feed );

		$this->assertSame( 'done', $result['status'] );
		$this->assertGreaterThan( 1, count( $parts ) );
		$this->assertSame( count( $parts ), $result['files'] );
		$this->assertSame( 20, $result['exported'] );
		$this->assertStringEndsWith( '-2.xml', Marketplace_Storage::part_url( $feed, 2 ) );

		$all = array();

		foreach ( $parts as $part ) {
			$path = Marketplace_Storage::part_path( $feed, $part['n'] );

			$this->assertTrue( Marketplace_Job::is_well_formed( $path ), 'Every file is complete XML' );
			$this->assertStringContainsString( '<root>', (string) file_get_contents( $path ) );
			$this->assertFileDoesNotExist( $path . '.part' );

			$all = array_merge( $all, $this->offer_ids( $path ) );
		}

		$this->assertSame( range( 1, 20 ), $all, 'No offer lost or duplicated, order kept' );
	}

	/**
	 * Variations of one group_id are never separated.
	 *
	 * @return void
	 */
	public function test_split_keeps_a_group_together(): void {
		F2000CS_Edge_Feed::$group_size = 4;
		$this->tiny_limits( 300, 150 );

		Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 20 ) );
		$feed = Marketplace_Registry::get( 'edge' );

		$groups_in = array();

		foreach ( Marketplace_Storage::parts( $feed ) as $part ) {
			preg_match_all( '/group_id="(g\d+)"/', (string) file_get_contents( Marketplace_Storage::part_path( $feed, $part['n'] ) ), $m );

			foreach ( array_unique( $m[1] ) as $group ) {
				$groups_in[ $group ][] = $part['n'];
			}
		}

		$this->assertCount( 5, $groups_in );

		foreach ( $groups_in as $group => $files ) {
			$this->assertCount( 1, $files, "Group $group sits in one file" );
		}
	}

	/**
	 * A later, smaller build removes the files it no longer needs.
	 *
	 * @return void
	 */
	public function test_smaller_build_removes_surplus_files(): void {
		$this->tiny_limits( 300, 200 );

		Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 20 ) );
		$feed = Marketplace_Registry::get( 'edge' );
		$this->assertGreaterThan( 1, count( Marketplace_Storage::parts( $feed ) ) );

		self::clear_limit_filters();
		update_option( Marketplace_Job::published_option( 'edge' ), 0, false );

		Marketplace_Job::run( 'edge', 10, new F2000CS_Edge_Source( 11 ), true );

		$this->assertCount( 1, Marketplace_Storage::parts( $feed ), 'Back to one file' );
		$this->assertFileDoesNotExist( Marketplace_Storage::part_path( $feed, 2 ) );
	}
}
