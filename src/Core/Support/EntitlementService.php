<?php
/**
 * Client entitlement storage and evaluation service.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Manages provider entitlements for clients in a local WordPress DB.
 *
 * Entitlements grant access to specific providers within modules.
 * Status values:
 * - active: Full access to provider
 * - grace: Access granted temporarily after cancellation (7 days)
 * - suspended: Access temporarily blocked (manual intervention)
 * - canceled: Entitlement terminated (no access after grace expires)
 */
class EntitlementService {
	/**
	 * Entitlement table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Grace period duration in days.
	 *
	 * @var int
	 */
	private const GRACE_PERIOD_DAYS = 7;

	/**
	 * Entitlement status constants.
	 */
	public const STATUS_ACTIVE    = 'active';
	public const STATUS_GRACE     = 'grace';
	public const STATUS_SUSPENDED = 'suspended';
	public const STATUS_CANCELED  = 'canceled';

	/**
	 * Per-request in-memory cache for entitlement lookups.
	 *
	 * Keyed by "$client_id:$module_slug:$provider_id". Reset between requests automatically
	 * (static property lifecycle). Use reset_request_cache() in tests.
	 *
	 * @var array<string,array<string,mixed>|null>
	 */
	private static array $request_cache = array();

	/**
	 * Entitlements schema version.
	 *
	 * @var int
	 */
	private const SCHEMA_VERSION = 2;

	/**
	 * Entitlements schema version option key.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION_OPTION = 'uplinkportal_client_entitlements_schema_version';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$this->table_name = $wpdb->prefix . 'uplinkportal_client_entitlements';
		} else {
			// Fallback for test environment.
			$this->table_name = 'wp_uplinkportal_client_entitlements';
		}
	}

	/**
	 * Ensures entitlement table exists.
	 *
	 * @return void
	 */
	public function ensure_schema(): void {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			module_slug varchar(64) NOT NULL,
			provider_id varchar(100) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			grace_until datetime NULL,
			subscription_ref varchar(191) NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_module_provider (client_id,module_slug,provider_id),
			KEY module_provider (module_slug,provider_id),
			KEY status (status),
			KEY grace_expiry (grace_until)
		) {$charset_collate};";

		dbDelta( $sql );

