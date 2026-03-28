<?php
/**
 * AJAX module.
 *
 * @package UplinkPortal
 */

use UplinkPortal\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Registers AJAX endpoints.
 */
class UplinkPortal_Ajax_Module extends UplinkPortal_Base_Module {
	/**
	 * Settings page dependency.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Constructor.
	 *
	 * @param SettingsPage $settings_page Settings page.
	 */
	public function __construct( SettingsPage $settings_page ) {
		$this->settings_page = $settings_page;
	}

	/**
	 * Registers AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_action( 'wp_ajax_uplinkportal_validation_test', array( $this->settings_page, 'handle_validation_ajax' ) );
	}
}
