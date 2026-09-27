<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turkish strings for the GiveWP v3 donation form UI.
 *
 * The v3 form is a React app that translates through @wordpress/i18n using the
 * `give` text domain. The site locale is tr_TR, but GiveWP ships no Turkish
 * translation files, so every plugin-supplied label renders in English.
 *
 * WordPress asks for those translations through `pre_load_script_translations`;
 * returning a Jed-format payload there short-circuits the missing-file lookup
 * and WP prints the usual `wp.i18n.setLocaleData()` block before the app runs.
 *
 * Text that belongs to a *form* (section titles, button captions) is not here -
 * that lives in each form's own `formBuilderFields` / `formBuilderSettings`.
 */
final class Translations implements ModuleInterface {

	public function register(): void {
		add_action( 'init', array( $this, 'switch_language_on_form_route' ), 0 );
		add_filter( 'pre_load_script_translations', array( $this, 'supply' ), 10, 4 );
		add_filter( 'gettext', array( $this, 'translate_php' ), 10, 3 );
	}

	/**
	 * The donation-form-view route is a custom GiveWP route: WPML does not run
	 * its normal language resolution on it, so the iframe renders in the default
	 * language. The modal passes ?lang=xx, which is honoured here.
	 */
	public function switch_language_on_form_route(): void {
		if ( ! isset( $_GET['givewp-route'] ) || 'donation-form-view' !== $_GET['givewp-route'] ) {
			return;
		}
		$lang = isset( $_GET['lang'] ) ? sanitize_key( (string) $_GET['lang'] ) : '';
		if ( '' === $lang ) {
			return;
		}
		do_action( 'wpml_switch_language', $lang );
	}

	/**
	 * @param string|false|null $translations Existing payload, if any.
	 */
	public function supply( $translations, $file, $handle, $domain ) {
		if ( 'give' !== $domain || is_admin() ) {
			return $translations;
		}
		if ( is_string( $translations ) && '' !== $translations ) {
			return $translations;
		}

		$messages = array(
			'' => array(
				'domain'       => 'messages',
				'lang'         => str_replace( '-', '_', (string) get_locale() ),
				'plural-forms' => 'nplurals=2; plural=(n != 1);',
			),
		);
		foreach ( self::localized() as $source => $value ) {
			$messages[ $source ] = array( $value );
		}

		return (string) wp_json_encode(
			array(
				'translation-revision-date' => gmdate( 'Y-m-d H:i:sO' ),
				'generator'                 => 'bizim-child',
				'domain'                    => 'messages',
				'locale_data'               => array( 'messages' => $messages ),
			)
		);
	}

	/**
	 * Server-rendered strings (payment gateway names and their blurbs) never
	 * reach the JS payload, so they are translated through gettext instead.
	 */
	public function translate_php( $translation, $text, $domain ) {
		if ( 'give' !== $domain || is_admin() ) {
			return $translation;
		}
		$strings = self::localized();
		return $strings[ $text ] ?? $translation;
	}

	/**
	 * The map for the current language.
	 *
	 * Each Turkish value is registered with WPML String Translation, so Arabic
	 * (and any later language) is filled in from the String Translation screen
	 * under the "bizim-givewp" domain rather than hard-coded here. Cached per
	 * request because the gettext filter runs on every translated string.
	 *
	 * @return array<string,string>
	 */
	private static function localized(): array {
		static $cache = array();
		$lang = (string) apply_filters( 'wpml_current_language', null );
		if ( isset( $cache[ $lang ] ) ) {
			return $cache[ $lang ];
		}
		$out = array();
		foreach ( self::strings() as $source => $turkish ) {
			$out[ $source ] = Translatable::translate( 'give_ui_' . substr( md5( $source ), 0, 10 ), $turkish );
		}
		$cache[ $lang ] = $out;
		return $out;
	}

