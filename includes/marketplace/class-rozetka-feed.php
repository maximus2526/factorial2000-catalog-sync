<?php
/**
 * ROZETKA marketplace price list (YML).
 *
 * Every purchasable product and every variation is a separate <offer>: ROZETKA has no
 * group element, variants differ by their name and <param> values. Offers carry
 * <stock_quantity> (required), <price_old> for a real discount, <vendor> (required),
 * <article>, name/name_ua, description/description_ua (HTML in CDATA) and <param>.
 * Categories are the shop's own; a rule can attach ROZETKA's category id (rz_id).
 *
 * Spec: https://sellerhelp.rozetka.com.ua/p185-pricelist-requirements.html
 * Variants: https://sellerhelp.rozetka.com.ua/p205-product-variety.html
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * ROZETKA feed.
 */
class Rozetka_Feed extends Marketplace_Feed {

	/**
	 * Limits of the ROZETKA specification.
	 */
	const MAX_NAME        = 255;
	const MAX_DESCRIPTION = 50000;
	const MAX_PARAM       = 500;
	const MAX_URL         = 500;
	const MAX_PICTURES    = 15;
	const MAX_PICTURE_URL = 1999;

	/**
	 * Skip reasons collected while one unit is processed.
	 *
	 * @var array<string,array{count:int,examples:array<int,string>}>
	 */
	private $reasons = array();

	/**
	 * Names already given to the offers of the current variable product (lowercase).
	 *
	 * @var array<string,bool>
	 */
	private $group_names = array();

