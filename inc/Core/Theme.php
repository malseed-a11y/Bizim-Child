<?php
namespace BizimTheme\Core;

use BizimTheme\Frontend\GlobalAssets;
use BizimTheme\Frontend\Counters;
use BizimTheme\Frontend\Rtl;
use BizimTheme\Customizer\TrustIndicators;
use BizimTheme\Bricks\DynamicData;
use BizimTheme\Bricks\PieChartElement;
use BizimTheme\Bricks\NumberFormat;
use BizimTheme\Bricks\PaletteColor;
use BizimTheme\Seo\Schema;
use BizimTheme\PostTypes\Service;
use BizimTheme\Media\SvgSupport;
use BizimTheme\Media\AsciiFilenames;
use BizimTheme\Compat\TextdomainPaths;
use BizimTheme\Integrations\ContactForm;
use BizimTheme\Integrations\GiveWP\Provider as GiveWpProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Application container / bootstrap. Builds shared services once, injects them
 * into modules (constructor DI) and registers each module's hooks.
 */
final class Theme {

	private static ?Theme $instance = null;
	private Assets $assets;
	/** @var ModuleInterface[] */
	private array $modules = array();
	private bool $booted = false;

	public static function instance(): Theme {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->assets = new Assets();
	}

	public function assets(): Assets {
		return $this->assets;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->modules = array(
			new GlobalAssets( $this->assets ),
			new Counters( $this->assets ),
			new Rtl( $this->assets ),
			new TrustIndicators(),
			new DynamicData(),
			new PieChartElement(),
			new NumberFormat(),
			new PaletteColor(),
			new Schema(),
			new Service(),
			new TextdomainPaths(),
			new SvgSupport(),
			new AsciiFilenames(),
			new ContactForm( $this->assets ),
			new GiveWpProvider( $this->assets ),
		);

		foreach ( $this->modules as $module ) {
			$module->register();
		}
	}
}