<?php
/**
 * Download live supplier feeds and write compact offline samples for unit tests.
 *
 * Usage: php tests/bin/build-feed-samples.php [output-dir]
 *
 * Samples keep currencies + a few categories + a few complete variable groups
 * so PHPUnit stays fast and offline.
 *
 * @package Factorial2000_Catalog_Sync
 */

$feeds = require dirname( __DIR__ ) . '/fixtures/feeds/urls.php';

$out_dir = $argv[1] ?? ( dirname( __DIR__ ) . '/fixtures/feeds' );
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0777, true );
}

$manifest = array();

foreach ( $feeds as $slug => $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		fwrite( STDERR, "SKIP {$slug}: no URL (set urls.local.php or F2000CS_FEED_URL_" . strtoupper( str_replace( '-', '_', $slug ) ) . ")\n" );
		continue;
	}

	$t0  = microtime( true );
	$tmp = tempnam( sys_get_temp_dir(), 'feed_' );

	$ctx = stream_context_create(
		array(
			'http' => array( 'timeout' => 180 ),
			'ssl'  => array(
				'verify_peer'      => false,
				'verify_peer_name' => false,
			),
		)
	);

	$in = fopen( $url, 'r', false, $ctx );
	if ( ! $in ) {
		fwrite( STDERR, "FAIL open {$slug}\n" );
		continue;
	}

	$out   = fopen( $tmp, 'w' );
	$bytes = stream_copy_to_stream( $in, $out );
	fclose( $in );
	fclose( $out );
	$dl = microtime( true ) - $t0;

	$reader = new XMLReader();
	if ( ! @$reader->open( $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		fwrite( STDERR, "FAIL parse {$slug}\n" );
		unlink( $tmp );
		continue;
	}

	$currencies        = array();
	$categories        = array();
	$groups            = array(); // group_id => list of offer XML (only a few groups kept)
	$complete_ids      = array(); // group_ids that already have 2+ offers
	$simple_offers     = array();
	$total_offers      = 0;
	$with_group        = 0;
	$without_group     = 0;
	$max_sample_groups = 3;
	$gid_seen          = array(); // lightweight full-scan duplicate detector
	$saw_multi_group   = false;

	while ( $reader->read() ) {
		if ( $reader->nodeType !== XMLReader::ELEMENT ) {
			continue;
		}

		$name = $reader->localName;

		if ( 'currency' === $name && count( $currencies ) < 8 ) {
			$currencies[] = $reader->readOuterXML();
			continue;
		}

		if ( 'category' === $name && count( $categories ) < 25 ) {
			$categories[] = $reader->readOuterXML();
			continue;
		}

		if ( 'offer' !== $name ) {
			continue;
		}

		$xml = $reader->readOuterXML();
		++$total_offers;
		$sx = @simplexml_load_string( $xml ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $sx ) {
			continue;
		}

		$gid = isset( $sx['group_id'] ) ? trim( (string) $sx['group_id'] ) : '';
		if ( '' === $gid ) {
			++$without_group;
			if ( count( $simple_offers ) < 8 ) {
				$simple_offers[] = $xml;
			}
			continue;
		}

		++$with_group;

		if ( isset( $gid_seen[ $gid ] ) ) {
			$saw_multi_group = true;
		} else {
			$gid_seen[ $gid ] = true;
		}

		// Stop collecting new groups once we have enough complete samples.
		// Also cap open incomplete groups (feeds with only unique group_ids).
		if ( ! isset( $groups[ $gid ] ) ) {
			if ( count( $complete_ids ) >= $max_sample_groups ) {
				continue;
			}
			if ( empty( $complete_ids ) && count( $groups ) >= 40 ) {
				continue;
			}
			$groups[ $gid ] = array();
		}

		if ( count( $groups[ $gid ] ) < 5 ) {
			$groups[ $gid ][] = $xml;
		}

		if ( count( $groups[ $gid ] ) >= 2 && ! isset( $complete_ids[ $gid ] ) ) {
			$complete_ids[ $gid ] = true;
		}
	}
	$reader->close();

	$complete_groups = array();
	foreach ( $groups as $gid => $offers ) {
		if ( count( $offers ) >= 2 ) {
			$complete_groups[ $gid ] = $offers;
		}
		if ( count( $complete_groups ) >= $max_sample_groups ) {
			break;
		}
	}

	$sample_offers = $simple_offers;
	foreach ( $complete_groups as $offers ) {
		foreach ( $offers as $offer_xml ) {
			$sample_offers[] = $offer_xml;
		}
	}

	// Feeds like tactic-shop mark every offer with a unique group_id (no 2+ groups).
	// Keep a few lone offers so simple-mode promotion can be tested offline.
	$lone_offers = array();
	if ( empty( $complete_groups ) ) {
		foreach ( $groups as $offers ) {
			if ( 1 === count( $offers ) ) {
				$lone_offers[] = $offers[0];
			}
			if ( count( $lone_offers ) >= 6 ) {
				break;
			}
		}
		$sample_offers = array_merge( $sample_offers, $lone_offers );
	}

	$sample_path = $out_dir . '/' . $slug . '-sample.xml';
	$fh          = fopen( $sample_path, 'w' );
	fwrite( $fh, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" );
	fwrite( $fh, "<yml_catalog date=\"sample\">\n<shop>\n" );
	if ( $currencies ) {
		fwrite( $fh, "<currencies>\n" . implode( "\n", $currencies ) . "\n</currencies>\n" );
	}
	fwrite( $fh, "<categories>\n" . implode( "\n", $categories ) . "\n</categories>\n" );
	fwrite( $fh, "<offers>\n" . implode( "\n", $sample_offers ) . "\n</offers>\n" );
	fwrite( $fh, "</shop>\n</yml_catalog>\n" );
	fclose( $fh );

	$sample_size = filesize( $sample_path );
	// Do not persist tokenized query strings (e.g. hash_tag) into the committed manifest.
	$manifest_url = $url;
	if ( false !== stripos( $url, 'hash_tag=' ) ) {
		$manifest_url = '';
	}

	$manifest[ $slug ] = array(
		'url'                     => $manifest_url,
		'source_bytes'            => $bytes,
		'source_mb'               => round( $bytes / 1048576, 2 ),
		'download_sec'            => round( $dl, 2 ),
		'offers_total'            => $total_offers,
		'offers_with_group_id'    => $with_group,
		'offers_without_group_id' => $without_group,
		'all_lone_group_ids'      => ( $with_group > 0 && ! $saw_multi_group && 0 === $without_group ),
		'simple_only'             => ( $without_group > 0 && 0 === $with_group ),
		'sample_file'             => basename( $sample_path ),
		'sample_bytes'            => $sample_size,
		'sample_offers'           => count( $sample_offers ),
		'sample_groups'           => count( $complete_groups ),
		'sample_lone_offers'      => count( $lone_offers ),
	);

	printf(
		"%s: %.1fMB dl=%.1fs offers=%d (group=%d / plain=%d) sample=%dB offers=%d groups=%d\n",
		$slug,
		$bytes / 1048576,
		$dl,
		$total_offers,
		$with_group,
		$without_group,
		$sample_size,
		count( $sample_offers ),
		count( $complete_groups )
	);

	unlink( $tmp );
}

file_put_contents(
	$out_dir . '/manifest.json',
	json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo 'OUT=' . $out_dir . "\n";