	/**
	 * Machine id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'rozetka';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return 'ROZETKA';
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Прайс-лист для маркетплейсу ROZETKA (YML за вимогами sellerhelp.rozetka.com.ua). Кожен товар і кожен різновид — окрема пропозиція з власним id, назвою, кількістю (stock_quantity), брендом (vendor), артикулом і характеристиками. Посилання на файл вказується в кабінеті продавця ROZETKA; оновлення цін і наявності ROZETKA забирає щогодини.', 'factorial2000-catalog-sync' );
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
			'brand'      => __( 'Бренд', 'factorial2000-catalog-sync' ),
			'categories' => __( 'Категорії ROZETKA', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Settings schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields(): array {
		return array(
			'shop_name'              => array(
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
			'company_name'           => array(
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
			'site_url'               => array(
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
			'currency'               => array(
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
			),
			'id_prefix'              => array(
				'group'       => 'products',
				'type'        => 'text',
				'label'       => __( 'Префікс id пропозиції', 'factorial2000-catalog-sync' ),
				'description' => __( 'id пропозиції = префікс + ID товару на сайті. ROZETKA приймає лише латинські літери й цифри, а id не можна змінювати після публікації товару — не міняйте префікс пізніше.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
			'include_unpublished'    => array(
				'group'       => 'products',
				'type'        => 'checkbox',
				'label'       => __( 'Неопубліковані товари', 'factorial2000-catalog-sync' ),
				'checkbox'    => __( 'Додавати у прайс-лист і чернетки', 'factorial2000-catalog-sync' ),
				'description' => __( 'За замовчуванням у прайс-лист потрапляють лише опубліковані товари.', 'factorial2000-catalog-sync' ),
				'default'     => '0',
			),
			'regular_price_only'     => array(
				'group'       => 'products',
				'type'        => 'checkbox',
				'label'       => __( 'Знижки', 'factorial2000-catalog-sync' ),
				'checkbox'    => __( 'Не передавати знижки — лише основну ціну', 'factorial2000-catalog-sync' ),
				'description' => __( 'У <price> іде звичайна ціна товару, <price_old> не додається, навіть якщо на сайті діє акція. ROZETKA вимагає, щоб ціна не була вищою, ніж на вашому сайті.', 'factorial2000-catalog-sync' ),
				'default'     => '0',
			),
			'default_stock_quantity' => array(
				'group'       => 'products',
				'type'        => 'number',
				'label'       => __( 'Кількість, якщо залишок не ведеться', 'factorial2000-catalog-sync' ),
				'description' => __( 'ROZETKA вимагає <stock_quantity> для кожної пропозиції; 0 — «немає в наявності». Товари в наявності без керування залишком на сайті отримують це число. ROZETKA сама кількість не зменшує після замовлень.', 'factorial2000-catalog-sync' ),
				'min'         => 1,
				'max'         => 9999,
				'default'     => '10',
			),
			'default_brand'          => array(
				'group'       => 'brand',
				'type'        => 'preset',
				'label'       => __( 'Бренд, якщо його не вказано', 'factorial2000-catalog-sync' ),
				'description' => __( '<vendor> обов’язковий у ROZETKA. Товари без бренду й без значення тут не потрапляють у прайс-лист.', 'factorial2000-catalog-sync' ),
				'default'     => '',
				'options'     => array(
					''            => __( 'Не підставляти (пропускати товари без бренду)', 'factorial2000-catalog-sync' ),
					'{site_name}' => __( 'Назва сайту (з налаштувань WordPress)', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'text',
					'label' => __( 'Свій бренд…', 'factorial2000-catalog-sync' ),
				),
			),
			'rz_category_rules'      => array(
				'group'       => 'categories',
				'type'        => 'textarea',
				'label'       => __( 'Правила: категорія магазину → категорія ROZETKA (rz_id)', 'factorial2000-catalog-sync' ),
				'description' => __( 'По одному правилу в рядку: категорія магазину (або кінець її шляху)=номер категорії ROZETKA, наприклад «одяг>куртки=4637799» або «куртки=4637799». Номер додається до категорії як rz_id і має пріоритет над її назвою. Спрацьовує перше правило, що збіглося; решту категорій ROZETKA зіставить за назвою.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
		);
	}

	// ------------------------------------------------------------------
	// Document
	// ------------------------------------------------------------------

	/**
	 * Head: declaration, <yml_catalog date>, shop and currencies.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public function head( array $settings ): string {
		$s = $this->with_defaults( $settings );

		foreach ( array( 'shop_name', 'company_name', 'site_url', 'currency' ) as $key ) {
			$s[ $key ] = Marketplace_Form::resolve( $s[ $key ] );
		}

		$date = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i' ) : gmdate( 'Y-m-d H:i' );

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<yml_catalog date="' . Marketplace_Text::xml_attr( (string) $date ) . '">' . "\n"
			. "  <shop>\n"
			. '    <name>' . Marketplace_Text::xml_text( self::truncate( $s['shop_name'], 255 ) ) . "</name>\n"
			. '    <company>' . Marketplace_Text::xml_text( self::truncate( $s['company_name'], 255 ) ) . "</company>\n"
			. '    <url>' . Marketplace_Text::xml_text( self::truncate( $s['site_url'], 255 ) ) . "</url>\n"
			. "    <currencies>\n"
			. '      <currency id="' . Marketplace_Text::xml_attr( strtoupper( $s['currency'] ) ) . '" rate="1"/>' . "\n"
			. "    </currencies>\n";
	}

	/**
	 * Head, the category list (with rz_id from the rules) and the opening of <offers>.
	 *
	 * @param array<string,mixed>       $settings Settings.
	 * @param Marketplace_Category_Tree $tree     Category tree collected during the build.
	 * @return string
	 */
	public function document_head( array $settings, Marketplace_Category_Tree $tree ): string {
		$settings = $this->with_defaults( $settings );
		$rules    = self::parse_rules( (string) $settings['rz_category_rules'] );
		$out      = '';

		foreach ( $tree->all() as $id => $node ) {
			$attrs = ' id="' . (int) $id . '"';

			if ( null !== $node['parent'] ) {
				$attrs .= ' parentId="' . (int) $node['parent'] . '"';
			}

			$rz_id = self::rz_id_for_path( str_replace( "\x1f", ' > ', (string) $node['path'] ), $rules );

			if ( '' !== $rz_id ) {
				$attrs .= ' rz_id="' . Marketplace_Text::xml_attr( $rz_id ) . '"';
			}

			$out .= '      <category' . $attrs . '>' . Marketplace_Text::xml_text( self::truncate( (string) $node['name'], 255 ) ) . "</category>\n";
		}

		return $this->head( $settings ) . "    <categories>\n" . $out . "    </categories>\n    <offers>\n";
	}

