<?php
/**
 * Check release files for WordPress.org review policies not covered by PHPCS.
 *
 * Usage: php scripts/check-plugin-review-policy.php [plugin-directory]
 *
 * @package TravelApp
 */

$root = isset( $argv[1] ) ? realpath( $argv[1] ) : realpath( dirname( __DIR__ ) );

if ( false === $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "Plugin directory does not exist.\n" );
	exit( 2 );
}

$failures = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$path     = $file->getPathname();
	$relative = ltrim( substr( $path, strlen( $root ) ), DIRECTORY_SEPARATOR );
	$top_dir  = strtok( str_replace( DIRECTORY_SEPARATOR, '/', $relative ), '/' );

	// Development-only files are excluded from release archives.
	if ( in_array( $top_dir, array( 'scripts', 'tests' ), true ) ) {
		continue;
	}

	$source   = file_get_contents( $path );

	if ( false === $source ) {
		$failures[] = $relative . ': could not read file';
		continue;
	}

	$checks = array(
		'/^\s*\*\s*Tested up to\s*:/mi' => 'declare "Tested up to" only in readme.txt',
		'/\bdefine\s*\(\s*[\'\"]DONOTCACHEPAGE[\'\"]/i' => 'do not change the global DONOTCACHEPAGE constant',
		'/\bWP_CONTENT_DIR\s*\.\s*[\'\"]\/plugins(?:\/|[\'\"])/i' => 'do not assume that plugins live below WP_CONTENT_DIR/plugins',
		'/\bWP_PLUGIN_DIR\s*\.\s*[\'\"]\/[^\'\"$]+\//i' => 'do not hard-code another plugin directory slug',
		'/\bPowered\s+by\b/i' => 'public credit text requires an explicit administrator opt-in',
	);

	foreach ( $checks as $pattern => $message ) {
		if ( preg_match( $pattern, $source, $match, PREG_OFFSET_CAPTURE ) ) {
			$line       = substr_count( substr( $source, 0, $match[0][1] ), "\n" ) + 1;
			$failures[] = sprintf( '%s:%d: %s', $relative, $line, $message );
		}
	}

	// First-party PHP must enqueue resources. Bundled libraries remain covered by
	// Plugin Check, without making this project responsible for their admin UI.
	if ( 'vendor' !== $top_dir ) {
		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) && T_INLINE_HTML === $token[0] && preg_match( '/<\s*(?:script|style)\b/i', $token[1], $match, PREG_OFFSET_CAPTURE ) ) {
				$line       = $token[2] + substr_count( substr( $token[1], 0, $match[0][1] ), "\n" );
				$failures[] = sprintf( '%s:%d: enqueue scripts and styles instead of printing tags from PHP', $relative, $line );
			}
		}
	}
}

if ( $failures ) {
	fwrite( STDERR, "WordPress.org review policy checks failed:\n - " . implode( "\n - ", $failures ) . "\n" );
	exit( 1 );
}

echo "WordPress.org review policy checks passed.\n";
