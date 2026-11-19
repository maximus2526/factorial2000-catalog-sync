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
			'excluded'    => __( 'не пройшло правила відбору товарів', 'factorial2000-catalog-sync' ),
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

				case 'filter_rules':
					$rows        = $raw['__filters'][ $key ] ?? null;
					$out[ $key ] = is_array( $rows ) ? self::filter_rows_to_json( $rows ) : ( null === $value ? (string) $default : self::filter_rows_to_json( self::parse_filter_rules( (string) $value ) ) );
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
	// Product filter rules (every feed)
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
				'type'        => 'filter_rules',
				'label'       => __( 'Правила відбору товарів', 'factorial2000-catalog-sync' ),
				'description' => __( 'Товар не потрапляє у вигрузку, якщо під нього підходить хоч одне правило «Не вивантажувати». Якщо є правила «Вивантажувати лише», у вигрузку йдуть тільки товари, що підходять хоча б під одне з них. Кілька значень через кому — «будь-яке з них». Правило для товару діє на всі його різновиди; правило, під яке підпадає різновид (артикул, атрибут, ціна, наявність), прибирає лише його. Товари, яких немає у файлі, маркетплейс обробляє за своїми налаштуваннями імпорту.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
		);
	}

	/**
	 * Section titles of the settings form, with the product filter section.
	 *
	 * @return array<string,string>
	 */
	public function settings_groups(): array {
		return $this->groups() + array( 'exclude' => __( 'Відбір товарів у вигрузку', 'factorial2000-catalog-sync' ) );
	}

	/**
	 * Fields a rule can test.
	 *
	 * @return array<string,string>
	 */
	public static function filter_fields(): array {
		return array(
			'brand'     => __( 'Бренд', 'factorial2000-catalog-sync' ),
			'category'  => __( 'Категорія', 'factorial2000-catalog-sync' ),
			'sku'       => __( 'Артикул (SKU)', 'factorial2000-catalog-sync' ),
			'name'      => __( 'Назва', 'factorial2000-catalog-sync' ),
			'attribute' => __( 'Атрибут', 'factorial2000-catalog-sync' ),
			'tag'       => __( 'Мітка', 'factorial2000-catalog-sync' ),
			'price'     => __( 'Ціна', 'factorial2000-catalog-sync' ),
			'stock'     => __( 'Наявність', 'factorial2000-catalog-sync' ),
			'id'        => __( 'ID товару', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Conditions of a rule.
	 *
	 * @return array<string,string>
	 */
	public static function filter_operators(): array {
		return array(
			'is'           => __( 'дорівнює', 'factorial2000-catalog-sync' ),
			'is_not'       => __( 'не дорівнює', 'factorial2000-catalog-sync' ),
			'contains'     => __( 'містить', 'factorial2000-catalog-sync' ),
			'not_contains' => __( 'не містить', 'factorial2000-catalog-sync' ),
			'starts_with'  => __( 'починається з', 'factorial2000-catalog-sync' ),
			'gt'           => __( 'більше ніж', 'factorial2000-catalog-sync' ),
			'lt'           => __( 'менше ніж', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Actions of a rule.
	 *
	 * @return array<string,string>
	 */
	public static function filter_actions(): array {
		return array(
			'exclude' => __( 'Не вивантажувати', 'factorial2000-catalog-sync' ),
			'include' => __( 'Вивантажувати лише', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * One clean rule, or null when it is incomplete.
	 *
	 * @param mixed $raw Rule array (action, field, attr, op, value).
	 * @return array{action:string,field:string,attr:string,op:string,value:string}|null
	 */
	public static function normalize_filter_rule( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$action = (string) ( $raw['action'] ?? 'exclude' );
		$field  = (string) ( $raw['field'] ?? '' );
		$op     = (string) ( $raw['op'] ?? 'is' );
		$attr   = Marketplace_Text::clean( str_replace( array( "\r", "\n" ), ' ', (string) ( $raw['attr'] ?? '' ) ) );
		$value  = Marketplace_Text::clean( str_replace( array( "\r", "\n" ), ' ', (string) ( $raw['value'] ?? '' ) ) );

		if ( ! isset( self::filter_actions()[ $action ], self::filter_fields()[ $field ], self::filter_operators()[ $op ] ) ) {
			return null;
		}

		if ( 'stock' === $field ) {
			// The form posts the stock choice from its own select.
			$value = isset( $raw['stock'] ) ? (string) $raw['stock'] : $value;
			$value = in_array( $value, array( 'instock', 'outofstock' ), true ) ? $value : 'outofstock';
			$op    = 'is_not' === $op ? 'is_not' : 'is';
		}

		if ( '' === $value || ( 'attribute' === $field && '' === $attr ) ) {
			return null;
		}

		if ( in_array( $op, array( 'gt', 'lt' ), true ) && ( 'price' !== $field || ! is_numeric( str_replace( ',', '.', $value ) ) ) ) {
			return null;
		}

		return array(
			'action' => $action,
			'field'  => $field,
			'attr'   => 'attribute' === $field ? $attr : '',
			'op'     => $op,
			'value'  => $value,
		);
	}

	/**
	 * Stored rules (JSON written by the form, or the older «type: value» lines).
	 *
	 * @param string $stored Stored setting.
	 * @return array<int, array{action:string,field:string,attr:string,op:string,value:string}>
	 */
	public static function parse_filter_rules( string $stored ): array {
		$stored = trim( $stored );

		if ( '' === $stored ) {
			return array();
		}

		$decoded = json_decode( $stored, true );
		$rules   = array();

		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $raw ) {
				$rule = self::normalize_filter_rule( $raw );

				if ( null !== $rule ) {
					$rules[] = $rule;
				}
			}

			return $rules;
		}

		// Older text form: «бренд: Mil-Tec», «артикул: AR_», «атрибут: Колір = Червоний» …
		$aliases = array(
			'brand'     => 'brand',
			'бренд'     => 'brand',
			'виробник'  => 'brand',
			'category'  => 'category',
			'категорія' => 'category',
			'sku'       => 'sku',
			'артикул'   => 'sku',
			'attribute' => 'attribute',
			'атрибут'   => 'attribute',
			'name'      => 'name',
			'назва'     => 'name',
			'id'        => 'id',
		);
		$ops     = array(
			'sku'  => 'starts_with',
			'name' => 'contains',
		);

		foreach ( (array) preg_split( '/\r\n|\r|\n/', $stored ) as $line ) {
			$line = trim( (string) $line );
			$at   = strpos( $line, ':' );

			if ( false === $at ) {
				continue;
			}

			$type  = $aliases[ Marketplace_Text::lower( trim( substr( $line, 0, $at ) ) ) ] ?? '';
			$value = trim( substr( $line, $at + 1 ) );
			$attr  = '';

			if ( 'attribute' === $type && false !== strpos( $value, '=' ) ) {
				$attr  = trim( substr( $value, 0, strpos( $value, '=' ) ) );
				$value = trim( substr( $value, strpos( $value, '=' ) + 1 ) );
			}

			$rule = self::normalize_filter_rule(
				array(
					'action' => 'exclude',
					'field'  => $type,
					'attr'   => $attr,
					'op'     => $ops[ $type ] ?? 'is',
					'value'  => $value,
				)
			);

			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}

		return $rules;
	}

	/**
	 * Values of a field for a row (lower case); a variation falls back to its product.
	 *
	 * @param string                    $field      Field.
	 * @param string                    $attr       Attribute name (for «attribute»).
	 * @param array<string,string>      $row        Row.
	 * @param array<string,string>|null $parent_row Parent row.
	 * @return array<int,string>
	 */
	public static function filter_values( string $field, string $attr, array $row, $parent_row ): array {
		// Texts may carry Factorial2000 Translator language tags: test every language.
		$langs = static function ( string $text ): array {
			list( $main, $ua ) = Prom_Feed::split_languages( $text );

			return array_values( array_unique( array_filter( array( $main, $ua ), 'strlen' ) ) );
		};

		$own_or_parent = static function ( string $column ) use ( $row, $parent_row ): string {
			$value = Marketplace_Text::clean( $row[ $column ] ?? '' );

			if ( '' === $value && is_array( $parent_row ) ) {
				$value = Marketplace_Text::clean( $parent_row[ $column ] ?? '' );
			}

			return $value;
		};

		switch ( $field ) {
			case 'brand':
				$out = array();

				foreach ( $langs( Prom_Feed::extract_vendor( $row, $parent_row ) ) as $text ) {
					$out = array_merge( $out, array_filter( array_map( 'trim', explode( ',', Marketplace_Text::lower( $text ) ) ) ) );
				}

				return array_values( array_unique( $out ) );

			case 'category':
				$out = array();

				foreach ( $langs( $own_or_parent( 'Categories' ) ) as $text ) {
					foreach ( Marketplace_Text::parse_category_paths( $text ) as $path ) {
						$out[] = Marketplace_Text::lower( implode( '>', $path ) );
					}
				}

				return array_values( array_unique( $out ) );

			case 'sku':
			case 'name':
			case 'tag':
				$column = array(
					'sku'  => 'SKU',
					'name' => 'Name',
					'tag'  => 'Tags',
				)[ $field ];
				$out    = array();

				foreach ( $langs( $own_or_parent( $column ) ) as $text ) {
					$text = Marketplace_Text::lower( $text );

					if ( 'tag' === $field ) {
						$out = array_merge( $out, array_filter( array_map( 'trim', Marketplace_Text::split_escaped( $text ) ) ) );
					} elseif ( '' !== $text ) {
						$out[] = $text;
					}
				}

				return array_values( array_unique( $out ) );

			case 'id':
				$ids = array( Marketplace_Text::clean( $row['ID'] ?? '' ) );

				if ( is_array( $parent_row ) ) {
					$ids[] = Marketplace_Text::clean( $parent_row['ID'] ?? '' );
				}

				return array_values( array_filter( $ids ) );

			case 'price':
				list( $price ) = Prom_Feed::pick_price( $row );

				if ( null === $price && is_array( $parent_row ) ) {
					list( $price ) = Prom_Feed::pick_price( $parent_row );
				}

				return null !== $price ? array( (string) $price ) : array();

			case 'stock':
				return array( Prom_Feed::row_available( $row ) ? 'instock' : 'outofstock' );

			case 'attribute':
				$want = Marketplace_Text::lower( $attr );
				$out  = array();

				foreach ( array( $row, $parent_row ) as $source ) {
					if ( ! is_array( $source ) || $out ) {
						continue;
					}

					for ( $i = 1; $i <= 40; $i++ ) {
						if ( in_array( $want, array_map( array( Marketplace_Text::class, 'lower' ), $langs( Marketplace_Text::clean( $source[ "Attribute {$i} name" ] ?? '' ) ) ), true ) ) {
							foreach ( $langs( (string) ( $source[ "Attribute {$i} value(s)" ] ?? '' ) ) as $text ) {
								foreach ( explode( ',', Marketplace_Text::lower( $text ) ) as $v ) {
									if ( '' !== trim( $v ) ) {
										$out[] = trim( $v );
									}
								}
							}
						}
					}
				}

				return $out;
		}

		return array();
	}

	/**
	 * Whether a row matches one rule's condition.
	 *
	 * Several comma-separated values mean «any of them». A category «дорівнює» also
	 * covers its subcategories. «не …» conditions hold when no value matches.
	 *
	 * @param array{action:string,field:string,attr:string,op:string,value:string} $rule       Rule.
	 * @param array<string,string>                                                 $row        Row.
	 * @param array<string,string>|null                                            $parent_row Parent row.
	 * @return bool
	 */
	public static function filter_rule_matches( array $rule, array $row, $parent_row = null ): bool {
		$have = self::filter_values( $rule['field'], $rule['attr'], $row, $parent_row );
		$op   = $rule['op'];

		if ( in_array( $op, array( 'gt', 'lt' ), true ) ) {
			$limit = (float) str_replace( ',', '.', $rule['value'] );

			foreach ( $have as $value ) {
				if ( ( 'gt' === $op && (float) $value > $limit ) || ( 'lt' === $op && (float) $value < $limit ) ) {
					return true;
				}
			}

			return false;
		}

		$wants = array_values( array_filter( array_map( 'trim', explode( ',', Marketplace_Text::lower( $rule['value'] ) ) ), 'strlen' ) );

		if ( 'category' === $rule['field'] ) {
			$wants = array_map(
				static function ( $w ) {
					return (string) preg_replace( '/\s*>\s*/u', '>', $w );
				},
				$wants
			);
		}

		if ( 'stock' === $rule['field'] || 'name' === $rule['field'] ) {
			$wants = array( Marketplace_Text::lower( $rule['value'] ) );
		}

		$positive = array(
			'is_not'       => 'is',
			'not_contains' => 'contains',
		)[ $op ] ?? $op;

		$hit = false;

		foreach ( $have as $value ) {
			foreach ( $wants as $want ) {
				switch ( $positive ) {
					case 'is':
						$hit = $value === $want || ( 'category' === $rule['field'] && 0 === strpos( $value, $want . '>' ) );
						break;
					case 'contains':
						$hit = false !== mb_strpos( $value, $want );
						break;
					case 'starts_with':
						$hit = 0 === strpos( $value, $want );
						break;
				}

				if ( $hit ) {
					break 2;
				}
			}
		}

		return in_array( $op, array( 'is_not', 'not_contains' ), true ) ? ! $hit : $hit;
	}

	/**
	 * Whether a row stays out of the feed under the rules.
	 *
	 * @param array<int, array{action:string,field:string,attr:string,op:string,value:string}> $rules      Rules.
	 * @param array<string,string>                                                             $row        Row.
	 * @param array<string,string>|null                                                        $parent_row Parent row.
	 * @return bool
	 */
	public static function row_excluded( array $rules, array $row, $parent_row = null ): bool {
		$includes = 0;
		$included = false;

		foreach ( $rules as $rule ) {
			$match = self::filter_rule_matches( $rule, $row, $parent_row );

			if ( 'exclude' === $rule['action'] && $match ) {
				return true;
			}

			if ( 'include' === $rule['action'] ) {
				++$includes;
				$included = $included || $match;
			}
		}

		return $includes > 0 && ! $included;
	}

	/**
	 * Drop what the rules keep out of the feed from one unit, before the feed sees it.
	 *
	 * Rules are checked on every offer (a variation with its product as fallback). A variable
	 * product whose variations all go out leaves the feed whole. Dropped offers are reported
	 * under the «excluded» reason.
	 *
	 * @param array<string,mixed> $unit     Unit (simple, lone or group).
	 * @param array<string,mixed> $settings Feed settings.
	 * @return array{0:?array,1:int,2:array<string,array{count:int,examples:array<int,string>}>} Unit (null when nothing is left), dropped offers, reasons.
	 */
	public function apply_exclusions( array $unit, array $settings ): array {
		$rules = self::parse_filter_rules( (string) ( $settings['exclude_rules'] ?? '' ) );

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

			if ( ! $children && $parent && self::row_excluded( $rules, $parent, null ) ) {
				return array( null, 1, $report( 1, array( $label( $parent ) ) ) );
			}

			if ( ! $dropped ) {
				return array( $unit, 0, array() );
			}

			if ( ! $kept ) {
				return array( null, $dropped, $report( $dropped, $parent ? array( $label( $parent ) ) : $examples ) );
			}

			$unit['children'] = $kept;

			return array( $unit, $dropped, $report( $dropped, $examples ) );
		}

		$row    = (array) ( $unit['row'] ?? array() );
		$parent = is_array( $unit['parent'] ?? null ) ? $unit['parent'] : null;

		if ( self::row_excluded( $rules, $row, $parent ) ) {
			return array( null, 1, $report( 1, array( $label( $row ) ) ) );
		}

		return array( $unit, 0, array() );
	}

	/**
	 * Rules posted by the form (rows) as the stored JSON.
	 *
	 * @param mixed $rows Posted rows.
	 * @return string
	 */
	public static function filter_rows_to_json( $rows ): string {
		$rules = array();

		foreach ( is_array( $rows ) ? $rows : array() as $raw ) {
			$rule = self::normalize_filter_rule( $raw );

			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}

		return $rules ? (string) ( function_exists( 'wp_json_encode' ) ? wp_json_encode( $rules, JSON_UNESCAPED_UNICODE ) : json_encode( $rules, JSON_UNESCAPED_UNICODE ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fallback outside WordPress (tests).
	}
}
