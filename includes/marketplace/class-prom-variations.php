<?php
/**
 * Prom.ua variation ("різновиди") rules.
 *
 * Direct port of the attribute / variation logic of the wc_to_prom_yml.py tool.
 * Prom builds variations only from characteristics of the main product: every
 * variation needs its own unique set of up to three dimension params (size /
 * colour first), values must come from the parent's options, and two variations
 * with the same set make Prom reject the whole group.
 *
 * Rows are associative arrays keyed like the WooCommerce product CSV export
 * ("ID", "Name", "Attribute 1 name", "Attribute 1 value(s)" …).
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Variation / param resolution for Prom.
 */
class Prom_Variations {

	const MAX_DIMENSIONS = 3;

	/**
	 * Normalized attribute keys that mean "size".
	 *
	 * @var array<int, string>
	 */
	const SIZE_KEYS = array( 'розмір', 'розміри', 'розмеры', 'size' );

	/**
	 * Normalized attribute keys that mean "colour".
	 *
	 * @var array<int, string>
	 */
	const COLOR_KEYS = array( 'колір', 'колір товару', 'цвет', 'кольорове рішення' );

	// ------------------------------------------------------------------
	// Small helpers
	// ------------------------------------------------------------------

	/**
	 * Whether $needle occurs in $haystack.
	 *
	 * @param string $haystack Text.
	 * @param string $needle   Fragment.
	 * @return bool
	 */
	private static function has( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}

	/**
	 * Letters only (Python: "".join(ch for ch in s if ch.isalpha())).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	private static function letters( string $value ): string {
		return (string) preg_replace( '/[^\p{L}]/u', '', $value );
	}

	/**
	 * Row ID as int; unparsable ids sort last (10**12).
	 *
	 * @param array<string,string> $row Row.
	 * @return int
	 */
	public static function row_id_int( array $row ): int {
		$raw = Marketplace_Text::clean( $row['ID'] ?? '0' );

		if ( '' === $raw ) {
			$raw = '0';
		}

		return preg_match( '/^[+-]?\d+$/', $raw ) ? (int) $raw : 1000000000000;
	}

	/**
	 * Normalized value tokens use the same trimming everywhere.
	 *
	 * @param string $value Token.
	 * @return string
	 */
	public static function trim_size_token( $value ): string {
		return Marketplace_Text::clean( $value );
	}

	// ------------------------------------------------------------------
	// Attribute name / value normalization
	// ------------------------------------------------------------------

	/**
	 * Display name of an attribute ("pa_розмір" → "розмір").
	 *
	 * @param string $raw Raw name.
	 * @return string
	 */
	public static function normalize_param_name( $raw ): string {
		$raw = Marketplace_Text::clean( $raw );

		if ( '' === $raw ) {
			return '';
		}

		$name = Marketplace_Text::clean( str_replace( '_', ' ', rawurldecode( $raw ) ) );

		if ( 0 === strpos( $name, 'pa ' ) ) {
			return Marketplace_Text::clean( substr( $name, 3 ) );
		}

		return $name;
	}

	/**
	 * Attribute value: " | " separators folded, percent-encoding decoded.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function normalize_param_value( $raw ): string {
		$value = Marketplace_Text::clean( str_replace( ' | ', '|', (string) $raw ) );

		if ( false !== strpos( $value, '%' ) ) {
			$value = rawurldecode( $value );
		}

		return $value;
	}

	/**
	 * Key that makes "pa_розмір-взуття" and "Розмір взуття" the same attribute.
	 *
	 * @param string $display_name Attribute name.
	 * @return string
	 */
	public static function normalized_attr_key( $display_name ): string {
		return (string) preg_replace( '/[\s\-]+/u', '', Marketplace_Text::lower( self::normalize_param_name( $display_name ) ) );
	}

	/**
	 * Split a value on "|" and ",".
	 *
	 * @param string $value Value.
	 * @return array<int, string>
	 */
	public static function split_param_tokens( $value ): array {
		$inner = self::normalize_param_value( $value );
		$out   = array();

		foreach ( (array) preg_split( '/[|,]/', $inner ) as $part ) {
			$part = Marketplace_Text::clean( $part );

			if ( '' !== $part ) {
				$out[] = $part;
			}
		}

		return $out;
	}

