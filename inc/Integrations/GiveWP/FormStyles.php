<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand colours + Lato inside the GiveWP v3 donation-form-view iframe.
 *
 * Two constraints shape this class:
 *
 * 1. The route renders its own minimal document and never fires
 *    wp_enqueue_scripts, so anything enqueued there is silently dropped.
 *    wp_print_footer_scripts does fire (it is how the amount-picker script
 *    reaches the iframe), so everything is echoed from there instead.
 *
 * 2. The font file cannot be loaded from GiveWP Custom Styles: GiveWP prepends
 *    its own :root{...} block to that CSS, which makes a leading @import
 *    invalid per spec and the browser discards it. Only a real link element
 *    gets Lato onto the page.
 */
final class FormStyles implements ModuleInterface {

	private const FONT_URL = 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap';

	public function register(): void {
		add_action( 'wp_print_footer_scripts', array( $this, 'inject' ), 5 );
	}

	private function is_form_view(): bool {
		return isset( $_GET['givewp-route'] ) && 'donation-form-view' === $_GET['givewp-route'];
	}

	public function inject(): void {
		if ( ! $this->is_form_view() ) {
			return;
		}

		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
		echo '<link rel="stylesheet" href="' . esc_url( self::FONT_URL ) . '" media="all">';

		$css  = ':root,.givewp-donation-form,[data-givewp-embed]{--givewp-primary-color:#244594 !important;--givewp-secondary-color:#f5b82e !important;--primaryColor:#244594 !important;--secondaryColor:#f5b82e !important;}';
		$css .= 'html,body,:host,button,input,select,textarea,optgroup,.givewp-donation-form,.givewp-donation-form *{font-family:"Lato",sans-serif !important;}';
		$css .= '.givewp-donation-form button[type="submit"],.givewp-donation-form [class*="donateButton"]{border-radius:10px !important;}';

		echo '<style id="bizim-givewp-form-styles">' . $css . '</style>';
	}
}