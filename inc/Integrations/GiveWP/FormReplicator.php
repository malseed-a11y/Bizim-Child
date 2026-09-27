<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\ModuleInterface;
use BizimTheme\Support\Lang;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives every translated campaign a working donation form of its own, with no
 * developer in the loop.
 *
 * WPML can create the translated give_forms post, but it can never fill it in:
 * GiveWP keeps the form builder, the goal and the SCF fields in its own
 * give_formmeta table, which WPML does not read or write. A translation
 * therefore arrives as an empty shell that renders nothing.
 *
 * This copies the original form's structure onto that shell as soon as it
 * appears, links it to the same campaign, and zeroes its donation counters so
 * the languages cannot double-count. The editor then rewrites the wording in
 * GiveWP's own form builder - no String Translation, no script to run.
 *
 * It never touches a form that already has builder data, so an editor's work is
 * safe: this only ever fills a blank.
 */
final class FormReplicator implements ModuleInterface {

	/** Belongs to the original form alone - must not be inherited. */
	private const SKIP = array( '_give_form_goal_progress', '_wpml_word_count' );

	/** Counters that start from zero on a brand-new language form. */
	private const RESET = array(
		'_give_form_earnings' => '0',
		'_give_form_sales'    => '0',
	);

	/** Taxonomies a form is filtered and sliced by on the front end. */
	private const TAXONOMIES = array( 'give_country', 'give_slider', 'give_forms_category' );

	private const NOTICE = 'bizim_forms_replicated';

