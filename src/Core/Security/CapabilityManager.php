<?php
/**
 * Capability management.
 *
 * @package UplinkPortal\Core\Security
 */

namespace UplinkPortal\Core\Security;

use UplinkPortal\Core\Support\SensitiveDataPseudonymizer;

defined( 'ABSPATH' ) || exit;

/**
 * Adds and removes capabilities used by this plugin.
 */
class CapabilityManager {
	/**
	 * Stored capability map version option key.
	 *
	 * @var string
	 */
	private const ROLE_SYNC_VERSION_OPTION = 'uplinkportal_role_sync_version';


	/**
	 * Audit log option key.
	 *
	 * @var string
	 */
	private const AUDIT_LOG_OPTION = 'uplinkportal_audit_log';

	/**
	 * Maximum audit entries retained.
	 *
	 * @var int
	 */
	private const AUDIT_LOG_LIMIT = 100;

	/**
	 * Core portal access capability.
	 *
	 * @var string
	 */
	private const CAP_ACCESS = 'client_access';

	/**
	 * Core management capability.
	 *
	 * @var string
	 */
	private const CAP_MANAGE = 'msp_manage';

	/**
	 * View billing capability.
	 *
	 * @var string
	 */
	private const CAP_VIEW_BILLING = 'client_view_billing';

	/**
	 * View tickets capability.
	 *
	 * @var string
	 */
	private const CAP_VIEW_TICKETS = 'client_view_tickets';

	/**
	 * View subscriptions capability.
	 *
	 * @var string
	 */
	private const CAP_VIEW_SUBSCRIPTIONS = 'client_view_subscriptions';

	/**
	 * Create own tickets capability.
	 *
	 * @var string
	 */
	private const CAP_TICKET_CREATE_OWN = 'client_ticket_create_own';

	/**
	 * View own tickets capability.
	 *
	 * @var string
	 */
	private const CAP_TICKET_VIEW_OWN = 'client_ticket_view_own';

	/**
	 * Company knowledge base access capability.
	 *
	 * @var string
	 */
	private const CAP_KB_ACCESS = 'client_kb_access';

	/**
	 * Allows customizing all permissions including owner permissions.
	 *
	 * @var string
	 */
	private const CAP_MANAGE_OWNER_PERMISSIONS = 'msp_manage_owner_permissions';

	/**
	 * Allows customizing all non-owner permissions.
	 *
	 * @var string
	 */
	private const CAP_MANAGE_NON_OWNER_PERMISSIONS = 'msp_manage_non_owner_permissions';

	/**
	 * Allows previewing the client portal from admin menu.
	 *
	 * @var string
	 */
	private const CAP_PREVIEW_CLIENT_PORTAL = 'msp_preview_client_portal';

	/**
	 * Grants access to the Operations Dashboard page (page-level gate).
	 *
	 * @var string
	 */
	private const CAP_VIEW_OPS_DASHBOARD = 'msp_view_operations_dashboard';

	/**
	 * Grants access to the Executive view in the Operations Dashboard.
	 *
	 * @var string
	 */
	private const CAP_VIEW_EXEC_DASHBOARD = 'msp_view_executive_dashboard';

	/**
	 * Grants access to the Financial view in the Operations Dashboard.
	 *
	 * @var string
	 */
	private const CAP_VIEW_FIN_DASHBOARD = 'msp_view_financial_dashboard';

	/**
	 * Grants access to the Technician view in the Operations Dashboard.
	 *
	 * @var string
	 */
	private const CAP_VIEW_TECH_DASHBOARD = 'msp_view_technician_dashboard';

	/**
	 * Plugin capability list.
	 *
	 * @var array<int,string>
	 */
	private const CAPS = array(
		self::CAP_ACCESS,
		self::CAP_MANAGE,
		self::CAP_VIEW_BILLING,
		self::CAP_VIEW_TICKETS,
		self::CAP_VIEW_SUBSCRIPTIONS,
		self::CAP_TICKET_CREATE_OWN,
		self::CAP_TICKET_VIEW_OWN,
		self::CAP_KB_ACCESS,
		self::CAP_MANAGE_OWNER_PERMISSIONS,
		self::CAP_MANAGE_NON_OWNER_PERMISSIONS,
		self::CAP_PREVIEW_CLIENT_PORTAL,
		self::CAP_VIEW_OPS_DASHBOARD,
		self::CAP_VIEW_EXEC_DASHBOARD,
		self::CAP_VIEW_FIN_DASHBOARD,
		self::CAP_VIEW_TECH_DASHBOARD,
	);

