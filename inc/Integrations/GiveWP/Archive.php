<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign archive (give-arsivi) presentation: its own stylesheet plus the
 * per-country flag icons injected into the native Bricks radio filter.
 */
final class Archive implements ModuleInterface {

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	private function is_archive(): bool {
		return is_post_type_archive( 'give_forms' ) || is_tax( 'give_country' );
	}

	public function enqueue(): void {
		if ( ! $this->is_archive() ) {
			return;
		}
		$this->assets->style( 'bizim-give-archive', 'assets/css/components/give-archive.css' );

		$terms = get_terms( array( 'taxonomy' => 'give_country', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}
		$css = '';
		foreach ( $terms as $term ) {
			$src = Country::icon_src( $term->term_id, 'medium' );
			if ( ! $src ) {
				continue;
			}
			$css .= '.bizim-give-filter input[value="' . esc_attr( $term->slug ) . '"] ~ .brx-option-text::before{background-image:url(' . esc_url( $src ) . ');}';
		}
		if ( $css ) {
			wp_add_inline_style( 'bizim-give-archive', $css );
		}
	}
}