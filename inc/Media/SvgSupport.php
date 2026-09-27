<?php
namespace BizimTheme\Media;

use BizimTheme\Core\ModuleInterface;
use BizimTheme\Support\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safe SVG uploads for administrators (flag icons). Sanitised on upload.
 */
final class SvgSupport implements ModuleInterface {

	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'allow' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_upload' ) );
	}

	public function allow( array $mimes ): array {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}

	public function check( array $data, $file, $filename, $mimes ): array {
		if ( current_user_can( 'manage_options' ) && '.svg' === strtolower( substr( (string) $filename, -4 ) ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}

	public function sanitize_upload( array $file ): array {
		if ( ! empty( $file['type'] ) && 'image/svg+xml' === $file['type'] && ! empty( $file['tmp_name'] ) && file_exists( $file['tmp_name'] ) ) {
			$clean = Svg::sanitize( (string) file_get_contents( $file['tmp_name'] ) );
			if ( '' === $clean ) {
				$file['error'] = 'Geçersiz veya güvensiz SVG dosyası.';
			} else {
				file_put_contents( $file['tmp_name'], $clean );
			}
		}
		return $file;
	}
}