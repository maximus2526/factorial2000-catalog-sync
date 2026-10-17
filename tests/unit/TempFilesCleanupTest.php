<?php
/**
 * Stale temp-file sweep and cache-flush scope tests.
 *
 * A mass URL import used to leave f2000cs_*.tmp files in the temp dir; the sweep
 * (cron + admin) removes stale ones, and the object-cache cleanup flushes only
 * the transients group instead of the whole cache (which used to drop the import
 * session cache mid-run).
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Temp file sweep tests.
 */
final class F2000CS_Unit_TempFilesCleanupTest extends F2000CS_Unit_TestCase {

	/**
	 * Make sure the cleanup module is loaded.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/includes/uninstall-cleanup.php';
	}

	/**
	 * Create a temp file with a given age.
	 *
	 * @param string $name File name inside the temp dir.
	 * @param int    $age  Age in seconds.
	 * @return string Path.
	 */
	private function temp_file( string $name, int $age ): string {
		$path = get_temp_dir() . $name;
		file_put_contents( $path, 'x' );
		touch( $path, time() - $age );

		return $path;
	}

	/**
	 * The sweep removes stale f2000cs files only.
	 *
	 * @return void
	 */
	public function test_sweep_removes_only_stale_plugin_temp_files() {
		$stale   = $this->temp_file( 'f2000cs_import_stale-abc.tmp', 3 * HOUR_IN_SECONDS );
		$fresh   = $this->temp_file( 'f2000cs_curr_fresh-def.tmp', 60 );
		$foreign = $this->temp_file( 'another-plugin-stale.tmp', 3 * HOUR_IN_SECONDS );

		$removed = f2000cs_cleanup_stale_temp_files();

		$this->assertSame( 1, $removed );
		$this->assertFileDoesNotExist( $stale );
		$this->assertFileExists( $fresh, 'Fresh files of running sessions are kept' );
		$this->assertFileExists( $foreign, 'Foreign temp files are untouched' );

		wp_delete_file( $fresh );
		wp_delete_file( $foreign );
	}

	/**
	 * The sweep works with a native Windows temp path (backslashes): glob()
	 * treats "\" as an escape character, so the pattern must be normalized.
	 *
	 * @return void
	 */
	public function test_sweep_handles_a_native_windows_temp_path() {
		F2000CS_Test_State::$temp_dir = rtrim( sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR;

		$stale = F2000CS_Test_State::$temp_dir . 'f2000cs_import_native-abc.tmp';
		file_put_contents( $stale, 'x' );
		touch( $stale, time() - 3 * HOUR_IN_SECONDS );

		$removed = f2000cs_cleanup_stale_temp_files();

		$this->assertSame( 1, $removed, 'A backslash temp path must still be swept' );
		$this->assertFileDoesNotExist( $stale );
	}

	/**
	 * The cron event runs the sweep.
	 *
	 * @return void
	 */
	public function test_cron_event_runs_the_sweep() {
		$this->assertNotFalse( has_filter( 'f2000cs_temp_cleanup_cron', 'f2000cs_cleanup_stale_temp_files' ) );

		$stale = $this->temp_file( 'f2000cs_import_cron-xyz.tmp', 3 * HOUR_IN_SECONDS );

		do_action( 'f2000cs_temp_cleanup_cron' );

		$this->assertFileDoesNotExist( $stale );
	}

	/**
	 * The admin sweep is wired, throttled to once an hour, and schedules the daily event.
	 *
	 * @return void
	 */
	public function test_admin_sweep_is_throttled_and_schedules_the_daily_event() {
		$this->assertNotFalse( has_filter( 'admin_init', 'f2000cs_maybe_cleanup_stale_temp_files' ) );

		$first = $this->temp_file( 'f2000cs_import_admin1-abc.tmp', 3 * HOUR_IN_SECONDS );
		f2000cs_maybe_cleanup_stale_temp_files();
		$this->assertFileDoesNotExist( $first );
		$this->assertNotFalse( wp_next_scheduled( 'f2000cs_temp_cleanup_cron' ) );

		$second = $this->temp_file( 'f2000cs_import_admin2-def.tmp', 3 * HOUR_IN_SECONDS );
		f2000cs_maybe_cleanup_stale_temp_files();
		$this->assertFileExists( $second, 'The sweep runs at most once an hour' );

		wp_delete_file( $second );
	}

	/**
	 * Uninstall clears the sweep cron event.
	 *
	 * @return void
	 */
	public function test_uninstall_clears_the_sweep_cron() {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'f2000cs_temp_cleanup_cron' );
		$this->assertNotFalse( wp_next_scheduled( 'f2000cs_temp_cleanup_cron' ) );

		f2000cs_uninstall_cleanup();

		$this->assertFalse( wp_next_scheduled( 'f2000cs_temp_cleanup_cron' ) );
	}

	/**
	 * Uninstall removes this plugin's temp files as well (any age).
	 *
	 * @return void
	 */
	public function test_uninstall_removes_plugin_temp_files() {
		$path = $this->temp_file( 'f2000cs_import_uninstall-abc.tmp', 60 );

		f2000cs_uninstall_cleanup();

		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * No production file calls the global wp_cache_flush() anymore.
	 *
	 * @return void
	 */
	public function test_production_code_has_no_global_cache_flush_calls() {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge(
			glob( $root . '/includes/*.php' ),
			glob( $root . '/includes/parsers/*.php' ),
			glob( $root . '/includes/marketplace/*.php' ),
			glob( $root . '/admin/*.php' ),
			array( $root . '/factorial2000-catalog-sync.php' )
		);

		foreach ( $files as $file ) {
			$this->assertDoesNotMatchRegularExpression(
				'/wp_cache_flush\s*\(\s*\)/',
				(string) file_get_contents( $file ),
				basename( $file ) . ' must flush only the transients group'
			);
		}
	}
}
