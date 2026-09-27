<?php
/**
 * Bizim Eller child theme.
 *
 * @package BizimTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BIZIM_CHILD_DIR', get_stylesheet_directory() );
define( 'BIZIM_CHILD_URI', get_stylesheet_directory_uri() );
define( 'BIZIM_STATS_COUNT', 4 );
define( 'BIZIM_NS', 'BizimTheme\\' );

/**
 * PSR-4 autoloader:  BizimTheme\Foo\Bar  ->  inc/Foo/Bar.php
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, BIZIM_NS ) ) {
			return;
		}
		$relative = substr( $class, strlen( BIZIM_NS ) );
		$path     = BIZIM_CHILD_DIR . '/inc/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

require_once BIZIM_CHILD_DIR . '/inc/compat.php';

\BizimTheme\Core\Theme::instance()->boot();