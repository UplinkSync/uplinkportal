<?php
/**
 * Admin notices.
 *
 * @package UplinkPortal\Admin
 */

namespace UplinkPortal\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders public notices.
 */
class Notices {
	/**
	 * No-op placeholder.
	 *
	 * @return void
	 */
	public function render_pro_notice(): void {}

	/**
	 * No-op placeholder.
	 *
	 * @return void
	 */
	public function render_role_security_notice(): void {}

	/**
	 * Renders an error notice when the crypto salt has not been changed from its placeholder value.
	 *
	 * Only shown to users with the `msp_manage` capability. Includes a direct link to the
	 * deployment security guide (Configuration > Security tab).
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function render_crypto_salt_notice(): void {
		if ( ! defined( 'UPLINKPORTAL_CRYPTO_SALT' ) ) {
			return;
		}

		if ( 'replace-with-project-specific-crypto-salt' !== UPLINKPORTAL_CRYPTO_SALT ) {
			return;
		}

		if ( ! current_user_can( 'msp_manage' ) ) {
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>';
		echo esc_html__(
			'UplinkPortal: The crypto salt has not been changed from its placeholder value. Update UPLINKPORTAL_CRYPTO_SALT in wp-config.php before using this plugin in production.',
			'uplinkportal'
		);
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=uplinkportal-settings&tab=security' ) ) . '">';
		echo esc_html__( 'View Deployment Security Guide', 'uplinkportal' );
		echo '</a>';
		echo '</p></div>';
	}

	/**
	 * Checks if notices can be rendered for current screen.
	 *
	 * @return bool
	 */
	private function can_render_for_uplinkportal_screen(): bool {
		if ( ! current_user_can( 'msp_manage' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'toplevel_page_uplinkportal', 'uplinkportal_page_uplinkportal-settings', 'uplinkportal_page_uplinkportal-preview' ), true ) ) {
			return false;
		}

		return true;
	}
}
