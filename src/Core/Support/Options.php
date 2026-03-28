<?php
/**
 * Option helper.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use UplinkPortal\Core\Licensing\SecretsStore;
use UplinkPortal\Core\Services\ProviderCatalogService;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes plugin settings.
 */
class Options {
	/**
	 * Option key.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'uplinkportal_settings';

	/**
	 * Schema version.
	 *
	 * @var int
	 */
	private const SCHEMA_VERSION = 2;

	/**
	 * Managed backend mode.
	 *
	 * @var string
	 */
	private const BACKEND_MODE_MANAGED = 'managed_service';

	/**
	 * Self-hosted backend mode.
	 *
	 * @var string
	 */
	private const BACKEND_MODE_SELF_HOSTED = 'self_hosted';

	/**
	 * Backend runtime mode: mock.
	 *
	 * @var string
	 */
	private const BACKEND_RUNTIME_MODE_MOCK = 'mock';

	/**
	 * Backend runtime mode: staging.
	 *
	 * @var string
	 */
	private const BACKEND_RUNTIME_MODE_STAGING = 'staging';

	/**
	 * Backend runtime mode: production.
	 *
	 * @var string
	 */
	private const BACKEND_RUNTIME_MODE_PRODUCTION = 'production';

	/**
	 * Default backend validation path.
	 *
	 * @var string
	 */
	private const BACKEND_VALIDATION_PATH_DEFAULT = '/v1/validate';

	/**
	 * Default backend health path.
	 *
	 * @var string
	 */
	private const BACKEND_HEALTH_PATH_DEFAULT = '/health';

	/**
	 * Env key: backend mode.
	 *
	 * @var string
	 */
	private const ENV_BACKEND_MODE = 'UPLINKPORTAL_BACKEND_MODE';

	/**
	 * Env key: backend base URL.
	 *
	 * @var string
	 */
	private const ENV_BACKEND_BASE_URL = 'UPLINKPORTAL_BACKEND_BASE_URL';

	/**
	 * Env key: backend service token.
	 *
	 * @var string
	 */
	private const ENV_BACKEND_SERVICE_TOKEN = 'UPLINKPORTAL_BACKEND_SERVICE_TOKEN';

	/**
	 * Env key: backend validation path.
	 *
	 * @var string
	 */
	private const ENV_BACKEND_VALIDATION_PATH = 'UPLINKPORTAL_BACKEND_VALIDATION_PATH';

	/**
	 * Env key: backend health path.
	 *
	 * @var string
	 */
	private const ENV_BACKEND_HEALTH_PATH = 'UPLINKPORTAL_BACKEND_HEALTH_PATH';

	/**
	 * Integration profile lifecycle: draft.
	 *
	 * @var string
	 */
	private const INTEGRATION_PROFILE_STATE_DRAFT = 'draft';

	/**
	 * Integration profile lifecycle: reviewed.
	 *
	 * @var string
	 */
	private const INTEGRATION_PROFILE_STATE_REVIEWED = 'reviewed';

	/**
	 * Integration profile lifecycle: active.
	 *
	 * @var string
	 */
	private const INTEGRATION_PROFILE_STATE_ACTIVE = 'active';

	/**
	 * Automation recipe mode: dry-run.
	 *
	 * @var string
	 */
	private const AUTOMATION_RECIPE_MODE_DRY_RUN = 'dry_run';

	/**
	 * Automation recipe mode: live.
	 *
	 * @var string
	 */
	private const AUTOMATION_RECIPE_MODE_LIVE = 'live';

	/**
	 * External secret manager disabled.
	 *
	 * @var string
	 */
	private const SECRET_MANAGER_PROVIDER_NONE = 'none';

	/**
	 * External secret manager: HashiCorp Vault.
	 *
	 * @var string
	 */
	private const SECRET_MANAGER_PROVIDER_VAULT = 'vault';

	/**
	 * Secrets profile provider mode: disabled.
	 *
	 * @var string
	 */
	private const SECRET_MANAGER_MODE_DISABLED = 'secrets_disabled';

	/**
	 * Secrets profile provider mode: external API secrets.
	 *
	 * @var string
	 */
	private const SECRET_MANAGER_MODE_EXTERNAL_API = 'external_api_secrets';

	/**
	 * Secrets profile provider mode: vault.
	 *
	 * @var string
	 */
	private const SECRET_MANAGER_MODE_VAULT = 'vault';

	/**
	 * Service module keys.
	 *
	 * @var array<int,string>
	 */
	private const SERVICE_MODULE_KEYS = array(
		'database',
		'finance',
		'it_support',
		'projects',
		'custom_dummy',
	);

	/**
	 * Service modules that are always enabled.
	 *
	 * @var array<int,string>
	 */
	private const REQUIRED_SERVICE_MODULE_KEYS = array(
		'database',
	);

	/**
	 * Validation section keys.
	 *
	 * @var array<int,string>
	 */
	private const VALIDATION_SECTION_KEYS = array(
		'billing',
		'helpdesk',
		'pm',
	);

	/**
	 * Model policy action keys.
	 *
	 * @var array<int,string>
	 */
	private const MODEL_POLICY_ACTIONS = array(
		'connect',
		'read',
		'sync',
		'export',
		'delete',
	);

	/**
	 * GDPR legal basis keys.
	 *
	 * @var array<int,string>
	 */
	private const GDPR_LEGAL_BASES = array(
		'consent',
		'contract',
		'legal_obligation',
		'vital_interests',
		'public_task',
		'legitimate_interests',
	);

	/**
	 * Tenant boundary mode: single-site.
	 *
	 * @var string
	 */
	private const TENANT_MODE_SINGLE_SITE = 'single_site';

	/**
	 * Tenant boundary mode: multisite site isolation.
	 *
	 * @var string
	 */
	private const TENANT_MODE_MULTISITE_SITE_ISOLATION = 'multisite_site_isolation';

	/**
	 * Tenant boundary mode: multisite network shared.
	 *
	 * @var string
	 */
	private const TENANT_MODE_MULTISITE_NETWORK_SHARED = 'multisite_network_shared';

	/**
	 * Monitoring mode: no external monitoring.
	 *
	 * @var string
	 */
	private const MONITORING_MODE_NONE = 'none';

	/**
	 * Monitoring mode: generic webhook forwarding.
	 *
	 * @var string
	 */
	private const MONITORING_MODE_GENERIC_WEBHOOK = 'generic_webhook';

	/**
	 * Monitoring mode: hosted centralized logging (subscription).
	 *
	 * @var string
	 */
	private const MONITORING_MODE_HOSTED_SUBSCRIPTION = 'hosted_subscription';

	/**
	 * Data protection at-rest mode disabled.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_AT_REST_DISABLED = 'disabled';

	/**
	 * Database encryption mode: not configured.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_DB_NONE = 'none';

	/**
	 * Database encryption mode: provider/native TDE.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_DB_PROVIDER_TDE = 'provider_tde';

	/**
	 * Database encryption mode: customer-managed key (KMS).
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_DB_KMS = 'kms_managed';

	/**
	 * Database encryption mode: self-hosted encrypted volume.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_DB_SELF_HOSTED = 'self_hosted_encrypted_volume';

	/**
	 * Document encryption mode: plugin-managed local encryption.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_DOC_PLUGIN_LOCAL = 'plugin_local_encrypted';

	/**
	 * Document encryption mode: object storage SSE-KMS.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_DOC_SSE_KMS = 'object_storage_sse_kms';

	/**
	 * Document encryption mode: delegated backend/add-on storage.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_DOC_BACKEND = 'backend_managed';

	/**
	 * KMS provider: none.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_KMS_NONE = 'none';

	/**
	 * KMS provider: Vault transit (self-hosted preferred).
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_KMS_VAULT_TRANSIT = 'vault_transit';

	/**
	 * KMS provider: AWS KMS.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_KMS_AWS = 'aws_kms';

	/**
	 * KMS provider: Azure Key Vault.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_KMS_AZURE = 'azure_key_vault';

	/**
	 * KMS provider: GCP Cloud KMS.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_KMS_GCP = 'gcp_kms';

	/**
	 * KMS provider: self-hosted HSM/KMS.
	 *
	 * @var string
	 */
	private const DATA_PROTECTION_KMS_SELF_HOSTED = 'self_hosted_hsm';

