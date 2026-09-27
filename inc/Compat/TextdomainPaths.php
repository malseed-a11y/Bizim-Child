<?php
namespace BizimTheme\Compat;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Silences open_basedir warnings coming out of WPML String Translation.
 *
 * WPML's LoadTranslationFile::getDefaultWordPressTranslationPath() builds a path
 * as "{dir}{domain}-{locale}.mo", but WP_Textdomain_Registry::get() returns
 * false for any text domain with no translation directory. false interpolates
 * to an empty string, so WPML calls file_exists() on a bare relative name such
 * as "action-scheduler-ar.mo". A relative path matches no open_basedir prefix,
 * so PHP emits a warning on every lookup.
 *
 * Rather than patch the plugin (an update would overwrite it), give the registry
 * a real absolute directory whenever it has none. WPML then builds an absolute
 * path inside the allowed roots, file_exists() simply returns false, and the
 * warning disappears.
 */
final class TextdomainPaths implements ModuleInterface {

	public function register(): void {
		add_filter( 'override_load_textdomain', array( $this, 'ensure_path' ), 1, 3 );
	}

	public function ensure_path( $override, $domain = '', $mofile = '' ) {
		global $wp_textdomain_registry;

		if ( ! isset( $wp_textdomain_registry ) || ! is_object( $wp_textdomain_registry ) ) {
			return $override;
		}
		if ( ! method_exists( $wp_textdomain_registry, 'get' ) || ! method_exists( $wp_textdomain_registry, 'set' ) ) {
			return $override;
		}
		if ( ! is_string( $domain ) || '' === $domain ) {
			return $override;
		}

		$fallback = is_string( $mofile ) && '' !== $mofile
			? trailingslashit( dirname( $mofile ) )
			: trailingslashit( WP_LANG_DIR ) . 'plugins/';

		foreach ( self::locales() as $locale ) {
			if ( false === $wp_textdomain_registry->get( $domain, $locale ) ) {
				$wp_textdomain_registry->set( $domain, $locale, $fallback );
			}
		}

		return $override;
	}

	/** Every locale WPML may look a file up for. */
	private static function locales(): array {
		$locales = array( determine_locale() );

		if ( function_exists( 'icl_get_languages' ) ) {
			foreach ( (array) icl_get_languages( 'skip_missing=0' ) as $lang ) {
				if ( ! empty( $lang['default_locale'] ) ) {
					$locales[] = $lang['default_locale'];
				}
			}
		}

		return array_unique( array_filter( $locales ) );
	}
}