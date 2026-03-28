<?php
/**
 * Integration profile and policy lint checks.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Validates integration profile posture for admin guidance.
 */
class IntegrationPolicyLinter {
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
	 * Returns lint findings.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function lint(): array {
		$findings            = array();
		$integration         = $this->options->get_integration_profiles();
		$validation_profiles = $this->options->get_validation_profiles();
		$policy_matrix       = $this->options->get_model_policy_matrix();
		$legal_basis_map     = $this->options->get_model_legal_basis_map();
		$minimization        = $this->options->get_data_minimization_profile();
		$tenant_boundary     = $this->options->get_tenant_boundary_profile();
		$secret_manager      = $this->options->get_secret_manager_config();
		$policy_bundle       = $this->options->get_policy_bundle();
		$monitoring          = $this->options->get_monitoring_profile();
		$data_protection     = $this->options->get_data_protection_profile();
		$recipe_profiles     = $this->options->get_automation_recipe_profiles();
		$evidence_trail      = $this->options->get_evidence_trail_enabled();
		$backend_base_url    = $this->options->get_backend_base_url();
		$worker_base_url     = $this->options->get_licensing_worker_base_url();
		$has_backend_token   = $this->options->has_backend_service_token();
		$has_vault_ref       = $this->options->has_vault_connection_reference();
		$is_vault_enabled    = 'vault' === sanitize_key( (string) ( $secret_manager['provider'] ?? 'none' ) );
		$strict_nonce        = $this->options->get_strict_rest_nonce();
		$show_audit          = $this->options->get_show_security_audit_log();
		$has_oauth_profiles  = false;
		$has_api_key_only    = false;

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$profile = isset( $integration[ $model_id ] ) && is_array( $integration[ $model_id ] )
				? $integration[ $model_id ]
				: array();

			$model_label = $this->get_model_label( $model_id );
			$provider    = isset( $profile['provider'] ) ? sanitize_key( (string) $profile['provider'] ) : '';
			$auth_type   = isset( $profile['auth_type'] ) ? sanitize_key( (string) $profile['auth_type'] ) : '';
			if ( false !== strpos( $auth_type, 'oauth' ) ) {
				$auth_type = 'oauth';
			} elseif ( 'hybrid' !== $auth_type ) {
				$auth_type = 'api_key';
			}
			$alias       = isset( $profile['endpoint_alias'] ) ? sanitize_text_field( (string) $profile['endpoint_alias'] ) : '';
			$oauth_ref   = isset( $profile['oauth_connection_ref'] ) ? sanitize_text_field( (string) $profile['oauth_connection_ref'] ) : '';
			$lifecycle   = isset( $profile['lifecycle_state'] ) ? sanitize_key( (string) $profile['lifecycle_state'] ) : 'draft';
			$scopes      = isset( $profile['scopes'] ) && is_array( $profile['scopes'] ) ? $profile['scopes'] : array();
			$policy_row  = isset( $policy_matrix[ $model_id ] ) && is_array( $policy_matrix[ $model_id ] ) ? $policy_matrix[ $model_id ] : array();
			$legal_basis = isset( $legal_basis_map[ $model_id ] ) ? sanitize_key( (string) $legal_basis_map[ $model_id ] ) : '';

			if ( '' === $provider ) {
				$findings[] = $this->build_finding(
					'warning',
					$model_label,
					__( 'Provider slug is empty.', 'uplinkportal' ),
					__( 'Select a provider template or enter a custom provider slug.', 'uplinkportal' )
				);
			}

			if ( '' === $alias && in_array( $model_id, array( IntegrationModelRegistry::MODEL_FINANCE, IntegrationModelRegistry::MODEL_IT_SUPPORT, IntegrationModelRegistry::MODEL_PROJECTS ), true ) ) {
				$findings[] = $this->build_finding(
					'warning',
					$model_label,
					__( 'Endpoint alias is empty.', 'uplinkportal' ),
					__( 'Set a stable endpoint alias for backend routing and mapping.', 'uplinkportal' )
				);
			}

			if ( 'oauth' === $auth_type && in_array( $model_id, array( IntegrationModelRegistry::MODEL_FINANCE, IntegrationModelRegistry::MODEL_IT_SUPPORT, IntegrationModelRegistry::MODEL_PROJECTS ), true ) && '' === $oauth_ref ) {
				$findings[] = $this->build_finding(
					'error',
					$model_label,
					__( 'OAuth auth type requires an OAuth connection reference.', 'uplinkportal' ),
					__( 'Save an opaque connection reference issued by your secure backend/add-on.', 'uplinkportal' )
				);
			}

			if ( 0 === strpos( $auth_type, 'oauth' ) && empty( $scopes ) ) {
				$findings[] = $this->build_finding(
					'warning',
					$model_label,
					__( 'OAuth profile has no scopes.', 'uplinkportal' ),
					__( 'Add least-privilege scopes required by this integration.', 'uplinkportal' )
				);
			}

			if ( ! in_array( $lifecycle, array( 'draft', 'reviewed', 'active' ), true ) ) {
				$findings[] = $this->build_finding(
					'warning',
					$model_label,
					__( 'Profile lifecycle state is invalid.', 'uplinkportal' ),
					__( 'Use draft, reviewed, or active state for profile promotion workflow.', 'uplinkportal' )
				);
			} elseif ( 'draft' === $lifecycle ) {
				$findings[] = $this->build_finding(
					'info',
					$model_label,
					__( 'Profile is still in draft state.', 'uplinkportal' ),
					__( 'Promote to reviewed after successful tests, then active for production use.', 'uplinkportal' )
				);
			}

			foreach ( array( 'connect', 'read', 'sync', 'export', 'delete' ) as $action_key ) {
				$capability = isset( $policy_row[ $action_key ] ) ? sanitize_key( (string) $policy_row[ $action_key ] ) : '';
				if ( '' === $capability ) {
					$findings[] = $this->build_finding(
						'error',
						$model_label,
						sprintf(
							/* translators: %s action key. */
							__( 'RBAC policy capability is missing for %s action.', 'uplinkportal' ),
							$action_key
						),
						__( 'Set a WordPress capability key in the Model RBAC Policies matrix.', 'uplinkportal' )
					);
				}
			}

			$validation_section = IntegrationModelRegistry::validation_section_for_model( $model_id );
			if ( '' === $validation_section ) {
				if ( in_array( $auth_type, array( 'oauth', 'hybrid' ), true ) ) {
					$has_oauth_profiles = true;
				}
				continue;
			}

			$validation_profile = isset( $validation_profiles[ $validation_section ] ) && is_array( $validation_profiles[ $validation_section ] )
				? $validation_profiles[ $validation_section ]
				: array();
			$base_url           = isset( $validation_profile['base_url'] ) ? untrailingslashit( esc_url_raw( (string) $validation_profile['base_url'] ) ) : '';
			$api_key            = isset( $validation_profile['api_key'] ) ? sanitize_text_field( (string) $validation_profile['api_key'] ) : '';
			$allow_query_key    = ! empty( $validation_profile['allow_query_api_key'] );

			if ( '' === $base_url ) {
				$findings[] = $this->build_finding(
					'error',
					$model_label,
					__( 'Validation base URL is missing.', 'uplinkportal' ),
					__( 'Set the module API base URL before running compatibility tests.', 'uplinkportal' )
				);
			} else {
				$transport_check = TransportSecurityPolicy::validate_url( $base_url, 'validation_' . $validation_section );
				if ( is_wp_error( $transport_check ) ) {
					$findings[] = $this->build_finding(
						'error',
						$model_label,
						__( 'Validation base URL does not meet transport security policy.', 'uplinkportal' ),
						__( 'Use HTTPS endpoints (or local HTTPS dev hosts) for connector validation routes.', 'uplinkportal' )
					);
				}
			}

			if ( 'api_key' === $auth_type && '' === $api_key ) {
				$findings[] = $this->build_finding(
					'error',
					$model_label,
					__( 'API key auth type is selected but no API key is stored.', 'uplinkportal' ),
					__( 'Save the API key or switch to OAuth/Hybrid auth type.', 'uplinkportal' )
				);
			}

			if ( 'hybrid' === $auth_type && '' === $api_key && '' === $oauth_ref ) {
				$findings[] = $this->build_finding(
					'error',
					$model_label,
					__( 'Hybrid auth type has no usable credential path.', 'uplinkportal' ),
					__( 'Store either an API key fallback or an OAuth connection reference.', 'uplinkportal' )
				);
			}

			if ( 'oauth' === $auth_type || 'hybrid' === $auth_type ) {
				$has_oauth_profiles = true;
			}
			if ( 'api_key' === $auth_type && in_array( $model_id, array( IntegrationModelRegistry::MODEL_FINANCE, IntegrationModelRegistry::MODEL_IT_SUPPORT, IntegrationModelRegistry::MODEL_PROJECTS ), true ) ) {
				$has_api_key_only = true;
			}

			if ( $allow_query_key ) {
				$findings[] = $this->build_finding(
					'warning',
					$model_label,
					__( 'Query-string API key mode is enabled.', 'uplinkportal' ),
					__( 'Disable query-string API key mode unless a provider strictly requires it.', 'uplinkportal' )
				);
			}

			if ( '' === $legal_basis ) {
				$findings[] = $this->build_finding(
					'error',
					$model_label,
					__( 'Legal basis is missing for this model.', 'uplinkportal' ),
					__( 'Select a GDPR legal basis in Model Legal Basis settings.', 'uplinkportal' )
				);
			}

			$model_allowlist   = isset( $minimization['model_allowlist'][ $model_id ] ) && is_array( $minimization['model_allowlist'][ $model_id ] )
				? $minimization['model_allowlist'][ $model_id ]
				: array();
			$provider_slug     = isset( $profile['provider'] ) ? sanitize_key( (string) $profile['provider'] ) : '';
			$provider_override = isset( $minimization['provider_overrides'][ $model_id ][ $provider_slug ] ) && is_array( $minimization['provider_overrides'][ $model_id ][ $provider_slug ] )
				? $minimization['provider_overrides'][ $model_id ][ $provider_slug ]
				: array();

			if ( empty( $model_allowlist ) ) {
				$findings[] = $this->build_finding(
					'warning',
					$model_label,
					__( 'Default data allowlist is empty.', 'uplinkportal' ),
					__( 'Populate model allowlist fields to enforce minimization defaults.', 'uplinkportal' )
				);
			}

			if ( '' !== $provider_slug && 'custom' !== $provider_slug && empty( $provider_override ) ) {
				$findings[] = $this->build_finding(
					'info',
					$model_label,
					__( 'No provider-specific allowlist override is configured.', 'uplinkportal' ),
					__( 'Recommended: define provider override fields for stricter minimization per connector.', 'uplinkportal' )
				);
			}
		}

