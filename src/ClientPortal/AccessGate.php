<?php
/**
 * Portal access gate.
 *
 * @package UplinkPortal\ClientPortal
 */

namespace UplinkPortal\ClientPortal;

use UplinkPortal\Core\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces WordPress login screen access for the portal page.
 */
class AccessGate {
	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Options helper.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Redirects unauthenticated visitors to WordPress login for portal page.
	 *
	 * @return void
	 */
	public function enforce_portal_login(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		$portal_page_id = $this->options->get_portal_page_id();
		if ( $portal_page_id > 0 && is_page( $portal_page_id ) ) {
			auth_redirect();
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( false === strpos( (string) $post->post_content, '[uplinkportal' ) ) {
			return;
		}

		if ( ! has_shortcode( (string) $post->post_content, 'uplinkportal' ) ) {
			return;
		}

		auth_redirect();
	}
}
