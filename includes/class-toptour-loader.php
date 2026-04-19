<?php
/**
 * Loader class for TOPTOUR Core.
 *
 * Responsible for including module class files and initialising each module.
 *
 * @package ToptourCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Toptour_Loader
 */
class Toptour_Loader {

	/**
	 * Load all module files and call their init() methods.
	 *
	 * @return void
	 */
	public static function init() {
		$modules = array(
			TOPTOUR_CORE_PATH . 'modules/offers/class-offers.php',
			TOPTOUR_CORE_PATH . 'modules/managers/class-managers.php',
			TOPTOUR_CORE_PATH . 'modules/reservations/class-reservations.php',
		);

		foreach ( $modules as $module_file ) {
			if ( file_exists( $module_file ) ) {
				require_once $module_file;
			}
		}

		if ( class_exists( 'Toptour_Offers' ) ) {
			Toptour_Offers::init();
		}

		if ( class_exists( 'Toptour_Managers' ) ) {
			Toptour_Managers::init();
		}

		if ( class_exists( 'Toptour_Reservations' ) ) {
			Toptour_Reservations::init();
		}
	}
}
