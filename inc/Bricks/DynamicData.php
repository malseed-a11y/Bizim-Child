<?php
namespace BizimTheme\Bricks;

use BizimTheme\Support\Strings;

use BizimTheme\Core\ModuleInterface;
use BizimTheme\Customizer\TrustIndicators as Stats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges Customizer/theme values into native Bricks dynamic-data tags.
 */
final class DynamicData implements ModuleInterface {

	public function register(): void {
		add_filter( 'bricks/dynamic_data/register_tags', array( $this, 'tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 9999, 4 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 10, 3 );
		add_filter( 'bricks/frontend/render_data', array( $this, 'render_data' ), 10, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'clean_excerpt' ), 99 );
	}

	/**
	 * A relative-date word, translatable through WPML String Translation.
	 * Registered under the "bizim" domain so Arabic can override it.
	 */
	private static function word( string $word ): string {
		return Strings::translate( 'bizim', 'time_ago_' . sanitize_key( $word ), $word );
	}

	public static function time_ago( int $pid = 0 ): string {
		$pid = $pid ?: (int) get_the_ID();
		$t   = $pid ? (int) get_post_time( 'U', true, $pid ) : 0;
		if ( ! $t ) {
			return '';
		}
		$diff  = max( 0, time() - $t );
		$units = array( array( 31536000, 'yıl' ), array( 2592000, 'ay' ), array( 604800, 'hafta' ), array( 86400, 'gün' ), array( 3600, 'saat' ), array( 60, 'dakika' ) );
		foreach ( $units as $u ) {
			if ( $diff >= $u[0] ) {
				return (int) floor( $diff / $u[0] ) . ' ' . self::word( $u[1] ) . ' ' . self::word( 'önce' );
			}
		}
		return self::word( 'az önce' );
	}

	public function value( string $name ) {
		if ( preg_match( '/^counter_([1-9])(_title|_plus|_icon)?$/', $name, $m ) ) {
			$i = (int) $m[1];
			if ( $i < 1 || $i > BIZIM_STATS_COUNT ) {
				return null;
			}
			switch ( $m[2] ?? '' ) {
				case '_title':
					return Stats::title( $i );
				case '_plus':
					return Stats::plus( $i );
				case '_icon':
					return Stats::icon_svg( $i );
				default:
					return (string) Stats::number( $i );
			}
		}
		if ( preg_match( '/^bizim_stat([1-9])_number$/', $name, $m ) ) {
			return (string) Stats::number( (int) $m[1] );
		}
		if ( preg_match( '/^bizim_stat([1-9])_title$/', $name, $m ) ) {
			return Stats::title( (int) $m[1] );
		}
		if ( 'bizim_reading_time' === $name ) {
			$pid = (int) get_the_ID();
			$w   = $pid ? str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) ) : 0;
			return max( 1, (int) ceil( $w / 180 ) ) . ' dk';
		}
		if ( 'bizim_time_ago' === $name ) {
			return self::time_ago( (int) get_the_ID() );
		}
		if ( 'bizim_stats_show_icon' === $name ) {
			return Stats::show_icon();
		}
		return null;
	}

	public function tags( array $tags ): array {
		for ( $i = 1; $i <= BIZIM_STATS_COUNT; $i++ ) {
			$tags[] = array( 'name' => '{counter_' . $i . '}', 'label' => 'Counter ' . $i . ' — Sayı', 'group' => 'Bizim Eller' );
			$tags[] = array( 'name' => '{counter_' . $i . '_title}', 'label' => 'Counter ' . $i . ' — Başlık', 'group' => 'Bizim Eller' );
			$tags[] = array( 'name' => '{counter_' . $i . '_icon}', 'label' => 'Counter ' . $i . ' — İkon (SVG)', 'group' => 'Bizim Eller' );
			$tags[] = array( 'name' => '{counter_' . $i . '_plus}', 'label' => 'Counter ' . $i . ' — Artı (+) [1/0]', 'group' => 'Bizim Eller' );
		}
		$tags[] = array( 'name' => '{bizim_time_ago}', 'label' => 'Göreceli tarih (önce)', 'group' => 'Bizim Eller' );
		$tags[] = array( 'name' => '{bizim_reading_time}', 'label' => 'Okuma süresi', 'group' => 'Bizim Eller' );
		$tags[] = array( 'name' => '{bizim_stats_show_icon}', 'label' => 'İkonları göster [true/false]', 'group' => 'Bizim Eller' );
		return $tags;
	}

	public function render_tag( $tag, $post = null, $context = 'text', $args = array() ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}
		$v = $this->value( trim( $tag, '{}' ) );
		return null === $v ? $tag : $v;
	}

	public function render_content( $content, $post = null, $context = 'text' ) {
		return $this->replace( $content );
	}

	public function render_data( $content, $post = null ) {
		return $this->replace( $content );
	}

	private function replace( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{' ) ) {
			return $content;
		}
		for ( $i = 1; $i <= BIZIM_STATS_COUNT; $i++ ) {
			$content = str_replace( '{counter_' . $i . '_title}', Stats::title( $i ), $content );
			$content = str_replace( '{counter_' . $i . '_icon}', Stats::icon_svg( $i ), $content );
			$content = str_replace( '{counter_' . $i . '_plus}', Stats::plus( $i ), $content );
			$content = str_replace( '{counter_' . $i . '}', (string) Stats::number( $i ), $content );
			$content = str_replace( '{bizim_stat' . $i . '_number}', (string) Stats::number( $i ), $content );
			$content = str_replace( '{bizim_stat' . $i . '_title}', Stats::title( $i ), $content );
		}
		if ( false !== strpos( $content, '{bizim_time_ago}' ) ) {
			$content = str_replace( '{bizim_time_ago}', self::time_ago( (int) get_the_ID() ), $content );
		}
		if ( false !== strpos( $content, '{bizim_reading_time}' ) ) {
			$content = str_replace( '{bizim_reading_time}', (string) $this->value( 'bizim_reading_time' ), $content );
		}
		$content = str_replace( '{bizim_stats_show_icon}', Stats::show_icon(), $content );
		return $content;
	}

	public function clean_excerpt( $excerpt ) {
		return ( '[]' === trim( (string) $excerpt ) || empty( $excerpt ) ) ? '' : $excerpt;
	}
}