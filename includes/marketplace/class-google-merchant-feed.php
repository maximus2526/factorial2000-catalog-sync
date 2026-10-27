<?php
/**
 * Google Merchant Center product feed (RSS 2.0 with the g: namespace).
 *
 * Every purchasable product and every variation is one <item>; variations of a variable
 * product share <g:item_group_id> and carry their own colour and size.
 *
 * Spec: https://support.google.com/merchants/answer/7052112
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000CS;

defined( 'ABSPATH' ) || exit;

/**
 * Google Merchant feed.
 */
class Google_Merchant_Feed extends Marketplace_Feed {

	/**
	 * Limits of the Google specification.
	 */
	const MAX_ID                = 50;
	const MAX_TITLE             = 150;
	const MAX_DESCRIPTION       = 5000;
	const MAX_BRAND             = 70;
	const MAX_MPN               = 70;
	const MAX_COLOR_PART        = 40;
	const MAX_COLOR             = 100;
	const MAX_SIZE              = 100;
	const MAX_MATERIAL          = 200;
	const MAX_PATTERN           = 100;
	const MAX_PRODUCT_TYPE      = 750;
	const MAX_ADDITIONAL_IMAGES = 10;

	/**
	 * Brand values Google rejects as placeholders.
	 */
	const PLACEHOLDER_BRANDS = array( 'n/a', 'na', 'generic', 'no brand', 'nobrand', 'none', 'unknown', '-' );

	/**
	 * Normalized attribute names for the optional apparel attributes.
	 */
	const MATERIAL_KEYS = array( 'матеріал', 'материал', 'material', 'тканина', 'ткань' );
	const PATTERN_KEYS  = array( 'візерунок', 'узор', 'pattern', 'малюнок', 'рисунок' );
	const GENDER_KEYS   = array( 'стать', 'пол', 'gender' );

