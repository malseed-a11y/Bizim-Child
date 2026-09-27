<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\Assets;
use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GiveWP feature sub-bootstrap. Builds shared services (Stats, ModalRegistry)
 * once and injects them into the concrete units.
 */
final class Provider implements ModuleInterface {

	/** @var ModuleInterface[] */
	private array $units = array();

	public function __construct( private Assets $assets ) {}

	public function register(): void {
		$stats = new Stats();
		$modal = new ModalRegistry();

		$this->units = array(
			new Forms( $this->assets ),
			new Cards( $this->assets, $stats, $modal ),
			new Modal( $modal ),
			new AmountPicker( $this->assets, $modal ),
			new FormStyles(),
			new Translations(),
			new Translatable(),
			new CampaignImage(),
			new Country( $this->assets ),
			new Archive( $this->assets ),
			new CountryFilterLink(),
			new CampaignData(),
			new AdminExtras(),
			new FormReplicator(),
		);

		foreach ( $this->units as $unit ) {
			$unit->register();
		}
	}
}