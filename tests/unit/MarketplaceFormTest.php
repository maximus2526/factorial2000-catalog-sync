<?php
/**
 * Tests of the marketplace settings form helpers and the Prom preset fields.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Form;
use F2000CS\Marketplace_Registry;
use F2000CS\Prom_Feed;

/**
 * Form helper tests.
 */
class F2000CS_Unit_MarketplaceFormTest extends F2000CS_Unit_TestCase {

	/**
	 * Categories fixture.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function categories(): array {
		return array(
			array( 'id' => 1, 'path' => 'Одяг', 'depth' => 0, 'count' => 3 ),
			array( 'id' => 2, 'path' => 'Одяг > Куртки', 'depth' => 1, 'count' => 2 ),
			array( 'id' => 3, 'path' => 'Взуття', 'depth' => 0, 'count' => 1 ),
		);
	}

	/**
	 * Preset vs custom detection.
	 *
	 * @return void
	 */
	public function test_selected_preset_and_value() {
		$options = array( 'a' => 'A', '' => 'None' );

		$this->assertSame( 'a', Marketplace_Form::selected_preset( 'a', $options ) );
		$this->assertSame( '', Marketplace_Form::selected_preset( '', $options ) );
		$this->assertSame( Marketplace_Form::CUSTOM, Marketplace_Form::selected_preset( 'zzz', $options ) );
		$this->assertSame( 'x', Marketplace_Form::preset_value( Marketplace_Form::CUSTOM, 'x' ) );
		$this->assertSame( 'a', Marketplace_Form::preset_value( 'a', 'x' ) );
	}

	/**
	 * Repeater rows round trip, dropping empty and half-filled rows.
	 *
	 * @return void
	 */
	public function test_rows_round_trip() {
		$text = Marketplace_Form::rows_to_text(
			array(
				array( 'l' => ' Nike ', 'r' => 'NIKE' ),
				array( 'l' => '', 'r' => '' ),
				array( 'l' => 'Lonely', 'r' => '' ),
				array( 'l' => 'A=B', 'r' => "C\nD" ),
			)
		);

		$this->assertSame( "Nike=NIKE\nA B=C D", $text );
		$this->assertSame( array( array( 'Nike', 'NIKE' ), array( 'A B', 'C D' ) ), Marketplace_Form::text_to_rows( $text ) );
	}

	/**
	 * Path normalisation matches the feed's rule matching.
	 *
	 * @return void
	 */
	public function test_normalize_path() {
		$this->assertSame( 'одяг>куртки', Marketplace_Form::normalize_path( '  Одяг  >  Куртки ' ) );
	}

	/**
	 * Deeper rules are written first so they win over their parents.
	 *
	 * @return void
	 */
	public function test_build_rules_orders_specific_first_and_keeps_extra() {
		$rules = Marketplace_Form::build_rules( array( 1 => '100', 2 => 'id 200', 3 => '' ), $this->categories(), "кросівки=300\n" );

		$this->assertSame( "одяг>куртки=200\nодяг=100\nкросівки=300", $rules );
	}

	/**
	 * Stored rules are split back into table cells and leftovers.
	 *
	 * @return void
	 */
	public function test_split_rules() {
		$split = Marketplace_Form::split_rules( "одяг>куртки=200\nодяг=100\nкросівки=300", $this->categories() );

		$this->assertSame( array( 2 => '200', 1 => '100' ), $split['by_id'] );
		$this->assertSame( 'кросівки=300', $split['extra'] );
	}

	/**
	 * Prom defaults are all presets (or simple switches) with live tokens.
	 *
	 * @return void
	 */
	public function test_prom_defaults_follow_the_site_and_sanitize_presets() {
		$feed = new Prom_Feed();
		$defs = $feed->defaults();

		$this->assertSame( '{site_name}', $defs['shop_name'] );
		$this->assertSame( '{wc_currency}', $defs['currency'] );

		$saved = $feed->sanitize(
			array(
				'currency'       => 'EUR',
				'max_pictures'   => '5',
				'default_vendor' => Marketplace_Form::CUSTOM,
				'mpn_meta_key'   => 'bogus',
				'__custom'       => array( 'default_vendor' => ' Acme ' ),
			)
		);

		$this->assertSame( 'EUR', $saved['currency'] );
		$this->assertSame( '5', $saved['max_pictures'] );
		$this->assertSame( 'Acme', $saved['default_vendor'] );
		$this->assertSame( 'f2000cs-updater-vendor', $saved['mpn_meta_key'], 'Unknown preset falls back to the default' );

		$saved = $feed->sanitize( array( 'default_vendor' => Marketplace_Form::CUSTOM, '__custom' => array( 'default_vendor' => '' ) ) );
		$this->assertSame( '', $saved['default_vendor'], 'Empty custom text falls back to the default' );
	}

	/**
	 * Tokens resolve to live site values in the document head.
	 *
	 * @return void
	 */
	public function test_resolve_tokens() {
		$this->assertSame( 'plain', Marketplace_Form::resolve( 'plain' ) );
		$this->assertNotSame( '{wc_currency}', Marketplace_Form::resolve( '{wc_currency}' ) );
	}
}
