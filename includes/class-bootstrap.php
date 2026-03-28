<?php
/**
 * Bootstrap module.
 *
 * @package UplinkPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots plugin runtime on plugins_loaded.
 */
class UplinkPortal_Bootstrap {
	/**
	 * Initializes runtime modules.
	 *
	 * @return void
	 */
	public static function run(): void {
		$plugin = new UplinkPortal_Main_Module();
		$plugin->register();
	}
}
