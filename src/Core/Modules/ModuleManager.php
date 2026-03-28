<?php
/**
 * Module manager.
 *
 * @package UplinkPortal\Core\Modules
 */

namespace UplinkPortal\Core\Modules;

use UplinkPortal\Core\Support\IntegrationModelRegistry;
use UplinkPortal\Core\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Loads and registers modules from filter hooks.
 */
class ModuleManager {
	/**
	 * Default module capability map.
	 *
	 * @var array<string,array<string,string>>
	 */
	private const MODULE_ACTION_CAPABILITIES = array(
		'finance'      => array(
			'read' => 'client_view_billing',
		),
		'it_support'   => array(
			'read' => 'client_view_tickets',
		),
		'projects'     => array(
			'read' => 'client_view_subscriptions',
		),
		'custom_dummy' => array(
			'read' => 'client_access',
		),
	);

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Registered modules.
	 *
	 * @var array<string,ModuleInterface>
	 */
	private array $modules = array();

	/**
	 * Per-request in-memory cache for capability lookups.
	 *
	 * Keyed by "$user_id:$module_id:$action". Reset between requests automatically.
	 * Use reset_request_cache() in tests.
	 *
	 * @var array<string,bool>
	 */
	private static array $request_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Options $options Options helper.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Registers defaults and applies external module filter.
	 *
	 * @return void
	 */
	public function register_default_modules(): void {
		$filtered_modules = apply_filters( 'uplinkportal_modules', array() );
		$custom_modules   = apply_filters( 'uplinkportal_custom_modules', array() );

		if ( ! is_array( $filtered_modules ) ) {
			$filtered_modules = array();
		}
		if ( ! is_array( $custom_modules ) ) {
			$custom_modules = array();
		}

		$all_modules = array_merge( $filtered_modules, $custom_modules );

		foreach ( $all_modules as $module ) {
			if ( $module instanceof ModuleInterface ) {
				$this->modules[ $module->id() ] = $module;
				$module->register();
			}
		}
	}

	/**
	 * Returns all known modules.
	 *
	 * @return array<string,ModuleInterface>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Returns enabled modules according to settings and module state.
	 *
	 * @return array<string,ModuleInterface>
	 */
	public function enabled(): array {
		$enabled_ids = array_values( array_filter( array_map( 'sanitize_key', $this->options->get_enabled_module_ids() ) ) );
		return array_filter(
			$this->modules,
			function ( ModuleInterface $module ) use ( $enabled_ids ): bool {
				if ( ! $module->is_enabled() ) {
					return false;
				}

				$module_id = sanitize_key( $module->id() );

				if ( $this->options->has_service_module_key( $module_id ) && ! $this->options->is_service_module_enabled( $module_id ) ) {
					return false;
				}

				if ( ! empty( $enabled_ids ) && ! $this->options->has_service_module_key( $module_id ) && ! in_array( $module_id, $enabled_ids, true ) ) {
					return false;
				}

				return (bool) apply_filters( 'uplinkportal_feature_enabled', true, $module_id );
			}
		);
	}

	/**
	 * Returns a module by id.
	 *
	 * @param string $module_id Module id.
	 * @return ModuleInterface|null
	 */
	public function get( string $module_id ): ?ModuleInterface {
		return $this->modules[ $module_id ] ?? null;
	}

	/**
	 * Returns whether current user can perform action on module.
	 *
	 * @param string $module_id Module id.
	 * @param string $action Action key.
	 * @return bool
	 */
	public function can_current_user_access_module( string $module_id, string $action = 'read' ): bool {
		return $this->can_user_access_module( get_current_user_id(), $module_id, $action );
	}

	/**
	 * Returns whether user can perform action on module.
	 *
	 * @param int    $user_id User id.
	 * @param string $module_id Module id.
	 * @param string $action Action key.
	 * @return bool
	 */
	public function can_user_access_module( int $user_id, string $module_id, string $action = 'read' ): bool {
		$user_id   = absint( $user_id );
		$module_id = sanitize_key( $module_id );
		$action    = sanitize_key( $action );
		if ( $user_id <= 0 || '' === $module_id || '' === $action ) {
			return false;
		}

		$cache_key = $user_id . ':' . $module_id . ':' . $action;
		if ( array_key_exists( $cache_key, self::$request_cache ) ) {
			return self::$request_cache[ $cache_key ];
		}

		$capability = 'msp_manage';
		$model_id   = IntegrationModelRegistry::model_for_module( $module_id );
		if ( '' !== $model_id ) {
			$policy_matrix = $this->options->get_model_policy_matrix();
			if ( isset( $policy_matrix[ $model_id ] ) && is_array( $policy_matrix[ $model_id ] ) && isset( $policy_matrix[ $model_id ][ $action ] ) ) {
				$policy_capability = sanitize_key( (string) $policy_matrix[ $model_id ][ $action ] );
				if ( '' !== $policy_capability ) {
					$capability = $policy_capability;
				}
			}
		}

		$module_capabilities = self::MODULE_ACTION_CAPABILITIES;
		if (
			'msp_manage' === $capability
			&& isset( $module_capabilities[ $module_id ] )
			&& is_array( $module_capabilities[ $module_id ] )
			&& isset( $module_capabilities[ $module_id ][ $action ] )
		) {
			$fallback_capability = sanitize_key( (string) $module_capabilities[ $module_id ][ $action ] );
			if ( '' !== $fallback_capability ) {
				$capability = $fallback_capability;
			}
		}

		$capability = apply_filters( 'uplinkportal_module_required_capability', $capability, $module_id, $action, $user_id );
		$capability = is_string( $capability ) ? sanitize_key( $capability ) : '';
		if ( '' === $capability ) {
			self::$request_cache[ $cache_key ] = false;
			return false;
		}

		$result                            = user_can( $user_id, $capability );
		self::$request_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Resets the per-request in-memory capability cache.
	 *
	 * Intended for use in tests only.
	 *
	 * @return void
	 */
	public static function reset_request_cache(): void {
		self::$request_cache = array();
	}
}