	public function register(): void {
		add_action( 'save_post_give_forms', array( $this, 'on_save' ), 20, 1 );
		add_action( 'save_post_give_forms', array( $this, 'claim_language' ), 999, 1 );
		add_action( 'save_post_give_forms', array( $this, 'sync_excerpt' ), 1000, 1 );
		add_action( 'wpml_pro_translation_completed', array( $this, 'on_save' ), 20, 1 );
		add_action( 'wpml_pro_translation_completed', array( $this, 'apply_translated_fields' ), 30, 1 );
		add_action( 'admin_init', array( $this, 'sweep' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/** A single form was just written - fill it if it is an empty translation. */
	public function on_save( $form_id ): void {
		$form_id = (int) $form_id;
		if ( $form_id && $this->populate( $form_id ) ) {
			$this->remember( array( $form_id ) );
		}
	}

	/**
	 * Safety net for every other route into a translation - Translation
	 * Management, the language-column plus icon, a duplicate, a manual insert.
	 * One indexed query that normally returns no rows at all.
	 */
	public function sweep(): void {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! current_user_can( 'edit_give_forms' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( $this->languageless() as $form_id ) {
			$this->claim_language( $form_id );
		}

		$done = array();
		foreach ( $this->pending() as $form_id ) {
			if ( $this->populate( $form_id ) ) {
				$done[] = $form_id;
			}
		}
		foreach ( $this->excerptless() as $form_id ) {
			$this->sync_excerpt( $form_id );
		}

		$this->sync_terms();

		$this->remember( $done );
	}

	/** Translated forms that exist but have no builder data yet. */
	private function pending(): array {
		global $wpdb;
		$translations = $wpdb->prefix . 'icl_translations';
		$form_meta    = $wpdb->prefix . 'give_formmeta';

		if ( ! $this->table_exists( $translations ) || ! $this->table_exists( $form_meta ) ) {
			return array();
		}

		$ids = $wpdb->get_col(
			"SELECT t.element_id
			   FROM {$translations} t
			   INNER JOIN {$wpdb->posts} p ON p.ID = t.element_id
			   LEFT JOIN {$form_meta} m
			          ON m.form_id = t.element_id AND m.meta_key = 'formBuilderFields'
			  WHERE t.element_type = 'post_give_forms'
			    AND t.source_language_code IS NOT NULL
			    AND t.element_id IS NOT NULL
			    AND p.post_status NOT IN ( 'trash', 'auto-draft' )
			    AND m.meta_id IS NULL"
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Copy the original form onto an empty translation.
	 *
	 * @return bool True when this call actually filled a blank form.
	 */
	public function populate( int $form_id ): bool {
		$origin = Lang::original_id( $form_id, 'give_forms' );

		if ( ! $origin || $origin === $form_id ) {
			return false; // Not a translation.
		}
		if ( $this->has_builder( $form_id ) || ! $this->has_builder( $origin ) ) {
			return false; // Already filled, or nothing to copy yet.
		}

		global $wpdb;
		$form_meta = $wpdb->prefix . 'give_formmeta';

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT meta_key, meta_value FROM {$form_meta} WHERE form_id = %d", $origin ),
			ARRAY_A
		);
		if ( ! $rows ) {
			return false;
		}

		foreach ( $rows as $row ) {
			$key = (string) $row['meta_key'];
			if ( in_array( $key, self::SKIP, true ) ) {
				continue;
			}
			$value = array_key_exists( $key, self::RESET ) ? self::RESET[ $key ] : $row['meta_value'];

			$wpdb->delete( $form_meta, array( 'form_id' => $form_id, 'meta_key' => $key ), array( '%d', '%s' ) );
			$wpdb->insert(
				$form_meta,
				array( 'form_id' => $form_id, 'meta_key' => $key, 'meta_value' => $value ),
				array( '%d', '%s', '%s' )
			);
		}

		$this->join_campaign( $origin, $form_id );
		$this->copy_terms( $origin, $form_id );

		return true;
	}

	/** Put the language form under the same campaign, so totals stay combined. */
	private function join_campaign( int $origin, int $form_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'give_campaign_forms';
		if ( ! $this->table_exists( $table ) ) {
			return;
		}
		$campaign = $wpdb->get_var( $wpdb->prepare( "SELECT campaign_id FROM {$table} WHERE form_id = %d LIMIT 1", $origin ) );
		if ( ! $campaign ) {
			return;
		}
		$linked = $wpdb->get_var( $wpdb->prepare( "SELECT campaign_id FROM {$table} WHERE form_id = %d LIMIT 1", $form_id ) );
		if ( $linked ) {
			return;
		}
		$wpdb->insert( $table, array( 'campaign_id' => (int) $campaign, 'form_id' => $form_id ), array( '%d', '%d' ) );
	}

	/**
	 * Mirror the country / slider / category assignments, mapped to the
	 * translated terms where they exist. Without these the form disappears from
	 * the country filter and the home slider in that language.
	 */
	private function copy_terms( int $origin, int $form_id ): void {
		$language = $this->language_of( $form_id );

		foreach ( self::TAXONOMIES as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$already = wp_get_object_terms( $form_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $already ) || $already ) {
				continue;
			}
			$source = wp_get_object_terms( $origin, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $source ) || ! $source ) {
				continue;
			}
			$mapped = array();
			foreach ( $source as $term_id ) {
				$localized = $language
					? apply_filters( 'wpml_object_id', (int) $term_id, $taxonomy, true, $language )
					: $term_id;
				if ( $localized ) {
					$mapped[] = (int) $localized;
				}
			}
			if ( $mapped ) {
				wp_set_object_terms( $form_id, array_unique( $mapped ), $taxonomy );
			}
		}
	}

	/**
	 * Give a brand-new form a language.
	 *
	 * GiveWP creates a campaign's donation form through its own model layer,
	 * which never travels the path WPML watches - so the form is left with no
	 * row in icl_translations at all. WPML filters every give_forms query by
	 * language, and a form belonging to no language belongs to none of them: it
	 * vanishes from the front end in every language at once, while looking
	 * perfectly healthy in the admin.
	 *
	 * Runs after WPML has had its chance (priority 999) and only ever fills a
	 * gap - a form that already has a language is left alone.
	 */
	public function claim_language( $form_id ): void {
		$form_id = (int) $form_id;
		if ( ! $form_id || 'give_forms' !== get_post_type( $form_id ) ) {
			return;
		}
		if ( in_array( (string) get_post_status( $form_id ), array( 'trash', 'auto-draft' ), true ) ) {
			return;
		}
		if ( '' !== $this->language_of( $form_id ) ) {
			return;
		}

		$default  = (string) apply_filters( 'wpml_default_language', null );
		$current  = (string) apply_filters( 'wpml_current_language', null );
		$language = ( '' === $current || 'all' === $current ) ? $default : $current;
		if ( '' === $language ) {
			return;
		}

		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $form_id,
				'element_type'         => 'post_give_forms',
				'trid'                 => null,
				'language_code'        => $language,
				'source_language_code' => null,
			)
		);
	}

	/** Published forms carrying no language at all. */
	private function languageless(): array {
		global $wpdb;
		$translations = $wpdb->prefix . 'icl_translations';
		if ( ! $this->table_exists( $translations ) ) {
			return array();
		}
		$ids = $wpdb->get_col(
			"SELECT p.ID
			   FROM {$wpdb->posts} p
			   LEFT JOIN {$translations} t
			          ON t.element_id = p.ID AND t.element_type = 'post_give_forms'
			  WHERE p.post_type = 'give_forms'
			    AND p.post_status NOT IN ( 'trash', 'auto-draft' )
			    AND t.translation_id IS NULL"
		);
		return array_map( 'intval', (array) $ids );
	}
	/**
	 * Give the form a real excerpt.
	 *
	 * GiveWP writes the literal string "[]" as a donation form's post_content and
	 * leaves post_excerpt empty, so Bricks' {post_excerpt} tag prints "[]" on every
	 * campaign card that has no hand-written excerpt. Bricks reads the column
	 * directly, so the get_the_excerpt filter never gets a chance to clean it up.
	 *
	 * Seed it from the campaign's own short description, read in the form's own
	 * language - which also gives search engines and link previews something real
	 * to show instead of a pair of brackets. An excerpt somebody actually wrote is
	 * never overwritten.
	 */
	public function sync_excerpt( $form_id ): void {
		$form_id = (int) $form_id;
		if ( ! $form_id || 'give_forms' !== get_post_type( $form_id ) ) {
			return;
		}

		$existing = trim( (string) get_post_field( 'post_excerpt', $form_id ) );
		if ( '' !== $existing && '[]' !== $existing ) {
			return;
		}

		$language = $this->language_of( $form_id );
		$previous = (string) apply_filters( 'wpml_current_language', null );
		$switched = ( '' !== $language && $language !== $previous );

		if ( $switched ) {
			do_action( 'wpml_switch_language', $language );
		}

		$description = trim( wp_strip_all_tags( (string) CampaignData::description( $form_id ) ) );

		if ( $switched && '' !== $previous ) {
			do_action( 'wpml_switch_language', $previous );
		}

		if ( '' === $description || '[]' === $description ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array( 'post_excerpt' => $description ),
			array( 'ID' => $form_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $form_id );
	}

	/** Forms whose excerpt is empty or still GiveWP's "[]" placeholder. */
	private function excerptless(): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			  WHERE post_type = 'give_forms'
			    AND post_status NOT IN ( 'trash', 'auto-draft' )
			    AND ( post_excerpt = '' OR post_excerpt = '[]' )"
		);
		return array_map( 'intval', (array) $ids );
	}
	/**
	 * Give every translated form the country and slider assignments its original
	 * carries, mapped to that form's own language.
	 *
	 * WPML copies neither, and the hero slider and the country filter are plain
	 * taxonomy queries - so a freshly translated campaign is invisible in its
	 * language until this runs. Only ever fills a gap: a translation that already
	 * carries its own terms is left untouched.
	 *
	 * Reads assignments straight from the database. wp_get_object_terms() is
	 * language-filtered, and trusting it here once already produced the opposite
	 * bug - Arabic forms silently re-pointed at Turkish terms.
	 *
	 * @return string[] Human-readable list of what was filled.
	 */
	public function sync_terms(): array {
		$filled = array();

		foreach ( $this->translated_forms() as $form_id ) {
			$original = Lang::original_id( $form_id, 'give_forms' );
			$language = $this->language_of( $form_id );
			if ( ! $original || $original === $form_id || '' === $language ) {
				continue;
			}

			foreach ( self::TAXONOMIES as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				if ( $this->assigned_terms( $form_id, $taxonomy ) ) {
					continue;
				}
				$source = $this->assigned_terms( $original, $taxonomy );
				if ( ! $source ) {
					continue;
				}

				$mapped = array();
				foreach ( $source as $term_id ) {
					$localized = apply_filters( 'wpml_object_id', (int) $term_id, $taxonomy, true, $language );
					if ( $localized ) {
						$mapped[] = (int) $localized;
					}
				}
				if ( ! $mapped ) {
					continue;
				}

				wp_set_object_terms( $form_id, array_values( array_unique( $mapped ) ), $taxonomy );
				$filled[] = $form_id . ' ' . $taxonomy . ' -> ' . implode( ',', array_unique( $mapped ) );
			}
		}

		return $filled;
	}