		$stored_version = absint( get_option( self::SCHEMA_VERSION_OPTION, 0 ) );
		if ( $stored_version < self::SCHEMA_VERSION ) {
			update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false );
		}
	}

	/**
	 * Returns supported status values.
	 *
	 * @return array<int,string>
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_ACTIVE,
			self::STATUS_GRACE,
			self::STATUS_SUSPENDED,
			self::STATUS_CANCELED,
		);
	}

	/**
	 * Returns entitlement record for a client/module/provider combination.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return array<string,mixed>|null Entitlement data or null if not found.
	 */
	public function get_entitlement( int $client_id, string $module_slug, string $provider_id ): ?array {
		global $wpdb;

		// Early return if wpdb not available (test environment).
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}

		$client_id   = absint( $client_id );
		$module_slug = sanitize_key( $module_slug );
		$provider_id = sanitize_text_field( $provider_id );

		if ( $client_id <= 0 || '' === $module_slug || '' === $provider_id ) {
			return null;
		}

		$cache_key = $client_id . ':' . $module_slug . ':' . $provider_id;
		if ( array_key_exists( $cache_key, self::$request_cache ) ) {
			return self::$request_cache[ $cache_key ];
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE client_id = %d
				AND module_slug = %s
				AND provider_id = %s",
				$client_id,
				$module_slug,
				$provider_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result                            = is_array( $row ) && ! empty( $row ) ? $row : null;
		self::$request_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Resets the per-request in-memory cache.
	 *
	 * Intended for use in tests only.
	 *
	 * @return void
	 */
	public static function reset_request_cache(): void {
		self::$request_cache = array();
	}

	/**
	 * Checks whether a client is currently entitled to a provider.
	 *
	 * Returns true if status is active OR if in grace period and grace has not expired.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return bool True if entitled (active or within grace).
	 */
	public function is_entitled_now( int $client_id, string $module_slug, string $provider_id ): bool {
		$summary = $this->get_status_summary( $client_id, $module_slug, $provider_id );
		return ! empty( $summary['entitled'] );
	}

	/**
	 * Checks whether a provider is locked (not entitled or grace expired).
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return bool True if locked (no access).
	 */
	public function is_locked_now( int $client_id, string $module_slug, string $provider_id ): bool {
		return ! $this->is_entitled_now( $client_id, $module_slug, $provider_id );
	}

	/**
	 * Creates or updates an entitlement record.
	 *
	 * @param int         $client_id Client user ID.
	 * @param string      $module_slug Module slug.
	 * @param string      $provider_id Provider ID.
	 * @param string      $status Entitlement status.
	 * @param string|null $grace_until Grace expiry date (datetime string or null).
	 * @param string|null $subscription_ref Optional subscription reference.
	 * @param string|null $notes Optional operator notes.
	 * @return bool True on success, false on failure.
	 */
	public function upsert_entitlement(
		int $client_id,
		string $module_slug,
		string $provider_id,
		string $status,
		?string $grace_until = null,
		?string $subscription_ref = null,
		?string $notes = null
	): bool {
		global $wpdb;

		// Early return if wpdb not available (test environment).
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}

		$client_id        = absint( $client_id );
		$module_slug      = sanitize_key( $module_slug );
		$provider_id      = sanitize_text_field( $provider_id );
		$status           = sanitize_key( $status );
		$notes            = null === $notes ? null : sanitize_textarea_field( $notes );
		$grace_until      = $this->normalize_datetime_value( $grace_until );
		$subscription_ref = null === $subscription_ref ? null : sanitize_text_field( $subscription_ref );

		if ( $client_id <= 0 || '' === $module_slug || '' === $provider_id || '' === $status ) {
			return false;
		}

		if ( ! in_array( $status, self::statuses(), true ) ) {
			return false;
		}

		$now      = current_time( 'mysql', true );
		$existing = $this->get_entitlement( $client_id, $module_slug, $provider_id );

		$data = array(
			'status'           => $status,
			'grace_until'      => $grace_until,
			'subscription_ref' => $subscription_ref,
			'notes'            => $notes,
			'updated_at'       => $now,
		);

		$format = array( '%s', '%s', '%s', '%s', '%s' );

		if ( null === $existing ) {
			// Insert new entitlement.
			$data['client_id']   = $client_id;
			$data['module_slug'] = $module_slug;
			$data['provider_id'] = $provider_id;
			$data['created_at']  = $now;

			$format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

			$result = $wpdb->insert( $this->table_name, $data, $format );
		} else {
			// Update existing entitlement.
			$result = $wpdb->update(
				$this->table_name,
				$data,
				array(
					'client_id'   => $client_id,
					'module_slug' => $module_slug,
					'provider_id' => $provider_id,
				),
				$format,
				array( '%d', '%s', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Transitions an entitlement to grace period.
	 *
	 * Sets status to 'grace' and grace_until to current time + 7 days.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return bool True on success, false on failure.
	 */
	public function transition_to_grace( int $client_id, string $module_slug, string $provider_id ): bool {
		$grace_until = gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::GRACE_PERIOD_DAYS . ' days', time() ) );

		return $this->upsert_entitlement(
			$client_id,
			$module_slug,
			$provider_id,
			self::STATUS_GRACE,
			$grace_until
		);
	}

	/**
	 * Suspends an entitlement (blocks access immediately).
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return bool True on success, false on failure.
	 */
	public function suspend_entitlement( int $client_id, string $module_slug, string $provider_id ): bool {
		return $this->upsert_entitlement(
			$client_id,
			$module_slug,
			$provider_id,
			self::STATUS_SUSPENDED
		);
	}

	/**
	 * Cancels an entitlement (blocks access immediately).
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return bool True on success, false on failure.
	 */
	public function cancel_entitlement( int $client_id, string $module_slug, string $provider_id ): bool {
		return $this->upsert_entitlement(
			$client_id,
			$module_slug,
			$provider_id,
			self::STATUS_CANCELED
		);
	}

	/**
	 * Activates an entitlement (grants full access).
	 *
	 * @param int         $client_id Client user ID.
	 * @param string      $module_slug Module slug.
	 * @param string      $provider_id Provider ID.
	 * @param string|null $subscription_ref Optional subscription reference.
	 * @return bool True on success, false on failure.
	 */
	public function activate_entitlement(
		int $client_id,
		string $module_slug,
		string $provider_id,
		?string $subscription_ref = null
	): bool {
		return $this->upsert_entitlement(
			$client_id,
			$module_slug,
			$provider_id,
			self::STATUS_ACTIVE,
			null,
			$subscription_ref,
			null
		);
	}

	/**
	 * Returns all entitlements for a client.
	 *
	 * @param int $client_id Client user ID.
	 * @return array<int,array<string,mixed>> Array of entitlement records.
	 */
	public function get_client_entitlements( int $client_id ): array {
		global $wpdb;

		// Early return if wpdb not available (test environment).
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}

		$client_id = absint( $client_id );
		if ( $client_id <= 0 ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE client_id = %d
				ORDER BY module_slug, provider_id",
				$client_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Deletes an entitlement record.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_entitlement( int $client_id, string $module_slug, string $provider_id ): bool {
		global $wpdb;

		// Early return if wpdb not available (test environment).
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}

		$client_id   = absint( $client_id );
		$module_slug = sanitize_key( $module_slug );
		$provider_id = sanitize_text_field( $provider_id );

		if ( $client_id <= 0 || '' === $module_slug || '' === $provider_id ) {
			return false;
		}

		$result = $wpdb->delete(
			$this->table_name,
			array(
				'client_id'   => $client_id,
				'module_slug' => $module_slug,
				'provider_id' => $provider_id,
			),
			array( '%d', '%s', '%s' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Returns grace period information for a provider entitlement.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return array{in_grace:bool,grace_until:string|null,days_remaining:int|null} Grace info.
	 */
	public function get_grace_info( int $client_id, string $module_slug, string $provider_id ): array {
		$status = $this->get_status_summary( $client_id, $module_slug, $provider_id );

		return array(
			'in_grace'       => ! empty( $status['in_grace'] ),
			'grace_until'    => isset( $status['grace_until'] ) ? $status['grace_until'] : null,
			'days_remaining' => isset( $status['days_remaining'] ) ? $status['days_remaining'] : null,
		);
	}

	/**
	 * Returns full entitlement status summary for one provider.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $module_slug Module slug.
	 * @param string $provider_id Provider ID.
	 * @return array{status:string,entitled:bool,in_grace:bool,grace_until:string|null,days_remaining:int|null,blocked_reason:string}
	 */
	public function get_status_summary( int $client_id, string $module_slug, string $provider_id ): array {
		$entitlement = $this->get_entitlement( $client_id, $module_slug, $provider_id );
		$default     = array(
			'status'         => 'none',
			'entitled'       => false,
			'in_grace'       => false,
			'grace_until'    => null,
			'days_remaining' => null,
			'blocked_reason' => 'missing',
		);

		if ( null === $entitlement ) {
			return $default;
		}

		$status      = isset( $entitlement['status'] ) ? sanitize_key( (string) $entitlement['status'] ) : 'none';
		$grace_until = isset( $entitlement['grace_until'] ) ? sanitize_text_field( (string) $entitlement['grace_until'] ) : '';
		$summary     = array(
			'status'         => $status,
			'entitled'       => false,
			'in_grace'       => false,
			'grace_until'    => '' !== $grace_until ? $grace_until : null,
			'days_remaining' => null,
			'blocked_reason' => 'missing',
		);

		if ( self::STATUS_ACTIVE === $status ) {
			$summary['entitled']       = true;
			$summary['blocked_reason'] = '';
			return $summary;
		}

		if ( self::STATUS_GRACE === $status ) {
			$grace_timestamp = '' !== $grace_until ? strtotime( $grace_until ) : false;
			if ( false !== $grace_timestamp ) {
				$now_timestamp = time();
				$in_grace      = $now_timestamp <= $grace_timestamp;
				$days          = max( 0, ceil( ( $grace_timestamp - $now_timestamp ) / DAY_IN_SECONDS ) );

				$summary['in_grace']       = $in_grace;
				$summary['days_remaining'] = (int) $days;
				$summary['entitled']       = $in_grace;
				$summary['blocked_reason'] = $in_grace ? '' : 'grace_expired';
				return $summary;
			}

			$summary['blocked_reason'] = 'grace_expired';
			return $summary;
		}

		if ( self::STATUS_SUSPENDED === $status ) {
			$summary['blocked_reason'] = 'suspended';
			return $summary;
		}

		if ( self::STATUS_CANCELED === $status ) {
			$summary['blocked_reason'] = 'canceled';
			return $summary;
		}

		return $summary;
	}

	/**
	 * Normalizes datetime values into mysql UTC format.
	 *
	 * @param string|null $value Raw datetime.
	 * @return string|null
	 */
	private function normalize_datetime_value( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( sanitize_text_field( $value ) );
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
