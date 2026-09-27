<?php
namespace BizimTheme\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Language-aware helpers.
 *
 * is_page( 'iletisim' ) matches on the slug, so it silently fails on every
 * translation - the Arabic contact page is /ar/تواصل-معنا/. These helpers
 * compare against the default-language original instead, so a check written
 * once keeps working in every language.
 */
final class Lang {

	/**
	 * True when the current page is the given default-language page, or any
	 * translation of it.
	 */
	public static function is_page( string $default_slug ): bool {
		$current = (int) get_queried_object_id();
		if ( ! $current || ! is_page() ) {
			return false;
		}
		return self::original_slug( $current ) === $default_slug;
	}

	/**
	 * The donation form to render for the language being viewed.
	 *
	 * Each language has its own GiveWP form (a WPML translation carrying its own
	 * form-builder JSON), so the visitor gets a form written in their language
	 * rather than the Turkish original run through a substitution layer.
	 *
	 * Falls back to the original whenever a language has no form yet, or has one
	 * that was never populated - so adding a language later degrades to Turkish
	 * instead of breaking.
	 */
	public static function localized_form_id( int $form_id ): int {
		if ( ! $form_id ) {
			return 0;
		}
		$current = (string) apply_filters( 'wpml_current_language', null );
		$default = (string) apply_filters( 'wpml_default_language', null );
		$origin  = self::original_id( $form_id, 'give_forms' );

		if ( '' === $current || $current === $default ) {
			return $origin;
		}

		$translated = apply_filters( 'wpml_object_id', $origin, 'give_forms', false, $current );
		if ( ! $translated || (int) $translated === $origin ) {
			return $origin;
		}

		$built = give_get_meta( (int) $translated, 'formBuilderFields', true );

		return ( is_string( $built ) && '' !== $built ) ? (int) $translated : $origin;
	}

	/**
	 * Every form belonging to the same campaign, across all languages.
	 *
	 * @return int[]
	 */
	public static function campaign_form_ids( int $form_id ): array {
		global $wpdb;
		$origin = self::original_id( $form_id, 'give_forms' );
		$table  = $wpdb->prefix . 'give_campaign_forms';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array( $origin );
		}
		$campaign = $wpdb->get_var( $wpdb->prepare( "SELECT campaign_id FROM {$table} WHERE form_id = %d LIMIT 1", $origin ) );
		if ( ! $campaign ) {
			return array( $origin );
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT form_id FROM {$table} WHERE campaign_id = %d", $campaign ) );
		$ids = array_map( 'intval', (array) $ids );
		if ( ! in_array( $origin, $ids, true ) ) {
			$ids[] = $origin;
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * The default-language original behind any post id.
	 *
	 * GiveWP stores form data (goal, earnings, the form builder) in its own
	 * give_formmeta table keyed to the original form id. A WPML translation is a
	 * separate post with no rows there, so anything reading Give data must ask
	 * for the original id first or it reads zeroes.
	 */
	public static function original_id( int $post_id, string $post_type = '' ): int {
		if ( ! $post_id ) {
			return 0;
		}
		$default = apply_filters( 'wpml_default_language', null );
		if ( ! $default ) {
			return $post_id;
		}
		$post_type = $post_type ?: (string) get_post_type( $post_id );
		$original  = apply_filters( 'wpml_object_id', $post_id, $post_type, true, $default );
		return $original ? (int) $original : $post_id;
	}

	/** Slug of the default-language original behind any post id. */
	public static function original_slug( int $post_id ): string {
		$default = apply_filters( 'wpml_default_language', null );
		if ( $default ) {
			$original = apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), true, $default );
			if ( $original ) {
				$post_id = (int) $original;
			}
		}
		return (string) get_post_field( 'post_name', $post_id );
	}
}