	/**
	 * Skip reasons collected while one unit is processed.
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
		return 'google';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return 'Google Merchant';
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Фід товарів для Google Merchant Center (безкоштовні лістинги й Shopping-реклама): RSS 2.0 з простором імен g:. Кожен товар і кожен різновид — окремий item з кольором, розміром, брендом, ціною, знижкою й наявністю; різновиди одного товару зв’язані через item_group_id. Посилання на файл вказується в Merchant Center як джерело даних.', 'factorial2000-catalog-sync' );
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
			'apparel'    => __( 'Одяг і взуття', 'factorial2000-catalog-sync' ),
			'categories' => __( 'Категорії Google', 'factorial2000-catalog-sync' ),
			'advanced'   => __( 'Додатково', 'factorial2000-catalog-sync' ),
		);
	}

	/**
	 * Settings schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields(): array {
		return array(
			'shop_name'             => array(
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
			'shop_description'      => array(
				'group'       => 'basic',
				'type'        => 'text',
				'label'       => __( 'Опис магазину', 'factorial2000-catalog-sync' ),
				'description' => __( 'Потрапляє в заголовок фіда (channel). Якщо порожньо, береться назва магазину.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
			'site_url'              => array(
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
			'language'              => array(
				'group'       => 'basic',
				'type'        => 'preset',
				'label'       => __( 'Мова фіда', 'factorial2000-catalog-sync' ),
				'description' => __( 'Назви, описи й категорії беруться цією мовою (потрібен плагін Factorial2000 Translator; без нього — як є на сайті). Мова має збігатися з мовою сайту, на який веде посилання.', 'factorial2000-catalog-sync' ),
				'default'     => 'uk',
				'options'     => array(
					'uk' => __( 'Українська', 'factorial2000-catalog-sync' ),
					'ru' => __( 'Російська', 'factorial2000-catalog-sync' ),
				),
			),
			'currency'              => array(
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
			'id_prefix'             => array(
				'group'       => 'products',
				'type'        => 'preset',
				'label'       => __( 'Префікс ID товару', 'factorial2000-catalog-sync' ),
				'description' => __( 'g:id = префікс + ID товару на сайті. Якщо ви переходите з іншого плагіна фідів, залиште той самий префікс: інакше Google вважатиме товари новими, а старі зникнуть. «gla_» використовують Google for WooCommerce (Google Listings & Ads) і CTX Feed.', 'factorial2000-catalog-sync' ),
				'default'     => 'gla_',
				'options'     => array(
					'gla_' => __( 'gla_ (як у Google for WooCommerce і CTX Feed)', 'factorial2000-catalog-sync' ),
					''     => __( 'Без префікса', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'text',
					'label' => __( 'Свій префікс…', 'factorial2000-catalog-sync' ),
				),
			),
			'include_unpublished'   => array(
				'group'       => 'products',
				'type'        => 'checkbox',
				'label'       => __( 'Неопубліковані товари', 'factorial2000-catalog-sync' ),
				'checkbox'    => __( 'Додавати у фід і чернетки', 'factorial2000-catalog-sync' ),
				'description' => __( 'За замовчуванням у фід потрапляють лише опубліковані товари.', 'factorial2000-catalog-sync' ),
				'default'     => '0',
			),
			'regular_price_only'    => array(
				'group'       => 'products',
				'type'        => 'checkbox',
				'label'       => __( 'Знижки', 'factorial2000-catalog-sync' ),
				'checkbox'    => __( 'Не передавати знижки — лише основну ціну', 'factorial2000-catalog-sync' ),
				'description' => __( 'У g:price іде звичайна ціна товару, g:sale_price не додається, навіть якщо на сайті діє акційна ціна.', 'factorial2000-catalog-sync' ),
				'default'     => '0',
			),
			'exclude_out_of_stock'  => array(
				'group'       => 'products',
				'type'        => 'checkbox',
				'label'       => __( 'Товари, яких немає в наявності', 'factorial2000-catalog-sync' ),
				'checkbox'    => __( 'Не додавати у фід товари без наявності', 'factorial2000-catalog-sync' ),
				'description' => __( 'За замовчуванням вони потрапляють у фід зі статусом out_of_stock, і Google показує їх як «немає в наявності» без переоцінювання.', 'factorial2000-catalog-sync' ),
				'default'     => '0',
			),
			'default_brand'         => array(
				'group'       => 'brand',
				'type'        => 'preset',
				'label'       => __( 'Бренд, якщо його не вказано', 'factorial2000-catalog-sync' ),
				'description' => __( 'Підставляється товарам без бренду. Google не приймає значення на кшталт «N/A», «Generic» чи «No brand»: для товарів без бренду краще нічого не вказувати.', 'factorial2000-catalog-sync' ),
				'default'     => '',
				'options'     => array(
					''            => __( 'Не вказувати', 'factorial2000-catalog-sync' ),
					'{site_name}' => __( 'Назва сайту (з налаштувань WordPress)', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'text',
					'label' => __( 'Свій бренд…', 'factorial2000-catalog-sync' ),
				),
			),
			'gender'                => array(
				'group'       => 'apparel',
				'type'        => 'preset',
				'label'       => __( 'Стать (gender)', 'factorial2000-catalog-sync' ),
				'description' => __( 'Для одягу Google вимагає стать. Якщо у товара є атрибут «Стать», береться він; інакше — це значення для всіх товарів.', 'factorial2000-catalog-sync' ),
				'default'     => '',
				'options'     => array(
					''       => __( 'Не вказувати', 'factorial2000-catalog-sync' ),
					'unisex' => __( 'unisex — унісекс', 'factorial2000-catalog-sync' ),
					'male'   => __( 'male — чоловіча', 'factorial2000-catalog-sync' ),
					'female' => __( 'female — жіноча', 'factorial2000-catalog-sync' ),
				),
			),
			'age_group'             => array(
				'group'       => 'apparel',
				'type'        => 'preset',
				'label'       => __( 'Вікова група (age_group)', 'factorial2000-catalog-sync' ),
				'description' => __( 'Для одягу Google вимагає вікову групу. Значення діє для всіх товарів.', 'factorial2000-catalog-sync' ),
				'default'     => '',
				'options'     => array(
					''        => __( 'Не вказувати', 'factorial2000-catalog-sync' ),
					'adult'   => __( 'adult — дорослі', 'factorial2000-catalog-sync' ),
					'kids'    => __( 'kids — діти', 'factorial2000-catalog-sync' ),
					'toddler' => __( 'toddler — малюки', 'factorial2000-catalog-sync' ),
					'infant'  => __( 'infant — немовлята', 'factorial2000-catalog-sync' ),
					'newborn' => __( 'newborn — новонароджені', 'factorial2000-catalog-sync' ),
				),
			),
			'google_category'       => array(
				'group'       => 'categories',
				'type'        => 'preset',
				'label'       => __( 'Категорія Google за замовчуванням', 'factorial2000-catalog-sync' ),
				'description' => __( 'Таксономія Google (google_product_category): номер або повний шлях англійською. Список: https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt', 'factorial2000-catalog-sync' ),
				'default'     => '',
				'options'     => array(
					''     => __( 'Не вказувати (Google визначить сам)', 'factorial2000-catalog-sync' ),
					'166'  => __( '166 — Apparel & Accessories (одяг і аксесуари)', 'factorial2000-catalog-sync' ),
					'1604' => __( '1604 — Apparel & Accessories > Clothing (одяг)', 'factorial2000-catalog-sync' ),
					'187'  => __( '187 — Apparel & Accessories > Shoes (взуття)', 'factorial2000-catalog-sync' ),
				),
				'custom'      => array(
					'type'  => 'text',
					'label' => __( 'Інша категорія…', 'factorial2000-catalog-sync' ),
				),
			),
			'google_category_rules' => array(
				'group'       => 'categories',
				'type'        => 'textarea',
				'label'       => __( 'Правила: категорія магазину → категорія Google', 'factorial2000-catalog-sync' ),
				'description' => __( 'По одному правилу в рядку: частина шляху категорії=номер Google, наприклад «одяг>куртки=5598» або «взуття=187». Спрацьовує перше правило, що збіглося; решті товарів дістається категорія за замовчуванням.', 'factorial2000-catalog-sync' ),
				'default'     => '',
			),
			'mpn_meta_key'          => array(
				'group'       => 'advanced',
				'type'        => 'preset',
				'label'       => __( 'Код постачальника (MPN)', 'factorial2000-catalog-sync' ),
				'description' => __( 'Звідки брати значення для g:mpn. Google вимагає MPN, якщо у товара немає GTIN.', 'factorial2000-catalog-sync' ),
				'default'     => 'f2000cs-updater-vendor',
				'options'     => array(
					'f2000cs-updater-vendor'       => __( 'Код постачальника з синхронізації Factorial2000', 'factorial2000-catalog-sync' ),
					Marketplace_WC_Source::MPN_SUPPLIER_THEN_SKU => __( 'Код постачальника, а якщо його немає — артикул (SKU)', 'factorial2000-catalog-sync' ),
					Marketplace_WC_Source::MPN_SKU => __( 'Артикул (SKU) товару — як у CTX Feed', 'factorial2000-catalog-sync' ),
					'prom-xml-updater-vendor'      => __( 'Код із плагіна prom-xml-updater', 'factorial2000-catalog-sync' ),
					''                             => __( 'Не передавати', 'factorial2000-catalog-sync' ),
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
	 * Head: declaration, <rss> and the <channel> header.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public function head( array $settings ): string {
		$s = $this->with_defaults( $settings );

		foreach ( array( 'shop_name', 'site_url' ) as $key ) {
			$s[ $key ] = Marketplace_Form::resolve( $s[ $key ] );
		}

		$description = Marketplace_Text::clean( $s['shop_description'] );

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n"
			. "  <channel>\n"
			. '    <title>' . Marketplace_Text::xml_text( $s['shop_name'] ) . "</title>\n"
			. '    <link>' . Marketplace_Text::xml_text( $s['site_url'] ) . "</link>\n"
			. '    <description>' . Marketplace_Text::xml_text( '' !== $description ? $description : $s['shop_name'] ) . "</description>\n";
	}

	/**
	 * The channel has no category list: the document starts with the first item.
	 *
	 * @param array<string,mixed>       $settings Settings.
	 * @param Marketplace_Category_Tree $tree     Unused.
	 * @return string
	 */
	public function document_head( array $settings, Marketplace_Category_Tree $tree ): string {
		return $this->head( $settings );
	}

