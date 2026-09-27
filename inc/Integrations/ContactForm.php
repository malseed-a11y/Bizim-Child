<?php
namespace BizimTheme\Integrations;

use BizimTheme\Support\Lang;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /iletisim page: phone-input assets + meta description. Loaded only there.
 */
final class ContactForm implements ModuleInterface {

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'wp_head', array( $this, 'meta' ), 1 );
	}

	public function enqueue(): void {
		if ( ! Lang::is_page( 'iletisim' ) ) {
			return;
		}
		wp_enqueue_style( 'intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css', array(), '18.2.1' );
		wp_enqueue_script( 'intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js', array(), '18.2.1', true );
		$this->assets->style( 'bizim-contact', 'assets/css/contact.css', array( 'intl-tel-input' ) );
		$this->assets->script( 'bizim-contact', 'assets/js/contact.js', array( 'intl-tel-input' ) );
	}

	public function meta(): void {
		if ( Lang::is_page( 'iletisim' ) ) {
			echo '<meta name="description" content="Bizim Eller ile iletişime geçin: iletişim formu, telefon, e-posta ve Ankara ofis adresimiz. Sorularınız, iş birliği ve bağış talepleriniz için bize ulaşın." />' . "\n";
		}
	}
}