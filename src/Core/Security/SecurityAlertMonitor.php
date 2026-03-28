<?php
/**
 * Security alert monitor.
 *
 * @package UplinkPortal\Core\Security
 */

namespace UplinkPortal\Core\Security;

use UplinkPortal\Core\Support\Options;
use UplinkPortal\Core\Support\TransportSecurityPolicy;

defined( 'ABSPATH' ) || exit;

/**
 * Detects repeated security failures and stores lightweight alerts.
 */
class SecurityAlertMonitor {
	/**
	 * Alert option key.
	 *
	 * @var string
	 */
	private const ALERT_OPTION = 'uplinkportal_security_alerts';

	/**
	 * Options helper.
	 *
	 * @var Options|null
	 */
	private ?Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options|null $options Options helper.
	 */
	public function __construct( ?Options $options = null ) {
		$this->options = $options;
	}

	/**
	 * Registers monitor hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'uplinkportal_security_audit_event', array( $this, 'handle_event' ) );
	}

	/**
	 * Processes one audit event payload.
	 *
	 * @param array<string,mixed> $event Event payload.
	 * @return void
	 */
	public function handle_event( array $event ): void {
		$result = isset( $event['result'] ) ? sanitize_key( (string) $event['result'] ) : 'unknown';
		if ( ! in_array( $result, array( 'error', 'failed', 'denied' ), true ) ) {
			return;
		}

		$source      = isset( $event['source'] ) ? sanitize_key( (string) $event['source'] ) : 'unknown';
		$actor_id    = isset( $event['actor_user_id'] ) ? absint( $event['actor_user_id'] ) : 0;
		$ip          = isset( $event['ip'] ) ? sanitize_text_field( (string) $event['ip'] ) : '';
		$window      = max( 60, absint( apply_filters( 'uplinkportal_security_alert_window_seconds', 10 * MINUTE_IN_SECONDS ) ) );
		$threshold   = max( 2, absint( apply_filters( 'uplinkportal_security_alert_threshold', 5 ) ) );
		$counter_key = 'uplinkportal_alert_counter_' . md5( $source . '|' . $actor_id . '|' . $ip );
		$count       = (int) get_transient( $counter_key );
		++$count;
		set_transient( $counter_key, $count, $window );

		if ( $count < $threshold ) {
			return;
		}

		$alert = array(
			'time'          => gmdate( 'c' ),
			'source'        => $source,
			'event'         => isset( $event['event'] ) ? sanitize_key( (string) $event['event'] ) : 'unknown',
			'result'        => $result,
			'message'       => isset( $event['message'] ) ? sanitize_text_field( (string) $event['message'] ) : '',
			'actor_user_id' => $actor_id,
			'ip'            => $ip,
			'count'         => $count,
			'window'        => $window,
		);

		$alerts = get_option( self::ALERT_OPTION, array() );
		$alerts = is_array( $alerts ) ? $alerts : array();
		array_unshift( $alerts, $alert );
		$alerts = array_slice( $alerts, 0, 25 );
		update_option( self::ALERT_OPTION, $alerts, false );

		/**
		 * Fires when the security alert threshold is reached.
		 *
		 * @param array<string,mixed> $alert Alert payload.
		 * @param array<string,mixed> $event Original audit event.
		 */
		do_action( 'uplinkportal_security_alert_triggered', $alert, $event );

		$this->forward_alert_to_backend( $alert );

		delete_transient( $counter_key );
	}

	/**
	 * Returns recent security alerts.
	 *
	 * @param int $limit Max alerts.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent_alerts( int $limit = 10 ): array {
		$alerts = get_option( self::ALERT_OPTION, array() );
		if ( ! is_array( $alerts ) ) {
			return array();
		}

		return array_slice( $alerts, 0, max( 1, absint( $limit ) ) );
	}

	/**
	 * Forwards one alert payload to backend if configured.
	 *
	 * @param array<string,mixed> $alert Alert payload.
	 * @return void
	 */
	private function forward_alert_to_backend( array $alert ): void {
		if ( ! $this->options instanceof Options ) {
			return;
		}

		$monitoring = $this->options->get_monitoring_profile();
		$mode       = isset( $monitoring['mode'] ) ? sanitize_key( (string) $monitoring['mode'] ) : 'none';
		if ( 'none' === $mode ) {
			return;
		}

		if ( 'generic_webhook' === $mode ) {
			$webhook_url = isset( $monitoring['webhook_url'] ) ? esc_url_raw( (string) $monitoring['webhook_url'] ) : '';
			if ( '' === $webhook_url ) {
				return;
			}

			$transport_check = TransportSecurityPolicy::validate_url( $webhook_url, 'security_alert_webhook' );
			if ( is_wp_error( $transport_check ) ) {
				return;
			}

			$body   = (string) wp_json_encode(
				array(
					'source'      => 'uplinkportal',
					'instance_id' => $this->options->get_instance_id(),
					'site_url'    => home_url( '/' ),
					'alert'       => $alert,
				)
			);
			$secret = $this->options->get_backend_service_token();
			$sig    = hash_hmac( 'sha256', $body, $secret );

			wp_remote_post(
				$webhook_url,
				TransportSecurityPolicy::secure_remote_args(
					array(
						'timeout' => 8,
						'headers' => array(
							'Content-Type'             => 'application/json',
							'X-UplinkPortal-Signature' => 'sha256=' . $sig,
						),
						'body'    => $body,
					)
				)
			);
			return;
		}

		$base_url = $this->options->get_backend_base_url();
		$token    = $this->options->get_backend_service_token();
		if ( '' === $base_url || '' === $token ) {
			return;
		}

		$endpoint = apply_filters( 'uplinkportal_security_alert_endpoint', '/uplinkportal/v1/security-alerts' );
		$endpoint = is_string( $endpoint ) ? $endpoint : '';
		if ( '' === $endpoint ) {
			return;
		}

		$transport_check = TransportSecurityPolicy::validate_url( $base_url, 'security_alerts' );
		if ( is_wp_error( $transport_check ) ) {
			return;
		}

		$url  = untrailingslashit( $base_url ) . '/' . ltrim( $endpoint, '/' );
		$body = (string) wp_json_encode(
			array(
				'instance_id' => $this->options->get_instance_id(),
				'site_url'    => home_url( '/' ),
				'alert'       => $alert,
			)
		);
		$sig  = hash_hmac( 'sha256', $body, $token );

		wp_remote_post(
			$url,
			TransportSecurityPolicy::secure_remote_args(
				array(
					'timeout' => 8,
					'headers' => array(
						'Authorization'            => 'Bearer ' . $token,
						'Content-Type'             => 'application/json',
						'X-UplinkPortal-Signature' => 'sha256=' . $sig,
					),
					'body'    => $body,
				)
			)
		);
	}
}
