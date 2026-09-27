<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Support\Lang;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight donation amount picker.
 *
 * Renders plain markup (no GiveWP embed, so nothing heavy loads with the page).
 * Choosing an amount and pressing the button opens the existing donation modal
 * with the chosen amount appended to the form URL; a companion script running
 * inside the form iframe applies it to the real GiveWP form.
 */
final class AmountPicker implements ModuleInterface {

	public const AMOUNT_PARAM = 'bizim-amount';

	/** @var array<int,array{levels:int[],default:int}> per-request level cache. */
	private static array $levels_cache = array();

	public function __construct(
		private Assets $assets,
		private ModalRegistry $modals
	) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_print_footer_scripts', array( $this, 'inject_into_form_view' ), 20 );
		add_shortcode( 'bizim_amount_picker', array( $this, 'render' ) );
	}

	public function register_assets(): void {
		$this->assets->register_style( 'bizim-amount-picker', 'assets/css/components/amount-picker.css' );
		$this->assets->register_script( 'bizim-amount-picker', 'assets/js/components/amount-picker.js' );
	}

	/**
	 * Inside the GiveWP form iframe: apply the requested amount to the real form.
	 */
	public function inject_into_form_view(): void {
		if ( ! isset( $_GET['givewp-route'] ) || 'donation-form-view' !== $_GET['givewp-route'] ) {
			return;
		}
		$rel = 'assets/js/components/give-amount-apply.js';
		printf(
			'<script src="%s"></script>',
			esc_url( $this->assets->url( $rel ) . '?ver=' . $this->assets->version( $rel ) )
		);
	}

	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'form'    => 0,
				'amounts' => '',
				'default' => 0,
				'title'   => 'Bugün ne kadar bağış yapmak istersiniz?',
				'text'    => 'Bağış Yap',
				'variant' => '',
			),
			$atts,
			'bizim_amount_picker'
		);

		$form_id = (int) $atts['form'] ?: (int) get_the_ID();
		$form_id = Lang::localized_form_id( $form_id );
		if ( ! $form_id ) {
			return '';
		}

		$amounts = array_values( array_filter( array_map( 'intval', explode( ',', (string) $atts['amounts'] ) ) ) );
		$default = (int) $atts['default'];

		// No explicit list: take the form's own GiveWP donation levels.
		if ( ! $amounts ) {
			$levels  = self::levels_for_form( $form_id );
			$amounts = $levels['levels'];
			if ( ! $default ) {
				$default = (int) $levels['default'];
			}
		}
		if ( ! $amounts ) {
			$amounts = array( 100, 250, 500, 1000, 2500, 5000 );
		}
		if ( ! in_array( $default, $amounts, true ) ) {
			$default = $amounts[0];
		}

		$symbol   = function_exists( 'give_currency_symbol' ) ? give_currency_symbol( give_get_currency() ) : '';
		$modal_id = 'bizim-modal-' . $form_id;
		$variant  = $atts['variant'] ? ' bizim-hd--' . sanitize_html_class( $atts['variant'] ) : '';

		$this->register_modal( $form_id, $modal_id );

		wp_enqueue_style( 'bizim-amount-picker' );
		wp_enqueue_script( 'bizim-amount-picker' );
		wp_enqueue_style( 'bizim-donation-modal' );
		wp_enqueue_script( 'bizim-donation-modal' );

		$h  = '<div class="bizim-hd' . $variant . '" data-bizim-picker data-form="' . esc_attr( $form_id ) . '" data-modal="' . esc_attr( $modal_id ) . '">';
		$h .= '<p class="bizim-hd__title">' . esc_html( Translatable::translate( 'ui_picker_title_' . substr( md5( (string) $atts['title'] ), 0, 8 ), (string) $atts['title'] ) ) . '</p>';

		$h .= '<div class="bizim-hd__grid" role="group">';
		foreach ( $amounts as $amount ) {
			$is_default = ( $amount === $default );
			$h .= '<button type="button" class="bizim-hd__level' . ( $is_default ? ' is-selected' : '' ) . '"'
				. ' data-amount="' . esc_attr( $amount ) . '" aria-pressed="' . ( $is_default ? 'true' : 'false' ) . '">'
				. esc_html( $symbol . number_format_i18n( $amount ) ) . '</button>';
		}
		$h .= '</div>';

		$h .= '<div class="bizim-hd__custom">';
		$h .= '<span class="bizim-hd__symbol">' . esc_html( $symbol ) . '</span>';
		$h .= '<input type="number" class="bizim-hd__input" min="1" step="1" inputmode="numeric" placeholder="' . esc_attr( Translatable::translate( 'ui_custom_amount', 'Diğer tutar' ) ) . '" aria-label="' . esc_attr( Translatable::translate( 'ui_custom_amount', 'Diğer tutar' ) ) . '">';
		$h .= '</div>';

		$h .= '<button type="button" class="bizim-hd__submit" data-bizim-modal="' . esc_attr( $modal_id ) . '">' . esc_html( Translatable::translate( 'ui_donate_button_' . substr( md5( (string) $atts['text'] ), 0, 8 ), (string) $atts['text'] ) ) . '</button>';
		$h .= '<p class="bizim-hd__secure">' . esc_html( Translatable::translate( 'ui_secure_donation', '%100 Güvenli Bağış' ) ) . '</p>';
		$h .= '</div>';

		return $h;
	}

	/**
	 * The donation levels configured on a GiveWP v3 form.
	 *
	 * v3 keeps the form builder as a JSON block tree in the `formBuilderFields`
	 * meta; the amounts live on the `givewp/donation-amount` block, with the
	 * preselected one flagged `checked`.
	 *
	 * @return array{levels:int[],default:int}
	 */
	public static function levels_for_form( int $form_id ): array {
		if ( isset( self::$levels_cache[ $form_id ] ) ) {
			return self::$levels_cache[ $form_id ];
		}
		$out = array( 'levels' => array(), 'default' => 0 );

		$raw = (string) get_post_meta( $form_id, 'formBuilderFields', true );
		if ( '' !== $raw ) {
			$blocks = json_decode( $raw, true );
			$attrs  = is_array( $blocks ) ? self::find_amount_block( $blocks ) : null;
			foreach ( (array) ( $attrs['levels'] ?? array() ) as $level ) {
				$value = isset( $level['value'] ) ? (int) $level['value'] : 0;
				if ( $value <= 0 ) {
					continue;
				}
				$out['levels'][] = $value;
				if ( ! empty( $level['checked'] ) ) {
					$out['default'] = $value;
				}
			}
		}

		self::$levels_cache[ $form_id ] = $out;
		return $out;
	}

	/** Depth-first search for the donation-amount block's attributes. */
	private static function find_amount_block( array $nodes ): ?array {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( 'givewp/donation-amount' === ( $node['name'] ?? '' ) ) {
				return is_array( $node['attributes'] ?? null ) ? $node['attributes'] : array();
			}
			if ( ! empty( $node['innerBlocks'] ) && is_array( $node['innerBlocks'] ) ) {
				$found = self::find_amount_block( $node['innerBlocks'] );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}
	/**
	 * Ensure the footer modal for this form exists (deduped by the registry).
	 */
	private function register_modal( int $form_id, string $modal_id ): void {
		if ( $this->modals->has( $form_id ) ) {
			return;
		}
		$view = add_query_arg(
			array( 'givewp-route' => 'donation-form-view', 'form-id' => $form_id, 'lang' => (string) apply_filters( 'wpml_current_language', null ) ),
			home_url( '/' )
		);
		$frame = '<iframe class="bizim-give-iframe" data-src="' . esc_url( $view ) . '" loading="lazy" style="width:100%;height:72vh;min-height:560px;border:0;display:block;background:#fff;"></iframe>';

		$modal  = '<div class="bizim-modal" id="' . esc_attr( $modal_id ) . '" aria-hidden="true">';
		$modal .= '<div class="bizim-modal-overlay" data-bizim-close="1"></div>';
		$modal .= '<div class="bizim-modal-box">';
		$modal .= '<button type="button" class="bizim-modal-close" data-bizim-close="1" aria-label="Kapat">&times;</button>';
		$modal .= '<div class="bizim-modal-form">' . $frame . '</div>';
		$modal .= '</div></div>';

		$this->modals->add( $form_id, $modal );
	}
}