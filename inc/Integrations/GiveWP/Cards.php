<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Support\Lang;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign card shortcodes: [bizim_progress] and [bizim_donate].
 */
final class Cards implements ModuleInterface {

	public function __construct(
		private Assets $assets,
		private Stats $stats,
		private ModalRegistry $modals
	) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'bizim_progress', array( $this, 'progress' ) );
		add_shortcode( 'bizim_donate', array( $this, 'donate' ) );
	}

	public function register_assets(): void {
		$this->assets->register_style( 'bizim-donation-progress', 'assets/css/components/donation-progress.css' );
		$this->assets->register_style( 'bizim-donation-card', 'assets/css/components/donation-card.css' );
		$this->assets->register_style( 'bizim-donation-modal', 'assets/css/components/donation-modal.css' );
		$this->assets->register_script( 'bizim-donation-modal', 'assets/js/components/donation-modal.js' );
	}

	public function progress( $atts ): string {
		$atts = shortcode_atts( array( 'form' => 0 ), $atts, 'bizim_progress' );
		$fid  = $atts['form'] ? (int) $atts['form'] : (int) get_the_ID();
		$fid = Lang::localized_form_id( $fid );
		if ( ! $fid || ! function_exists( 'give_get_meta' ) ) {
			return '';
		}
		wp_enqueue_style( 'bizim-donation-progress' );
		wp_enqueue_style( 'bizim-donation-card' );

		$s = $this->stats->for_form( $fid );

		$h  = '<div class="bizim-prog">';
		$h .= '<div class="bizim-prog-row"><span class="bizim-prog-pct">' . esc_html( $s['pct'] ) . '% ' . esc_html( Translatable::translate( 'ui_progress_complete', 'tamamlandı' ) ) . '</span><span class="bizim-prog-amt"><strong>' . esc_html( $s['raised'] ) . '</strong> / ' . esc_html( $s['goal'] ) . '</span></div>';
		$h .= '<div class="bizim-prog-track"><span class="bizim-prog-fill" style="--pct:' . esc_attr( $s['pct'] ) . '%"></span></div>';
		$h .= '</div>';
		return $h;
	}

	public function donate( $atts ): string {
		$atts = shortcode_atts( array( 'form' => 0, 'text' => 'Bağış Yap' ), $atts, 'bizim_donate' );
		$fid  = $atts['form'] ? (int) $atts['form'] : (int) get_the_ID();
		$fid = Lang::localized_form_id( $fid );
		if ( ! $fid ) {
			return '';
		}
		wp_enqueue_style( 'bizim-donation-card' );
		wp_enqueue_style( 'bizim-donation-modal' );
		wp_enqueue_script( 'bizim-donation-modal' );

		$id = 'bizim-modal-' . $fid;

		if ( ! $this->modals->has( $fid ) ) {
			$view = add_query_arg( array( 'givewp-route' => 'donation-form-view', 'form-id' => $fid, 'lang' => (string) apply_filters( 'wpml_current_language', null ) ), home_url( '/' ) );
			$form = '<iframe class="bizim-give-iframe" data-src="' . esc_url( $view ) . '" loading="lazy" style="width:100%;height:72vh;min-height:560px;border:0;display:block;background:#fff;"></iframe>';

			$modal  = '<div class="bizim-modal" id="' . esc_attr( $id ) . '" aria-hidden="true">';
			$modal .= '<div class="bizim-modal-overlay" data-bizim-close="1"></div>';
			$modal .= '<div class="bizim-modal-box">';
			$modal .= '<button type="button" class="bizim-modal-close" data-bizim-close="1" aria-label="Kapat">&times;</button>';
			$modal .= '<div class="bizim-modal-form">' . $form . '</div>';
			$modal .= '</div></div>';

			$this->modals->add( $fid, $modal );
		}

		$label = Translatable::translate( 'ui_donate_button_' . substr( md5( (string) $atts['text'] ), 0, 8 ), (string) $atts['text'] );
		return '<button type="button" class="bizim-donate-btn" data-bizim-modal="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</button>';
	}
}