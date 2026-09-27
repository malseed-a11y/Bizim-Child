<?php
namespace BizimTheme\Bricks;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the custom Bricks pie-chart element (elements/pie-chart.php).
 */
final class PieChartElement implements ModuleInterface {

	public function register(): void {
		add_action( 'init', array( $this, 'register_element' ), 11 );
	}

	public function register_element(): void {
		if ( ! class_exists( '\Bricks\Elements' ) ) {
			return;
		}
		$file = BIZIM_CHILD_DIR . '/elements/pie-chart.php';
		if ( file_exists( $file ) ) {
			\Bricks\Elements::register_element( $file );
		}
	}
}