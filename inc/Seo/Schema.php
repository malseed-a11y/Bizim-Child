<?php
namespace BizimTheme\Seo;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FAQPage JSON-LD on the front page.
 */
final class Schema implements ModuleInterface {

	public function register(): void {
		add_action( 'wp_head', array( $this, 'faq_schema' ), 20 );
	}

	public static function faqs(): array {
		return array(
			array( 'q' => 'Nasıl bağış yapabilirim?', 'a' => 'Web sitemizdeki güvenli ödeme sayfası üzerinden veya derneğin resmî banka hesaplarına havale/EFT ile bağış yapabilirsiniz. Tüm başlıca para birimlerini kabul ediyoruz.' ),
			array( 'q' => 'Dernek resmî olarak ruhsatlı mı?', 'a' => 'Evet. Bizim Eller, Türkiye Cumhuriyeti’nde resmî olarak tescil edilmiş, kamu yararına çalışan bir dernektir ve ilgili resmî mercilerin denetimine tabidir.' ),
			array( 'q' => 'Bağışımın hak sahibine ulaştığından nasıl emin olurum?', 'a' => 'Her projeyi belgeliyoruz; saha raporları ve şeffaf mali kayıtlarla bağışların doğru şekilde hak sahiplerine ulaştığını düzenli olarak raporluyoruz.' ),
			array( 'q' => 'Desteklemek istediğim projeyi seçebilir miyim?', 'a' => 'Evet. Bağış sırasında desteklemek istediğiniz kampanyayı veya projeyi seçebilir, katkınızı dilediğiniz alana yönlendirebilirsiniz.' ),
		);
	}

	public function faq_schema(): void {
		if ( ! is_front_page() ) {
			return;
		}
		$items = array();
		foreach ( self::faqs() as $f ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => $f['q'],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
			);
		}
		$schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items );
		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}