	/**
	 * Tail.
	 *
	 * @return string
	 */
	public function tail(): string {
		return "  </channel>\n</rss>\n";
	}

	/**
	 * Line that opens an item.
	 *
	 * @return string
	 */
	public function offer_line_prefix(): string {
		return '      <item>';
	}

	/**
	 * Group of an item block (its g:item_group_id).
	 *
	 * @param string $block One item.
	 * @return string
	 */
	public function offer_group_id( string $block ): string {
		return preg_match( '/<g:item_group_id>([^<]*)</', $block, $m ) ? $m[1] : '';
	}

	/**
	 * Labels of the skip reasons.
	 *
	 * @return array<string,string>
	 */
	public function skip_reason_labels(): array {
		return parent::skip_reason_labels() + array(
			'no_link'      => __( 'немає посилання на товар', 'factorial2000-catalog-sync' ),
			'out_of_stock' => __( 'немає в наявності (виключено в налаштуваннях)', 'factorial2000-catalog-sync' ),
		);
	}

	// ------------------------------------------------------------------
	// Units
	// ------------------------------------------------------------------

	/**
	 * Convert one unit to items.
	 *
	 * @param array<string,mixed>       $unit     Unit.
	 * @param Marketplace_Category_Tree $tree     Unused (Google has no category list).
	 * @param array<string,mixed>       $settings Settings.
	 * @return array{xml:string,exported:int,skipped:int,excluded:array<int,string>,used_ids:array<int,string>,reasons:array<string,array{count:int,examples:array<int,string>}>}
	 */
	public function process_unit( array $unit, Marketplace_Category_Tree $tree, array $settings ): array {
		$settings      = $this->with_defaults( $settings );
		$unit          = self::without_sale_prices( $unit, $settings );
		$this->reasons = array();
		$res           = $this->result();

		switch ( $unit['kind'] ?? '' ) {
			case 'group':
				$this->process_group( $res, $unit['parent'] ?? null, (array) ( $unit['children'] ?? array() ), $settings );
				break;

			case 'lone':
				$this->add_item( $res, (array) $unit['row'], $unit['parent'] ?? null, null, $settings );
				break;

			case 'simple':
				$this->add_item( $res, (array) $unit['row'], null, null, $settings );
				break;
		}

		$res['reasons'] = $this->reasons;

		return $res;
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
	 * Variable product: one item per published variation.
	 *
	 * @param array<string,mixed>              $res          Result (changed).
	 * @param array<string,string>|null        $parent_row   Parent row.
	 * @param array<int, array<string,string>> $raw_children Variation rows.
	 * @param array<string,mixed>              $settings     Settings.
	 * @return void
	 */
	private function process_group( array &$res, $parent_row, array $raw_children, array $settings ): void {
		$children = array();

		foreach ( $raw_children as $child ) {
			if ( Prom_Feed::publish_ok( $child, $settings ) ) {
				$children[] = $child;
			}
		}

		if ( ! $children ) {
			return;
		}

		$children  = Prom_Variations::sort_rows_by_id( $children );
		$hints     = array();
		$axis_list = array();

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

			$this->add_item( $res, $child, $parent_row, $params, $settings );
		}
	}

