<?php
/**
 * Client access and identity mapping storage.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Persists client identity mappings and module entitlement overrides in custom tables.
 */
class ClientAccessStore {
	/**
	 * Identity table name.
	 *
	 * @var string
	 */
	private string $identity_table_name;

	/**
	 * Module access table name.
	 *
	 * @var string
	 */
	private string $module_access_table_name;

	/**
	 * Optional feature flags table name.
	 *
	 * @var string
	 */
	private string $feature_flags_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->identity_table_name      = $wpdb->prefix . 'uplinkportal_client_identity_map';
		$this->module_access_table_name = $wpdb->prefix . 'uplinkportal_client_module_access';
		$this->feature_flags_table_name = $wpdb->prefix . 'uplinkportal_client_feature_flags';
	}

	/**
	 * Ensures client access custom tables exist.
	 *
	 * @return void
	 */
	public function ensure_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$identity_sql = "CREATE TABLE {$this->identity_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned NOT NULL,
			external_system varchar(64) NOT NULL DEFAULT 'wordpress_users',
			external_client_id varchar(191) NOT NULL DEFAULT '',
			plan_key varchar(64) NOT NULL DEFAULT 'starter',
			override_enabled tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY wp_user_id (wp_user_id),
			KEY external_lookup (external_system,external_client_id)
		) {$charset_collate};";

		dbDelta( $identity_sql );

		$module_sql = "CREATE TABLE {$this->module_access_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned NOT NULL,
			module_key varchar(64) NOT NULL,
			access_state varchar(20) NOT NULL DEFAULT 'inherit',
			entitlement_source varchar(20) NOT NULL DEFAULT 'local',
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_module (wp_user_id,module_key),
			KEY module_state (module_key,access_state)
		) {$charset_collate};";

		dbDelta( $module_sql );

		$feature_flags_sql = "CREATE TABLE {$this->feature_flags_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned NOT NULL,
			module_key varchar(64) NOT NULL,
			feature_key varchar(100) NOT NULL,
			is_enabled tinyint(1) NOT NULL DEFAULT 0,
			feature_value longtext NULL,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_module_feature (wp_user_id,module_key,feature_key),
			KEY module_feature (module_key,feature_key)
		) {$charset_collate};";

		dbDelta( $feature_flags_sql );
	}

	/**
	 * Returns identity mapping for one client user.
	 *
	 * @param int $user_id User id.
	 * @return array<string,mixed>
	 */
	public function get_identity_mapping( int $user_id ): array {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return $this->default_identity_mapping();
		}

		$row = $this->get_identity_row( $user_id );
		if ( empty( $row ) ) {
			return $this->default_identity_mapping();
		}

		return array(
			'external_system'    => isset( $row['external_system'] ) ? sanitize_key( (string) $row['external_system'] ) : 'wordpress_users',
			'external_client_id' => isset( $row['external_client_id'] ) ? sanitize_text_field( (string) $row['external_client_id'] ) : '',
			'plan_key'           => isset( $row['plan_key'] ) ? sanitize_key( (string) $row['plan_key'] ) : 'starter',
			'override_enabled'   => ! empty( $row['override_enabled'] ),
		);
	}

	/**
	 * Returns plan key for one client user.
	 *
	 * @param int    $user_id User id.
	 * @param string $fallback_plan Default plan key.
	 * @return string
	 */
	public function get_client_plan( int $user_id, string $fallback_plan = 'starter' ): string {
		$fallback_plan = sanitize_key( $fallback_plan );
		if ( '' === $fallback_plan ) {
			$fallback_plan = 'starter';
		}

		$mapping = $this->get_identity_mapping( $user_id );
		$plan    = isset( $mapping['plan_key'] ) ? sanitize_key( (string) $mapping['plan_key'] ) : '';
		if ( '' === $plan ) {
			return $fallback_plan;
		}

		return $plan;
	}

	/**
	 * Returns override state and modules for one client user.
	 *
	 * @param int               $user_id User id.
	 * @param array<int,string> $module_keys Allowed module keys.
	 * @return array<string,mixed>
	 */
	public function get_override_data( int $user_id, array $module_keys ): array {
		$user_id     = absint( $user_id );
		$module_keys = array_values( array_unique( array_filter( array_map( 'sanitize_key', $module_keys ) ) ) );

		if ( $user_id <= 0 || empty( $module_keys ) ) {
			return array(
				'enabled'          => false,
				'modules'          => array(),
				'module_states'    => array(),
				'disabled_modules' => array(),
			);
		}

		$mapping       = $this->get_identity_mapping( $user_id );
		$enabled       = ! empty( $mapping['override_enabled'] );
		$module_states = $this->get_module_state_map( $user_id, $module_keys );
		$enabled_keys  = array();
		$disabled_keys = array();

		foreach ( $module_states as $module_key => $state ) {
			if ( 'enabled' === $state ) {
				$enabled_keys[] = $module_key;
			} elseif ( 'disabled' === $state ) {
				$disabled_keys[] = $module_key;
			}
		}

		return array(
			'enabled'          => $enabled,
			'modules'          => $enabled_keys,
			'module_states'    => $module_states,
			'disabled_modules' => $disabled_keys,
		);
	}

	/**
	 * Saves full client access profile (identity + plan + module states).
	 *
	 * @param int                  $user_id User id.
	 * @param string               $plan_key Plan key.
	 * @param bool                 $override_enabled Override mode.
	 * @param array<string,string> $module_states Module state map.
	 * @param string               $external_system External system key.
	 * @param string               $external_client_id External client id.
	 * @param int                  $updated_by Actor user id.
	 * @return void
	 */
	public function save_client_access_profile( int $user_id, string $plan_key, bool $override_enabled, array $module_states, string $external_system, string $external_client_id, int $updated_by = 0 ): void {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		$plan_key           = sanitize_key( $plan_key );
		$plan_key           = '' !== $plan_key ? $plan_key : 'starter';
		$external_system    = sanitize_key( $external_system );
		$external_system    = '' !== $external_system ? $external_system : 'wordpress_users';
		$external_client_id = sanitize_text_field( $external_client_id );
		$updated_by         = absint( $updated_by );
		$now                = current_time( 'mysql', true );

		$identity_row = $this->get_identity_row( $user_id );
		if ( empty( $identity_row ) ) {
			$wpdb->insert(
				$this->identity_table_name,
				array(
					'wp_user_id'         => $user_id,
					'external_system'    => $external_system,
					'external_client_id' => $external_client_id,
					'plan_key'           => $plan_key,
					'override_enabled'   => $override_enabled ? 1 : 0,
					'created_at'         => $now,
					'updated_at'         => $now,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		} else {
			$wpdb->update(
				$this->identity_table_name,
				array(
					'external_system'    => $external_system,
					'external_client_id' => $external_client_id,
					'plan_key'           => $plan_key,
					'override_enabled'   => $override_enabled ? 1 : 0,
					'updated_at'         => $now,
				),
				array(
					'wp_user_id' => $user_id,
				),
				array( '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		$this->save_module_state_map( $user_id, $module_states, $updated_by );
	}

	/**
	 * Returns module access states for one client user.
	 *
	 * @param int               $user_id User id.
	 * @param array<int,string> $module_keys Allowed module keys.
	 * @return array<string,string>
	 */
	public function get_module_state_map( int $user_id, array $module_keys ): array {
		global $wpdb;

		$user_id     = absint( $user_id );
		$module_keys = array_values( array_unique( array_filter( array_map( 'sanitize_key', $module_keys ) ) ) );
		$states      = array();

		foreach ( $module_keys as $module_key ) {
			$states[ $module_key ] = 'inherit';
		}

		if ( $user_id <= 0 || empty( $module_keys ) ) {
			return $states;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT module_key, access_state
				FROM {$this->module_access_table_name}
				WHERE wp_user_id = %d",
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return $states;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$module_key = isset( $row['module_key'] ) ? sanitize_key( (string) $row['module_key'] ) : '';
			if ( '' === $module_key || ! in_array( $module_key, $module_keys, true ) ) {
				continue;
			}

			$states[ $module_key ] = $this->normalize_access_state( isset( $row['access_state'] ) ? (string) $row['access_state'] : 'inherit' );
		}

		return $states;
	}

	/**
	 * Saves optional module feature flags.
	 *
	 * @param int                 $user_id User id.
	 * @param string              $module_key Module key.
	 * @param array<string,mixed> $feature_flags Feature flag map.
	 * @param int                 $updated_by Actor user id.
	 * @return void
	 */
	public function save_feature_flags( int $user_id, string $module_key, array $feature_flags, int $updated_by = 0 ): void {
		global $wpdb;

		$user_id    = absint( $user_id );
		$module_key = sanitize_key( $module_key );
		$updated_by = absint( $updated_by );
		$now        = current_time( 'mysql', true );

		if ( $user_id <= 0 || '' === $module_key ) {
			return;
		}

		foreach ( $feature_flags as $feature_key => $feature_value ) {
			$feature_key = sanitize_key( (string) $feature_key );
			if ( '' === $feature_key ) {
				continue;
			}

			$is_enabled = ! empty( $feature_value ) ? 1 : 0;
			$value_json = wp_json_encode( $feature_value );
			$value_json = is_string( $value_json ) ? $value_json : '';

			$wpdb->replace(
				$this->feature_flags_table_name,
				array(
					'wp_user_id'    => $user_id,
					'module_key'    => $module_key,
					'feature_key'   => $feature_key,
					'is_enabled'    => $is_enabled,
					'feature_value' => $value_json,
					'updated_by'    => $updated_by,
					'updated_at'    => $now,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Saves module state rows.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string,string> $module_states Module states.
	 * @param int                  $updated_by Actor user id.
	 * @return void
	 */
	private function save_module_state_map( int $user_id, array $module_states, int $updated_by ): void {
		global $wpdb;

		$user_id    = absint( $user_id );
		$updated_by = absint( $updated_by );
		$now        = current_time( 'mysql', true );

		foreach ( $module_states as $module_key => $state ) {
			$module_key = sanitize_key( (string) $module_key );
			if ( '' === $module_key ) {
				continue;
			}

			$wpdb->replace(
				$this->module_access_table_name,
				array(
					'wp_user_id'         => $user_id,
					'module_key'         => $module_key,
					'access_state'       => $this->normalize_access_state( (string) $state ),
					'entitlement_source' => 'local',
					'updated_by'         => $updated_by,
					'updated_at'         => $now,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Returns identity row by user id.
	 *
	 * @param int $user_id User id.
	 * @return array<string,mixed>
	 */
	private function get_identity_row( int $user_id ): array {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT wp_user_id, external_system, external_client_id, plan_key, override_enabled
				FROM {$this->identity_table_name}
				WHERE wp_user_id = %d
				LIMIT 1",
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? $row : array();
	}

	/**
	 * Returns default identity mapping payload.
	 *
	 * @return array<string,mixed>
	 */
	private function default_identity_mapping(): array {
		return array(
			'external_system'    => 'wordpress_users',
			'external_client_id' => '',
			'plan_key'           => 'starter',
			'override_enabled'   => false,
		);
	}

	/**
	 * Normalizes one access state.
	 *
	 * @param string $state Raw state.
	 * @return string
	 */
	private function normalize_access_state( string $state ): string {
		$state = sanitize_key( $state );
		if ( in_array( $state, array( 'enabled', 'disabled', 'inherit' ), true ) ) {
			return $state;
		}

		return 'inherit';
	}

	/**
	 * Returns default module key list used for migration fallback.
	 *
	 * @return array<int,string>
	 */
	private function default_module_keys(): array {
		return array(
			'finance',
			'it_support',
			'projects',
			'custom_dummy',
		);
	}
}
