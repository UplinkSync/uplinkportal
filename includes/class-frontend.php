<?php
/**
 * Frontend module.
 *
 * @package UplinkPortal
 */

use UplinkPortal\ClientPortal\AccessGate;
use UplinkPortal\ClientPortal\Shortcode;

defined( 'ABSPATH' ) || exit;

/**
 * Registers frontend portal hooks.
 */
class UplinkPortal_Frontend_Module extends UplinkPortal_Base_Module {
	/**
	 * Shortcode handler.
	 *
	 * @var Shortcode
	 */
	private Shortcode $shortcode;

	/**
	 * Access gate handler.
	 *
	 * @var AccessGate
	 */
	private AccessGate $access_gate;

	/**
	 * Constructor.
	 *
	 * @param Shortcode  $shortcode Shortcode handler.
	 * @param AccessGate $access_gate Access gate.
	 */
	public function __construct( Shortcode $shortcode, AccessGate $access_gate ) {
		$this->shortcode   = $shortcode;
		$this->access_gate = $access_gate;
	}

	/**
	 * Registers frontend hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_action( 'init', array( $this->shortcode, 'register' ) );
		$this->register_action( 'template_redirect', array( $this->access_gate, 'enforce_portal_login' ) );
	}
}
