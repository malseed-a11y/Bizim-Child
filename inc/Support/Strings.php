<?php
namespace BizimTheme\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registering and reading WPML String Translation strings.
 *
 * Two rules, both learned the hard way:
 *
 * 1. Register with an explicit source language. The wpml_register_single_string
 *    action takes none, so WPML stamps every string with its "language of
 *    strings in the source code" default - English - even though the text is
 *    Turkish. Worse, re-registering resets the record, so any language a human
 *    corrects in the UI is silently undone on the next page load.
 *
 * 2. Only register while viewing the default language. These helpers run on
 *    render, so on an Arabic page the value read is already Arabic; registering
 *    it would create a second "source" string holding a translation.
 */
final class Strings {

	public static function translate( string $domain, string $name, string $value ): string {
		if ( '' === trim( $value ) ) {
			return $value;
		}

		$default = (string) apply_filters( 'wpml_default_language', null );
		$current = (string) apply_filters( 'wpml_current_language', null );

		if ( '' === $default || $current === $default ) {
			self::register_if_changed( $domain, $name, $value, $default );
		}

		$out = apply_filters( 'wpml_translate_single_string', $value, $domain, $name );

		return is_string( $out ) && '' !== $out ? $out : $value;
	}

	/** Source strings already recorded, per domain: name => value. */
	private static array $known = array();

	/**
	 * Register only when the text is genuinely new or has changed.
	 *
	 * Registering is not free and it is not idempotent from WPML's side: every
	 * call fires wpml_st_string_registered, which invalidates String
	 * Translation's MO-domain cache. The very next lookup then rebuilds that
	 * cache and writes the transient again. Calling this on every render put the
	 * two into a loop - measured at 348 option writes and 65 queries per ten
	 * translated strings on a single page.
	 *
	 * One indexed read per domain per request replaces all of it.
	 */
	private static function register_if_changed( string $domain, string $name, string $value, string $source_lang ): void {
		$known = self::known( $domain );

		if ( array_key_exists( $name, $known ) && $known[ $name ] === $value ) {
			return;
		}

		self::register( $domain, $name, $value, $source_lang );
		self::$known[ $domain ][ $name ] = $value;
	}

	/** Every source string in a domain, read once per request. */
	private static function known( string $domain ): array {
		if ( isset( self::$known[ $domain ] ) ) {
			return self::$known[ $domain ];
		}

		self::$known[ $domain ] = array();

		global $wpdb;
		$table = $wpdb->prefix . 'icl_strings';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return self::$known[ $domain ];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT name, value FROM {$table} WHERE context = %s", $domain ),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			self::$known[ $domain ][ (string) $row['name'] ] = (string) $row['value'];
		}

		return self::$known[ $domain ];
	}

	private static function register( string $domain, string $name, string $value, string $source_lang ): void {
		if ( function_exists( 'icl_register_string' ) ) {
			icl_register_string( $domain, $name, $value, false, $source_lang ?: null );
			return;
		}
		do_action( 'wpml_register_single_string', $domain, $name, $value );
	}
}