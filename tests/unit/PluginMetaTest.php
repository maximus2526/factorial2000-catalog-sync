<?php
/**
 * Plugin meta / release consistency tests.
 *
 * Guards the version sync that the gulp release pipeline relies on:
 * header version, F2000CS_VERSION constant and readme.txt Stable tag must
 * all match, otherwise a broken release would ship.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Plugin meta tests.
 */
final class F2000CS_Unit_PluginMetaTest extends F2000CS_Unit_TestCase {

	/**
	 * Plugin root directory.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Main plugin file contents.
	 *
	 * @var string
	 */
	private $main_file;

	/**
	 * readme.txt contents.
	 *
	 * @var string
	 */
	private $readme;

	/**
	 * Set up shared fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->root      = dirname( __DIR__, 2 );
		$this->main_file = (string) file_get_contents( $this->root . '/factorial2000-catalog-sync.php' );
		$this->readme    = (string) file_get_contents( $this->root . '/readme.txt' );
	}

	/**
	 * Header version, define() version and Stable tag must be identical.
	 *
	 * @return void
	 */
	public function test_version_is_consistent_across_files() {
		preg_match( '/\* Version:\s*([0-9.]+)/', $this->main_file, $header_match );
		preg_match( "/define\( 'F2000CS_VERSION', '([0-9.]+)' \)/", $this->main_file, $define_match );
		preg_match( '/Stable tag:\s*([0-9.]+)/', $this->readme, $stable_match );

		$this->assertSame( 1, preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/', $header_match[1] ?? '' ), 'Header version must be semver' );
		$this->assertSame( $header_match[1], $define_match[1], 'F2000CS_VERSION define must match header version' );
		$this->assertSame( $header_match[1], $stable_match[1], 'readme.txt Stable tag must match header version' );
	}

	/**
	 * The version constant must actually be declared in the main file.
	 *
	 * @return void
	 */
	public function test_version_constant_declared() {
		$this->assertStringContainsString( "define( 'F2000CS_VERSION',", $this->main_file );
	}

	/**
	 * Image_Processor must be loaded from the main plugin file (settings + import).
	 *
	 * @return void
	 */
	public function test_main_file_requires_image_processor() {
		$this->assertStringContainsString(
			"require_once F2000CS_PATH . 'includes/class-image-processor.php';",
			$this->main_file
		);
	}

	/**
	 * Freemius must be initialized at plugin-load time (top level), not only
	 * on plugins_loaded: during activation the file is included after
	 * plugins_loaded has fired, so a deferred init would never register the
	 * SDK activation hook and the post-activation opt-in wizard would never
	 * appear.
	 *
	 * @return void
	 */
	public function test_freemius_init_runs_before_activation_hook_registration() {
		$top_level_init = "if ( function_exists( 'f2000cs_fs' ) ) {\n\t\tf2000cs_fs();\n\t}";

		$this->assertStringContainsString( $top_level_init, preg_replace( "/\r\n/", "\n", $this->main_file ) );
	}

	/**
	 * Paths from the @fs_premium_only header tag.
	 *
	 * @return array<string>
	 */
	private function premium_only_paths() {
		preg_match( '/@fs_premium_only\s+([^\r\n]+)/', $this->main_file, $match );

		return array_values( array_filter( array_map( 'trim', explode( ',', $match[1] ?? '' ) ) ) );
	}

	/**
	 * Every premium-only path must exist, and the build detector's marker file
	 * (class-xml-editor.php) must be among them — otherwise the free build
	 * would still look like the Pro build.
	 *
	 * @return void
	 */
	public function test_premium_only_paths_exist_and_include_marker() {
		$paths = $this->premium_only_paths();

		$this->assertNotEmpty( $paths, 'Main file header must declare @fs_premium_only paths' );
		$this->assertContains( '/includes/class-xml-editor.php', $paths );

		foreach ( $paths as $path ) {
			$this->assertFileExists( $this->root . $path, "Premium-only path missing: {$path}" );
		}
	}

	/**
	 * Premium files may only be loaded inside the f2000cs_has_premium_code()
	 * guard of the main file, otherwise the free build (which lacks them) fatals.
	 *
	 * @return void
	 */
	public function test_premium_files_are_required_only_behind_the_guard() {
		$main = preg_replace( "/\r\n/", "\n", $this->main_file );

		$guard_start = strpos( $main, 'if ( f2000cs_has_premium_code() ) {' );
		$this->assertNotFalse( $guard_start, 'Premium guard block missing in main file' );
		$guard_end = strpos( $main, "\n\t}\n", $guard_start );

		foreach ( $this->premium_only_paths() as $path ) {
			if ( '.php' !== substr( $path, -4 ) || 0 === strpos( $path, '/assets/' ) ) {
				continue;
			}

			$needle = "F2000CS_PATH . '" . ltrim( $path, '/' ) . "'";

			$offset = 0;
			while ( false !== ( $pos = strpos( $main, $needle, $offset ) ) ) {
				$this->assertTrue(
					$pos > $guard_start && $pos < $guard_end,
					"{$path} is required outside the premium guard"
				);
				$offset = $pos + 1;
			}

			$this->assertGreaterThan( 0, substr_count( $main, $needle ), "{$path} is never loaded" );
		}
	}

	/**
	 * Free-build placeholders must be declared only when the real implementation
	 * is missing, or the Pro build would fatal on redeclaration.
	 *
	 * @return void
	 */
	public function test_upsell_placeholders_are_function_exists_guarded() {
		$upsell = (string) file_get_contents( $this->root . '/admin/premium-upsell.php' );

		preg_match_all( '/^function\s+(\w+)/m', $upsell, $top_level );
		$this->assertEmpty( $top_level[1], 'Placeholders must not be declared unconditionally' );

		preg_match_all( "/if \( ! function_exists\( '(\w+)' \) \) \{\s+\/\*\*.*?function\s+(\w+)/s", $upsell, $pairs, PREG_SET_ORDER );
		$this->assertNotEmpty( $pairs );

		foreach ( $pairs as $pair ) {
			$this->assertSame( $pair[1], $pair[2], 'Guard must check the function it declares' );
		}
	}

	/**
	 * Required plugin header fields must exist.
	 *
	 * @return void
	 */
	public function test_plugin_header_fields_present() {
		foreach ( array( 'Plugin Name:', 'Description:', 'Version:', 'Author:', 'License:', 'Text Domain:', 'Requires Plugins:' ) as $field ) {
			$this->assertStringContainsString( $field, $this->main_file, "Missing header field: {$field}" );
		}
		$this->assertStringContainsString( 'Requires Plugins:  woocommerce', $this->main_file );
	}

	/**
	 * Text domain must match the plugin folder name (translation loading).
	 *
	 * @return void
	 */
	public function test_text_domain_matches_folder_name() {
		preg_match( '/Text Domain:\s*([a-z0-9-]+)/', $this->main_file, $match );

		$this->assertSame( 'factorial2000-catalog-sync', $match[1] ?? '' );
		$this->assertSame( 'factorial2000-catalog-sync', basename( $this->root ) );
	}

	/**
	 * Required PHP version must be at least 7.4 (typed properties in parsers).
	 *
	 * @return void
	 */
	public function test_php_version_requirement() {
		preg_match( '/Requires PHP:\s*([0-9.]+)/', $this->main_file, $match );

		$this->assertNotEmpty( $match[1] ?? '' );
		$this->assertGreaterThanOrEqual( 7.4, (float) $match[1] );
	}

	/**
	 * WordPress.org readme.txt must have the required sections.
	 *
	 * @return void
	 */
	public function test_readme_has_required_sections() {
		foreach ( array( '== Description ==', '== Installation ==', '== Changelog ==' ) as $section ) {
			$this->assertStringContainsString( $section, $this->readme, "Missing readme section: {$section}" );
		}
	}

	/**
	 * Every file in includes/ must be guarded by ABSPATH; files declaring a
	 * class/interface/trait must also live in the F2000CS namespace.
	 *
	 * The scan is dynamic so newly added files are checked automatically.
	 * Procedural files (functions.php, class-licensing.php,
	 * freemius-uk-i18n.php) intentionally stay in the global namespace.
	 *
	 * @return void
	 */
	public function test_include_files_namespaced_and_guarded() {
		$files = $this->find_php_files( $this->root . '/includes' );

		$this->assertNotEmpty( $files, 'No PHP files found under includes/' );

		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $file );
			$relative = str_replace( $this->root . '/', '', $file );

			$this->assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $contents, "No ABSPATH guard in {$relative}" );

			if ( preg_match( '/\b(?:abstract\s+)?(?:final\s+)?(?:class|interface|trait)\s+[A-Za-z_]\w*\s*(?:\{|extends|implements)/', $contents ) ) {
				$this->assertStringContainsString( 'namespace F2000CS;', $contents, "Class file without F2000CS namespace: {$relative}" );
			}
		}
	}

	/**
	 * Recursively collect PHP files under a directory.
	 *
	 * @param string $dir Directory path.
	 * @return array<string> Sorted list of PHP file paths.
	 */
	private function find_php_files( $dir ) {
		$files = array();

		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;

			if ( is_dir( $path ) ) {
				$files = array_merge( $files, $this->find_php_files( $path ) );
			} elseif ( 'php' === strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ) ) {
				$files[] = $path;
			}
		}

		sort( $files );

		return $files;
	}
}
