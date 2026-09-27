<?php
namespace BizimTheme\Customizer;

use BizimTheme\Support\Strings;

use BizimTheme\Core\ModuleInterface;
use BizimTheme\Support\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trust-counter data model + Customizer panel (one feature).
 */
final class TrustIndicators implements ModuleInterface {

	public function register(): void {
		add_action( 'customize_register', array( $this, 'customize' ) );
	}

	public static function defaults(): array {
		return array(
			1 => array( 'title' => 'Faaliyet ülkesi', 'number' => 5,     'plus' => false, 'icon' => 200 ),
			2 => array( 'title' => 'Gönüllü',          'number' => 800,   'plus' => false, 'icon' => 201 ),
			3 => array( 'title' => 'İnsani proje',     'number' => 120,   'plus' => true,  'icon' => 202 ),
			4 => array( 'title' => 'Yararlanan kişi',  'number' => 25000, 'plus' => true,  'icon' => 203 ),
		);
	}

	public static function number( int $i ): int {
		$d = self::defaults();
		return absint( get_theme_mod( 'bizim_stat' . $i . '_number', $d[ $i ]['number'] ?? 0 ) );
	}

	/**
	 * Counter label.
	 *
	 * Registered with WPML String Translation so the label is translatable even
	 * when it is left at its default (Customizer defaults are never written to
	 * theme_mods, so there would otherwise be nothing for WPML to index).
	 * Appears under String Translation, domain "bizim".
	 */
	public static function title( int $i ): string {
		$d     = self::defaults();
		$value = sanitize_text_field( get_theme_mod( 'bizim_stat' . $i . '_title', $d[ $i ]['title'] ?? '' ) );
		if ( '' === $value ) {
			return '';
		}
		$name = 'counter_' . $i . '_title';
		return Strings::translate( 'bizim', $name, $value );
	}

	public static function plus( int $i ): string {
		$d = self::defaults();
		return get_theme_mod( 'bizim_stat' . $i . '_plus', $d[ $i ]['plus'] ?? false ) ? '1' : '0';
	}

	public static function icon_id( int $i ): int {
		$d = self::defaults();
		return absint( get_theme_mod( 'bizim_stat' . $i . '_icon', $d[ $i ]['icon'] ?? 0 ) );
	}

	public static function icon_svg( int $i ): string {
		return Svg::currentcolor( Svg::inline_from_attachment( self::icon_id( $i ) ) );
	}

	public static function show_icon(): string {
		return get_theme_mod( 'bizim_stats_show_icon', true ) ? 'true' : 'false';
	}

	public static function sanitize_checkbox( $v ): bool {
		return ( true === $v || '1' === $v || 1 === $v || 'true' === $v || 'on' === $v );
	}

	public function customize( \WP_Customize_Manager $wp ): void {
		$wp->add_section(
			'bizim_trust',
			array(
				'title'       => 'Bizim – Güven Göstergeleri',
				'description' => 'Ana sayfa metrik bölümü. Bricks dinamik verisi: {counter_1}..{counter_4}, {counter_N_title}, {counter_N_plus}, {counter_N_icon}.',
				'priority'    => 30,
			)
		);

		$wp->add_setting( 'bizim_stats_show_icon', array( 'default' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ) ) );
		$wp->add_control( 'bizim_stats_show_icon', array( 'label' => 'İkonları göster', 'section' => 'bizim_trust', 'type' => 'checkbox' ) );

		$d = self::defaults();
		for ( $i = 1; $i <= BIZIM_STATS_COUNT; $i++ ) {
			$dd = $d[ $i ];

			$wp->add_setting( 'bizim_stat' . $i . '_number', array( 'default' => $dd['number'], 'sanitize_callback' => 'absint' ) );
			$wp->add_control( 'bizim_stat' . $i . '_number', array( 'label' => '#' . $i . ' Sayı', 'section' => 'bizim_trust', 'type' => 'number', 'input_attrs' => array( 'min' => 0, 'step' => 1 ) ) );

			$wp->add_setting( 'bizim_stat' . $i . '_title', array( 'default' => $dd['title'], 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp->add_control( 'bizim_stat' . $i . '_title', array( 'label' => '#' . $i . ' Başlık', 'section' => 'bizim_trust', 'type' => 'text' ) );

			$wp->add_setting( 'bizim_stat' . $i . '_icon', array( 'default' => $dd['icon'], 'sanitize_callback' => 'absint' ) );
			$wp->add_control( new \WP_Customize_Media_Control( $wp, 'bizim_stat' . $i . '_icon', array( 'label' => '#' . $i . ' İkon', 'section' => 'bizim_trust', 'mime_type' => 'image' ) ) );

			$wp->add_setting( 'bizim_stat' . $i . '_plus', array( 'default' => $dd['plus'], 'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ) ) );
			$wp->add_control( 'bizim_stat' . $i . '_plus', array( 'label' => '#' . $i . ' Artı işareti (+) göster', 'section' => 'bizim_trust', 'type' => 'checkbox' ) );
		}
	}
}