		if ( '' === $backend_base_url ) {
			$findings[] = $this->build_finding(
				'error',
				__( 'Modules', 'uplinkportal' ),
				__( 'Backend base URL is not configured.', 'uplinkportal' ),
				__( 'Set the secure backend URL used for delegated validation and document key exchange.', 'uplinkportal' )
			);
		} else {
			$backend_transport = TransportSecurityPolicy::validate_url( $backend_base_url, 'backend_base_url' );
			if ( is_wp_error( $backend_transport ) ) {
				$findings[] = $this->build_finding(
					'error',
					__( 'Modules', 'uplinkportal' ),
					__( 'Backend base URL does not meet transport security policy.', 'uplinkportal' ),
					__( 'Use HTTPS for backend service URLs in production environments.', 'uplinkportal' )
				);
			}
		}

		if ( '' !== $worker_base_url ) {
			$worker_transport = TransportSecurityPolicy::validate_url( $worker_base_url, 'licensing_worker' );
			if ( is_wp_error( $worker_transport ) ) {
				$findings[] = $this->build_finding(
					'error',
					__( 'Licensing', 'uplinkportal' ),
					__( 'Licensing worker URL does not meet transport security policy.', 'uplinkportal' ),
					__( 'Use HTTPS for licensing worker endpoints.', 'uplinkportal' )
				);
			}
		}

