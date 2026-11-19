<?php
/**
 * Product fields that belong to a marketplace (Prom.ua, Rozetka, etc.).
 *
 * A field is one post meta key shown on the product edit screen and filled
 * from a supplier feed. Fields are grouped into one meta box per marketplace,
 * so Prom.ua and other marketplaces never share a box. The Prom.ua keywords
 * field is registered by default; other plugins add their own fields with the
 * `f2000cs_marketplace_fields` filter or Marketplace_Fields::register().
 *
 * Example:
 *
 *     add_filter( 'f2000cs_marketplace_fields', function ( $fields ) {
 *         $fields['rozetka_keywords'] = array(
 *             'marketplace'       => 'rozetka',
 *             'marketplace_label' => 'Rozetka',
 *             'label'             => 'Ключові слова',
 *             'meta_key'          => '_f2000cs_rozetka_keywords',
 *             'translatable'      => true,
 *         );
 *         return $fields;
 *     } );
 *
 * A multilingual plugin can translate any field marked `translatable` by
 * filtering `f2000cs_marketplace_field_value`,
 * `f2000cs_marketplace_field_save_value` and
 * `f2000cs_marketplace_field_import_value`.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Marketplace field registry, product meta boxes and import entry points.
 */
class Marketplace_Fields {

	/**
	 * Nonce action for the product meta boxes.
	 */
	const NONCE_ACTION = 'f2000cs_save_marketplace_fields';

	/**
	 * Nonce field name.
	 */
	const NONCE_NAME = 'f2000cs_marketplace_fields_nonce';

	/**
	 * POST array with field values.
	 */
	const FORM_KEY = 'f2000cs_marketplace_fields';

	/**
	 * Default Prom.ua keywords field id.
	 */
	const PROM_KEYWORDS = 'prom_keywords';

	/**
	 * Prom.ua product title field id (overrides the product name in the Prom feed).
	 */
	const PROM_TITLE = 'prom_title';