	/**
	 * Tail.
	 *
	 * @return string
	 */
	public function tail(): string {
		return "    </offers>\n  </shop>\n</yml_catalog>\n";
	}

	/**
	 * ROZETKA has no offer groups: a big feed may be cut between any two offers.
	 *
	 * @param string $block One offer.
	 * @return string
	 */
	public function offer_group_id( string $block ): string {
		return '';
	}

	/**
	 * Labels of the skip reasons.
	 *
	 * @return array<string,string>
	 */
	public function skip_reason_labels(): array {
		return parent::skip_reason_labels() + array(
			'no_vendor' => __( 'немає бренду (vendor обов’язковий у ROZETKA)', 'factorial2000-catalog-sync' ),
		);
	}

	// ------------------------------------------------------------------
	// Units
	// ------------------------------------------------------------------

	/**
	 * Convert one unit to offers.
	 *
	 * @param array<string,mixed>       $unit     Unit.
	 * @param Marketplace_Category_Tree $tree     Category tree.
	 * @param array<string,mixed>       $settings Settings.
	 * @return array{xml:string,exported:int,skipped:int,excluded:array<int,string>,used_ids:array<int,string>,reasons:array<string,array{count:int,examples:array<int,string>}>}
	 */
	public function process_unit( array $unit, Marketplace_Category_Tree $tree, array $settings ): array {
		$settings      = $this->with_defaults( $settings );
		$unit          = self::without_sale_prices( $unit, $settings );
		$this->reasons = array();
		$res           = array(
			'xml'      => '',
			'exported' => 0,
			'skipped'  => 0,
			'excluded' => array(),
			'used_ids' => array(),
		);

		$tree->use_stable_ids( true );

		switch ( $unit['kind'] ?? '' ) {
			case 'group':
				$this->process_group( $res, $tree, $unit['parent'] ?? null, (array) ( $unit['children'] ?? array() ), $settings );
				break;

			case 'lone':
				$this->add_offer( $res, $tree, (array) $unit['row'], $unit['parent'] ?? null, array(), $settings );
				break;

			case 'simple':
				$this->add_offer( $res, $tree, (array) $unit['row'], null, array(), $settings );
				break;
		}

		$res['reasons'] = $this->reasons;

		return $res;
	}

	/**
	 * Variable product: one offer per published variation.
	 *
	 * @param array<string,mixed>              $res          Result (changed).
	 * @param Marketplace_Category_Tree        $tree         Category tree.
	 * @param array<string,string>|null        $parent_row   Parent row.
	 * @param array<int, array<string,string>> $raw_children Variation rows.
	 * @param array<string,mixed>              $settings     Settings.
	 * @return void
	 */
	private function process_group( array &$res, Marketplace_Category_Tree $tree, $parent_row, array $raw_children, array $settings ): void {
		$children = array();

		foreach ( $raw_children as $child ) {
			if ( Prom_Feed::publish_ok( $child, $settings ) ) {
				$children[] = $child;
			}
		}

		if ( ! $children || ( $parent_row && ! Prom_Feed::publish_ok( $parent_row, $settings ) ) ) {
			return;
		}

		$children          = Prom_Variations::sort_rows_by_id( $children );
		$this->group_names = array();
		$hints             = array();
		$axis_list         = array();

		if ( $parent_row ) {
			$hints     = Prom_Variations::infer_size_hints( $parent_row, $children );
			$axis_list = Prom_Variations::group_variation_axis_list( $parent_row );
		}

		foreach ( array_values( $children ) as $i => $child ) {
			$vid    = Marketplace_Text::clean( $child['ID'] ?? '' );
			$params = array();

			if ( $parent_row ) {
				if ( $axis_list ) {
					$params = Prom_Variations::params_aligned_for_group( $parent_row, $child, $hints[ $vid ] ?? null, $axis_list, $i, false );
				}

				if ( ! $params ) {
					$params = Prom_Variations::params_for_variation( $parent_row, $child, $hints[ $vid ] ?? null );
				}
			}

			$this->add_offer( $res, $tree, $child, $parent_row, $params, $settings );
		}
	}

