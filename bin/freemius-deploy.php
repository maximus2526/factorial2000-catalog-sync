<?php
/**
 * Deploy a plugin zip to Freemius (developer scope).
 *
 * Required env:
 *   FREEMIUS_PUBLIC_KEY, FREEMIUS_SECRET_KEY,
 *   FREEMIUS_PLUGIN_ID, ZIP_PATH, VERSION
 * Optional:
 *   FREEMIUS_API_SCOPE=plugin|developer (default: plugin)
 *   FREEMIUS_DEV_ID — required only for developer scope
 *   FREEMIUS_RELEASE_MODE=pending|beta|released (default: pending)
 *   FREEMIUS_SANDBOX=true|false (default: false)
 *   FREEMIUS_SDK_PATH=/path/to/freemius-php-sdk
 *
 * Prefer plugin scope keys from Freemius → Product → Settings → Keys.
 * Developer scope keys are under Freemius → My Profile → Keys.
 *
 * Exit codes: 0 success, 1 failure.
 */

declare(strict_types=1);

/**
 * @param string $message Message.
 * @return never
 */
function f2000cs_fs_deploy_fail( string $message ): void {
	fwrite( STDERR, "[freemius-deploy] ERROR: {$message}\n" );
	exit( 1 );
}

/**
 * @param string $message Message.
 */
function f2000cs_fs_deploy_log( string $message ): void {
	fwrite( STDOUT, "[freemius-deploy] {$message}\n" );
}

/**
 * @param mixed $value API response.
 */
function f2000cs_fs_deploy_dump( $value ): string {
	if ( is_string( $value ) ) {
		return $value;
	}
	$encoded = json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	return false !== $encoded ? $encoded : print_r( $value, true );
}

$scope        = strtolower( trim( (string) ( getenv( 'FREEMIUS_API_SCOPE' ) ?: 'plugin' ) ) );
$dev_id       = trim( (string) getenv( 'FREEMIUS_DEV_ID' ) );
$public_key   = trim( (string) getenv( 'FREEMIUS_PUBLIC_KEY' ) );
$secret_key   = trim( (string) getenv( 'FREEMIUS_SECRET_KEY' ) );
$plugin_id    = trim( (string) getenv( 'FREEMIUS_PLUGIN_ID' ) );
$zip_path     = trim( (string) getenv( 'ZIP_PATH' ) );
$version      = trim( (string) getenv( 'VERSION' ) );
$release_mode = trim( (string) ( getenv( 'FREEMIUS_RELEASE_MODE' ) ?: 'pending' ) );
$sandbox      = filter_var( getenv( 'FREEMIUS_SANDBOX' ) ?: 'false', FILTER_VALIDATE_BOOLEAN );
$sdk_path     = trim( (string) ( getenv( 'FREEMIUS_SDK_PATH' ) ?: '' ) );

if ( ! in_array( $scope, array( 'plugin', 'developer' ), true ) ) {
	f2000cs_fs_deploy_fail( 'FREEMIUS_API_SCOPE must be plugin or developer.' );
}
if ( '' === $public_key || 0 !== strpos( $public_key, 'pk_' ) ) {
	f2000cs_fs_deploy_fail( 'FREEMIUS_PUBLIC_KEY must be a Freemius public key (pk_…). For plugin scope use Product → Settings → Keys.' );
}
if ( '' === $secret_key || 0 !== strpos( $secret_key, 'sk_' ) ) {
	f2000cs_fs_deploy_fail( 'FREEMIUS_SECRET_KEY must be a Freemius secret key (sk_…). For plugin scope use Product → Settings → Keys.' );
}
if ( '' === $plugin_id || ! ctype_digit( $plugin_id ) ) {
	f2000cs_fs_deploy_fail( 'FREEMIUS_PLUGIN_ID must be numeric.' );
}
if ( 'developer' === $scope && ( '' === $dev_id || ! ctype_digit( $dev_id ) ) ) {
	f2000cs_fs_deploy_fail( 'FREEMIUS_DEV_ID must be a numeric Developer ID from Freemius → My Profile → Keys.' );
}
if ( '' === $version ) {
	f2000cs_fs_deploy_fail( 'VERSION is required.' );
}
if ( ! in_array( $release_mode, array( 'pending', 'beta', 'released' ), true ) ) {
	f2000cs_fs_deploy_fail( 'FREEMIUS_RELEASE_MODE must be pending, beta, or released.' );
}
if ( '' === $zip_path || ! is_file( $zip_path ) ) {
	f2000cs_fs_deploy_fail( "ZIP_PATH not found: {$zip_path}" );
}

$zip_path = realpath( $zip_path );
if ( false === $zip_path ) {
	f2000cs_fs_deploy_fail( 'Could not resolve ZIP_PATH.' );
}

if ( '' === $sdk_path ) {
	$sdk_path = dirname( __DIR__ ) . '/vendor-bin/freemius-php-sdk';
}
$sdk_base = $sdk_path . '/freemius/FreemiusBase.php';
$sdk_main = $sdk_path . '/freemius/Freemius.php';
if ( ! is_file( $sdk_base ) || ! is_file( $sdk_main ) ) {
	f2000cs_fs_deploy_fail( "Freemius PHP SDK not found at {$sdk_path}. Clone Freemius/freemius-php-sdk first." );
}

require_once $sdk_base;
require_once $sdk_main;

$entity_id = 'plugin' === $scope ? (int) $plugin_id : (int) $dev_id;

