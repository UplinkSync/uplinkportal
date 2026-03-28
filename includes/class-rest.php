<?php
/**
 * REST module.
 *
 * @package UplinkPortal
 */

use UplinkPortal\ClientPortal\Rest\PortalController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers REST routes.
 */
class UplinkPortal_Rest_Module extends UplinkPortal_Base_Module {
	/**
	 * REST controller.
	 *
	 * @var PortalController
	 */
	private PortalController $controller;

	/**
	 * Constructor.
	 *
	 * @param PortalController $controller REST controller.
	 */
	public function __construct( PortalController $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Registers REST hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
	}
}
