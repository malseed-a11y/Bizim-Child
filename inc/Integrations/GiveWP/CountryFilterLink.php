<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes country-filter links work in every language.
 *
 * The archive is a Bricks template, and WPML gives each translation its own
 * copy with its own element ids - the Turkish filter is "ofruit", the Arabic
 * one "fc0733". Bricks reads a filter from ?brx_<elementId>, so a link built as
 * ?brx_ofruit=suriye simply does nothing on the Arabic archive.
 *
 * Renaming an element to match is not safe: Bricks keeps a filter index keyed
 * by element id, and editing the template directly orphans it.
 *
 * So the incoming parameter is mapped instead. Whatever ?brx_<id> a link
 * carries, the value is copied onto the filter element that the current
 * language actually uses, looked up from Bricks' own filter table. No ids are
 * hard-coded, so this keeps working for languages added later.
 */
final class CountryFilterLink implements ModuleInterface {

	private const TAXONOMY = 'give_country';

	public function register(): void {
		add_action( 'init', array( $this, 'map_parameter' ), 1 );
	}

	public function map_parameter(): void {
		if ( is_admin() || empty( $_GET ) ) {
			return;
		}

		$value = '';
		foreach ( $_GET as $key => $raw ) {
			if ( 0 === strpos( (string) $key, 'brx_' ) && '' !== (string) $raw ) {
				$value = sanitize_title( (string) $raw );
				break;
			}
		}
		if ( '' === $value ) {
			return;
		}

		$target = self::filter_id_for_language();
		if ( '' === $target ) {
			return;
		}

		$param = 'brx_' . $target;
		if ( ! isset( $_GET[ $param ] ) || '' === $_GET[ $param ] ) {
			$_GET[ $param ]     = $value;
			$_REQUEST[ $param ] = $value;
		}
	}

	/** The give_country filter element id registered for the current language. */
	private static function filter_id_for_language(): string {
		global $wpdb;

		$table = $wpdb->prefix . 'bricks_filters_element';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return '';
		}

		$current = (string) apply_filters( 'wpml_current_language', null );
		$rows    = $wpdb->get_results( "SELECT filter_id, language, settings FROM {$table} WHERE filter_type LIKE 'filter-%'" );

		$fallback = '';
		foreach ( (array) $rows as $row ) {
			$settings = json_decode( (string) $row->settings, true );
			if ( ! is_array( $settings ) || ( $settings['filterTaxonomy'] ?? '' ) !== self::TAXONOMY ) {
				continue;
			}
			$lang = (string) $row->language;
			if ( $lang === $current ) {
				return (string) $row->filter_id;
			}
			if ( '' === $lang && '' === $fallback ) {
				$fallback = (string) $row->filter_id;
			}
		}

		return $fallback;
	}
}