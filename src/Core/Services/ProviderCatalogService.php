<?php
/**
 * Provider catalog service.
 *
 * @package UplinkPortal\Core\Services
 */

namespace UplinkPortal\Core\Services;

use UplinkPortal\Core\Support\EntitlementService;
use UplinkPortal\Core\Support\IntegrationProviderTemplates;
use UplinkPortal\Core\Support\Options;
use UplinkPortal\Core\Support\TransportSecurityPolicy;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Provides static + dynamically extensible provider catalogs with entitlement-based locking.
 */
class ProviderCatalogService {
	/**
	 * Cache TTL in seconds (15 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 900;

	/**
	 * Locked-provider helper text fallback.
	 *
	 * @var string
	 */
	private const LOCKED_PROVIDER_HELPER_TEXT = "This provider requires an active add-on. Your profile will remain Draft and automations can't be enabled.";

	/**
	 * Grace period locked provider helper text.
	 *
	 * @var string
	 */
	private const GRACE_PERIOD_HELPER_TEXT = 'Add-on in grace until %s. Existing automations continue during grace; profile changes stay Draft until entitlement is active.';

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Entitlement service.
	 *
	 * @var EntitlementService|null
	 */
	private ?EntitlementService $entitlement_service = null;

	/**
	 * Constructor.
	 *
	 * @param Options                 $options Options helper.
	 * @param EntitlementService|null $entitlement_service Entitlement service.
	 */
	public function __construct( Options $options, ?EntitlementService $entitlement_service = null ) {
		$this->options             = $options;
		$this->entitlement_service = $entitlement_service;
	}

	/**
	 * Returns providers for one module slug (no client context, no entitlement locking).
	 *
	 * Combines static baseline + dynamically filtered providers.
	 * Always includes "Custom Provider" option.
	 *
	 * @param string $module_slug Module slug.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_providers_for_module( string $module_slug ): array {
		$module_slug = sanitize_key( $module_slug );
		if ( '' === $module_slug ) {
			return array();
		}

		// Start with static baseline providers.
		$providers = $this->get_baseline_providers( $module_slug );

		// Merge in any cached/fetched remote providers.
		$remote = $this->get_remote_providers( $module_slug );
		if ( ! empty( $remote ) ) {
			$providers = array_merge( $providers, $remote );
		}

		/**
		 * Filters the provider catalog for a module.
		 *
		 * Use this to dynamically add providers to the catalog.
		 *
		 * @param array<int,array<string,mixed>> $providers Provider list.
		 * @param string $module_slug Module slug.
		 */
		$providers = apply_filters( 'uplinkportal_provider_catalog', $providers, $module_slug );

		/**
		 * Filters the provider catalog for a specific module.
		 *
		 * @param array<int,array<string,mixed>> $providers Provider list.
		 */
		$providers = apply_filters( "uplinkportal_provider_catalog_module_{$module_slug}", $providers );

		// Ensure "Custom Provider" is always included.
		$providers = $this->ensure_custom_provider( $providers, $module_slug );

		usort(
			$providers,
			static function ( array $left, array $right ): int {
				$left_sort  = isset( $left['sort'] ) ? absint( $left['sort'] ) : 9999;
				$right_sort = isset( $right['sort'] ) ? absint( $right['sort'] ) : 9999;
				if ( $left_sort === $right_sort ) {
					$left_label  = isset( $left['label'] ) ? sanitize_text_field( (string) $left['label'] ) : '';
					$right_label = isset( $right['label'] ) ? sanitize_text_field( (string) $right['label'] ) : '';
					return strcasecmp( $left_label, $right_label );
				}

				return $left_sort <=> $right_sort;
			}
		);

