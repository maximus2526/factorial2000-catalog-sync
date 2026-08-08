'use strict';

/**
 * Gulp build system for the Factorial2000 Catalog Sync plugin.
 *
 * Tasks:
 *   gulp                      — interactive menu
 *   gulp release              — build release/ (clean plugin folder + zip + svn layout)
 *   gulp version:up           — bump version (--level=patch|minor|major)
 *   gulp version:down         — decrement patch version
 *   gulp version:set          — set a specific version (--version=1.2.3)
 *   gulp phpcs                — run PHP_CodeSniffer
 *   gulp phpunit              — run the unit tests
 *   gulp full                 — phpcs → phpunit → version:up (patch) → release
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

const gulp = require( 'gulp' );
const inquirerModule = require( 'inquirer' );

// inquirer 10+ exposes the API on .default for CommonJS consumers.
const inquirer = inquirerModule.default || inquirerModule;
const archiver = require( 'archiver' );
const semver = require( 'semver' );

const PLUGIN_SLUG = 'factorial2000-catalog-sync';
const ROOT = __dirname;
const MAIN_FILE = path.join( ROOT, PLUGIN_SLUG + '.php' );
const README_TXT = path.join( ROOT, 'readme.txt' );
const RELEASE_DIR = path.join( ROOT, 'release' );

/**
 * Paths stripped from the release build.
 */
const EXCLUDE_LIST = [
	'.git',
	'.github',
	'.gitignore',
	'tests',
	'bin',
	'node_modules',
	'vendor',
	'vendor-bin',
	'release',
	'.phpunit.cache',
	'.phpunit.result.cache',
	'gulpfile.js',
	'package.json',
	'package-lock.json',
	'composer.json',
	'composer.lock',
	'phpunit.xml.dist',
	'.phpcs.xml.dist',
	'README.md',
	'SKILL.md',
];

/**
 * Log helper.
 *
 * @param {string} message Message.
 */
function log( message ) {
	console.log( '\x1b[36m[build]\x1b[0m ' + message );
}

/**
 * Read the current plugin version from the main plugin file header.
 *
 * @returns {string} Semver string.
 */
function readVersion() {
	const contents = fs.readFileSync( MAIN_FILE, 'utf8' );
	const match = contents.match( /Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/ );

	if ( ! match ) {
		throw new Error( 'Could not find the version in ' + MAIN_FILE );
	}

	return match[ 1 ];
}

/**
 * Write the version into the plugin header, the F2000CS_VERSION constant
 * and the readme.txt Stable tag, keeping all three in sync.
 *
 * @param {string} version New semver version.
 */
function writeVersion( version ) {
	if ( ! semver.valid( version ) ) {
		throw new Error( 'Invalid version: ' + version );
	}

	let main = fs.readFileSync( MAIN_FILE, 'utf8' );
	main = main.replace( /(\* Version:\s*)[0-9.]+/, '$1' + version );
	main = main.replace( /(define\( 'F2000CS_VERSION', ')[0-9.]+(' \);)/, '$1' + version + '$2' );
	fs.writeFileSync( MAIN_FILE, main );

	let readme = fs.readFileSync( README_TXT, 'utf8' );
	readme = readme.replace( /(Stable tag:\s*)[0-9.]+/, '$1' + version );
	fs.writeFileSync( README_TXT, readme );

	log( 'Version updated to ' + version + ' (main file, F2000CS_VERSION, readme.txt)' );
}

/**
 * Decrement a version (patch first, then minor, then major; never negative).
 *
 * @param {string} version Semver string.
 * @returns {string} Decremented version.
 */
function decrementVersion( version ) {
	const parts = version.split( '.' ).map( Number );
	const major = parts[ 0 ] || 0;
	const minor = parts[ 1 ] || 0;
	const patch = parts[ 2 ] || 0;

	if ( patch > 0 ) {
		return major + '.' + minor + '.' + ( patch - 1 );
	}

	if ( minor > 0 ) {
		return major + '.' + ( minor - 1 ) + '.0';
	}

	return Math.max( 0, major - 1 ) + '.0.0';
}

/**
 * Wipe the release directory.
 */
async function cleanRelease() {
	fs.rmSync( RELEASE_DIR, { recursive: true, force: true } );
	log( 'Release directory cleaned' );
}

/**
 * Copy the plugin into release/<slug>/ without dev-only folders.
 */
