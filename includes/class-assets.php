<?php
/**
 * Asset module.
 *
 * @package UplinkPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralized asset registration and enqueue logic.
 */
class UplinkPortal_Assets_Module extends UplinkPortal_Base_Module {
	/**
	 * Registers asset hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		$this->register_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueues admin assets.
	 *
	 * @param string $hook_suffix Current hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'uplinkportal' ) ) {
			return;
		}

		wp_enqueue_style(
			'uplinkportal-admin-console',
			$this->asset_url( 'assets/css/admin-console.css' ),
			array(),
			UPLINKPORTAL_VERSION
		);
	}

	/**
	 * Enqueues frontend assets when portal shortcode is detected.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( false === strpos( (string) $post->post_content, '[uplinkportal' ) ) {
			return;
		}

		wp_enqueue_style(
			'uplinkportal-portal',
			$this->asset_url( 'assets/css/portal.css' ),
			array(),
			UPLINKPORTAL_VERSION
		);

		wp_enqueue_script(
			'uplinkportal-portal',
			uplinkportal_js_url( 'assets/js/portal.js' ),
			array(),
			uplinkportal_js_ver( 'assets/js/portal.js' ),
			true
		);

		wp_localize_script(
			'uplinkportal-portal',
			'UplinkPortalPortal',
			array(
				'rest_data_base' => trailingslashit( rest_url( 'uplinkportal/v1/data' ) ),
				'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
