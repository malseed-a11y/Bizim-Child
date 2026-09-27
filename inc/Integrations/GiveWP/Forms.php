<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Support\Lang;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * give_forms single view: thumbnail support, Bricks single template, assets, SEO.
 */
final class Forms implements ModuleInterface {

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		add_filter( 'shortcode_atts_give_form', array( $this, 'localize_shortcode' ), 10, 3 );
		add_action( 'after_setup_theme', array( $this, 'thumbnail_support' ) );
		add_filter( 'template_include', array( $this, 'single_template' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'single_assets' ), 20 );
		add_action( 'wp_head', array( $this, 'meta' ), 1 );
	}

	public function thumbnail_support(): void {
		add_post_type_support( 'give_forms', 'thumbnail' );
	}

	public function single_template( $template ) {
		if ( is_singular( 'give_forms' ) ) {
			$theme = locate_template( array( 'single.php', 'singular.php', 'index.php' ) );
			if ( $theme ) {
				return $theme;
			}
		}
		return $template;
	}

	public function single_assets(): void {
		if ( ! is_singular( 'give_forms' ) ) {
			return;
		}
		$this->assets->style( 'bizim-give-single', 'assets/css/give-single.css' );
		foreach ( array( 'givewp-donation-form-embed-app', 'givewp-form-donations-async-data' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}

	public function meta(): void {
		if ( is_singular( 'give_forms' ) ) {
			$d = get_the_excerpt();
			if ( $d ) {
				echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $d ) ) . '" />' . "\n";
			}
		}
	}

	/**
	 * Point GiveWP's own [give_form] shortcode at the current language's form.
	 *
	 * Bricks elements embed the shortcode with a hard-coded Turkish form id, and
	 * that markup is re-created by the local-to-staging sync, so the id is
	 * remapped here instead of being edited in the builder.
	 *
	 * @param array $out
	 * @param array $pairs
	 * @param array $atts
	 * @return array
	 */
	public function localize_shortcode( $out, $pairs = array(), $atts = array() ) {
		if ( empty( $out['id'] ) ) {
			return $out;
		}
		$out['id'] = Lang::localized_form_id( (int) $out['id'] );

		return $out;
	}
}
