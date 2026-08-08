<?php
/**
 * Base test case for the Factorial2000 Catalog Sync suite.
 *
 * Every test class should extend this one: it resets the stub state before
 * each test and provides a simple capture() helper for render functions.
 *
 * @package Factorial2000_Catalog_Sync
 */

use PHPUnit\Framework\TestCase;

/**
 * Base test case.
 */
class F2000CS_Unit_TestCase extends TestCase {

	/**
	 * Reset stubs before every test (options, transients, cron, HTTP, hooks).
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		F2000CS_Test_State::reset();

		// Pro unlock is a filter; drop it without wiping bootstrap-registered hooks.
		unset( F2000CS_Test_State::$hooks['f2000cs_is_pro'] );

		// Clean any residual superglobal pollution between tests.
		$_GET  = array();
		$_POST = array();
		unset( $_FILES['xml_file'] );
	}

	/**
	 * Unlock Pro features for the current test (Freemius is stubbed out).
	 *
	 * @return void
	 */
	protected function enable_pro(): void {
		unset( F2000CS_Test_State::$hooks['f2000cs_is_pro'] );
		add_filter( 'f2000cs_is_pro', '__return_true' );
	}

	/**
	 * Capture the echoed output of a callback, then flush any nested
	 * output buffers the callback may have started.
	 *
	 * @param callable $callback Function to call.
	 * @return string Captured output.
	 */
	protected function capture( callable $callback ): string {
		$level = ob_get_level();
		ob_start();
		$callback();
		$output = (string) ob_get_clean();

		// Close any buffers that the callback left open.
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}

		return $output;
	}
}