async function copyPlugin() {
	const dest = path.join( RELEASE_DIR, PLUGIN_SLUG );
	fs.mkdirSync( dest, { recursive: true } );

	for ( const entry of fs.readdirSync( ROOT, { withFileTypes: true } ) ) {
		if ( EXCLUDE_LIST.includes( entry.name ) ) {
			continue;
		}
		fs.cpSync( path.join( ROOT, entry.name ), path.join( dest, entry.name ), { recursive: true } );
	}

	log( 'Plugin copied to release/' + PLUGIN_SLUG + '/' );
}

/**
 * Create release/<slug>-<version>.zip containing the plugin folder.
 */
async function zipRelease() {
	const version = readVersion();
	const zipPath = path.join( RELEASE_DIR, PLUGIN_SLUG + '-' + version + '.zip' );
	const output = fs.createWriteStream( zipPath );
	const archive = archiver( 'zip', { zlib: { level: 9 } } );

	await new Promise( ( resolve, reject ) => {
		output.on( 'close', resolve );
		archive.on( 'error', reject );
		archive.pipe( output );
		archive.directory( path.join( RELEASE_DIR, PLUGIN_SLUG ), PLUGIN_SLUG );
		archive.finalize();
	} );

	log( 'Zip created: release/' + PLUGIN_SLUG + '-' + version + '.zip' );
}

/**
 * Build the WordPress.org SVN layout: svn/trunk, svn/tags/<version>,
 * svn/assets plus a README with the commit commands.
 */
async function makeSvnLayout() {
	const version = readVersion();
	const svnDir = path.join( RELEASE_DIR, 'svn' );
	const trunkDir = path.join( svnDir, 'trunk' );
	const tagDir = path.join( svnDir, 'tags', version );

	fs.mkdirSync( trunkDir, { recursive: true } );
	fs.mkdirSync( tagDir, { recursive: true } );
	fs.mkdirSync( path.join( svnDir, 'assets' ), { recursive: true } );

	fs.cpSync( path.join( RELEASE_DIR, PLUGIN_SLUG ), trunkDir, { recursive: true } );
	fs.cpSync( path.join( RELEASE_DIR, PLUGIN_SLUG ), tagDir, { recursive: true } );

	fs.writeFileSync(
		path.join( svnDir, 'README.txt' ),
		[
			'WordPress.org SVN layout',
			'=======================',
			'',
			'This folder mirrors the plugin SVN repository structure:',
			'',
			'  trunk/          — development copy (what you commit normally)',
			'  tags/<version>/ — release snapshot (one folder per release)',
			'  assets/         — banners, icons, screenshots (optional)',
			'',
			'How to commit a release:',
			'',
			'  svn co https://plugins.svn.wordpress.org/' + PLUGIN_SLUG + '/ release/svn-checkout',
			'  cp -r release/svn/trunk/* release/svn-checkout/trunk/',
			'  cp -r release/svn/tags/' + version + ' release/svn-checkout/tags/' + version,
			'  cd release/svn-checkout',
			'  svn add trunk/* tags/' + version,
			'  svn commit -m "Release ' + version + '"',
			'',
		].join( '\n' )
	);

	log( 'SVN layout created: release/svn/ (trunk, tags/' + version + ', assets)' );
}

/**
 * Run phpcs on the plugin (uses composer-installed phpcs when available).
 */
async function runPhpcs() {
	const vendorPhpcs = path.join( ROOT, 'vendor', 'bin', 'phpcs' );
	const bin = fs.existsSync( vendorPhpcs ) ? vendorPhpcs : 'phpcs';

	log( 'Running PHP_CodeSniffer...' );
	const result = spawnSync( bin, [ '--standard=.phpcs.xml.dist' ], {
		cwd: ROOT,
		stdio: 'inherit',
		shell: true,
	} );

	if ( result.status !== 0 ) {
		throw new Error( 'PHPCS reported issues' );
	}

	log( 'PHPCS: no issues found' );
}

/**
 * Run the PHPUnit test suite.
 */
async function runPhpunit() {
	const vendorPhpunit = path.join( ROOT, 'vendor', 'bin', 'phpunit' );

	if ( ! fs.existsSync( vendorPhpunit ) ) {
		throw new Error( 'vendor/bin/phpunit not found — run: composer install' );
	}

	log( 'Running PHPUnit...' );
	const result = spawnSync( 'php', [ vendorPhpunit, '--configuration', path.join( ROOT, 'phpunit.xml.dist' ) ], {
		cwd: ROOT,
		stdio: 'inherit',
	} );

	if ( result.status !== 0 ) {
		throw new Error( 'PHPUnit tests failed' );
	}

	log( 'PHPUnit: all tests passed' );
}

// ---------------------------------------------------------------- tasks