	/**
	 * Cached options.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $cached_options = null;

	/**
	 * Secrets store.
	 *
	 * @var SecretsStore
	 */
	private SecretsStore $secrets_store;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->secrets_store = new SecretsStore();
	}

	/**
	 * Option key getter.
	 *
	 * @return string
	 */
	public function key(): string {
		return self::OPTION_KEY;
	}

	/**
	 * Ensures schema is migrated and persisted.
	 *
	 * @return void
	 */
	public function ensure_schema(): void {
		$raw_option = get_option( self::OPTION_KEY, array() );
		$raw_option = is_array( $raw_option ) ? $raw_option : array();
		$normalized = $this->normalize_options( $raw_option );

		if ( $normalized !== $raw_option ) {
			update_option( self::OPTION_KEY, $normalized, false );
		}

		$this->cached_options = $normalized;
	}

	/**
	 * Ensures licensing instance id exists.
	 *
	 * @return void
	 */
	public function ensure_instance_id(): void {
		$options = $this->get_all();
		if ( '' !== (string) $options['licensing']['instance_id'] ) {
			return;
		}

		$options['licensing']['instance_id'] = wp_generate_uuid4();
		update_option( self::OPTION_KEY, $options, false );
		$this->cached_options = $options;
	}

	/**
	 * Returns full settings array.
	 *
	 * @return array<string,mixed>
	 */
	public function get_all(): array {
		if ( null !== $this->cached_options ) {
			return $this->cached_options;
		}

		$stored               = get_option( self::OPTION_KEY, array() );
		$stored               = is_array( $stored ) ? $stored : array();
		$this->cached_options = $this->normalize_options( $stored );

		return $this->cached_options;
	}

	/**
	 * Gets portal page id.
	 *
	 * @return int
	 */
	public function get_portal_page_id(): int {
		$options = $this->get_all();
		return absint( $options['portal_page_id'] );
	}

	/**
	 * Gets enabled module ids.
	 *
	 * @return array<int,string>
	 */
	public function get_enabled_module_ids(): array {
		$options = $this->get_all();
		return is_array( $options['enabled_modules'] ) ? $options['enabled_modules'] : array();
	}

	/**
	 * Gets service module toggles.
	 *
	 * @return array<string,int>
	 */
	public function get_service_modules(): array {
		$options = $this->get_all();
		return is_array( $options['service_modules'] ) ? $options['service_modules'] : array_fill_keys( self::SERVICE_MODULE_KEYS, 0 );
	}

	/**
	 * Returns whether module key exists in managed service toggles.
	 *
	 * @param string $module_key Module key.
	 * @return bool
	 */
	public function has_service_module_key( string $module_key ): bool {
		return in_array( sanitize_key( $module_key ), self::SERVICE_MODULE_KEYS, true );
	}

	/**
	 * Returns whether service module is enabled.
	 *
	 * @param string $module_key Module key.
	 * @return bool
	 */
	public function is_service_module_enabled( string $module_key ): bool {
		$module_key      = sanitize_key( $module_key );
		$service_modules = $this->get_service_modules();

		if ( ! array_key_exists( $module_key, $service_modules ) ) {
			return false;
		}

		return ! empty( $service_modules[ $module_key ] );
	}

	/**
	 * Gets backend hosting mode.
	 *
	 * Values represent hosting ownership (`managed_service|self_hosted`).
	 *
	 * @return string
	 */
	public function get_backend_mode(): string {
		$env_mode = $this->get_env_backend_provider_mode();
		if ( '' !== $env_mode ) {
			return $env_mode;
		}

		$options = $this->get_all();
		$mode    = sanitize_key( (string) $options['backend']['mode'] );

		if ( ! in_array( $mode, $this->get_supported_backend_provider_modes(), true ) ) {
			return self::BACKEND_MODE_SELF_HOSTED;
		}

		return $mode;
	}

	/**
	 * Gets backend runtime connectivity mode.
	 *
	 * Values represent environment behavior (`mock|staging|production`).
	 *
	 * @return string
	 */
	public function get_backend_runtime_mode(): string {
		$env_mode = $this->get_env_backend_runtime_mode();
		if ( '' !== $env_mode ) {
			return $env_mode;
		}

		return self::BACKEND_RUNTIME_MODE_PRODUCTION;
	}

	/**
	 * Gets backend base URL.
	 *
	 * @return string
	 */
	public function get_backend_base_url(): string {
		$env_base_url = $this->get_env_backend_base_url();
		if ( '' !== $env_base_url ) {
			return $env_base_url;
		}

		$options = $this->get_all();
		return untrailingslashit( esc_url_raw( (string) $options['backend']['base_url'] ) );
	}

	/**
	 * Gets backend validation path.
	 *
	 * @return string
	 */
	public function get_backend_validation_path(): string {
		$env_path = $this->get_env_backend_validation_path();
		if ( '' !== $env_path ) {
			return $env_path;
		}

		$options = $this->get_all();
		$stored  = isset( $options['backend']['validation_path'] ) ? (string) $options['backend']['validation_path'] : '';

		return $this->normalize_backend_path( $stored, self::BACKEND_VALIDATION_PATH_DEFAULT );
	}

	/**
	 * Gets backend health path.
	 *
	 * @return string
	 */
	public function get_backend_health_path(): string {
		$env_path = $this->get_env_backend_health_path();
		if ( '' !== $env_path ) {
			return $env_path;
		}

		$options = $this->get_all();
		$stored  = isset( $options['backend']['health_path'] ) ? (string) $options['backend']['health_path'] : '';

		return $this->normalize_backend_path( $stored, self::BACKEND_HEALTH_PATH_DEFAULT );
	}

	/**
	 * Gets backend mode source.
	 *
	 * @return string
	 */
	public function get_backend_mode_source(): string {
		return '' !== $this->get_env_backend_provider_mode() ? 'env' : 'wp_options';
	}

	/**
	 * Gets backend runtime mode source.
	 *
	 * @return string
	 */
	public function get_backend_runtime_mode_source(): string {
		return '' !== $this->get_env_backend_runtime_mode() ? 'env' : 'default';
	}

	/**
	 * Gets backend base URL source.
	 *
	 * @return string
	 */
	public function get_backend_base_url_source(): string {
		return '' !== $this->get_env_backend_base_url() ? 'env' : 'wp_options';
	}

	/**
	 * Gets backend service token source.
	 *
	 * @return string
	 */
	public function get_backend_service_token_source(): string {
		return '' !== $this->get_env_backend_service_token() ? 'env' : 'wp_options';
	}

	/**
	 * Gets secret manager provider key.
	 *
	 * @return string
	 */
	public function get_secret_manager_provider(): string {
		$options  = $this->get_all();
		$provider = isset( $options['secret_manager']['provider'] )
			? sanitize_key( (string) $options['secret_manager']['provider'] )
			: self::SECRET_MANAGER_PROVIDER_NONE;

		if ( ! in_array( $provider, array( self::SECRET_MANAGER_PROVIDER_NONE, self::SECRET_MANAGER_PROVIDER_VAULT ), true ) ) {
			return self::SECRET_MANAGER_PROVIDER_NONE;
		}

		return $provider;
	}

	/**
	 * Gets external secret manager configuration.
	 *
	 * Non-sensitive Vault routing fields are stored in options.
	 * Sensitive Vault connection references are stored in SecretsStore.
	 *
	 * @return array<string,string>
	 */
	public function get_secret_manager_config(): array {
		$options       = $this->get_all();
		$stored        = isset( $options['secret_manager'] ) && is_array( $options['secret_manager'] )
			? $options['secret_manager']
			: array();
		$provider_mode = isset( $stored['provider_mode'] ) ? sanitize_key( (string) $stored['provider_mode'] ) : '';
		if ( ! in_array( $provider_mode, array( self::SECRET_MANAGER_MODE_DISABLED, self::SECRET_MANAGER_MODE_EXTERNAL_API, self::SECRET_MANAGER_MODE_VAULT ), true ) ) {
			$provider_mode = self::SECRET_MANAGER_PROVIDER_VAULT === $this->get_secret_manager_provider()
				? self::SECRET_MANAGER_MODE_VAULT
				: self::SECRET_MANAGER_MODE_DISABLED;
		}

		return array(
			'provider'             => $this->get_secret_manager_provider(),
			'provider_mode'        => $provider_mode,
			'vault_addr'           => isset( $stored['vault_addr'] ) ? untrailingslashit( esc_url_raw( (string) $stored['vault_addr'] ) ) : '',
			'vault_namespace'      => isset( $stored['vault_namespace'] ) ? sanitize_text_field( (string) $stored['vault_namespace'] ) : '',
			'vault_kv_mount'       => isset( $stored['vault_kv_mount'] ) ? sanitize_key( (string) $stored['vault_kv_mount'] ) : 'kv',
			'vault_path_prefix'    => isset( $stored['vault_path_prefix'] ) ? sanitize_text_field( (string) $stored['vault_path_prefix'] ) : 'uplinkportal',
			'vault_auth_mount'     => isset( $stored['vault_auth_mount'] ) ? sanitize_key( (string) $stored['vault_auth_mount'] ) : 'approle',
			'vault_role'           => isset( $stored['vault_role'] ) ? sanitize_text_field( (string) $stored['vault_role'] ) : '',
			'vault_connection_ref' => $this->secrets_store->get_vault_connection_reference(),
		);
	}

	/**
	 * Gets selected compliance policy bundle.
	 *
	 * @return string
	 */
	public function get_policy_bundle(): string {
		$options = $this->get_all();
		$bundle  = isset( $options['governance']['policy_bundle'] )
			? sanitize_key( (string) $options['governance']['policy_bundle'] )
			: CompliancePolicyProfiles::BUNDLE_MSP_DEFAULT;

		if ( ! CompliancePolicyProfiles::has_bundle( $bundle ) ) {
			return CompliancePolicyProfiles::BUNDLE_MSP_DEFAULT;
		}

		return $bundle;
	}

	/**
	 * Gets model-level RBAC capability matrix.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_model_policy_matrix(): array {
		$options = $this->get_all();
		$stored  = isset( $options['governance']['model_policies'] ) && is_array( $options['governance']['model_policies'] )
			? $options['governance']['model_policies']
			: array();
		$matrix  = $this->get_default_model_policy_matrix();

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			if ( ! isset( $stored[ $model_id ] ) || ! is_array( $stored[ $model_id ] ) ) {
				continue;
			}

			foreach ( self::MODEL_POLICY_ACTIONS as $action ) {
				if ( ! isset( $stored[ $model_id ][ $action ] ) ) {
					continue;
				}

				$matrix[ $model_id ][ $action ] = $this->normalize_model_policy_capability( (string) $stored[ $model_id ][ $action ] );
			}
		}

		return $matrix;
	}

	/**
	 * Gets model-level legal basis map.
	 *
	 * @return array<string,string>
	 */
	public function get_model_legal_basis_map(): array {
		$options = $this->get_all();
		$stored  = isset( $options['governance']['model_legal_basis'] ) && is_array( $options['governance']['model_legal_basis'] )
			? $options['governance']['model_legal_basis']
			: array();
		$map     = $this->get_default_legal_basis_map();

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			if ( ! isset( $stored[ $model_id ] ) ) {
				continue;
			}

			$map[ $model_id ] = $this->normalize_legal_basis( (string) $stored[ $model_id ] );
		}

		return $map;
	}

	/**
	 * Gets data minimization profile.
	 *
	 * @return array<string,mixed>
	 */
	public function get_data_minimization_profile(): array {
		$options = $this->get_all();
		$stored  = isset( $options['governance']['data_minimization'] ) && is_array( $options['governance']['data_minimization'] )
			? $options['governance']['data_minimization']
			: array();

		$default_allowlist = $this->get_default_model_allowlist_map();
		$model_allowlist   = isset( $stored['model_allowlist'] ) && is_array( $stored['model_allowlist'] ) ? $stored['model_allowlist'] : array();
		$provider_override = isset( $stored['provider_overrides'] ) && is_array( $stored['provider_overrides'] ) ? $stored['provider_overrides'] : array();

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			if ( isset( $model_allowlist[ $model_id ] ) && is_array( $model_allowlist[ $model_id ] ) ) {
				$default_allowlist[ $model_id ] = $this->sanitize_allowlist_fields( $model_allowlist[ $model_id ] );
			}
		}

		$normalized_overrides = array();
		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$normalized_overrides[ $model_id ] = array();
			if ( ! isset( $provider_override[ $model_id ] ) || ! is_array( $provider_override[ $model_id ] ) ) {
				continue;
			}

			foreach ( $provider_override[ $model_id ] as $provider_slug => $fields ) {
				$provider_slug = sanitize_key( (string) $provider_slug );
				if ( '' === $provider_slug || ! is_array( $fields ) ) {
					continue;
				}
				$normalized_overrides[ $model_id ][ $provider_slug ] = $this->sanitize_allowlist_fields( $fields );
			}
		}

		return array(
			'model_allowlist'    => $default_allowlist,
			'provider_overrides' => $normalized_overrides,
			'mask_logs'          => isset( $stored['mask_logs'] ) ? ( ! empty( $stored['mask_logs'] ) ? 1 : 0 ) : 1,
		);
	}

	/**
	 * Gets tenant boundary profile.
	 *
	 * @return array<string,mixed>
	 */
	public function get_tenant_boundary_profile(): array {
		$options = $this->get_all();
		$stored  = isset( $options['governance']['tenant_boundary'] ) && is_array( $options['governance']['tenant_boundary'] )
			? $options['governance']['tenant_boundary']
			: array();

		$default_mode = is_multisite() ? self::TENANT_MODE_MULTISITE_SITE_ISOLATION : self::TENANT_MODE_SINGLE_SITE;
		$mode         = isset( $stored['mode'] ) ? $this->normalize_tenant_mode( (string) $stored['mode'] ) : $default_mode;

		return array(
			'mode'                   => $mode,
			'enforce_site_scope'     => isset( $stored['enforce_site_scope'] ) ? ( ! empty( $stored['enforce_site_scope'] ) ? 1 : 0 ) : 1,
			'require_super_admin'    => isset( $stored['require_super_admin'] ) ? ( ! empty( $stored['require_super_admin'] ) ? 1 : 0 ) : 1,
			'enable_multisite_guard' => is_multisite() ? 1 : 0,
		);
	}

	/**
	 * Returns whether audit logs should be masked in admin UI.
	 *
	 * @return bool
	 */
	public function should_mask_audit_logs(): bool {
		$profile = $this->get_data_minimization_profile();
		return ! empty( $profile['mask_logs'] );
	}

	/**
	 * Returns normalized tenant boundary mode.
	 *
	 * @return string
	 */
	public function get_tenant_boundary_mode(): string {
		$profile = $this->get_tenant_boundary_profile();
		return isset( $profile['mode'] ) ? sanitize_key( (string) $profile['mode'] ) : self::TENANT_MODE_SINGLE_SITE;
	}

	/**
	 * Returns whether per-site scope is enforced.
	 *
	 * @return bool
	 */
	public function is_tenant_site_scope_enforced(): bool {
		$profile = $this->get_tenant_boundary_profile();
		return ! empty( $profile['enforce_site_scope'] );
	}

	/**
	 * Returns whether super admin is required for network-shared mode.
	 *
	 * @return bool
	 */
	public function requires_super_admin_for_network_shared(): bool {
		$profile = $this->get_tenant_boundary_profile();
		return ! empty( $profile['require_super_admin'] );
	}

	/**
	 * Gets consolidated governance profile for backend delegation.
	 *
	 * @return array<string,mixed>
	 */
	public function get_governance_profile(): array {
		return array(
			'policy_bundle'     => $this->get_policy_bundle(),
			'model_policies'    => $this->get_model_policy_matrix(),
			'model_legal_basis' => $this->get_model_legal_basis_map(),
			'data_minimization' => $this->get_data_minimization_profile(),
			'tenant_boundaries' => $this->get_tenant_boundary_profile(),
			'monitoring'        => $this->get_monitoring_profile(),
			'evidence_trail'    => $this->get_evidence_trail_enabled() ? 1 : 0,
			'data_protection'   => $this->get_data_protection_profile(),
		);
	}

	/**
	 * Whether strict REST nonce enforcement is enabled.
	 *
	 * @return bool
	 */
	public function get_strict_rest_nonce(): bool {
		$options = $this->get_all();
		return ! empty( $options['security']['strict_rest_nonce'] );
	}

	/**
	 * Whether security audit log is visible.
	 *
	 * @return bool
	 */
	public function get_show_security_audit_log(): bool {
		$options = $this->get_all();
		return ! empty( $options['security']['show_security_audit_log'] );
	}

	/**
	 * Whether tamper-evident evidence trail is enabled.
	 *
	 * @return bool
	 */
	public function get_evidence_trail_enabled(): bool {
		$options = $this->get_all();
		return ! empty( $options['security']['evidence_trail_enabled'] );
	}

	/**
	 * Returns monitoring profile.
	 *
	 * @return array<string,string>
	 */
	public function get_monitoring_profile(): array {
		$options     = $this->get_all();
		$stored      = isset( $options['security']['monitoring'] ) && is_array( $options['security']['monitoring'] )
			? $options['security']['monitoring']
			: array();
		$mode        = isset( $stored['mode'] ) ? sanitize_key( (string) $stored['mode'] ) : self::MONITORING_MODE_NONE;
		$webhook_url = isset( $stored['webhook_url'] ) ? esc_url_raw( (string) $stored['webhook_url'] ) : '';

		return array(
			'mode'        => $this->normalize_monitoring_mode( $mode ),
			'webhook_url' => $webhook_url,
		);
	}

	/**
	 * Returns data protection profile for at-rest controls and key governance.
	 *
	 * @return array<string,mixed>
	 */
	public function get_data_protection_profile(): array {
		$options = $this->get_all();
		$stored  = isset( $options['security']['data_protection'] ) && is_array( $options['security']['data_protection'] )
			? $options['security']['data_protection']
			: array();

		$at_rest_status = isset( $stored['at_rest_status'] )
			? sanitize_key( (string) $stored['at_rest_status'] )
			: self::DATA_PROTECTION_AT_REST_DISABLED;
		$db_mode        = isset( $stored['database_encryption'] )
			? sanitize_key( (string) $stored['database_encryption'] )
			: self::DATA_PROTECTION_DB_NONE;
		$doc_mode       = isset( $stored['document_encryption'] )
			? sanitize_key( (string) $stored['document_encryption'] )
			: self::DATA_PROTECTION_DOC_PLUGIN_LOCAL;
		$kms_provider   = isset( $stored['kms_provider'] )
			? sanitize_key( (string) $stored['kms_provider'] )
			: self::DATA_PROTECTION_KMS_NONE;

			return array(
				'at_rest_status'               => $this->normalize_at_rest_status( $at_rest_status ),
				'database_encryption'          => $this->normalize_database_encryption_mode( $db_mode ),
				'document_encryption'          => $this->normalize_document_encryption_mode( $doc_mode ),
				'kms_provider'                 => $this->normalize_kms_provider( $kms_provider ),
				'key_rotation_days'            => isset( $stored['key_rotation_days'] ) ? $this->normalize_key_rotation_days( absint( $stored['key_rotation_days'] ) ) : 90,
				'separation_of_duties'         => isset( $stored['separation_of_duties'] ) ? ( ! empty( $stored['separation_of_duties'] ) ? 1 : 0 ) : 0,
				'emergency_revoke_runbook_url' => isset( $stored['emergency_revoke_runbook_url'] ) ? esc_url_raw( (string) $stored['emergency_revoke_runbook_url'] ) : '',
			);
	}

	/**
	 * Gets licensing key.
	 *
	 * @return string
	 */
	public function get_license_key(): string {
		$options = $this->get_all();
		return sanitize_text_field( (string) $options['licensing']['license_key'] );
	}

	/**
	 * Gets licensing instance id.
	 *
	 * @return string
	 */
	public function get_instance_id(): string {
		$options = $this->get_all();
		return sanitize_text_field( (string) $options['licensing']['instance_id'] );
	}

	/**
	 * Gets licensing worker base URL.
	 *
	 * @return string
	 */
	public function get_licensing_worker_base_url(): string {
		$options = $this->get_all();
		return untrailingslashit( esc_url_raw( (string) $options['licensing']['worker_base_url'] ) );
	}

	/**
	 * Gets licensing product slug.
	 *
	 * @return string
	 */
	public function get_licensing_product_slug(): string {
		$options = $this->get_all();
		$slug    = sanitize_key( (string) $options['licensing']['product_slug'] );
		return '' !== $slug ? $slug : 'uplinkportal';
	}

	/**
	 * Gets licensing validation cache TTL.
	 *
	 * @return int
	 */
	public function get_licensing_cache_ttl(): int {
		$options = $this->get_all();
		$ttl     = absint( $options['licensing']['cache_ttl'] );
		return max( 60, $ttl );
	}

	/**
	 * Gets validation profiles.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_validation_profiles(): array {
		$options  = $this->get_all();
		$profiles = array();

		foreach ( self::VALIDATION_SECTION_KEYS as $section ) {
			$stored = isset( $options['validation_profiles'][ $section ] ) && is_array( $options['validation_profiles'][ $section ] )
				? $options['validation_profiles'][ $section ]
				: array();

			$profiles[ $section ] = array(
				'base_url'            => isset( $stored['base_url'] ) ? untrailingslashit( esc_url_raw( (string) $stored['base_url'] ) ) : '',
				'api_key'             => $this->secrets_store->get_validation_api_key( $section ),
				'allow_query_api_key' => ! empty( $stored['allow_query_api_key'] ) ? 1 : 0,
			);
		}

		return $profiles;
	}

	/**
	 * Gets validation profile.
	 *
	 * @param string $section Section key.
	 * @return array<string,mixed>
	 */
	public function get_validation_profile( string $section ): array {
		$section  = sanitize_key( $section );
		$profiles = $this->get_validation_profiles();

		return $profiles[ $section ] ?? array(
			'base_url'            => '',
			'api_key'             => '',
			'allow_query_api_key' => 0,
		);
	}

	/**
	 * Returns whether query-string API key is allowed for section.
	 *
	 * @param string $section Section key.
	 * @return bool
	 */
	public function allow_query_api_key_for_section( string $section ): bool {
		$profile = $this->get_validation_profile( $section );
		return ! empty( $profile['allow_query_api_key'] );
	}

	/**
	 * Gets integration profiles keyed by canonical model id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_integration_profiles(): array {
		$options  = $this->get_all();
		$profiles = array();

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$stored = isset( $options['integration_profiles'][ $model_id ] ) && is_array( $options['integration_profiles'][ $model_id ] )
				? $options['integration_profiles'][ $model_id ]
				: array();

			$scopes = array();
			if ( isset( $stored['scopes'] ) && is_array( $stored['scopes'] ) ) {
				foreach ( $stored['scopes'] as $scope ) {
					$scope = sanitize_text_field( (string) $scope );
					if ( '' !== $scope ) {
						$scopes[] = $scope;
					}
				}
			}

			$profiles[ $model_id ] = array(
				'provider'             => isset( $stored['provider'] ) ? sanitize_key( (string) $stored['provider'] ) : 'custom',
				'auth_type'            => isset( $stored['auth_type'] ) ? sanitize_key( (string) $stored['auth_type'] ) : 'api_key',
				'scopes'               => $scopes,
				'endpoint_alias'       => isset( $stored['endpoint_alias'] ) ? sanitize_text_field( (string) $stored['endpoint_alias'] ) : '',
				'retention_policy'     => isset( $stored['retention_policy'] ) ? sanitize_key( (string) $stored['retention_policy'] ) : 'default',
				'lifecycle_state'      => $this->normalize_profile_lifecycle_state( isset( $stored['lifecycle_state'] ) ? (string) $stored['lifecycle_state'] : self::INTEGRATION_PROFILE_STATE_DRAFT ),
				'oauth_connection_ref' => $this->secrets_store->get_oauth_connection_reference( $model_id ),
			);
		}

		return $profiles;
	}

	/**
	 * Gets one integration profile by model id.
	 *
	 * @param string $model_id Canonical model id.
	 * @return array<string,mixed>
	 */
	public function get_integration_profile( string $model_id ): array {
		$model_id = sanitize_key( $model_id );
		$profiles = $this->get_integration_profiles();

		return $profiles[ $model_id ] ?? array(
			'provider'             => 'custom',
			'auth_type'            => 'api_key',
			'scopes'               => array(),
			'endpoint_alias'       => '',
			'retention_policy'     => 'default',
			'lifecycle_state'      => self::INTEGRATION_PROFILE_STATE_DRAFT,
			'oauth_connection_ref' => '',
		);
	}

	/**
	 * Gets automation recipe profiles keyed by recipe id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_automation_recipe_profiles(): array {
		$options   = $this->get_all();
		$stored    = isset( $options['automation_recipes'] ) && is_array( $options['automation_recipes'] )
			? $options['automation_recipes']
			: array();
		$templates = AutomationRecipeCatalog::templates();
		$profiles  = array();

		foreach ( $templates as $recipe_id => $template ) {
			$entry = isset( $stored[ $recipe_id ] ) && is_array( $stored[ $recipe_id ] )
				? $stored[ $recipe_id ]
				: array();

			$profiles[ $recipe_id ] = array(
				'enabled' => isset( $entry['enabled'] ) ? ( ! empty( $entry['enabled'] ) ? 1 : 0 ) : 0,
				'mode'    => isset( $entry['mode'] )
				? $this->normalize_automation_recipe_mode( (string) $entry['mode'] )
				: self::AUTOMATION_RECIPE_MODE_DRY_RUN,
				'model'   => isset( $template['model'] ) ? sanitize_key( (string) $template['model'] ) : '',
			);
		}

		return $profiles;
	}

	/**
	 * Gets one automation recipe profile.
	 *
	 * @param string $recipe_id Recipe id.
	 * @return array<string,mixed>
	 */
	public function get_automation_recipe_profile( string $recipe_id ): array {
		$recipe_id = sanitize_key( $recipe_id );
		$profiles  = $this->get_automation_recipe_profiles();

		return $profiles[ $recipe_id ] ?? array(
			'enabled' => 0,
			'mode'    => self::AUTOMATION_RECIPE_MODE_DRY_RUN,
			'model'   => '',
		);
	}

	/**
	 * Gets recipe revision history (newest first).
	 *
	 * @param string $recipe_id Recipe id.
	 * @param int    $limit Max entries.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_automation_recipe_revisions( string $recipe_id, int $limit = 10 ): array {
		$recipe_id = sanitize_key( $recipe_id );
		$limit     = max( 1, absint( $limit ) );
		$options   = $this->get_all();
		$raw       = isset( $options['automation_recipe_revisions'][ $recipe_id ] ) && is_array( $options['automation_recipe_revisions'][ $recipe_id ] )
			? $options['automation_recipe_revisions'][ $recipe_id ]
			: array();
		$entries   = $this->normalize_recipe_revision_entries( $raw );
		if ( empty( $entries ) ) {
			return array();
		}

		return array_slice( array_reverse( $entries ), 0, $limit );
	}

	/**
	 * Returns whether a third-party secret is stored.
	 *
	 * @param string $secret_name Secret key.
	 * @return bool
	 */
	public function has_third_party_secret( string $secret_name ): bool {
		return $this->secrets_store->has_third_party_secret( $secret_name );
	}

	/**
	 * Returns backend service token for backend-to-backend operations.
	 *
	 * @return string
	 */
	public function get_backend_service_token(): string {
		$env_token = $this->get_env_backend_service_token();
		if ( '' !== $env_token ) {
			return $env_token;
		}

		return sanitize_text_field( $this->secrets_store->get_backend_service_token() );
	}

	/**
	 * Returns whether backend service token exists.
	 *
	 * @return bool
	 */
	public function has_backend_service_token(): bool {
		if ( '' !== $this->get_env_backend_service_token() ) {
			return true;
		}

		return $this->secrets_store->has_backend_service_token();
	}

	/**
	 * Returns whether Vault connection reference exists.
	 *
	 * @return bool
	 */
	public function has_vault_connection_reference(): bool {
		return $this->secrets_store->has_vault_connection_reference();
	}

	/**
	 * Returns whether OAuth connection reference exists for model.
	 *
	 * @param string $model_id Canonical model id.
	 * @return bool
	 */
	public function has_oauth_connection_reference( string $model_id ): bool {
		return $this->secrets_store->has_oauth_connection_reference( $model_id );
	}

	/**
	 * Returns validation API key saved-at timestamp.
	 *
	 * @param string $section Validation section key.
	 * @return string
	 */
	public function get_validation_api_key_saved_at( string $section ): string {
		return sanitize_text_field( $this->secrets_store->get_validation_api_key_saved_at( $section ) );
	}

	/**
	 * Returns backend service token saved-at timestamp.
	 *
	 * @return string
	 */
	public function get_backend_service_token_saved_at(): string {
		return sanitize_text_field( $this->secrets_store->get_backend_service_token_saved_at() );
	}

	/**
	 * Returns Vault connection reference saved-at timestamp.
	 *
	 * @return string
	 */
	public function get_vault_connection_reference_saved_at(): string {
		return sanitize_text_field( $this->secrets_store->get_vault_connection_reference_saved_at() );
	}

	/**
	 * Returns OAuth connection reference saved-at timestamp for model.
	 *
	 * @param string $model_id Canonical model id.
	 * @return string
	 */
	public function get_oauth_connection_reference_saved_at( string $model_id ): string {
		return sanitize_text_field( $this->secrets_store->get_oauth_connection_reference_saved_at( $model_id ) );
	}

	/**
	 * Returns sodium availability for encryption-at-rest.
	 *
	 * @return bool
	 */
	public function secrets_encryption_available(): bool {
		return $this->secrets_store->sodium_available();
	}

	/**
	 * Returns fallback warning when encryption is not available.
	 *
	 * @return string
	 */
	public function get_secrets_encryption_warning(): string {
		return $this->secrets_store->get_encryption_warning();
	}

	/**
	 * Returns env override value.
	 *
	 * @param string $key Env key.
	 * @return string
	 */
	private function get_env_value( string $key ): string {
		$value = getenv( $key );
		if ( false === $value ) {
			return '';
		}

		$value = trim( (string) $value );
		return '' === $value ? '' : $value;
	}

	/**
	 * Returns env backend hosting mode when valid.
	 *
	 * @return string
	 */
	private function get_env_backend_provider_mode(): string {
		$env_mode = sanitize_key( $this->get_env_value( self::ENV_BACKEND_MODE ) );
		if ( '' === $env_mode ) {
			return '';
		}

		if ( ! in_array( $env_mode, $this->get_supported_backend_provider_modes(), true ) ) {
			return '';
		}

		return $env_mode;
	}

	/**
	 * Returns env backend runtime mode when valid.
	 *
	 * @return string
	 */
	private function get_env_backend_runtime_mode(): string {
		$env_mode = sanitize_key( $this->get_env_value( self::ENV_BACKEND_MODE ) );
		if ( '' === $env_mode ) {
			return '';
		}

		if ( ! in_array( $env_mode, $this->get_supported_backend_runtime_modes(), true ) ) {
			return '';
		}

		return $env_mode;
	}

	/**
	 * Returns env backend base URL when available.
	 *
	 * @return string
	 */
	private function get_env_backend_base_url(): string {
		$env_url = $this->get_env_value( self::ENV_BACKEND_BASE_URL );
		if ( '' === $env_url ) {
			return '';
		}

		return untrailingslashit( esc_url_raw( $env_url ) );
	}

	/**
	 * Returns env backend service token when available.
	 *
	 * @return string
	 */
	private function get_env_backend_service_token(): string {
		return trim( sanitize_text_field( $this->get_env_value( self::ENV_BACKEND_SERVICE_TOKEN ) ) );
	}

	/**
	 * Returns env backend validation path when available.
	 *
	 * @return string
	 */
	private function get_env_backend_validation_path(): string {
		$env_path = $this->get_env_value( self::ENV_BACKEND_VALIDATION_PATH );
		if ( '' === $env_path ) {
			return '';
		}

		return $this->normalize_backend_path( $env_path, self::BACKEND_VALIDATION_PATH_DEFAULT );
	}

	/**
	 * Returns env backend health path when available.
	 *
	 * @return string
	 */
	private function get_env_backend_health_path(): string {
		$env_path = $this->get_env_value( self::ENV_BACKEND_HEALTH_PATH );
		if ( '' === $env_path ) {
			return '';
		}

		return $this->normalize_backend_path( $env_path, self::BACKEND_HEALTH_PATH_DEFAULT );
	}

	/**
	 * Normalizes a backend endpoint path.
	 *
	 * @param string $path Raw path.
	 * @param string $default_path Default path.
	 * @return string
	 */
	private function normalize_backend_path( string $path, string $default_path ): string {
		$path = trim( sanitize_text_field( $path ) );
		if ( '' === $path ) {
			return $default_path;
		}

		$path = '/' . ltrim( $path, '/' );
		$path = preg_replace( '#/+#', '/', $path );

		return is_string( $path ) && '' !== $path ? $path : $default_path;
	}

	/**
	 * Returns valid backend hosting mode values.
	 *
	 * @return array<int,string>
	 */
	private function get_supported_backend_provider_modes(): array {
		return array(
			self::BACKEND_MODE_MANAGED,
			self::BACKEND_MODE_SELF_HOSTED,
		);
	}

	/**
	 * Returns valid backend runtime mode values.
	 *
	 * @return array<int,string>
	 */
	private function get_supported_backend_runtime_modes(): array {
		return array(
			self::BACKEND_RUNTIME_MODE_MOCK,
			self::BACKEND_RUNTIME_MODE_STAGING,
			self::BACKEND_RUNTIME_MODE_PRODUCTION,
		);
	}

	/**
	 * Sanitizes settings payload.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $value ): array {
		$existing = $this->get_all();
		$value    = is_array( $value ) ? $value : array();

		$sanitized                   = $existing;
		$sanitized['schema_version'] = self::SCHEMA_VERSION;
		$locked_models               = array();

		$sanitized['portal_page_id'] = isset( $value['portal_page_id'] ) ? absint( $value['portal_page_id'] ) : $existing['portal_page_id'];

		if ( isset( $value['enabled_modules'] ) && is_array( $value['enabled_modules'] ) ) {
			$sanitized['enabled_modules'] = array_values( array_filter( array_map( 'sanitize_key', $value['enabled_modules'] ) ) );
		}

		$backend_mode = self::BACKEND_MODE_SELF_HOSTED;
		if ( isset( $value['backend']['mode'] ) ) {
			$backend_mode = sanitize_key( (string) $value['backend']['mode'] );
		}
		if ( in_array( $backend_mode, array( self::BACKEND_MODE_MANAGED, self::BACKEND_MODE_SELF_HOSTED ), true ) ) {
			$sanitized['backend']['mode'] = $backend_mode;
		}

		if ( isset( $value['backend']['base_url'] ) ) {
			$sanitized['backend']['base_url'] = untrailingslashit( esc_url_raw( (string) $value['backend']['base_url'] ) );
		}

		if ( isset( $value['backend']['validation_path'] ) ) {
			$sanitized['backend']['validation_path'] = $this->normalize_backend_path( (string) $value['backend']['validation_path'], self::BACKEND_VALIDATION_PATH_DEFAULT );
		}

		if ( isset( $value['backend']['health_path'] ) ) {
			$sanitized['backend']['health_path'] = $this->normalize_backend_path( (string) $value['backend']['health_path'], self::BACKEND_HEALTH_PATH_DEFAULT );
		}

		$secret_manager_provider = self::SECRET_MANAGER_PROVIDER_NONE;
		if ( isset( $value['secret_manager']['provider'] ) ) {
			$secret_manager_provider = sanitize_key( (string) $value['secret_manager']['provider'] );
		}

		$secret_provider_mode = isset( $value['secret_manager']['provider_mode'] )
			? sanitize_key( (string) $value['secret_manager']['provider_mode'] )
			: '';
		if ( in_array( $secret_provider_mode, array( self::SECRET_MANAGER_MODE_DISABLED, self::SECRET_MANAGER_MODE_EXTERNAL_API, self::SECRET_MANAGER_MODE_VAULT ), true ) ) {
			$sanitized['secret_manager']['provider_mode'] = $secret_provider_mode;
			$secret_manager_provider                      = self::SECRET_MANAGER_MODE_VAULT === $secret_provider_mode
				? self::SECRET_MANAGER_PROVIDER_VAULT
				: self::SECRET_MANAGER_PROVIDER_NONE;
		}

		if ( in_array( $secret_manager_provider, array( self::SECRET_MANAGER_PROVIDER_NONE, self::SECRET_MANAGER_PROVIDER_VAULT ), true ) ) {
			$sanitized['secret_manager']['provider'] = $secret_manager_provider;
		}

		if ( isset( $value['secret_manager'] ) && is_array( $value['secret_manager'] ) ) {
			$secret_manager = $value['secret_manager'];

			if ( isset( $secret_manager['vault_addr'] ) ) {
				$sanitized['secret_manager']['vault_addr'] = untrailingslashit( esc_url_raw( (string) $secret_manager['vault_addr'] ) );
			}
			if ( isset( $secret_manager['vault_namespace'] ) ) {
				$sanitized['secret_manager']['vault_namespace'] = sanitize_text_field( (string) $secret_manager['vault_namespace'] );
			}
			if ( isset( $secret_manager['vault_kv_mount'] ) ) {
				$mount = sanitize_key( (string) $secret_manager['vault_kv_mount'] );
				$sanitized['secret_manager']['vault_kv_mount'] = '' !== $mount ? $mount : 'kv';
			}
			if ( isset( $secret_manager['vault_path_prefix'] ) ) {
				$prefix = sanitize_text_field( (string) $secret_manager['vault_path_prefix'] );
				$sanitized['secret_manager']['vault_path_prefix'] = '' !== $prefix ? $prefix : 'uplinkportal';
			}
			if ( isset( $secret_manager['vault_auth_mount'] ) ) {
				$auth_mount                                      = sanitize_key( (string) $secret_manager['vault_auth_mount'] );
				$sanitized['secret_manager']['vault_auth_mount'] = '' !== $auth_mount ? $auth_mount : 'approle';
			}
			if ( isset( $secret_manager['vault_role'] ) ) {
				$sanitized['secret_manager']['vault_role'] = sanitize_text_field( (string) $secret_manager['vault_role'] );
			}
			if ( isset( $secret_manager['vault_connection_ref'] ) ) {
				$vault_reference = trim( sanitize_text_field( (string) $secret_manager['vault_connection_ref'] ) );
				if ( '' !== $vault_reference ) {
					$this->secrets_store->save_vault_connection_reference( $vault_reference );
				}
			}
		}

		$policy_bundle = CompliancePolicyProfiles::BUNDLE_MSP_DEFAULT;
		if ( isset( $value['governance']['policy_bundle'] ) ) {
			$policy_bundle = sanitize_key( (string) $value['governance']['policy_bundle'] );
		}
		$sanitized['governance']['policy_bundle'] = $this->normalize_policy_bundle( $policy_bundle );

		if ( isset( $value['governance']['model_policies'] ) && is_array( $value['governance']['model_policies'] ) ) {
			$submitted_matrix = $value['governance']['model_policies'];
			$base_matrix      = $this->get_default_model_policy_matrix();

			foreach ( IntegrationModelRegistry::ids() as $model_id ) {
				if ( ! isset( $submitted_matrix[ $model_id ] ) || ! is_array( $submitted_matrix[ $model_id ] ) ) {
					continue;
				}

				foreach ( self::MODEL_POLICY_ACTIONS as $action ) {
					if ( ! isset( $submitted_matrix[ $model_id ][ $action ] ) ) {
						continue;
					}

					$base_matrix[ $model_id ][ $action ] = $this->normalize_model_policy_capability( (string) $submitted_matrix[ $model_id ][ $action ] );
				}
			}

			$sanitized['governance']['model_policies'] = $base_matrix;
		}

		if ( isset( $value['governance']['model_legal_basis'] ) && is_array( $value['governance']['model_legal_basis'] ) ) {
			$submitted_legal_basis = $value['governance']['model_legal_basis'];
			$legal_basis_map       = $this->get_default_legal_basis_map();

			foreach ( IntegrationModelRegistry::ids() as $model_id ) {
				if ( ! isset( $submitted_legal_basis[ $model_id ] ) ) {
					continue;
				}

				$legal_basis_map[ $model_id ] = $this->normalize_legal_basis( (string) $submitted_legal_basis[ $model_id ] );
			}

			$sanitized['governance']['model_legal_basis'] = $legal_basis_map;
		}

		if ( isset( $value['governance']['data_minimization'] ) && is_array( $value['governance']['data_minimization'] ) ) {
			$submitted_minimization = $value['governance']['data_minimization'];
			$model_allowlist        = $this->get_default_model_allowlist_map();
			$provider_overrides     = array_fill_keys( IntegrationModelRegistry::ids(), array() );

			if ( isset( $submitted_minimization['model_allowlist_text'] ) && is_array( $submitted_minimization['model_allowlist_text'] ) ) {
				foreach ( IntegrationModelRegistry::ids() as $model_id ) {
					if ( ! isset( $submitted_minimization['model_allowlist_text'][ $model_id ] ) ) {
						continue;
					}

					$model_allowlist[ $model_id ] = $this->parse_allowlist_text( (string) $submitted_minimization['model_allowlist_text'][ $model_id ] );
				}
			}

			if ( isset( $submitted_minimization['provider_overrides_text'] ) && is_array( $submitted_minimization['provider_overrides_text'] ) ) {
				foreach ( IntegrationModelRegistry::ids() as $model_id ) {
					if ( ! isset( $submitted_minimization['provider_overrides_text'][ $model_id ] ) ) {
						continue;
					}

					$provider_overrides[ $model_id ] = $this->parse_provider_override_text( (string) $submitted_minimization['provider_overrides_text'][ $model_id ] );
				}
			}

			$sanitized['governance']['data_minimization'] = array(
				'model_allowlist'    => $model_allowlist,
				'provider_overrides' => $provider_overrides,
				'mask_logs'          => isset( $submitted_minimization['mask_logs'] ) && ! empty( $submitted_minimization['mask_logs'] ) ? 1 : 0,
			);
		}

		if ( isset( $value['governance']['tenant_boundary'] ) && is_array( $value['governance']['tenant_boundary'] ) ) {
			$submitted_tenant = $value['governance']['tenant_boundary'];
			$default_mode     = is_multisite() ? self::TENANT_MODE_MULTISITE_SITE_ISOLATION : self::TENANT_MODE_SINGLE_SITE;
			$mode             = isset( $submitted_tenant['mode'] ) ? $this->normalize_tenant_mode( (string) $submitted_tenant['mode'] ) : $default_mode;

			$sanitized['governance']['tenant_boundary'] = array(
				'mode'                => $mode,
				'enforce_site_scope'  => isset( $submitted_tenant['enforce_site_scope'] ) && ! empty( $submitted_tenant['enforce_site_scope'] ) ? 1 : 0,
				'require_super_admin' => isset( $submitted_tenant['require_super_admin'] ) && ! empty( $submitted_tenant['require_super_admin'] ) ? 1 : 0,
			);
		}

		if ( isset( $value['strict_rest_nonce'] ) || isset( $value['security'] ) ) {
			$sanitized['security']['strict_rest_nonce'] = ! empty( $value['strict_rest_nonce'] ) || ! empty( $value['security']['strict_rest_nonce'] ) ? 1 : 0;
		}
		if ( isset( $value['show_security_audit_log'] ) || isset( $value['security'] ) ) {
			$sanitized['security']['show_security_audit_log'] = ! empty( $value['show_security_audit_log'] ) || ! empty( $value['security']['show_security_audit_log'] ) ? 1 : 0;
		}
		if ( isset( $value['evidence_trail_enabled'] ) || isset( $value['security'] ) ) {
			$sanitized['security']['evidence_trail_enabled'] = ! empty( $value['evidence_trail_enabled'] ) || ! empty( $value['security']['evidence_trail_enabled'] ) ? 1 : 0;
		}
		if ( isset( $value['security']['monitoring'] ) && is_array( $value['security']['monitoring'] ) ) {
			$submitted_monitoring                = $value['security']['monitoring'];
			$mode                                = isset( $submitted_monitoring['mode'] ) ? sanitize_key( (string) $submitted_monitoring['mode'] ) : self::MONITORING_MODE_NONE;
			$sanitized['security']['monitoring'] = array(
				'mode'        => $this->normalize_monitoring_mode( $mode ),
				'webhook_url' => isset( $submitted_monitoring['webhook_url'] ) ? esc_url_raw( (string) $submitted_monitoring['webhook_url'] ) : '',
			);
		}
		if ( isset( $value['security']['data_protection'] ) && is_array( $value['security']['data_protection'] ) ) {
			$submitted_protection = $value['security']['data_protection'];
			$at_rest_status       = isset( $submitted_protection['at_rest_status'] )
				? sanitize_key( (string) $submitted_protection['at_rest_status'] )
				: self::DATA_PROTECTION_AT_REST_DISABLED;
			$db_encryption        = isset( $submitted_protection['database_encryption'] )
				? sanitize_key( (string) $submitted_protection['database_encryption'] )
				: self::DATA_PROTECTION_DB_NONE;
			$doc_encryption       = isset( $submitted_protection['document_encryption'] )
				? sanitize_key( (string) $submitted_protection['document_encryption'] )
				: self::DATA_PROTECTION_DOC_PLUGIN_LOCAL;
			$kms_provider         = isset( $submitted_protection['kms_provider'] )
				? sanitize_key( (string) $submitted_protection['kms_provider'] )
				: self::DATA_PROTECTION_KMS_NONE;

			$sanitized['security']['data_protection'] = array(
				'at_rest_status'               => $this->normalize_at_rest_status( $at_rest_status ),
				'database_encryption'          => $this->normalize_database_encryption_mode( $db_encryption ),
				'document_encryption'          => $this->normalize_document_encryption_mode( $doc_encryption ),
				'kms_provider'                 => $this->normalize_kms_provider( $kms_provider ),
				'key_rotation_days'            => isset( $submitted_protection['key_rotation_days'] ) ? $this->normalize_key_rotation_days( absint( $submitted_protection['key_rotation_days'] ) ) : 90,
				'separation_of_duties'         => isset( $submitted_protection['separation_of_duties'] ) && ! empty( $submitted_protection['separation_of_duties'] ) ? 1 : 0,
				'emergency_revoke_runbook_url' => isset( $submitted_protection['emergency_revoke_runbook_url'] ) ? esc_url_raw( (string) $submitted_protection['emergency_revoke_runbook_url'] ) : '',
			);
		}

		$service_modules_submitted = ! empty( $value['service_modules_submitted'] );
		if ( $service_modules_submitted || ( isset( $value['service_modules'] ) && is_array( $value['service_modules'] ) ) ) {
			$submitted_modules = isset( $value['service_modules'] ) && is_array( $value['service_modules'] ) ? $value['service_modules'] : array();
			foreach ( self::SERVICE_MODULE_KEYS as $module_key ) {
				$sanitized['service_modules'][ $module_key ] = ! empty( $submitted_modules[ $module_key ] ) ? 1 : 0;
			}
		}
		foreach ( self::REQUIRED_SERVICE_MODULE_KEYS as $required_module ) {
			$sanitized['service_modules'][ $required_module ] = 1;
		}

		$submitted_licensing = isset( $value['licensing'] ) && is_array( $value['licensing'] ) ? $value['licensing'] : array();

		if ( isset( $submitted_licensing['license_key'] ) ) {
			$sanitized['licensing']['license_key'] = sanitize_text_field( (string) $submitted_licensing['license_key'] );
		}
		if ( isset( $submitted_licensing['worker_base_url'] ) ) {
			$sanitized['licensing']['worker_base_url'] = untrailingslashit( esc_url_raw( (string) $submitted_licensing['worker_base_url'] ) );
		}
		if ( isset( $submitted_licensing['product_slug'] ) ) {
			$slug                                   = sanitize_key( (string) $submitted_licensing['product_slug'] );
			$sanitized['licensing']['product_slug'] = '' !== $slug ? $slug : 'uplinkportal';
		}
		if ( isset( $submitted_licensing['cache_ttl'] ) ) {
			$sanitized['licensing']['cache_ttl'] = max( 60, absint( $submitted_licensing['cache_ttl'] ) );
		}

		if ( isset( $value['validation_profiles'] ) && is_array( $value['validation_profiles'] ) ) {
			foreach ( self::VALIDATION_SECTION_KEYS as $section ) {
				$submitted = isset( $value['validation_profiles'][ $section ] ) && is_array( $value['validation_profiles'][ $section ] )
				? $value['validation_profiles'][ $section ]
				: array();

				if ( isset( $submitted['base_url'] ) ) {
					$sanitized['validation_profiles'][ $section ]['base_url'] = untrailingslashit( esc_url_raw( (string) $submitted['base_url'] ) );
				}

				$sanitized['validation_profiles'][ $section ]['allow_query_api_key'] = ! empty( $submitted['allow_query_api_key'] ) ? 1 : 0;

				if ( isset( $submitted['api_key'] ) ) {
					$api_key = sanitize_text_field( (string) $submitted['api_key'] );
					if ( '' !== $api_key ) {
						$this->secrets_store->save_validation_api_key( $section, $api_key );
					}
				}
			}
		}

		if ( isset( $value['integration_profiles'] ) && is_array( $value['integration_profiles'] ) ) {
			$entitlement_client_id    = isset( $value['entitlements_context_client_id'] ) ? absint( $value['entitlements_context_client_id'] ) : 0;
			$provider_catalog_service = null;
			foreach ( IntegrationModelRegistry::ids() as $model_id ) {
				$submitted = isset( $value['integration_profiles'][ $model_id ] ) && is_array( $value['integration_profiles'][ $model_id ] )
				? $value['integration_profiles'][ $model_id ]
				: array();

				$provider   = isset( $submitted['provider'] ) ? sanitize_key( (string) $submitted['provider'] ) : 'custom';
				$auth_type  = isset( $submitted['auth_type'] ) ? sanitize_key( (string) $submitted['auth_type'] ) : 'api_key';
				$alias      = isset( $submitted['endpoint_alias'] ) ? sanitize_text_field( (string) $submitted['endpoint_alias'] ) : '';
				$retention  = isset( $submitted['retention_policy'] ) ? sanitize_key( (string) $submitted['retention_policy'] ) : 'default';
				$lifecycle  = isset( $submitted['lifecycle_state'] ) ? sanitize_key( (string) $submitted['lifecycle_state'] ) : self::INTEGRATION_PROFILE_STATE_DRAFT;
				$scope_text = isset( $submitted['scopes_text'] ) ? sanitize_textarea_field( (string) $submitted['scopes_text'] ) : '';
				if ( 'custom' === $provider && isset( $submitted['provider_custom'] ) ) {
					$provider_custom = sanitize_key( (string) $submitted['provider_custom'] );
					if ( '' !== $provider_custom ) {
						$provider = $provider_custom;
					}
				}

				$is_locked_provider = false;
				if ( '' !== $provider && 'custom' !== $provider ) {
					if ( null === $provider_catalog_service ) {
						$provider_catalog_service = new ProviderCatalogService( $this );
					}

					$provider_module    = $this->provider_catalog_module_slug_for_model( sanitize_key( $model_id ) );
					$is_locked_provider = $provider_catalog_service->is_provider_locked(
						$provider_module,
						$provider,
						$entitlement_client_id > 0 ? $entitlement_client_id : null
					);
				}

				if ( $is_locked_provider ) {
					$lifecycle       = self::INTEGRATION_PROFILE_STATE_DRAFT;
					$locked_models[] = $model_id;
				}

				$scopes = array();
				if ( '' !== $scope_text ) {
					$raw_scopes = preg_split( '/[\r\n,]+/', $scope_text );
					if ( is_array( $raw_scopes ) ) {
						foreach ( $raw_scopes as $scope_value ) {
							$scope_value = sanitize_text_field( (string) $scope_value );
							if ( '' !== $scope_value ) {
								$scopes[] = $scope_value;
							}
						}
					}
				}

				$sanitized['integration_profiles'][ $model_id ] = array(
					'provider'         => '' !== $provider ? $provider : 'custom',
					'auth_type'        => '' !== $auth_type ? $auth_type : 'api_key',
					'scopes'           => $scopes,
					'endpoint_alias'   => $alias,
					'retention_policy' => '' !== $retention ? $retention : 'default',
					'lifecycle_state'  => $this->normalize_profile_lifecycle_state( $lifecycle ),
				);

				if ( isset( $submitted['oauth_connection_ref'] ) ) {
					$oauth_reference = trim( sanitize_text_field( (string) $submitted['oauth_connection_ref'] ) );
					if ( '' !== $oauth_reference ) {
						$this->secrets_store->save_oauth_connection_reference( $model_id, $oauth_reference );
					}
				}
			}
		}
		$locked_models = array_values( array_unique( array_map( 'sanitize_key', $locked_models ) ) );

		if ( isset( $value['automation_recipes'] ) && is_array( $value['automation_recipes'] ) ) {
			$recipe_templates = AutomationRecipeCatalog::templates();
			foreach ( AutomationRecipeCatalog::ids() as $recipe_id ) {
				$submitted = isset( $value['automation_recipes'][ $recipe_id ] ) && is_array( $value['automation_recipes'][ $recipe_id ] )
				? $value['automation_recipes'][ $recipe_id ]
				: array();
				$current   = isset( $existing['automation_recipes'][ $recipe_id ] ) && is_array( $existing['automation_recipes'][ $recipe_id ] )
				? $this->sanitize_recipe_profile_state( $existing['automation_recipes'][ $recipe_id ] )
				: array(
					'enabled' => 0,
					'mode'    => self::AUTOMATION_RECIPE_MODE_DRY_RUN,
				);
				$revisions = isset( $existing['automation_recipe_revisions'][ $recipe_id ] ) && is_array( $existing['automation_recipe_revisions'][ $recipe_id ] )
				? $this->normalize_recipe_revision_entries( $existing['automation_recipe_revisions'][ $recipe_id ] )
				: array();
				$new_state = $this->sanitize_recipe_profile_state( $submitted );

				$rollback_apply = isset( $submitted['rollback_apply'] ) && ! empty( $submitted['rollback_apply'] );
				$rollback_id    = isset( $submitted['rollback_revision_id'] ) ? sanitize_text_field( (string) $submitted['rollback_revision_id'] ) : '';
				if ( $rollback_apply && '' !== $rollback_id ) {
					foreach ( $revisions as $revision ) {
						$revision_id = isset( $revision['id'] ) ? sanitize_text_field( (string) $revision['id'] ) : '';
						if ( $rollback_id !== $revision_id ) {
							continue;
						}

						if ( isset( $revision['state'] ) && is_array( $revision['state'] ) ) {
							$new_state = $this->sanitize_recipe_profile_state( $revision['state'] );
						}
						break;
					}
				}

				$recipe_model = isset( $recipe_templates[ $recipe_id ]['model'] )
					? sanitize_key( (string) $recipe_templates[ $recipe_id ]['model'] )
					: '';
				if ( '' !== $recipe_model && in_array( $recipe_model, $locked_models, true ) ) {
					$new_state['enabled'] = 0;
				}

				if ( $new_state !== $current ) {
					$revisions = $this->append_recipe_revision( $revisions, $current );
				}

				$sanitized['automation_recipes'][ $recipe_id ]          = $new_state;
				$sanitized['automation_recipe_revisions'][ $recipe_id ] = $revisions;
			}
		}

		if ( isset( $value['third_party_secrets'] ) && is_array( $value['third_party_secrets'] ) ) {
			foreach ( array( 'api_key', 'api_secret' ) as $secret_key ) {
				if ( ! isset( $value['third_party_secrets'][ $secret_key ] ) ) {
					continue;
				}

				$new_secret = trim( sanitize_text_field( (string) $value['third_party_secrets'][ $secret_key ] ) );
				if ( '' !== $new_secret ) {
					$this->secrets_store->save_third_party_secret( $secret_key, $new_secret );
				}
			}
		}

		if ( isset( $value['backend_secrets'] ) && is_array( $value['backend_secrets'] ) && isset( $value['backend_secrets']['service_token'] ) ) {
			$backend_token = trim( sanitize_text_field( (string) $value['backend_secrets']['service_token'] ) );
			if ( '' !== $backend_token ) {
				$this->secrets_store->save_backend_service_token( $backend_token );
			}
		} elseif ( isset( $value['backend_service_token'] ) ) {
			$backend_token = trim( sanitize_text_field( (string) $value['backend_service_token'] ) );
			if ( '' !== $backend_token ) {
				$this->secrets_store->save_backend_service_token( $backend_token );
			}
		}

		if ( '' === (string) $sanitized['licensing']['instance_id'] ) {
			$sanitized['licensing']['instance_id'] = wp_generate_uuid4();
		}

		$this->cached_options = $sanitized;
		return $sanitized;
	}

	/**
	 * Returns normalized settings shape.
	 *
	 * @param array<string,mixed> $raw Raw option payload.
	 * @return array<string,mixed>
	 */
	private function normalize_options( array $raw ): array {
		$defaults = array(
			'schema_version'              => self::SCHEMA_VERSION,
			'portal_page_id'              => 0,
			'enabled_modules'             => array(),
			'backend'                     => array(
				'mode'            => self::BACKEND_MODE_SELF_HOSTED,
				'base_url'        => '',
				'validation_path' => self::BACKEND_VALIDATION_PATH_DEFAULT,
				'health_path'     => self::BACKEND_HEALTH_PATH_DEFAULT,
			),
			'secret_manager'              => array(
				'provider'          => self::SECRET_MANAGER_PROVIDER_NONE,
				'provider_mode'     => self::SECRET_MANAGER_MODE_DISABLED,
				'vault_addr'        => '',
				'vault_namespace'   => '',
				'vault_kv_mount'    => 'kv',
				'vault_path_prefix' => 'uplinkportal',
				'vault_auth_mount'  => 'approle',
				'vault_role'        => '',
			),
			'governance'                  => array(
				'policy_bundle'     => CompliancePolicyProfiles::BUNDLE_MSP_DEFAULT,
				'model_policies'    => $this->get_default_model_policy_matrix(),
				'model_legal_basis' => $this->get_default_legal_basis_map(),
				'data_minimization' => array(
					'model_allowlist'    => $this->get_default_model_allowlist_map(),
					'provider_overrides' => array_fill_keys( IntegrationModelRegistry::ids(), array() ),
					'mask_logs'          => 1,
				),
				'tenant_boundary'   => array(
					'mode'                => is_multisite() ? self::TENANT_MODE_MULTISITE_SITE_ISOLATION : self::TENANT_MODE_SINGLE_SITE,
					'enforce_site_scope'  => 1,
					'require_super_admin' => 1,
				),
			),
			'security'                    => array(
				'strict_rest_nonce'       => 1,
				'show_security_audit_log' => 1,
				'evidence_trail_enabled'  => 0,
				'monitoring'              => array(
					'mode'        => self::MONITORING_MODE_NONE,
					'webhook_url' => '',
				),
				'data_protection'         => array(
					'at_rest_status'               => self::DATA_PROTECTION_AT_REST_DISABLED,
					'database_encryption'          => self::DATA_PROTECTION_DB_NONE,
					'document_encryption'          => self::DATA_PROTECTION_DOC_PLUGIN_LOCAL,
					'kms_provider'                 => self::DATA_PROTECTION_KMS_NONE,
					'key_rotation_days'            => 90,
					'separation_of_duties'         => 0,
					'emergency_revoke_runbook_url' => '',
				),
			),
			'service_modules'             => array_fill_keys( self::SERVICE_MODULE_KEYS, 0 ),
			'validation_profiles'         => array(),
			'integration_profiles'        => array(),
			'automation_recipes'          => array(),
			'automation_recipe_revisions' => array(),
			'licensing'                   => array(
				'license_key'     => '',
				'instance_id'     => '',
				'worker_base_url' => '',
				'product_slug'    => 'uplinkportal',
				'cache_ttl'       => 300,
			),
		);

		foreach ( self::VALIDATION_SECTION_KEYS as $section ) {
			$defaults['validation_profiles'][ $section ] = array(
				'base_url'            => '',
				'allow_query_api_key' => 0,
			);
		}

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$defaults['integration_profiles'][ $model_id ] = array(
				'provider'         => 'custom',
				'auth_type'        => 'api_key',
				'scopes'           => array(),
				'endpoint_alias'   => '',
				'retention_policy' => 'default',
				'lifecycle_state'  => self::INTEGRATION_PROFILE_STATE_DRAFT,
			);
		}
		foreach ( AutomationRecipeCatalog::ids() as $recipe_id ) {
			$defaults['automation_recipes'][ $recipe_id ]          = array(
				'enabled' => 0,
				'mode'    => self::AUTOMATION_RECIPE_MODE_DRY_RUN,
			);
			$defaults['automation_recipe_revisions'][ $recipe_id ] = array();
		}

		$normalized = $defaults;

		if ( isset( $raw['schema_version'] ) && absint( $raw['schema_version'] ) >= self::SCHEMA_VERSION ) {
			$normalized['schema_version'] = self::SCHEMA_VERSION;
		}

		$normalized['portal_page_id'] = isset( $raw['portal_page_id'] ) ? absint( $raw['portal_page_id'] ) : 0;

		if ( isset( $raw['enabled_modules'] ) && is_array( $raw['enabled_modules'] ) ) {
			$normalized['enabled_modules'] = array_values( array_filter( array_map( 'sanitize_key', $raw['enabled_modules'] ) ) );
		}

		if ( isset( $raw['backend'] ) && is_array( $raw['backend'] ) ) {
			$backend_mode = isset( $raw['backend']['mode'] ) ? sanitize_key( (string) $raw['backend']['mode'] ) : self::BACKEND_MODE_SELF_HOSTED;
			if ( in_array( $backend_mode, array( self::BACKEND_MODE_MANAGED, self::BACKEND_MODE_SELF_HOSTED ), true ) ) {
				$normalized['backend']['mode'] = $backend_mode;
			}

			$normalized['backend']['base_url']        = isset( $raw['backend']['base_url'] ) ? untrailingslashit( esc_url_raw( (string) $raw['backend']['base_url'] ) ) : '';
			$normalized['backend']['validation_path'] = $this->normalize_backend_path(
				isset( $raw['backend']['validation_path'] ) ? (string) $raw['backend']['validation_path'] : self::BACKEND_VALIDATION_PATH_DEFAULT,
				self::BACKEND_VALIDATION_PATH_DEFAULT
			);
			$normalized['backend']['health_path']     = $this->normalize_backend_path(
				isset( $raw['backend']['health_path'] ) ? (string) $raw['backend']['health_path'] : self::BACKEND_HEALTH_PATH_DEFAULT,
				self::BACKEND_HEALTH_PATH_DEFAULT
			);
		}

		if ( isset( $raw['secret_manager'] ) && is_array( $raw['secret_manager'] ) ) {
			$secret_manager = $raw['secret_manager'];
			$provider_mode  = isset( $secret_manager['provider_mode'] ) ? sanitize_key( (string) $secret_manager['provider_mode'] ) : self::SECRET_MANAGER_MODE_DISABLED;
			if ( ! in_array( $provider_mode, array( self::SECRET_MANAGER_MODE_DISABLED, self::SECRET_MANAGER_MODE_EXTERNAL_API, self::SECRET_MANAGER_MODE_VAULT ), true ) ) {
				$provider_mode = self::SECRET_MANAGER_MODE_DISABLED;
			}
			$normalized['secret_manager']['provider_mode'] = $provider_mode;

			$provider = isset( $secret_manager['provider'] ) ? sanitize_key( (string) $secret_manager['provider'] ) : self::SECRET_MANAGER_PROVIDER_NONE;
			if ( self::SECRET_MANAGER_MODE_VAULT === $provider_mode ) {
				$provider = self::SECRET_MANAGER_PROVIDER_VAULT;
			} elseif ( self::SECRET_MANAGER_MODE_DISABLED === $provider_mode || self::SECRET_MANAGER_MODE_EXTERNAL_API === $provider_mode ) {
				$provider = self::SECRET_MANAGER_PROVIDER_NONE;
			}
			if ( in_array( $provider, array( self::SECRET_MANAGER_PROVIDER_NONE, self::SECRET_MANAGER_PROVIDER_VAULT ), true ) ) {
				$normalized['secret_manager']['provider'] = $provider;
			}

			$normalized['secret_manager']['vault_addr']      = isset( $secret_manager['vault_addr'] ) ? untrailingslashit( esc_url_raw( (string) $secret_manager['vault_addr'] ) ) : '';
			$normalized['secret_manager']['vault_namespace'] = isset( $secret_manager['vault_namespace'] ) ? sanitize_text_field( (string) $secret_manager['vault_namespace'] ) : '';

			$vault_mount                                    = isset( $secret_manager['vault_kv_mount'] ) ? sanitize_key( (string) $secret_manager['vault_kv_mount'] ) : 'kv';
			$normalized['secret_manager']['vault_kv_mount'] = '' !== $vault_mount ? $vault_mount : 'kv';

			$vault_prefix                                      = isset( $secret_manager['vault_path_prefix'] ) ? sanitize_text_field( (string) $secret_manager['vault_path_prefix'] ) : 'uplinkportal';
			$normalized['secret_manager']['vault_path_prefix'] = '' !== $vault_prefix ? $vault_prefix : 'uplinkportal';

			$vault_auth_mount                                 = isset( $secret_manager['vault_auth_mount'] ) ? sanitize_key( (string) $secret_manager['vault_auth_mount'] ) : 'approle';
			$normalized['secret_manager']['vault_auth_mount'] = '' !== $vault_auth_mount ? $vault_auth_mount : 'approle';
			$normalized['secret_manager']['vault_role']       = isset( $secret_manager['vault_role'] ) ? sanitize_text_field( (string) $secret_manager['vault_role'] ) : '';

			if ( isset( $secret_manager['vault_connection_ref'] ) && ! $this->secrets_store->has_vault_connection_reference() ) {
				$vault_reference = sanitize_text_field( (string) $secret_manager['vault_connection_ref'] );
				if ( '' !== $vault_reference ) {
					$this->secrets_store->save_vault_connection_reference( $vault_reference );
				}
			}
		}

		$policy_bundle = CompliancePolicyProfiles::BUNDLE_MSP_DEFAULT;
		if ( isset( $raw['governance'] ) && is_array( $raw['governance'] ) && isset( $raw['governance']['policy_bundle'] ) ) {
			$policy_bundle = sanitize_key( (string) $raw['governance']['policy_bundle'] );
		}
		$normalized['governance']['policy_bundle'] = $this->normalize_policy_bundle( $policy_bundle );

		$model_policies = $this->get_default_model_policy_matrix();
		if ( isset( $raw['governance'] ) && is_array( $raw['governance'] ) && isset( $raw['governance']['model_policies'] ) && is_array( $raw['governance']['model_policies'] ) ) {
			$stored_matrix = $raw['governance']['model_policies'];
			foreach ( IntegrationModelRegistry::ids() as $model_id ) {
				if ( ! isset( $stored_matrix[ $model_id ] ) || ! is_array( $stored_matrix[ $model_id ] ) ) {
					continue;
				}
				foreach ( self::MODEL_POLICY_ACTIONS as $action ) {
					if ( ! isset( $stored_matrix[ $model_id ][ $action ] ) ) {
						continue;
					}
					$model_policies[ $model_id ][ $action ] = $this->normalize_model_policy_capability( (string) $stored_matrix[ $model_id ][ $action ] );
				}
			}
		}
		$normalized['governance']['model_policies'] = $model_policies;

		$model_legal_basis = $this->get_default_legal_basis_map();
		if ( isset( $raw['governance'] ) && is_array( $raw['governance'] ) && isset( $raw['governance']['model_legal_basis'] ) && is_array( $raw['governance']['model_legal_basis'] ) ) {
			$stored_legal_basis = $raw['governance']['model_legal_basis'];
			foreach ( IntegrationModelRegistry::ids() as $model_id ) {
				if ( ! isset( $stored_legal_basis[ $model_id ] ) ) {
					continue;
				}
				$model_legal_basis[ $model_id ] = $this->normalize_legal_basis( (string) $stored_legal_basis[ $model_id ] );
			}
		}
		$normalized['governance']['model_legal_basis'] = $model_legal_basis;

		$model_allowlist    = $this->get_default_model_allowlist_map();
		$provider_overrides = array_fill_keys( IntegrationModelRegistry::ids(), array() );
		$mask_logs          = 1;

		if ( isset( $raw['governance'] ) && is_array( $raw['governance'] ) && isset( $raw['governance']['data_minimization'] ) && is_array( $raw['governance']['data_minimization'] ) ) {
			$stored_minimization = $raw['governance']['data_minimization'];
			if ( isset( $stored_minimization['model_allowlist'] ) && is_array( $stored_minimization['model_allowlist'] ) ) {
				foreach ( IntegrationModelRegistry::ids() as $model_id ) {
					if ( ! isset( $stored_minimization['model_allowlist'][ $model_id ] ) || ! is_array( $stored_minimization['model_allowlist'][ $model_id ] ) ) {
						continue;
					}
					$model_allowlist[ $model_id ] = $this->sanitize_allowlist_fields( $stored_minimization['model_allowlist'][ $model_id ] );
				}
			}

			if ( isset( $stored_minimization['provider_overrides'] ) && is_array( $stored_minimization['provider_overrides'] ) ) {
				foreach ( IntegrationModelRegistry::ids() as $model_id ) {
					if ( ! isset( $stored_minimization['provider_overrides'][ $model_id ] ) || ! is_array( $stored_minimization['provider_overrides'][ $model_id ] ) ) {
						continue;
					}
					foreach ( $stored_minimization['provider_overrides'][ $model_id ] as $provider_slug => $fields ) {
						$provider_slug = sanitize_key( (string) $provider_slug );
						if ( '' === $provider_slug || ! is_array( $fields ) ) {
							continue;
						}
						$provider_overrides[ $model_id ][ $provider_slug ] = $this->sanitize_allowlist_fields( $fields );
					}
				}
			}

			if ( isset( $stored_minimization['mask_logs'] ) ) {
				$mask_logs = ! empty( $stored_minimization['mask_logs'] ) ? 1 : 0;
			}
		}

		$normalized['governance']['data_minimization'] = array(
			'model_allowlist'    => $model_allowlist,
			'provider_overrides' => $provider_overrides,
			'mask_logs'          => $mask_logs,
		);

		$tenant_boundary = array(
			'mode'                => is_multisite() ? self::TENANT_MODE_MULTISITE_SITE_ISOLATION : self::TENANT_MODE_SINGLE_SITE,
			'enforce_site_scope'  => 1,
			'require_super_admin' => 1,
		);
		if ( isset( $raw['governance'] ) && is_array( $raw['governance'] ) && isset( $raw['governance']['tenant_boundary'] ) && is_array( $raw['governance']['tenant_boundary'] ) ) {
			$stored_tenant = $raw['governance']['tenant_boundary'];
			if ( isset( $stored_tenant['mode'] ) ) {
				$tenant_boundary['mode'] = $this->normalize_tenant_mode( (string) $stored_tenant['mode'] );
			}
			if ( isset( $stored_tenant['enforce_site_scope'] ) ) {
				$tenant_boundary['enforce_site_scope'] = ! empty( $stored_tenant['enforce_site_scope'] ) ? 1 : 0;
			}
			if ( isset( $stored_tenant['require_super_admin'] ) ) {
				$tenant_boundary['require_super_admin'] = ! empty( $stored_tenant['require_super_admin'] ) ? 1 : 0;
			}
		}
		$normalized['governance']['tenant_boundary'] = $tenant_boundary;

		if ( isset( $raw['security'] ) && is_array( $raw['security'] ) ) {
			if ( isset( $raw['security']['strict_rest_nonce'] ) ) {
				$normalized['security']['strict_rest_nonce'] = ! empty( $raw['security']['strict_rest_nonce'] ) ? 1 : 0;
			}
			if ( isset( $raw['security']['show_security_audit_log'] ) ) {
				$normalized['security']['show_security_audit_log'] = ! empty( $raw['security']['show_security_audit_log'] ) ? 1 : 0;
			}
			if ( isset( $raw['security']['evidence_trail_enabled'] ) ) {
				$normalized['security']['evidence_trail_enabled'] = ! empty( $raw['security']['evidence_trail_enabled'] ) ? 1 : 0;
			}
			if ( isset( $raw['security']['monitoring'] ) && is_array( $raw['security']['monitoring'] ) ) {
				$stored_monitoring = $raw['security']['monitoring'];
				$mode              = isset( $stored_monitoring['mode'] ) ? sanitize_key( (string) $stored_monitoring['mode'] ) : self::MONITORING_MODE_NONE;
				$normalized['security']['monitoring']['mode']        = $this->normalize_monitoring_mode( $mode );
				$normalized['security']['monitoring']['webhook_url'] = isset( $stored_monitoring['webhook_url'] ) ? esc_url_raw( (string) $stored_monitoring['webhook_url'] ) : '';
			}
			if ( isset( $raw['security']['data_protection'] ) && is_array( $raw['security']['data_protection'] ) ) {
				$stored_protection = $raw['security']['data_protection'];
				$at_rest_status    = isset( $stored_protection['at_rest_status'] )
				? sanitize_key( (string) $stored_protection['at_rest_status'] )
				: self::DATA_PROTECTION_AT_REST_DISABLED;
				$db_mode           = isset( $stored_protection['database_encryption'] )
				? sanitize_key( (string) $stored_protection['database_encryption'] )
				: self::DATA_PROTECTION_DB_NONE;
				$doc_mode          = isset( $stored_protection['document_encryption'] )
				? sanitize_key( (string) $stored_protection['document_encryption'] )
				: self::DATA_PROTECTION_DOC_PLUGIN_LOCAL;
				$kms_provider      = isset( $stored_protection['kms_provider'] )
				? sanitize_key( (string) $stored_protection['kms_provider'] )
				: self::DATA_PROTECTION_KMS_NONE;

				$normalized['security']['data_protection'] = array(
					'at_rest_status'               => $this->normalize_at_rest_status( $at_rest_status ),
					'database_encryption'          => $this->normalize_database_encryption_mode( $db_mode ),
					'document_encryption'          => $this->normalize_document_encryption_mode( $doc_mode ),
					'kms_provider'                 => $this->normalize_kms_provider( $kms_provider ),
					'key_rotation_days'            => isset( $stored_protection['key_rotation_days'] ) ? $this->normalize_key_rotation_days( absint( $stored_protection['key_rotation_days'] ) ) : 90,
					'separation_of_duties'         => isset( $stored_protection['separation_of_duties'] ) && ! empty( $stored_protection['separation_of_duties'] ) ? 1 : 0,
					'emergency_revoke_runbook_url' => isset( $stored_protection['emergency_revoke_runbook_url'] ) ? esc_url_raw( (string) $stored_protection['emergency_revoke_runbook_url'] ) : '',
				);
			}
		}

		if ( isset( $raw['service_modules'] ) && is_array( $raw['service_modules'] ) ) {
			foreach ( self::SERVICE_MODULE_KEYS as $module_key ) {
				$normalized['service_modules'][ $module_key ] = ! empty( $raw['service_modules'][ $module_key ] ) ? 1 : 0;
			}
		}
		foreach ( self::REQUIRED_SERVICE_MODULE_KEYS as $required_module ) {
			$normalized['service_modules'][ $required_module ] = 1;
		}

		if ( isset( $raw['licensing'] ) && is_array( $raw['licensing'] ) ) {
			$normalized['licensing']['license_key']     = isset( $raw['licensing']['license_key'] ) ? sanitize_text_field( (string) $raw['licensing']['license_key'] ) : '';
			$normalized['licensing']['instance_id']     = isset( $raw['licensing']['instance_id'] ) ? sanitize_text_field( (string) $raw['licensing']['instance_id'] ) : '';
			$normalized['licensing']['worker_base_url'] = isset( $raw['licensing']['worker_base_url'] ) ? untrailingslashit( esc_url_raw( (string) $raw['licensing']['worker_base_url'] ) ) : '';
			$normalized['licensing']['product_slug']    = isset( $raw['licensing']['product_slug'] ) ? sanitize_key( (string) $raw['licensing']['product_slug'] ) : 'uplinkportal';
			$normalized['licensing']['cache_ttl']       = isset( $raw['licensing']['cache_ttl'] ) ? max( 60, absint( $raw['licensing']['cache_ttl'] ) ) : 300;
		}

		if ( isset( $raw['validation_profiles'] ) && is_array( $raw['validation_profiles'] ) ) {
			foreach ( self::VALIDATION_SECTION_KEYS as $section ) {
				$stored = isset( $raw['validation_profiles'][ $section ] ) && is_array( $raw['validation_profiles'][ $section ] )
				? $raw['validation_profiles'][ $section ]
				: array();

				$normalized['validation_profiles'][ $section ]['base_url']            = isset( $stored['base_url'] ) ? untrailingslashit( esc_url_raw( (string) $stored['base_url'] ) ) : '';
				$normalized['validation_profiles'][ $section ]['allow_query_api_key'] = ! empty( $stored['allow_query_api_key'] ) ? 1 : 0;
			}
		}

		if ( isset( $raw['integration_profiles'] ) && is_array( $raw['integration_profiles'] ) ) {
			foreach ( IntegrationModelRegistry::ids() as $model_id ) {
				$stored = isset( $raw['integration_profiles'][ $model_id ] ) && is_array( $raw['integration_profiles'][ $model_id ] )
				? $raw['integration_profiles'][ $model_id ]
				: array();

				$scopes = array();
				if ( isset( $stored['scopes'] ) && is_array( $stored['scopes'] ) ) {
					foreach ( $stored['scopes'] as $scope_value ) {
						$scope_value = sanitize_text_field( (string) $scope_value );
						if ( '' !== $scope_value ) {
							$scopes[] = $scope_value;
						}
					}
				}

				$normalized['integration_profiles'][ $model_id ] = array(
					'provider'         => isset( $stored['provider'] ) ? sanitize_key( (string) $stored['provider'] ) : 'custom',
					'auth_type'        => isset( $stored['auth_type'] ) ? sanitize_key( (string) $stored['auth_type'] ) : 'api_key',
					'scopes'           => $scopes,
					'endpoint_alias'   => isset( $stored['endpoint_alias'] ) ? sanitize_text_field( (string) $stored['endpoint_alias'] ) : '',
					'retention_policy' => isset( $stored['retention_policy'] ) ? sanitize_key( (string) $stored['retention_policy'] ) : 'default',
					'lifecycle_state'  => $this->normalize_profile_lifecycle_state(
						isset( $stored['lifecycle_state'] )
						? (string) $stored['lifecycle_state']
						: self::INTEGRATION_PROFILE_STATE_DRAFT
					),
				);
			}
		}

		if ( isset( $raw['automation_recipes'] ) && is_array( $raw['automation_recipes'] ) ) {
			foreach ( AutomationRecipeCatalog::ids() as $recipe_id ) {
				$stored = isset( $raw['automation_recipes'][ $recipe_id ] ) && is_array( $raw['automation_recipes'][ $recipe_id ] )
				? $raw['automation_recipes'][ $recipe_id ]
				: array();

				$normalized['automation_recipes'][ $recipe_id ] = array(
					'enabled' => isset( $stored['enabled'] ) && ! empty( $stored['enabled'] ) ? 1 : 0,
					'mode'    => isset( $stored['mode'] )
					? $this->normalize_automation_recipe_mode( (string) $stored['mode'] )
					: self::AUTOMATION_RECIPE_MODE_DRY_RUN,
				);
			}
		}
		if ( isset( $raw['automation_recipe_revisions'] ) && is_array( $raw['automation_recipe_revisions'] ) ) {
			foreach ( AutomationRecipeCatalog::ids() as $recipe_id ) {
				$entries = isset( $raw['automation_recipe_revisions'][ $recipe_id ] ) && is_array( $raw['automation_recipe_revisions'][ $recipe_id ] )
				? $raw['automation_recipe_revisions'][ $recipe_id ]
				: array();
				$normalized['automation_recipe_revisions'][ $recipe_id ] = $this->normalize_recipe_revision_entries( $entries );
			}
		}

		if ( '' === (string) $normalized['licensing']['product_slug'] ) {
			$normalized['licensing']['product_slug'] = 'uplinkportal';
		}

		if ( '' === (string) $normalized['licensing']['instance_id'] ) {
			$normalized['licensing']['instance_id'] = wp_generate_uuid4();
		}

		return $normalized;
	}

	/**
	 * Normalizes integration profile lifecycle state.
	 *
	 * @param string $state Raw state.
	 * @return string
	 */
	private function normalize_profile_lifecycle_state( string $state ): string {
		$state = sanitize_key( $state );

		if ( in_array( $state, array( self::INTEGRATION_PROFILE_STATE_DRAFT, self::INTEGRATION_PROFILE_STATE_REVIEWED, self::INTEGRATION_PROFILE_STATE_ACTIVE ), true ) ) {
			return $state;
		}

		return self::INTEGRATION_PROFILE_STATE_DRAFT;
	}

	/**
	 * Maps model id to provider catalog module slug.
	 *
	 * @param string $model_id Model id.
	 * @return string
	 */
	private function provider_catalog_module_slug_for_model( string $model_id ): string {
		$model_id = sanitize_key( $model_id );
		if ( IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id || IntegrationModelRegistry::MODEL_LICENSING === $model_id ) {
			return 'database';
		}

		return $model_id;
	}

	/**
	 * Normalizes automation recipe mode key.
	 *
	 * @param string $mode Mode key.
	 * @return string
	 */
	private function normalize_automation_recipe_mode( string $mode ): string {
		$mode = sanitize_key( $mode );
		if ( in_array( $mode, array( self::AUTOMATION_RECIPE_MODE_DRY_RUN, self::AUTOMATION_RECIPE_MODE_LIVE ), true ) ) {
			return $mode;
		}

		return self::AUTOMATION_RECIPE_MODE_DRY_RUN;
	}

	/**
	 * Normalizes compliance policy bundle key.
	 *
	 * @param string $bundle_key Bundle key.
	 * @return string
	 */
	private function normalize_policy_bundle( string $bundle_key ): string {
		$bundle_key = sanitize_key( $bundle_key );
		if ( CompliancePolicyProfiles::has_bundle( $bundle_key ) ) {
			return $bundle_key;
		}

		return CompliancePolicyProfiles::BUNDLE_MSP_DEFAULT;
	}

	/**
	 * Returns default model policy capability matrix.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function get_default_model_policy_matrix(): array {
		$matrix = array();

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$matrix[ $model_id ] = array();
			foreach ( self::MODEL_POLICY_ACTIONS as $action ) {
				$matrix[ $model_id ][ $action ] = 'msp_manage';
			}
		}

		return $matrix;
	}

	/**
	 * Normalizes one model policy capability key.
	 *
	 * @param string $capability Capability key.
	 * @return string
	 */
	private function normalize_model_policy_capability( string $capability ): string {
		$capability = sanitize_key( $capability );

		return '' !== $capability ? $capability : 'msp_manage';
	}

	/**
	 * Normalizes one legal basis key.
	 *
	 * @param string $basis Legal basis.
	 * @return string
	 */
	private function normalize_legal_basis( string $basis ): string {
		$basis = sanitize_key( $basis );
		if ( in_array( $basis, self::GDPR_LEGAL_BASES, true ) ) {
			return $basis;
		}

		return 'contract';
	}

	/**
	 * Returns default legal basis map by model.
	 *
	 * @return array<string,string>
	 */
	private function get_default_legal_basis_map(): array {
		return array(
			IntegrationModelRegistry::MODEL_FINANCE    => 'contract',
			IntegrationModelRegistry::MODEL_IT_SUPPORT => 'contract',
			IntegrationModelRegistry::MODEL_PROJECTS   => 'legitimate_interests',
			IntegrationModelRegistry::MODEL_LICENSING  => 'legal_obligation',
			IntegrationModelRegistry::MODEL_DOCUMENTS  => 'contract',
		);
	}

	/**
	 * Returns default model allowlist map.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function get_default_model_allowlist_map(): array {
		return array(
			IntegrationModelRegistry::MODEL_FINANCE    => array( 'customer_id', 'invoice_id', 'status', 'amount_due', 'currency' ),
			IntegrationModelRegistry::MODEL_IT_SUPPORT => array( 'ticket_id', 'priority', 'status', 'requester_id', 'assignee_id' ),
			IntegrationModelRegistry::MODEL_PROJECTS   => array( 'project_id', 'milestone_id', 'status', 'owner_id' ),
			IntegrationModelRegistry::MODEL_LICENSING  => array( 'license_key', 'instance_id', 'product_slug', 'status' ),
			IntegrationModelRegistry::MODEL_DOCUMENTS  => array( 'document_id', 'client_id', 'mime_type', 'updated_at' ),
		);
	}

	/**
	 * Parses model allowlist textarea.
	 *
	 * @param string $text Raw textarea.
	 * @return array<int,string>
	 */
	private function parse_allowlist_text( string $text ): array {
		$items = preg_split( '/[\r\n,]+/', sanitize_textarea_field( $text ) );
		if ( ! is_array( $items ) ) {
			return array();
		}

		return $this->sanitize_allowlist_fields( $items );
	}

	/**
	 * Sanitizes allowlist fields.
	 *
	 * @param array<int,mixed> $fields Field list.
	 * @return array<int,string>
	 */
	private function sanitize_allowlist_fields( array $fields ): array {
		$normalized = array();
		foreach ( $fields as $field ) {
			$field = sanitize_key( (string) $field );
			if ( '' !== $field ) {
				$normalized[] = $field;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Parses provider override textarea.
	 *
	 * Format:
	 * provider_slug: field_a, field_b
	 *
	 * @param string $text Raw textarea.
	 * @return array<string,array<int,string>>
	 */
	private function parse_provider_override_text( string $text ): array {
		$lines     = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( $text ) );
		$overrides = array();
		if ( ! is_array( $lines ) ) {
			return $overrides;
		}

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}

			$parts        = explode( ':', $line, 2 );
			$provider_key = sanitize_key( (string) $parts[0] );
			$field_text   = isset( $parts[1] ) ? (string) $parts[1] : '';
			if ( '' === $provider_key ) {
				continue;
			}

			$overrides[ $provider_key ] = $this->parse_allowlist_text( $field_text );
		}

		return $overrides;
	}

	/**
	 * Sanitizes one automation recipe profile state.
	 *
	 * @param array<string,mixed> $state Raw state payload.
	 * @return array<string,mixed>
	 */
	private function sanitize_recipe_profile_state( array $state ): array {
		$mode = isset( $state['mode'] )
			? $this->normalize_automation_recipe_mode( (string) $state['mode'] )
			: self::AUTOMATION_RECIPE_MODE_DRY_RUN;

		return array(
			'enabled' => isset( $state['enabled'] ) && ! empty( $state['enabled'] ) ? 1 : 0,
			'mode'    => $mode,
		);
	}

	/**
	 * Normalizes stored recipe revision entries.
	 *
	 * @param array<int,mixed> $entries Raw entries.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_recipe_revision_entries( array $entries ): array {
		$normalized = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$state = isset( $entry['state'] ) && is_array( $entry['state'] )
				? $this->sanitize_recipe_profile_state( $entry['state'] )
				: array(
					'enabled' => 0,
					'mode'    => self::AUTOMATION_RECIPE_MODE_DRY_RUN,
				);

			$revision_id = isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '';
			if ( '' === $revision_id ) {
				$revision_id = wp_generate_uuid4();
			}

			$normalized[] = array(
				'id'      => $revision_id,
				'time'    => isset( $entry['time'] ) ? sanitize_text_field( (string) $entry['time'] ) : gmdate( 'c' ),
				'user_id' => isset( $entry['user_id'] ) ? absint( $entry['user_id'] ) : 0,
				'state'   => $state,
			);
		}

		return $normalized;
	}

	/**
	 * Appends one revision entry and trims retention length.
	 *
	 * @param array<int,array<string,mixed>> $revisions Existing revisions.
	 * @param array<string,mixed>            $state Snapshot state.
	 * @return array<int,array<string,mixed>>
	 */
	private function append_recipe_revision( array $revisions, array $state ): array {
		$revisions[] = array(
			'id'      => wp_generate_uuid4(),
			'time'    => gmdate( 'c' ),
			'user_id' => get_current_user_id(),
			'state'   => $this->sanitize_recipe_profile_state( $state ),
		);

		$limit = max( 5, absint( apply_filters( 'uplinkportal_recipe_revision_limit', 20 ) ) );
		if ( count( $revisions ) > $limit ) {
			$revisions = array_slice( $revisions, -1 * $limit );
		}

		return $revisions;
	}

	/**
	 * Normalizes at-rest status key.
	 *
	 * @param string $status At-rest status.
	 * @return string
	 */
	private function normalize_at_rest_status( string $status ): string {
		$status = sanitize_key( $status );
		if ( in_array( $status, array( self::DATA_PROTECTION_AT_REST_DISABLED, 'planned', 'enforced' ), true ) ) {
			return $status;
		}

		return self::DATA_PROTECTION_AT_REST_DISABLED;
	}

	/**
	 * Normalizes database encryption mode key.
	 *
	 * @param string $mode Database encryption mode.
	 * @return string
	 */
	private function normalize_database_encryption_mode( string $mode ): string {
		$mode = sanitize_key( $mode );
		if ( in_array( $mode, array( self::DATA_PROTECTION_DB_NONE, self::DATA_PROTECTION_DB_PROVIDER_TDE, self::DATA_PROTECTION_DB_KMS, self::DATA_PROTECTION_DB_SELF_HOSTED ), true ) ) {
			return $mode;
		}

		return self::DATA_PROTECTION_DB_NONE;
	}

	/**
	 * Normalizes document encryption mode key.
	 *
	 * @param string $mode Document encryption mode.
	 * @return string
	 */
	private function normalize_document_encryption_mode( string $mode ): string {
		$mode = sanitize_key( $mode );
		if ( in_array( $mode, array( self::DATA_PROTECTION_DOC_PLUGIN_LOCAL, self::DATA_PROTECTION_DOC_SSE_KMS, self::DATA_PROTECTION_DOC_BACKEND ), true ) ) {
			return $mode;
		}

		return self::DATA_PROTECTION_DOC_PLUGIN_LOCAL;
	}

	/**
	 * Normalizes KMS provider key.
	 *
	 * @param string $provider KMS provider.
	 * @return string
	 */
	private function normalize_kms_provider( string $provider ): string {
		$provider = sanitize_key( $provider );
		if ( in_array( $provider, array( self::DATA_PROTECTION_KMS_NONE, self::DATA_PROTECTION_KMS_VAULT_TRANSIT, self::DATA_PROTECTION_KMS_AWS, self::DATA_PROTECTION_KMS_AZURE, self::DATA_PROTECTION_KMS_GCP, self::DATA_PROTECTION_KMS_SELF_HOSTED ), true ) ) {
			return $provider;
		}

		return self::DATA_PROTECTION_KMS_NONE;
	}

	/**
	 * Normalizes key rotation interval in days.
	 *
	 * @param int $days Rotation days.
	 * @return int
	 */
	private function normalize_key_rotation_days( int $days ): int {
		$days = absint( $days );
		if ( $days < 30 ) {
			return 30;
		}
		if ( $days > 3650 ) {
			return 3650;
		}

		return $days;
	}

	/**
	 * Normalizes monitoring mode.
	 *
	 * @param string $mode Monitoring mode.
	 * @return string
	 */
	private function normalize_monitoring_mode( string $mode ): string {
		$mode = sanitize_key( $mode );
		if ( in_array( $mode, array( self::MONITORING_MODE_NONE, self::MONITORING_MODE_GENERIC_WEBHOOK, self::MONITORING_MODE_HOSTED_SUBSCRIPTION ), true ) ) {
			return $mode;
		}

		return self::MONITORING_MODE_NONE;
	}

	/**
	 * Normalizes tenant boundary mode.
	 *
	 * @param string $mode Mode key.
	 * @return string
	 */
	private function normalize_tenant_mode( string $mode ): string {
		$mode = sanitize_key( $mode );

		if ( ! is_multisite() ) {
			return self::TENANT_MODE_SINGLE_SITE;
		}

		if ( in_array( $mode, array( self::TENANT_MODE_SINGLE_SITE, self::TENANT_MODE_MULTISITE_SITE_ISOLATION, self::TENANT_MODE_MULTISITE_NETWORK_SHARED ), true ) ) {
			return $mode;
		}

		return self::TENANT_MODE_MULTISITE_SITE_ISOLATION;
	}
}
