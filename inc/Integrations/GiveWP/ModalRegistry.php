<?php
namespace BizimTheme\Integrations\GiveWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request-scoped store for donation modals. Cards writes, Modal reads on footer.
 */
final class ModalRegistry {

	/** @var array<int,string> */
	private array $modals = array();

	public function add( int $form_id, string $html ): void {
		$this->modals[ $form_id ] = $html;
	}

	public function has( int $form_id ): bool {
		return isset( $this->modals[ $form_id ] );
	}

	public function is_empty(): bool {
		return empty( $this->modals );
	}

	public function render(): string {
		return implode( "\n", $this->modals );
	}
}