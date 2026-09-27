<?php
namespace BizimTheme\Bricks;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Presents plain numeric field values with thousands separators (8500 -> 8,500).
 *
 * Two entry points:
 *  - every ACF/SCF field listed in fields() is grouped wherever it is output,
 *    Bricks {acf_*} tags included, so no builder change is needed;
 *  - {bizim_number:<alan>} is a reusable Bricks tag for any other meta value.
 */
final class NumberFormat implements ModuleInterface {

	private const THOUSANDS = ',';
	private const DECIMAL   = '.';
	private const PREFIX    = 'bizim_number:';

	public function register(): void {
		foreach ( $this->fields() as $field ) {
			add_filter( 'acf/format_value/name=' . $field, array( $this, 'format_acf' ), 20, 3 );
		}
		add_filter( 'bricks/dynamic_data/register_tags', array( $this, 'tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 9998, 4 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 10, 3 );
		add_filter( 'bricks/frontend/render_data', array( $this, 'render_data' ), 10, 2 );
	}

	/** Field names whose values are displayed as grouped numbers. */
	private function fields(): array {
		return (array) apply_filters( 'bizim/number_format/fields', array( 'beneficiary_count' ) );
	}

	public function format_acf( $value, $post_id = 0, $field = array() ) {
		return $this->format( $value );
	}

	/** 8500 -> "8,500". Anything non-numeric passes through untouched. */
	public function format( $value ) {
		if ( is_array( $value ) || is_object( $value ) || null === $value || is_bool( $value ) ) {
			return $value;
		}
		$raw = trim( (string) $value );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return $value;
		}
		$number   = (float) $raw;
		$decimals = ( floor( $number ) === $number ) ? 0 : 2;
		return number_format( $number, $decimals, self::DECIMAL, self::THOUSANDS );
	}

	public function tags( array $tags ): array {
		$tags[] = array(
			'name'  => '{' . self::PREFIX . 'alan_adi}',
			'label' => 'Sayi (binlik ayracli)',
			'group' => 'Bizim Eller',
		);
		return $tags;
	}

	public function render_tag( $tag, $post = null, $context = 'text', $args = array() ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}
		$name = trim( $tag, '{}' );
		if ( 0 !== strpos( $name, self::PREFIX ) ) {
			return $tag;
		}
		$value = $this->lookup( substr( $name, strlen( self::PREFIX ) ), $post );
		return null === $value ? $tag : $value;
	}

	public function render_content( $content, $post = null, $context = 'text' ) {
		return $this->replace( $content, $post );
	}

	public function render_data( $content, $post = null ) {
		return $this->replace( $content, $post );
	}

	private function replace( $content, $post = null ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{' . self::PREFIX ) ) {
			return $content;
		}
		return preg_replace_callback(
			'/\{' . self::PREFIX . '([A-Za-z0-9_\-]+)\}/',
			function ( $m ) use ( $post ) {
				$value = $this->lookup( $m[1], $post );
				return null === $value ? $m[0] : (string) $value;
			},
			$content
		);
	}

	private function lookup( string $field, $post = null ) {
		$field = trim( $field );
		if ( '' === $field ) {
			return null;
		}
		if ( 0 === strpos( $field, 'acf_' ) ) {
			$field = substr( $field, 4 );
		}
		$pid = ( $post instanceof \WP_Post ) ? (int) $post->ID : (int) get_the_ID();
		if ( ! $pid ) {
			return null;
		}
		$raw = get_post_meta( $pid, $field, true );
		if ( '' === $raw && function_exists( 'get_field' ) ) {
			$raw = get_field( $field, $pid );
		}
		return ( '' === $raw || null === $raw ) ? null : $this->format( $raw );
	}
}