		return is_array( $providers ) ? array_values( $providers ) : array();
	}

	/**
	 * Returns providers for a client with entitlement-based locking overlay.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_providers_for_client( int $client_id, string $module_slug ): array {
		$providers = $this->get_providers_for_module( $module_slug );

		if ( null === $this->entitlement_service ) {
			return $providers;
		}

		$client_id = absint( $client_id );
		if ( $client_id <= 0 ) {
			return $providers;
		}

		// Overlay entitlement-based locking.
		foreach ( $providers as $index => $provider ) {
			$provider_id = isset( $provider['id'] ) ? sanitize_text_field( (string) $provider['id'] ) : '';

			// Skip custom provider (always unlocked).
			if ( 'custom' === $provider_id ) {
				continue;
			}

			$status     = $this->entitlement_service->get_status_summary( $client_id, $module_slug, $provider_id );
			$is_locked  = ! empty( $provider['locked'] ) || ! empty( $status['blocked_reason'] );
			$grace_info = array(
				'in_grace'       => ! empty( $status['in_grace'] ),
				'grace_until'    => isset( $status['grace_until'] ) ? $status['grace_until'] : null,
				'days_remaining' => isset( $status['days_remaining'] ) ? $status['days_remaining'] : null,
			);

			if ( $is_locked ) {
				$providers[ $index ]['locked']      = true;
				$providers[ $index ]['helper_text'] = self::LOCKED_PROVIDER_HELPER_TEXT;
			}

			// If in grace period, update helper text with grace expiration.
			if ( ! empty( $grace_info['in_grace'] ) && ! empty( $grace_info['grace_until'] ) ) {
				$grace_date                         = gmdate( 'M j, Y', strtotime( (string) $grace_info['grace_until'] ) );
				$providers[ $index ]['helper_text'] = sprintf( self::GRACE_PERIOD_HELPER_TEXT, $grace_date );
				$providers[ $index ]['grace_info']  = $grace_info;
			}
		}

		return array_values( $providers );
	}

	/**
	 * Returns whether a provider is locked for one module slug.
	 *
	 * If client_id is provided, checks entitlement-based locking.
	 * Otherwise checks static locked flag from catalog.
	 *
	 * @param string   $module_slug Module slug.
	 * @param string   $provider_id Provider id.
	 * @param int|null $client_id Optional client user ID for entitlement check.
	 * @return bool
	 */
	public function is_provider_locked( string $module_slug, string $provider_id, ?int $client_id = null ): bool {
		$module_slug = sanitize_key( $module_slug );
		$provider_id = sanitize_key( $provider_id );

		if ( '' === $module_slug || '' === $provider_id || 'custom' === $provider_id ) {
			return false;
		}

		// If client context provided, check entitlement.
		if ( null !== $client_id && null !== $this->entitlement_service ) {
			return $this->entitlement_service->is_locked_now( absint( $client_id ), $module_slug, $provider_id );
		}

		// Otherwise check static locked flag.
		$providers = $this->get_providers_for_module( $module_slug );
		foreach ( $providers as $provider ) {
			$provider_key = isset( $provider['id'] ) ? sanitize_key( (string) $provider['id'] ) : '';
			if ( $provider_id !== $provider_key ) {
				continue;
			}

			return ! empty( $provider['locked'] );
		}

		return false;
	}

	/**
	 * Returns baseline static providers for a module.
	 *
	 * @param string $module_slug Module slug.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_baseline_providers( string $module_slug ): array {
		if ( 'database' === $module_slug ) {
			return $this->database_module_providers();
		}

		$templates = IntegrationProviderTemplates::for_model( $module_slug );
		$providers = array();
		foreach ( $templates as $provider_id => $template ) {
			$provider_id = sanitize_key( (string) $provider_id );
			if ( '' === $provider_id || ! is_array( $template ) ) {
				continue;
			}

			$providers[] = $this->build_provider_from_template( $module_slug, $provider_id, $template, 100 );
		}

		return $providers;
	}

	/**
	 * Ensures "Custom Provider" is included in provider list.
	 *
	 * @param array<int,array<string,mixed>> $providers Providers.
	 * @param string                         $module_slug Module slug.
	 * @return array<int,array<string,mixed>>
	 */
	private function ensure_custom_provider( array $providers, string $module_slug ): array {
		// Check if custom provider already exists.
		foreach ( $providers as $provider ) {
			$provider_id = isset( $provider['id'] ) ? sanitize_key( (string) $provider['id'] ) : '';
			if ( 'custom' === $provider_id ) {
				return $providers;
			}
		}

		// Add custom provider.
		$providers[] = array(
			'id'          => 'custom',
			'label'       => __( 'Custom Provider', 'uplinkportal' ),
			'module'      => $module_slug,
			'locked'      => false,
			'auth_types'  => array( 'api_key', 'oauth', 'hybrid' ),
			'defaults'    => array(
				'scopes'           => array(),
				'endpoint_alias'   => '',
				'retention_policy' => 'default',
			),
			'helper_text' => __( 'Configure a custom integration provider with manual endpoint and authentication settings.', 'uplinkportal' ),
			'sort'        => 9999,
		);

		return $providers;
	}

	/**
	 * Builds one normalized provider row from a template payload.
	 *
	 * @param string              $module_slug Module slug.
	 * @param string              $provider_id Provider id.
	 * @param array<string,mixed> $template Template payload.
	 * @param int                 $sort Sort index.
	 * @return array<string,mixed>
	 */
	private function build_provider_from_template( string $module_slug, string $provider_id, array $template, int $sort ): array {
		$auth_type  = isset( $template['auth_type'] ) ? (string) $template['auth_type'] : 'api_key';
		$auth_types = $this->normalize_auth_types( array( $auth_type ) );

		$scopes = isset( $template['scopes'] ) && is_array( $template['scopes'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', $template['scopes'] ) ) )
			: array();

		return array(
			'id'          => $provider_id,
			'label'       => isset( $template['label'] ) ? sanitize_text_field( (string) $template['label'] ) : $provider_id,
			'module'      => $module_slug,
			'locked'      => false,
			'auth_types'  => ! empty( $auth_types ) ? $auth_types : array( 'api_key' ),
			'defaults'    => array(
				'scopes'           => $scopes,
				'endpoint_alias'   => isset( $template['endpoint_alias'] ) ? sanitize_text_field( (string) $template['endpoint_alias'] ) : '',
				'retention_policy' => isset( $template['retention_policy'] ) ? sanitize_key( (string) $template['retention_policy'] ) : 'default',
			),
			'helper_text' => null,
			'sort'        => $sort,
		);
	}

	/**
	 * Returns database module providers spanning profiles.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function database_module_providers(): array {
		$providers = array();

		$licensing_templates = IntegrationProviderTemplates::for_model( 'licensing' );
		foreach ( $licensing_templates as $provider_id => $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}
			$provider_id         = sanitize_key( (string) $provider_id );
			$provider            = $this->build_provider_from_template( 'database', $provider_id, $template, 100 );
			$provider['profile'] = 'licensing';
			$providers[]         = $provider;
		}

		$documents_templates = IntegrationProviderTemplates::for_model( 'documents' );
		foreach ( $documents_templates as $provider_id => $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}
			$provider_id         = sanitize_key( (string) $provider_id );
			$provider            = $this->build_provider_from_template( 'database', $provider_id, $template, 200 );
			$provider['profile'] = 'file_storage';
			$providers[]         = $provider;
		}

		$providers[] = array(
			'id'          => 'secrets_disabled',
			'label'       => __( 'Disabled', 'uplinkportal' ),
			'module'      => 'database',
			'profile'     => 'secrets',
			'locked'      => false,
			'auth_types'  => array( 'api_key' ),
			'defaults'    => array(
				'scopes'         => array(),
				'endpoint_alias' => '',
			),
			'helper_text' => null,
			'sort'        => 300,
		);
		$providers[] = array(
			'id'          => 'external_api_secrets',
			'label'       => __( 'External API Secrets', 'uplinkportal' ),
			'module'      => 'database',
			'profile'     => 'secrets',
			'locked'      => false,
			'auth_types'  => array( 'api_key' ),
			'defaults'    => array(
				'scopes'         => array(),
				'endpoint_alias' => '',
			),
			'helper_text' => null,
			'sort'        => 310,
		);
		$providers[] = array(
			'id'          => 'vault',
			'label'       => __( 'HashiCorp Vault', 'uplinkportal' ),
			'module'      => 'database',
			'profile'     => 'secrets',
			'locked'      => false,
			'auth_types'  => array( 'api_key' ),
			'defaults'    => array(
				'scopes'         => array(),
				'endpoint_alias' => '',
			),
			'helper_text' => null,
			'sort'        => 320,
		);

		return $providers;
	}

	/**
	 * Fetches remote provider catalog.
	 *
	 * @param string $module_slug Module slug.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function fetch_remote_catalog( string $module_slug ) {
		$base_url      = $this->options->get_licensing_worker_base_url();
		$service_token = $this->options->get_backend_service_token();

		if ( '' === $base_url || '' === $service_token ) {
			return new WP_Error(
				'uplinkportal_provider_catalog_configuration_missing',
				__( 'Provider catalog endpoint or token is missing.', 'uplinkportal' )
			);
		}

		$transport_check = TransportSecurityPolicy::validate_url( $base_url, 'licensing_worker' );
		if ( is_wp_error( $transport_check ) ) {
			return $transport_check;
		}

		$request_url = add_query_arg(
			array(
				'module' => $module_slug,
			),
			$base_url . '/providers/catalog'
		);

		$response = wp_remote_get(
			$request_url,
			TransportSecurityPolicy::secure_remote_args(
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $service_token,
					),
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'uplinkportal_provider_catalog_http_error',
				__( 'Provider catalog request failed.', 'uplinkportal' )
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'uplinkportal_provider_catalog_invalid_json',
				__( 'Provider catalog response was not valid JSON.', 'uplinkportal' )
			);
		}

		$raw_providers = array();
		if ( isset( $decoded['providers'] ) && is_array( $decoded['providers'] ) ) {
			$raw_providers = $decoded['providers'];
		} elseif ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			$raw_providers = $decoded['data'];
		} elseif ( array_is_list( $decoded ) ) {
			$raw_providers = $decoded;
		}

		$normalized = $this->normalize_remote_providers( $raw_providers );
		if ( empty( $normalized ) ) {
			return new WP_Error(
				'uplinkportal_provider_catalog_invalid_shape',
				__( 'Provider catalog response did not include valid providers.', 'uplinkportal' )
			);
		}

		return $normalized;
	}

	/**
	 * Normalizes remote providers into UI payload shape.
	 *
	 * @param array<int,mixed> $raw_providers Raw providers.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_remote_providers( array $raw_providers ): array {
		$providers = array();

		foreach ( $raw_providers as $provider ) {
			if ( ! is_array( $provider ) ) {
				continue;
			}

			$provider_id = isset( $provider['id'] ) ? sanitize_key( (string) $provider['id'] ) : '';
			if ( '' === $provider_id && isset( $provider['slug'] ) ) {
				$provider_id = sanitize_key( (string) $provider['slug'] );
			}

			$label = isset( $provider['name'] ) ? sanitize_text_field( (string) $provider['name'] ) : '';
			if ( '' === $label && isset( $provider['label'] ) ) {
				$label = sanitize_text_field( (string) $provider['label'] );
			}

			$auth_types = array();
			if ( isset( $provider['auth_types'] ) && is_array( $provider['auth_types'] ) ) {
				$auth_types = $this->normalize_auth_types( $provider['auth_types'] );
			} elseif ( isset( $provider['auth_type'] ) ) {
				$auth_types = $this->normalize_auth_types( array( (string) $provider['auth_type'] ) );
			}

			if ( '' === $provider_id || '' === $label || empty( $auth_types ) ) {
				continue;
			}

			$defaults_source = isset( $provider['defaults'] ) && is_array( $provider['defaults'] ) ? $provider['defaults'] : $provider;
			$scopes_raw      = isset( $defaults_source['scopes'] ) && is_array( $defaults_source['scopes'] ) ? $defaults_source['scopes'] : array();
			$defaults        = array(
				'scopes'           => array_values( array_filter( array_map( 'sanitize_text_field', $scopes_raw ) ) ),
				'endpoint_alias'   => isset( $defaults_source['endpoint_alias'] ) ? sanitize_text_field( (string) $defaults_source['endpoint_alias'] ) : '',
				'retention_policy' => isset( $defaults_source['retention_policy'] ) ? sanitize_key( (string) $defaults_source['retention_policy'] ) : 'default',
			);

			$locked      = ! empty( $provider['locked'] );
			$helper_text = isset( $provider['helper_text'] ) ? sanitize_text_field( (string) $provider['helper_text'] ) : '';
			if ( $locked && '' === $helper_text ) {
				$helper_text = self::LOCKED_PROVIDER_HELPER_TEXT;
			}

				$providers[] = array(
					'id'          => $provider_id,
					'label'       => $label,
					'locked'      => $locked,
					'auth_types'  => $auth_types,
					'defaults'    => $defaults,
					'helper_text' => '' !== $helper_text ? $helper_text : null,
				);
		}

		return $providers;
	}

	/**
	 * Builds fallback provider payload from current hardcoded templates.
	 *
	 * @param string $module_slug Module slug.
	 * @return array<int,array<string,mixed>>
	 */
	private function fallback_templates_for_module( string $module_slug ): array {
		$templates = IntegrationProviderTemplates::for_model( $module_slug );
		$fallback  = array();

		foreach ( $templates as $provider_id => $template ) {
			$provider_id = sanitize_key( (string) $provider_id );
			if ( '' === $provider_id || ! is_array( $template ) ) {
				continue;
			}

			$raw_auth_type = isset( $template['auth_type'] ) ? (string) $template['auth_type'] : 'api_key';
			$auth_types    = $this->normalize_auth_types( array( $raw_auth_type ) );

			$scopes = isset( $template['scopes'] ) && is_array( $template['scopes'] )
				? array_values( array_filter( array_map( 'sanitize_text_field', $template['scopes'] ) ) )
				: array();

				$fallback[] = array(
					'id'          => $provider_id,
					'label'       => isset( $template['label'] ) ? sanitize_text_field( (string) $template['label'] ) : $provider_id,
					'locked'      => false,
					'auth_types'  => ! empty( $auth_types ) ? $auth_types : array( 'api_key' ),
					'defaults'    => array(
						'scopes'           => $scopes,
						'endpoint_alias'   => isset( $template['endpoint_alias'] ) ? sanitize_text_field( (string) $template['endpoint_alias'] ) : '',
						'retention_policy' => isset( $template['retention_policy'] ) ? sanitize_key( (string) $template['retention_policy'] ) : 'default',
					),
					'helper_text' => null,
				);
		}

		return $fallback;
	}

	/**
	 * Normalizes auth types.
	 *
	 * @param array<int,mixed> $raw_auth_types Raw auth types.
	 * @return array<int,string>
	 */
	private function normalize_auth_types( array $raw_auth_types ): array {
		$auth_types = array();

		foreach ( $raw_auth_types as $raw_auth_type ) {
			$normalized = $this->normalize_auth_type( (string) $raw_auth_type );
			if ( '' === $normalized ) {
				continue;
			}

			$auth_types[ $normalized ] = $normalized;
		}

		return array_values( $auth_types );
	}

	/**
	 * Normalizes auth type value to UI-friendly keys.
	 *
	 * @param string $auth_type Raw auth type.
	 * @return string
	 */
	private function normalize_auth_type( string $auth_type ): string {
		$auth_type = strtolower( sanitize_key( $auth_type ) );
		if ( '' === $auth_type ) {
			return '';
		}

		if ( 'hybrid' === $auth_type ) {
			return 'hybrid';
		}

		if ( false !== strpos( $auth_type, 'oauth' ) ) {
			return 'oauth';
		}

		return 'api_key';
	}

	/**
	 * Returns remote providers for a module slug, using a transient cache.
	 *
	 * Fetches from the backend on cache miss; stores result for CACHE_TTL seconds.
	 * Returns an empty array if the backend is unreachable or not configured.
	 *
	 * @param string $module_slug Module slug.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_remote_providers( string $module_slug ): array {
		$cache_key = $this->build_cache_key( $module_slug );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->fetch_remote_catalog( $module_slug );
		if ( is_wp_error( $result ) ) {
			return array();
		}

		$ttl = (int) apply_filters( 'uplinkportal_provider_catalog_cache_ttl', self::CACHE_TTL );
		set_transient( $cache_key, $result, $ttl );

		return $result;
	}

	/**
	 * Invalidates the transient cache for one module slug.
	 *
	 * Call this when Modules settings are saved to force a fresh fetch.
	 *
	 * @param string $module_slug Module slug.
	 * @return void
	 */
	public function invalidate_catalog_cache( string $module_slug ): void {
		delete_transient( $this->build_cache_key( sanitize_key( $module_slug ) ) );
	}

	/**
	 * Builds transient cache key.
	 *
	 * @param string $module_slug Module slug.
	 * @return string
	 */
	private function build_cache_key( string $module_slug ): string {
		return 'uplinkportal_provider_catalog_' . sanitize_key( $module_slug );
	}

	/**
	 * Builds option key for last-success payload.
	 *
	 * @param string $module_slug Module slug.
	 * @return string
	 */
	private function build_last_success_option_key( string $module_slug ): string {
		return 'uplinkportal_provider_catalog_last_success_' . sanitize_key( $module_slug );
	}
}