	/**
	 * Fields registered by other plugins.
	 *
	 * @var array<string, array>
	 */
	private static $registered = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'f2000cs_offer_keywords', array( __CLASS__, 'import_keywords' ), 10, 2 );
	}

	/**
	 * Register a marketplace field.
	 *
	 * @param string $id    Field id (sanitized).
	 * @param array  $field Field definition.
	 * @return void
	 */
	public static function register( $id, array $field ) {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return;
		}
		self::$registered[ $id ] = $field;
	}

	/**
	 * Remove a previously registered field.
	 *
	 * @param string $id Field id.
	 * @return void
	 */
	public static function unregister( $id ) {
		unset( self::$registered[ sanitize_key( $id ) ] );
	}

	/**
	 * All fields, normalized and filterable.
	 *
	 * @return array<string, array>
	 */
	public static function fields() {
		$fields = array(
			self::PROM_TITLE    => array(
				'marketplace'       => 'prom',
				'marketplace_label' => __( 'Prom.ua', 'factorial2000-catalog-sync' ),
				'label'             => __( 'Заголовок для Prom', 'factorial2000-catalog-sync' ),
				'meta_key'          => '_f2000tr_prom_title', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- field config, not a query.
				'post_types'        => array( 'product' ),
				'context'           => 'side',
				'priority'          => 'default',
				'rows'              => 2,
				'translatable'      => true,
				'description'       => __( 'Якщо заповнено, у вигрузці Prom замість назви товару йде цей заголовок (і для всіх його різновидів). З Factorial2000 Translator — окремо для української та російської.', 'factorial2000-catalog-sync' ),
			),
			self::PROM_KEYWORDS => array(
				'marketplace'       => 'prom',
				'marketplace_label' => __( 'Prom.ua', 'factorial2000-catalog-sync' ),
				'label'             => __( 'Ключові запити', 'factorial2000-catalog-sync' ),
				'meta_key'          => '_f2000tr_prom_keywords', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- field config, not a query.
				'post_types'        => array( 'product' ),
				'context'           => 'side',
				'priority'          => 'default',
				'rows'              => 3,
				'translatable'      => true,
				'description'       => __( 'Через кому. Заповнюється з XML або вручну; з Factorial2000 Translator — окремо для кожної мови.', 'factorial2000-catalog-sync' ),
			),
		);

		$fields = array_merge( $fields, self::$registered );

		// A Translator version without the adapter still owns this field and
		// its import. Keep the old behaviour until Translator is updated.
		if ( self::has_legacy_translator() ) {
			unset( $fields[ self::PROM_KEYWORDS ] );
		}

		/**
		 * Filter the marketplace product fields.
		 *
		 * @param array<string, array> $fields Field id => definition.
		 */
		$fields = apply_filters( 'f2000cs_marketplace_fields', $fields );
		if ( ! is_array( $fields ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $fields as $id => $field ) {
			$field = self::normalize( $id, $field );
			if ( null !== $field ) {
				$normalized[ $field['id'] ] = $field;
			}
		}

		return $normalized;
	}

	/**
	 * Whether an old Translator version still owns the Prom.ua field.
	 *
	 * @return bool
	 */
	private static function has_legacy_translator() {
		return class_exists( 'F2000TR\\Prom_Keywords' ) && ! class_exists( 'F2000TR\\Marketplace_Fields' );
	}

	/**
	 * One field by id.
	 *
	 * @param string $id Field id.
	 * @return array|null
	 */
	public static function field( $id ) {
		$fields = self::fields();
		$id     = sanitize_key( $id );

		return isset( $fields[ $id ] ) ? $fields[ $id ] : null;
	}

	/**
	 * Normalize a field definition.
	 *
	 * @param string $id    Field id.
	 * @param mixed  $field Field definition.
	 * @return array|null
	 */
	private static function normalize( $id, $field ) {
		if ( ! is_array( $field ) ) {
			return null;
		}

		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return null;
		}

		$meta_key = isset( $field['meta_key'] ) && is_string( $field['meta_key'] ) ? trim( $field['meta_key'] ) : '';
		if ( '' === $meta_key ) {
			return null;
		}

		$marketplace = isset( $field['marketplace'] ) ? sanitize_key( $field['marketplace'] ) : 'prom';
		if ( '' === $marketplace ) {
			$marketplace = 'prom';
		}

		$marketplace_label = isset( $field['marketplace_label'] ) ? trim( (string) $field['marketplace_label'] ) : '';
		if ( '' === $marketplace_label ) {
			$marketplace_label = ucfirst( $marketplace );
		}

		if ( isset( $field['post_types'] ) ) {
			$post_types = (array) $field['post_types'];
		} elseif ( isset( $field['post_type'] ) ) {
			$post_types = (array) $field['post_type'];
		} else {
			$post_types = array( 'product' );
		}
		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
		if ( array() === $post_types ) {
			$post_types = array( 'product' );
		}

		$context = isset( $field['context'] ) ? (string) $field['context'] : 'side';
		if ( ! in_array( $context, array( 'normal', 'side', 'advanced' ), true ) ) {
			$context = 'side';
		}

		$priority = isset( $field['priority'] ) ? (string) $field['priority'] : 'default';
		if ( ! in_array( $priority, array( 'high', 'core', 'default', 'low' ), true ) ) {
			$priority = 'default';
		}

		$sanitize_callback = isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ? $field['sanitize_callback'] : 'sanitize_text_field';

		return array(
			'id'                => $id,
			'marketplace'       => $marketplace,
			'marketplace_label' => $marketplace_label,
			'label'             => isset( $field['label'] ) ? (string) $field['label'] : $id,
			'meta_key'          => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- field config, not a query.
			'post_types'        => $post_types,
			'context'           => $context,
			'priority'          => $priority,
			'rows'              => isset( $field['rows'] ) ? max( 1, (int) $field['rows'] ) : 3,
			'translatable'      => ! empty( $field['translatable'] ),
			'description'       => isset( $field['description'] ) ? (string) $field['description'] : '',
			'placeholder'       => isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '',
			'sanitize_callback' => $sanitize_callback,
		);
	}

	/**
	 * Fields of one post type.
	 *
	 * @param string $post_type Post type.
	 * @return array<string, array>
	 */
	public static function fields_for_post_type( $post_type ) {
		$out = array();
		foreach ( self::fields() as $id => $field ) {
			if ( in_array( $post_type, $field['post_types'], true ) ) {
				$out[ $id ] = $field;
			}
		}

		return $out;
	}

	/**
	 * Fields of one marketplace for a post type.
	 *
	 * @param string $marketplace Marketplace slug.
	 * @param string $post_type   Post type.
	 * @return array<string, array>
	 */
	public static function fields_for_marketplace( $marketplace, $post_type ) {
		$out = array();
		foreach ( self::fields_for_post_type( $post_type ) as $id => $field ) {
			if ( $marketplace === $field['marketplace'] ) {
				$out[ $id ] = $field;
			}
		}

		return $out;
	}

	/**
	 * One meta box per marketplace.
	 *
	 * @param string $post_type Post type of the current screen.
	 * @return void
	 */
	public static function add_meta_boxes( $post_type ) {
		$groups = array();
		foreach ( self::fields_for_post_type( $post_type ) as $field ) {
			$slug = $field['marketplace'];
			if ( ! isset( $groups[ $slug ] ) ) {
				$groups[ $slug ] = array(
					'label'    => $field['marketplace_label'],
					'context'  => $field['context'],
					'priority' => $field['priority'],
				);
			}
		}

		foreach ( $groups as $slug => $group ) {
			add_meta_box(
				'f2000cs-marketplace-' . $slug,
				$group['label'],
				array( __CLASS__, 'render' ),
				$post_type,
				$group['context'],
				$group['priority'],
				array( 'marketplace' => $slug )
			);
		}
	}

	/**
	 * Render the fields of one marketplace.
	 *
	 * @param \WP_Post $post Product.
	 * @param array    $box  Meta box arguments.
	 * @return void
	 */
	public static function render( $post, $box ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$marketplace = isset( $box['args']['marketplace'] ) ? sanitize_key( $box['args']['marketplace'] ) : '';
		$fields      = self::fields_for_marketplace( $marketplace, $post->post_type );
		if ( array() === $fields ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		/**
		 * Let a multilingual plugin add its hidden language field.
		 *
		 * @param string   $marketplace Marketplace slug.
		 * @param \WP_Post $post        Product.
		 */
		do_action( 'f2000cs_marketplace_field_form', $marketplace, $post );

		foreach ( $fields as $field ) {
			$stored = get_post_meta( $post->ID, $field['meta_key'], true );
			$stored = is_string( $stored ) ? $stored : '';

			/**
			 * Filter the value shown on the product edit screen.
			 *
			 * @param string   $value Stored value.
			 * @param array    $field Field definition.
			 * @param \WP_Post $post  Product.
			 * @param string   $mode  Context (`edit`).
			 */
			$value = apply_filters( 'f2000cs_marketplace_field_value', $stored, $field, $post, 'edit' );
			$value = is_string( $value ) ? $value : $stored;

			/**
			 * Filter the field label.
			 *
			 * @param string $label Field label.
			 * @param array  $field Field definition.
			 * @param \WP_Post $post Product.
			 */
			$label = apply_filters( 'f2000cs_marketplace_field_label', $field['label'], $field, $post );
			$label = is_string( $label ) ? $label : $field['label'];

			/**
			 * Filter the field description.
			 *
			 * @param string $description Field description.
			 * @param array  $field       Field definition.
			 * @param \WP_Post $post      Product.
			 */
			$description = apply_filters( 'f2000cs_marketplace_field_description', $field['description'], $field, $post );
			$description = is_string( $description ) ? $description : $field['description'];

			$input_id = 'f2000cs-field-' . $field['id'];

			do_action( 'f2000cs_marketplace_field_before', $field, $post );
			?>
			<p>
				<label for="<?php echo esc_attr( $input_id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
				<textarea id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( self::FORM_KEY . '[' . $field['id'] . ']' ); ?>" class="widefat" rows="<?php echo esc_attr( (string) $field['rows'] ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			</p>
			<?php if ( '' !== $description ) : ?>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<?php
			do_action( 'f2000cs_marketplace_field_after', $field, $post );
		}
	}

	/**
	 * Save submitted marketplace fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post = null ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) && is_string( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above; each value goes through its field sanitize_callback below.
		$posted = isset( $_POST[ self::FORM_KEY ] ) && is_array( $_POST[ self::FORM_KEY ] ) ? wp_unslash( $_POST[ self::FORM_KEY ] ) : array();
		if ( ! is_array( $posted ) ) {
			return;
		}

		$post_type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		foreach ( self::fields_for_post_type( $post_type ) as $field ) {
			if ( ! array_key_exists( $field['id'], $posted ) ) {
				continue;
			}

			$raw = $posted[ $field['id'] ];
			if ( ! is_scalar( $raw ) ) {
				continue;
			}

			$input  = call_user_func( $field['sanitize_callback'], (string) $raw );
			$stored = get_post_meta( (int) $post_id, $field['meta_key'], true );
			$stored = is_string( $stored ) ? $stored : '';

			/**
			 * Filter the value before it is stored.
			 *
			 * @param string   $input  Submitted value.
			 * @param array    $field  Field definition.
			 * @param int      $post_id Post ID.
			 * @param string   $stored Stored value.
			 */
			$value = apply_filters( 'f2000cs_marketplace_field_save_value', $input, $field, (int) $post_id, $stored );
			$value = is_string( $value ) ? $value : $input;

			if ( '' === $value ) {
				if ( '' !== $stored ) {
					delete_post_meta( (int) $post_id, $field['meta_key'] );
				}
			} elseif ( $value !== $stored ) {
				update_post_meta( (int) $post_id, $field['meta_key'], wp_slash( $value ) );
			}

			/**
			 * Fires after one marketplace field was saved.
			 *
			 * @param int    $post_id Post ID.
			 * @param array  $field   Field definition.
			 * @param string $value   Stored value ('' when removed).
			 */
			do_action( 'f2000cs_marketplace_field_saved', (int) $post_id, $field, $value );
		}
	}

	/**
	 * Store keywords read from a Prom.ua offer.
	 *
	 * Kept on the `f2000cs_offer_keywords` action for compatibility with
	 * existing integrations. The actual storage now lives in this plugin.
	 *
	 * @param int   $post_id Product ID.
	 * @param array $by_lang Language code => comma-separated keywords.
	 * @return void
	 */
	public static function import_keywords( $post_id, $by_lang ) {
		self::import( self::PROM_KEYWORDS, $post_id, $by_lang );
	}

	/**
	 * Import one field from a feed.
	 *
	 * Other marketplaces can call this directly from their own importer:
	 * Marketplace_Fields::import( 'rozetka_keywords', $post_id, $by_lang ).
	 *
	 * @param string $field_id Field id.
	 * @param int    $post_id  Product ID.
	 * @param array  $by_lang  Language code => comma-separated value.
	 * @return bool Whether the stored value changed.
	 */
	public static function import( $field_id, $post_id, $by_lang ) {
		$field = self::field( $field_id );
		if ( null === $field || ! is_array( $by_lang ) || (int) $post_id <= 0 ) {
			return false;
		}

		$default = self::first_value( $by_lang );
		$stored  = get_post_meta( (int) $post_id, $field['meta_key'], true );
		$stored  = is_string( $stored ) ? $stored : '';

		/**
		 * Filter the imported value.
		 *
		 * @param string $value   Default imported value.
		 * @param array  $field   Field definition.
		 * @param array  $by_lang Language code => value.
		 * @param int    $post_id Product ID.
		 * @param string $stored  Stored value.
		 */
		$value = apply_filters( 'f2000cs_marketplace_field_import_value', $default, $field, $by_lang, (int) $post_id, $stored );
		$value = is_string( $value ) ? $value : $default;

		if ( '' === trim( $value ) || $value === $stored ) {
			return false;
		}

		update_post_meta( (int) $post_id, $field['meta_key'], wp_slash( $value ) );

		return true;
	}

	/**
	 * First non-empty value in the order of the feed data.
	 *
	 * @param array $by_lang Language code => value.
	 * @return string
	 */
	private static function first_value( array $by_lang ) {
		foreach ( $by_lang as $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}
}
