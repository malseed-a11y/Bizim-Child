<?php
namespace BizimTheme\Bricks;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns Bricks palette colour ids into usable CSS.
 *
 * The stat_color field stores a Bricks palette id such as "e44a9b", not a hex
 * value, so printing it straight into an inline style produces
 * "background-color:e44a9b", which is invalid and renders transparent. The pie
 * chart element already wraps the id in var(--bricks-color-...); this does the
 * same for every other reader, so a Bricks element can simply output
 * {acf_stat_color} and get a working colour.
 *
 * Doing it here rather than in the Bricks element settings means it survives
 * re-saving the page in the builder, which previously wiped the fix.
 *
 * The chart itself reads raw post meta, not the ACF value, so it is unaffected.
 */
final class PaletteColor implements ModuleInterface {

	/** Fields holding a Bricks palette id. */
	private const FIELDS = array( 'stat_color' );

	public function register(): void {
		foreach ( self::FIELDS as $field ) {
			add_filter( 'acf/format_value/name=' . $field, array( $this, 'to_css' ), 30, 3 );
		}
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public function to_css( $value, $post_id = 0, $field = array() ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$raw = trim( $value );
		if ( '' === $raw ) {
			return $value;
		}
		// Already usable as-is.
		if ( '#' === $raw[0] || 0 === strpos( $raw, 'rgb' ) || 0 === strpos( $raw, 'var(' ) || 0 === strpos( $raw, 'hsl' ) ) {
			return $value;
		}
		$id = preg_replace( '/[^a-z0-9_-]/i', '', $raw );
		if ( '' === $id ) {
			return $value;
		}
		return 'var(--bricks-color-' . $id . ')';
	}
}