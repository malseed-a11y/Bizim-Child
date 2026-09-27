<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Support\Strings;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the "Ek Bilgiler & Ulke" campaign fields translatable with WPML.
 *
 * GiveWP v3 keeps form meta in its own give_formmeta table rather than
 * wp_postmeta, so WPML's custom-field translation never sees these values and
 * no WPML setting can expose them. Instead each value is registered with WPML
 * String Translation as it is read, and the translated value is returned in
 * its place. The strings appear under the "bizim-givewp" domain.
 *
 * Only give_forms are handled: highlight_title / highlight_text also exist on
 * regular posts, where they live in postmeta and WPML already handles them.
 */
final class Translatable implements ModuleInterface {

	public const DOMAIN = 'bizim-givewp';

	private const FIELDS = array(
		'completed_projects_text',
		'impact_report',
		'highlight_title',
		'highlight_text',
		'madde_metni',
	);

	public function register(): void {
		add_filter( 'get_post_metadata', array( $this, 'translate_form_builder' ), 20, 4 );
		foreach ( self::FIELDS as $field ) {
			add_filter( 'acf/format_value/name=' . $field, array( $this, 'translate_field' ), 30, 3 );
		}
	}

	/**
	 * GiveWP v3 keeps each form's visible text (section titles and descriptions,
	 * field labels, button captions) inside the formBuilderFields /
	 * formBuilderSettings JSON in give_formmeta, which WPML cannot reach. The
	 * JSON is decoded here on read, every human-readable value is registered as
	 * a WPML string, and the translation is put back before GiveWP parses it.
	 *
	 * @param mixed $value
	 * @param int $object_id
	 * @param string $meta_key
	 * @return mixed
	 */
	public function translate_form_builder( $value, $object_id = 0, $meta_key = '', $single = false ) {
		if ( ! in_array( $meta_key, array( 'formBuilderFields', 'formBuilderSettings' ), true ) ) {
			return $value;
		}
		if ( is_admin() ) {
			return $value;
		}
		static $busy = false;
		if ( $busy ) {
			return $value;
		}

		// GiveWP reads these keys many times per request; decoding and walking the
		// JSON each time is far too slow, so the result is memoised per form,
		// key and language.
		static $memo = array();
		$memo_key = (int) $object_id . '|' . $meta_key . '|' . (string) apply_filters( 'wpml_current_language', null );
		if ( isset( $memo[ $memo_key ] ) ) {
			return $memo[ $memo_key ];
		}

		// GiveWP short-circuits this same filter to read its own give_formmeta
		// table, so $value usually arrives already populated.
		$raw = null;
		if ( is_string( $value ) ) {
			$raw = $value;
		} elseif ( is_array( $value ) && isset( $value[0] ) && is_string( $value[0] ) ) {
			$raw = $value[0];
		}
		if ( null === $raw ) {
			$busy = true;
			$meta = get_post_meta( (int) $object_id, $meta_key, true );
			$busy = false;
			if ( is_string( $meta ) ) {
				$raw = $meta;
			}
		}

		if ( ! is_string( $raw ) || '' === $raw ) {
			return $value;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return $value;
		}
		$id   = (int) $object_id;
		$keys = array( 'title', 'description', 'label', 'firstNameLabel', 'lastNameLabel', 'firstNamePlaceholder', 'lastNamePlaceholder', 'donateButtonCaption', 'multiStepFirstButtonText', 'multiStepNextButtonText', 'heading', 'goalAchievedMessage' );
		self::walk( $data, $keys, $id, $meta_key );
		$memo[ $memo_key ] = array( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		return $memo[ $memo_key ];
	}

	/** Recursively translate every known text key in the decoded JSON. */
	private static function walk( array &$node, array $keys, int $form_id, string $meta_key ): void {
		foreach ( $node as $k => &$v ) {
			if ( is_array( $v ) ) {
				self::walk( $v, $keys, $form_id, $meta_key );
				continue;
			}
			if ( ! is_string( $v ) || '' === trim( $v ) ) {
				continue;
			}
			if ( ! in_array( (string) $k, $keys, true ) ) {
				continue;
			}
			$name = 'form_' . $form_id . '_' . $k . '_' . substr( md5( $v ), 0, 8 );
			$v    = self::translate( $name, $v );
		}
	}

	/**
	 * GiveWP builds the donation form from its own query builder over
	 * give_formmeta, bypassing get_post_meta entirely, so no meta filter can
	 * reach the text inside the form. The strings are registered with WPML
	 * already, so the rendered route is buffered and each known original is
	 * swapped for its translation.
	 *
	 * Only exact matches of strings this theme registered are replaced, in both
	 * plain and JSON-escaped form, because the form data is embedded as JSON.
	 */
	/** Retained for reference; no longer hooked - each language has its own form. */
	public function buffer_form_route(): void {
		if ( ! isset( $_GET['givewp-route'] ) || 'donation-form-view' !== $_GET['givewp-route'] ) {
			return;
		}
		ob_start( array( $this, 'filter_form_route_output' ) );
	}

	public function filter_form_route_output( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$map = self::form_string_map();
		if ( ! $map ) {
			return $html;
		}
		$search  = array();
		$replace = array();
		foreach ( $map as $from => $to ) {
			$search[]  = $from;
			$replace[] = $to;
			$fromJson = trim( (string) wp_json_encode( $from ), '"' );
			$toJson   = trim( (string) wp_json_encode( $to ), '"' );
			if ( $fromJson !== $from ) {
				$search[]  = $fromJson;
				$replace[] = $toJson;
			}
		}
		return str_replace( $search, $replace, $html );
	}

	/**
	 * Original => translation for every form string, in the current language.
	 * Longest first, so a short string can never clobber a longer one that
	 * contains it.
	 *
	 * @return array<string,string>
	 */
	private static function form_string_map(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		global $wpdb;
		$cache = array();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT name, value FROM {$wpdb->prefix}icl_strings WHERE context = %s", self::DOMAIN ) );
		foreach ( (array) $rows as $row ) {
			$original = (string) $row->value;
			if ( '' === trim( $original ) ) {
				continue;
			}
			$translated = apply_filters( 'wpml_translate_single_string', $original, self::DOMAIN, (string) $row->name );
			if ( is_string( $translated ) && '' !== $translated && $translated !== $original ) {
				$cache[ $original ] = $translated;
			}
		}
		uksort( $cache, static function ( $a, $b ) {
			return strlen( $b ) <=> strlen( $a );
		} );
		return $cache;
	}

	public function translate_field( $value, $post_id = 0, $field = array() ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $value;
		}

		// Never translate inside wp-admin. "Ek Bilgiler & Ulke" fills its inputs
		// with get_field(), so a translated value here lands in the textarea and is
		// written straight back to give_formmeta on save - silently replacing the
		// stored text with whatever language the admin happened to be viewing in.
		// That corrupted 20 field values across 8 campaigns. An editing screen must
		// always show what is actually stored; translation belongs on the front end.
		if ( is_admin() ) {
			return $value;
		}
		$id = (int) $post_id;
		if ( ! $id || 'give_forms' !== get_post_type( $id ) ) {
			return $value;
		}
		$name = isset( $field['name'] ) ? (string) $field['name'] : '';
		if ( '' === $name ) {
			return $value;
		}
		// Each language has its own form, carrying its own copy of every field.
		// When an editor has actually written a translation there - Bagis Formlari
		// -> Ek Bilgiler & Ulke with the admin language switched - that stored value
		// IS the translation, so use it. Routing it through String Translation
		// instead would demand a second, hidden pass per language, which is exactly
		// what having one form per language was meant to remove.
		//
		// Only when the translation still holds the original's text verbatim (never
		// touched) do we fall back to String Translation, so campaigns already
		// translated that way keep working.
		// Compare STORED against STORED. $value has already been through ACF's own
		// formatting by the time this filter runs - wpautop rewrites a wysiwyg field
		// - so comparing it against the raw column would never match and would skip
		// translation for every rich-text field.
		$original = \BizimTheme\Support\Lang::original_id( $id, 'give_forms' );

		// An original form holds source text - there is nothing to translate, and
		// sending it through String Translation only re-registers it as a string
		// on every Turkish page view. Campaign translation lives in WPML's editor
		// now (see JobFields), so String Translation is not consulted for fields.
		if ( ! $original || $original === $id ) {
			return $value;
		}

		if ( $original !== $id ) {
			$mine   = self::stored_on_form( $id, $name );
			$source = self::stored_on_form( $original, $name );
			if ( '' !== $mine && $mine !== $source ) {
				return $value;
			}
		}

		$key = 'form_' . $id . '_' . $name;
		if ( 'madde_metni' === $name ) {
			$key .= '_' . substr( md5( $value ), 0, 8 );
		}
		return self::translate( $key, $value );
	}

	/**
	 * Raw field value as stored against a form.
	 *
	 * Read straight from give_formmeta - these fields never reach wp_postmeta,
	 * which is also why WPML's translation editor cannot see them at all. Going
	 * through get_field() here would re-enter the very filter we are inside.
	 * Returns '' when the field cannot be resolved (repeater sub-fields), which
	 * deliberately leaves those on the String Translation path.
	 */
	private static function stored_on_form( int $form_id, string $name ): string {
		static $cache = array();
		$slot = $form_id . '|' . $name;
		if ( ! isset( $cache[ $slot ] ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'give_formmeta';
			$found = $wpdb->get_var(
				$wpdb->prepare( "SELECT meta_value FROM {$table} WHERE form_id = %d AND meta_key = %s LIMIT 1", $form_id, $name )
			);
			$cache[ $slot ] = is_string( $found ) ? $found : '';
		}
		return $cache[ $slot ];
	}

	/**
	 * Register a value as a WPML string and return its translation.
	 * Falls back to the original when String Translation is not active.
	 */
	public static function translate( string $key, string $value ): string {
		return Strings::translate( self::DOMAIN, $key, $value );
	}
}