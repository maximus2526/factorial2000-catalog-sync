<?php
/**
 * Offline tests against compact samples of real supplier feeds.
 *
 * Full feeds are 10–33 MB and are NOT downloaded in CI. Samples are built by
 * tests/bin/build-feed-samples.php and stored under tests/fixtures/feeds/.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Real-feed fixture tests (offline samples only).
 */
final class F2000CS_Unit_FeedFixturesTest extends F2000CS_Unit_TestCase {

	/**
	 * Fixture directory.
	 *
	 * @var string
	 */
	private static $dir;

	/**
	 * Manifest data.
	 *
	 * @var array<string, array>
	 */
	private static $manifest = array();

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::$dir = dirname( __DIR__ ) . '/fixtures/feeds';
		$manifest  = self::$dir . '/manifest.json';
		if ( is_readable( $manifest ) ) {
			$decoded         = json_decode( (string) file_get_contents( $manifest ), true );
			self::$manifest = is_array( $decoded ) ? $decoded : array();
		}

		require_once dirname( __DIR__, 2 ) . '/admin/page-import.php';
	}

	/**
	 * @return array<int, string>
	 */
	private function allSlugs(): array {
		return array( 'tactic-shop', 'armoline', 'vik-tailor', 'bm', 'powerplay' );
	}

	/**
	 * @return array<int, string>
	 */
	private function multiGroupSlugs(): array {
		return array( 'armoline', 'vik-tailor', 'bm' );
	}

	/**
	 * @param string $slug Feed slug.
	 * @return string Absolute sample path.
	 */
	private function samplePath( string $slug ): string {
		$file = self::$manifest[ $slug ]['sample_file'] ?? ( $slug . '-sample.xml' );
		return self::$dir . '/' . $file;
	}

	/**
	 * @param string $slug Feed slug.
	 */
	private function requireSample( string $slug ): void {
		$path = $this->samplePath( $slug );
		if ( ! is_readable( $path ) ) {
			$this->markTestSkipped( "Missing feed sample for {$slug}. Run: php tests/bin/build-feed-samples.php" );
		}
	}

	public function test_samples_are_valid_yml_and_small(): void {
		foreach ( $this->allSlugs() as $slug ) {
			$this->requireSample( $slug );
			$path = $this->samplePath( $slug );

			$size = filesize( $path );
			$this->assertNotFalse( $size, $slug );
			$this->assertLessThan( 250000, $size, "{$slug} sample must stay small" );

			$reader = new XMLReader();
			$this->assertTrue( @$reader->open( $path ), $slug ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$has_shop   = false;
			$has_offers = false;
			while ( $reader->read() ) {
				if ( $reader->nodeType !== XMLReader::ELEMENT ) {
					continue;
				}
				if ( 'shop' === $reader->localName ) {
					$has_shop = true;
				}
				if ( 'offers' === $reader->localName ) {
					$has_offers = true;
					break;
				}
			}
			$reader->close();

			$this->assertTrue( $has_shop, $slug );
			$this->assertTrue( $has_offers, $slug );
		}
	}

	public function test_tactic_shop_lone_group_ids_import_as_simple(): void {
		$this->requireSample( 'tactic-shop' );
		$this->assertTrue( ! empty( self::$manifest['tactic-shop']['all_lone_group_ids'] ) );

		set_transient( 'f2000cs_import_variations_temp', '0', HOUR_IN_SECONDS );
		$parser = new F2000CS_ImportSpyParser( $this->samplePath( 'tactic-shop' ), false, 'TS_' );
		$result = $parser->import_products( 0, 100 );

		$this->assertSame( array(), $parser->variable_groups );
		$this->assertGreaterThanOrEqual( 1, count( $parser->simple_skus ) );
		$this->assertSame( count( $parser->simple_skus ), $result['total'] );
		$this->assertTrue( $result['finished'] );
	}

	public function test_tactic_shop_variable_analyze_finds_no_multi_groups(): void {
		$this->requireSample( 'tactic-shop' );
		$groups = f2000cs_analyze_variable_groups( $this->samplePath( 'tactic-shop' ) );
		$this->assertSame( array(), $groups );
	}

	public function test_powerplay_is_simple_only_feed(): void {
		$this->requireSample( 'powerplay' );
		$this->assertTrue( ! empty( self::$manifest['powerplay']['simple_only'] ) );

		$groups = f2000cs_analyze_variable_groups( $this->samplePath( 'powerplay' ) );
		$this->assertSame( array(), $groups, 'No group_id clusters expected' );

		set_transient( 'f2000cs_import_variations_temp', '0', HOUR_IN_SECONDS );
		$simple = new F2000CS_ImportSpyParser( $this->samplePath( 'powerplay' ), false, 'PP_' );
		$simple_result = $simple->import_products( 0, 100 );
		$this->assertSame( array(), $simple->variable_groups );
		$this->assertGreaterThanOrEqual( 1, count( $simple->simple_skus ) );
		$this->assertTrue( $simple_result['finished'] );

		// Variable mode must skip plain offers (nothing to import).
		set_transient( 'f2000cs_import_variations_temp', '1', HOUR_IN_SECONDS );
		$variable = new F2000CS_ImportSpyParser( $this->samplePath( 'powerplay' ), false, 'PP_' );
		$variable_result = $variable->import_products( 0, 100 );
		$this->assertSame( array(), $variable->simple_skus );
		$this->assertSame( array(), $variable->variable_groups );
		$this->assertSame( 0, $variable_result['total'] );
		$this->assertTrue( $variable_result['finished'] );
	}

	public function test_variable_feeds_analyze_finds_groups(): void {
		foreach ( $this->multiGroupSlugs() as $slug ) {
			$this->requireSample( $slug );
			$groups = f2000cs_analyze_variable_groups( $this->samplePath( $slug ) );

			$this->assertNotEmpty( $groups, $slug );
			foreach ( $groups as $group ) {
				$this->assertGreaterThanOrEqual( 2, $group['variations_count'], $slug );
				$this->assertNotEmpty( $group['attributes'], $slug );
			}
		}
	}

	public function test_variable_feeds_import_variable_mode(): void {
		foreach ( $this->multiGroupSlugs() as $slug ) {
			$this->requireSample( $slug );

			set_transient( 'f2000cs_import_variations_temp', '1', HOUR_IN_SECONDS );
			$parser = new F2000CS_ImportSpyParser( $this->samplePath( $slug ), false, 'VF_' );
			$result = $parser->import_products( 0, 100 );

			$this->assertSame( array(), $parser->simple_skus, $slug );
			$this->assertGreaterThanOrEqual( 1, count( $parser->variable_groups ), $slug );
			$this->assertSame( count( $parser->variable_groups ), $result['imported'], $slug );
			$this->assertTrue( $result['finished'], $slug );
		}
	}

	public function test_live_feed_urls_reachable_when_enabled(): void {
		if ( '1' !== getenv( 'F2000CS_LIVE_FEEDS' ) ) {
			$this->markTestSkipped( 'Set F2000CS_LIVE_FEEDS=1 to hit live supplier URLs' );
		}

		$urls = require self::$dir . '/urls.php';
		$this->assertNotEmpty( $urls );

		$checked = 0;
		foreach ( $urls as $slug => $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				continue; // Private/tokenized feeds stay out of the public repo.
			}
			++$checked;

			// Prefer a short GET probe: some hosts ignore/deny HEAD or omit Content-Length.
			$ctx = stream_context_create(
				array(
					'http' => array(
						'method'     => 'GET',
						'timeout'    => 45,
						'header'     => "Range: bytes=0-2047\r\n",
						'user_agent' => 'F2000CS-FeedFixtures/1.0',
					),
					'ssl'  => array(
						'verify_peer'      => false,
						'verify_peer_name' => false,
					),
				)
			);

			$handle = @fopen( $url, 'r', false, $ctx ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->assertNotFalse( $handle, "Cannot open live feed: {$slug}" );

			$chunk = (string) stream_get_contents( $handle, 2048 );
			fclose( $handle );

			$this->assertNotSame( '', $chunk, $slug );
			$this->assertMatchesRegularExpression( '/<(\?xml|yml_catalog|shop|offers)/i', $chunk, $slug );
		}

		$this->assertGreaterThan( 0, $checked, 'No public live feed URLs configured' );
	}
}
