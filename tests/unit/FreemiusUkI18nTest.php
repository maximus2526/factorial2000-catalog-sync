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
}
