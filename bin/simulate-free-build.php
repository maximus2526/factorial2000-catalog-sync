<?php
/**
 * Emulates the Freemius free/premium split and smoke-tests both builds.
 *
 * Freemius removes every path listed in the `@fs_premium_only` tag of the main
 * plugin file header (and any `*__premium_only.*` file) from the free build that
 * is published on wordpress.org. This script reproduces that locally:
 *
 *   php bin/simulate-free-build.php
 *
 * It copies the plugin twice into a temp dir (free = premium paths removed,
 * pro = untouched), lints every PHP file and runs tests/integration/free-build-smoke.php
 * against each build. Exit code is non-zero when anything fails.
 *
 * Dev tool only — `bin/` is excluded from release builds.
 */

$root = dirname( __DIR__ );
$main = $root . '/factorial2000-catalog-sync.php';

$header = (string) file_get_contents( $main );
if ( ! preg_match( '/@fs_premium_only\s+([^\r\n]+)/', $header, $m ) ) {
	fwrite( STDERR, "No @fs_premium_only tag in the main plugin file header.\n" );
	exit( 1 );
}

$premium = array_values( array_filter( array_map( static fn( $p ) => trim( $p, " \t/" ), explode( ',', $m[1] ) ) ) );

$skip = array( '.git', '.github', '.claude', 'vendor', 'vendor-bin', 'node_modules', 'tests', 'bin', 'release', '.phpunit.cache', 'Promo' );

function sim_copy( string $src, string $dst, array $skip ): void {
	if ( ! is_dir( $dst ) ) {
		mkdir( $dst, 0777, true );
	}
	foreach ( scandir( $src ) as $entry ) {
		if ( '.' === $entry || '..' === $entry || in_array( $entry, $skip, true ) ) {
			continue;
		}
		$from = $src . '/' . $entry;
		$to   = $dst . '/' . $entry;
		if ( is_dir( $from ) ) {
			sim_copy( $from, $to, array() );
		} else {
			copy( $from, $to );
		}
	}
}

function sim_rrmdir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$p = $dir . '/' . $entry;
		is_dir( $p ) ? sim_rrmdir( $p ) : unlink( $p );
	}
	rmdir( $dir );
}

function sim_php_files( string $dir ): array {
	$out = array();
	foreach ( scandir( $dir ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$p = $dir . '/' . $entry;
		if ( is_dir( $p ) ) {
			$out = array_merge( $out, sim_php_files( $p ) );
		} elseif ( 'php' === pathinfo( $p, PATHINFO_EXTENSION ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

$tmp = sys_get_temp_dir() . '/f2000cs-sim-' . uniqid();
$ok  = true;

foreach ( array( 'free', 'pro' ) as $mode ) {
	$dir = $tmp . '/' . $mode . '/factorial2000-catalog-sync';
	sim_copy( $root, $dir, $skip );

	if ( 'free' === $mode ) {
		foreach ( $premium as $path ) {
			$target = $dir . '/' . $path;
			if ( is_dir( $target ) ) {
				sim_rrmdir( $target );
			} elseif ( is_file( $target ) ) {
				unlink( $target );
			} else {
				echo "[{$mode}] WARNING: premium path not found: {$path}\n";
			}
		}
		foreach ( sim_php_files( $dir ) as $f ) {
			if ( false !== strpos( basename( $f ), '__premium_only' ) ) {
				unlink( $f );
			}
		}
	}

	echo "=== {$mode} build: lint ===\n";
	$lint_fail = 0;
	foreach ( sim_php_files( $dir ) as $f ) {
		if ( false !== strpos( $f, '/freemius/' ) ) {
			continue;
		}
		exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $f ) . ' 2>&1', $o, $code );
		if ( 0 !== $code ) {
			echo implode( "\n", $o ) . "\n";
			++$lint_fail;
		}
		$o = array();
	}
	echo $lint_fail ? "LINT FAILED ({$lint_fail})\n" : "lint ok\n";
	$ok = $ok && 0 === $lint_fail;

	echo "=== {$mode} build: smoke ===\n";
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/tests/integration/free-build-smoke.php' ) . ' ' . escapeshellarg( $dir ) . ' ' . $mode, $code );
	$ok = $ok && 0 === $code;
}

sim_rrmdir( $tmp );
echo $ok ? "\nALL OK\n" : "\nFAILED\n";
exit( $ok ? 0 : 1 );