	/**
	 * Adds default capabilities to administrators.
	 *
	 * @return void
	 */
	public static function add_default_caps(): void {
		self::sync_wordpress_administrator_caps();
		self::sync_uplinkportal_roles();
		self::mark_role_map_synced();
		self::log_event( 'roles_synced_activation', 'Roles synchronized during plugin activation.' );
	}

	/**
	 * Removes plugin capabilities on deactivation.
	 *
	 * @return void
	 */
	public static function remove_default_caps(): void {
		self::remove_caps_from_wordpress_administrator();
		self::remove_caps_from_uplinkportal_roles();
		self::log_event( 'roles_removed_deactivation', 'Roles/capabilities removed during plugin deactivation.' );
		delete_option( self::ROLE_SYNC_VERSION_OPTION );
	}

	/**
	 * Runtime placeholder for additional role registration.
	 *
	 * @return void
	 */
	public static function register_caps_for_runtime_checks(): void {
		if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( self::is_role_map_current() ) {
			return;
		}

		// Ensure custom roles and capability maps remain in sync when plugin map changes.
		self::sync_wordpress_administrator_caps();
		self::sync_uplinkportal_roles();
		self::mark_role_map_synced();
		self::log_event( 'roles_synced_runtime', 'Roles synchronized during runtime integrity check.' );
	}

	/**
	 * Runs explicit role/capability resync from admin action.
	 *
	 * @return void
	 */
	public static function manual_resync_roles(): void {
		self::sync_wordpress_administrator_caps();
		self::sync_uplinkportal_roles();
		self::mark_role_map_synced();
		self::log_event( 'roles_synced_manual', 'Roles synchronized from manual admin action.' );
	}

	/**
	 * Capability list helper.
	 *
	 * @return array<int,string>
	 */
	public static function capabilities(): array {
		return self::CAPS;
	}


	/**
	 * Returns recent audit log entries.
	 *
	 * @param int $limit Max entries.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent_audit_log( int $limit = 20 ): array {
		$log = get_option( self::AUDIT_LOG_OPTION, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_slice( $log, 0, max( 1, $limit ) );
	}

	/**
	 * Purges audit entries older than N days.
	 *
	 * @param int $days Retention days.
	 * @return int
	 */
	public static function purge_audit_log_older_than( int $days ): int {
		$days = max( 1, absint( $days ) );
		$log  = get_option( self::AUDIT_LOG_OPTION, array() );
		if ( ! is_array( $log ) || empty( $log ) ) {
			return 0;
		}

		$cutoff = time() - ( $days * DAY_IN_SECONDS );
		$kept   = array();

		foreach ( $log as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$entry_time = isset( $entry['time'] ) ? strtotime( (string) $entry['time'] ) : false;
			if ( false === $entry_time || $entry_time >= $cutoff ) {
				$kept[] = $entry;
			}
		}

		$removed = count( $log ) - count( $kept );
		if ( $removed > 0 ) {
			update_option( self::AUDIT_LOG_OPTION, array_slice( $kept, 0, self::AUDIT_LOG_LIMIT ), false );
		}

		return max( 0, $removed );
	}

	/**
	 * Syncs plugin capabilities to WordPress administrator.
	 *
	 * @return void
	 */
	private static function sync_wordpress_administrator_caps(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::CAPS as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Removes plugin capabilities from WordPress administrator.
	 *
	 * @return void
	 */
	private static function remove_caps_from_wordpress_administrator(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::CAPS as $capability ) {
			$role->remove_cap( $capability );
		}
	}

	/**
	 * Creates or updates all UplinkPortal-specific roles.
	 *
	 * @return void
	 */
	private static function sync_uplinkportal_roles(): void {
		foreach ( self::role_capability_map() as $role_slug => $role_data ) {
			self::upsert_role( $role_slug, $role_data['label'], $role_data['caps'] );
		}
	}