		if ( $is_vault_enabled ) {
			$vault_addr  = isset( $secret_manager['vault_addr'] ) ? untrailingslashit( esc_url_raw( (string) $secret_manager['vault_addr'] ) ) : '';
			$kv_mount    = isset( $secret_manager['vault_kv_mount'] ) ? sanitize_key( (string) $secret_manager['vault_kv_mount'] ) : '';
			$path_prefix = isset( $secret_manager['vault_path_prefix'] ) ? sanitize_text_field( (string) $secret_manager['vault_path_prefix'] ) : '';
			$auth_mount  = isset( $secret_manager['vault_auth_mount'] ) ? sanitize_key( (string) $secret_manager['vault_auth_mount'] ) : '';

			if ( '' === $vault_addr ) {
				$findings[] = $this->build_finding(
					'error',
					__( 'Secret Manager', 'uplinkportal' ),
					__( 'Vault provider is enabled but Vault address is missing.', 'uplinkportal' ),
					__( 'Set the Vault base URL (for example, https://vault.example.com).', 'uplinkportal' )
				);
			}
			if ( '' === $kv_mount || '' === $path_prefix || '' === $auth_mount ) {
				$findings[] = $this->build_finding(
					'warning',
					__( 'Secret Manager', 'uplinkportal' ),
					__( 'Vault routing metadata is incomplete.', 'uplinkportal' ),
					__( 'Set KV mount, path prefix, and auth mount to keep backend secret lookups deterministic.', 'uplinkportal' )
				);
			}
			if ( ! $has_vault_ref ) {
				$findings[] = $this->build_finding(
					'error',
					__( 'Secret Manager', 'uplinkportal' ),
					__( 'Vault provider is enabled but no Vault connection reference is stored.', 'uplinkportal' ),
					__( 'Save the opaque Vault reference issued by your secure backend/add-on.', 'uplinkportal' )
				);
			}
		}

