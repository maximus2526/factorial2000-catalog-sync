<?php
/**
 * Prom.ua YML feed.
 *
 * Port of wc_to_prom_yml.py: WooCommerce product rows → Prom YML offers,
 * including Prom's variation ("різновиди") rules (see Prom_Variations).
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Prom.ua feed.
 */
class Prom_Feed extends Marketplace_Feed {

	/**
	 * Currencies Prom accepts in a feed (its import error list).
	 */
	const CURRENCIES = array( 'UAH', 'USD', 'EUR', 'CHF', 'RUB', 'GBP', 'JPY', 'PLZ', 'BYN', 'KZT', 'MDL' );

	/**
	 * Why rows of the unit being processed were left out.
	 *
	 * @var array<string,array{count:int,examples:array<int,string>}>
	 */
	private $reasons = array();

	/**
	 * Machine id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'prom';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return 'Prom.ua';
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'YML-вигрузка товарів WooCommerce для Prom.ua: основні товари, різновиди (розмір / колір), фото, характеристики, бренд, артикул. Посилання на готовий файл вказується в кабінеті Prom як джерело імпорту.', 'factorial2000-catalog-sync' );
	}

	/**
	 * Section titles of the settings form.
	 *
	 * @return array<string,string>
	 */
	public function groups(): array {
		return array(
			'basic'      => __( 'Магазин', 'factorial2000-catalog-sync' ),
			'products'   => __( 'Товари', 'factorial2000-catalog-sync' ),
			'brand'      => __( 'Виробник (бренд)', 'factorial2000-catalog-sync' ),
			'categories' => __( 'Категорії на Prom', 'factorial2000-catalog-sync' ),
			'advanced'   => __( 'Додатково', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Settings schema.
	 *
	 * Most fields are presets with a "custom" option, so the common choices are one
	 * click and anything unusual is still possible. Tokens like {site_name} follow
	 * the site settings instead of freezing today's value.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields(): array {
		return array(
			'shop_name'                    => array(
				'group'   => 'basic',
				'type'    => 'preset',
				'label'   => __( 'Назва магазину', 'factorial2000-catalog-sync' ),
				'default' => '{site_name}',
				'options' => array( '{site_name}' => __( 'Назва сайту (з налаштувань WordPress)', 'factorial2000-catalog-sync' ) ),
				'custom'  => array(
					'type'  => 'text',
					'label' => __( 'Своя назва…', 'factorial2000-catalog-sync' ),
				),
			),
			'company_name'                 => array(
				'group'   => 'basic',
				'type'    => 'preset',
				'label'   => __( 'Назва компанії', 'factorial2000-catalog-sync' ),
				'default' => '{site_name}',
				'options' => array( '{site_name}' => __( 'Назва сайту (з налаштувань WordPress)', 'factorial2000-catalog-sync' ) ),
				'custom'  => array(
					'type'  => 'text',
					'label' => __( 'Своя назва…', 'factorial2000-catalog-sync' ),
				),
			),
			'site_url'                     => array(
				'group'   => 'basic',
				'type'    => 'preset',
				'label'   => __( 'Адреса сайту', 'factorial2000-catalog-sync' ),
				'default' => '{site_url}',
				'options' => array( '{site_url}' => __( 'Адреса сайту (з налаштувань WordPress)', 'factorial2000-catalog-sync' ) ),
				'custom'  => array(
					'type'  => 'text',
					'label' => __( 'Інша адреса…', 'factorial2000-catalog-sync' ),
				),
			),
			'currency'                     => array(
				'group'   => 'basic',
				'type'    => 'preset',
				'label'   => __( 'Валюта цін', 'factorial2000-catalog-sync' ),
				'default' => '{wc_currency}',
				'options' => array(
					'{wc_currency}' => __( 'Валюта магазину WooCommerce', 'factorial2000-catalog-sync' ),
					'UAH'           => __( 'UAH — гривня', 'factorial2000-catalog-sync' ),
					'USD'           => __( 'USD — долар', 'factorial2000-catalog-sync' ),
					'EUR'           => __( 'EUR — євро', 'factorial2000-catalog-sync' ),
				),
				'custom'  => array(
					'type'  => 'text',
					'label' => __( 'Інша валюта…', 'factorial2000-catalog-sync' ),
				),
			),
			'max_pictures'                 => array(
				'group'       => 'products',
				'type'        => 'preset',
				'label'       => __( 'Фото на товар', 'factorial2000-catalog-sync' ),
				'description' => __( 'Prom приймає до 10 фото на товар.', 'factorial2000-catalog-sync' ),
				'default'     => '10',
				'options'     => array(
					'10' => __( 'До 10 (максимум Prom)', 'factorial2000-catalog-sync' ),
					'5'  => __( 'До 5', 'factorial2000-catalog-sync' ),
					'3'  => __( 'До 3', 'factorial2000-catalog-sync' ),
					'1'  => __( 'Лише головне фото', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'number',
					'label' => __( 'Інша кількість…', 'factorial2000-catalog-sync' ),
					'min'   => 1,
					'max'   => 10,
				),
			),
			'include_unpublished'          => array(
				'group'       => 'products',
				'type'        => 'checkbox',
				'label'       => __( 'Неопубліковані товари', 'factorial2000-catalog-sync' ),
				'checkbox'    => __( 'Додавати у вигрузку й чернетки', 'factorial2000-catalog-sync' ),
				'description' => __( 'За замовчуванням у вигрузку потрапляють лише опубліковані товари.', 'factorial2000-catalog-sync' ),
				'default'     => '0',
			),
			'description_fallback_snippet' => array(
				'group'       => 'products',
				'type'        => 'preset',
				'label'       => __( 'Якщо в товара немає опису', 'factorial2000-catalog-sync' ),
				'description' => __( 'Prom вимагає опис. Фраза додається після назви товару.', 'factorial2000-catalog-sync' ),
				'default'     => '',
				'options'     => array(
					''                => __( 'Лише назва товару', 'factorial2000-catalog-sync' ),
					'Деталі товару вказані в параметрах нижче.' => __( '«Деталі товару вказані в параметрах нижче.»', 'factorial2000-catalog-sync' ),
					'Опис незабаром.' => __( '«Опис незабаром.»', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'text',
					'label' => __( 'Свій текст…', 'factorial2000-catalog-sync' ),
				),
			),
			'default_vendor'               => array(
				'group'       => 'brand',
				'type'        => 'preset',
				'label'       => __( 'Виробник, якщо бренд не вказано', 'factorial2000-catalog-sync' ),
				'description' => __( 'Підставляється товарам без бренду. Назва має збігатися з довідником Prom.', 'factorial2000-catalog-sync' ),
				'default'     => '',
				'options'     => array(
					''                   => __( 'Не вказувати', 'factorial2000-catalog-sync' ),
					'Власне виробництво' => __( 'Власне виробництво', 'factorial2000-catalog-sync' ),
					'Без бренду'         => __( 'Без бренду', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'text',
					'label' => __( 'Інший виробник…', 'factorial2000-catalog-sync' ),
				),
			),
			'vendors_fallback_to_default'  => array(
				'group'       => 'brand',
				'type'        => 'checkbox',
				'label'       => __( 'Завжди цей виробник', 'factorial2000-catalog-sync' ),
				'checkbox'    => __( 'Підставляти його навіть тоді, коли бренд товара відомий', 'factorial2000-catalog-sync' ),
				'description' => __( 'Зазвичай не потрібно: тоді всі бренди замінюються одним значенням.', 'factorial2000-catalog-sync' ),
				'default'     => '0',
			),
			'vendor_aliases'               => array(
				'group'        => 'brand',
				'type'         => 'pairs',
				'label'        => __( 'Назви брендів у Prom', 'factorial2000-catalog-sync' ),
				'description'  => __( 'Якщо бренд на сайті пишеться інакше, ніж у довіднику Prom, вкажіть відповідність. Бренди сайту підказуються при введенні.', 'factorial2000-catalog-sync' ),
				'left_label'   => __( 'Бренд на сайті', 'factorial2000-catalog-sync' ),
				'right_label'  => __( 'Бренд у Prom', 'factorial2000-catalog-sync' ),
				'left_suggest' => 'brands',
				'default'      => '',
			),
			'default_portal_category_id'   => array(
				'group'       => 'categories',
				'type'        => 'prom_category',
				'label'       => __( 'Категорія Prom за замовчуванням', 'factorial2000-catalog-sync' ),
				'description' => __( 'Категорія маркетплейсу Prom (portal_category_id) для всіх товарів, у яких немає свого правила нижче чи поля «Категорія Prom» у картці товару. Почніть вводити назву — номер підставиться сам. Можна залишити порожнім.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
			'portal_category_rules'        => array(
				'group'       => 'categories',
				'type'        => 'category_map',
				'label'       => __( 'Категорії магазину → категорії Prom', 'factorial2000-catalog-sync' ),
				'description' => __( 'Навпроти категорії магазину почніть вводити назву категорії Prom і виберіть її зі списку — номер підставиться сам. Правило для батьківської категорії діє й на вкладені, якщо в них немає власного. Поле «Категорія Prom» у картці товару важливіше за ці правила.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
			'mpn_meta_key'                 => array(
				'group'       => 'advanced',
				'type'        => 'preset',
				'label'       => __( 'Код постачальника (MPN)', 'factorial2000-catalog-sync' ),
				'description' => __( 'Звідки брати значення для <mpn> у Prom.', 'factorial2000-catalog-sync' ),
				'default'     => 'f2000cs-updater-vendor',
				'options'     => array(
					'f2000cs-updater-vendor'  => __( 'Код постачальника з синхронізації Factorial2000', 'factorial2000-catalog-sync' ),
					'prom-xml-updater-vendor' => __( 'Код із плагіна prom-xml-updater', 'factorial2000-catalog-sync' ),
					''                        => __( 'Не передавати', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'text',
					'label' => __( 'Інше мета-поле…', 'factorial2000-catalog-sync' ),
				),
			),
		);
	}

	// ------------------------------------------------------------------
	// Document
	// ------------------------------------------------------------------

	/**
	 * XML head (declaration → currencies).
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public function head( array $settings ): string {
		$s = $this->with_defaults( $settings );

		foreach ( array( 'shop_name', 'company_name', 'site_url', 'currency' ) as $key ) {
			$s[ $key ] = Marketplace_Form::resolve( $s[ $key ] );
		}

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">' . "\n"
			. "<yml_catalog>\n"
			. "  <shop>\n"
			. '    <name>' . Marketplace_Text::xml_text( $s['shop_name'] ) . "</name>\n"
			. '    <company>' . Marketplace_Text::xml_text( $s['company_name'] ) . "</company>\n"
			. '    <url>' . Marketplace_Text::xml_text( $s['site_url'] ) . "</url>\n"
			. "    <currencies>\n"
			. '      <currency id="' . Marketplace_Text::xml_attr( $s['currency'] ) . '" rate="1" />' . "\n"
			. "    </currencies>\n";
	}

	/**
	 * XML tail.
	 *
	 * @return string
	 */
	public function tail(): string {
		return "    </offers>\n  </shop>\n</yml_catalog>\n";
	}

	// ------------------------------------------------------------------
	// Row helpers (ported)
	// ------------------------------------------------------------------

	/**
	 * Price and regular price of a row.
	 *
	 * @param array<string,string> $row Row.
	 * @return array{0:?float,1:?float}
	 */
	public static function pick_price( array $row ): array {
		$sale    = Marketplace_Text::parse_float( $row['Sale price'] ?? '' );
		$regular = Marketplace_Text::parse_float( $row['Regular price'] ?? '' );

		if ( null !== $sale && $sale > 0 ) {
			return array( $sale, $regular );
		}

		if ( null !== $regular && $regular > 0 ) {
			return array( $regular, $regular );
		}

		return array( null, $regular );
	}

	/**
	 * Whether a row is purchasable (stock, quantity or backorders).
	 *
	 * @param array<string,string> $row Row.
	 * @return bool
	 */
	public static function row_available( array $row ): bool {
		$in_stock = Marketplace_Text::parse_bool( $row['In stock?'] ?? '' );
		$count    = Marketplace_Text::parse_float( $row['Stock'] ?? '' );
		$count    = null === $count ? 0.0 : $count;

		if ( $in_stock || $count > 0 ) {
			return true;
		}

		$bo = Marketplace_Text::lower( Marketplace_Text::clean( $row['Backorders allowed?'] ?? '' ) );

		return in_array( $bo, array( '1', 'true', 'yes', 'notify' ), true );
	}

	/**
	 * Cheapest price among children.
	 *
	 * @param array<int, array<string,string>> $children Rows.
	 * @return array{0:?float,1:?float}
	 */
	public static function min_price_from_children( array $children ): array {
		$best_price   = null;
		$best_regular = null;

		foreach ( $children as $row ) {
			list( $price, $regular ) = self::pick_price( $row );

			if ( null === $price ) {
				continue;
			}

			if ( null === $best_price || $price < $best_price ) {
				$best_price   = $price;
				$best_regular = $regular;
			}
		}

		return array( $best_price, $best_regular );
	}

	/**
	 * Any child purchasable?
	 *
	 * @param array<int, array<string,string>> $children Rows.
	 * @return bool
	 */
	public static function any_child_available( array $children ): bool {
		foreach ( $children as $row ) {
			if ( self::row_available( $row ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Drop raw line breaks from HTML: Prom would show them as literal \n.
	 *
	 * @param string $raw HTML.
	 * @return string
	 */
	public static function normalize_description_html( $raw ): string {
		$raw = (string) $raw;

		if ( '' === $raw ) {
			return $raw;
		}

		// Block editor comments and registered shortcodes mean nothing to the marketplace
		// (other bracketed text such as "[XL]" is product text and stays).
		$raw = strip_shortcodes( (string) preg_replace( '/<!--.*?-->/su', '', $raw ) );

		// A classic description is plain text with line breaks: keep them as paragraphs.
		if ( ! preg_match( '/<(?:p|br|div|ul|ol|li|h[1-6]|table)\b/i', $raw ) ) {
			$raw = wpautop( $raw );
		}

		$text = str_replace( array( "\r\n", "\r", "\n" ), ' ', $raw );
		$text = str_replace( array( '\\n', '\\r' ), ' ', $text );
		$text = (string) preg_replace( '/>\s+</u', '><', $text );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		return Marketplace_Text::clean( $text );
	}

	/**
	 * Whether an HTML fragment contains visible text.
	 *
	 * @param string $fragment HTML.
	 * @return bool
	 */
	public static function html_text_meaningful( $fragment ): bool {
		if ( '' === Marketplace_Text::clean( $fragment ) ) {
			return false;
		}

		$text = (string) preg_replace( '/\s+/u', ' ', Marketplace_Text::strip_tags_to_space( $fragment ) );

		return '' !== Marketplace_Text::clean( $text );
	}

	/**
	 * Description: own, own short, parent's, parent's short, else a fallback line.
	 *
	 * @param array<string,string>      $row          Row.
	 * @param array<string,string>|null $parent_data       Parent row.
	 * @param string                    $product_name Product name.
	 * @param array<string,mixed>       $settings     Settings.
	 * @return string
	 */
	public static function pick_description_html( array $row, $parent_data, $product_name, array $settings ): string {
		$raw = self::pick_raw_description( $row, $parent_data );

		if ( '' !== $raw ) {
			return self::normalize_description_html( $raw );
		}

		return self::fallback_description_html( $product_name, $settings );
	}

	/**
	 * First description with visible text: own, own short, parent's, parent's short.
	 *
	 * Returned as stored (may be a translator-tagged string); '' when there is none.
	 *
	 * @param array<string,string>      $row         Row.
	 * @param array<string,string>|null $parent_data Parent row.
	 * @return string
	 */
	public static function pick_raw_description( array $row, $parent_data ): string {
		$chain = array(
			array( 'Description', $row ),
			array( 'Short description', $row ),
		);

		if ( $parent_data ) {
			$chain[] = array( 'Description', $parent_data );
			$chain[] = array( 'Short description', $parent_data );
		}

		foreach ( $chain as $item ) {
			$raw = Marketplace_Text::clean( $item[1][ $item[0] ] ?? '' );

			if ( self::html_text_meaningful( $raw ) ) {
				return $raw;
			}
		}

		return '';
	}

	/**
	 * Fallback description line: the product name plus the settings snippet.
	 *
	 * @param string              $product_name Product name.
	 * @param array<string,mixed> $settings     Settings.
	 * @return string
	 */
	public static function fallback_description_html( string $product_name, array $settings ): string {
		$extra = (string) ( $settings['description_fallback_snippet'] ?? '' );
		$inner = '' !== $extra
			? Marketplace_Text::html_escape( $product_name ) . '. ' . Marketplace_Text::html_escape( $extra )
			: Marketplace_Text::html_escape( $product_name );

		return '<p>' . $inner . '</p>';
	}

	/**
	 * Split a possibly multilingual field into the Prom main and `_ua` variants.
	 *
	 * With the Factorial2000 Translator active and both Russian and Ukrainian
	 * enabled, the main Prom fields carry the Russian text and the `_ua` fields
	 * the Ukrainian one; a missing Russian translation falls back to the main
	 * language (as the translator reads it). Without the translator, or without
	 * both languages, the value is used as it is for both fields.
	 *
	 * @param string $text Field value (may be a translator-tagged string).
	 * @return array{0:string,1:string} Main and Ukrainian variants.
	 */
	public static function split_languages( $text ): array {
		$text = (string) $text;

		if ( ! class_exists( '\F2000TR\Strings' ) || ! class_exists( '\F2000TR\Languages' ) ) {
			return array( $text, $text );
		}

		$languages = \F2000TR\Languages::get_languages();

		if ( ! is_array( $languages ) || ! isset( $languages['ru'], $languages['uk'] ) ) {
			return array( $text, $text );
		}

		return array(
			self::translate_language( $text, 'ru' ),
			self::translate_language( $text, 'uk' ),
		);
	}

	/**
	 * One language of a possibly tagged field.
	 *
	 * The translator's group-wise reader is preferred: it keeps text outside
	 * the language tags, e.g. variation name suffixes like " - S".
	 * Strings::translate() is the fallback for translator builds without it.
	 *
	 * @param string $text Field value.
	 * @param string $lang Language code.
	 * @return string
	 */
	private static function translate_language( string $text, string $lang ): string {
		if ( class_exists( '\F2000TR\WooCommerce' ) && method_exists( '\F2000TR\WooCommerce', 'translate_display' ) ) {
			return (string) \F2000TR\WooCommerce::translate_display( $text, $lang );
		}

		return (string) \F2000TR\Strings::translate( $text, $lang );
	}

	/**
	 * Brand of a row (own, parent's, then "Виробник"/"Бренд" attributes).
	 *
	 * @param array<string,string>      $row    Row.
	 * @param array<string,string>|null $parent_data Parent row.
	 * @return string
	 */
	public static function extract_vendor( array $row, $parent_data = null ): string {
		$vendor = Marketplace_Text::clean( $row['Brands'] ?? '' );

		if ( '' !== $vendor ) {
			return $vendor;
		}

		if ( $parent_data ) {
			$parent_vendor = Marketplace_Text::clean( $parent_data['Brands'] ?? '' );

			if ( '' !== $parent_vendor ) {
				return $parent_vendor;
			}
		}

		foreach ( array_filter( array( $row, $parent_data ) ) as $source ) {
			for ( $idx = 1; $idx <= 13; $idx++ ) {
				$name  = Marketplace_Text::clean( $source[ "Attribute {$idx} name" ] ?? '' );
				$value = Marketplace_Text::clean( $source[ "Attribute {$idx} value(s)" ] ?? '' );

				if ( in_array( Marketplace_Text::lower( $name ), array( 'виробник', 'бренд' ), true ) && '' !== $value ) {
					return $value;
				}
			}
		}

		return '';
	}

	/**
	 * Brand as it must appear in Prom (aliases, default).
	 *
	 * @param string              $extracted Brand from the product.
	 * @param array<string,mixed> $settings  Settings.
	 * @return string
	 */
	public static function apply_vendor_for_prom( $extracted, array $settings ): string {
		$v       = Marketplace_Text::clean( $extracted );
		$aliases = self::parse_pairs( (string) ( $settings['vendor_aliases'] ?? '' ) );
		$default = Marketplace_Text::clean( $settings['default_vendor'] ?? '' );

		if ( '' !== $v ) {
			if ( isset( $aliases[ $v ] ) ) {
				return Marketplace_Text::clean( $aliases[ $v ] );
			}

			$lv = Marketplace_Text::lower( $v );

			foreach ( $aliases as $src => $tgt ) {
				if ( Marketplace_Text::lower( Marketplace_Text::clean( (string) $src ) ) === $lv ) {
					return Marketplace_Text::clean( $tgt );
				}
			}

			if ( '1' === (string) ( $settings['vendors_fallback_to_default'] ?? '0' ) && '' !== $default ) {
				return $default;
			}

			return $v;
		}

		return $default;
	}

	/**
	 * Supplier code (MPN) of a row or its parent.
	 *
	 * @param array<string,string>      $row    Row.
	 * @param array<string,string>|null $parent_data Parent row.
	 * @return string
	 */
	public static function extract_mpn( array $row, $parent_data = null ): string {
		foreach ( array( 'Meta: mpn', 'Meta: prom-xml-updater-vendor', 'meta:prom-xml-updater-vendor', 'prom-xml-updater-vendor' ) as $key ) {
			$val = Marketplace_Text::clean( $row[ $key ] ?? '' );

			if ( '' !== $val ) {
				return $val;
			}
		}

		if ( null !== $parent_data ) {
			return self::extract_mpn( $parent_data, null );
		}

		return '';
	}

	/**
	 * Portal category id from rules (prefix of the category path, whole segments), else the default.
	 *
	 * The rule "одяг" matches "Одяг" and "Одяг > Куртки" but not "Дитячий одяг".
	 *
	 * @param array<int, array<int, string>> $paths    Category paths.
	 * @param array<string,mixed>            $settings Settings.
	 * @return string
	 */
	public static function portal_category_for_paths( array $paths, array $settings ): string {
		$normalized = array();

		foreach ( $paths as $path ) {
			$segments = array();

			foreach ( $path as $seg ) {
				$seg = Marketplace_Text::clean( $seg );

				if ( '' !== $seg ) {
					$segments[] = Marketplace_Text::lower( $seg );
				}
			}

			$normalized[] = implode( '>', $segments );
		}

		foreach ( self::parse_pairs( (string) ( $settings['portal_category_rules'] ?? '' ) ) as $sub => $cid ) {
			$sub = Marketplace_Form::normalize_path( (string) $sub );
			$cid = Marketplace_Text::clean( $cid );

			if ( '' === $sub || '' === $cid ) {
				continue;
			}

			foreach ( $normalized as $path ) {
				if ( $path === $sub || 0 === strpos( $path, $sub . '>' ) ) {
					return $cid;
				}
			}
		}

		return Marketplace_Text::clean( $settings['default_portal_category_id'] ?? '' );
	}

	/**
	 * Portal category of an offer: the product's own «Категорія Prom» field (a variation
	 * takes its parent's), else the category rules and the default.
	 *
	 * Prom gives the offer-level <portal_category_id> priority over the group's portal_id,
	 * so a product-level choice always wins.
	 *
	 * @param array<string,string>           $row        Row.
	 * @param array<string,string>|null      $parent_row Parent row.
	 * @param array<int, array<int, string>> $paths      Category paths of the offer.
	 * @param array<string,mixed>            $settings   Settings.
	 * @return string
	 */
	public static function portal_category_for_row( array $row, $parent_row, array $paths, array $settings ): string {
		foreach ( array( $row, $parent_row ) as $source ) {
			$own = is_array( $source ) ? Prom_Categories::sanitize_id( $source[ Marketplace_WC_Source::PROM_CATEGORY_COLUMN ] ?? '' ) : '';

			if ( '' !== $own ) {
				return $own;
			}
		}

		return Prom_Categories::sanitize_id( self::portal_category_for_paths( $paths, $settings ) );
	}

	// ------------------------------------------------------------------
	// Offers
	// ------------------------------------------------------------------

	/**
	 * Build one <offer>. Returns array( xml|null, skipped ).
	 *
	 * @param Marketplace_Category_Tree $tree           Category tree.
	 * @param array<string,mixed>       $settings       Settings.
	 * @param array<string,string>      $row            Row.
	 * @param array<string,string>|null $parent_row     Parent row (title/description/price/pictures fallbacks).
	 * @param string                    $group_id       Group id or ''.
	 * @param array|null                $params         Params override.
	 * @param array|null                $price_override array( price, old price ).
	 * @param array<string,string>|null $images_fallback_row Row whose pictures/categories are used as a last resort.
	 * @param bool|null                 $available_override  Forced availability.
	 * @param string|null               $sku_override   Forced vendorCode.
	 * @return array{0:?string,1:int}
	 */
	public function compose_offer( Marketplace_Category_Tree $tree, array $settings, array $row, $parent_row, string $group_id, $params = null, $price_override = null, $images_fallback_row = null, $available_override = null, $sku_override = null ): array {
		$name_raw = Marketplace_Text::clean( $row['Name'] ?? '' );

		if ( '' === $name_raw && $parent_row ) {
			$name_raw = Marketplace_Text::clean( $parent_row['Name'] ?? '' );
		}

		list( $name, $name_ua ) = self::split_languages( $name_raw );

		// Prom rejects a name without letters (only digits, dashes or spaces).
		if ( ! preg_match( '/\p{L}/u', $name ) ) {
			$this->skip( 'no_name', $row, $parent_row );

			return array( null, 1 );
		}

		$raw_description = self::pick_raw_description( $row, $parent_row );

		if ( '' !== $raw_description ) {
			list( $description, $description_ua ) = self::split_languages( $raw_description );

			$description    = self::normalize_description_html( $description );
			$description_ua = self::normalize_description_html( $description_ua );
		} else {
			$description    = self::fallback_description_html( $name, $settings );
			$description_ua = self::fallback_description_html( $name_ua, $settings );
		}

		if ( null !== $price_override ) {
			list( $price, $old_price ) = $price_override;
		} else {
			list( $price, $old_price ) = self::pick_price( $row );

			if ( null === $price && $parent_row ) {
				list( $price, $old_price ) = self::pick_price( $parent_row );
			}
		}

		if ( null === $price ) {
			$this->skip( 'no_price', $row, $parent_row );

			return array( null, 1 );
		}

		$max    = (int) ( $settings['max_pictures'] ?? 10 );
		$images = Marketplace_Text::parse_image_list( $row['Images'] ?? '', $max );

		if ( ! $images && $parent_row ) {
			$images = Marketplace_Text::parse_image_list( $parent_row['Images'] ?? '', $max );
		}

		if ( ! $images && $images_fallback_row ) {
			$images = Marketplace_Text::parse_image_list( $images_fallback_row['Images'] ?? '', $max );
		}

		if ( ! $images ) {
			$this->skip( 'no_pictures', $row, $parent_row );

			return array( null, 1 );
		}

		$category_paths = Marketplace_Text::parse_category_paths( $row['Categories'] ?? '' );

		if ( ! $category_paths && $parent_row ) {
			$category_paths = Marketplace_Text::parse_category_paths( $parent_row['Categories'] ?? '' );
		}

		if ( ! $category_paths && $images_fallback_row ) {
			$category_paths = Marketplace_Text::parse_category_paths( $images_fallback_row['Categories'] ?? '' );
		}

		$category_id = $tree->add_paths( $category_paths );

		if ( null === $category_id ) {
			$this->skip( 'no_category', $row, $parent_row );

			return array( null, 1 );
		}

		$offer_id = Marketplace_Text::clean( $row['ID'] ?? '' );

		if ( '' === $offer_id ) {
			$offer_id = Marketplace_Text::clean( $row['SKU'] ?? '' );
		}

		if ( '' === $offer_id ) {
			$this->skip( 'no_id', $row, $parent_row );

			return array( null, 1 );
		}

		$available = null !== $available_override ? (bool) $available_override : self::row_available( $row );

		$ind  = '      ';
		$ind2 = '        ';
		$attr = ' id="' . Marketplace_Text::xml_attr( $offer_id ) . '" available="' . ( $available ? 'true' : 'false' ) . '"';

		if ( '' !== $group_id ) {
			$attr .= ' group_id="' . Marketplace_Text::xml_attr( $group_id ) . '"';
		}

		$xml  = $ind . '<offer' . $attr . ">\n";
		$xml .= $ind2 . '<name>' . Marketplace_Text::xml_text( $name ) . "</name>\n";
		$xml .= $ind2 . '<name_ua>' . Marketplace_Text::xml_text( $name_ua ) . "</name_ua>\n";
		$xml .= $ind2 . '<price>' . Marketplace_Text::money( (float) $price ) . "</price>\n";
		$xml .= $ind2 . '<currencyId>' . Marketplace_Text::xml_text( Marketplace_Form::resolve( $settings['currency'] ?? 'UAH' ) ) . "</currencyId>\n";
		$xml .= $ind2 . '<categoryId>' . (int) $category_id . "</categoryId>\n";

		$portal = self::portal_category_for_row( $row, $parent_row, $category_paths, $settings );

		if ( '' !== $portal ) {
			$xml .= $ind2 . '<portal_category_id>' . Marketplace_Text::xml_text( $portal ) . "</portal_category_id>\n";
		}

		$xml .= $ind2 . '<description>' . Marketplace_Text::xml_text( $description ) . "</description>\n";
		$xml .= $ind2 . '<description_ua>' . Marketplace_Text::xml_text( $description_ua ) . "</description_ua>\n";

		$sku = Marketplace_Text::clean( null !== $sku_override ? $sku_override : '' );

		if ( '' === $sku ) {
			$sku = Marketplace_Text::clean( $row['SKU'] ?? '' );
		}

		if ( '' === $sku && $parent_row ) {
			$sku = Marketplace_Text::clean( $parent_row['SKU'] ?? '' );
		}

		$article = self::prom_article( $sku );

		if ( '' !== $article ) {
			$xml .= $ind2 . '<vendorCode>' . Marketplace_Text::xml_text( $article ) . "</vendorCode>\n";
		}

		$vendor = self::apply_vendor_for_prom( self::extract_vendor( $row, $parent_row ), $settings );

		if ( '' !== $vendor ) {
			$xml .= $ind2 . '<vendor>' . Marketplace_Text::xml_text( $vendor ) . "</vendor>\n";
		}

		$mpn = self::extract_mpn( $row, $parent_row );

		if ( '' !== $mpn ) {
			$xml .= $ind2 . '<mpn>' . Marketplace_Text::xml_text( $mpn ) . "</mpn>\n";
		}

		$gtin = Marketplace_Text::clean( $row['GTIN, UPC, EAN, or ISBN'] ?? '' );

		if ( '' !== $gtin ) {
			// Prom reads <barcode> as an alias of the article (vendorCode); the product code is <gtin>.
			$xml .= $ind2 . '<gtin>' . Marketplace_Text::xml_text( $gtin ) . "</gtin>\n";
		}

		// Prom accepts a discount only for items that are in stock or on backorder.
		if ( $available && null !== $old_price && $old_price > $price ) {
			$xml .= $ind2 . '<oldprice>' . Marketplace_Text::money( (float) $old_price ) . "</oldprice>\n";
		}

		foreach ( $images as $url ) {
			$xml .= $ind2 . '<picture>' . Marketplace_Text::xml_text( $url ) . "</picture>\n";
		}

		if ( null === $params ) {
			$params = Prom_Variations::collect_params( $row, false );
		}

		$seen = array();

		foreach ( $params as $pair ) {
			$key = Marketplace_Text::lower( trim( $pair[0] ) ) . "\x1f" . Marketplace_Text::lower( trim( $pair[1] ) );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$xml         .= $ind2 . '<param name="' . Marketplace_Text::xml_attr( $pair[0] ) . '">' . Marketplace_Text::xml_text( $pair[1] ) . "</param>\n";
		}

		$xml .= $ind . "</offer>\n";

		return array( $xml, 0 );
	}

	/**
	 * Article (vendorCode) as Prom accepts it: up to 25 characters, only digits, Cyrillic and
	 * Latin letters and - _ . / and spaces. A longer or odd SKU would fail the whole offer.
	 *
	 * @param string $sku SKU.
	 * @return string
	 */
	public static function prom_article( string $sku ): string {
		$article = (string) preg_replace( '/[^\p{Cyrillic}A-Za-z0-9\-_.\/ ]+/u', '', $sku );
		$article = trim( (string) preg_replace( '/\s+/u', ' ', $article ) );

		return trim( mb_substr( $article, 0, 25 ) );
	}

	/**
	 * Whether a row may be exported given the "unpublished" setting.
	 *
	 * @param array<string,string> $row      Row.
	 * @param array<string,mixed>  $settings Settings.
	 * @return bool
	 */
	public static function publish_ok( array $row, array $settings ): bool {
		return '1' === (string) ( $settings['include_unpublished'] ?? '0' ) || Marketplace_Text::parse_bool( $row['Published'] ?? '' );
	}

	// ------------------------------------------------------------------
	// Units
	// ------------------------------------------------------------------

	/**
	 * Convert one unit to offer XML.
	 *
	 * @param array<string,mixed>       $unit     Unit.
	 * @param Marketplace_Category_Tree $tree     Category tree.
	 * @param array<string,mixed>       $settings Settings.
	 * @return array{xml:string,exported:int,skipped:int,excluded:array<int,string>,used_ids:array<int,string>,reasons:array<string,array{count:int,examples:array<int,string>}>}
	 */
	public function process_unit( array $unit, Marketplace_Category_Tree $tree, array $settings ): array {
		$settings      = $this->with_defaults( $settings );
		$this->reasons = array();

		switch ( $unit['kind'] ?? '' ) {
			case 'group':
				$res = $this->process_group( $unit['parent'] ?? null, (array) ( $unit['children'] ?? array() ), $tree, $settings );
				break;

			case 'lone':
				$res = $this->process_lone( (array) $unit['row'], $unit['parent'] ?? null, $tree, $settings );
				break;

			case 'simple':
				$res = $this->process_simple( (array) $unit['row'], $tree, $settings );
				break;

			default:
				$res = $this->result();
		}

		$res['reasons'] = $this->reasons;

		return $res;
	}

	/**
	 * Record why a row was left out (shown on the admin page after the build).
	 *
	 * @param string                    $reason     Reason key (see skip_reason_labels()).
	 * @param array<string,string>|null $row        Row.
	 * @param array<string,string>|null $parent_row Parent row (its SKU names a variation without one).
	 * @param int                       $count      Offers left out.
	 * @return void
	 */
	private function skip( string $reason, $row, $parent_row = null, int $count = 1 ): void {
		$label = '';

		foreach ( array( $row, $parent_row ) as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$label = Marketplace_Text::clean( $source['SKU'] ?? '' );

			if ( '' === $label && '' !== Marketplace_Text::clean( $source['ID'] ?? '' ) ) {
				$label = 'ID ' . Marketplace_Text::clean( $source['ID'] );
			}

			if ( '' !== $label ) {
				break;
			}
		}

		$this->reasons = self::merge_reasons(
			$this->reasons,
			array(
				$reason => array(
					'count'    => $count,
					'examples' => '' !== $label ? array( $label ) : array(),
				),
			)
		);
	}

	/**
	 * Labels of the skip reasons, Prom-specific ones included.
	 *
	 * @return array<string,string>
	 */
	public function skip_reason_labels(): array {
		return parent::skip_reason_labels() + array(
			'duplicate_variations' => __( 'різновиди з однаковими характеристиками', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Empty result.
	 *
	 * @return array{xml:string,exported:int,skipped:int,excluded:array<int,string>,used_ids:array<int,string>}
	 */
	private function result(): array {
		return array(
			'xml'      => '',
			'exported' => 0,
			'skipped'  => 0,
			'excluded' => array(),
			'used_ids' => array(),
		);
	}

	/**
	 * Simple product.
	 *
	 * @param array<string,string>      $row      Row.
	 * @param Marketplace_Category_Tree $tree     Tree.
	 * @param array<string,mixed>       $settings Settings.
	 * @return array
	 */
	private function process_simple( array $row, Marketplace_Category_Tree $tree, array $settings ): array {
		$res = $this->result();

		if ( ! self::publish_ok( $row, $settings ) ) {
			$res['skipped'] = 1;
			$this->skip( 'unpublished', $row );

			return $res;
		}

		list( $xml, $sk ) = $this->compose_offer( $tree, $settings, $row, null, '' );

		if ( null !== $xml ) {
			$res['xml']      = $xml;
			$res['exported'] = 1;
		} else {
			$res['skipped'] = $sk;
		}

		return $res;
	}

	/**
	 * Variation whose group could not be built.
	 *
	 * @param array<string,string>      $row        Variation row.
	 * @param array<string,string>|null $parent_row Parent row.
	 * @param Marketplace_Category_Tree $tree       Tree.
	 * @param array<string,mixed>       $settings   Settings.
	 * @return array
	 */
	private function process_lone( array $row, $parent_row, Marketplace_Category_Tree $tree, array $settings ): array {
		$res = $this->result();

		if ( ! self::publish_ok( $row, $settings ) ) {
			$res['skipped'] = 1;
			$this->skip( 'unpublished', $row );

			return $res;
		}

		$vid = Marketplace_Text::clean( $row['ID'] ?? '' );
		$gid = $parent_row ? Marketplace_Text::clean( $parent_row['ID'] ?? '' ) : '';

		if ( $parent_row ) {
			$hints = Prom_Variations::infer_size_hints( $parent_row, array( $row ) );
			$sh    = $hints[ $vid ] ?? null;
			$axes  = Prom_Variations::group_variation_axis_list( $parent_row );
			$pv    = Prom_Variations::params_pick_for_variation( $parent_row, $row, $sh, $axes, 0 );
		} else {
			$pv = Prom_Variations::collect_params( $row, false );
		}

		list( $xml, $sk ) = $this->compose_offer( $tree, $settings, $row, $parent_row, $gid, $pv );

		if ( null !== $xml ) {
			$res['xml']      = $xml;
			$res['exported'] = 1;

			if ( '' !== $vid ) {
				$res['used_ids'][] = $vid;
			}
		} else {
			$res['skipped'] = $sk;
		}

		return $res;
	}

	/**
	 * Variable product: main offer + variations.
	 *
	 * @param array<string,string>|null        $parent_row Parent row.
	 * @param array<int, array<string,string>> $raw_children Variation rows.
	 * @param Marketplace_Category_Tree        $tree       Tree.
	 * @param array<string,mixed>              $settings   Settings.
	 * @return array
	 */
	private function process_group( $parent_row, array $raw_children, Marketplace_Category_Tree $tree, array $settings ): array {
		$res  = $this->result();
		$used = array();

		$children = array();

		foreach ( $raw_children as $c ) {
			if ( self::publish_ok( $c, $settings ) ) {
				$children[] = $c;
			}
		}

		$children = Prom_Variations::sort_rows_by_id( $children );

		if ( ! $children ) {
			return $res;
		}

		$gid = $parent_row ? Marketplace_Text::clean( $parent_row['ID'] ?? '' ) : '';

		$hints     = array();
		$axis_list = array();

		if ( $parent_row ) {
			$hints     = Prom_Variations::infer_size_hints( $parent_row, $children );
			$axis_list = Prom_Variations::group_variation_axis_list( $parent_row );
		}

		$slot_entries = array();
		$full_by_vid  = array();
		$slot_i       = 0;

		foreach ( $children as $child ) {
			$vid = Marketplace_Text::clean( $child['ID'] ?? '' );

			if ( '' === $vid ) {
				++$res['skipped'];
				$this->skip( 'no_id', $child, $parent_row );
				continue;
			}

			if ( isset( $used[ $vid ] ) ) {
				continue;
			}

			if ( $parent_row ) {
				$full = array();

				if ( $axis_list ) {
					$full = Prom_Variations::params_aligned_for_group( $parent_row, $child, $hints[ $vid ] ?? null, $axis_list, $slot_i, false );
				}

				if ( $full ) {
					$full_by_vid[ $vid ] = $full;
					$pv                  = $full;
				} else {
					$pv = Prom_Variations::params_for_variation( $parent_row, $child, $hints[ $vid ] ?? null );
				}
			} else {
				$pv = Prom_Variations::collect_params( $child, false );
			}

			$slot_entries[] = array( $vid, $child, $pv );
			++$slot_i;
		}

		$try_parent = $parent_row && self::publish_ok( $parent_row, $settings ) && '' !== $gid;

		$main_params = $parent_row ? Prom_Variations::build_parent_prom_params( $parent_row ) : array();

		// The main Prom offer is a real variation (its size, stock, price and SKU),
		// which is then not exported again on its own.
		$main_idx = null;

		if ( $try_parent && $slot_entries ) {
			$main_idx = Prom_Variations::pick_main_variation_index( $slot_entries, $main_params );

			if ( null === $main_idx ) {
				$candidates = array();

				foreach ( $slot_entries as $i => $entry ) {
					if ( isset( $full_by_vid[ $entry[0] ] ) ) {
						$candidates[] = $i;
					}
				}

				$available_candidates = array();

				foreach ( $candidates as $i ) {
					if ( self::row_available( $slot_entries[ $i ][1] ) ) {
						$available_candidates[] = $i;
					}
				}

				$pool = $available_candidates ? $available_candidates : $candidates;

				if ( $pool ) {
					$main_idx = $pool[0];
				}
			}

			if ( null !== $main_idx ) {
				$main_vid = $slot_entries[ $main_idx ][0];

				if ( isset( $full_by_vid[ $main_vid ] ) ) {
					$main_params = Prom_Variations::override_params( $main_params, $full_by_vid[ $main_vid ] );
				}
			}
		}

		if ( $parent_row ) {
			$baseline_main = array();

			foreach ( $main_params as $pair ) {
				$nk = Prom_Variations::normalized_attr_key( $pair[0] );

				if ( '' !== $nk ) {
					$baseline_main[ $nk ] = Marketplace_Text::clean( $pair[1] );
				}
			}

			foreach ( $slot_entries as $i => $entry ) {
				if ( isset( $full_by_vid[ $entry[0] ] ) ) {
					$slot_entries[ $i ] = array(
						$entry[0],
						$entry[1],
						Prom_Variations::drop_baseline_only_if_non_empty( $full_by_vid[ $entry[0] ], $baseline_main ),
					);
				}
			}
		}

		$main_entry = null;

		if ( null !== $main_idx ) {
			$main_entry = $slot_entries[ $main_idx ];
			array_splice( $slot_entries, $main_idx, 1 );
		}

		Prom_Variations::dedupe_variation_param_sets_for_group( $parent_row, $slot_entries );

		// Prom rejects a group whose members share the same characteristics: skip it entirely.
		$group_params = $try_parent ? array( $main_params ) : array();

		foreach ( $slot_entries as $entry ) {
			$group_params[] = $entry[2];
		}

		if ( $try_parent && Prom_Variations::has_duplicate_param_sets( $main_params, $group_params ) ) {
			$res['excluded'][] = Marketplace_Text::clean( $parent_row['SKU'] ?? '' ) ? Marketplace_Text::clean( $parent_row['SKU'] ?? '' ) : $gid;
			$dropped           = 1 + count( $slot_entries ) + ( $main_entry ? 1 : 0 );
			$res['skipped']   += $dropped;
			$this->skip( 'duplicate_variations', $parent_row, null, $dropped );

			foreach ( $slot_entries as $entry ) {
				$res['used_ids'][] = $entry[0];
			}

			if ( $main_entry ) {
				$res['used_ids'][] = $main_entry[0];
			}

			return $res;
		}

		if ( $try_parent ) {
			$main_child = $main_entry ? $main_entry[1] : null;

			if ( $main_child ) {
				list( $min_p, $min_old ) = self::pick_price( $main_child );

				$available_main = self::row_available( $main_child );
				$sku_main       = '' !== Marketplace_Text::clean( $main_child['SKU'] ?? '' ) ? Marketplace_Text::clean( $main_child['SKU'] ?? '' ) : null;
			} else {
				list( $min_p, $min_old ) = self::min_price_from_children( $children );

				$available_main = self::any_child_available( $children );
				$sku_main       = null;
			}

			if ( null === $min_p ) {
				list( $min_p, $min_old ) = self::pick_price( $parent_row );
			}

			if ( null === $min_p ) {
				list( $min_p, $min_old ) = self::pick_price( $children[0] );
			}

			if ( null === $min_p ) {
				++$res['skipped'];
				$this->skip( 'no_price', $parent_row );
			} else {
				list( $xml, $sk ) = $this->compose_offer(
					$tree,
					$settings,
					$parent_row,
					null,
					$gid,
					$main_params,
					array( $min_p, $min_old ),
					$main_child ? $main_child : $children[0],
					$available_main,
					$sku_main
				);

				if ( null !== $xml ) {
					$res['xml'] .= $xml;
					++$res['exported'];

					if ( $main_entry ) {
						$used[ $main_entry[0] ] = true;
					}
				} else {
					$res['skipped'] += $sk;
				}
			}
		}

		if ( $main_entry && ! isset( $used[ $main_entry[0] ] ) ) {
			array_unshift( $slot_entries, $main_entry );
		}

		foreach ( $slot_entries as $entry ) {
			list( $vid, $child, $pv ) = $entry;

			if ( isset( $used[ $vid ] ) ) {
				continue;
			}

			list( $xml, $sk ) = $this->compose_offer( $tree, $settings, $child, $parent_row, $gid, $pv );

			if ( null !== $xml ) {
				$res['xml'] .= $xml;
				++$res['exported'];
				$used[ $vid ] = true;
			} else {
				$res['skipped'] += $sk;
			}
		}

		$res['used_ids'] = array_values( array_unique( array_merge( $res['used_ids'], array_map( 'strval', array_keys( $used ) ) ) ) );

		return $res;
	}
}
