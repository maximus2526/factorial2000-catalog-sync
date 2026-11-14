<?php
/**
 * Per-term, per-Prom-category values.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Stores a term-level map: Prom category id => official value.
 *
 * The site term keeps its own name; the feed replaces only the value it sends
 * to Prom for the category where a map exists.
 */
class Prom_Term_Values {

	/**
	 * Term meta key.
	 */
	const META_KEY = '_f2000cs_prom_value_map';

	/**
	 * Term indexes by taxonomy.
	 *
	 * @var array<string, array<string, int>>
	 */
	private static $indexes = array();

	/**
	 * Parsed maps by term id.
	 *
	 * @var array<int, array<string, string>>
	 */
	private static $maps = array();

	/**
	 * Forget request caches.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$indexes = array();
		self::$maps    = array();
	}

	/**
	 * Resolve a value for a site term and Prom category.
	 *
	 * When a term map exists it wins, but only when the mapped value is an
	 * official value of the target characteristic: otherwise the original
	 * value is kept so Prom imports it as a custom characteristic instead of
	 * a wrong main one.
	 *
	 * @param string     $site_key Site attribute taxonomy, e.g. pa_color.
	 * @param string     $value    Value currently produced by WooCommerce.
	 * @param string     $portal   Prom category id.
	 * @param array<int> $term_ids Term ids from the row, when known.
	 * @param string     $target   Prom characteristic name the value goes under.
	 * @return string Official value, or the original value as a fallback.
	 */
	public static function resolve( $site_key, $value, $portal, $term_ids = array(), $target = '' ): string {
		$site_key = (string) $site_key;
		$value    = (string) $value;
		$portal   = Prom_Categories::sanitize_id( $portal );
		$term_ids = array_values(
			array_filter(
				array_map( 'intval', (array) $term_ids ),
				static function ( $id ) {
					return $id > 0;
				}
			)
		);

		if ( '' === $value || '' === $portal || 0 !== strpos( $site_key, 'pa_' ) ) {
			return $value;
		}

		// A combined value has no single term identity; keep it for Prom's
		// custom characteristics instead of guessing a main characteristic.
		if ( count( $term_ids ) > 1 ) {
			return $value;
		}

		$term_id = array() !== $term_ids ? (int) $term_ids[0] : self::find_term_id( $site_key, $value );

		if ( 0 === $term_id ) {
			return $value;
		}

		$map = self::map_for_term( $term_id );

		if ( ! isset( $map[ $portal ] ) ) {
			return $value;
		}

		$mapped = (string) $map[ $portal ];

		if ( '' !== $target && false === Prom_Values::is_official( $portal, $target, $mapped ) ) {
			return $value;
		}

		return $mapped;
	}