	/**
	 * Removes plugin capabilities from UplinkPortal-specific roles.
	 *
	 * @return void
	 */
	private static function remove_caps_from_uplinkportal_roles(): void {
		foreach ( array_keys( self::role_capability_map() ) as $role_slug ) {
			$role = get_role( $role_slug );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::CAPS as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}

	/**
	 * Creates role when missing and enforces exact plugin capability set.
	 *
	 * @param string            $role_slug Role slug.
	 * @param string            $role_label Role label.
	 * @param array<int,string> $caps Role capabilities.
	 * @return void
	 */
	private static function upsert_role( string $role_slug, string $role_label, array $caps ): void {
		$role = get_role( $role_slug );

		if ( ! $role ) {
			$role = add_role(
				$role_slug,
				$role_label,
				array(
					'read' => true,
				)
			);
		}

		if ( ! $role ) {
			return;
		}

		self::sync_role_label( $role_slug, $role_label );

		foreach ( self::CAPS as $capability ) {
			$role->remove_cap( $capability );
		}

		foreach ( $caps as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Updates role display name in role registry.
	 *
	 * @param string $role_slug Role slug.
	 * @param string $role_label Role display label.
	 * @return void
	 */
	private static function sync_role_label( string $role_slug, string $role_label ): void {
		$wp_roles = wp_roles();
		if ( ! $wp_roles instanceof \WP_Roles || ! isset( $wp_roles->roles[ $role_slug ] ) ) {
			return;
		}

		$current_label = isset( $wp_roles->roles[ $role_slug ]['name'] ) ? (string) $wp_roles->roles[ $role_slug ]['name'] : '';
		if ( $current_label === $role_label ) {
			return;
		}

		$wp_roles->roles[ $role_slug ]['name'] = $role_label;
		$wp_roles->role_names[ $role_slug ]    = $role_label;
		update_option( $wp_roles->role_key, $wp_roles->roles );
	}

	/**
	 * Role-to-capability map.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function role_capability_map(): array {
		$all_caps = array_values(
			array_filter(
				self::CAPS,
				static function ( string $capability ): bool {
					return self::CAP_MANAGE_OWNER_PERMISSIONS !== $capability;
				}
			)
		);

		return array(
			// Client roles.
			'uplinkportal_client_owner'           => array(
				'label' => __( 'Client Owner (UplinkPortal)', 'uplinkportal' ),
				'caps'  => self::CAPS,
			),
			'uplinkportal_client_manager'         => array(
				'label' => __( 'Client Manager (UplinkPortal)', 'uplinkportal' ),
				'caps'  => $all_caps,
			),
			'uplinkportal_client_tech'            => array(
				'label' => __( 'Client Tech (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_VIEW_TICKETS,
					self::CAP_TICKET_CREATE_OWN,
					self::CAP_TICKET_VIEW_OWN,
					self::CAP_KB_ACCESS,
				),
			),
			'uplinkportal_client_project_manager' => array(
				'label' => __( 'Client Project Manager (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_VIEW_TICKETS,
					self::CAP_TICKET_VIEW_OWN,
					self::CAP_KB_ACCESS,
				),
			),
			'uplinkportal_client_finance'         => array(
				'label' => __( 'Client Finance (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_VIEW_BILLING,
					self::CAP_KB_ACCESS,
				),
			),
			'uplinkportal_client_user'            => array(
				'label' => __( 'Client User (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_TICKET_CREATE_OWN,
					self::CAP_TICKET_VIEW_OWN,
					self::CAP_KB_ACCESS,
				),
			),

			// MSP roles.
			'uplinkportal_msp_owner'              => array(
				'label' => __( 'MSP Owner (UplinkPortal)', 'uplinkportal' ),
				'caps'  => self::CAPS,
			),
			'uplinkportal_msp_manager'            => array(
				'label' => __( 'MSP Manager (UplinkPortal)', 'uplinkportal' ),
				'caps'  => $all_caps,
			),
			'uplinkportal_msp_executive'          => array(
				'label' => __( 'MSP Executive (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_MANAGE,
					self::CAP_VIEW_OPS_DASHBOARD,
					self::CAP_VIEW_EXEC_DASHBOARD,
				),
			),
			'uplinkportal_msp_project_manager'    => array(
				'label' => __( 'MSP Project Manager (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_MANAGE,
					self::CAP_VIEW_TICKETS,
					self::CAP_VIEW_SUBSCRIPTIONS,
					self::CAP_KB_ACCESS,
					self::CAP_VIEW_OPS_DASHBOARD,
					self::CAP_VIEW_EXEC_DASHBOARD,
				),
			),
			'uplinkportal_msp_finance'            => array(
				'label' => __( 'MSP Finance (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_VIEW_BILLING,
					self::CAP_VIEW_SUBSCRIPTIONS,
					self::CAP_KB_ACCESS,
					self::CAP_VIEW_OPS_DASHBOARD,
					self::CAP_VIEW_FIN_DASHBOARD,
				),
			),
			'uplinkportal_msp_tech'               => array(
				'label' => __( 'MSP Tech (UplinkPortal)', 'uplinkportal' ),
				'caps'  => array(
					self::CAP_ACCESS,
					self::CAP_VIEW_TICKETS,
					self::CAP_TICKET_CREATE_OWN,
					self::CAP_TICKET_VIEW_OWN,
					self::CAP_KB_ACCESS,
					self::CAP_VIEW_OPS_DASHBOARD,
					self::CAP_VIEW_TECH_DASHBOARD,
				),
			),

		);
	}

	/**
	 * Returns whether role map sync has been recorded for current plugin version.
	 *
	 * @return bool
	 */
	private static function is_role_map_current(): bool {
		$stored = get_option( self::ROLE_SYNC_VERSION_OPTION, '' );
		return self::role_map_version() === (string) $stored;
	}

	/**
	 * Marks role map sync completed for current plugin version.
	 *
	 * @return void
	 */
	private static function mark_role_map_synced(): void {
		update_option( self::ROLE_SYNC_VERSION_OPTION, self::role_map_version(), false );
	}

	/**
	 * Returns sync version string.
	 *
	 * @return string
	 */
	private static function role_map_version(): string {
		$plugin_version = defined( 'UPLINKPORTAL_VERSION' ) ? (string) UPLINKPORTAL_VERSION : '0.0.0';
		return 'roles-v5-' . $plugin_version;
	}

	/**
	 * Adds an event to plugin audit log.
	 *
	 * @param string $event Event key.
	 * @param string $message Event summary.
	 * @return void
	 */
	private static function log_event( string $event, string $message ): void {
		$log = get_option( self::AUDIT_LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$user      = wp_get_current_user();
		$user_id   = $user instanceof \WP_User ? (int) $user->ID : 0;
		$user_name = $user instanceof \WP_User ? (string) $user->user_login : '';
		$ip        = '';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
		}

		$entry = array(
			'time'      => gmdate( 'c' ),
			'event'     => sanitize_key( $event ),
			'message'   => SensitiveDataPseudonymizer::pseudonymize_message( $message ),
			'user_id'   => $user_id,
			'user'      => sanitize_text_field( $user_name ),
			'ip'        => SensitiveDataPseudonymizer::pseudonymize_ip( $ip ),
			'component' => 'capability_manager',
		);

		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::AUDIT_LOG_LIMIT );

		update_option( self::AUDIT_LOG_OPTION, $log, false );

		$result = 'success';
		if ( str_contains( $entry['event'], 'denied' ) || str_contains( $entry['event'], 'error' ) || str_contains( $entry['event'], 'failed' ) ) {
			$result = 'error';
		}

		/**
		 * Fires when a security audit event is recorded.
		 *
		 * @param array<string,mixed> $payload Event payload.
		 */
		do_action(
			'uplinkportal_security_audit_event',
			array(
				'source'        => 'capability_manager',
				'time'          => $entry['time'],
				'event'         => $entry['event'],
				'result'        => $result,
				'message'       => $entry['message'],
				'actor_user_id' => $entry['user_id'],
				'ip'            => $entry['ip'],
			)
		);
	}
}
