<?php
/**
 * Service definition store.
 *
 * @package UplinkPortal\Core\Services
 */

namespace UplinkPortal\Core\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes module-level service definitions stored as JSON in wp_options.
 *
 * Each module can declare line-item services that appear in the Service Catalog
 * and the Configuration > Modules section. Core module defaults are hard-coded
 * here; the MSP company can override them via the admin UI, and custom modules
 * can inject their own services via the `uplinkportal_module_services` filter.
 */
class ServiceDefinitionStore {

	/**
	 * Option key prefix for per-module overrides.
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'uplinkportal_module_services_';

	/**
	 * Per-request resolved service definitions, keyed by module slug.
	 *
	 * Prevents repeated get_option() calls when the same module is queried
	 * more than once per request (e.g. Service Catalog + Operations Dashboard).
	 *
	 * @var array<string,array<int,array<string,string>>>
	 */
	private array $module_cache = array();

	/**
	 * Returns service definitions for a single module slug.
	 *
	 * Merges the hard-coded defaults with any MSP-saved overrides from wp_options,
	 * then passes through the `uplinkportal_module_services` filter.
	 *
	 * @param string $module_slug Module identifier (e.g. 'it_support').
	 * @return array<int,array<string,string>> Indexed list of service definitions.
	 */
	public function get_services_for_module( string $module_slug ): array {
		if ( isset( $this->module_cache[ $module_slug ] ) ) {
			return $this->module_cache[ $module_slug ];
		}

		$defaults  = $this->default_services( $module_slug );
		$overrides = get_option( self::OPTION_PREFIX . sanitize_key( $module_slug ), null );

		if ( is_array( $overrides ) && ! empty( $overrides ) ) {
			$defaults = $overrides;
		}

		/**
		 * Filters service definitions for a given module.
		 *
		 * Custom and add-on modules should hook here to declare their services.
		 *
		 * @param array<int,array<string,string>> $definitions Service definitions.
		 * @param string                          $module_slug Module identifier.
		 */
		$result                             = (array) apply_filters( 'uplinkportal_module_services', $defaults, $module_slug );
		$this->module_cache[ $module_slug ] = $result;

		return $result;
	}

	/**
	 * Returns all service definitions across a list of enabled module slugs.
	 *
	 * Each returned item includes a `module_slug` key for attribution.
	 *
	 * @param array<int,string> $enabled_module_slugs Slugs returned by ModuleManager::enabled().
	 * @return array<int,array<string,string>> Flattened list of all service definitions.
	 */
	public function get_all_enabled_services( array $enabled_module_slugs ): array {
		$all = array();

		foreach ( $enabled_module_slugs as $slug ) {
			$slug     = sanitize_key( (string) $slug );
			$services = $this->get_services_for_module( $slug );

			foreach ( $services as $service ) {
				if ( ! is_array( $service ) ) {
					continue;
				}
				$service['module_slug'] = $slug;
				$all[]                  = $service;
			}
		}

		return $all;
	}

	/**
	 * Sanitizes and saves MSP-customized service definitions for a module.
	 *
	 * Callers must verify `msp_manage` capability before invoking this method.
	 *
	 * @param string                          $module_slug  Module identifier.
	 * @param array<int,array<string,string>> $definitions  Raw definitions from form input.
	 * @return bool True on success.
	 */
	public function save_services_for_module( string $module_slug, array $definitions ): bool {
		$module_slug = sanitize_key( $module_slug );
		$sanitized   = array();

		foreach ( $definitions as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$sanitized[] = array(
				'slug'        => sanitize_key( (string) ( $item['slug'] ?? '' ) ),
				'name'        => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				'description' => sanitize_text_field( (string) ( $item['description'] ?? '' ) ),
				'icon'        => sanitize_key( (string) ( $item['icon'] ?? 'dashicons-admin-generic' ) ),
			);
		}

		unset( $this->module_cache[ $module_slug ] );

		return (bool) update_option( self::OPTION_PREFIX . $module_slug, $sanitized, false );
	}

	/**
	 * Returns hard-coded default service definitions for core module slugs.
	 *
	 * @param string $module_slug Module identifier.
	 * @return array<int,array<string,string>> Default service definitions, or empty array for unknown slugs.
	 */
	private function default_services( string $module_slug ): array {
		$map = array(
			'finance'    => array(
				array(
					'slug'        => 'invoice_management',
					'name'        => __( 'Invoice Management', 'uplinkportal' ),
					'description' => __( 'View and manage client invoices from connected billing systems.', 'uplinkportal' ),
					'icon'        => 'dashicons-money-alt',
				),
				array(
					'slug'        => 'subscription_billing',
					'name'        => __( 'Subscription Billing', 'uplinkportal' ),
					'description' => __( 'Recurring service subscription management and payment tracking.', 'uplinkportal' ),
					'icon'        => 'dashicons-update',
				),
			),
			'it_support' => array(
				array(
					'slug'        => 'help_desk_support',
					'name'        => __( 'Help Desk Support', 'uplinkportal' ),
					'description' => __( 'Remote support tickets and incident management via connected help desk systems.', 'uplinkportal' ),
					'icon'        => 'dashicons-sos',
				),
				array(
					'slug'        => 'remote_desktop_support',
					'name'        => __( 'Remote Desktop Support', 'uplinkportal' ),
					'description' => __( 'Secure remote access and screen sharing for on-demand technical support.', 'uplinkportal' ),
					'icon'        => 'dashicons-desktop',
				),
				array(
					'slug'        => 'onsite_support',
					'name'        => __( 'Onsite Support', 'uplinkportal' ),
					'description' => __( 'Scheduled onsite technician visits and hardware support services.', 'uplinkportal' ),
					'icon'        => 'dashicons-admin-users',
				),
			),
			'projects'   => array(
				array(
					'slug'        => 'project_tracking',
					'name'        => __( 'Project Tracking', 'uplinkportal' ),
					'description' => __( 'Active project status and milestone visibility for client-facing projects.', 'uplinkportal' ),
					'icon'        => 'dashicons-chart-bar',
				),
				array(
					'slug'        => 'milestone_reporting',
					'name'        => __( 'Milestone Reporting', 'uplinkportal' ),
					'description' => __( 'Structured milestone reports with completion tracking and sign-off workflows.', 'uplinkportal' ),
					'icon'        => 'dashicons-flag',
				),
			),
		);

		return isset( $map[ $module_slug ] ) ? $map[ $module_slug ] : array();
	}
}
