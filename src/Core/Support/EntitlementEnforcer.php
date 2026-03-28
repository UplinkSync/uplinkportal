<?php
/**
 * Entitlement enforcement helpers.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides entitlement enforcement utilities for automation/recipe executors.
 */
class EntitlementEnforcer {
	/**
	 * Entitlement service.
	 *
	 * @var EntitlementService
	 */
	private EntitlementService $entitlement_service;

	/**
	 * Constructor.
	 *
	 * @param EntitlementService $entitlement_service Entitlement service.
	 */
	public function __construct( EntitlementService $entitlement_service ) {
		$this->entitlement_service = $entitlement_service;
	}

	/**
	 * Checks if execution is allowed for a client/module/provider combination.
	 *
	 * Returns true if:
	 * - Provider is "custom" (always allowed)
	 * - Client has active entitlement
	 * - Client is in grace period (grace hasn't expired)
	 *
	 * Returns false if:
	 * - Entitlement is suspended or canceled
	 * - Grace period has expired
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return bool True if execution allowed.
	 */
	public function is_execution_allowed( int $client_id, string $module_slug, string $provider_id ): bool {
		$client_id   = absint( $client_id );
		$module_slug = sanitize_key( $module_slug );
		$provider_id = sanitize_text_field( $provider_id );

		if ( $client_id <= 0 || '' === $module_slug || '' === $provider_id ) {
			return false;
		}

		// Custom provider is always allowed.
		if ( 'custom' === $provider_id ) {
			return true;
		}

		$status = $this->entitlement_service->get_status_summary( $client_id, $module_slug, $provider_id );
		return ! empty( $status['entitled'] );
	}

	/**
	 * Enforces entitlement gate before automation/recipe execution.
	 *
	 * Should be called at the entry point of any automation/recipe executor.
	 * Logs denial and returns WP_Error if not entitled.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @param string $context Execution context (for logging).
	 * @return true|\WP_Error True if allowed, WP_Error if denied.
	 */
	public function enforce_execution_gate( int $client_id, string $module_slug, string $provider_id, string $context = '' ): bool|\WP_Error {
		if ( $this->is_execution_allowed( $client_id, $module_slug, $provider_id ) ) {
			return true;
		}

		$status = $this->entitlement_service->get_status_summary( $client_id, $module_slug, $provider_id );
		$reason = isset( $status['blocked_reason'] ) ? sanitize_key( (string) $status['blocked_reason'] ) : 'missing';

		$error_code = $this->map_error_code( $reason );
		$message    = $this->map_error_message( $reason );
		$grace_info = array(
			'in_grace'       => ! empty( $status['in_grace'] ),
			'grace_until'    => isset( $status['grace_until'] ) ? $status['grace_until'] : null,
			'days_remaining' => isset( $status['days_remaining'] ) ? $status['days_remaining'] : null,
		);

		/**
		 * Fires when execution is blocked due to entitlement.
		 *
		 * @param int    $client_id Client user ID.
		 * @param string $module_slug Module slug.
		 * @param string $provider_id Provider ID.
		 * @param string $context Execution context.
		 * @param array  $grace_info Grace period info.
		 */
		do_action(
			'uplinkportal_execution_blocked_entitlement',
			$client_id,
			$module_slug,
			$provider_id,
			$context,
			$grace_info
		);

		$this->log_blocked_execution(
			$client_id,
			$module_slug,
			$provider_id,
			$context,
			$error_code,
			$reason,
			$status
		);

		return new \WP_Error( $error_code, $message );
	}

	/**
	 * Returns entitlement status summary for a provider.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return array{status:string,entitled:bool,in_grace:bool,grace_until:string|null,days_remaining:int|null}
	 */
	public function get_status_summary( int $client_id, string $module_slug, string $provider_id ): array {
		return $this->entitlement_service->get_status_summary( $client_id, $module_slug, $provider_id );
	}

	/**
	 * Maps blocked reason to WP_Error code.
	 *
	 * @param string $reason Block reason.
	 * @return string
	 */
	private function map_error_code( string $reason ): string {
		switch ( $reason ) {
			case 'grace_expired':
				return 'grace_period_expired';
			case 'suspended':
				return 'entitlement_suspended';
			case 'canceled':
				return 'entitlement_canceled';
		}

		return 'entitlement_missing';
	}

	/**
	 * Maps blocked reason to user-facing message.
	 *
	 * @param string $reason Block reason.
	 * @return string
	 */
	private function map_error_message( string $reason ): string {
		switch ( $reason ) {
			case 'grace_expired':
				return __( 'Execution blocked: Grace period has expired for this provider.', 'uplinkportal' );
			case 'suspended':
				return __( 'Execution blocked: Entitlement is suspended for this provider.', 'uplinkportal' );
			case 'canceled':
				return __( 'Execution blocked: Entitlement is canceled for this provider.', 'uplinkportal' );
		}

		return __( 'Execution blocked: No active entitlement for this provider.', 'uplinkportal' );
	}

	/**
	 * Logs blocked execution in structured format.
	 *
	 * @param int                 $client_id Client user ID.
	 * @param string              $module_slug Module slug.
	 * @param string              $provider_id Provider id.
	 * @param string              $context Context.
	 * @param string              $error_code WP_Error code.
	 * @param string              $reason Block reason.
	 * @param array<string,mixed> $status Status summary payload.
	 * @return void
	 */
	private function log_blocked_execution(
		int $client_id,
		string $module_slug,
		string $provider_id,
		string $context,
		string $error_code,
		string $reason,
		array $status
	): void {
		$event = array(
			'source'        => 'entitlement_enforcer',
			'time'          => gmdate( 'c' ),
			'event'         => 'execution_blocked_entitlement',
			'result'        => 'error',
			'message'       => sprintf(
				'Blocked execution: client=%d module=%s provider=%s context=%s code=%s reason=%s',
				$client_id,
				$module_slug,
				$provider_id,
				$context,
				$error_code,
				$reason
			),
			'actor_user_id' => 0,
			'ip'            => '',
			'details'       => $status,
		);

		/**
		 * Fires security audit event for blocked entitlement execution.
		 *
		 * @param array<string,mixed> $event Structured event payload.
		 */
		do_action( 'uplinkportal_security_audit_event', $event );

		if ( function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Structured ops logging for runtime denials.
			error_log( 'UplinkPortal: ' . wp_json_encode( $event ) );
		}
	}
}
