<?php
namespace BizimTheme\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every feature module implements. register() wires its WordPress hooks.
 */
interface ModuleInterface {
	public function register(): void;
}