	/** Term ids assigned to a post, read raw so no language filter applies. */
	private function assigned_terms( int $post_id, string $taxonomy ): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tx.term_id
				   FROM {$wpdb->term_relationships} tr
				   INNER JOIN {$wpdb->term_taxonomy} tx ON tx.term_taxonomy_id = tr.term_taxonomy_id
				  WHERE tr.object_id = %d AND tx.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/** Every give_forms post that is a translation of another. */
	private function translated_forms(): array {
		global $wpdb;
		$translations = $wpdb->prefix . 'icl_translations';
		if ( ! $this->table_exists( $translations ) ) {
			return array();
		}
		$ids = $wpdb->get_col(
			"SELECT t.element_id
			   FROM {$translations} t
			   INNER JOIN {$wpdb->posts} p ON p.ID = t.element_id
			  WHERE t.element_type = 'post_give_forms'
			    AND t.source_language_code IS NOT NULL
			    AND t.element_id IS NOT NULL
			    AND p.post_status NOT IN ( 'trash', 'auto-draft' )"
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Write the translations done in WPML's editor onto the form itself.
	 *
	 * The editor DOES offer these fields - they appear in the job as
	 * field-impact_report-0 and friends, and the translator fills them in - but
	 * WPML never lands them on a give_forms post. The work sat complete and
	 * unused in the job tables while the form kept showing the source language,
	 * which is why the same text had to be translated a second time in String
	 * Translation. Applying it here makes the editor the single place a
	 * campaign is translated.
	 *
	 * @return int Number of fields written.
	 */
	public function apply_translated_fields( $form_id ): int {
		return JobFields::apply( (int) $form_id );
	}
	private function language_of( int $post_id ): string {
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		return ( is_array( $details ) && ! empty( $details['language_code'] ) ) ? (string) $details['language_code'] : '';
	}

	private function has_builder( int $form_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'give_formmeta';
		$value = $wpdb->get_var(
			$wpdb->prepare( "SELECT meta_value FROM {$table} WHERE form_id = %d AND meta_key = 'formBuilderFields' LIMIT 1", $form_id )
		);
		return is_string( $value ) && '' !== trim( $value );
	}

	private function table_exists( string $table ): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	private function remember( array $ids ): void {
		if ( $ids ) {
			set_transient( self::NOTICE, array_values( array_unique( $ids ) ), 120 );
		}
	}

	/** Tell the editor what happened, in the language they are working in. */
	public function notice(): void {
		$ids = get_transient( self::NOTICE );
		if ( ! $ids || ! is_array( $ids ) ) {
			return;
		}
		delete_transient( self::NOTICE );

		$links = array();
		foreach ( $ids as $id ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) ),
				esc_html( get_the_title( (int) $id ) ?: ( '#' . (int) $id ) )
			);
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>Bizim Eller:</strong> %s <em>%s</em></p></div>',
			wp_kses_post( sprintf( 'yeni dil için bağış formu hazırlandı: %s.', implode( ', ', $links ) ) ),
			esc_html( 'Formu açıp metinleri form oluşturucuda çevirin.' )
		);
	}
}