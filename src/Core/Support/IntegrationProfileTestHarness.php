<?php
/**
 * Integration profile test harness.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides non-destructive readiness tests for integration profiles.
 */
class IntegrationProfileTestHarness {
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
	 * Runs harness checks for all models.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function run(): array {
		$rows                = array();
		$integration         = $this->options->get_integration_profiles();
		$validation_profiles = $this->options->get_validation_profiles();
		$policy_matrix       = $this->options->get_model_policy_matrix();
		$legal_basis_map     = $this->options->get_model_legal_basis_map();
		$minimization        = $this->options->get_data_minimization_profile();
		$backend_base_url    = $this->options->get_backend_base_url();
		$has_backend_token   = $this->options->has_backend_service_token();
		$has_vault_ref       = $this->options->has_vault_connection_reference();
		$secret_manager      = $this->options->get_secret_manager_config();
		$data_protection     = $this->options->get_data_protection_profile();
		$vault_enabled       = 'vault' === sanitize_key( (string) ( $secret_manager['provider'] ?? 'none' ) );
		$backend_validator   = has_filter( 'uplinkportal_backend_validation_result' );

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$profile = isset( $integration[ $model_id ] ) && is_array( $integration[ $model_id ] )
				? $integration[ $model_id ]
				: array();
			$section = IntegrationModelRegistry::validation_section_for_model( $model_id );
			$label   = $this->get_model_label( $model_id );

			$auth_test       = $this->run_auth_test( $model_id, $profile, $validation_profiles, $has_backend_token, $has_vault_ref );
			$schema_test     = $this->run_schema_test( $model_id, $profile, $section, $legal_basis_map, $minimization );
			$permission_test = $this->run_permission_test( $model_id, $policy_matrix );
			$dry_run_test    = $this->run_dry_run_test( $model_id, $section, $validation_profiles, $backend_base_url, $backend_validator, $has_backend_token, $has_vault_ref, $vault_enabled, $data_protection );

			$rows[] = array(
				'model'           => $label,
				'auth_test'       => $auth_test,
				'schema_test'     => $schema_test,
				'permission_test' => $permission_test,
				'dry_run_test'    => $dry_run_test,
			);
		}

