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
	 * Types: text, number, checkbox, textarea, select (with 'options'),
	 * prom_attribute_map (Prom recommended characteristics).
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
			'excluded'    => __( 'виключено правилами «Не вивантажувати»', 'factorial2000-catalog-sync' ),
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

		foreach ( $this->settings_fields() as $key => $field ) {
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

		foreach ( $this->settings_fields() as $key => $field ) {
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

				case 'prom_attribute_map':
					$out[ $key ] = Prom_Recommendations::sanitize_json( null === $value ? (string) $default : $value );
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

	// ------------------------------------------------------------------
	// Exclusion rules (every feed)
	// ------------------------------------------------------------------

	/**
	 * Settings schema of the feed plus the fields every feed has.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function settings_fields(): array {
		return $this->fields() + array(
			'exclude_rules' => array(
				'group'       => 'exclude',
				'type'        => 'textarea',
				'label'       => __( 'Не вивантажувати товари', 'factorial2000-catalog-sync' ),
				'description' => __( 'По одному правилу в рядку: бренд: Mil-Tec · категорія: Одяг > Куртки (разом з підкатегоріями) · артикул: AR_ (початок артикула) · атрибут: Колір = Червоний · назва: слово з назви · id: 123. Правило, під яке підпадає товар, прибирає його разом з усіма різновидами; правило, під яке підпадає різновид, прибирає лише його. Товари, яких немає у файлі, маркетплейс обробляє за своїми налаштуваннями імпорту.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
		);
	}

	/**
	 * Section titles of the settings form, with the exclusion section.
	 *
	 * @return array<string,string>
	 */
	public function settings_groups(): array {
		return $this->groups() + array( 'exclude' => __( 'Виключення з вигрузки', 'factorial2000-catalog-sync' ) );
	}

	/**
	 * Parse the «type: value» lines of the exclusion setting.
	 *
	 * Types: brand/бренд/виробник, category/категорія, sku/артикул, attribute/атрибут
	 * («Name = Value»), name/назва, id. Unknown lines are ignored.
	 *
	 * @param string $text Rules text.
	 * @return array<int, array<string,string>>
	 */
	public static function parse_exclusion_rules( string $text ): array {
		$aliases = array(
			'brand'     => 'brand',
			'бренд'     => 'brand',
			'виробник'  => 'brand',
			'vendor'    => 'brand',
			'category'  => 'category',
			'категорія' => 'category',
			'категория' => 'category',
			'sku'       => 'sku',
			'артикул'   => 'sku',
			'attribute' => 'attribute',
			'атрибут'   => 'attribute',
			'name'      => 'name',
			'назва'     => 'name',
			'название'  => 'name',
			'id'        => 'id',
		);
		$rules   = array();

		foreach ( (array) preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( (string) $line );
			$at   = strpos( $line, ':' );

			if ( '' === $line || false === $at ) {
				continue;
			}

			$type  = Marketplace_Text::lower( trim( substr( $line, 0, $at ) ) );
			$value = trim( substr( $line, $at + 1 ) );

			if ( ! isset( $aliases[ $type ] ) || '' === $value ) {
				continue;
			}

			$rule = array(
				'type'  => $aliases[ $type ],
				'value' => Marketplace_Text::lower( $value ),
			);

			if ( 'attribute' === $rule['type'] ) {
				$eq = strpos( $value, '=' );

				if ( false === $eq ) {
					continue;
				}

				$rule['attr']  = Marketplace_Text::lower( trim( substr( $value, 0, $eq ) ) );
				$rule['value'] = Marketplace_Text::lower( trim( substr( $value, $eq + 1 ) ) );

				if ( '' === $rule['attr'] || '' === $rule['value'] ) {
					continue;
				}
			}

			if ( 'category' === $rule['type'] ) {
				$rule['value'] = (string) preg_replace( '/\s*>\s*/u', '>', $rule['value'] );
			}

			$rules[] = $rule;
		}

		return $rules;
	}

	/**
	 * Whether a product or variation row falls under any exclusion rule.
	 *
	 * A variation inherits the brand, categories, name and attributes of its product.
	 *
	 * @param array<int, array<string,string>> $rules      Parsed rules.
	 * @param array<string,string>             $row        Row.
	 * @param array<string,string>|null        $parent_row Parent row.
	 * @return bool
	 */
	public static function row_excluded( array $rules, array $row, $parent_row = null ): bool {
		foreach ( $rules as $rule ) {
			switch ( $rule['type'] ) {
				case 'brand':
					$vendor = Marketplace_Text::lower( Prom_Feed::extract_vendor( $row, $parent_row ) );

					foreach ( (array) preg_split( '/\s*,\s*/u', $vendor ) as $part ) {
						if ( '' !== $part && $part === $rule['value'] ) {
							return true;
						}
					}
					break;

				case 'category':
					$cats = Marketplace_Text::clean( $row['Categories'] ?? '' );

					if ( '' === $cats && $parent_row ) {
						$cats = Marketplace_Text::clean( $parent_row['Categories'] ?? '' );
					}

					foreach ( Marketplace_Text::parse_category_paths( $cats ) as $path ) {
						$key = Marketplace_Text::lower( implode( '>', $path ) );

						if ( $key === $rule['value'] || 0 === strpos( $key, $rule['value'] . '>' ) ) {
							return true;
						}
					}
					break;

				case 'sku':
					$sku = Marketplace_Text::lower( Marketplace_Text::clean( $row['SKU'] ?? '' ) );

					if ( '' !== $sku && 0 === strpos( $sku, $rule['value'] ) ) {
						return true;
					}
					break;

				case 'attribute':
					foreach ( array( $row, $parent_row ) as $source ) {
						if ( ! is_array( $source ) ) {
							continue;
						}

						for ( $i = 1; $i <= 40; $i++ ) {
							$name = Marketplace_Text::lower( Marketplace_Text::clean( $source[ "Attribute {$i} name" ] ?? '' ) );

							if ( '' === $name || $name !== $rule['attr'] ) {
								continue;
							}

							$values = array_map( 'trim', explode( ',', Marketplace_Text::lower( (string) ( $source[ "Attribute {$i} value(s)" ] ?? '' ) ) ) );

							if ( in_array( $rule['value'], $values, true ) ) {
								return true;
							}
						}
					}
					break;

				case 'name':
					$name = Marketplace_Text::lower( Marketplace_Text::clean( $row['Name'] ?? '' ) );

					if ( '' === $name && $parent_row ) {
						$name = Marketplace_Text::lower( Marketplace_Text::clean( $parent_row['Name'] ?? '' ) );
					}

					if ( false !== mb_strpos( $name, $rule['value'] ) ) {
						return true;
					}
					break;

				case 'id':
					if ( Marketplace_Text::clean( $row['ID'] ?? '' ) === $rule['value'] ) {
						return true;
					}
					break;
			}
		}

		return false;
	}

	/**
	 * Drop what the exclusion rules forbid from one unit, before the feed sees it.
	 *
	 * A product that falls under a rule goes with all its variations; a variation is
	 * dropped on its own. Dropped offers are reported under the «excluded» reason.
	 *
	 * @param array<string,mixed> $unit     Unit (simple, lone or group).
	 * @param array<string,mixed> $settings Feed settings.
	 * @return array{0:?array,1:int,2:array<string,array{count:int,examples:array<int,string>}>} Unit (null when nothing is left), dropped offers, reasons.
	 */
	public function apply_exclusions( array $unit, array $settings ): array {
		$rules = self::parse_exclusion_rules( (string) ( $settings['exclude_rules'] ?? '' ) );

		if ( ! $rules ) {
			return array( $unit, 0, array() );
		}

		$label  = static function ( $row ): string {
			$row = is_array( $row ) ? $row : array();
			$sku = Marketplace_Text::clean( $row['SKU'] ?? '' );

			return '' !== $sku ? $sku : 'ID ' . Marketplace_Text::clean( $row['ID'] ?? '' );
		};
		$report = static function ( int $count, array $examples ): array {
			return array(
				'excluded' => array(
					'count'    => $count,
					'examples' => array_slice( array_values( array_unique( $examples ) ), 0, 5 ),
				),
			);
		};

		if ( 'group' === ( $unit['kind'] ?? '' ) ) {
			$parent   = is_array( $unit['parent'] ?? null ) ? $unit['parent'] : null;
			$children = (array) ( $unit['children'] ?? array() );

			if ( $parent && self::row_excluded( $rules, $parent, null ) ) {
				$count = max( 1, count( $children ) );

				return array( null, $count, $report( $count, array( $label( $parent ) ) ) );
			}

			$kept     = array();
			$dropped  = 0;
			$examples = array();

			foreach ( $children as $child ) {
				if ( self::row_excluded( $rules, (array) $child, $parent ) ) {
					++$dropped;
					$examples[] = $label( $child );
				} else {
					$kept[] = $child;
				}
			}

			if ( ! $dropped ) {
				return array( $unit, 0, array() );
			}

			if ( ! $kept ) {
				return array( null, $dropped, $report( $dropped, $examples ) );
			}

			$unit['children'] = $kept;

			return array( $unit, $dropped, $report( $dropped, $examples ) );
		}

		$row    = (array) ( $unit['row'] ?? array() );
		$parent = is_array( $unit['parent'] ?? null ) ? $unit['parent'] : null;

		if ( self::row_excluded( $rules, $row, $parent ) || ( $parent && self::row_excluded( $rules, $parent, null ) ) ) {
			return array( null, 1, $report( 1, array( $label( $row ) ) ) );
		}

		return array( $unit, 0, array() );
	}
}
