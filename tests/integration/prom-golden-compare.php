<?php
/**
 * Golden-file comparison: PHP Prom_Feed port vs the original Python tool.
 *
 * Feeds the WooCommerce CSV export that wc_to_prom_yml.py consumed through the PHP
 * port and compares the result with the YML the Python tool produced from it.
 *
 * Usage:
 *   php -d memory_limit=3G tests/integration/prom-golden-compare.php <products.csv> <expected.yml> <config.json> [max-diffs]
 *
 * Dev tool only (the real data files are large and not part of the repository).
 *
 * @package Factorial2000_Catalog_Sync
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$repo = dirname( __DIR__, 2 );
$csv  = $argv[1] ?? '';
$yml  = $argv[2] ?? '';
$cfg  = $argv[3] ?? '';
$maxd = (int) ( $argv[4] ?? 15 );

if ( ! is_file( $csv ) || ! is_file( $yml ) || ! is_file( $cfg ) ) {
	fwrite( STDERR, "Usage: php prom-golden-compare.php <products.csv> <expected.yml> <config.json> [max-diffs]\n" );
	exit( 2 );
}

define( 'ABSPATH', $repo . '/' );
define( 'WP_DEBUG', false );

require_once $repo . '/vendor/autoload.php';
require_once $repo . '/tests/includes/wp-stubs.php';

foreach ( glob( $repo . '/includes/marketplace/*.php' ) as $file ) {
	if ( false === strpos( $file, 'registry' ) && false === strpos( $file, 'job' ) && false === strpos( $file, 'storage' ) && false === strpos( $file, 'wc-source' ) && false === strpos( $file, 'cli' ) ) {
		require_once $file;
	}
}

use F2000CS\Marketplace_Category_Tree;
use F2000CS\Marketplace_Text;
use F2000CS\Prom_Feed;
use F2000CS\Prom_Variations;

$config   = json_decode( (string) file_get_contents( $cfg ), true );
$settings = array(
	'shop_name'                    => $config['shop_name'] ?? '',
	'company_name'                 => $config['company_name'] ?? '',
	'site_url'                     => $config['site_url'] ?? '',
	'currency'                     => $config['currency'] ?? 'UAH',
	'max_pictures'                 => (int) ( $config['max_pictures'] ?? 10 ),
	'include_unpublished'          => ! empty( $config['include_unpublished'] ) ? '1' : '0',
	'default_vendor'               => $config['default_vendor'] ?? '',
	'vendors_fallback_to_default'  => ! empty( $config['vendors_fallback_to_default'] ) ? '1' : '0',
	'description_fallback_snippet' => $config['description_fallback_snippet'] ?? '',
	'default_portal_category_id'   => $config['default_portal_category_id'] ?? '',
);

$pairs = static function ( $map ) {
	$out = array();
	foreach ( (array) $map as $k => $v ) {
		$out[] = $k . '=' . $v;
	}
	return implode( "\n", $out );
};

$settings['vendor_aliases'] = $pairs( $config['vendor_aliases'] ?? array() );

$rules = array();
foreach ( (array) ( $config['portal_category_rules'] ?? array() ) as $r ) {
	$rules[] = ( $r['match_substring'] ?? '' ) . '=' . ( $r['portal_category_id'] ?? '' );
}
$settings['portal_category_rules'] = implode( "\n", $rules );

// ---------------------------------------------------------------- read CSV

$t0   = microtime( true );
$rows = array();
$fh   = fopen( $csv, 'r' );
$head = fgetcsv( $fh, 0, ',', '"', '' );
$head[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $head[0] );

while ( false !== ( $line = fgetcsv( $fh, 0, ',', '"', '' ) ) ) {
	if ( array( null ) === $line ) {
		continue;
	}
	$row = array();
	foreach ( $head as $i => $name ) {
		$row[ $name ] = $line[ $i ] ?? '';
	}
	$rows[] = $row;
}
fclose( $fh );

printf( "CSV rows: %d (%.1fs)\n", count( $rows ), microtime( true ) - $t0 );

// ---------------------------------------------------------------- units in the Python order

$clean = array( Marketplace_Text::class, 'clean' );

$by_sku        = array();
$variable_by   = array();
$children_by   = array();
$variable_ord  = array();
$var_parent_ord = array();

foreach ( $rows as $row ) {
	$sku   = Marketplace_Text::clean( $row['SKU'] ?? '' );
	$ptype = Marketplace_Text::lower( Marketplace_Text::clean( $row['Type'] ?? '' ) );

	if ( '' !== $sku ) {
		$by_sku[ $sku ] = $row;

		if ( 'variable' === $ptype ) {
			$variable_by[ $sku ] = $row;

			if ( ! in_array( $sku, $variable_ord, true ) ) {
				$variable_ord[] = $sku;
			}
		}
	}

	if ( 'variation' === $ptype ) {
		$psku = Marketplace_Text::clean( $row['Parent'] ?? '' );

		if ( '' !== $psku ) {
			$children_by[ $psku ][] = $row;

			if ( ! in_array( $psku, $var_parent_ord, true ) ) {
				$var_parent_ord[] = $psku;
			}
		}
	}
}

$parent_skus = $variable_ord;
foreach ( $var_parent_ord as $s ) {
	if ( ! in_array( $s, $parent_skus, true ) ) {
		$parent_skus[] = $s;
	}
}

$feed = new Prom_Feed();
$tree = new Marketplace_Category_Tree();

$tmp = tempnam( sys_get_temp_dir(), 'prom_php_' );
$out = fopen( $tmp, 'w' );

$exported = 0;
$skipped  = 0;
$excluded = array();
$used     = array();

$t0 = microtime( true );

$run = static function ( array $unit ) use ( $feed, $tree, $settings, &$exported, &$skipped, &$excluded, &$used, $out ) {
	$r         = $feed->process_unit( $unit, $tree, $settings );
	$exported += $r['exported'];
	$skipped  += $r['skipped'];
	$excluded  = array_merge( $excluded, $r['excluded'] );
	foreach ( $r['used_ids'] as $id ) {
		$used[ (string) $id ] = true;
	}
	fwrite( $out, $r['xml'] );
};

foreach ( $parent_skus as $sku ) {
	$parent = $variable_by[ $sku ] ?? ( $by_sku[ $sku ] ?? null );
	$run( array( 'kind' => 'group', 'parent' => $parent, 'children' => $children_by[ $sku ] ?? array() ) );
}

foreach ( $rows as $row ) {
	if ( 'variation' !== Marketplace_Text::lower( Marketplace_Text::clean( $row['Type'] ?? '' ) ) ) {
		continue;
	}

	$vid = Marketplace_Text::clean( $row['ID'] ?? '' );

	if ( Prom_Feed::publish_ok( $row, $settings ) && '' !== $vid && isset( $used[ $vid ] ) ) {
		continue;
	}

	$psku   = Marketplace_Text::clean( $row['Parent'] ?? '' );
	$parent = '' !== $psku ? ( $variable_by[ $psku ] ?? ( $by_sku[ $psku ] ?? null ) ) : null;

	$run( array( 'kind' => 'lone', 'row' => $row, 'parent' => $parent ) );
}

foreach ( $rows as $row ) {
	$t = Marketplace_Text::lower( Marketplace_Text::clean( $row['Type'] ?? '' ) );

	if ( 'variable' === $t || 'variation' === $t ) {
		continue;
	}

	$run( array( 'kind' => 'simple', 'row' => $row ) );
}

fclose( $out );

printf( "PHP: offers=%d categories=%d skipped=%d excluded_groups=%d (%.1fs)\n", $exported, $tree->count(), $skipped, count( $excluded ), microtime( true ) - $t0 );

// ---------------------------------------------------------------- offers → md5 map

/**
 * Stream a file of <offer> elements (or a whole feed) into id => md5.
 *
 * @param string $path    File.
 * @param bool   $wrapped Whether the file is a complete feed.
 * @return array<string,string>
 */