f2000cs_fs_deploy_log( "Deploying plugin {$plugin_id} version {$version}" );
f2000cs_fs_deploy_log( "API scope={$scope}, entity_id={$entity_id}" );
f2000cs_fs_deploy_log( 'Zip: ' . $zip_path . ' (' . filesize( $zip_path ) . ' bytes)' );
f2000cs_fs_deploy_log( 'Release mode: ' . $release_mode . ( $sandbox ? ' (sandbox)' : '' ) );

try {
	$api = new Freemius_Api( $scope, $entity_id, $public_key, $secret_key, $sandbox );
} catch ( Exception $e ) {
	f2000cs_fs_deploy_fail( 'Failed to init Freemius API: ' . $e->getMessage() );
}

try {
	$tags_response = $api->Api( 'plugins/' . $plugin_id . '/tags.json', 'GET', array( 'count' => 50 ) );
} catch ( Exception $e ) {
	f2000cs_fs_deploy_fail(
		'Failed to list tags (check API scope / keys / PLUGIN_ID): ' . $e->getMessage()
	);
}

if ( is_object( $tags_response ) && isset( $tags_response->error ) ) {
	$hint = 'UnauthorizedAccess' === ( $tags_response->error->code ?? '' )
		|| 'unauthorized_access' === ( $tags_response->error->code ?? '' )
		? ' Hint: for plugin scope put Product → Settings → Keys into FREEMIUS_PUBLIC_KEY / FREEMIUS_SECRET_KEY (not My Profile developer keys, and not only the public pk_ from the SDK snippet).'
		: '';
	f2000cs_fs_deploy_fail( 'List tags API error: ' . f2000cs_fs_deploy_dump( $tags_response ) . $hint );
}

$existing = null;
if ( is_object( $tags_response ) && ! empty( $tags_response->tags ) && is_array( $tags_response->tags ) ) {
	foreach ( $tags_response->tags as $tag ) {
		if ( is_object( $tag ) && isset( $tag->version ) && (string) $tag->version === $version ) {
			$existing = $tag;
			break;
		}
	}
}

if ( $existing ) {
	f2000cs_fs_deploy_log( "Version {$version} already exists on Freemius (tag id {$existing->id})." );
	$deploy = $existing;
} else {
	f2000cs_fs_deploy_log( 'Uploading zip…' );
	try {
		$deploy = $api->Api(
			'plugins/' . $plugin_id . '/tags.json',
			'POST',
			array( 'add_contributor' => false ),
			array( 'file' => $zip_path )
		);
	} catch ( Exception $e ) {
		f2000cs_fs_deploy_fail( 'Upload failed: ' . $e->getMessage() );
	}

	if ( ! is_object( $deploy ) || ! isset( $deploy->id ) ) {
		f2000cs_fs_deploy_fail( 'Upload response missing tag id: ' . f2000cs_fs_deploy_dump( $deploy ) );
	}

	f2000cs_fs_deploy_log( "Upload OK — tag id {$deploy->id}, version {$deploy->version}." );
}

if ( ! isset( $deploy->release_mode ) || (string) $deploy->release_mode !== $release_mode ) {
	f2000cs_fs_deploy_log( "Setting release_mode={$release_mode}…" );
	try {
		$updated = $api->Api(
			'plugins/' . $plugin_id . '/tags/' . $deploy->id . '.json',
			'PUT',
			array( 'release_mode' => $release_mode )
		);
	} catch ( Exception $e ) {
		f2000cs_fs_deploy_fail( 'Failed to set release_mode: ' . $e->getMessage() );
	}
	if ( is_object( $updated ) && isset( $updated->error ) ) {
		f2000cs_fs_deploy_fail( 'release_mode API error: ' . f2000cs_fs_deploy_dump( $updated ) );
	}
	$deploy = is_object( $updated ) && isset( $updated->id ) ? $updated : $deploy;
}

$out_dir = dirname( $zip_path );
$free_out = $out_dir . '/' . pathinfo( $zip_path, PATHINFO_FILENAME ) . '__free.zip';
$pro_out  = $out_dir . '/' . pathinfo( $zip_path, PATHINFO_FILENAME ) . '__premium.zip';

f2000cs_fs_deploy_log( 'Downloading generated free/premium packages…' );
try {
	$free_url = $api->GetSignedUrl( 'plugins/' . $plugin_id . '/tags/' . $deploy->id . '.zip?is_premium=false' );
	$pro_url  = $api->GetSignedUrl( 'plugins/' . $plugin_id . '/tags/' . $deploy->id . '.zip?is_premium=true' );
	$free_bin = file_get_contents( $free_url );
	$pro_bin  = file_get_contents( $pro_url );
} catch ( Exception $e ) {
	f2000cs_fs_deploy_fail( 'Failed to download generated packages: ' . $e->getMessage() );
}

if ( false === $free_bin || strlen( $free_bin ) < 100 ) {
	f2000cs_fs_deploy_fail( 'Free package download empty/invalid.' );
}
if ( false === $pro_bin || strlen( $pro_bin ) < 100 ) {
	f2000cs_fs_deploy_fail( 'Premium package download empty/invalid.' );
}

file_put_contents( $free_out, $free_bin );
file_put_contents( $pro_out, $pro_bin );

f2000cs_fs_deploy_log( 'Free package: ' . $free_out );
f2000cs_fs_deploy_log( 'Premium package: ' . $pro_out );

$github_output = getenv( 'GITHUB_OUTPUT' );
if ( is_string( $github_output ) && '' !== $github_output ) {
	file_put_contents(
		$github_output,
		"tag_id={$deploy->id}\nfree_version={$free_out}\npro_version={$pro_out}\n",
		FILE_APPEND
	);
}

f2000cs_fs_deploy_log( "SUCCESS: Freemius has version {$version} (release_mode={$release_mode})." );
exit( 0 );
