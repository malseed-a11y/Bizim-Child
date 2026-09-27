<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints the collected donation modals in the footer, outside the card DOM.
 */
final class Modal implements ModuleInterface {

	public function __construct( private ModalRegistry $modals ) {}

	public function register(): void {
		add_action( 'wp_footer', array( $this, 'print_modals' ), 100 );
	}

	public function print_modals(): void {
		if ( $this->modals->is_empty() ) {
			return;
		}
		echo $this->modals->render();
	}
}