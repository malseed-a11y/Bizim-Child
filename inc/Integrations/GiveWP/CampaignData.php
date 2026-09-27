<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Support\Lang;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes GiveWP campaign fields (stored in the custom give_campaigns table,
 * not post meta) to Bricks as dynamic data: {campaign_desc}.
 */
final class CampaignData implements ModuleInterface {

	/** @var array<int,string> per-request cache, keyed by form id. */
	private static array $cache = array();

	public function register(): void {
		add_filter( 'bricks/dynamic_data/register_tags', array( $this, 'tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 10, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 9, 3 );
		add_filter( 'bricks/frontend/render_data', array( $this, 'render_data' ), 9, 2 );
	}

	/**
	 * Campaign short description for a donation form, falling back to the
	 * form excerpt so cards never render empty.
	 */
	public static function description( int $form_id = 0, int $words = 0 ): string {
		$requested = $form_id ?: (int) get_the_ID();
		$form_id   = Lang::original_id( $requested, 'give_forms' );
		if ( ! $form_id ) {
			return '';
		}

		// A translated form carries its own excerpt, written by whoever translated
		// it in WPML's editor - that is where editors actually work, so it wins.
		// Without this the campaign's Turkish short_desc is shown until somebody
		// ALSO translates it as a string, which is exactly the second, hidden step
		// we removed everywhere else when each language got its own form.
		if ( $requested && $requested !== $form_id ) {
			$own = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $requested ) ) );
			if ( '' !== $own && '[]' !== $own ) {
				return $words > 0 ? wp_trim_words( $own, $words, '...' ) : $own;
			}
		}
		if ( ! isset( self::$cache[ $form_id ] ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'give_campaigns';
			$desc  = '';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$desc = (string) $wpdb->get_var( $wpdb->prepare( "SELECT short_desc FROM {$table} WHERE form_id = %d LIMIT 1", $form_id ) );
				if ( '' === $desc ) {
					$link = $wpdb->prefix . 'give_campaign_forms';
					if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $link ) ) === $link ) {
						$desc = (string) $wpdb->get_var( $wpdb->prepare( "SELECT c.short_desc FROM {$table} c INNER JOIN {$link} l ON l.campaign_id = c.id WHERE l.form_id = %d LIMIT 1", $form_id ) );
					}
				}
			}
			if ( '' === trim( $desc ) ) {
				$desc = (string) get_the_excerpt( $form_id );
			}
			self::$cache[ $form_id ] = trim( wp_strip_all_tags( $desc ) );
		}
		$desc = self::$cache[ $form_id ];
		if ( '' !== $desc ) {
			$desc = Translatable::translate( 'campaign_' . $form_id . '_short_desc', $desc );
		}
		if ( $words > 0 && $desc !== '' ) {
			$desc = wp_trim_words( $desc, $words, '...' );
		}
		return $desc;
	}

	public function tags( array $tags ): array {
		$tags[] = array( 'name' => '{campaign_desc}', 'label' => 'Kampanya Açıklaması (kısa)', 'group' => 'Bizim Eller' );
		return $tags;
	}

	public function render_tag( $tag, $post = null, $context = 'text' ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}
		$clean = trim( $tag, '{}' );
		if ( 'campaign_desc' === $clean ) {
			return self::description( (int) get_the_ID() );
		}
		if ( 0 === strpos( $clean, 'campaign_desc:' ) ) {
			return self::description( (int) get_the_ID(), (int) substr( $clean, 14 ) );
		}
		return $tag;
	}

	public function render_content( $content, $post = null, $context = 'text' ) {
		return $this->replace( $content );
	}

	public function render_data( $content, $post = null ) {
		return $this->replace( $content );
	}

	private function replace( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{campaign_desc' ) ) {
			return $content;
		}
		$content = preg_replace_callback(
			'/\{campaign_desc(?::(\d+))?\}/',
			static function ( $m ) {
				return self::description( (int) get_the_ID(), isset( $m[1] ) ? (int) $m[1] : 0 );
			},
			$content
		);
		return $content;
	}
}