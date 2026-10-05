<?php
/**
 * Helpers behind the marketplace settings form: presets with a "custom" escape
 * hatch, brand suggestions, "left=right" repeaters and the category → portal
 * category table.
 *
 * Everything that talks to WordPress is isolated in two small loaders so the rest
 * (conversion between form rows and the stored text) is plain, testable PHP.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Form helpers.
 */
class Marketplace_Form {

	/**
	 * Select value that reveals the free-text input of a preset field.
	 */
	const CUSTOM = '__custom__';

	/**
	 * Replace {tokens} with live site values (so presets follow the site settings).
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function resolve( $value ): string {
		$value = (string) $value;

		if ( false === strpos( $value, '{' ) ) {
			return $value;
		}

		$map = array(
			'{site_name}'   => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '',
			'{site_url}'    => function_exists( 'home_url' ) ? (string) home_url() : '',
			'{wc_currency}' => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'UAH',
		);

		return strtr( $value, $map );
	}

	/**
	 * Which option of a preset field a stored value corresponds to.
	 *
	 * @param string               $value   Stored value.
	 * @param array<string,string> $options Preset options value => label.
	 * @return string The option value, or CUSTOM.
	 */
	public static function selected_preset( $value, array $options ): string {
		return array_key_exists( (string) $value, $options ) ? (string) $value : self::CUSTOM;
	}

	/**
	 * Final value of a preset field from the posted select + custom input.
	 *
	 * @param string $select Posted select value.
	 * @param string $custom Posted custom text.
	 * @return string
	 */
	public static function preset_value( $select, $custom ): string {
		return self::CUSTOM === (string) $select ? (string) $custom : (string) $select;
	}

	/**
	 * Rows of a "left=right" text.
	 *
	 * @param string $text Stored text.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function text_to_rows( $text ): array {
		$rows = array();

		foreach ( Marketplace_Feed::parse_pairs( (string) $text ) as $left => $right ) {
			$rows[] = array( (string) $left, (string) $right );
		}

		return $rows;
	}

	/**
	 * Posted repeater rows into the stored "left=right" text (blank / half-filled rows dropped).
	 *
	 * @param array<int, array{l?:string,r?:string}> $rows Posted rows.
	 * @return string
	 */
	public static function rows_to_text( array $rows ): string {
		$lines = array();

		foreach ( $rows as $row ) {
			$left  = Marketplace_Text::clean( str_replace( array( "\r", "\n", '=' ), ' ', (string) ( $row['l'] ?? '' ) ) );
			$right = Marketplace_Text::clean( str_replace( array( "\r", "\n" ), ' ', (string) ( $row['r'] ?? '' ) ) );

			if ( '' !== $left && '' !== $right ) {
				$lines[] = $left . '=' . $right;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Category path the way the feed matches it: lower-case segments joined by ">".
	 *
	 * @param string $path Path such as "Одяг > Куртки".
	 * @return string
	 */
	public static function normalize_path( $path ): string {
		return (string) preg_replace( '/\s*>\s*/u', '>', Marketplace_Text::lower( Marketplace_Text::clean( $path ) ) );
	}

	/**
	 * Prefill of the category table from the stored rules.
	 *
	 * Rules that equal the path of a listed category fill that row; everything else
	 * (custom substring rules) is returned separately so it is not lost.
	 *
	 * @param string                                $rules      Stored "path=id" text.
	 * @param array<int, array{id:int,path:string}> $categories Categories (path like "Одяг > Куртки").
	 * @return array{by_id:array<int,string>,extra:string}
	 */
	public static function split_rules( $rules, array $categories ): array {
		$by_path = array();

		foreach ( $categories as $cat ) {
			$by_path[ self::normalize_path( $cat['path'] ) ] = (int) $cat['id'];
		}

		$by_id = array();
		$extra = array();

		foreach ( Marketplace_Feed::parse_pairs( (string) $rules ) as $left => $right ) {
			$key = self::normalize_path( (string) $left );

			if ( isset( $by_path[ $key ] ) ) {
				$by_id[ $by_path[ $key ] ] = (string) $right;
			} else {
				$extra[] = $left . '=' . $right;
			}
		}

		return array(
			'by_id' => $by_id,
			'extra' => implode( "\n", $extra ),
		);
	}

	/**
	 * Build the stored rules from the category table and the custom lines.
	 *
	 * More specific (deeper) paths come first because the feed uses the first rule
	 * that matches, so "одяг>куртки" must win over "одяг".
	 *
	 * @param array<int,string>                     $ids_by_term Posted portal ids by category id.
	 * @param array<int, array{id:int,path:string}> $categories  Categories.
	 * @param string                                $extra       Custom "substring=id" lines.
	 * @return string
	 */
	public static function build_rules( array $ids_by_term, array $categories, string $extra = '' ): string {
		$rules = array();

		foreach ( $categories as $cat ) {
			$id = Marketplace_Text::clean( $ids_by_term[ $cat['id'] ] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			$rules[] = array( self::normalize_path( $cat['path'] ), preg_replace( '/[^0-9]/', '', $id ) );
		}

		$rules = array_filter(
			$rules,
			static function ( $r ) {
				return '' !== $r[1];
			}
		);

		$rules = Marketplace_Text::sorted_by(
			array_values( $rules ),
			static function ( $r ) {
				return array( -substr_count( $r[0], '>' ), $r[0] );
			}
		);

		$lines = array();

		foreach ( $rules as $r ) {
			$lines[] = $r[0] . '=' . $r[1];
		}

		foreach ( (array) preg_split( '/\r\n|\r|\n/', $extra ) as $line ) {
			$line = Marketplace_Text::clean( $line );

			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}

	// ------------------------------------------------------------------
	// WordPress loaders (only used by the admin page)
	// ------------------------------------------------------------------

	/**
	 * Product categories as flat list with their full path, ordered like the tree.
	 *
	 * @return array<int, array{id:int,path:string,depth:int,count:int}>
	 */
	public static function load_categories(): array {
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$by_id = array();
		foreach ( $terms as $t ) {
			$by_id[ (int) $t->term_id ] = $t;
		}

		$path_of = static function ( $term ) use ( $by_id ) {
			$names = array( $term->name );
			$guard = 0;
			$pid   = (int) $term->parent;

			while ( $pid && isset( $by_id[ $pid ] ) && $guard++ < 12 ) {
				array_unshift( $names, $by_id[ $pid ]->name );
				$pid = (int) $by_id[ $pid ]->parent;
			}

			return $names;
		};

		$out = array();

		foreach ( $by_id as $id => $term ) {
			$names = $path_of( $term );
			$out[] = array(
				'id'    => $id,
				'path'  => implode( ' > ', $names ),
				'depth' => count( $names ) - 1,
				'count' => (int) $term->count,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( Marketplace_Text::lower( $a['path'] ), Marketplace_Text::lower( $b['path'] ) );
			}
		);

		return $out;
	}

	/**
	 * Brand names found on the site (first brand taxonomy that exists).
	 *
	 * @return array<int,string>
	 */
	public static function load_brands(): array {
		if ( ! function_exists( 'get_terms' ) || ! function_exists( 'taxonomy_exists' ) ) {
			return array();
		}

		$taxonomies = (array) apply_filters( 'f2000cs_marketplace_brand_taxonomies', array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand', 'brand' ) );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$names = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'fields'     => 'names',
					'number'     => 300,
				)
			);

			if ( is_array( $names ) && $names ) {
				$names = array_map( 'strval', $names );
				sort( $names );

				return $names;
			}
		}

		return array();
	}
}