	/**
	 * Build one item and add it to the result.
	 *
	 * @param array<string,mixed>                       $res        Result (changed).
	 * @param array<string,string>                      $row        Product or variation row.
	 * @param array<string,string>|null                 $parent_row Parent row of a variation.
	 * @param array<int, array{0:string,1:string}>|null $params     Resolved variation attributes.
	 * @param array<string,mixed>                       $settings   Settings.
	 * @return void
	 */
	private function add_item( array &$res, array $row, $parent_row, $params, array $settings ): void {
		if ( ! Prom_Feed::publish_ok( $row, $settings ) ) {
			$this->skip( $res, 'unpublished', $row, $parent_row );

			return;
		}

		list( $item, $reason ) = $this->compose_item( $row, $parent_row, null === $params ? array() : $params, $settings );

		if ( null === $item ) {
			$this->skip( $res, $reason, $row, $parent_row );

			return;
		}

		$res['xml']       .= $item;
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
	// Item
	// ------------------------------------------------------------------

	/**
	 * XML of one item, or the skip reason when the product cannot be listed.
	 *
	 * @param array<string,string>                 $row        Row.
	 * @param array<string,string>|null            $parent_row Parent row of a variation.
	 * @param array<int, array{0:string,1:string}> $params     Resolved variation attributes.
	 * @param array<string,mixed>                  $settings   Settings.
	 * @return array{0:?string,1:string} Item XML (null when skipped) and the skip reason.
	 */
	private function compose_item( array $row, $parent_row, array $params, array $settings ): array {
		$lang = 'ru' === $settings['language'] ? 'ru' : 'uk';

		// Name.
		$name_raw = Marketplace_Text::clean( $row['Name'] ?? '' );

		if ( '' === $name_raw && $parent_row ) {
			$name_raw = Marketplace_Text::clean( $parent_row['Name'] ?? '' );
		}

		$title = Marketplace_Text::clean( self::plain_text( self::pick_language( $name_raw, $lang ) ) );

		if ( ! preg_match( '/\p{L}/u', $title ) ) {
			return array( null, 'no_name' );
		}

		// Id.
		$offer_id = Marketplace_Text::clean( $row['ID'] ?? '' );

		if ( '' === $offer_id ) {
			$offer_id = Marketplace_Text::clean( $row['SKU'] ?? '' );
		}

		if ( '' === $offer_id ) {
			return array( null, 'no_id' );
		}

		$prefix = preg_replace( '/[^A-Za-z0-9_\-.]/', '', Marketplace_Form::resolve( $settings['id_prefix'] ) );
		$id     = mb_substr( $prefix . $offer_id, 0, self::MAX_ID );

		// Price.
		list( $price, $regular ) = Prom_Feed::pick_price( $row );

		if ( null === $price && $parent_row ) {
			list( $price, $regular ) = Prom_Feed::pick_price( $parent_row );
		}

		if ( null === $price || $price <= 0 ) {
			return array( null, 'no_price' );
		}

		// Link.
		$link = self::first_url( $row['Permalink'] ?? '', $parent_row ? ( $parent_row['Permalink'] ?? '' ) : '' );

		if ( '' === $link ) {
			return array( null, 'no_link' );
		}

		// Pictures.
		$images = Marketplace_Text::parse_image_list( $row['Images'] ?? '', 1 + self::MAX_ADDITIONAL_IMAGES );

		if ( ! $images && $parent_row ) {
			$images = Marketplace_Text::parse_image_list( $parent_row['Images'] ?? '', 1 + self::MAX_ADDITIONAL_IMAGES );
		}

		if ( ! $images ) {
			return array( null, 'no_pictures' );
		}

		// Availability.
		$availability = self::availability( $row );

		if ( 'out_of_stock' === $availability && '1' === (string) $settings['exclude_out_of_stock'] ) {
			return array( null, 'out_of_stock' );
		}

		// Attributes.
		$candidates = $params;

		foreach ( Prom_Variations::collect_params( $row, true ) as $pair ) {
			$candidates[] = $pair;
		}

		if ( $parent_row ) {
			foreach ( Prom_Variations::collect_params( $parent_row, true ) as $pair ) {
				$candidates[] = $pair;
			}
		}

		$color    = self::color( self::pick_attribute( $candidates, 'color', $lang ) );
		$size     = self::truncate( self::pick_attribute( $candidates, 'size', $lang ), self::MAX_SIZE );
		$material = self::truncate( self::pick_attribute( $candidates, 'material', $lang ), self::MAX_MATERIAL );
		$pattern  = self::truncate( self::pick_attribute( $candidates, 'pattern', $lang ), self::MAX_PATTERN );
		$gender   = self::gender( self::pick_attribute( $candidates, 'gender', $lang ), (string) $settings['gender'] );

		// A variation title must tell the variants apart.
		if ( $parent_row ) {
			foreach ( array( $color, $size ) as $variant ) {
				if ( '' !== $variant && false === mb_stripos( $title, $variant ) ) {
					$title .= ' - ' . $variant;
				}
			}
		}

		$title = self::truncate( $title, self::MAX_TITLE );

		// Description: plain text, never empty.
		$raw_description = Prom_Feed::pick_raw_description( $row, $parent_row );
		$description     = '' !== $raw_description ? self::plain_text( self::pick_language( $raw_description, $lang ) ) : '';
		$description     = self::truncate( '' !== $description ? $description : $title, self::MAX_DESCRIPTION );

		// Categories.
		$cats = Marketplace_Text::clean( $row['Categories'] ?? '' );

		if ( '' === $cats && $parent_row ) {
			$cats = Marketplace_Text::clean( $parent_row['Categories'] ?? '' );
		}

		$paths     = Marketplace_Text::parse_category_paths( self::pick_language( $cats, $lang ) );
		$deepest   = $paths ? $paths[ count( $paths ) - 1 ] : array();
		$path_text = implode( ' > ', $deepest );

		// Identifiers.
		$brand = self::brand( $row, $parent_row, $settings );
		$gtin  = self::valid_gtin( $row['GTIN, UPC, EAN, or ISBN'] ?? '' );
		$mpn   = mb_substr( Prom_Feed::extract_mpn( $row, $parent_row ), 0, self::MAX_MPN );

		// Money.
		$currency = strtoupper( Marketplace_Form::resolve( $settings['currency'] ) );
		$fmt      = static function ( float $value ) use ( $currency ): string {
			return number_format( $value, 2, '.', '' ) . ' ' . $currency;
		};

		$group_id = $parent_row ? Marketplace_Text::clean( $parent_row['ID'] ?? '' ) : '';
		$ind      = '        ';
		$xml      = "      <item>\n";
		$xml     .= self::tag( $ind, 'g:id', $id );
		$xml     .= self::tag( $ind, 'g:title', $title );
		$xml     .= self::tag( $ind, 'g:description', $description );

		if ( '' !== $group_id ) {
			$xml .= self::tag( $ind, 'g:item_group_id', mb_substr( $group_id, 0, self::MAX_ID ) );
		}

		$xml .= self::tag( $ind, 'link', $link );

		if ( '' !== $path_text ) {
			$xml .= self::tag( $ind, 'g:product_type', mb_substr( $path_text, 0, self::MAX_PRODUCT_TYPE ) );
		}

		$google_category = $this->google_category( $path_text, $settings );

		if ( '' !== $google_category ) {
			$xml .= self::tag( $ind, 'g:google_product_category', $google_category );
		}

		$xml .= self::tag( $ind, 'g:image_link', $images[0] );
		$xml .= self::tag( $ind, 'g:condition', 'new' );
		$xml .= self::tag( $ind, 'g:availability', $availability );

		// g:price is the regular price; g:sale_price only while the product is really discounted.
		if ( null !== $regular && $regular > $price ) {
			$xml .= self::tag( $ind, 'g:price', $fmt( $regular ) );
			$xml .= self::tag( $ind, 'g:sale_price', $fmt( $price ) );
		} else {
			$xml .= self::tag( $ind, 'g:price', $fmt( $price ) );
		}

		if ( '' !== $gtin ) {
			$xml .= self::tag( $ind, 'g:gtin', $gtin );
		}

		if ( '' !== $mpn ) {
			$xml .= self::tag( $ind, 'g:mpn', $mpn );
		}

		if ( '' !== $brand ) {
			$xml .= self::tag( $ind, 'g:brand', $brand );
		}

		// A variation URL carries the chosen attributes: the product page is its canonical.
		$canonical = $parent_row ? self::first_url( $parent_row['Permalink'] ?? '', '' ) : '';

		if ( '' !== $canonical && $canonical !== $link ) {
			$xml .= self::tag( $ind, 'g:canonical_link', $canonical );
		}

		foreach ( array_slice( $images, 1, self::MAX_ADDITIONAL_IMAGES ) as $url ) {
			$xml .= self::tag( $ind, 'g:additional_image_link', $url );
		}

		// Google treats the identifiers as present unless told otherwise.
		if ( '' === $gtin && '' === $mpn ) {
			$xml .= self::tag( $ind, 'g:identifier_exists', 'no' );
		}

		if ( '' !== $color ) {
			$xml .= self::tag( $ind, 'g:color', $color );
		}

		if ( '' !== $size ) {
			$xml .= self::tag( $ind, 'g:size', $size );
		}

		if ( '' !== $gender ) {
			$xml .= self::tag( $ind, 'g:gender', $gender );
		}

		$age_group = (string) $settings['age_group'];

		if ( in_array( $age_group, array( 'newborn', 'infant', 'toddler', 'kids', 'adult' ), true ) ) {
			$xml .= self::tag( $ind, 'g:age_group', $age_group );
		}

		if ( '' !== $material ) {
			$xml .= self::tag( $ind, 'g:material', $material );
		}

		if ( '' !== $pattern ) {
			$xml .= self::tag( $ind, 'g:pattern', $pattern );
		}

		return array( $xml . "      </item>\n", '' );
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

	// ------------------------------------------------------------------
	// Field helpers
	// ------------------------------------------------------------------

	/**
	 * Language variant of a possibly translator-tagged text.
	 *
	 * @param string $text Text.
	 * @param string $lang "ru" or "uk".
	 * @return string
	 */
	private static function pick_language( string $text, string $lang ): string {
		list( $main, $ukrainian ) = Prom_Feed::split_languages( $text );

		return 'ru' === $lang ? $main : $ukrainian;
	}

	/**
	 * HTML to plain text: tags dropped, entities decoded, white space collapsed.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function plain_text( $html ): string {
		$html = (string) preg_replace( '/<!--.*?-->/su', '', (string) $html );
		$html = strip_shortcodes( $html );
		$html = (string) preg_replace( '~<(?:/(?:p|div|li|ul|ol|h[1-6]|tr|table)|br\s*/?)\s*>~iu', ' ', $html );
		$text = html_entity_decode( Marketplace_Text::strip_tags_to_space( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
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

	/**
	 * First well-formed http(s) URL.
	 *
	 * @param string $first  Preferred URL.
	 * @param string $second Fallback URL.
	 * @return string
	 */
	private static function first_url( $first, $second ): string {
		foreach ( array( $first, $second ) as $url ) {
			$url = Marketplace_Text::clean( $url );

			if ( preg_match( '#^https?://#i', $url ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Google availability of a row.
	 *
	 * @param array<string,string> $row Row.
	 * @return string in_stock, backorder or out_of_stock
	 */
	public static function availability( array $row ): string {
		if ( ! Prom_Feed::row_available( $row ) ) {
			return 'out_of_stock';
		}

		$count    = Marketplace_Text::parse_float( $row['Stock'] ?? '' );
		$in_stock = Marketplace_Text::parse_bool( $row['In stock?'] ?? '' );

		// Purchasable only because backorders are allowed.
		return ( ! $in_stock && ( null === $count || $count <= 0 ) ) ? 'backorder' : 'in_stock';
	}

	/**
	 * Brand: the product's own, else the default; placeholders are dropped.
	 *
	 * @param array<string,string>      $row        Row.
	 * @param array<string,string>|null $parent_row Parent row.
	 * @param array<string,mixed>       $settings   Settings.
	 * @return string
	 */
	private static function brand( array $row, $parent_row, array $settings ): string {
		foreach ( array( Prom_Feed::extract_vendor( $row, $parent_row ), Marketplace_Text::clean( Marketplace_Form::resolve( $settings['default_brand'] ?? '' ) ) ) as $brand ) {
			$brand = Marketplace_Text::clean( $brand );

			if ( '' !== $brand && ! in_array( Marketplace_Text::lower( $brand ), self::PLACEHOLDER_BRANDS, true ) ) {
				return mb_substr( $brand, 0, self::MAX_BRAND );
			}
		}

		return '';
	}

	/**
	 * GTIN when it is 8, 12, 13 or 14 digits with a valid GS1 check digit.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function valid_gtin( $value ): string {
		$digits = (string) preg_replace( '/\D+/', '', (string) $value );
		$length = strlen( $digits );

		if ( ! in_array( $length, array( 8, 12, 13, 14 ), true ) ) {
			return '';
		}

		$sum = 0;

		for ( $i = 0; $i < $length - 1; $i++ ) {
			$digit = (int) $digits[ $length - 2 - $i ];
			$sum  += $digit * ( 0 === $i % 2 ? 3 : 1 );
		}

		return ( ( 10 - $sum % 10 ) % 10 ) === (int) $digits[ $length - 1 ] ? $digits : '';
	}

	/**
	 * Value of one kind of attribute among name/value pairs; the first single-valued match.
	 *
	 * @param array<int, array{0:string,1:string}> $pairs Attribute name/value pairs.
	 * @param string                               $kind  color, size, material, pattern or gender.
	 * @param string                               $lang  Feed language.
	 * @return string
	 */
	private static function pick_attribute( array $pairs, string $kind, string $lang ): string {
		foreach ( $pairs as $pair ) {
			$name = (string) $pair[0];
			$nk   = Prom_Variations::normalized_attr_key( $name );

			switch ( $kind ) {
				case 'color':
					$match = Prom_Variations::is_color_related_axis( $nk, $name );
					break;

				case 'size':
					$match = Prom_Variations::is_size_related_axis( $nk, $name );
					break;

				case 'material':
					$match = in_array( $nk, self::MATERIAL_KEYS, true );
					break;

				case 'pattern':
					$match = in_array( $nk, self::PATTERN_KEYS, true );
					break;

				default:
					$match = in_array( $nk, self::GENDER_KEYS, true );
			}

			if ( ! $match ) {
				continue;
			}

			$value = Marketplace_Text::clean( self::pick_language( (string) $pair[1], $lang ) );

			// A list of values is a choice among variations, not the value of this item.
			if ( '' === $value || Prom_Variations::is_multi_choice_value( $value ) ) {
				continue;
			}

			return $value;
		}

		return '';
	}

	/**
	 * Colour in Google's form: at most 40 characters per colour, no hex codes or bare numbers.
	 *
	 * @param string $value Raw colour.
	 * @return string
	 */
	private static function color( string $value ): string {
		$parts = array();

		foreach ( (array) preg_split( '#\s*/\s*#u', $value ) as $part ) {
			$part = Marketplace_Text::clean( $part );

			$is_hex_code = 1 === preg_match( '/^#[0-9a-f]{3,8}$/i', $part );

			if ( '' === $part || $is_hex_code || ctype_digit( $part ) ) {
				continue;
			}

			$parts[] = self::truncate( $part, self::MAX_COLOR_PART );
		}

		return self::truncate( implode( '/', array_slice( $parts, 0, 3 ) ), self::MAX_COLOR );
	}

	/**
	 * Gender: from the product attribute when it can be read, else the setting.
	 *
	 * @param string $attribute Attribute value.
	 * @param string $fallback  Setting.
	 * @return string male, female, unisex or ''
	 */
	private static function gender( string $attribute, string $fallback ): string {
		$value = Marketplace_Text::lower( $attribute );

		if ( '' !== $value ) {
			if ( false !== strpos( $value, 'уніс' ) || false !== strpos( $value, 'unisex' ) ) {
				return 'unisex';
			}

			if ( false !== strpos( $value, 'жін' ) || false !== strpos( $value, 'жен' ) || 'female' === $value ) {
				return 'female';
			}

			if ( false !== strpos( $value, 'чолов' ) || false !== strpos( $value, 'муж' ) || 'male' === $value ) {
				return 'male';
			}
		}

		return in_array( $fallback, array( 'male', 'female', 'unisex' ), true ) ? $fallback : '';
	}

	/**
	 * Google category of a product: the first matching rule, else the default.
	 *
	 * @param string              $path     Category path "A > B".
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function google_category( string $path, array $settings ): string {
		$normalize = static function ( string $text ): string {
			return (string) preg_replace( '/\s*>\s*/u', '>', Marketplace_Text::lower( Marketplace_Text::clean( $text ) ) );
		};

		$haystack = $normalize( $path );

		if ( '' !== $haystack ) {
			foreach ( self::parse_pairs( (string) $settings['google_category_rules'] ) as $part => $category ) {
				$needle = $normalize( (string) $part );

				if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
					return Marketplace_Text::clean( $category );
				}
			}
		}

		return Marketplace_Text::clean( $settings['google_category'] );
	}
}
