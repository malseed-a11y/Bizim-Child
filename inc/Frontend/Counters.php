<?php
namespace BizimTheme\Frontend;

use BizimTheme\Support\Lang;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Odometer trust-counters. Loaded only where the metric block renders.
 */
final class Counters implements ModuleInterface {

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	private function is_active(): bool {
		return is_front_page() || Lang::is_page( 'hakkimizda' );
	}

	public function enqueue(): void {
		if ( ! $this->is_active() ) {
			return;
		}
		wp_enqueue_style( 'odometer-theme', 'https://cdnjs.cloudflare.com/ajax/libs/odometer.js/0.4.8/themes/odometer-theme-default.min.css', array(), '0.4.8' );
		wp_enqueue_script( 'odometer', 'https://cdnjs.cloudflare.com/ajax/libs/odometer.js/0.4.8/odometer.min.js', array(), '0.4.8', true );
		wp_add_inline_script( 'odometer', 'window.odometerOptions={auto:false};', 'before' );
		$this->assets->script( 'bizim-counters', 'assets/js/counters.js', array( 'odometer' ) );
	}
}