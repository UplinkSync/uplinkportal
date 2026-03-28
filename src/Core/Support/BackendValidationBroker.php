<?php
/**
 * Backend validation broker.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Delegates module connection validation to a secure backend service.
 */
class BackendValidationBroker {
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
	 * Validates a module profile by delegating to secure backend handlers.
	 *
	 * @param string $section Validation section.
	 * @param string $base_url API base URL.
	 * @param string $api_key API key.
	 * @param bool   $allow_query_api_key Whether query-string API key mode is allowed.
	 * @param string $oauth_connection_ref Opaque OAuth/token-vault connection reference.
	 * @param string $auth_type Integration profile auth type.
	 * @return array<string,mixed>
	 */
	public function validate( string $section, string $base_url, string $api_key, bool $allow_query_api_key, string $oauth_connection_ref = '', string $auth_type = 'api_key' ): array {
		$payload = array(
			'section'                 => sanitize_key( $section ),
			'base_url'                => untrailingslashit( esc_url_raw( $base_url ) ),
			'api_key'                 => sanitize_text_field( $api_key ),
			'allow_query_api_key'     => $allow_query_api_key,
			'oauth_connection_ref'    => sanitize_text_field( $oauth_connection_ref ),
			'auth_type'               => sanitize_key( $auth_type ),
			'backend_mode'            => $this->options->get_backend_mode(),
			'backend_runtime_mode'    => $this->options->get_backend_runtime_mode(),
			'backend_validation_path' => $this->options->get_backend_validation_path(),
			'backend_health_path'     => $this->options->get_backend_health_path(),
			'policy_bundle'           => $this->options->get_policy_bundle(),
			'secret_manager'          => $this->options->get_secret_manager_config(),
			'governance'              => $this->options->get_governance_profile(),
		);

		/**
		 * Allows secure backend add-ons to return module validation results.
		 *
		 * Return an array with:
		 * - status: success|warning|error
		 * - compatible: bool
		 * - score: int 0..100
		 * - evidence: array<int,array<string,mixed>>
		 * - guidance: array<int,string>
		 *
		 * Payload includes optional external secret-manager metadata under
		 * `secret_manager` (for example Vault routing fields + opaque reference).
		 *
		 * @param array<string,mixed>|null $result  Validation result.
		 * @param array<string,mixed>      $payload Sanitized payload.
		 */
		$result = apply_filters( 'uplinkportal_backend_validation_result', null, $payload );

		if ( is_array( $result ) ) {
			return $this->normalize_result( $result );
		}

		return array(
			'status'     => 'warning',
			'compatible' => false,
			'score'      => 0,
			'evidence'   => array(
				array(
					'type'        => 'backend_validation_unavailable',
					'message'     => __( 'No secure backend validation service is connected.', 'uplinkportal' ),
					'http_status' => 0,
				),
			),
			'guidance'   => array(
				__( 'Connect a managed addon or your own secure backend service to run connection and compatibility tests.', 'uplinkportal' ),
				__( 'Keep final module validation logic in that backend service.', 'uplinkportal' ),
			),
		);
	}

	/**
	 * Normalizes result payload shape.
	 *
	 * @param array<string,mixed> $result Raw result.
	 * @return array<string,mixed>
	 */
	private function normalize_result( array $result ): array {
		$status = isset( $result['status'] ) ? sanitize_key( (string) $result['status'] ) : 'warning';
		if ( ! in_array( $status, array( 'success', 'warning', 'error' ), true ) ) {
			$status = 'warning';
		}

		$score = isset( $result['score'] ) ? absint( $result['score'] ) : 0;
		if ( $score > 100 ) {
			$score = 100;
		}

		$guidance = array();
		if ( isset( $result['guidance'] ) && is_array( $result['guidance'] ) ) {
			foreach ( $result['guidance'] as $line ) {
				$line = sanitize_text_field( (string) $line );
				if ( '' !== $line ) {
					$guidance[] = $line;
				}
			}
		}

		$evidence = array();
		if ( isset( $result['evidence'] ) && is_array( $result['evidence'] ) ) {
			foreach ( $result['evidence'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$evidence[] = array(
					'type'        => isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : 'probe',
					'message'     => isset( $item['message'] ) ? sanitize_text_field( (string) $item['message'] ) : '',
					'endpoint'    => isset( $item['endpoint'] ) ? esc_url_raw( (string) $item['endpoint'] ) : '',
					'http_status' => isset( $item['http_status'] ) ? absint( $item['http_status'] ) : 0,
					'auth'        => isset( $item['auth'] ) ? sanitize_text_field( (string) $item['auth'] ) : '',
				);
			}
		}

		return array(
			'status'     => $status,
			'compatible' => ! empty( $result['compatible'] ),
			'score'      => $score,
			'evidence'   => $evidence,
			'guidance'   => $guidance,
		);
	}
}
