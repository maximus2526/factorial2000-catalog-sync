<?php
/**
 * Real-WordPress integration smoke test for the XML Editor + admin flow.
 *
 * Boots the actual site (wp-load.php) and exercises:
 *  - session source resolution (URL / file / local path)
 *  - XML_Editor load / get_categories / count_offers / get_offers / get_offer_ids
 *  - conditional filtering (only_in_stock, min/max price, search)
 *  - filtered XML generation (descendants, extra/excluded, oldprice strip, SKU prefix)
 *  - save to uploads
 *
 * MUST be run from the plugin root with a working WordPress install:
 *   php -d extension=mysqli -d mysqli.default_port=<port> tests/integration/wp-smoke-editor.php
 *
 * @package Factorial2000_Catalog_Sync
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$site    = getenv( 'F2000CS_SITE_PATH' ) ?: 'E:/LocalWP/woodmart-dev/app/public';
$plugdir = $site . '/wp-content/plugins/factorial2000-catalog-sync';

require $site . '/wp-load.php';

if ( ! function_exists( 'f2000cs_send_telegram_notification' ) ) {
	require_once $plugdir . '/includes/functions.php';
}
if ( ! class_exists( 'F2000CS\XML_Editor' ) ) {
	require_once $plugdir . '/includes/class-xml-editor.php';
}
if ( ! function_exists( 'f2000cs_xml_editor_session_dir' ) ) {
	require_once $plugdir . '/admin/xml-editor.php';
}

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
	global $pass, $fail;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " | {$label}" . ( $detail ? " | {$detail}" : '' ) . PHP_EOL;
	$ok ? $pass++ : $fail++;
}

// ---------------------------------------------------------------- session

$dir = f2000cs_xml_editor_session_dir();
check( 'session dir created under uploads', '' !== $dir && is_dir( $dir ), (string) $dir );

$token        = wp_generate_password( 20, false, false );
$session_file = $dir . '/' . $token . '.xml';

$fixture = '<?xml version="1.0" encoding="utf-8"?><yml_catalog><shop><name>Test</name><categories>' .
	'<category id="1" parentId="0">Одяг</category><category id="11" parentId="1">Футболки</category>' .
	'<category id="2">Взуття</category>' .
	'</categories><offers>' .
	'<offer id="1001" available="true"><name>Сорочка</name><price>300</price><oldprice>400</oldprice><categoryId>11</categoryId><picture>http://img.test/s1.jpg</picture></offer>' .
	'<offer id="1002" available="TRUE"><name>Штани</name><price>500</price><categoryId>1</categoryId></offer>' .
	'<offer id="2001" available="1"><name>Черевики</name><price>700</price><categoryId>2</categoryId></offer>' .
	'<offer id="9001" available="yes"><name>Аксесуар без категорії</name><price>99</price></offer>' .
	'</offers></shop></yml_catalog>';

check( 'session file write', false !== file_put_contents( $session_file, $fixture ) );
check( 'session path resolves', is_file( f2000cs_xml_editor_session_path( $token ) ) );
check( 'session path rejects traversal', '' === f2000cs_xml_editor_session_path( '../evil' ) );

// ---------------------------------------------------------------- resolve source

$fixture_file = sys_get_temp_dir() . '/f2000cs-smoke-source.xml';
file_put_contents( $fixture_file, $fixture );

$_FILES['xml_file'] = array( 'error' => 0, 'name' => 'smoke-feed.xml', 'tmp_name' => $fixture_file, 'size' => filesize( $fixture_file ) );
$resolved = f2000cs_xml_editor_resolve_source( 'file', '', $_FILES['xml_file'] );
check( 'resolve: uploaded file', '' === $resolved['error'] && false !== strpos( $resolved['content'], 'yml_catalog' ), $resolved['error'] );

$resolved = f2000cs_xml_editor_resolve_source( 'url', $fixture_file, array() );
check( 'resolve: local file path', '' === $resolved['error'] && false !== strpos( $resolved['content'], 'yml_catalog' ), $resolved['error'] );

$resolved = f2000cs_xml_editor_resolve_source( 'url', '', array() );
check( 'resolve: empty url error', '' !== $resolved['error'] && '' === $resolved['content'] );

$resolved = f2000cs_xml_editor_resolve_source( 'url', 'not-a-url', array() );
check( 'resolve: invalid url error', '' !== $resolved['error'] );

// ---------------------------------------------------------------- editor

$editor = new F2000CS\XML_Editor( $session_file );
check( 'editor loads real file', $editor->load() );
check( 'total offers = 4', 4 === $editor->get_total_offers(), (string) $editor->get_total_offers() );

$categories = $editor->get_categories();
$by_id      = array();
foreach ( $categories as $category ) {
	$by_id[ (string) $category['id'] ] = $category;
}
check( '3 real + 1 synthetic category', count( $categories ) === 4 );
check( 'parentId=0 normalized to root', isset( $by_id['1'] ) && '' === $by_id['1']['parent'] );
check( 'subcategory attached', isset( $by_id['11'] ) && '1' === $by_id['11']['parent'] );
check( 'ids are strings (JSON-safe)', is_string( $by_id['1']['id'] ?? null ) );
check( 'synthetic uncategorized category', isset( $by_id['__none__'] ) && 1 === (int) $by_id['__none__']['count'] );

// Category with descendants.
check( 'count cat 1 incl descendants = 2', 2 === (int) ( $by_id['1']['count'] ?? 0 ) );

// Offers by category (descendants included).
$offers = $editor->get_offers( array( '1' ), 200, 0, array() );
$ids    = array_column( $offers, 'id' );
check( 'offers for cat 1 = 2 (descendants!)', count( $ids ) === 2 && in_array( '1001', $ids, true ) && in_array( '1002', $ids, true ) );
check( 'offer meta (title/image/price)', isset( $offers[0]['title'], $offers[0]['image'], $offers[0]['price'] ) );
check( 'offer id is string', is_string( $offers[0]['id'] ?? null ) );

// Pagination.
$page1 = $editor->get_offers( array( '1' ), 1, 0, array() );
$page2 = $editor->get_offers( array( '1' ), 1, 1, array() );
check( 'pagination works', 1 === count( $page1 ) && 1 === count( $page2 ) && $page1[0]['id'] !== $page2[0]['id'] );

// Conditions (tolerant availability: TRUE/1/yes).
check( 'only_in_stock (tolerant) = 4', 4 === $editor->count_offers( array(), array( 'only_in_stock' => true ) ) );
check( 'min_price 600 = 1', 1 === $editor->count_offers( array(), array( 'min_price' => 600 ) ) );
check( 'max_price 350 = 2', 2 === $editor->count_offers( array(), array( 'max_price' => 350 ) ) );

// Search (Cyrillic case-insensitive).
check( 'search "сорочка" = 1', 1 === $editor->count_offers( array(), array( 'search' => 'сорочка' ) ) );
check( 'search by numeric id = 1', 1 === $editor->count_offers( array(), array( 'search' => '2001' ) ) );

// Uncategorized offers selectable.
$offers = $editor->get_offers( array( '__none__' ) );
check( 'uncategorized offer listed', 1 === count( $offers ) && '9001' === $offers[0]['id'] );

// offer_ids for select-all.
$ids = $editor->get_offer_ids( array( '1' ), array() );
check( 'offer_ids cat 1 = 2', count( $ids ) === 2 && is_string( $ids[0] ) );

// ---------------------------------------------------------------- generate

$result = $editor->generate( array( '11' ), array(), array(), array( 'only_in_stock' => false, 'keep_oldprice' => true, 'sku_prefix' => 'SM_' ) );
check( 'generate count = 1', 1 === $result['count'] );

$generated = simplexml_load_string( $result['xml'] );
$offer     = $generated->shop->offers->offer[0] ?? null;
check( 'generated offer id prefixed', $offer && 'SM_1001' === (string) $offer['id'] );
check( 'oldprice kept', $offer && '400' === (string) $offer->oldprice );
check( 'parent category kept', isset( $generated->shop->categories->category[0] ) && '1' === (string) $generated->shop->categories->category[0]['id'] );

// Extra + excluded with numeric ids.
$result = $editor->generate( array( '2' ), array( '1002' ), array( '2001' ), array() );
check( 'generate extra + excluded = 1', 1 === $result['count'] );
$generated = simplexml_load_string( $result['xml'] );
check( 'extra offer kept (1002)', isset( $generated->shop->offers->offer[0] ) && '1002' === (string) $generated->shop->offers->offer[0]['id'] );

// oldprice stripping.
$result = $editor->generate( array( '11' ), array(), array(), array( 'keep_oldprice' => false ) );
$generated = simplexml_load_string( $result['xml'] );
check( 'oldprice stripped', 0 === count( (array) $generated->shop->offers->offer[0]->oldprice ) );

// Save to real uploads dir.
$saved = $editor->save( $result['xml'] );
check(
	'save success + url',
	$saved['success'] && false !== strpos( $saved['url'], 'action=f2000cs_download_export' )
);
check( 'saved file exists', is_file( $saved['path'] ) );
if ( is_file( $saved['path'] ) ) {
	unlink( $saved['path'] );
}

// ---------------------------------------------------------------- cleanup

f2000cs_xml_editor_prune_sessions();
check( 'prune runs without errors', true );

unlink( $session_file );
unlink( $fixture_file );

echo PHP_EOL . "RESULT: {$pass} passed, {$fail} failed" . PHP_EOL;
exit( $fail > 0 ? 1 : 0 );
