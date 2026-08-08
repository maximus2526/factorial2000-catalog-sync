<?php
/**
 * Public live supplier feed URLs for sample builder / optional live tests.
 *
 * Feeds that need private tokens (e.g. Prom hash_tag) must NOT be committed here.
 * Put overrides in urls.local.php (gitignored) or set env F2000CS_FEED_URL_<SLUG>.
 *
 * Example urls.local.php:
 *   <?php
 *   return array(
 *     'powerplay' => 'https://example.com/products_feed.xml?hash_tag=...',
 *   );
 *
 * @package Factorial2000_Catalog_Sync
 */

$urls = array(
	'tactic-shop' => 'https://tactic-shop.in.ua/content/export/d77f172121c4b4665f86b9e7df1cbc12.xml',
	'armoline'    => 'https://armoline.com.ua/content/export/664bf270de235ffaef27313fa9849f15.xml',
	'vik-tailor'  => 'https://rechovyk.com.ua/extractor-xml-py/VIK-TAILOR-cleared.xml',
	'bm'          => 'https://rechovyk.com.ua/extractor-bm-xml/BM-cleared.xml',
	// Offline sample only — live URL via urls.local.php or F2000CS_FEED_URL_POWERPLAY.
	'powerplay'   => '',
);

$local = __DIR__ . '/urls.local.php';
if ( is_readable( $local ) ) {
	$overrides = require $local;
	if ( is_array( $overrides ) ) {
		$urls = array_merge( $urls, $overrides );
	}
}

foreach ( array_keys( $urls ) as $slug ) {
	$env_key = 'F2000CS_FEED_URL_' . strtoupper( str_replace( '-', '_', $slug ) );
	$env_val = getenv( $env_key );
	if ( is_string( $env_val ) && '' !== $env_val ) {
		$urls[ $slug ] = $env_val;
	}
}

return $urls;