	/**
	 * Build one offer and add it to the result.
	 *
	 * @param array<string,mixed>                  $res        Result (changed).
	 * @param Marketplace_Category_Tree            $tree       Category tree.
	 * @param array<string,string>                 $row        Product or variation row.
	 * @param array<string,string>|null            $parent_row Parent row of a variation.
	 * @param array<int, array{0:string,1:string}> $params     Resolved variation attributes.
	 * @param array<string,mixed>                  $settings   Settings.
	 * @return void
	 */
	private function add_offer( array &$res, Marketplace_Category_Tree $tree, array $row, $parent_row, array $params, array $settings ): void {
		if ( ! Prom_Feed::publish_ok( $row, $settings ) ) {
			$this->skip( $res, 'unpublished', $row, $parent_row );

			return;
		}

		list( $xml, $reason ) = $this->compose_offer( $tree, $row, $parent_row, $params, $settings );

		if ( null === $xml ) {
			$this->skip( $res, $reason, $row, $parent_row );

			return;
		}

		$res['xml']       .= $xml;
		$res['exported']  += 1;
		$res['used_ids'][] = Marketplace_Text::clean( $row['ID'] ?? '' );
	}

	/**
	 * Record why a row was left out.
	 *
	 * @param array<string,mixed>       $res        Result (changed).
	 * @param string                    $reason     Reason key.
	 * @param array<string,string>      $row        Row.
	 * @param array<string,string>|null $parent_row Parent row.
	 * @return void
	 */
	private function skip( array &$res, string $reason, array $row, $parent_row ): void {
		$res['skipped'] += 1;
		$label           = '';

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
					'count'    => 1,
					'examples' => '' !== $label ? array( $label ) : array(),
				),
			)
		);
	}

	// ------------------------------------------------------------------
	// Offer
	// ------------------------------------------------------------------

	/**
	 * XML of one offer, or the skip reason when the product cannot be listed.
	 *
	 * @param Marketplace_Category_Tree            $tree       Category tree.
	 * @param array<string,string>                 $row        Row.
	 * @param array<string,string>|null            $parent_row Parent row of a variation.
	 * @param array<int, array{0:string,1:string}> $params     Resolved variation attributes.
	 * @param array<string,mixed>                  $settings   Settings.
	 * @return array{0:?string,1:string} Offer XML (null when skipped) and the skip reason.
	 */
	public function compose_offer( Marketplace_Category_Tree $tree, array $row, $parent_row, array $params, array $settings ): array {
		$settings = $this->with_defaults( $settings );

		// Names (Russian main + Ukrainian).
		$name_raw = Marketplace_Text::clean( $row['Name'] ?? '' );

		if ( '' === $name_raw && $parent_row ) {
			$name_raw = Marketplace_Text::clean( $parent_row['Name'] ?? '' );
		}

		list( $name, $name_ua ) = Prom_Feed::split_languages( $name_raw );

		$name    = Google_Merchant_Feed::plain_text( $name );
		$name_ua = Google_Merchant_Feed::plain_text( $name_ua );

		if ( ! preg_match( '/\p{L}/u', $name . $name_ua ) ) {
			return array( null, 'no_name' );
		}

		// Id: Latin letters and digits only.
		$offer_id = (string) preg_replace( '/[^A-Za-z0-9]/', '', Marketplace_Text::clean( $row['ID'] ?? '' ) );

		if ( '' === $offer_id ) {
			return array( null, 'no_id' );
		}

		$offer_id = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $settings['id_prefix'] ) . $offer_id;

		// Price.
		list( $price, $regular ) = Prom_Feed::pick_price( $row );

		if ( null === $price && $parent_row ) {
			list( $price, $regular ) = Prom_Feed::pick_price( $parent_row );
		}

		if ( null === $price || $price <= 0 ) {
			return array( null, 'no_price' );
		}

		// Category.
		$category_paths = Marketplace_Text::parse_category_paths( $row['Categories'] ?? '' );

		if ( ! $category_paths && $parent_row ) {
			$category_paths = Marketplace_Text::parse_category_paths( $parent_row['Categories'] ?? '' );
		}

		if ( ! $category_paths ) {
			return array( null, 'no_category' );
		}

		// Pictures: https only, at most 15.
		$images = self::https_pictures( $row['Images'] ?? '' );

		if ( ! $images && $parent_row ) {
			$images = self::https_pictures( $parent_row['Images'] ?? '' );
		}

		if ( ! $images ) {
			return array( null, 'no_pictures' );
		}

		// Brand (required).
		$vendor = Marketplace_Text::clean( Prom_Feed::extract_vendor( $row, $parent_row ) );

		if ( '' === $vendor ) {
			$vendor = Marketplace_Text::clean( Marketplace_Form::resolve( (string) $settings['default_brand'] ) );
		}

		if ( '' === $vendor ) {
			return array( null, 'no_vendor' );
		}

		// Parameters: the variation's own first, then the product's.
		$pairs = $params;

		foreach ( Prom_Variations::collect_params( $row, false ) as $pair ) {
			$pairs[] = $pair;
		}

		if ( $parent_row ) {
			foreach ( Prom_Variations::collect_params( $parent_row, false ) as $pair ) {
				$pairs[] = $pair;
			}
		}

		$pairs = self::unique_params( $pairs );

		$sku = Marketplace_Text::clean( $row['SKU'] ?? '' );

		if ( '' === $sku && $parent_row ) {
			$sku = Marketplace_Text::clean( $parent_row['SKU'] ?? '' );
		}

		// ROZETKA wants unique names: a variation's name carries its own values (size,
		// colour…), and if two variations still look the same, the offer id tells them apart.
		if ( $parent_row ) {
			list( $name, $name_ua ) = self::names_with_variant( $name, $name_ua, array_merge( $params, Prom_Variations::collect_params( $row, false ) ) );

			if ( isset( $this->group_names[ Marketplace_Text::lower( '' !== $name ? $name : $name_ua ) ] ) ) {
				$name    = '' !== $name ? $name . ' (' . $offer_id . ')' : '';
				$name_ua = '' !== $name_ua ? $name_ua . ' (' . $offer_id . ')' : '';
			}
		}

		$name    = self::truncate( '' !== $name ? $name : $name_ua, self::MAX_NAME );
		$name_ua = self::truncate( '' !== $name_ua ? $name_ua : $name, self::MAX_NAME );

		// Description (required): HTML in CDATA, the name when the product has none.
		$raw_description = Prom_Feed::pick_raw_description( $row, $parent_row );

		if ( '' !== $raw_description ) {
			list( $description, $description_ua ) = Prom_Feed::split_languages( $raw_description );

			$description    = Prom_Feed::normalize_description_html( $description );
			$description_ua = Prom_Feed::normalize_description_html( $description_ua );
		} else {
			$description    = '';
			$description_ua = '';
		}

		$description    = '' !== trim( $description ) ? $description : '<p>' . Marketplace_Text::html_escape( $name ) . '</p>';
		$description_ua = '' !== trim( $description_ua ) ? $description_ua : '<p>' . Marketplace_Text::html_escape( $name_ua ) . '</p>';

		// The category is registered only for an offer that is written, so the list
		// carries no categories of skipped products.
		$category_id = $tree->add_paths( $category_paths );

		if ( null === $category_id ) {
			return array( null, 'no_category' );
		}

		// Stock.
		$available = Prom_Feed::row_available( $row );
		$quantity  = $available ? self::stock_quantity( $row, $settings ) : 0;
		$available = $quantity > 0;

		if ( $parent_row ) {
			$this->group_names[ Marketplace_Text::lower( $name ) ] = true;
		}

		$url      = Prom_Feed::pick_url( $row, $parent_row );
		$currency = strtoupper( Marketplace_Form::resolve( (string) $settings['currency'] ) );
		$ind      = '      ';
		$ind2     = '        ';

		$xml  = $ind . '<offer id="' . Marketplace_Text::xml_attr( $offer_id ) . '" available="' . ( $available ? 'true' : 'false' ) . "\">\n";
		$xml .= self::tag( $ind2, 'price', Marketplace_Text::money( (float) $price ) );

		// A real discount only: the old price must be higher than the price.
		if ( null !== $regular && $regular > $price ) {
			$xml .= self::tag( $ind2, 'price_old', Marketplace_Text::money( (float) $regular ) );
		}

		$xml .= self::tag( $ind2, 'currencyId', $currency );
		$xml .= $ind2 . '<categoryId>' . (int) $category_id . "</categoryId>\n";

		foreach ( $images as $picture ) {
			$xml .= self::tag( $ind2, 'picture', $picture );
		}

		$xml .= self::tag( $ind2, 'vendor', self::truncate( $vendor, 255 ) );

		if ( '' !== $sku ) {
			$xml .= self::tag( $ind2, 'article', self::truncate( $sku, 255 ) );
		}

		$xml .= $ind2 . '<stock_quantity>' . (int) $quantity . "</stock_quantity>\n";

		if ( '' !== $url && mb_strlen( $url ) <= self::MAX_URL ) {
			$xml .= self::tag( $ind2, 'url', $url );
		}

		$xml .= self::tag( $ind2, 'name', $name );
		$xml .= self::tag( $ind2, 'name_ua', $name_ua );
		$xml .= $ind2 . '<description>' . self::cdata( self::truncate( $description, self::MAX_DESCRIPTION ) ) . "</description>\n";
		$xml .= $ind2 . '<description_ua>' . self::cdata( self::truncate( $description_ua, self::MAX_DESCRIPTION ) ) . "</description_ua>\n";

		foreach ( $pairs as $pair ) {
			$xml .= $ind2 . '<param name="' . Marketplace_Text::xml_attr( $pair[0] ) . '">' . Marketplace_Text::xml_text( self::truncate( $pair[1], self::MAX_PARAM ) ) . "</param>\n";
		}

		return array( $xml . $ind . "</offer>\n", '' );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Quantity of an available row: the stock count, else the configured default.
	 *
	 * @param array<string,string> $row      Row.
	 * @param array<string,mixed>  $settings Settings.
	 * @return int
	 */
	public static function stock_quantity( array $row, array $settings ): int {
		$count = Marketplace_Text::parse_float( $row['Stock'] ?? '' );

		if ( null !== $count && $count >= 1 ) {
			return (int) floor( $count );
		}

		return max( 1, min( 9999, (int) ( $settings['default_stock_quantity'] ?? 10 ) ) );
	}

	/**
	 * Picture URLs ROZETKA accepts: https, encoded, no longer than the limit, at most 15.
	 *
	 * @param string $images Images cell.
	 * @return array<int,string>
	 */
	public static function https_pictures( $images ): array {
		$out = array();

		foreach ( Marketplace_Text::split_escaped( $images ) as $url ) {
			$url = Marketplace_Text::encode_url( Marketplace_Text::clean( $url ) );

			if ( 0 === stripos( $url, 'http://' ) ) {
				$url = 'https://' . substr( $url, 7 );
			}

			if ( 0 !== stripos( $url, 'https://' ) || strlen( $url ) > self::MAX_PICTURE_URL ) {
				continue;
			}

			$out[] = $url;

			if ( count( $out ) >= self::MAX_PICTURES ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Non-empty, unique name/value pairs (case-insensitive name and value).
	 *
	 * @param array<int, array{0:string,1:string}> $pairs Pairs.
	 * @return array<int, array{0:string,1:string}>
	 */
	private static function unique_params( array $pairs ): array {
		$seen  = array();
		$names = array();
		$out   = array();

		foreach ( $pairs as $pair ) {
			$name  = Marketplace_Text::clean( $pair[0] ?? '' );
			$value = Marketplace_Text::clean( $pair[1] ?? '' );

			if ( '' === $name || '' === $value ) {
				continue;
			}

			$key = Marketplace_Text::lower( $name );

			// The first value of a characteristic wins (the variation's own size beats the product's list).
			if ( isset( $names[ $key ] ) ) {
				continue;
			}

			$names[ $key ] = true;

			if ( isset( $seen[ $key . "\x1f" . Marketplace_Text::lower( $value ) ] ) ) {
				continue;
			}

			$seen[ $key . "\x1f" . Marketplace_Text::lower( $value ) ] = true;
			$out[] = array( $name, $value );
		}

		return $out;
	}

	/**
	 * Append the variation's values (size, colour…) to its names when they are missing.
	 *
	 * @param string                               $name    Main name.
	 * @param string                               $name_ua Ukrainian name.
	 * @param array<int, array{0:string,1:string}> $params  Variation attributes.
	 * @return array{0:string,1:string}
	 */
	private static function names_with_variant( string $name, string $name_ua, array $params ): array {
		foreach ( $params as $pair ) {
			$value = Marketplace_Text::clean( $pair[1] ?? '' );

			if ( '' === $value ) {
				continue;
			}

			foreach ( array( 'name', 'name_ua' ) as $var ) {
				// Whole words only: the size «L» is not already in «turtleneck».
				if ( '' !== $$var && ! preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $value, '/' ) . '(?![\p{L}\p{N}])/iu', $$var ) ) {
					$$var .= ' ' . $value;
				}
			}
		}

		return array( $name, $name_ua );
	}

	/**
	 * Parse «path part=rz_id» lines.
	 *
	 * @param string $text Rules text.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function parse_rules( string $text ): array {
		$rules = array();

		foreach ( (array) preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}

			$at    = strrpos( $line, '=' );
			$match = Marketplace_Text::lower( trim( substr( $line, 0, $at ) ) );
			$id    = preg_replace( '/\D+/', '', substr( $line, $at + 1 ) );

			if ( '' !== $match && '' !== $id ) {
				$rules[] = array( (string) preg_replace( '/\s*>\s*/u', '>', $match ), (string) $id );
			}
		}

		return $rules;
	}

	/**
	 * ROZETKA category id of a shop category path, by the first matching rule.
	 *
	 * @param string                               $path  «A > B > C».
	 * @param array<int, array{0:string,1:string}> $rules Parsed rules.
	 * @return string
	 */
	public static function rz_id_for_path( string $path, array $rules ): string {
		$haystack = (string) preg_replace( '/\s*>\s*/u', '>', Marketplace_Text::lower( $path ) );

		foreach ( $rules as $rule ) {
			// The rule names the end of the path, so «одяг» does not tag «одяг > куртки»,
			// while «куртки» tags «одяг > куртки».
			$tail = '>' . $rule[0];

			if ( $haystack === $rule[0] || ( strlen( $haystack ) > strlen( $tail ) && substr( $haystack, -strlen( $tail ) ) === $tail ) ) {
				return $rule[1];
			}
		}

		return '';
	}

	/**
	 * CDATA section (a «]]>» inside is split so the section stays valid).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function cdata( string $text ): string {
		return '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', Marketplace_Text::xml_safe( $text ) ) . ']]>';
	}

	/**
	 * One element line.
	 *
	 * @param string $indent Indent.
	 * @param string $name   Element name.
	 * @param string $value  Text.
	 * @return string
	 */
	private static function tag( string $indent, string $name, string $value ): string {
		return $indent . '<' . $name . '>' . Marketplace_Text::xml_text( $value ) . '</' . $name . ">\n";
	}

	/**
	 * Cut a text to a number of characters.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function truncate( string $text, int $max ): string {
		return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max ) ) : $text;
	}
}
