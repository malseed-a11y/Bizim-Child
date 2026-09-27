<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * give_country presentation: card chip, archive filter, ?ulke loop filter, /ulke/ slug.
 */
final class Country implements ModuleInterface {

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'bizim_country', array( $this, 'chip' ) );
		add_shortcode( 'bizim_country_filter', array( $this, 'filter_bar' ) );
		add_filter( 'bricks/posts/query_vars', array( $this, 'filter_query' ), 10, 3 );
		add_filter( 'register_taxonomy_args', array( $this, 'slug' ), 20, 2 );
	}

	public function register_assets(): void {
		$this->assets->register_style( 'bizim-country', 'assets/css/components/country.css' );
	}

	public static function icon_src( int $term_id, string $size = 'thumbnail' ): string {
		$icon = get_term_meta( $term_id, 'country_icon', true );
		if ( ! $icon ) {
			return '';
		}
		$src = wp_get_attachment_image_url( (int) $icon, $size );
		if ( ! $src ) {
			$src = wp_get_attachment_url( (int) $icon );
		}
		return $src ? $src : '';
	}

	public function chip( $atts ): string {
		$atts  = shortcode_atts( array( 'form' => 0 ), $atts, 'bizim_country' );
		$fid   = $atts['form'] ? (int) $atts['form'] : (int) get_the_ID();
		$terms = $fid ? get_the_terms( $fid, 'give_country' ) : array();
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		wp_enqueue_style( 'bizim-country' );

		$t    = $terms[0];
		$src  = self::icon_src( $t->term_id );
		$img  = $src
			? '<img class="bizim-country-flag" src="' . esc_url( $src ) . '" alt="" loading="lazy">'
			: '<span class="bizim-country-flag bizim-country-flag--ph">🌍</span>';
		$link = get_term_link( $t );
		$href = is_wp_error( $link ) ? '#' : $link;

		return '<a class="bizim-country" href="' . esc_url( $href ) . '">' . $img . '<span>' . esc_html( $t->name ) . '</span></a>';
	}

	public function filter_bar( $atts ): string {
		$atts  = shortcode_atts( array( 'base' => 0 ), $atts, 'bizim_country_filter' );
		$terms = get_terms( array( 'taxonomy' => 'give_country', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		wp_enqueue_style( 'bizim-country' );

		$base = $atts['base'] ? get_permalink( (int) $atts['base'] ) : ( is_page() ? get_permalink( get_queried_object_id() ) : home_url( '/' ) );
		$cur  = isset( $_GET['ulke'] ) ? sanitize_title( wp_unslash( $_GET['ulke'] ) ) : '';

		$out  = '<div class="bizim-country-filter">';
		$out .= '<a class="bizim-cf-item' . ( '' === $cur ? ' is-active' : '' ) . '" href="' . esc_url( $base ) . '"><span>Tümü</span></a>';
		foreach ( $terms as $t ) {
			$src  = self::icon_src( $t->term_id );
			$img  = $src ? '<img src="' . esc_url( $src ) . '" alt="" loading="lazy">' : '';
			$link = add_query_arg( 'ulke', $t->slug, $base );
			$out .= '<a class="bizim-cf-item' . ( $cur === $t->slug ? ' is-active' : '' ) . '" href="' . esc_url( $link ) . '">' . $img . '<span>' . esc_html( $t->name ) . '</span></a>';
		}
		$out .= '</div>';
		return $out;
	}

	public function filter_query( $query_vars, $settings, $element_id ) {
		if ( empty( $_GET['ulke'] ) ) {
			return $query_vars;
		}
		$pt = isset( $query_vars['post_type'] ) ? (array) $query_vars['post_type'] : array();
		if ( ! in_array( 'give_forms', $pt, true ) ) {
			return $query_vars;
		}
		$tq   = isset( $query_vars['tax_query'] ) ? $query_vars['tax_query'] : array();
		$tq[] = array( 'taxonomy' => 'give_country', 'field' => 'slug', 'terms' => sanitize_title( wp_unslash( $_GET['ulke'] ) ) );
		$query_vars['tax_query'] = $tq;
		return $query_vars;
	}

	public function slug( array $args, string $taxonomy ): array {
		if ( 'give_country' === $taxonomy ) {
			$args['rewrite']   = array( 'slug' => 'ulke', 'with_front' => false );
			$args['query_var'] = 'ulke';
		}
		return $args;
	}

}