	/**
	 * Whether the value lists several options.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_multi_choice_value( $value ): bool {
		return count( self::split_param_tokens( $value ) ) > 1;
	}

	/**
	 * Whether the attribute is a size, colour or footwear dimension.
	 *
	 * @param string $display_name Attribute name.
	 * @return bool
	 */
	public static function dimension_like_attribute( $display_name ): bool {
		$nkey = self::normalized_attr_key( $display_name );

		if ( in_array( $nkey, self::SIZE_KEYS, true ) || in_array( $nkey, self::COLOR_KEYS, true ) ) {
			return true;
		}

		$compact = self::letters( $nkey );

		foreach ( array( 'розмір', 'колір', 'цвет', 'size', 'взут', 'обув' ) as $hint ) {
			if ( self::has( $compact, $hint ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Size-related axis?
	 *
	 * @param string $nk    Normalized key.
	 * @param string $pname Display name.
	 * @return bool
	 */
	public static function is_size_related_axis( $nk, $pname ): bool {
		$pl         = Marketplace_Text::lower( $pname );
		$nk2        = '' !== $nk ? Marketplace_Text::lower( $nk ) : '';
		$letters_nk = Marketplace_Text::lower( self::letters( (string) $nk ) );

		if ( in_array( $nk, self::SIZE_KEYS, true ) ) {
			return true;
		}

		if ( self::has( (string) $nk, 'розмір' ) || self::has( (string) $nk, 'размер' ) ) {
			return true;
		}

		if ( self::has( $letters_nk, 'обув' ) || self::has( $nk2, 'взут' ) || self::has( $pl, 'обув' ) || self::has( $pl, 'взутт' ) ) {
			return true;
		}

		return self::has( $letters_nk, 'razmerobuvi' ) || self::has( $nk2, 'size' );
	}

	/**
	 * Colour-related axis?
	 *
	 * @param string $nk    Normalized key.
	 * @param string $pname Display name.
	 * @return bool
	 */
	public static function is_color_related_axis( $nk, $pname ): bool {
		$n  = '' !== $nk ? Marketplace_Text::lower( $nk ) : '';
		$pl = Marketplace_Text::lower( $pname );

		if ( in_array( $n, self::COLOR_KEYS, true ) ) {
			return true;
		}

		foreach ( array( 'колір', 'цвет', 'colour' ) as $x ) {
			if ( '' !== $n && self::has( $n, $x ) ) {
				return true;
			}
		}

		return self::has( $pl, 'колір' ) || self::has( $pl, 'цвет' );
	}

	/**
	 * Priority of an axis when more than three are available (lower = earlier).
	 *
	 * @param string $nk    Normalized key.
	 * @param string $pname Display name.
	 * @return int
	 */
	public static function variation_axis_pick_score( $nk, $pname ): int {
		if ( self::is_color_related_axis( $nk, $pname ) ) {
			return 8;
		}

		if ( self::is_size_related_axis( $nk, $pname ) ) {
			return 16;
		}

		if ( self::dimension_like_attribute( $pname ) ) {
			return 40;
		}

		return 999;
	}

	// ------------------------------------------------------------------
	// Size tokens
	// ------------------------------------------------------------------

	/**
	 * Sort key for size-like tokens (numbers, XS…XXL, 2XL).
	 *
	 * @param string $value Token.
	 * @return array{0:int,1:string}
	 */
	public static function sort_size_like_token( $value ): array {
		$stripped = str_replace( ' ', '', Marketplace_Text::upper( self::trim_size_token( $value ) ) );

		if ( preg_match( '/^(\d+)$/u', $stripped, $m ) ) {
			return array( 0, Marketplace_Text::zfill( $stripped, 4 ) );
		}

		if ( preg_match( '/^(\d+)(XL|XS|S|M|L)$/iu', $stripped, $m ) ) {
			return array( 100 + (int) $m[1], Marketplace_Text::upper( $m[0] ) );
		}

		$order_pref = array(
			'XXXS' => 10,
			'XXS'  => 20,
			'XS'   => 30,
			'S'    => 40,
			'M'    => 50,
			'L'    => 60,
			'XL'   => 70,
		);

		if ( isset( $order_pref[ $stripped ] ) ) {
			return array( 200 + $order_pref[ $stripped ], $stripped );
		}

		if ( preg_match( '/^(\d+)XL$/iu', $stripped, $m ) ) {
			return array( 300 + (int) $m[1], Marketplace_Text::upper( $stripped ) );
		}

		return array( 9000, $stripped );
	}

	/**
	 * Tokens ordered like sizes.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @return array<int, string>
	 */
	public static function sorted_size_tokens( array $tokens ): array {
		return Marketplace_Text::sorted_by(
			$tokens,
			static function ( $t ) {
				return self::sort_size_like_token( self::trim_size_token( $t ) );
			}
		);
	}

	/**
	 * EU shoe size from a title such as "… , 40 - olive".
	 *
	 * @param string $title Title.
	 * @return string|null
	 */
	public static function parse_eu_shoe_size_from_title( $title ) {
		if ( ! preg_match( '/,\s*(\d{2})\s*-\s*/u', (string) $title, $m ) ) {
			return null;
		}

		$n = (int) $m[1];

		return ( $n >= 32 && $n <= 52 ) ? $m[1] : null;
	}

	/**
	 * Clothing size suffix from a title such as "… - XL".
	 *
	 * @param string $title Title.
	 * @return string|null
	 */
	public static function parse_clothing_size_suffix_from_title( $title ) {
		if ( ! preg_match( '/[-–—]\s*((?:XXXS|XXS|XS|XL|XXL|S|M|L|2XL|3XL|[23]?XL))\s*$/iu', (string) $title, $m ) ) {
			return null;
		}

		return str_replace( ' ', '', Marketplace_Text::upper( self::trim_size_token( $m[1] ) ) );
	}

	/**
	 * Last " - " fragment of a title (often a colour).
	 *
	 * @param string $title Title.
	 * @return string|null
	 */
	public static function parse_trailing_option_slug( $title ) {
		$parts = preg_split( '/\s*[-–—]\s*/u', Marketplace_Text::clean( $title ) );

		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return null;
		}

		$tail = Marketplace_Text::clean( end( $parts ) );

		if ( '' === $tail ) {
			return null;
		}

		$tail = Marketplace_Text::clean( rtrim( $tail, '.' ) );

		if ( preg_match( '/^\p{Nd}+$/u', $tail ) && mb_strlen( $tail, 'UTF-8' ) <= 2 ) {
			return null;
		}

		return $tail;
	}

	/**
	 * Candidate value as one of the parent's tokens (case-insensitive).
	 *
	 * @param string             $candidate Candidate.
	 * @param array<int, string> $tokens    Parent tokens.
	 * @return string|null
	 */
	public static function normalize_to_parent_token( $candidate, array $tokens ) {
		$cand = self::normalize_param_value( $candidate );

		if ( '' === $cand || ! $tokens ) {
			return null;
		}

		$map = array();
		foreach ( $tokens as $t ) {
			$map[ Marketplace_Text::lower( $t ) ] = $t;
		}

		return $map[ Marketplace_Text::lower( $cand ) ] ?? null;
	}

	// ------------------------------------------------------------------
	// Params from rows
	// ------------------------------------------------------------------

	/**
	 * Name/value pairs of a row (up to 13 attributes).
	 *
	 * Variations include attributes regardless of the "visible" flag, otherwise
	 * Prom would see no unique characteristics.
	 *
	 * @param array<string,string> $row           Row.
	 * @param bool                 $for_variation Variation mode.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function collect_params( array $row, bool $for_variation = false ): array {
		$params = array();

		for ( $idx = 1; $idx <= 13; $idx++ ) {
			$name    = self::normalize_param_name( $row[ "Attribute {$idx} name" ] ?? '' );
			$value   = self::normalize_param_value( $row[ "Attribute {$idx} value(s)" ] ?? '' );
			$visible = Marketplace_Text::clean( $row[ "Attribute {$idx} visible" ] ?? '' );

			if ( '' === $name || '' === $value ) {
				continue;
			}

			if ( ! $for_variation && '' !== $visible && ! Marketplace_Text::parse_bool( $visible ) ) {
				continue;
			}

			$params[] = array( $name, $value );
		}

		return $params;
	}

	/**
	 * On the main offer several size/colour options collapse into one value.
	 *
	 * @param string $parent_name  Attribute name.
	 * @param string $parent_value Attribute value.
	 * @return string
	 */
	public static function main_display_value( $parent_name, $parent_value ): string {
		if ( ! self::dimension_like_attribute( $parent_name ) ) {
			return (string) $parent_value;
		}

		$tokens = self::split_param_tokens( $parent_value );

		if ( count( $tokens ) > 1 ) {
			return self::sorted_size_tokens( $tokens )[0];
		}

		return (string) $parent_value;
	}

	/**
	 * Params shown on the main (parent) offer.
	 *
	 * @param array<string,string> $row Parent row.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function build_parent_prom_params( array $row ): array {
		$seen   = array();
		$result = array();

		foreach ( self::collect_params( $row, true ) as $pair ) {
			list( $n, $v ) = $pair;

			$v2  = self::main_display_value( $n, $v );
			$key = self::normalized_attr_key( $n ) . "\x1f" . Marketplace_Text::lower( Marketplace_Text::clean( $v2 ) );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$result[]     = array( $n, $v2 );
		}

		return $result;
	}

	/**
	 * Parent params by normalized key (first occurrence wins, like the Python reversed() map).
	 *
	 * @param array<int, array{0:string,1:string}> $parent_prom_params Params.
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function parent_param_map_by_normalized( array $parent_prom_params ): array {
		$out = array();

		foreach ( array_reverse( $parent_prom_params ) as $pair ) {
			$out[ self::normalized_attr_key( $pair[0] ) ] = $pair;
		}

		return $out;
	}

	/**
	 * Parent raw params by normalized key (last occurrence wins).
	 *
	 * @param array<string,string> $parent_row Parent row.
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function raw_parent_value_by_normalized( array $parent_row ): array {
		$out = array();

		foreach ( self::collect_params( $parent_row, true ) as $pair ) {
			$out[ self::normalized_attr_key( $pair[0] ) ] = $pair;
		}

		return $out;
	}

	/**
	 * Size axis (name + sorted options) of a parent, or [null, []].
	 *
	 * @param array<int, array{0:string,1:string}> $parent_params Parent params.
	 * @return array{0:?string,1:array<int,string>}
	 */
	public static function extract_size_axis_from_parent( array $parent_params ): array {
		foreach ( $parent_params as $pair ) {
			list( $pname, $pval ) = $pair;

			$nkey   = self::normalized_attr_key( $pname );
			$tokens = self::split_param_tokens( $pval );

			if ( ! $tokens ) {
				continue;
			}

			if (
				in_array( $nkey, self::SIZE_KEYS, true )
				|| self::has( $nkey, 'розмір' )
				|| self::has( $nkey, 'розмири' )
				|| self::has( Marketplace_Text::lower( $pname ), 'розмір' )
				|| self::is_size_related_axis( $nkey, $pname )
			) {
				return array( $pname, self::sorted_size_tokens( $tokens ) );
			}
		}

		return array( null, array() );
	}

	/**
	 * Lower-cased colour of a variation row, or null.
	 *
	 * @param array<string,string> $var_row Variation row.
	 * @return string|null
	 */
	public static function extract_color_value( array $var_row ) {
		foreach ( self::collect_params( $var_row, true ) as $pair ) {
			$nk = self::normalized_attr_key( $pair[0] );

			if ( in_array( $nk, self::COLOR_KEYS, true ) || self::has( $nk, 'колір' ) || self::has( $nk, 'цвет' ) ) {
				return Marketplace_Text::lower( Marketplace_Text::clean( $pair[1] ) );
			}
		}

		return null;
	}

	/**
	 * Keep at most $max params, preferring colour then size.
	 *
	 * @param array<int, array{0:string,1:string}> $params Params.
	 * @param int                                  $max    Max dimensions.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function cap_dimensions_for_prom( array $params, int $max ): array {
		if ( count( $params ) <= $max ) {
			return $params;
		}

		$ordered = Marketplace_Text::sorted_by(
			$params,
			static function ( $pair ) {
				$n     = self::normalized_attr_key( $pair[0] );
				$score = 100;

				if ( self::has( $n, 'колір' ) || self::has( $n, 'цвет' ) ) {
					$score = 10;
				} elseif ( self::has( $n, 'розмір' ) || self::has( $n, 'взут' ) || self::has( $n, 'обув' ) || self::has( $n, 'размер' ) ) {
					$score = 20;
				}

				return array( $score, Marketplace_Text::lower( $pair[0] ) );
			}
		);

		return array_slice( $ordered, 0, $max );
	}

	/**
	 * Drop values equal to the main offer unless nothing would be left.
	 *
	 * @param array<int, array{0:string,1:string}> $pairs    Pairs.
	 * @param array<string,string>                 $baseline Main params by normalized key.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function drop_baseline_only_if_non_empty( array $pairs, array $baseline ): array {
		if ( ! $pairs ) {
			return $pairs;
		}

		$out = array();

		foreach ( $pairs as $pair ) {
			$bl = $baseline[ self::normalized_attr_key( $pair[0] ) ] ?? '';

			if ( Marketplace_Text::lower( Marketplace_Text::clean( $pair[1] ) ) === Marketplace_Text::lower( Marketplace_Text::clean( $bl ) ) ) {
				continue;
			}

			$out[] = array( $pair[0], Marketplace_Text::clean( $pair[1] ) );
		}

		return $out ? $out : $pairs;
	}

	/**
	 * Extra multi-valued axes of a parent used to tell identical variations apart.
	 *
	 * @param array<string,string> $parent_row Parent row.
	 * @return array<int, array{0:string,1:array<int,string>}>
	 */
	public static function bonus_multi_axes_for_parent( array $parent_row ): array {
		$cand = array();

		foreach ( self::raw_parent_value_by_normalized( $parent_row ) as $nk => $pair ) {
			list( $pname, $raw_full ) = $pair;

			if ( '' === $pname || ! self::is_multi_choice_value( $raw_full ) ) {
				continue;
			}

			$toks = self::split_param_tokens( $raw_full );

			if ( count( $toks ) < 2 ) {
				continue;
			}

			$prio = ( ! ( self::dimension_like_attribute( $pname ) || self::is_size_related_axis( (string) $nk, $pname ) || self::is_color_related_axis( (string) $nk, $pname ) ) ) ? 0 : 1;

			$sorted = Marketplace_Text::sorted_by(
				$toks,
				static function ( $t ) {
					return array( Marketplace_Text::lower( Marketplace_Text::clean( $t ) ) );
				}
			);

			$cand[] = array( $prio, Marketplace_Text::clean( $pname ), $sorted );
		}

		$cand = Marketplace_Text::sorted_by(
			$cand,
			static function ( $c ) {
				return array( $c[0], Marketplace_Text::lower( $c[1] ) );
			}
		);

		return array_map(
			static function ( $c ) {
				return array( $c[1], $c[2] );
			},
			$cand
		);
	}

	/**
	 * Ordered list of up to three axes (normalized key + display name) of a parent.
	 *
	 * @param array<string,string> $parent_row Parent row.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function group_variation_axis_list( array $parent_row ): array {
		$scored = array();

		foreach ( self::raw_parent_value_by_normalized( $parent_row ) as $nk => $pair ) {
			list( $pname, $pval ) = $pair;

			if ( '' === $pname || '' === $pval ) {
				continue;
			}

			$dim = self::dimension_like_attribute( $pname ) || self::is_size_related_axis( (string) $nk, $pname );

			if ( ! $dim ) {
				continue;
			}

			$scored[] = array( self::variation_axis_pick_score( (string) $nk, $pname ), (string) $nk, Marketplace_Text::clean( $pname ) );
		}

		$scored = Marketplace_Text::sorted_by(
			$scored,
			static function ( $s ) {
				return array( $s[0], $s[1] );
			}
		);

		$out  = array();
		$seen = array();

		foreach ( $scored as $s ) {
			if ( isset( $seen[ $s[1] ] ) ) {
				continue;
			}

			$seen[ $s[1] ] = true;
			$out[]         = array( $s[1], $s[2] );

			if ( count( $out ) >= self::MAX_DIMENSIONS ) {
				break;
			}
		}

		return $out;
	}

	// ------------------------------------------------------------------
	// Resolving one variation
	// ------------------------------------------------------------------

	/**
	 * Value of one axis for one variation.
	 *
	 * @param string                        $nk                     Normalized key.
	 * @param string                        $pname_raw              Parent display name.
	 * @param string                        $raw_full               Parent raw value (all options).
	 * @param string                        $baseline_value         Main offer value (unused, kept for parity).
	 * @param array<string,string>          $var_row                Variation row.
	 * @param array{0:string,1:string}|null $size_hint          Inferred size.
	 * @param int                           $sibling_fallback_index Index among siblings.
	 * @return string
	 */
	public static function resolve_variation_axis_value( $nk, $pname_raw, $raw_full, $baseline_value, array $var_row, $size_hint, int $sibling_fallback_index = 0 ): string {
		$tok_list  = self::split_param_tokens( $raw_full );
		$tok_lower = array();

		foreach ( $tok_list as $t ) {
			$tok_lower[ Marketplace_Text::lower( $t ) ] = $t;
		}

		// 1) Attributes from the variation row itself.
		foreach ( self::collect_params( $var_row, true ) as $pair ) {
			list( $vn, $vv ) = $pair;

			if ( self::normalized_attr_key( $vn ) !== $nk ) {
				continue;
			}

			$vv_clean = self::normalize_param_value( $vv );

			if ( '' === $vv_clean ) {
				continue;
			}

			if ( self::is_multi_choice_value( $raw_full ) ) {
				$hit = self::normalize_to_parent_token( $vv_clean, $tok_list );

				return null !== $hit && '' !== $hit ? $hit : $vv_clean;
			}

			return $vv_clean;
		}

		$nm = Marketplace_Text::clean( $var_row['Name'] ?? '' );

		// 2) Size hint.
		if ( $size_hint ) {
			list( $sk, $sv ) = $size_hint;

			if ( self::normalized_attr_key( $sk ) === $nk ) {
				$sv = self::normalize_param_value( $sv );

				if ( self::is_multi_choice_value( $raw_full ) && $tok_lower ) {
					return $tok_lower[ Marketplace_Text::lower( $sv ) ] ?? $sv;
				}

				return $sv;
			}
		}

		// 3) Size from the title.
		if ( self::is_size_related_axis( $nk, $pname_raw ) ) {
			$eu = self::parse_eu_shoe_size_from_title( $nm );

			if ( null !== $eu && $tok_list ) {
				foreach ( $tok_list as $t ) {
					if ( ltrim( trim( $t ), '0' ) === ltrim( $eu, '0' ) || $t === $eu ) {
						return $t;
					}
				}

				if ( isset( $tok_lower[ Marketplace_Text::lower( $eu ) ] ) ) {
					return $tok_lower[ Marketplace_Text::lower( $eu ) ];
				}
			}

			$suffix = self::parse_clothing_size_suffix_from_title( $nm );

			if ( null !== $suffix && $tok_list ) {
				foreach ( $tok_list as $t ) {
					$tn = str_replace( ' ', '', Marketplace_Text::upper( self::trim_size_token( $t ) ) );

					if ( $tn === $suffix || $tn === Marketplace_Text::upper( $suffix ) ) {
						return $t;
					}

					if ( Marketplace_Text::lower( $tn ) === Marketplace_Text::lower( $suffix ) ) {
						return $t;
					}
				}
			}
		}

		// 4) Colour from the tail of the title.
		if ( self::is_color_related_axis( $nk, $pname_raw ) && $tok_list ) {
			$slug = self::parse_trailing_option_slug( $nm );

			if ( null !== $slug && '' !== $slug ) {
				$hit = self::normalize_to_parent_token( $slug, $tok_list );

				if ( null !== $hit && '' !== $hit ) {
					return $hit;
				}
			}
		}

		// 5) The only option of the parent.
		if ( 1 === count( $tok_list ) ) {
			return $tok_list[0];
		}

		// Never fall back to the main offer's value: siblings would collapse into duplicates.
		if ( $tok_list ) {
			if ( self::is_size_related_axis( $nk, $pname_raw ) ) {
				$ordered = self::sorted_size_tokens( $tok_list );
			} else {
				$ordered = Marketplace_Text::sorted_by(
					$tok_list,
					static function ( $t ) {
						return array( Marketplace_Text::lower( Marketplace_Text::clean( $t ) ) );
					}
				);
			}

			return $ordered[ $sibling_fallback_index % count( $ordered ) ];
		}

		return '';
	}

	/**
	 * Params of a variation on the group's common axes.
	 *
	 * @param array<string,string>                 $parent_row             Parent row.
	 * @param array<string,string>                 $var_row                Variation row.
	 * @param array{0:string,1:string}|null        $size_hint              Inferred size.
	 * @param array<int, array{0:string,1:string}> $axis_list      Group axes.
	 * @param int                                  $sibling_fallback_index Index among siblings.
	 * @param bool                                 $drop_baseline          Remove values equal to the main offer.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function params_aligned_for_group( array $parent_row, array $var_row, $size_hint, array $axis_list, int $sibling_fallback_index = 0, bool $drop_baseline = true ): array {
		$baseline = array();

		foreach ( self::build_parent_prom_params( $parent_row ) as $pair ) {
			$key = self::normalized_attr_key( $pair[0] );

			if ( '' !== $key ) {
				$baseline[ $key ] = Marketplace_Text::clean( $pair[1] );
			}
		}

		$parent_raw_vals = self::raw_parent_value_by_normalized( $parent_row );
		$out             = array();

		foreach ( $axis_list as $axis ) {
			$nk        = $axis[0];
			$raw_entry = $parent_raw_vals[ $nk ] ?? null;

			if ( ! $raw_entry ) {
				continue;
			}

			list( $pname_raw, $raw_full ) = $raw_entry;

			$val = self::resolve_variation_axis_value( $nk, $pname_raw, $raw_full, $baseline[ $nk ] ?? '', $var_row, $size_hint, $sibling_fallback_index );

			if ( '' === Marketplace_Text::clean( $val ) ) {
				continue;
			}

			$out[] = array( Marketplace_Text::clean( $pname_raw ), Marketplace_Text::clean( $val ) );
		}

		$capped = self::cap_dimensions_for_prom( $out, self::MAX_DIMENSIONS );

		return $drop_baseline ? self::drop_baseline_only_if_non_empty( $capped, $baseline ) : $capped;
	}

	/**
	 * Params of a variation when the group has no usable common axes.
	 *
	 * @param array<string,string>          $parent_row Parent row.
	 * @param array<string,string>          $var_row    Variation row.
	 * @param array{0:string,1:string}|null $size_hint  Inferred size.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function params_for_variation( array $parent_row, array $var_row, $size_hint ): array {
		$parent_display  = self::build_parent_prom_params( $parent_row );
		$parent_map      = self::parent_param_map_by_normalized( $parent_display );
		$parent_raw_vals = self::raw_parent_value_by_normalized( $parent_row );
		$seen            = array();
		$collected       = array();

		$remember = static function ( $name_orig, $value ) use ( &$seen, &$collected ) {
			$nk = self::normalized_attr_key( $name_orig );

			if ( isset( $seen[ $nk ] ) ) {
				return;
			}

			$seen[ $nk ] = true;
			$collected[] = array( Marketplace_Text::clean( $name_orig ), Marketplace_Text::clean( $value ) );
		};

		$baseline = array();
		foreach ( $parent_display as $pair ) {
			$baseline[ self::normalized_attr_key( $pair[0] ) ] = Marketplace_Text::clean( $pair[1] );
		}

		// Attributes from the variation row itself.
		foreach ( self::collect_params( $var_row, true ) as $pair ) {
			list( $vn, $vv ) = $pair;

			$nk = self::normalized_attr_key( $vn );

			if ( ! isset( $parent_map[ $nk ] ) ) {
				continue;
			}

			$vv_clean = self::normalize_param_value( $vv );

			if ( '' === $vv_clean ) {
				continue;
			}

			if ( ! isset( $parent_raw_vals[ $nk ] ) ) {
				continue;
			}

			list( $pname_raw_orig, $raw_val_full ) = $parent_raw_vals[ $nk ];

			if ( self::is_multi_choice_value( $raw_val_full ) ) {
				$allowed = self::split_param_tokens( $raw_val_full );
				$lowered = array_map( array( Marketplace_Text::class, 'lower' ), $allowed );

				if ( ! in_array( $vv_clean, $allowed, true ) && ! in_array( Marketplace_Text::lower( $vv_clean ), $lowered, true ) ) {
					continue;
				}
			}

			$baseline_val = Marketplace_Text::lower( Marketplace_Text::clean( $baseline[ $nk ] ?? '' ) );

			if ( Marketplace_Text::lower( Marketplace_Text::clean( $vv_clean ) ) === $baseline_val ) {
				continue;
			}

			$remember( Marketplace_Text::clean( $pname_raw_orig ), $vv_clean );
		}

		// Inferred size.
		if ( $size_hint ) {
			list( $sname_orig, $ssize ) = $size_hint;

			$nk = self::normalized_attr_key( $sname_orig );

			if ( ! isset( $seen[ $nk ] ) && isset( $parent_map[ $nk ] ) ) {
				if ( Marketplace_Text::lower( Marketplace_Text::clean( $ssize ) ) !== Marketplace_Text::lower( Marketplace_Text::clean( $baseline[ $nk ] ?? '' ) ) ) {
					$remember( $parent_map[ $nk ][0], $ssize );
				}
			}
		}

		// Everything matched the main offer: keep at least the inferred size.
		if ( ! $collected && $size_hint ) {
			list( $sname_orig, $ssize ) = $size_hint;

			$nk = self::normalized_attr_key( $sname_orig );

			if ( ! isset( $seen[ $nk ] ) && isset( $parent_raw_vals[ $nk ] ) && '' !== Marketplace_Text::clean( $ssize ) ) {
				$remember( $parent_raw_vals[ $nk ][0], $ssize );
			}
		}

		if ( ! $collected ) {
			foreach ( self::collect_params( $var_row, true ) as $pair ) {
				list( $vn, $vv ) = $pair;

				$nk = self::normalized_attr_key( $vn );

				if ( isset( $seen[ $nk ] ) || ! isset( $parent_map[ $nk ] ) || ! isset( $parent_raw_vals[ $nk ] ) ) {
					continue;
				}

				$vv_clean = self::normalize_param_value( $vv );

				if ( '' === $vv_clean ) {
					continue;
				}

				list( $pname_raw_orig, $raw_val_full ) = $parent_raw_vals[ $nk ];

				if ( self::is_multi_choice_value( $raw_val_full ) ) {
					$allowed = self::split_param_tokens( $raw_val_full );
					$lowered = array_map( array( Marketplace_Text::class, 'lower' ), $allowed );

					if ( ! in_array( $vv_clean, $allowed, true ) && ! in_array( Marketplace_Text::lower( $vv_clean ), $lowered, true ) ) {
						continue;
					}
				}

				$remember( Marketplace_Text::clean( $pname_raw_orig ), $vv_clean );
				break;
			}
		}

		return self::cap_dimensions_for_prom( $collected, self::MAX_DIMENSIONS );
	}

	/**
	 * Aligned params when the group has axes, otherwise the per-variation fallback.
	 *
	 * @param array<string,string>                 $parent_row Parent row.
	 * @param array<string,string>                 $var_row    Variation row.
	 * @param array{0:string,1:string}|null        $size_hint  Inferred size.
	 * @param array<int, array{0:string,1:string}> $axis_list Group axes.
	 * @param int                                  $sibling_fallback_index Index among siblings.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function params_pick_for_variation( array $parent_row, array $var_row, $size_hint, array $axis_list, int $sibling_fallback_index = 0 ): array {
		if ( $axis_list ) {
			$lined = self::params_aligned_for_group( $parent_row, $var_row, $size_hint, $axis_list, $sibling_fallback_index );

			if ( $lined ) {
				return $lined;
			}
		}

		return self::params_for_variation( $parent_row, $var_row, $size_hint );
	}

	// ------------------------------------------------------------------
	// Group-level logic
	// ------------------------------------------------------------------

	/**
	 * Size of every variation of a parent (from the title, else by position within a colour).
	 *
	 * @param array<string,string>             $parent_row      Parent row.
	 * @param array<int, array<string,string>> $children_sorted Variation rows.
	 * @return array<string, array{0:string,1:string}> variation id => [size name, size].
	 */
	public static function infer_size_hints( array $parent_row, array $children_sorted ): array {
		list( $size_name_orig, $sorted_sizes ) = self::extract_size_axis_from_parent( self::collect_params( $parent_row, true ) );

		$hints = array();

		if ( null === $size_name_orig || ! $sorted_sizes ) {
			return $hints;
		}

		$by_color = array();

		foreach ( self::sort_rows_by_id( $children_sorted ) as $ch ) {
			$c = self::extract_color_value( $ch );
			$k = ( null !== $c && '' !== $c ) ? $c : '__no_color__';

			$by_color[ $k ][] = $ch;
		}

		$low_map = array();
		foreach ( $sorted_sizes as $t ) {
			$low_map[ Marketplace_Text::lower( $t ) ] = $t;
		}

		foreach ( $by_color as $grp ) {
			$grp_sorted = self::sort_rows_by_id( $grp );

			foreach ( $grp_sorted as $idx => $vr ) {
				$vid = Marketplace_Text::clean( $vr['ID'] ?? '' );

				if ( '' === $vid ) {
					continue;
				}

				$nm    = Marketplace_Text::clean( $vr['Name'] ?? '' );
				$canon = null;

				$eu = self::parse_eu_shoe_size_from_title( $nm );

				if ( null !== $eu ) {
					foreach ( $sorted_sizes as $t ) {
						$ts = trim( $t );

						if ( $ts === $eu || ltrim( $ts, '0' ) === ltrim( $eu, '0' ) ) {
							$canon = $t;
							break;
						}
					}

					if ( null === $canon && isset( $low_map[ Marketplace_Text::lower( $eu ) ] ) ) {
						$canon = $low_map[ Marketplace_Text::lower( $eu ) ];
					}
				}

				if ( null === $canon ) {
					$suf = self::parse_clothing_size_suffix_from_title( $nm );

					if ( null !== $suf ) {
						foreach ( $sorted_sizes as $t ) {
							$tn = str_replace( ' ', '', Marketplace_Text::upper( self::trim_size_token( $t ) ) );

							if ( $tn === $suf || $tn === Marketplace_Text::upper( $suf ) || Marketplace_Text::lower( $t ) === Marketplace_Text::lower( $suf ) ) {
								$canon = $t;
								break;
							}
						}
					}
				}

				if ( null === $canon ) {
					$canon = $sorted_sizes[ $idx % count( $sorted_sizes ) ];
				}

				$hints[ $vid ] = array( $size_name_orig, $canon );
			}
		}

		return $hints;
	}

	/**
	 * Rows ordered by numeric id (stable).
	 *
	 * @param array<int, array<string,string>> $rows Rows.
	 * @return array<int, array<string,string>>
	 */
	public static function sort_rows_by_id( array $rows ): array {
		return Marketplace_Text::sorted_by(
			$rows,
			static function ( $r ) {
				return array( self::row_id_int( $r ) );
			}
		);
	}

	/**
	 * Replace values of $base with $overrides (matched by normalized key), appending new ones.
	 *
	 * @param array<int, array{0:string,1:string}> $base      Base params.
	 * @param array<int, array{0:string,1:string}> $overrides Overrides.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function override_params( array $base, array $overrides ): array {
		$out   = array_values( $base );
		$index = array();

		foreach ( $out as $i => $pair ) {
			$index[ self::normalized_attr_key( $pair[0] ) ] = $i;
		}

		foreach ( $overrides as $pair ) {
			$k = self::normalized_attr_key( $pair[0] );

			if ( isset( $index[ $k ] ) ) {
				$out[ $index[ $k ] ] = array( $out[ $index[ $k ] ][0], $pair[1] );
			} else {
				$index[ $k ] = count( $out );
				$out[]       = array( $pair[0], $pair[1] );
			}
		}

		return $out;
	}

	/**
	 * Index of the variation whose params equal the main offer's (Prom sees it as a duplicate).
	 *
	 * @param array<int, array{0:string,1:array<string,string>,2:array}> $entries     Entries [vid, row, params].
	 * @param array<int, array{0:string,1:string}>                       $main_params Main params.
	 * @return int|null
	 */
	public static function pick_main_variation_index( array $entries, array $main_params ) {
		$baseline = array();

		foreach ( $main_params as $pair ) {
			$baseline[ self::normalized_attr_key( $pair[0] ) ] = Marketplace_Text::lower( Marketplace_Text::clean( $pair[1] ) );
		}

		foreach ( $entries as $i => $entry ) {
			$pv = $entry[2];

			if ( ! $pv ) {
				continue;
			}

			$all = true;

			foreach ( $pv as $pair ) {
				$nk = self::normalized_attr_key( $pair[0] );

				if ( ! array_key_exists( $nk, $baseline ) || $baseline[ $nk ] !== Marketplace_Text::lower( Marketplace_Text::clean( $pair[1] ) ) ) {
					$all = false;
					break;
				}
			}

			if ( $all ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Whether two members of a group end up with the same effective params.
	 *
	 * @param array<int, array{0:string,1:string}>             $main_params  Main params.
	 * @param array<int, array<int, array{0:string,1:string}>> $group_params Params of each member.
	 * @return bool
	 */
	public static function has_duplicate_param_sets( array $main_params, array $group_params ): bool {
		$base = array();

		foreach ( $main_params as $pair ) {
			$base[ self::normalized_attr_key( $pair[0] ) ] = Marketplace_Text::lower( Marketplace_Text::clean( $pair[1] ) );
		}

		$seen = array();

		foreach ( $group_params as $params ) {
			$eff = $base;

			foreach ( $params as $pair ) {
				$eff[ self::normalized_attr_key( $pair[0] ) ] = Marketplace_Text::lower( Marketplace_Text::clean( $pair[1] ) );
			}

			$items = array();
			foreach ( $eff as $k => $v ) {
				$items[] = $k . "\x1e" . $v;
			}
			sort( $items );
			$sig = implode( "\x1f", $items );

			if ( isset( $seen[ $sig ] ) ) {
				return true;
			}

			$seen[ $sig ] = true;
		}

		return false;
	}

	/**
	 * Break up identical param sets inside a group by borrowing a parent axis (in place).
	 *
	 * @param array<string,string>|null $parent_row Parent row.
	 * @param array                     $entries    Entries [vid, row, params] (by reference).
	 * @return void
	 */
	public static function dedupe_variation_param_sets_for_group( $parent_row, array &$entries ): void {
		if ( null === $parent_row || count( $entries ) < 2 ) {
			return;
		}

		$buckets = array();

		foreach ( $entries as $i => $entry ) {
			$items = array();

			foreach ( $entry[2] as $pair ) {
				$items[] = Marketplace_Text::lower( trim( $pair[0] ) ) . "\x1e" . Marketplace_Text::lower( trim( $pair[1] ) );
			}

			$items = array_values( array_unique( $items ) );
			sort( $items );

			$buckets[ implode( "\x1f", $items ) ][] = $i;
		}

		$bonuses = self::bonus_multi_axes_for_parent( $parent_row );

		list( $size_name_orig, $size_tokens ) = array( null, array() );

		list( $sn, $stoks ) = self::extract_size_axis_from_parent( self::collect_params( $parent_row, true ) );

		if ( null !== $sn && count( $stoks ) > 1 ) {
			$size_name_orig = $sn;
			$size_tokens    = array_values( $stoks );
		}

		foreach ( $buckets as $indices ) {
			if ( count( $indices ) <= 1 ) {
				continue;
			}

			$used_bonus = array();

			foreach ( array_values( $indices ) as $j => $ti ) {
				list( $vid, $ch, $pv ) = $entries[ $ti ];

				$pv_list = $pv;
				$nk_have = array();

				foreach ( $pv_list as $pair ) {
					$nk_have[ self::normalized_attr_key( $pair[0] ) ] = true;
				}

				$placed = false;
				$ord_j  = $j + self::row_id_int( $ch );

				foreach ( $bonuses as $bonus ) {
					list( $b_pname, $b_toks ) = $bonus;

					$bnk = self::normalized_attr_key( $b_pname );

					if ( isset( $nk_have[ $bnk ] ) ) {
						continue;
					}

					$k                  = $used_bonus[ $bnk ] ?? 0;
					$used_bonus[ $bnk ] = $k + 1;
					$pick               = $b_toks[ ( $ord_j + $k ) % count( $b_toks ) ];
					$pv_list[]          = array( $b_pname, $pick );
					$placed             = true;
					break;
				}

				if ( ! $placed && null !== $size_name_orig && count( $size_tokens ) > 1 ) {
					$snk = self::normalized_attr_key( $size_name_orig );

					if ( ! isset( $nk_have[ $snk ] ) ) {
						$pick      = $size_tokens[ ( $ord_j + $j ) % count( $size_tokens ) ];
						$pv_list[] = array( Marketplace_Text::clean( $size_name_orig ), $pick );
						$placed    = true;
					}
				}

				if ( $placed ) {
					$entries[ $ti ] = array( $vid, $ch, self::cap_dimensions_for_prom( $pv_list, self::MAX_DIMENSIONS ) );
				}
			}
		}
	}
}
