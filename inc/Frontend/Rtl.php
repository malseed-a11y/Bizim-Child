<?php
namespace BizimTheme\Frontend;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Right-to-left corrections for the Arabic site.
 *
 * The site was designed left-to-right and Bricks stores physical left/right
 * offsets, which do not mirror when the document flips. This loads Cairo (Lato
 * carries no Arabic glyphs) plus a stylesheet of RTL-only corrections, and only
 * on RTL requests so the Turkish site is untouched.
 */
final class Rtl implements ModuleInterface {

	private const FONT_URL = 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap';

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	public function enqueue(): void {
		if ( ! is_rtl() ) {
			return;
		}
		wp_enqueue_style( 'bizim-cairo', self::FONT_URL, array(), null );
		$this->assets->style( 'bizim-rtl', 'assets/css/rtl.css', array( 'bizim-theme', 'bizim-cairo' ) );
	}
}