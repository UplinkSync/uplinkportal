<?php
/**
 * Secure sample custom module scaffold.
 *
 * @package UplinkPortal\Core\Modules\Custom
 */

namespace UplinkPortal\Core\Modules\Custom;

use UplinkPortal\Core\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Production-style sample for MSP-owned custom modules.
 */
class SecureSampleCustomModule implements ModuleInterface {
	/**
	 * Admin-post action slug for sample request.
	 *
	 * @var string
	 */
	private const SAMPLE_ACTION = 'uplinkportal_custom_module_sample_ping';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'custom_secure_sample';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Custom Secure Sample', 'uplinkportal' );
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::SAMPLE_ACTION, array( $this, 'handle_sample_ping_action' ) );
	}

	/**
	 * Whether module is internally enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Capability requirements.
	 *
	 * @return array<int,string>
	 */
	public function capabilities_required(): array {
		return array( 'msp_manage' );
	}

	/**
	 * Handles sample admin-post request.
	 *
	 * @return void
	 */
	public function handle_sample_ping_action(): void {
		if ( ! current_user_can( 'msp_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this custom module action.', 'uplinkportal' ) );
		}

		check_admin_referer( self::SAMPLE_ACTION, 'uplinkportal_custom_module_sample_nonce' );

		set_transient( 'uplinkportal_custom_module_sample_last_ping', gmdate( 'c' ), HOUR_IN_SECONDS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Admin-post payload is nonce-protected above.
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
		$redirect_to = wp_validate_redirect(
			$redirect_to,
			add_query_arg(
				array(
					'page' => 'uplinkportal',
				),
				admin_url( 'admin.php' )
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'uplinkportal_custom_sample' => 'ok',
				),
				$redirect_to
			)
		);
		exit;
	}
}
