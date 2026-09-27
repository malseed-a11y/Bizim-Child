<?php
namespace BizimTheme\PostTypes;

use BizimTheme\Core\ModuleInterface;
use BizimTheme\Support\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service CPT glue (registered in SCF): /hizmet/ slug + inline icon dynamic tag.
 */
final class Service implements ModuleInterface {

	public function register(): void {
		add_filter( 'register_post_type_args', array( $this, 'slug' ), 20, 2 );
		add_filter( 'bricks/dynamic_data/register_tags', array( $this, 'tag' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 10, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 9, 3 );
	}

	public function slug( array $args, string $post_type ): array {
		if ( 'service' === $post_type ) {
			$args['rewrite'] = array( 'slug' => 'hizmet', 'with_front' => false );
		}
		return $args;
	}

	public static function icon_svg( int $pid = 0 ): string {
		$pid = $pid ?: (int) get_the_ID();
		$att = ( $pid && function_exists( 'get_field' ) ) ? get_field( 'service_icon', $pid ) : 0;
		if ( is_array( $att ) ) {
			$att = (int) ( $att['ID'] ?? 0 );
		}
		return $att ? Svg::inline_from_attachment( (int) $att ) : '';
	}

	public function tag( array $tags ): array {
		$tags[] = array( 'name' => '{service_icon_svg}', 'label' => 'Hizmet ikonu (SVG)', 'group' => 'Bizim Eller' );
		return $tags;
	}

	public function render_tag( $tag, $post = null, $context = 'text' ) {
		return ( is_string( $tag ) && '{service_icon_svg}' === $tag ) ? self::icon_svg( (int) get_the_ID() ) : $tag;
	}

	public function render_content( $content, $post = null, $context = 'text' ) {
		if ( is_string( $content ) && false !== strpos( $content, '{service_icon_svg}' ) ) {
			$content = str_replace( '{service_icon_svg}', self::icon_svg( (int) get_the_ID() ), $content );
		}
		return $content;
	}
}