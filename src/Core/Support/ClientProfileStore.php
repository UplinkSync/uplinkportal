<?php
/**
 * Client profile storage and guardrails.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Stores dedicated client profile metadata with strict access guards.
 */
class ClientProfileStore {
	/**
	 * Profile provider id.
	 *
	 * @var string
	 */
	public const PROVIDER_WORDPRESS_USERS_META = 'wordpress_users_meta';

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	private const META_KEY_PREFIX = 'uplinkportal_client_profile_';

	/**
	 * Allowed contract type values.
	 *
	 * @var array<int,string>
	 */
	public const CONTRACT_TYPES = array( 'recurring', 'project', 'none' );

	/**
	 * Allowed contract status values.
	 *
	 * @var array<int,string>
	 */
	public const CONTRACT_STATUSES = array( 'active', 'lapsed' );

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
	 * Registers runtime hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_profile_meta_schema' ) );
	}

	/**
	 * Returns profile provider id.
	 *
	 * @return string
	 */
	public function get_default_provider(): string {
		return self::PROVIDER_WORDPRESS_USERS_META;
	}

	/**
	 * Returns profile field schema.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_profile_schema(): array {
		return array(
			'company_name'  => array(
				'label'       => __( 'Company Name', 'uplinkportal' ),
				'description' => __( 'Client organization or company display name.', 'uplinkportal' ),
			),
			'department'    => array(
				'label'       => __( 'Department', 'uplinkportal' ),
				'description' => __( 'Primary internal department for the client user.', 'uplinkportal' ),
			),
			'title'         => array(
				'label'       => __( 'Title', 'uplinkportal' ),
				'description' => __( 'Role title shown to support and project teams.', 'uplinkportal' ),
			),
			'phone_number'  => array(
				'label'       => __( 'Phone Number', 'uplinkportal' ),
				'description' => __( 'Primary phone number for client contact and notifications.', 'uplinkportal' ),
			),
			'email_address' => array(
				'label'       => __( 'Email Address', 'uplinkportal' ),
				'description' => __( 'Client profile contact email (may differ from WordPress account email).', 'uplinkportal' ),
			),
			'contract_type' => array(
				'label'       => __( 'Contract Type', 'uplinkportal' ),
				'description' => __( 'Engagement type for hiring-trigger metrics: recurring, project, or none.', 'uplinkportal' ),
			),
			'contract_status' => array(
				'label'       => __( 'Contract Status', 'uplinkportal' ),
				'description' => __( 'Whether the client contract is currently active or lapsed.', 'uplinkportal' ),
			),
		);
	}

	/**
	 * Registers typed client profile user-meta fields.
	 *
	 * @return void
	 */
	public function register_profile_meta_schema(): void {
		foreach ( array_keys( $this->get_profile_schema() ) as $field_key ) {
			register_meta(
				'user',
				self::meta_key( $field_key ),
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'sanitize_callback' => function ( $value ) use ( $field_key ) {
						return $this->sanitize_profile_field( $field_key, $value );
					},
					'auth_callback'     => function ( $allowed, $meta_key, $user_id ) {
						return $this->can_current_user_access_profile( absint( $user_id ), false );
					},
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Returns profile values for one user.
	 *
	 * @param int $user_id User id.
	 * @return array<string,string>
	 */
	public function get_profile_for_user( int $user_id ): array {
		$profile = array();
		$user_id = absint( $user_id );

		foreach ( array_keys( $this->get_profile_schema() ) as $field_key ) {
			$value = get_user_meta( $user_id, self::meta_key( $field_key ), true );
			if ( ! is_string( $value ) ) {
				$value = '';
			}
			$profile[ $field_key ] = $this->sanitize_profile_field( $field_key, $value );
		}

		return $profile;
	}

	/**
	 * Updates profile values for one user.
	 *
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,string>|WP_Error
	 */
	public function update_profile_for_user( int $user_id, array $payload ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! $this->is_client_workspace_user( $user_id ) ) {
			return new WP_Error( 'uplinkportal_invalid_client_profile_user', __( 'Invalid client user id.', 'uplinkportal' ) );
		}

		$saved = array();
		foreach ( array_keys( $this->get_profile_schema() ) as $field_key ) {
			$raw_value = isset( $payload[ $field_key ] ) ? $payload[ $field_key ] : '';
			$value     = $this->sanitize_profile_field( $field_key, $raw_value );
			update_user_meta( $user_id, self::meta_key( $field_key ), $value );
			$saved[ $field_key ] = $value;
		}

		return $saved;
	}

	/**
	 * Returns whether current user can access target client profile.
	 *
	 * @param int  $target_user_id Target user id.
	 * @param bool $for_write Whether write access is required.
	 * @return bool
	 */
	public function can_current_user_access_profile( int $target_user_id, bool $for_write = false ): bool {
		$target_user_id = absint( $target_user_id );
		$current_user   = get_current_user_id();

		if ( $target_user_id <= 0 || $current_user <= 0 ) {
			return false;
		}

		if ( ! $this->is_client_workspace_user( $target_user_id ) ) {
			return false;
		}

		if ( ! TenantBoundaryScope::is_user_in_scope( $target_user_id, $this->options ) ) {
			return false;
		}

		if ( current_user_can( 'msp_manage' ) ) {
			return true;
		}

		if ( $current_user === $target_user_id && current_user_can( 'client_access' ) ) {
			return true;
		}

		return ! $for_write && $current_user === $target_user_id;
	}

	/**
	 * Returns client users in current tenant scope.
	 *
	 * @return array<int,WP_User>
	 */
	public function get_client_workspace_users(): array {
		$query_args = array(
			'role__in' => $this->get_client_role_slugs(),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'fields'   => 'all',
		);
		$query_args = TenantBoundaryScope::apply_user_query_scope( $query_args, $this->options );
		$users      = get_users( $query_args );

		if ( ! is_array( $users ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$users,
				static function ( $user ): bool {
					return $user instanceof WP_User;
				}
			)
		);
	}

	/**
	 * Returns whether user belongs to one client role.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public function is_client_workspace_user( int $user_id ): bool {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		foreach ( $this->get_client_role_slugs() as $role_slug ) {
			if ( in_array( $role_slug, $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds full user-meta key for one field.
	 *
	 * @param string $field_key Field key.
	 * @return string
	 */
	public static function meta_key( string $field_key ): string {
		return self::META_KEY_PREFIX . sanitize_key( $field_key );
	}

	/**
	 * Sanitizes one profile field.
	 *
	 * @param string $field_key Field key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	private function sanitize_profile_field( string $field_key, $value ): string {
		$field_key = sanitize_key( $field_key );
		$value     = is_scalar( $value ) ? (string) $value : '';

		if ( 'email_address' === $field_key ) {
			return sanitize_email( $value );
		}

		if ( 'phone_number' === $field_key ) {
			return sanitize_text_field( preg_replace( '/[^0-9\\+\\(\\)\\-\\.\\sx]/i', '', $value ) );
		}

		if ( 'contract_type' === $field_key ) {
			$value = sanitize_key( $value );
			return in_array( $value, self::CONTRACT_TYPES, true ) ? $value : 'none';
		}

		if ( 'contract_status' === $field_key ) {
			$value = sanitize_key( $value );
			return in_array( $value, self::CONTRACT_STATUSES, true ) ? $value : 'lapsed';
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Returns client role slugs.
	 *
	 * @return array<int,string>
	 */
	private function get_client_role_slugs(): array {
		return array(
			'uplinkportal_client_owner',
			'uplinkportal_client_manager',
			'uplinkportal_client_project_manager',
			'uplinkportal_client_finance',
			'uplinkportal_client_tech',
			'uplinkportal_client_user',
		);
	}
}