		/**
		 * Filters integration profile harness output.
		 *
		 * @param array<int,array<string,mixed>> $rows Harness rows.
		 */
		return apply_filters( 'uplinkportal_integration_profile_harness_rows', $rows );
	}

	/**
	 * Runs auth posture test for one model.
	 *
	 * @param string                            $model_id Model id.
	 * @param array<string,mixed>               $profile Profile payload.
	 * @param array<string,array<string,mixed>> $validation_profiles Validation profiles.
	 * @param bool                              $has_backend_token Backend token flag.
	 * @param bool                              $has_vault_ref Vault reference flag.
	 * @return array<string,string>
	 */
	private function run_auth_test( string $model_id, array $profile, array $validation_profiles, bool $has_backend_token, bool $has_vault_ref ): array {
		$oauth_ref = isset( $profile['oauth_connection_ref'] ) ? sanitize_text_field( (string) $profile['oauth_connection_ref'] ) : '';
		$auth_type = isset( $profile['auth_type'] ) ? sanitize_key( (string) $profile['auth_type'] ) : 'api_key';
		if ( false !== strpos( $auth_type, 'oauth' ) ) {
			$auth_type = 'oauth';
		} elseif ( 'hybrid' !== $auth_type ) {
			$auth_type = 'api_key';
		}
		$section = IntegrationModelRegistry::validation_section_for_model( $model_id );
		$api_key = '';

		if ( '' !== $section && isset( $validation_profiles[ $section ] ) && is_array( $validation_profiles[ $section ] ) ) {
			$api_key = isset( $validation_profiles[ $section ]['api_key'] ) ? sanitize_text_field( (string) $validation_profiles[ $section ]['api_key'] ) : '';
		}

		if ( IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id ) {
			if ( $has_backend_token || $has_vault_ref || '' !== $oauth_ref ) {
				return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Delegated backend auth path is available for documents.', 'uplinkportal' ) );
			}

			return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'Missing backend service token and Vault reference for documents.', 'uplinkportal' ) );
		}

		if ( IntegrationModelRegistry::MODEL_LICENSING === $model_id ) {
			$worker_url = $this->options->get_licensing_worker_base_url();
			$license    = $this->options->get_license_key();
			if ( '' !== $worker_url && '' !== $license ) {
				return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Licensing worker URL and license key are configured.', 'uplinkportal' ) );
			}

			return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'Missing licensing worker URL or license key.', 'uplinkportal' ) );
		}

		if ( 'oauth' === $auth_type ) {
			if ( '' !== $oauth_ref ) {
				return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Delegated OAuth reference is available.', 'uplinkportal' ) );
			}

			return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'OAuth auth type requires an OAuth connection reference.', 'uplinkportal' ) );
		}

		if ( 'api_key' === $auth_type ) {
			if ( '' !== $api_key ) {
				return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'API key credential is available.', 'uplinkportal' ) );
			}

			return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'API key auth type is selected but no API key is stored.', 'uplinkportal' ) );
		}

		if ( '' !== $oauth_ref || '' !== $api_key ) {
			return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Hybrid auth type has at least one usable credential path.', 'uplinkportal' ) );
		}

		return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'Hybrid auth type is selected but no OAuth reference or API key is available.', 'uplinkportal' ) );
	}

	/**
	 * Runs schema test for one model.
	 *
	 * @param string               $model_id Model id.
	 * @param array<string,mixed>  $profile Profile payload.
	 * @param string               $section Validation section.
	 * @param array<string,string> $legal_basis_map Legal basis map.
	 * @param array<string,mixed>  $minimization Data minimization profile.
	 * @return array<string,string>
	 */
	private function run_schema_test( string $model_id, array $profile, string $section, array $legal_basis_map, array $minimization ): array {
		$provider       = isset( $profile['provider'] ) ? sanitize_key( (string) $profile['provider'] ) : '';
		$auth_type      = isset( $profile['auth_type'] ) ? sanitize_key( (string) $profile['auth_type'] ) : '';
		$endpoint_alias = isset( $profile['endpoint_alias'] ) ? sanitize_text_field( (string) $profile['endpoint_alias'] ) : '';
		$lifecycle      = isset( $profile['lifecycle_state'] ) ? sanitize_key( (string) $profile['lifecycle_state'] ) : 'draft';

		if ( '' === $provider || '' === $auth_type ) {
			return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'Provider and auth type are required.', 'uplinkportal' ) );
		}

		if ( '' !== $section && '' === $endpoint_alias ) {
			return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'Endpoint alias is recommended for backend routing consistency.', 'uplinkportal' ) );
		}

		if ( ! in_array( $lifecycle, array( 'draft', 'reviewed', 'active' ), true ) ) {
			return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'Lifecycle state is invalid and should be reset.', 'uplinkportal' ) );
		}

		$legal_basis = isset( $legal_basis_map[ $model_id ] ) ? sanitize_key( (string) $legal_basis_map[ $model_id ] ) : '';
		if ( '' === $legal_basis ) {
			return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'Legal basis is missing for this model.', 'uplinkportal' ) );
		}

		$model_allowlist = isset( $minimization['model_allowlist'][ $model_id ] ) && is_array( $minimization['model_allowlist'][ $model_id ] )
			? $minimization['model_allowlist'][ $model_id ]
			: array();
		if ( empty( $model_allowlist ) ) {
			return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'Model field allowlist is empty.', 'uplinkportal' ) );
		}

		return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Required schema fields are present.', 'uplinkportal' ) );
	}

	/**
	 * Runs permission test for one model using policy matrix.
	 *
	 * @param string                             $model_id Model id.
	 * @param array<string,array<string,string>> $policy_matrix Policy matrix.
	 * @return array<string,string>
	 */
	private function run_permission_test( string $model_id, array $policy_matrix ): array {
		$policy = isset( $policy_matrix[ $model_id ] ) && is_array( $policy_matrix[ $model_id ] )
			? $policy_matrix[ $model_id ]
			: array();
		$cap    = isset( $policy['connect'] ) ? sanitize_key( (string) $policy['connect'] ) : '';

		if ( '' === $cap ) {
			return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'No connect capability is defined for this model.', 'uplinkportal' ) );
		}

		if ( current_user_can( $cap ) ) {
			return $this->build_result(
				'enabled',
				__( 'Pass', 'uplinkportal' ),
				sprintf(
					/* translators: %s capability key. */
					__( 'Current user has capability: %s', 'uplinkportal' ),
					$cap
				)
			);
		}

		return $this->build_result(
			'warning',
			__( 'Needs Review', 'uplinkportal' ),
			sprintf(
				/* translators: %s capability key. */
				__( 'Current user is missing capability: %s', 'uplinkportal' ),
				$cap
			)
		);
	}

	/**
	 * Runs dry-run readiness test for one model.
	 *
	 * @param string                            $model_id Model id.
	 * @param string                            $section Validation section.
	 * @param array<string,array<string,mixed>> $validation_profiles Validation profiles.
	 * @param string                            $backend_base_url Backend URL.
	 * @param bool|int                          $backend_validator Backend validator hook indicator.
	 * @param bool                              $has_backend_token Backend token flag.
	 * @param bool                              $has_vault_ref Vault reference flag.
	 * @param bool                              $vault_enabled Vault provider flag.
	 * @param array<string,mixed>               $data_protection Data protection profile.
	 * @return array<string,string>
	 */
	private function run_dry_run_test( string $model_id, string $section, array $validation_profiles, string $backend_base_url, $backend_validator, bool $has_backend_token, bool $has_vault_ref, bool $vault_enabled, array $data_protection ): array {
		$at_rest_status = isset( $data_protection['at_rest_status'] ) ? sanitize_key( (string) $data_protection['at_rest_status'] ) : 'disabled';

		if ( 'disabled' === $at_rest_status && IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id ) {
			return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'At-rest controls are disabled; document workflow should not be promoted to production.', 'uplinkportal' ) );
		}

		if ( '' !== $section ) {
			$profile  = isset( $validation_profiles[ $section ] ) && is_array( $validation_profiles[ $section ] ) ? $validation_profiles[ $section ] : array();
			$base_url = isset( $profile['base_url'] ) ? untrailingslashit( esc_url_raw( (string) $profile['base_url'] ) ) : '';

			if ( '' === $base_url ) {
				return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'Validation base URL is missing for dry-run readiness.', 'uplinkportal' ) );
			}

			$transport_check = TransportSecurityPolicy::validate_url( $base_url, 'validation_' . $section );
			if ( is_wp_error( $transport_check ) ) {
				return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'Validation endpoint must use HTTPS under transport policy.', 'uplinkportal' ) );
			}

			if ( empty( $backend_validator ) ) {
				return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'No backend validation hook detected. Dry-run checks are not currently delegated.', 'uplinkportal' ) );
			}

			return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Dry-run readiness confirmed through delegated backend validation hook.', 'uplinkportal' ) );
		}

		if ( IntegrationModelRegistry::MODEL_LICENSING === $model_id ) {
			$worker_url = $this->options->get_licensing_worker_base_url();
			if ( '' !== $worker_url && ! is_wp_error( TransportSecurityPolicy::validate_url( $worker_url, 'licensing_worker' ) ) ) {
				return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Licensing dry-run readiness is available via worker endpoint.', 'uplinkportal' ) );
			}

			return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'Licensing worker URL is missing or not HTTPS; dry-run cannot execute.', 'uplinkportal' ) );
		}

		if ( IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id ) {
			if ( '' === $backend_base_url ) {
				return $this->build_result( 'disabled', __( 'Fail', 'uplinkportal' ), __( 'Backend base URL is missing for document key dry-runs.', 'uplinkportal' ) );
			}
			if ( ! $has_backend_token && ! $has_vault_ref ) {
				return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'No backend token or Vault reference available for document dry-run.', 'uplinkportal' ) );
			}
			if ( $vault_enabled && ! $has_vault_ref ) {
				return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'Vault provider is enabled without a Vault connection reference.', 'uplinkportal' ) );
			}

			return $this->build_result( 'enabled', __( 'Pass', 'uplinkportal' ), __( 'Document dry-run prerequisites are available.', 'uplinkportal' ) );
		}

		return $this->build_result( 'warning', __( 'Needs Review', 'uplinkportal' ), __( 'No dry-run implementation is defined for this model.', 'uplinkportal' ) );
	}

	/**
	 * Builds one harness test result.
	 *
	 * @param string $state Visual state (`enabled|warning|disabled`).
	 * @param string $label Label.
	 * @param string $message Message.
	 * @return array<string,string>
	 */
	private function build_result( string $state, string $label, string $message ): array {
		$state = sanitize_key( $state );
		if ( ! in_array( $state, array( 'enabled', 'warning', 'disabled' ), true ) ) {
			$state = 'warning';
		}

		return array(
			'state'   => $state,
			'label'   => sanitize_text_field( $label ),
			'message' => sanitize_text_field( $message ),
		);
	}

	/**
	 * Returns model label.
	 *
	 * @param string $model_id Model id.
	 * @return string
	 */
	private function get_model_label( string $model_id ): string {
		switch ( sanitize_key( $model_id ) ) {
			case IntegrationModelRegistry::MODEL_FINANCE:
				return __( 'Finance', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_IT_SUPPORT:
				return __( 'IT Support', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_PROJECTS:
				return __( 'Projects', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_LICENSING:
				return __( 'Licensing', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_DOCUMENTS:
				return __( 'Documents', 'uplinkportal' );
		}

		return sanitize_text_field( $model_id );
	}
}
