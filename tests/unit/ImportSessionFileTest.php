<?php
/**
 * URL-import session file tests (admin/page-import.php).
 *
 * The downloaded XML is cached in a deterministic per-session temp file instead
 * of relying on a transient: the plugin's own cache/transients cleanup can drop
 * the transient mid-import, which used to re-download the feed into a new temp
 * file on every request (hundreds of leftovers).
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Import session file tests.
 */
final class F2000CS_Unit_ImportSessionFileTest extends F2000CS_Unit_TestCase {

	/**
	 * Load the import admin module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/admin/page-import.php';
	}

	/**
	 * Deterministic path of a session's cached XML file.
	 *
	 * @param string $session Session key.
	 * @return string
	 */
	private function session_path( string $session ): string {
		return get_temp_dir() . 'f2000cs_import_' . sanitize_key( $session ) . '.tmp';
	}

	/**
	 * A URL download lands in the deterministic per-session file and keeps the transient.
	 *
	 * @return void
	 */
	public function test_url_download_uses_the_deterministic_session_path() {
		$_POST = array(
			'import_source'  => 'url',
			'import_xml_url' => 'http://feed.test/products.xml',
		);
		F2000CS_Test_State::$http_get_responses['http://feed.test/products.xml'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<yml_catalog></yml_catalog>',
		);

		$result = f2000cs_resolve_import_xml_source();

		$this->assertIsArray( $result, 'Resolve must not fail' );
		$this->assertNotSame( '', $result['session'] );
		$this->assertSame( $this->session_path( $result['session'] ), $result['path'] );
		$this->assertFileExists( $result['path'] );
		$this->assertSame( '<yml_catalog></yml_catalog>', file_get_contents( $result['path'] ) );
		$this->assertSame( $result['path'], F2000CS_Test_State::$transients[ 'f2000cs_import_xml_' . $result['session'] ]['value'] ?? null );

		wp_delete_file( $result['path'] );
	}

	/**
	 * The file alone is enough to reuse a session: the transient may be gone.
	 *
	 * The request carries no URL on purpose: had the code tried to download
	 * again, resolve would have returned an "invalid_url" error.
	 *
	 * @return void
	 */
	public function test_existing_session_file_is_reused_without_the_transient() {
		$session = 'abc123';
		$path    = $this->session_path( $session );
		file_put_contents( $path, '<cached/>' );

		$_POST = array(
			'import_source'  => 'url',
			'import_session' => $session,
		);

		$result = f2000cs_resolve_import_xml_source();

		$this->assertIsArray( $result );
		$this->assertSame( $path, $result['path'] );
		$this->assertSame( '<cached/>', file_get_contents( $path ), 'The cached download is kept' );

		wp_delete_file( $path );
	}

	/**
	 * Reusing an active session refreshes the file age, keeping it out of the stale sweep.
	 *
	 * @return void
	 */
	public function test_reuse_refreshes_the_file_mtime() {
		$session = 'touch1';
		$path    = $this->session_path( $session );
		file_put_contents( $path, '<cached/>' );
		touch( $path, time() - 3 * HOUR_IN_SECONDS );

		$_POST = array(
			'import_source'  => 'url',
			'import_session' => $session,
		);

		f2000cs_resolve_import_xml_source();

		clearstatcache();
		$this->assertGreaterThan( time() - 60, (int) filemtime( $path ) );

		wp_delete_file( $path );
	}

	/**
	 * Session cleanup deletes the deterministic file even without the transient.
	 *
	 * @return void
	 */
	public function test_session_cleanup_removes_the_file_without_the_transient() {
		$session = 'cleanme';
		$path    = $this->session_path( $session );
		file_put_contents( $path, '<cached/>' );

		f2000cs_cleanup_import_xml_session( $session );

		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * A resumable import is still found when the XML transient was dropped.
	 *
	 * @return void
	 */
	public function test_pending_import_is_found_without_the_transient() {
		$session = 'resume1';
		file_put_contents( $this->session_path( $session ), '<cached/>' );
		set_transient( 'f2000cs_import_resume_' . $session, array( 'offset' => 10 ), HOUR_IN_SECONDS );

		global $wpdb;
		$wpdb->col_queue = array( array( '_transient_f2000cs_import_resume_' . $session ) );

		$pending = f2000cs_get_pending_import();

		$this->assertSame( $session, $pending['session'] ?? null );
		$this->assertSame( 10, $pending['offset'] ?? null );

		wp_delete_file( $this->session_path( $session ) );
	}
}
