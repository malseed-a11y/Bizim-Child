<?php
/**
 * Backward-compatibility global template tags (delegate to services).
 *
 * @package BizimTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BizimTheme\Customizer\TrustIndicators;
use BizimTheme\Bricks\DynamicData;
use BizimTheme\PostTypes\Service;

if ( ! function_exists( 'bizim_stat_number' ) ) {
	function bizim_stat_number( $i ) { return TrustIndicators::number( (int) $i ); }
}
if ( ! function_exists( 'bizim_stat_title' ) ) {
	function bizim_stat_title( $i ) { return TrustIndicators::title( (int) $i ); }
}
if ( ! function_exists( 'bizim_stat_plus' ) ) {
	function bizim_stat_plus( $i ) { return TrustIndicators::plus( (int) $i ); }
}
if ( ! function_exists( 'bizim_stat_icon_svg' ) ) {
	function bizim_stat_icon_svg( $i ) { return TrustIndicators::icon_svg( (int) $i ); }
}
if ( ! function_exists( 'bizim_stats_show_icon' ) ) {
	function bizim_stats_show_icon() { return TrustIndicators::show_icon(); }
}
if ( ! function_exists( 'bizim_time_ago' ) ) {
	function bizim_time_ago( $pid = 0 ) { return DynamicData::time_ago( (int) $pid ); }
}
if ( ! function_exists( 'bizim_service_icon_svg' ) ) {
	function bizim_service_icon_svg( $pid = 0 ) { return Service::icon_svg( (int) $pid ); }
}