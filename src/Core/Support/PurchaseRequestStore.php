<?php
/**
 * Purchase request storage.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Stores service/add-on purchase requests for manual MSP fulfillment.
 */
class PurchaseRequestStore {
	/**
	 * Requests table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'uplinkportal_purchase_requests';
	}

	/**
	 * Ensures purchase request table exists.
	 *
	 * @return void
	 */
	public function ensure_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_type varchar(32) NOT NULL DEFAULT 'service_request',
			requested_item_key varchar(80) NOT NULL,
			requested_item_label varchar(191) NOT NULL,
			request_context varchar(40) NOT NULL DEFAULT 'client_portal',
			requester_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			requester_display_name varchar(120) NOT NULL DEFAULT '',
			requester_email varchar(190) NOT NULL DEFAULT '',
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_created (status,created_at),
			KEY requester_created (requester_user_id,created_at),
			KEY item_key_created (requested_item_key,created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Creates one purchase request row.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @return int Inserted request id or 0 on failure.
	 */
	public function create_request( array $payload ): int {
		global $wpdb;

		$request_type = isset( $payload['request_type'] ) ? sanitize_key( (string) $payload['request_type'] ) : 'service_request';
		if ( ! in_array( $request_type, array( 'service_request', 'addon_request' ), true ) ) {
			$request_type = 'service_request';
		}

		$item_key   = isset( $payload['requested_item_key'] ) ? sanitize_key( (string) $payload['requested_item_key'] ) : '';
		$item_label = isset( $payload['requested_item_label'] ) ? sanitize_text_field( (string) $payload['requested_item_label'] ) : '';
		if ( '' === $item_key || '' === $item_label ) {
			return 0;
		}

		$context = isset( $payload['request_context'] ) ? sanitize_key( (string) $payload['request_context'] ) : 'client_portal';
		if ( ! in_array( $context, array( 'client_portal', 'service_catalog', 'module_catalog' ), true ) ) {
			$context = 'client_portal';
		}

		$status = isset( $payload['status'] ) ? sanitize_key( (string) $payload['status'] ) : 'new';
		if ( ! in_array( $status, array( 'new', 'in_review', 'fulfilled', 'dismissed' ), true ) ) {
			$status = 'new';
		}

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'request_type'           => $request_type,
				'requested_item_key'     => $item_key,
				'requested_item_label'   => $item_label,
				'request_context'        => $context,
				'requester_user_id'      => isset( $payload['requester_user_id'] ) ? absint( $payload['requester_user_id'] ) : 0,
				'requester_display_name' => isset( $payload['requester_display_name'] ) ? sanitize_text_field( (string) $payload['requester_display_name'] ) : '',
				'requester_email'        => isset( $payload['requester_email'] ) ? sanitize_email( (string) $payload['requester_email'] ) : '',
				'notes'                  => isset( $payload['notes'] ) ? sanitize_textarea_field( (string) $payload['notes'] ) : '',
				'status'                 => $status,
				'created_at'             => $now,
				'updated_at'             => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Returns count of open requests.
	 *
	 * @return int
	 */
	public function count_open_requests(): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE status IN (%s,%s)",
				'new',
				'in_review'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return absint( $total );
	}

	/**
	 * Returns recent purchase requests.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_requests( int $limit = 10 ): array {
		global $wpdb;

		$limit = max( 1, min( 50, absint( $limit ) ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, request_type, requested_item_key, requested_item_label, request_context, requester_user_id, requester_display_name, requester_email, notes, status, created_at
				FROM {$this->table_name}
				ORDER BY created_at DESC, id DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Returns ticket request totals grouped by requester user id.
	 *
	 * @return array<int,int>
	 */
	public function get_ticket_counts_by_requester(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT requester_user_id, COUNT(*) AS total
				FROM {$this->table_name}
				WHERE requested_item_key = %s
				GROUP BY requester_user_id",
				'it_support'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->normalize_grouped_count_rows( $rows );
	}

	/**
	 * Returns active project request totals grouped by requester user id.
	 *
	 * Active projects are represented by open project-related requests.
	 *
	 * @return array<int,int>
	 */
	public function get_active_project_counts_by_requester(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT requester_user_id, COUNT(*) AS total
				FROM {$this->table_name}
				WHERE requested_item_key = %s
					AND status IN (%s,%s)
				GROUP BY requester_user_id",
				'projects',
				'new',
				'in_review'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->normalize_grouped_count_rows( $rows );
	}

	/**
	 * Normalizes grouped SQL count rows into requester-id map.
	 *
	 * @param mixed $rows Database rows.
	 * @return array<int,int>
	 */
	private function normalize_grouped_count_rows( $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$counts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$user_id = isset( $row['requester_user_id'] ) ? absint( $row['requester_user_id'] ) : 0;
			if ( $user_id <= 0 ) {
				continue;
			}

			$counts[ $user_id ] = isset( $row['total'] ) ? absint( $row['total'] ) : 0;
		}

		return $counts;
	}
}
