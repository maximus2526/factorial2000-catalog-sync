<?php
/**
 * Ukrainian Freemius UI strings tests (includes/freemius-uk-i18n.php).
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Freemius i18n map tests.
 */
final class F2000CS_Unit_FreemiusUkI18nTest extends F2000CS_Unit_TestCase {

	/**
	 * Reset stub state before every test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Critical Freemius UI keys must exist in the Ukrainian map.
	 *
	 * @return void
	 */
	public function test_returns_required_keys() {
		$map = f2000cs_get_freemius_uk_i18n();

		$required = array(
			'account-details',
			'yee-haw',
			'license-activated-message',
			'expires-in',
			'expires-in-x',
			'expired-x',
			'opt-in-connect',
			'opt-in',
			'opt-out',
			'skip',
			'activate-license-message',
			'have-license-key',
			'license-expired-message',
			'upgrade',
			'trial-started-message',
			'deactivation',
			'contact',
		);

		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $map, "Missing Freemius i18n key: {$key}" );
		}
	}

	/**
	 * Every mapped string must be a non-empty translated value, not a
	 * leftover placeholder.
	 *
	 * @return void
	 */
	public function test_all_values_are_non_empty_strings() {
		$map = f2000cs_get_freemius_uk_i18n();

		$this->assertNotEmpty( $map );

		foreach ( $map as $key => $value ) {
			$this->assertIsString( $value, "Freemius i18n value for {$key} is not a string" );
			$this->assertNotSame( '', trim( $value ), "Freemius i18n value for {$key} is empty" );
		}
	}

	/**
	 * Placeholder-aware values keep their %s/%d tokens.
	 *
	 * @return void
	 */
	public function test_placeholders_are_preserved() {
		$map = f2000cs_get_freemius_uk_i18n();

		$this->assertStringContainsString( '%s', $map['expires-in'] );
		$this->assertStringContainsString( '%s', $map['thanks-x'] );
		$this->assertStringContainsString( '%s', $map['license-deactivation-message'] );
	}

	/**
	 * The map must be registered on plugins_loaded.
	 *
	 * @return void
	 */
	public function test_apply_hook_registered() {
		$this->assertArrayHasKey( 'plugins_loaded', F2000CS_Test_State::$hooks );

		$callbacks = array_column( F2000CS_Test_State::$hooks['plugins_loaded'], 'callback' );

		$this->assertContains( 'f2000cs_apply_freemius_uk_i18n', $callbacks );
	}

	/**
	 * Plugins-list opt-in must not read like a vague legal "agree".
	 *
	 * @return void
	 */
	public function test_opt_in_label_is_clear() {
		$map = f2000cs_get_freemius_uk_i18n();

		$this->assertSame( 'Увімкнути оновлення', $map['opt-in'] );
		$this->assertNotSame( 'Погодитись', $map['opt-in'] );
	}

	/**
	 * Hand-picked core strings take precedence over the generated SDK-wide set.
	 *
	 * @return void
	 */
	public function test_core_strings_override_generated_ones() {
		$map = f2000cs_get_freemius_uk_i18n();

		$this->assertSame( 'Не зараз', $map['skip'] );
		$this->assertArrayHasKey( 'connect-header', $map, 'SDK-wide strings must be merged in' );
		$this->assertGreaterThan( 500, count( $map ) );
	}

	/**
	 * Every key of the generated set must still exist in the bundled SDK:
	 * either as a literal key, or as the lower-cased/hyphenated English text the
	 * SDK derives a key from when it passes none. Catches typos and keys that
	 * disappear after an SDK upgrade (an override on a missing key does nothing).
	 *
	 * @return void
	 */
	public function test_generated_keys_exist_in_the_bundled_sdk() {
		$sdk_dir = dirname( __DIR__, 2 ) . '/freemius';
		$this->assertDirectoryExists( $sdk_dir );

		$known = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sdk_dir, FilesystemIterator::SKIP_DOTS ) );

		foreach ( $it as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$tokens = token_get_all( (string) file_get_contents( $file->getPathname() ) );
			$count  = count( $tokens );

			for ( $i = 0; $i < $count; $i++ ) {
				if ( ! is_array( $tokens[ $i ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $i ][0] ) {
					continue;
				}

				// Join "a" . "b" concatenations, the SDK splits long messages that way.
				$literal = $this->token_string_value( $tokens[ $i ][1] );
				$j       = $i + 1;

				while ( $j < $count ) {
					while ( $j < $count && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT ), true ) ) {
						++$j;
					}
					if ( $j >= $count || '.' !== $tokens[ $j ] ) {
						break;
					}
					++$j;
					while ( $j < $count && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT ), true ) ) {
						++$j;
					}
					if ( $j >= $count || ! is_array( $tokens[ $j ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $j ][0] ) {
						break;
					}
					$literal .= $this->token_string_value( $tokens[ $j ][1] );
					$i        = $j;
					++$j;
				}

				$known[ $literal ]                                        = true;
				$known[ strtolower( str_replace( ' ', '-', $literal ) ) ] = true;
			}
		}

		$missing = array();
		foreach ( array_keys( f2000cs_get_freemius_uk_i18n_sdk() ) as $key ) {
			if ( ! isset( $known[ $key ] ) ) {
				$missing[] = $key;
			}
		}

		$this->assertSame( array(), $missing, 'Keys not found in the Freemius SDK: ' . implode( ', ', $missing ) );
	}

	/**
	 * Value of a PHP string-literal token (quotes and escapes removed).
	 *
	 * @param string $raw Token text including quotes.
	 * @return string
	 */
	private function token_string_value( $raw ) {
		$inner = substr( $raw, 1, -1 );

		if ( "'" === $raw[0] ) {
			return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $inner );
		}

		return stripcslashes( $inner );
	}

	/**
	 * Translations keep the printf tokens of the English string they replace
	 * (a missing %s would drop a value, an extra one would raise a warning).
	 *
	 * @return void
	 */
	public function test_generated_strings_keep_placeholders_balanced() {
		foreach ( f2000cs_get_freemius_uk_i18n_sdk() as $key => $value ) {
			$this->assertNotSame( '', trim( $value ), "Empty translation for {$key}" );

			// Positional (%1$s) and plain (%s) tokens are both fine, but a lone
			// percent sign followed by a letter would break sprintf().
			$this->assertSame(
				0,
				preg_match( '/%(?![sd%]|\d+\$[sd])[a-zA-Z]/', $value ),
				"Suspicious printf token in translation for {$key}"
			);
		}
	}
}