	/**
	 * Find a term by its display name in a taxonomy.
	 *
	 * Term names may contain translator tags, so the index compares the plain
	 * name with all language tags removed.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $value    Display value.
	 * @return int Term id or 0.
	 */
	private static function find_term_id( $taxonomy, $value ): int {
		if ( ! isset( self::$indexes[ $taxonomy ] ) ) {
			self::$indexes[ $taxonomy ] = array();

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_id = isset( $term->term_id ) ? (int) $term->term_id : (int) ( is_array( $term ) ? ( $term['term_id'] ?? 0 ) : 0 );
					$name    = isset( $term->name ) ? (string) $term->name : (string) ( is_array( $term ) ? ( $term['name'] ?? '' ) : '' );
					$slug    = isset( $term->slug ) ? (string) $term->slug : (string) ( is_array( $term ) ? ( $term['slug'] ?? '' ) : '' );

					if ( $term_id <= 0 ) {
						continue;
					}

					$candidates = array_merge( self::name_variants( $name ), array( self::plain( $slug ) ) );

					foreach ( array_unique( $candidates ) as $candidate ) {
						if ( '' === $candidate ) {
							continue;
						}

						if ( ! isset( self::$indexes[ $taxonomy ][ $candidate ] ) ) {
							self::$indexes[ $taxonomy ][ $candidate ] = $term_id;
						} elseif ( self::$indexes[ $taxonomy ][ $candidate ] !== $term_id ) {
							// Same display name on two terms: mapping by name is unsafe.
							self::$indexes[ $taxonomy ][ $candidate ] = -1;
						}
					}
				}
			}
		}

		foreach ( self::name_variants( $value ) as $key ) {
			if ( ! isset( self::$indexes[ $taxonomy ][ $key ] ) ) {
				continue;
			}

			$term_id = (int) self::$indexes[ $taxonomy ][ $key ];

			return $term_id > 0 ? $term_id : 0;
		}

		return 0;
	}

	/**
	 * Plain names of every language group in a translator-tagged name.
	 *
	 * @param string $text Name or value.
	 * @return array<int,string>
	 */
	private static function name_variants( $text ): array {
		$text     = (string) $text;
		$variants = array();

		if ( preg_match_all( '/\[:[^\]]*\](.*?)(?=\[:|$)/su', $text, $matches ) && isset( $matches[1] ) ) {
			foreach ( $matches[1] as $part ) {
				$part = self::plain( $part );

				if ( '' !== $part ) {
					$variants[] = $part;
				}
			}
		}

		if ( array() === $variants ) {
			$variants[] = self::plain( $text );
		}

		return array_values( array_unique( array_filter( $variants ) ) );
	}

	/**
	 * Plain, case-folded text without translator tags.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function plain( $text ): string {
		$text = preg_replace( '/\[:[^\]]*\]/u', '', (string) $text );
		$text = is_string( $text ) ? trim( $text ) : '';

		return Marketplace_Text::lower( $text );
	}

	/**
	 * Map for a term.
	 *
	 * @param int $term_id Term id.
	 * @return array<string,string>
	 */
	private static function map_for_term( $term_id ): array {
		$term_id = (int) $term_id;

		if ( isset( self::$maps[ $term_id ] ) ) {
			return self::$maps[ $term_id ];
		}

		$raw = function_exists( 'get_term_meta' ) ? get_term_meta( $term_id, self::META_KEY, true ) : '';
		$map = self::sanitize_map( $raw );

		self::$maps[ $term_id ] = $map;

		return $map;
	}

	/**
	 * Sanitize a stored map or the admin textarea format.
	 *
	 * Admin format is one "category_id=official value" per line; JSON is also
	 * accepted for imports. Empty values are dropped.
	 *
	 * @param mixed $raw Raw map.
	 * @return array<string,string>
	 */
	public static function sanitize_map( $raw ): array {
		if ( is_string( $raw ) ) {
			$trimmed = trim( $raw );

			if ( '' === $trimmed ) {
				return array();
			}

			$decoded = json_decode( $trimmed, true );

			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			} else {
				$raw = array();

				foreach ( preg_split( '/\R/u', $trimmed ) as $line ) {
					$line = trim( $line );

					if ( '' === $line || false === strpos( $line, '=' ) ) {
						continue;
					}

					list( $category_id, $value ) = explode( '=', $line, 2 );
					$raw[ $category_id ]         = $value;
				}
			}
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $category_id => $value ) {
			$category_id = Prom_Categories::sanitize_id( $category_id );
			$value       = Marketplace_Text::clean( (string) $value );

			if ( '' !== $category_id && '' !== $value ) {
				$out[ $category_id ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Map formatted for the term edit textarea.
	 *
	 * @param int $term_id Term id.
	 * @return string
	 */
	public static function format_for_editor( $term_id ): string {
		$lines = array();

		foreach ( self::map_for_term( $term_id ) as $category_id => $value ) {
			$lines[] = $category_id . '=' . $value;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Save a map for a term.
	 *
	 * @param int   $term_id Term id.
	 * @param mixed $raw     Map.
	 * @return void
	 */
	public static function save_for_term( $term_id, $raw ): void {
		$map     = self::sanitize_map( $raw );
		$term_id = (int) $term_id;

		if ( 0 >= $term_id ) {
			return;
		}

		if ( array() === $map ) {
			delete_term_meta( $term_id, self::META_KEY );
		} else {
			update_term_meta( $term_id, self::META_KEY, $map );
		}

		unset( self::$maps[ $term_id ] );
	}
}
