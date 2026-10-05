<?php
/**
 * Text / number helpers shared by the marketplace feed builders.
 *
 * Behavior mirrors the Python tool this module was ported from (str.strip(),
 * str.lower(), float(), html.escape() …) so feeds stay byte-compatible.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Static string helpers.
 */
class Marketplace_Text {

	/**
	 * Python-style str.strip(): also trims NBSP and other Unicode spaces.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean( $value ): string {
		$value = (string) $value;
		$ws    = '[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]';
		$out   = preg_replace( '/^' . $ws . '+|' . $ws . '+$/u', '', $value );

		return is_string( $out ) ? $out : trim( $value );
	}

	/**
	 * Unicode lower-casing (Python str.lower()).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function lower( $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value, 'UTF-8' ) : strtolower( (string) $value );
	}

	/**
	 * Unicode upper-casing (Python str.upper()).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function upper( $value ): string {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $value, 'UTF-8' ) : strtoupper( (string) $value );
	}

	/**
	 * Flag-style booleans from export columns.
	 *
	 * @param mixed $value Raw cell.
	 * @return bool
	 */
	public static function parse_bool( $value ): bool {
		return in_array( self::lower( self::clean( $value ) ), array( '1', 'true', 'yes' ), true );
	}

	/**
	 * Parse a float, accepting a decimal comma. Null when not numeric.
	 *
	 * @param mixed $value Raw cell.
	 * @return float|null
	 */
	public static function parse_float( $value ) {
		$raw = str_replace( ',', '.', self::clean( $value ) );

		if ( '' === $raw ) {
			return null;
		}

		if ( ! preg_match( '/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $raw ) ) {
			return null;
		}

		return (float) $raw;
	}

	/**
	 * Split on commas that are not escaped with a backslash; drop empty parts.
	 *
	 * @param mixed $value Raw cell.
	 * @return array<int, string>
	 */
	public static function split_escaped( $value ): array {
		$raw = self::clean( $value );

		if ( '' === $raw ) {
			return array();
		}

		$parts = preg_split( '/(?<!\\\\),/', $raw );
		$out   = array();

		foreach ( (array) $parts as $part ) {
			$part = (string) $part;

			if ( '' === self::clean( $part ) ) {
				continue;
			}

			$out[] = self::clean( str_replace( '\\,', ',', $part ) );
		}

		return $out;
	}

	/**
	 * First N picture URLs of a comma-separated cell.
	 *
	 * @param mixed $value Raw cell.
	 * @param int   $limit Max pictures.
	 * @return array<int, string>
	 */
	public static function parse_image_list( $value, int $limit ): array {
		return array_slice( self::split_escaped( $value ), 0, max( 0, $limit ) );
	}

	/**
	 * Category cell "A > B, C" into [['A','B'],['C']].
	 *
	 * @param mixed $value Raw cell.
	 * @return array<int, array<int, string>>
	 */
	public static function parse_category_paths( $value ): array {
		$paths = array();

		foreach ( self::split_escaped( $value ) as $entry ) {
			$segments = array();

			foreach ( explode( '>', $entry ) as $segment ) {
				$segment = self::clean( $segment );

				if ( '' !== $segment ) {
					$segments[] = $segment;
				}
			}

			if ( $segments ) {
				$paths[] = $segments;
			}
		}

		return $paths;
	}

	/**
	 * Python html.escape(s, quote=True).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function html_escape( $value ): string {
		return str_replace(
			array( '&', '<', '>', '"', "'" ),
			array( '&amp;', '&lt;', '&gt;', '&quot;', '&#x27;' ),
			(string) $value
		);
	}

	/**
	 * Remove tags (Python re.sub(r"<[^>]+>", " ")).
	 *
	 * @param string $value HTML.
	 * @return string
	 */
	public static function strip_tags_to_space( $value ): string {
		return (string) preg_replace( '/<[^>]+>/', ' ', (string) $value );
	}

	/**
	 * Escape text for XML character data (ElementTree compatible: & < >).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function xml_text( $value ): string {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), self::xml_safe( $value ) );
	}

	/**
	 * Escape text for an XML attribute value.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function xml_attr( $value ): string {
		return str_replace(
			array( '&', '<', '>', '"', "\n", "\r", "\t" ),
			array( '&amp;', '&lt;', '&gt;', '&quot;', '&#10;', '&#13;', '&#9;' ),
			self::xml_safe( $value )
		);
	}

	/**
	 * Drop characters that are illegal in XML 1.0 (feeds with one would be rejected).
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public static function xml_safe( $value ): string {
		$value = (string) $value;
		$out   = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value );

		return is_string( $out ) ? $out : $value;
	}

	/**
	 * Stable sort by a computed key (PHP < 8 usort is not stable).
	 *
	 * Keys are arrays of ints/strings compared element by element, strings by
	 * code point (byte order of valid UTF-8), like Python tuple comparison.
	 *
	 * @param array<int, mixed> $items   Items (re-indexed).
	 * @param callable          $key_fn  item => array key.
	 * @return array<int, mixed>
	 */
	public static function sorted_by( array $items, callable $key_fn ): array {
		$decorated = array();
		$i         = 0;

		foreach ( array_values( $items ) as $item ) {
			$decorated[] = array( (array) $key_fn( $item ), $i++, $item );
		}

		usort(
			$decorated,
			static function ( $a, $b ) {
				$n = max( count( $a[0] ), count( $b[0] ) );

				for ( $k = 0; $k < $n; $k++ ) {
					if ( ! array_key_exists( $k, $a[0] ) ) {
						return -1;
					}
					if ( ! array_key_exists( $k, $b[0] ) ) {
						return 1;
					}

					$x = $a[0][ $k ];
					$y = $b[0][ $k ];

					if ( is_string( $x ) || is_string( $y ) ) {
						$cmp = strcmp( (string) $x, (string) $y );
					} else {
						$cmp = $x <=> $y;
					}

					if ( 0 !== $cmp ) {
						return $cmp < 0 ? -1 : 1;
					}
				}

				return $a[1] <=> $b[1];
			}
		);

		return array_map(
			static function ( $row ) {
				return $row[2];
			},
			$decorated
		);
	}

	/**
	 * Python str.zfill().
	 *
	 * @param string $value Text.
	 * @param int    $width Width.
	 * @return string
	 */
	public static function zfill( $value, int $width ): string {
		return str_pad( (string) $value, $width, '0', STR_PAD_LEFT );
	}

	/**
	 * Number as Prom expects it: integer without decimals, else two decimals.
	 *
	 * @param float $value Amount.
	 * @return string
	 */
	public static function money( float $value ): string {
		if ( floor( $value ) === $value ) {
			return (string) (int) $value;
		}

		return number_format( $value, 2, '.', '' );
	}
}