function offer_hashes( string $path, bool $wrapped ): array {
	$xml = $path;

	if ( ! $wrapped ) {
		$xml = $path . '.wrapped';
		$w   = fopen( $xml, 'w' );
		fwrite( $w, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<offers>\n" );
		$r = fopen( $path, 'r' );
		stream_copy_to_stream( $r, $w );
		fclose( $r );
		fwrite( $w, "</offers>\n" );
		fclose( $w );
	}

	$reader = new XMLReader();
	$reader->open( $xml, null, LIBXML_NONET | LIBXML_PARSEHUGE );
	$map = array();

	while ( $reader->read() ) {
		if ( XMLReader::ELEMENT === $reader->nodeType && 'offer' === $reader->name ) {
			$id         = (string) $reader->getAttribute( 'id' );
			$map[ $id ] = md5( $reader->readOuterXML() );
			$reader->next();
		}
	}

	$reader->close();

	return $map;
}

$mine     = offer_hashes( $tmp, false );
$expected = offer_hashes( $yml, true );

printf( "offers: PHP=%d expected=%d\n", count( $mine ), count( $expected ) );

$only_php = array_diff_key( $mine, $expected );
$only_exp = array_diff_key( $expected, $mine );
$changed  = array();

foreach ( array_intersect_key( $mine, $expected ) as $id => $h ) {
	if ( $expected[ $id ] !== $h ) {
		$changed[] = (string) $id;
	}
}

printf( "only in PHP: %d | only in expected: %d | different content: %d\n", count( $only_php ), count( $only_exp ), count( $changed ) );

// ---------------------------------------------------------------- categories

$exp_cats = array();
$reader   = new XMLReader();
$reader->open( $yml, null, LIBXML_NONET | LIBXML_PARSEHUGE );

while ( $reader->read() ) {
	if ( XMLReader::ELEMENT === $reader->nodeType && 'category' === $reader->name ) {
		$exp_cats[ (string) $reader->getAttribute( 'id' ) ] = array( (string) $reader->getAttribute( 'parentId' ), $reader->readString() );
	}
	if ( XMLReader::ELEMENT === $reader->nodeType && 'offers' === $reader->name ) {
		break;
	}
}
$reader->close();

$cat_diff = 0;
foreach ( $tree->all() as $id => $node ) {
	$e = $exp_cats[ (string) $id ] ?? null;
	if ( ! $e || $e[1] !== $node['name'] || $e[0] !== ( null === $node['parent'] ? '' : (string) $node['parent'] ) ) {
		++$cat_diff;
	}
}
printf( "categories: PHP=%d expected=%d different=%d\n", $tree->count(), count( $exp_cats ), $cat_diff );

// ---------------------------------------------------------------- show differences

$show = 0;
foreach ( $changed as $id ) {
	if ( $show++ >= $maxd ) {
		break;
	}

	$a = extract_offer( $tmp, $id, false );
	$b = extract_offer( $yml, $id, true );

	echo "\n--- offer {$id} differs ---\n";
	$la = explode( "\n", $a );
	$lb = explode( "\n", $b );
	$n  = max( count( $la ), count( $lb ) );
	$k  = 0;

	for ( $i = 0; $i < $n && $k < 6; $i++ ) {
		if ( ( $la[ $i ] ?? '' ) !== ( $lb[ $i ] ?? '' ) ) {
			echo '  PHP : ' . substr( $la[ $i ] ?? '(none)', 0, 220 ) . "\n";
			echo '  PY  : ' . substr( $lb[ $i ] ?? '(none)', 0, 220 ) . "\n";
			++$k;
		}
	}
}

foreach ( array_slice( array_keys( $only_php ), 0, 5 ) as $id ) {
	echo "\nonly in PHP: {$id}\n";
}
foreach ( array_slice( array_keys( $only_exp ), 0, 5 ) as $id ) {
	echo "\nonly in expected: {$id}\n";
}

/**
 * Pull one offer's XML out of a feed.
 *
 * @param string $path    File.
 * @param string $id      Offer id.
 * @param bool   $wrapped Complete feed or bare offers.
 * @return string
 */
function extract_offer( string $path, string $id, bool $wrapped ): string {
	$xml = $wrapped ? $path : $path . '.wrapped';
	$r   = new XMLReader();
	$r->open( $xml, null, LIBXML_NONET | LIBXML_PARSEHUGE );

	while ( $r->read() ) {
		if ( XMLReader::ELEMENT === $r->nodeType && 'offer' === $r->name && (string) $r->getAttribute( 'id' ) === $id ) {
			$s = $r->readOuterXML();
			$r->close();
			return $s;
		}
	}

	return '';
}

$ok = ! $only_php && ! $only_exp && ! $changed && 0 === $cat_diff;
echo $ok ? "\nGOLDEN MATCH\n" : "\nMISMATCH\n";
@unlink( $tmp );
@unlink( $tmp . '.wrapped' );
exit( $ok ? 0 : 1 );
