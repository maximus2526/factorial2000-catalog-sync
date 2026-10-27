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
	 * Example products kept per skip reason for the admin report.
	 */
	const MAX_EXAMPLES = 5;

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
	 * @return array{xml:string,exported:int,skipped:int,excluded:array<int,string>,used_ids:array<int,string>,reasons?:array<string,array{count:int,examples:array<int,string>}>}
	 */
	abstract public function process_unit( array $unit, Marketplace_Category_Tree $tree, array $settings ): array;

	/**
	 * Everything written before the first offer.
	 *
	 * The default is the YML layout of Prom: head, the category list and the opening of <offers>.
	 * A feed with another layout overrides it.
	 *
	 * @param array<string,mixed>       $settings Feed settings.
	 * @param Marketplace_Category_Tree $tree     Category tree collected during the build.
	 * @return string
	 */
	public function document_head( array $settings, Marketplace_Category_Tree $tree ): string {
		return $this->head( $settings ) . "    <categories>\n" . $tree->to_xml( '      ' ) . "    </categories>\n    <offers>\n";
	}

	/**
	 * Start of the line that opens an offer (the job cuts a big feed into files there).
	 *
	 * @return string
	 */
	public function offer_line_prefix(): string {
		return '      <offer';
	}

	/**
	 * Group an offer block belongs to; the blocks of one group are never split between files.
	 *
	 * @param string $block One offer, from its opening tag.
	 * @return string Group id or ''.
	 */
	public function offer_group_id( string $block ): string {
		return preg_match( '/^\s*<offer[^>]*\sgroup_id="([^"]*)"/', $block, $m ) ? $m[1] : '';
	}

	/**
	 * Human labels of the skip reasons a feed reports in process_unit()['reasons'].
	 *
	 * @return array<string,string>
	 */
	public function skip_reason_labels(): array {
		return array(
			'no_name'     => __( 'немає назви', 'factorial2000-catalog-sync' ),
			'no_price'    => __( 'немає ціни', 'factorial2000-catalog-sync' ),
			'no_pictures' => __( 'немає фото', 'factorial2000-catalog-sync' ),
			'no_category' => __( 'немає категорії', 'factorial2000-catalog-sync' ),
			'no_id'       => __( 'немає ID', 'factorial2000-catalog-sync' ),
			'unpublished' => __( 'не опубліковано', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Add skip reasons to running totals: counts are summed, a few distinct examples kept.
	 *
	 * @param array<string,array{count:int,examples:array<int,string>}> $total Totals.
	 * @param array<string,array{count:int,examples:array<int,string>}> $add   Reasons to add.
	 * @return array<string,array{count:int,examples:array<int,string>}>
	 */
	public static function merge_reasons( array $total, array $add ): array {
		foreach ( $add as $reason => $info ) {
			$reason = (string) $reason;

			if ( ! isset( $total[ $reason ] ) ) {
				$total[ $reason ] = array(
					'count'    => 0,
					'examples' => array(),
				);
			}

			$total[ $reason ]['count'] += (int) ( $info['count'] ?? 0 );

			foreach ( (array) ( $info['examples'] ?? array() ) as $example ) {
				if ( count( $total[ $reason ]['examples'] ) >= self::MAX_EXAMPLES ) {
					break;
				}

				if ( ! in_array( (string) $example, $total[ $reason ]['examples'], true ) ) {
					$total[ $reason ]['examples'][] = (string) $example;
				}
			}
		}

		return $total;
	}

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
	 * A unit without sale prices when the feed sends the regular price only.
	 *
	 * The sale price is dropped from every row, so the feed sees no discount:
	 * no old price, no sale price, the price is the regular one.
	 *
	 * @param array<string,mixed> $unit     Unit (simple, lone or group).
	 * @param array<string,mixed> $settings Feed settings.
	 * @return array<string,mixed>
	 */
	protected static function without_sale_prices( array $unit, array $settings ): array {
		if ( '1' !== (string) ( $settings['regular_price_only'] ?? '0' ) ) {
			return $unit;
		}

		$strip = static function ( $row ) {
			if ( is_array( $row ) ) {
				$row['Sale price'] = '';
			}

			return $row;
		};

		foreach ( array( 'row', 'parent' ) as $key ) {
			if ( isset( $unit[ $key ] ) ) {
				$unit[ $key ] = $strip( $unit[ $key ] );
			}
		}

		if ( isset( $unit['children'] ) && is_array( $unit['children'] ) ) {
			$unit['children'] = array_map( $strip, $unit['children'] );
		}

		return $unit;
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
				case 'category_map':
					$out[ $key ] = null === $value ? (string) $default : $this->sanitize_multiline( (string) $value );
					break;

				case 'select':
					$allowed     = array_map( 'strval', array_keys( (array) ( $field['options'] ?? array() ) ) );
					$out[ $key ] = in_array( (string) $value, $allowed, true ) ? (string) $value : (string) $default;
					break;

				case 'prom_category':
					$out[ $key ] = null === $value ? (string) $default : Prom_Categories::sanitize_id( $value );
					break;

				case 'url':
					$out[ $key ] = null === $value ? (string) $default : esc_url_raw( trim( (string) $value ) );
					break;

				case 'preset':
					$out[ $key ] = $this->sanitize_preset( $key, $field, $raw );
					break;

				case 'pairs':
					$rows        = $raw['__rows'][ $key ] ?? null;
					$out[ $key ] = is_array( $rows ) ? $this->sanitize_multiline( Marketplace_Form::rows_to_text( $rows ) ) : ( null === $value ? (string) $default : $this->sanitize_multiline( (string) $value ) );
					break;

				default:
					$out[ $key ] = null === $value ? (string) $default : sanitize_text_field( (string) $value );
			}
		}

		return $out;
	}

	/**
	 * Section titles of the settings form (group key => label), in display order.
	 *
	 * @return array<string,string>
	 */
	public function groups(): array {
		return array(
			'basic' => __( 'Основне', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Final value of a preset field: the chosen preset, or the custom text.
	 *
	 * @param string              $key   Field key.
	 * @param array<string,mixed> $field Field schema.
	 * @param array<string,mixed> $raw   Raw input (custom texts live under __custom).
	 * @return string
	 */
	protected function sanitize_preset( string $key, array $field, array $raw ): string {
		$default = (string) ( $field['default'] ?? '' );
		$options = (array) ( $field['options'] ?? array() );
		$select  = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : $default;
		$custom  = $field['custom'] ?? array();

		if ( Marketplace_Form::CUSTOM !== $select ) {
			return array_key_exists( $select, $options ) ? $select : $default;
		}

		$text = (string) ( $raw['__custom'][ $key ] ?? '' );

		if ( 'number' === ( $custom['type'] ?? 'text' ) ) {
			$num = is_numeric( $text ) ? (int) $text : (int) $default;

			return (string) max( (int) ( $custom['min'] ?? 0 ), min( (int) ( $custom['max'] ?? PHP_INT_MAX ), $num ) );
		}

		$text = sanitize_text_field( $text );

		return '' === $text ? $default : $text;
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