		if ( $has_oauth_profiles && ! $is_vault_enabled ) {
			$findings[] = $this->build_finding(
				'info',
				__( 'Secret Manager', 'uplinkportal' ),
				__( 'OAuth-enabled module profiles are configured without Vault provider enabled.', 'uplinkportal' ),
				__( 'Recommended: enable Vault for external secret lifecycle governance.', 'uplinkportal' )
			);
		}

		if ( ! $has_backend_token && ! $has_vault_ref ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Documents', 'uplinkportal' ),
				__( 'No backend service token or Vault reference is available for document key workflows.', 'uplinkportal' ),
				__( 'Provide at least one delegated backend auth path for client document encryption key retrieval.', 'uplinkportal' )
			);
		}

		if ( CompliancePolicyProfiles::BUNDLE_GDPR_BASELINE === $policy_bundle && $has_api_key_only ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Governance', 'uplinkportal' ),
				__( 'GDPR baseline is selected while one or more module profiles use API key auth.', 'uplinkportal' ),
				__( 'Recommended: use delegated OAuth + external secret governance where providers support it.', 'uplinkportal' )
			);
		}

		if ( CompliancePolicyProfiles::BUNDLE_HIPAA_BASELINE === $policy_bundle && ( ! $strict_nonce || ! $show_audit ) ) {
			$findings[] = $this->build_finding(
				'error',
				__( 'Governance', 'uplinkportal' ),
				__( 'HIPAA baseline requires strict nonce checks and visible audit logs.', 'uplinkportal' ),
				__( 'Enable strict REST nonce protection and security audit log visibility.', 'uplinkportal' )
			);
		}
		if ( CompliancePolicyProfiles::BUNDLE_HIPAA_BASELINE === $policy_bundle && ! $evidence_trail ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Governance', 'uplinkportal' ),
				__( 'HIPAA baseline is selected while tamper-evident evidence trail is disabled.', 'uplinkportal' ),
				__( 'Enable evidence trail in Security settings for stronger forensic controls.', 'uplinkportal' )
			);
		}

		$monitoring_mode = isset( $monitoring['mode'] ) ? sanitize_key( (string) $monitoring['mode'] ) : 'none';
		if ( 'none' === $monitoring_mode ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Monitoring', 'uplinkportal' ),
				__( 'External monitoring is disabled.', 'uplinkportal' ),
				__( 'Configure Generic Webhook or Hosted monitoring to improve detection and compliance posture.', 'uplinkportal' )
			);
		}
		if ( 'generic_webhook' === $monitoring_mode ) {
			$webhook_url = isset( $monitoring['webhook_url'] ) ? esc_url_raw( (string) $monitoring['webhook_url'] ) : '';
			if ( '' === $webhook_url ) {
				$findings[] = $this->build_finding(
					'error',
					__( 'Monitoring', 'uplinkportal' ),
					__( 'Generic webhook monitoring is selected but webhook URL is empty.', 'uplinkportal' ),
					__( 'Set a valid SIEM webhook URL or switch monitoring mode.', 'uplinkportal' )
				);
			}
		}

		$at_rest_status = isset( $data_protection['at_rest_status'] ) ? sanitize_key( (string) $data_protection['at_rest_status'] ) : 'disabled';
		$db_mode        = isset( $data_protection['database_encryption'] ) ? sanitize_key( (string) $data_protection['database_encryption'] ) : 'none';
		$doc_mode       = isset( $data_protection['document_encryption'] ) ? sanitize_key( (string) $data_protection['document_encryption'] ) : 'plugin_local_encrypted';
		$kms_provider   = isset( $data_protection['kms_provider'] ) ? sanitize_key( (string) $data_protection['kms_provider'] ) : 'none';
		$rotation_days  = isset( $data_protection['key_rotation_days'] ) ? absint( $data_protection['key_rotation_days'] ) : 90;
		$split_roles    = ! empty( $data_protection['separation_of_duties'] );
		$runbook_url    = isset( $data_protection['emergency_revoke_runbook_url'] ) ? esc_url_raw( (string) $data_protection['emergency_revoke_runbook_url'] ) : '';

		if ( 'disabled' === $at_rest_status ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Data Protection', 'uplinkportal' ),
				__( 'At-rest encryption controls are disabled.', 'uplinkportal' ),
				__( 'Set At-Rest Control Status to Planned or Enforced and configure database/document encryption modes.', 'uplinkportal' )
			);
		}
		if ( 'none' === $db_mode ) {
			$findings[] = $this->build_finding(
				'error',
				__( 'Data Protection', 'uplinkportal' ),
				__( 'Database encryption mode is not configured.', 'uplinkportal' ),
				__( 'Use provider TDE, KMS-managed keys, or self-hosted encrypted volumes.', 'uplinkportal' )
			);
		}
		if ( 'none' === $kms_provider && 'enforced' === $at_rest_status ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Data Protection', 'uplinkportal' ),
				__( 'At-rest enforcement is enabled without a key-management provider.', 'uplinkportal' ),
				__( 'Recommended: configure Vault Transit or another managed KMS for key lifecycle governance.', 'uplinkportal' )
			);
		}
		if ( $rotation_days > 365 ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Key Management', 'uplinkportal' ),
				__( 'Key rotation interval is longer than 365 days.', 'uplinkportal' ),
				__( 'Use a shorter key-rotation interval (for example, 90-180 days) to reduce key exposure windows.', 'uplinkportal' )
			);
		}
		if ( '' === $runbook_url ) {
			$findings[] = $this->build_finding(
				'info',
				__( 'Key Management', 'uplinkportal' ),
				__( 'Emergency key-revoke runbook URL is not configured.', 'uplinkportal' ),
				__( 'Add an internal runbook URL for compromise response and legal/audit readiness.', 'uplinkportal' )
			);
		}
		if ( 'backend_managed' === $doc_mode && ! $has_backend_token && ! $has_vault_ref ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Data Protection', 'uplinkportal' ),
				__( 'Backend-managed document encryption is selected but no delegated auth path is configured.', 'uplinkportal' ),
				__( 'Provide a backend service token or Vault connection reference for delegated storage key workflows.', 'uplinkportal' )
			);
		}
		if ( CompliancePolicyProfiles::BUNDLE_GDPR_BASELINE === $policy_bundle && 'disabled' === $at_rest_status ) {
			$findings[] = $this->build_finding(
				'error',
				__( 'Governance', 'uplinkportal' ),
				__( 'GDPR baseline requires at-rest controls to be at least planned.', 'uplinkportal' ),
				__( 'Set at-rest status to Planned/Enforced and document your encryption implementation path.', 'uplinkportal' )
			);
		}
		if ( CompliancePolicyProfiles::BUNDLE_HIPAA_BASELINE === $policy_bundle && 'enforced' !== $at_rest_status ) {
			$findings[] = $this->build_finding(
				'error',
				__( 'Governance', 'uplinkportal' ),
				__( 'HIPAA baseline requires enforced at-rest controls.', 'uplinkportal' ),
				__( 'Set At-Rest Control Status to Enforced with documented database and document encryption modes.', 'uplinkportal' )
			);
		}
		if ( CompliancePolicyProfiles::BUNDLE_HIPAA_BASELINE === $policy_bundle && ! $split_roles ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Governance', 'uplinkportal' ),
				__( 'HIPAA baseline is selected while separation of duties for key operations is disabled.', 'uplinkportal' ),
				__( 'Enable separation-of-duties control for key-management operations.', 'uplinkportal' )
			);
		}

		$live_recipe_count = 0;
		foreach ( $recipe_profiles as $recipe_profile ) {
			if ( ! is_array( $recipe_profile ) ) {
				continue;
			}

			$recipe_enabled = ! empty( $recipe_profile['enabled'] );
			$recipe_mode    = isset( $recipe_profile['mode'] ) ? sanitize_key( (string) $recipe_profile['mode'] ) : 'dry_run';
			if ( $recipe_enabled && 'live' === $recipe_mode ) {
				++$live_recipe_count;
			}
		}

		if ( $live_recipe_count > 0 && 'none' === $monitoring_mode ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Automation Recipes', 'uplinkportal' ),
				__( 'Live automation recipes are enabled while external monitoring is disabled.', 'uplinkportal' ),
				__( 'Configure Generic Webhook or Hosted monitoring before promoting recipes to live execution.', 'uplinkportal' )
			);
		}
		if ( $live_recipe_count > 0 && 'disabled' === $at_rest_status ) {
			$findings[] = $this->build_finding(
				'warning',
				__( 'Automation Recipes', 'uplinkportal' ),
				__( 'Live automation recipes are enabled while at-rest controls are disabled.', 'uplinkportal' ),
				__( 'Set at-rest controls to Planned or Enforced before enabling live recipe execution.', 'uplinkportal' )
			);
		}

		if ( is_multisite() ) {
			$tenant_mode = isset( $tenant_boundary['mode'] ) ? sanitize_key( (string) $tenant_boundary['mode'] ) : '';
			if ( 'multisite_network_shared' === $tenant_mode && empty( $tenant_boundary['require_super_admin'] ) ) {
				$findings[] = $this->build_finding(
					'warning',
					__( 'Tenant Boundaries', 'uplinkportal' ),
					__( 'Network-shared tenant mode is active without super-admin restriction.', 'uplinkportal' ),
					__( 'Enable super-admin requirement to reduce cross-tenant access risk.', 'uplinkportal' )
				);
			}
			if ( empty( $tenant_boundary['enforce_site_scope'] ) ) {
				$findings[] = $this->build_finding(
					'warning',
					__( 'Tenant Boundaries', 'uplinkportal' ),
					__( 'Site-scope enforcement is disabled in multisite.', 'uplinkportal' ),
					__( 'Enable site-scope enforcement for stronger tenant isolation.', 'uplinkportal' )
				);
			}
		}

		usort( $findings, array( $this, 'sort_findings' ) );

		/**
		 * Filters integration policy lint findings.
		 *
		 * @param array<int,array<string,string>> $findings Lint findings.
		 * @param array<string,array<string,mixed>> $integration Integration profile map.
		 */
		return apply_filters( 'uplinkportal_integration_policy_findings', $findings, $integration );
	}

	/**
	 * Builds a normalized lint finding.
	 *
	 * @param string $severity Severity key.
	 * @param string $scope Scope label.
	 * @param string $message Message.
	 * @param string $recommendation Recommendation.
	 * @return array<string,string>
	 */
	private function build_finding( string $severity, string $scope, string $message, string $recommendation ): array {
		$severity = sanitize_key( $severity );
		if ( ! in_array( $severity, array( 'error', 'warning', 'info' ), true ) ) {
			$severity = 'warning';
		}

		return array(
			'severity'       => $severity,
			'scope'          => sanitize_text_field( $scope ),
			'message'        => sanitize_text_field( $message ),
			'recommendation' => sanitize_text_field( $recommendation ),
		);
	}

	/**
	 * Sort callback for findings severity.
	 *
	 * @param array<string,string> $left Left finding.
	 * @param array<string,string> $right Right finding.
	 * @return int
	 */
	private function sort_findings( array $left, array $right ): int {
		$order = array(
			'error'   => 0,
			'warning' => 1,
			'info'    => 2,
		);

		$left_severity  = isset( $left['severity'] ) ? sanitize_key( (string) $left['severity'] ) : 'warning';
		$right_severity = isset( $right['severity'] ) ? sanitize_key( (string) $right['severity'] ) : 'warning';

		$left_order  = isset( $order[ $left_severity ] ) ? $order[ $left_severity ] : 9;
		$right_order = isset( $order[ $right_severity ] ) ? $order[ $right_severity ] : 9;

		return $left_order <=> $right_order;
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
