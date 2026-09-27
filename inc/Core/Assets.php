<?php
namespace BizimTheme\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central asset service: single source of truth for theme asset URLs + versions.
 */
final class Assets {

	private string $dir;
	private string $uri;

	public function __construct() {
		$this->dir = BIZIM_CHILD_DIR;
		$this->uri = BIZIM_CHILD_URI;
	}

	public function version( string $relative ): string {
		$path = $this->dir . '/' . ltrim( $relative, '/' );
		return is_readable( $path ) ? (string) filemtime( $path ) : '1.0.0';
	}

	public function url( string $relative ): string {
		return $this->uri . '/' . ltrim( $relative, '/' );
	}

	public function style( string $handle, string $relative, array $deps = array() ): void {
		wp_enqueue_style( $handle, $this->url( $relative ), $deps, $this->version( $relative ) );
	}

	public function register_style( string $handle, string $relative, array $deps = array() ): void {
		wp_register_style( $handle, $this->url( $relative ), $deps, $this->version( $relative ) );
	}

	public function script( string $handle, string $relative, array $deps = array(), bool $in_footer = true ): void {
		wp_enqueue_script( $handle, $this->url( $relative ), $deps, $this->version( $relative ), $in_footer );
	}

	public function register_script( string $handle, string $relative, array $deps = array(), bool $in_footer = true ): void {
		wp_register_script( $handle, $this->url( $relative ), $deps, $this->version( $relative ), $in_footer );
	}
}