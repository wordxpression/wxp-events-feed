<?php
/**
 * Builds the release zip: only what the plugin needs to run, in a folder named after the plugin.
 *
 * Usage (from the plugin folder): composer build-release
 *
 * It starts from a list of what to ship, so development files (tests, node_modules, vendor, the block source,
 * config files) can never end up in the zip by accident. Run `npm run build` first; the script checks that the
 * built block exists. Upload the zip as an asset of the GitHub release: the updater looks for it there.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );
$slug = basename( $root );
$main = $root . '/' . $slug . '.php';

// What goes into the zip. Paths relative to the plugin folder; folders are included completely.
$ship = array(
	$slug . '.php',
	'uninstall.php',
	'readme.txt',
	'src',
	'languages',
	'assets',
);

// Left out of the shipped folders: the block source (the build is shipped) and files nobody needs at run time.
$skip = array(
	'#^assets/block/src(/|$)#',
	'#\.map$#',
	'#(^|/)\.DS_Store$#',
	'#(^|/)Thumbs\.db$#',
);

// Without these the plugin cannot run: stop instead of shipping a broken zip.
$required = array(
	$slug . '.php',
	'assets/css/events-feed.css',
	'assets/block/events-feed/block.json',
	'assets/block/build/index.js',
	'assets/block/build/index.asset.php',
);

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "The PHP zip extension is not available.\n" );
	exit( 1 );
}

foreach ( $required as $path ) {
	if ( ! is_file( $root . '/' . $path ) ) {
		fwrite( STDERR, "Missing $path. Run `npm run build` first.\n" );
		exit( 1 );
	}
}

$header = (string) file_get_contents( $main );
if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/mi', $header, $match ) ) {
	fwrite( STDERR, "No Version header in $slug.php.\n" );
	exit( 1 );
}
$version = $match[1];

// The version in the header, the constant and the readme must agree, or the updater would offer the wrong update.
if ( ! preg_match( "/const VERSION\s*=\s*'([^']+)'/", $header, $constant ) || $constant[1] !== $version ) {
	fwrite( STDERR, "The VERSION constant does not match the Version header ($version).\n" );
	exit( 1 );
}
if ( ! preg_match( '/^Stable tag:\s*(\S+)/mi', (string) file_get_contents( $root . '/readme.txt' ), $stable ) || $stable[1] !== $version ) {
	fwrite( STDERR, "The Stable tag in readme.txt does not match the Version header ($version).\n" );
	exit( 1 );
}

$dist = $root . '/dist';
if ( ! is_dir( $dist ) && ! mkdir( $dist, 0777, true ) ) {
	fwrite( STDERR, "Cannot create $dist.\n" );
	exit( 1 );
}
$target = $dist . '/' . $slug . '-' . $version . '.zip';
if ( is_file( $target ) ) {
	unlink( $target );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $target, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create $target.\n" );
	exit( 1 );
}

$add = static function ( string $relative ) use ( $zip, $root, $slug, $skip ): bool {
	foreach ( $skip as $pattern ) {
		if ( preg_match( $pattern, $relative ) ) {
			return false;
		}
	}
	$zip->addFile( $root . '/' . $relative, $slug . '/' . $relative );
	return true;
};

$count = 0;
foreach ( $ship as $item ) {
	$full = $root . '/' . $item;
	if ( is_file( $full ) ) {
		$count += $add( $item ) ? 1 : 0;
		continue;
	}
	if ( ! is_dir( $full ) ) {
		continue;
	}
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $full, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		if ( $file->isFile() ) {
			$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
			$count   += $add( $relative ) ? 1 : 0;
		}
	}
}
$zip->close();

printf( "%s: %d files, %s KB\n", basename( $target ), $count, number_format( (float) filesize( $target ) / 1024, 0 ) );
