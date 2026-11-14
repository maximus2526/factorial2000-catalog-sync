<?php
/**
 * Prom.ua category characteristic values (bundled data).
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Search official values for a Prom category.
 */
class Prom_Values {

	/**
	 * File with the value list.
	 *
	 * @return string
	 */
	public static function file(): string {
		/**
		 * Filters the file with Prom characteristic values.
		 *
		 * @param string $file Absolute path.
		 */
		return (string) apply_filters( 'f2000cs_prom_values_file', __DIR__ . '/data/prom-values.tsv' );
	}

	/**
	 * All rows: category_id => list of values.
	 *
	 * @return array<string, array<int, array{value_id:string,attribute_id:string,attribute_uk:string,attribute_ru:string,value_uk:string,value_ru:string}>>
	 */
	public static function all(): array {
		static $data = null;

		if ( null !== $data ) {
			return $data;
		}

		$data = array();
		$file = self::file();

		if ( ! is_readable( $file ) ) {
			return $data;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file -- Bundled read-only data file.
		foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}

			$parts = explode( "\t", $line );

			if ( count( $parts ) < 7 ) {
				continue;
			}

			// Values come from a scraped list; a stray carriage return would reach
			// the Prom feed and break the official value match. Normalise it here.
			$parts = array_map(
				static function ( $part ) {
					return trim( str_replace( "\r", ' ', (string) $part ) );
				},
				$parts
			);

			list( $category_id, $attribute_id, $attribute_uk, $attribute_ru, $value_id, $value_uk, $value_ru ) = $parts;

			if ( '' === $category_id || '' === $value_id || ( '' === $value_uk && '' === $value_ru ) ) {
				continue;
			}

			$data[ $category_id ][] = array(
				'value_id'     => $value_id,
				'attribute_id' => $attribute_id,
				'attribute_uk' => $attribute_uk,
				'attribute_ru' => $attribute_ru,
				'value_uk'     => $value_uk,
				'value_ru'     => $value_ru,
			);
		}

		return $data;
	}

	/**
	 * Whether a value is an official value of a category attribute.
	 *
	 * Unknown categories and attributes return null: the bundled list may be
	 * older than Prom, so absence there must not veto a mapped value.
	 *
	 * @param string $category_id Prom category id.
	 * @param string $attribute   Official attribute name.
	 * @param string $value       Value to check.
	 * @return bool|null
	 */
	public static function is_official( string $category_id, string $attribute, string $value ): ?bool {
		$category_id = Prom_Categories::sanitize_id( $category_id );
		$attribute   = trim( $attribute );
		$value       = trim( $value );

		if ( '' === $category_id || '' === $attribute || '' === $value ) {
			return null;
		}

		$data = self::all();

		if ( ! isset( $data[ $category_id ] ) ) {
			return null;
		}

		$known = false;

		foreach ( $data[ $category_id ] as $row ) {
			if ( self::same( $row['attribute_uk'], $attribute ) || self::same( $row['attribute_ru'], $attribute ) ) {
				$known = true;

				if ( self::same( $row['value_uk'], $value ) || self::same( $row['value_ru'], $value ) ) {
					return true;
				}
			}
		}

		return $known ? false : null;
	}

	/**
	 * Case-insensitive comparison of official names.
	 *
	 * @param string $a First string.
	 * @param string $b Second string.
	 * @return bool
	 */
	private static function same( string $a, string $b ): bool {
		return '' !== trim( $a ) && Marketplace_Text::lower( trim( $a ) ) === Marketplace_Text::lower( trim( $b ) );
	}

	/**
	 * Search values in one Prom category.
	 *
	 * @param string $category_id Prom category id.
	 * @param string $query       Search text.
	 * @param int    $limit       Maximum results.
	 * @return array<int, array{id:string,text:string,value:string,value_uk:string,value_ru:string}>
	 */
	public static function search( string $category_id, string $query = '', int $limit = 30 ): array {
		$category_id = Prom_Categories::sanitize_id( $category_id );
		$query       = Marketplace_Text::lower( Marketplace_Text::clean( $query ) );

		if ( '' === $category_id ) {
			return array();
		}

		$rows = self::all()[ $category_id ] ?? array();
		$out  = array();

		foreach ( $rows as $row ) {
			$value_uk = (string) $row['value_uk'];
			$value_ru = (string) $row['value_ru'];
			$label    = '' !== $value_uk ? $value_uk : $value_ru;
			$haystack = Marketplace_Text::lower( $value_uk . ' ' . $value_ru . ' ' . $row['attribute_uk'] . ' ' . $row['attribute_ru'] . ' ' . $row['value_id'] );

			if ( '' !== $query && false === strpos( $haystack, $query ) ) {
				continue;
			}

			$out[] = array(
				'id'       => (string) $row['value_id'],
				'text'     => (string) $row['attribute_uk'] . ': ' . $label,
				'value'    => $label,
				'value_uk' => $value_uk,
				'value_ru' => $value_ru,
			);

			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}
}
