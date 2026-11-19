<?php
/**
 * Marketplace product fields: registry, meta boxes, saving and feed import.
 *
 * @package Factorial2000_Catalog_Sync
 */

use F2000CS\Marketplace_Fields;

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal post object for meta box callbacks.
	 */
	class WP_Post {
		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public $ID = 0;

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Post status.
		 *
		 * @var string
		 */
		public $post_status = 'publish';
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	/**
	 * Record a registered meta box.
	 *
	 * @param string   $id       Meta box id.
	 * @param string   $title    Title.
	 * @param callable $callback Render callback.
	 * @param string   $screen   Screen.
	 * @param string   $context  Context.
	 * @param string   $priority Priority.
	 * @param array    $args     Callback args.
	 * @return void
	 */
	function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $args = array() ) {
		$GLOBALS['f2000cs_test_meta_boxes'][ $id ] = array(
			'title'   => $title,
			'context' => $context,
			'args'    => $args,
		);
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	/**
	 * No-op slashing for the tests.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_slash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function wp_is_post_autosave( $post_id ) {
		return false;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function wp_is_post_revision( $post_id ) {
		return false;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

final class F2000CS_Unit_MarketplaceFieldsTest extends F2000CS_Unit_TestCase {

	/**
	 * The Prom title field is registered next to the keywords, translatable.
	 *
	 * @return void
	 */
	public function test_prom_title_field_registered(): void {
		$fields = Marketplace_Fields::fields();

		$this->assertArrayHasKey( 'prom_title', $fields );
		$this->assertSame( '_f2000tr_prom_title', $fields['prom_title']['meta_key'] );
		$this->assertSame( 'prom', $fields['prom_title']['marketplace'] );
		$this->assertTrue( $fields['prom_title']['translatable'] );
	}

	/**
	 * Default Prom.ua field keeps the legacy meta key.
	 *
	 * @return void
	 */
	public function test_prom_keywords_field_keeps_legacy_meta_key(): void {
		$fields = Marketplace_Fields::fields();

		$this->assertArrayHasKey( 'prom_keywords', $fields );
		$this->assertSame( '_f2000tr_prom_keywords', $fields['prom_keywords']['meta_key'] );
		$this->assertSame( 'prom', $fields['prom_keywords']['marketplace'] );
		$this->assertTrue( $fields['prom_keywords']['translatable'] );
	}

	/**
	 * Registered fields join the registry and can be removed again.
	 *
	 * @return void
	 */
	public function test_register_and_unregister_field(): void {
		Marketplace_Fields::register(
			'rozetka_keywords',
			array(
				'marketplace'       => 'rozetka',
				'marketplace_label' => 'Rozetka',
				'label'             => 'Ключові слова',
				'meta_key'          => '_f2000cs_rozetka_keywords',
			)
		);

		$fields = Marketplace_Fields::fields();
		$this->assertArrayHasKey( 'rozetka_keywords', $fields );
		$this->assertSame( 'rozetka', $fields['rozetka_keywords']['marketplace'] );

		Marketplace_Fields::unregister( 'rozetka_keywords' );
		$this->assertArrayNotHasKey( 'rozetka_keywords', Marketplace_Fields::fields() );
	}

	/**
	 * A field without a meta key is dropped.
	 *
	 * @return void
	 */
	public function test_field_without_meta_key_is_dropped(): void {
		Marketplace_Fields::register( 'broken', array( 'label' => 'Broken' ) );

		$this->assertArrayNotHasKey( 'broken', Marketplace_Fields::fields() );
	}

	/**
	 * Fields are grouped by marketplace.
	 *
	 * @return void
	 */
	public function test_fields_grouped_by_marketplace(): void {
		Marketplace_Fields::register(
			'rozetka_keywords',
			array(
				'marketplace' => 'rozetka',
				'meta_key'    => '_f2000cs_rozetka_keywords',
			)
		);

		$this->assertArrayHasKey( 'prom_keywords', Marketplace_Fields::fields_for_marketplace( 'prom', 'product' ) );
		$this->assertArrayNotHasKey( 'rozetka_keywords', Marketplace_Fields::fields_for_marketplace( 'prom', 'product' ) );
		$this->assertArrayHasKey( 'rozetka_keywords', Marketplace_Fields::fields_for_marketplace( 'rozetka', 'product' ) );

		Marketplace_Fields::unregister( 'rozetka_keywords' );
	}

	/**
	 * Each marketplace gets its own product meta box.
	 *
	 * @return void
	 */
	public function test_each_marketplace_gets_its_own_meta_box(): void {
		Marketplace_Fields::register(
			'rozetka_keywords',
			array(
				'marketplace'       => 'rozetka',
				'marketplace_label' => 'Rozetka',
				'meta_key'          => '_f2000cs_rozetka_keywords',
			)
		);

		Marketplace_Fields::add_meta_boxes( 'product' );

		$boxes = isset( $GLOBALS['f2000cs_test_meta_boxes'] ) ? $GLOBALS['f2000cs_test_meta_boxes'] : array();
		$this->assertArrayHasKey( 'f2000cs-marketplace-prom', $boxes );
		$this->assertArrayHasKey( 'f2000cs-marketplace-rozetka', $boxes );
		$this->assertSame( 'Prom.ua', $boxes['f2000cs-marketplace-prom']['title'] );
		$this->assertSame( 'Rozetka', $boxes['f2000cs-marketplace-rozetka']['title'] );

		Marketplace_Fields::unregister( 'rozetka_keywords' );
	}

	/**
	 * The f2000cs_offer_keywords action stores feed keywords.
	 *
	 * @return void
	 */
	public function test_offer_keywords_action_stores_keywords(): void {
		Marketplace_Fields::init();

		do_action( 'f2000cs_offer_keywords', 34, array( 'uk' => 'штани тактичні', 'ru' => 'штаны тактические' ) );

		$this->assertSame( 'штани тактичні', get_post_meta( 34, '_f2000tr_prom_keywords', true ) );
	}

	/**
	 * Saving the meta box stores the sanitized value.
	 *
	 * @return void
	 */
	public function test_save_stores_field_value(): void {
		$post            = new WP_Post();
		$post->ID        = 30;
		$post->post_type = 'product';

		$_POST = array(
			Marketplace_Fields::NONCE_NAME => 'nonce',
			Marketplace_Fields::FORM_KEY   => array( 'prom_keywords' => 'case, glass' ),
		);

		Marketplace_Fields::save( 30, $post );

		$this->assertSame( 'case, glass', get_post_meta( 30, '_f2000tr_prom_keywords', true ) );
	}

	/**
	 * An empty submitted value removes the stored meta.
	 *
	 * @return void
	 */
	public function test_save_removes_empty_value(): void {
		$post            = new WP_Post();
		$post->ID        = 31;
		$post->post_type = 'product';
		F2000CS_Test_State::$post_meta['31:_f2000tr_prom_keywords'] = 'old, words';

		$_POST = array(
			Marketplace_Fields::NONCE_NAME => 'nonce',
			Marketplace_Fields::FORM_KEY   => array( 'prom_keywords' => '' ),
		);

		Marketplace_Fields::save( 31, $post );

		$this->assertSame( '', get_post_meta( 31, '_f2000tr_prom_keywords', true ) );
	}
}
