<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mirrors each campaign cover image onto its form featured image. Throttled 15 min.
 */
final class CampaignImage implements ModuleInterface {

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_sync' ), 5 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'sync_after_campaign_write' ), 10, 3 );
	}

	public function maybe_sync(): void {
		if ( is_admin() || get_transient( 'bizim_thumbs_synced' ) ) {
			return;
		}
		set_transient( 'bizim_thumbs_synced', 1, 15 * MINUTE_IN_SECONDS );
		$this->sync();
	}

	/**
	 * Mirror the cover image the moment a campaign is saved.
	 *
	 * GiveWP writes campaigns through its REST controller and fires no action of
	 * its own, so without this the featured image only appears on the next
	 * front-end request that happens to fall outside the 15-minute throttle -
	 * i.e. an editor uploads a cover, looks at the home page, and sees nothing.
	 *
	 * @param mixed $response Result of the REST callback.
	 * @return mixed Untouched - this only observes.
	 */
	public function sync_after_campaign_write( $response, $handler, $request ) {
		if ( ! $request instanceof \WP_REST_Request || is_wp_error( $response ) ) {
			return $response;
		}
		if ( ! in_array( strtoupper( (string) $request->get_method() ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return $response;
		}
		if ( false === stripos( (string) $request->get_route(), '/campaigns' ) ) {
			return $response;
		}

		$this->sync();
		delete_transient( 'bizim_thumbs_synced' );

		return $response;
	}
	public function sync(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'give_campaigns';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return;
		}
		$rows = $wpdb->get_results( "SELECT form_id, campaign_image FROM {$table}", ARRAY_A );
		foreach ( (array) $rows as $r ) {
			if ( empty( $r['form_id'] ) || empty( $r['campaign_image'] ) ) {
				continue;
			}
			$aid = $this->url_to_attachment_id( $r['campaign_image'] );
			if ( ! $aid ) {
				continue;
			}
			foreach ( $this->form_and_translations( (int) $r['form_id'] ) as $target ) {
				if ( (int) get_post_thumbnail_id( $target ) !== $aid ) {
					set_post_thumbnail( $target, $aid );
				}
			}
		}
	}

	/**
	 * The campaign's form plus every translation of it.
	 *
	 * A campaign row only knows its default-language form, but each language now
	 * has a form of its own, and the cards and the hero slider read the featured
	 * image from whichever form they are rendering. Mirroring onto the original
	 * alone leaves every Arabic card and slide with no image at all.
	 *
	 * @return int[]
	 */
	private function form_and_translations( int $form_id ): array {
		$ids  = array( $form_id );
		$trid = apply_filters( 'wpml_element_trid', null, $form_id, 'post_give_forms' );
		if ( $trid ) {
			$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_give_forms' );
			foreach ( (array) $translations as $translation ) {
				if ( ! empty( $translation->element_id ) ) {
					$ids[] = (int) $translation->element_id;
				}
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}
	private function url_to_attachment_id( string $url ): int {
		if ( ! $url ) {
			return 0;
		}
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			return (int) $id;
		}
		if ( false !== strpos( $url, '-scaled' ) ) {
			$id = attachment_url_to_postid( str_replace( '-scaled', '', $url ) );
			if ( $id ) {
				return (int) $id;
			}
		}
		global $wpdb;
		$basename = basename( (string) parse_url( $url, PHP_URL_PATH ) );
		$mid      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
				'%' . $wpdb->esc_like( $basename )
			)
		);
		return $mid ? (int) $mid : 0;
	}
}