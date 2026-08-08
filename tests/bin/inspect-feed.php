<?php
$url = $argv[1] ?? '';
if ( '' === $url ) {
	fwrite( STDERR, "Usage: php inspect-feed.php <url>\n" );
	exit( 1 );
}

$ctx = stream_context_create(
	array(
		'ssl'  => array(
			'verify_peer'      => false,
			'verify_peer_name' => false,
		),
		'http' => array( 'timeout' => 120 ),
	)
);

$tmp = tempnam( sys_get_temp_dir(), 'insp_' );
$in  = fopen( $url, 'r', false, $ctx );
if ( ! $in ) {
	fwrite( STDERR, "Cannot open URL\n" );
	exit( 1 );
}
$out = fopen( $tmp, 'w' );
stream_copy_to_stream( $in, $out );
fclose( $in );
fclose( $out );

$reader = new XMLReader();
$reader->open( $tmp );

$n     = 0;
$seen  = array();
$first = null;

while ( $reader->read() ) {
	if ( $reader->nodeType !== XMLReader::ELEMENT || 'offer' !== $reader->localName ) {
		continue;
	}

	$sx = simplexml_load_string( $reader->readOuterXML() );
	if ( ! $sx ) {
		continue;
	}

	$attrs = array();
	foreach ( $sx->attributes() as $k => $v ) {
		$attrs[ (string) $k ] = (string) $v;
	}

	$gid = isset( $sx['group_id'] ) ? trim( (string) $sx['group_id'] ) : '';
	++$n;

	if ( $n <= 5 ) {
		echo 'offer#' . $n . ' ' . json_encode( $attrs, JSON_UNESCAPED_UNICODE ) . PHP_EOL;
	}

	if ( '' !== $gid ) {
		if ( isset( $seen[ $gid ] ) && null === $first ) {
			$first = $n;
		}
		$seen[ $gid ] = ( $seen[ $gid ] ?? 0 ) + 1;
	}

	if ( $n >= 800 && null !== $first ) {
		break;
	}
}

echo "scanned_offers={$n}\n";
echo 'unique_group_ids=' . count( $seen ) . "\n";
echo 'first_duplicate_at=' . var_export( $first, true ) . "\n";

$multi = 0;
foreach ( $seen as $c ) {
	if ( $c >= 2 ) {
		++$multi;
	}
}
echo "groups_with_2plus_in_scan={$multi}\n";

unlink( $tmp );
