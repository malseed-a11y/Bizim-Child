<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Support\Lang;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes a form's goal / raised / percent (formatted). Pure service.
 */
final class Stats {

	public function for_form( int $form_id ): array {
		// Each language has its own form, so a campaign's total is the sum of
		// every form under it. The goal is defined once, on the original.
		$form_id = Lang::original_id( $form_id, 'give_forms' );
		$goal    = (float) give_get_meta( $form_id, '_give_set_goal', true );
		$raised  = 0.0;
		foreach ( Lang::campaign_form_ids( $form_id ) as $id ) {
			$raised += (float) give_get_meta( $id, '_give_form_earnings', true );
		}
		$pct    = $goal > 0 ? (int) min( 100, round( $raised / $goal * 100 ) ) : 0;

		return array(
			'pct'    => $pct,
			'raised' => $this->money( $raised ),
			'goal'   => $this->money( $goal ),
		);
	}

	private function money( float $amount ): string {
		if ( function_exists( 'give_currency_filter' ) ) {
			return give_currency_filter( give_format_amount( $amount, array( 'sanitize' => false ) ) );
		}
		return number_format( $amount );
	}
}