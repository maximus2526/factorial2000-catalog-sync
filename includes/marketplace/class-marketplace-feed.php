<?php
/**
 * Base class of a marketplace feed (Prom.ua today; Rozetka, Hotline … later).
 *
 * A feed turns "units" read from WooCommerce into XML. The surrounding
 * machinery (chunked builds, storage, scheduling, admin UI) lives in
 * Marketplace_Job / the admin page and works for every registered feed.
 *
 * A unit is one of:
 *  - array( 'kind' => 'group',  'parent' => row|null, 'children' => rows )  variable product
 *  - array( 'kind' => 'lone',   'row' => row, 'parent' => row|null )         variation without a group
 *  - array( 'kind' => 'simple', 'row' => row )                               simple product
 * where a row is an associative array keyed like the WooCommerce product CSV export.
 *
 * To add a marketplace: extend this class, implement the abstract methods and
 * register it with Marketplace_Registry::register() (or the f2000cs_marketplace_feeds filter).
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract feed.
 */
abstract class Marketplace_Feed {

	/**
	 * Stable machine id ("prom").
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Human label.
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * Settings form schema: key => array( type, label, description, default, ...).
	 *
	 * Types: text, number, checkbox, textarea, select (with 'options').
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract public function fields(): array;

	/**
	 * XML from the declaration up to (and excluding) <categories>.
	 *
	 * @param array<string,mixed> $settings Feed settings.
	 * @return string
	 */
	abstract public function head( array $settings ): string;

	/**
	 * XML after the offers.
	 *
	 * @return string
	 */
	abstract public function tail(): string;

	/**
	 * Convert one unit to offer XML.
	 *
	 * @param array<string,mixed>       $unit     Unit (see class docs).
	 * @param Marketplace_Category_Tree $tree    Category tree (mutated).
	 * @param array<string,mixed>       $settings Feed settings.
	 * @return array{xml:string,exported:int,skipped:int,excluded:array<int,string>,used_ids:array<int,string>}
	 */
	abstract public function process_unit( array $unit, Marketplace_Category_Tree $tree, array $settings ): array;

	/**
	 * File extension of the generated feed.
	 *
	 * @return string
	 */
	public function extension(): string {
		return 'xml';
	}

	/**
	 * Short description shown above the settings form.
	 *
	 * @return string
	 */
	public function description(): string {
		return '';
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public function defaults(): array {
		$out = array();

		foreach ( $this->fields() as $key => $field ) {
			$out[ $key ] = $field['default'] ?? '';
		}

		return $out;
	}

	/**
	 * Settings with defaults filled in.
	 *
	 * @param array<string,mixed> $saved Saved settings.
	 * @return array<string,mixed>
	 */
	public function with_defaults( array $saved ): array {
		return array_merge( $this->defaults(), array_intersect_key( $saved, $this->defaults() ) );
	}

	/**
	 * Sanitize posted settings according to the schema.
	 *
	 * @param array<string,mixed> $raw Raw (unslashed) input.
	 * @return array<string,mixed>
	 */
	public function sanitize( array $raw ): array {
		$out = array();

		foreach ( $this->fields() as $key => $field ) {
			$type    = $field['type'] ?? 'text';
			$default = $field['default'] ?? '';
			$value   = $raw[ $key ] ?? null;

			switch ( $type ) {
				case 'checkbox':
					$out[ $key ] = ( '1' === (string) $value || 'yes' === (string) $value || 'on' === (string) $value || true === $value ) ? '1' : '0';
					break;

				case 'number':
					$num         = is_numeric( $value ) ? (int) $value : (int) $default;
					$min         = isset( $field['min'] ) ? (int) $field['min'] : 0;
					$max         = isset( $field['max'] ) ? (int) $field['max'] : PHP_INT_MAX;
					$out[ $key ] = (string) max( $min, min( $max, $num ) );
					break;

				case 'textarea':
					$out[ $key ] = null === $value ? (string) $default : $this->sanitize_multiline( (string) $value );
					break;

				case 'select':
					$allowed     = array_map( 'strval', array_keys( (array) ( $field['options'] ?? array() ) ) );
					$out[ $key ] = in_array( (string) $value, $allowed, true ) ? (string) $value : (string) $default;
					break;

				case 'url':
					$out[ $key ] = null === $value ? (string) $default : esc_url_raw( trim( (string) $value ) );
					break;

				default:
					$out[ $key ] = null === $value ? (string) $default : sanitize_text_field( (string) $value );
			}
		}

		return $out;
	}

	/**
	 * Sanitize a textarea line by line (keeps line breaks).
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	protected function sanitize_multiline( string $value ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$out   = array();

		foreach ( (array) $lines as $line ) {
			$line = sanitize_text_field( $line );

			if ( '' !== $line ) {
				$out[] = $line;
			}
		}

		return implode( "\n", $out );
	}

	/**
	 * Parse "left=right" lines into a map.
	 *
	 * @param string $text Multi-line text.
	 * @return array<string,string>
	 */
	public static function parse_pairs( string $text ): array {
		$out = array();

		foreach ( (array) preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$pos = strpos( (string) $line, '=' );

			if ( false === $pos ) {
				continue;
			}

			$left  = Marketplace_Text::clean( substr( $line, 0, $pos ) );
			$right = Marketplace_Text::clean( substr( $line, $pos + 1 ) );

			if ( '' !== $left && '' !== $right ) {
				$out[ $left ] = $right;
			}
		}

		return $out;
	}
}
