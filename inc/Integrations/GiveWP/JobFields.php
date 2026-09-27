<?php
namespace BizimTheme\Integrations\GiveWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges WPML translation jobs to GiveWP form storage.
 *
 * WPML's editor offers the single-page fields for translation - they appear in
 * the job as field-impact_report-0 and friends - and translators fill them in.
 * But WPML never writes them onto a give_forms post, because GiveWP keeps that
 * data in its own give_formmeta table. The finished translations therefore sat
 * unused in the job tables while the form still showed the source language,
 * forcing the same text to be translated a second time in String Translation.
 *
 * Copying them across makes the editor the single place a campaign is
 * translated.
 */
final class JobFields {

	/** Fields GiveWP stores outside wp_postmeta, as named inside a job. */
	public const FIELDS = array(
		'impact_report',
		'highlight_title',
		'highlight_text',
		'completed_projects_text',
		'neden_biz_listesi_0_madde_metni',
		'neden_biz_listesi_1_madde_metni',
		'neden_biz_listesi_2_madde_metni',
	);

	/**
	 * Apply every finished job translation to one form.
	 *
	 * @return int Number of fields written.
	 */
	public static function apply( int $form_id ): int {
		global $wpdb;
		if ( ! $form_id || 'give_forms' !== get_post_type( $form_id ) ) {
			return 0;
		}

		$job_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT j.job_id
				   FROM {$wpdb->prefix}icl_translate_job j
				   INNER JOIN {$wpdb->prefix}icl_translation_status st ON st.rid = j.rid
				   INNER JOIN {$wpdb->prefix}icl_translations tr ON tr.translation_id = st.translation_id
				  WHERE tr.element_type = 'post_give_forms' AND tr.element_id = %d
				  ORDER BY j.job_id DESC LIMIT 1",
				$form_id
			)
		);
		if ( ! $job_id ) {
			return 0;
		}

		$meta    = $wpdb->prefix . 'give_formmeta';
		$applied = 0;

		foreach ( self::FIELDS as $field ) {
			$raw = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT field_data_translated FROM {$wpdb->prefix}icl_translate
					  WHERE job_id = %d AND field_type = %s LIMIT 1",
					$job_id,
					'field-' . $field . '-0'
				)
			);
			$translated = trim( self::decode( $raw ) );
			if ( '' === $translated ) {
				continue;
			}

			$current = $wpdb->get_var(
				$wpdb->prepare( "SELECT meta_value FROM {$meta} WHERE form_id = %d AND meta_key = %s LIMIT 1", $form_id, $field )
			);
			if ( null === $current || trim( (string) $current ) === $translated ) {
				continue;
			}

			$wpdb->update( $meta, array( 'meta_value' => $translated ), array( 'form_id' => $form_id, 'meta_key' => $field ), array( '%s' ), array( '%d', '%s' ) );
			$applied++;
		}

		return $applied;
	}

	/** Job payloads are base64, usually deflated on top. */
	public static function decode( $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$bytes = base64_decode( $value, true );
		if ( false === $bytes ) {
			return '';
		}
		$plain = @gzuncompress( $bytes );
		if ( false !== $plain ) {
			return $plain;
		}
		$plain = @gzinflate( $bytes );
		return false !== $plain ? $plain : $bytes;
	}
}