gulp.task( 'clean', cleanRelease );

gulp.task( 'release', gulp.series( cleanRelease, copyPlugin, zipRelease, makeSvnLayout ) );

gulp.task(
	'version:up',
	async function versionUp() {
		const arg = process.argv.find( ( item ) => item.startsWith( '--level=' ) );
		const level = arg ? arg.split( '=' )[ 1 ] : 'patch';

		if ( ! [ 'patch', 'minor', 'major' ].includes( level ) ) {
			throw new Error( 'Invalid level: ' + level + ' (use --level=patch|minor|major)' );
		}

		const current = readVersion();
		const next = semver.inc( current, level );
		log( 'Bumping ' + level + ': ' + current + ' → ' + next );
		writeVersion( next );
	}
);

gulp.task(
	'version:down',
	async function versionDown() {
		const current = readVersion();
		const next = decrementVersion( current );
		log( 'Decrementing: ' + current + ' → ' + next );
		writeVersion( next );
	}
);

gulp.task(
	'version:set',
	async function versionSet() {
		const arg = process.argv.find( ( item ) => item.startsWith( '--version=' ) );

		if ( ! arg ) {
			throw new Error( 'Usage: gulp version:set --version=1.2.3' );
		}

		const version = arg.split( '=' )[ 1 ];
		log( 'Setting version to ' + version );
		writeVersion( version );
	}
);

gulp.task( 'phpcs', runPhpcs );
gulp.task( 'phpunit', runPhpunit );

gulp.task(
	'full',
	gulp.series(
		runPhpcs,
		runPhpunit,
		async function bumpAndRelease() {
			writeVersion( semver.inc( readVersion(), 'patch' ) );
		},
		cleanRelease,
		copyPlugin,
		zipRelease,
		makeSvnLayout
	)
);

// ---------------------------------------------------------------- menu

/**
 * Interactive build menu.
 */
async function menu() {
	let running = true;

	while ( running ) {
		const answers = await inquirer.prompt( [
			{
				type: 'list',
				name: 'action',
				message: 'Factorial2000 Catalog Sync — поточна версія: ' + readVersion(),
				choices: [
					{ name: '🚀 Повна збірка (phpcs → phpunit → +patch → release)', value: 'full' },
					{ name: '📦 Зібрати release для SVN (папка + zip + trunk/tags)', value: 'release' },
					{ name: '🔢 Підвищити версію', value: 'up' },
					{ name: '🔽 Понизити версію', value: 'down' },
					{ name: '✏️ Встановити власну версію', value: 'set' },
					{ name: '🔍 Перевірка PHPCS', value: 'phpcs' },
					{ name: '🧪 Запуск PHPUnit тестів', value: 'phpunit' },
					{ name: '❌ Вихід', value: 'exit' },
				],
			},
		] );

		try {
			switch ( answers.action ) {
				case 'full':
					await runPhpcs();
					await runPhpunit();
					writeVersion( semver.inc( readVersion(), 'patch' ) );
					await cleanRelease();
					await copyPlugin();
					await zipRelease();
					await makeSvnLayout();
					break;

				case 'release':
					await cleanRelease();
					await copyPlugin();
					await zipRelease();
					await makeSvnLayout();
					break;

				case 'up': {
					const levelAnswer = await inquirer.prompt( [
						{
							type: 'list',
							name: 'level',
							message: 'Який рівень підвищення?',
							choices: [
								{ name: 'Patch (0.5.7 → 0.5.8)', value: 'patch' },
								{ name: 'Minor (0.5.7 → 0.6.0)', value: 'minor' },
								{ name: 'Major (0.5.7 → 1.0.0)', value: 'major' },
							],
						},
					] );
					const current = readVersion();
					writeVersion( semver.inc( current, levelAnswer.level ) );
					break;
				}

				case 'down':
					writeVersion( decrementVersion( readVersion() ) );
					break;

				case 'set': {
					const versionAnswer = await inquirer.prompt( [
						{
							type: 'input',
							name: 'version',
							message: 'Версія (напр. 0.6.0):',
							validate: ( input ) => ( semver.valid( input ) ? true : 'Введіть коректну версію x.y.z' ),
						},
					] );
					writeVersion( versionAnswer.version );
					break;
				}

				case 'phpcs':
					await runPhpcs();
					break;

				case 'phpunit':
					await runPhpunit();
					break;

				case 'exit':
				default:
					running = false;
					break;
			}
		} catch ( error ) {
			console.log( '\x1b[31m[build] Помилка: ' + error.message + '\x1b[0m' );
		}
	}
}

exports.default = menu;
