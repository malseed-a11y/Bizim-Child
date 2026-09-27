<?php
namespace BizimTheme\Frontend;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide CSS/JS, split from the old bizim.css / bizim.js and enqueued only
 * where the markup they style/animate can appear.
 */
final class GlobalAssets implements ModuleInterface {

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	public function enqueue(): void {
		wp_enqueue_style( 'themify-icons', get_template_directory_uri() . '/assets/css/libs/themify-icons.min.css', array(), '2.3.9' );
		$this->assets->style( 'bizim-theme', 'assets/css/theme.css' );

		if ( is_singular( 'post' ) ) {
			$this->assets->style( 'bizim-single-news', 'assets/css/single-news.css', array( 'bizim-theme' ) );
		}
		if ( is_singular( 'story' ) ) {
			$this->assets->style( 'bizim-single-story', 'assets/css/single-story.css', array( 'bizim-theme' ) );
		}

		$this->assets->script( 'bizim-header', 'assets/js/header.js' );
		if ( is_front_page() ) {
			$this->assets->style( 'bizim-country-strip', 'assets/css/components/country-strip.css', array( 'bizim-theme' ) );
			$this->assets->style( 'bizim-give-embed', 'assets/css/components/give-embed.css', array( 'bizim-theme' ) );
			$this->assets->script( 'bizim-give-embed', 'assets/js/components/give-embed.js' );
		}
	}
}