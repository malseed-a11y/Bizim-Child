<?php
namespace BizimTheme\Media;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps uploaded file names ASCII.
 *
 * WordPress is happy to store a file called "مياه-نظيفة-لغزة.jpg", which
 * produces a URL carrying raw UTF-8. GiveWP's campaign REST schema declares its
 * image field as format: uri and the campaign editor validates against that
 * schema in the browser, so choosing such an image fails with "Image must be a
 * valid URL" and the campaign cannot be saved at all. Those URLs also travel
 * badly through e-mail clients, social scrapers and PDF receipts, and read
 * poorly in search results.
 *
 * Transliterate what can be transliterated (Turkish g u s i o c), drop what
 * cannot (Arabic), and fall back to a traceable slug when nothing printable
 * survives. Runs late so it sees the name WordPress has already sanitised.
 */
final class AsciiFilenames implements ModuleInterface {

	public function register(): void {
		add_filter( 'sanitize_file_name', array( $this, 'to_ascii' ), 20 );
	}

	/**
	 * @param string $name File name WordPress intends to use.
	 * @return string ASCII-only equivalent, extension preserved.
	 */
	public function to_ascii( $name ) {
		$name = (string) $name;

		// Already safe - hand back exactly what WordPress produced.
		if ( '' === $name || preg_match( '/^[A-Za-z0-9._-]+$/', $name ) ) {
			return $name;
		}

		$extension = '';
		if ( preg_match( '/\.([A-Za-z0-9]{1,8})$/', $name, $matches ) ) {
			$extension = strtolower( $matches[1] );
			$name      = substr( $name, 0, - ( strlen( $matches[1] ) + 1 ) );
		}

		$ascii = remove_accents( $name );
		$ascii = preg_replace( '/[^A-Za-z0-9._-]+/', '-', (string) $ascii );
		$ascii = preg_replace( '/-{2,}/', '-', (string) $ascii );
		$ascii = trim( (string) $ascii, '-._' );

		if ( '' === $ascii ) {
			// Nothing printable survived - an all-Arabic name. Keep it stable for
			// the same source name; wp_unique_filename() resolves any collision.
			$ascii = 'media-' . substr( md5( $name ), 0, 10 );
		}

		return '' !== $extension ? $ascii . '.' . $extension : $ascii;
	}
}