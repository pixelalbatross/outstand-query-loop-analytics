<?php
/**
 * Strauss bootstrap shim.
 *
 * Composer 2.10+ no longer exposes its runtime `Composer\*` classes to binaries
 * it invokes, and it always excludes the `composer/composer` package from the
 * generated project autoloader. Strauss needs `Composer\Factory`, so running
 * `vendor/bin/strauss` directly fatals with "Class Composer\Factory not found".
 *
 * This shim loads the project autoloader and then registers composer/composer's
 * PSR-4 so Strauss can resolve those classes at runtime. Invoked from the
 * `prefix-deps` composer script; not shipped in the release (see .gitattributes).
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if ( ! is_file( $autoload ) ) {
	fwrite( STDERR, "Run `composer install` first.\n" );
	exit( 1 );
}
require $autoload;

$composer_src = __DIR__ . '/../vendor/composer/composer/src';
if ( ! is_dir( $composer_src ) ) {
	fwrite( STDERR, "composer/composer is missing; run `composer install` (with dev).\n" );
	exit( 1 );
}

spl_autoload_register(
	static function ( $class ) use ( $composer_src ) {
		if ( strpos( $class, 'Composer\\' ) !== 0 ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( 'Composer\\' ) ) );
		$file     = $composer_src . '/Composer/' . $relative . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

// Strauss 0.19 copies into the target directory without clearing it first, so
// repeated runs accumulate stale files (e.g. previously-trimmed Google API
// services pile back up). Wipe the target for a deterministic rebuild.
$root      = dirname( __DIR__ );
$composer  = json_decode( (string) file_get_contents( $root . '/composer.json' ), true );
$target    = $composer['extra']['strauss']['target_directory'] ?? 'vendor-prefixed';
$target_dir = $root . '/' . trim( $target, '/' );

if ( is_dir( $target_dir ) ) {
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $target_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $target_dir );
}

$version = class_exists( \Composer\InstalledVersions::class )
	? (string) \Composer\InstalledVersions::getPrettyVersion( 'brianhenryie/strauss' )
	: 'unknown';

$app = new \BrianHenryIE\Strauss\Console\Application( $version );
$app->run();