	/**
	 * English source string => Turkish. Keys must match GiveWP's msgid exactly;
	 * anything not listed simply stays in English.
	 *
	 * @return array<string,string>
	 */
	private static function strings(): array {
		return (array) apply_filters(
			'bizim/givewp/translations',
			array(
				// Amount step
				'Enter custom amount'      => 'Diğer tutar',
				'Custom amount'            => 'Diğer tutar',
				'Donation Amount'          => 'Bağış Tutarı',
				'Amount'                   => 'Tutar',
				'One time'                 => 'Tek seferlik',
				'Monthly'                  => 'Aylık',
				'Yearly'                   => 'Yıllık',

				// Navigation / actions
				'Donate now'               => 'Şimdi Bağış Yap',
				'Continue'                 => 'Devam Et',
				'Back'                     => 'Geri',
				'Previous'                 => 'Geri',
				'Go back'                  => 'Geri dön',
				'Next'                     => 'İleri',
				'Loading...'               => 'Yükleniyor...',
				'Processing...'            => 'İşleniyor...',

				// Donor step
				'First name'               => 'Ad',
				'First Name'               => 'Ad',
				'Last name'                => 'Soyad',
				'Last Name'                => 'Soyad',
				'Email Address'            => 'E-posta Adresi',
				'Email address'            => 'E-posta adresi',
				'Company Name'             => 'Şirket Adı',
				'Comment'                  => 'Yorum',
				'Anonymous Donation'       => 'Anonim Bağış',
				'Personal Info'            => 'Kişisel Bilgiler',

				// Summary / payment
				'Donation Summary'         => 'Bağış Özeti',
				'Payment Amount'           => 'Ödeme Tutarı',
				'Giving Frequency'         => 'Bağış Sıklığı',
				'Donation Total'           => 'Toplam Bağış',
				'Total'                    => 'Toplam',
				'Subtotal'                 => 'Ara Toplam',
				'Payment Method'           => 'Ödeme Yöntemi',
				'Test Donation'            => 'Test Bağışı',
				'Offline Donation'         => 'Havale / EFT',
				'100% Secure Donation'     => '%100 Güvenli Bağış',

				// Confirmation receipt. GenerateConfirmationPageReceipt builds these
				// with __( ..., 'give' ), so translate_php() catches them; 'Donor
				// Details' is rendered client-side and arrives via the Jed payload.
				// Adding a key here also registers it in WPML String Translation.
				'Donor Details'            => 'Bağışçı Bilgileri',
				'Donor Name'               => 'Bağışçı Adı',
				'Phone Number'             => 'Telefon Numarası',
				'Billing Address'          => 'Fatura Adresi',
				'Payment Status'           => 'Ödeme Durumu',
				'Completed'                => 'Tamamlandı',
				'Processing Fee'           => 'İşlem Ücreti',
				'Subscription'             => 'Abonelik',
				'Subscription Status'      => 'Abonelik Durumu',
				'Renewal Date'             => 'Yenileme Tarihi',
				'Progress'                 => 'İlerleme',
				'Ongoing'                  => 'Devam Ediyor',

				// Payment gateways (JS side)
				'Donate with %s'           => '%s ile bağış yapın',
				'Test mode is <strong>enabled</strong>. While in test mode no live donations are processed.' => 'Test modu <strong>etkin</strong>. Test modundayken gerçek bağış işlemi yapılmaz.',
				'There are no fields for this gateway and you will not be charged. This payment option is only for you to test the donation experience.' => 'Bu ödeme yönteminde doldurulacak alan yoktur ve hesabınızdan ücret alınmaz. Bu seçenek yalnızca bağış akışını test etmeniz içindir.',

				// Payment gateways (server-rendered)
				'Donate with %1$s'    => '%1$s ile bağış yapın',
				'Test GiveWP with the Test Donation Gateway' => 'GiveWP'. 'i Test Bağışı ödeme yöntemiyle test edin',
				'How it works:'            => 'Nasıl çalışır:',
				'How it works'             => 'Nasıl çalışır',
				'Notice:'                  => 'Not:',

				// Validation
				'This field is required'   => 'Bu alan zorunludur',
				'This is a required field' => 'Bu alan zorunludur',
				'Invalid email address'    => 'Geçersiz e-posta adresi',
				'Please enter a valid email address' => 'Lütfen geçerli bir e-posta adresi girin',
			)
		);
	}
}