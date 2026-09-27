<?php
namespace BizimTheme\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared SVG helpers: inline an attachment, force currentColor, sanitise uploads.
 */
final class Svg {

	public static function inline_from_attachment( int $attachment_id ): string {
		$path = $attachment_id ? get_attached_file( $attachment_id ) : '';
		if ( ! $path || ! file_exists( $path ) ) {
			return '';
		}
		$svg = (string) file_get_contents( $path );
		if ( '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return '';
		}
		$svg = preg_replace( '/<\?xml.*?\?>/is', '', $svg );
		$svg = preg_replace( '/<!DOCTYPE.*?>/is', '', $svg );
		return trim( (string) $svg );
	}

	public static function currentcolor( string $svg ): string {
		if ( '' === $svg || false === strpos( $svg, '<svg' ) ) {
			return '';
		}
		$svg  = substr( $svg, strpos( $svg, '<svg' ) );
		$end  = strpos( $svg, '>' );
		$open = substr( $svg, 0, $end );
		$rest = substr( $svg, $end );
		$open = str_replace( 'fill="none"', 'fill="currentColor"', $open );
		if ( false === strpos( $open, 'fill=' ) ) {
			$open = str_replace( '<svg', '<svg fill="currentColor"', $open );
		}
		return trim( $open . $rest );
	}

	public static function sanitize( string $svg ): string {
		if ( '' === $svg || false === stripos( $svg, '<svg' ) || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}
		$prev   = libxml_use_internal_errors( true );
		$dom    = new \DOMDocument();
		$loaded = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $loaded || ! $dom->documentElement ) {
			return '';
		}
		foreach ( array( 'script', 'foreignObject', 'iframe', 'embed', 'use', 'a', 'set', 'animate' ) as $tag ) {
			$nodes = $dom->getElementsByTagName( $tag );
			for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
				$node = $nodes->item( $i );
				if ( $node && $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
		$xpath = new \DOMXPath( $dom );
		$kill  = array();
		foreach ( $xpath->query( '//@*' ) as $attr ) {
			$name = strtolower( $attr->nodeName );
			$val  = strtolower( (string) $attr->nodeValue );
			if ( 0 === strpos( $name, 'on' ) || false !== strpos( $val, 'javascript:' ) ) {
				$kill[] = $attr;
			}
		}
		foreach ( $kill as $attr ) {
			if ( $attr->ownerElement ) {
				$attr->ownerElement->removeAttribute( $attr->nodeName );
			}
		}
		return (string) $dom->saveXML( $dom->documentElement );
	}
}