<?php
/**
 * Central audit journal.
 *
 * @package UplinkPortal\Core\Security
 */

namespace UplinkPortal\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Stores normalized audit events from all plugin security emitters.
 */
class CentralAuditJournal {
	/**
	 * Option key for journal.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'uplinkportal_central_audit_journal';

	/**
	 * Default retained entries.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_ENTRIES = 2000;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'uplinkportal_security_audit_event', array( $this, 'append_event' ) );
	}

	/**
	 * Appends one normalized journal entry.
	 *
	 * @param array<string,mixed> $event Event payload.
	 * @return void
	 */
	public function append_event( array $event ): void {
		$entries = self::get_entries();
		$max     = max( 100, absint( apply_filters( 'uplinkportal_central_audit_max_entries', self::DEFAULT_MAX_ENTRIES ) ) );

		$entries[] = array(
			'time'           => isset( $event['time'] ) ? sanitize_text_field( (string) $event['time'] ) : gmdate( 'c' ),
			'source'         => isset( $event['source'] ) ? sanitize_key( (string) $event['source'] ) : 'unknown',
			'event'          => isset( $event['event'] ) ? sanitize_key( (string) $event['event'] ) : 'unknown',
			'result'         => isset( $event['result'] ) ? sanitize_key( (string) $event['result'] ) : 'unknown',
			'message'        => isset( $event['message'] ) ? sanitize_text_field( (string) $event['message'] ) : '',
			'actor_user_id'  => isset( $event['actor_user_id'] ) ? absint( $event['actor_user_id'] ) : 0,
			'client_user_id' => isset( $event['client_user_id'] ) ? absint( $event['client_user_id'] ) : 0,
			'document_id'    => isset( $event['document_id'] ) ? absint( $event['document_id'] ) : 0,
		);

		if ( count( $entries ) > $max ) {
			$entries = array_slice( $entries, -1 * $max );
		}

		update_option( self::OPTION_KEY, $entries, false );
	}

	/**
	 * Returns recent journal entries (newest first).
	 *
	 * @param int $limit Max items.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent_entries( int $limit = 20 ): array {
		$limit   = max( 1, absint( $limit ) );
		$entries = self::get_entries();
		if ( empty( $entries ) ) {
			return array();
		}

		return array_reverse( array_slice( $entries, -1 * $limit ) );
	}

	/**
	 * Purges journal entries older than N days.
	 *
	 * @param int $days Retention days.
	 * @return int
	 */
	public static function purge_older_than( int $days ): int {
		$days    = max( 1, absint( $days ) );
		$entries = self::get_entries();
		if ( empty( $entries ) ) {
			return 0;
		}

		$cutoff = time() - ( $days * DAY_IN_SECONDS );
		$kept   = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$entry_time = isset( $entry['time'] ) ? strtotime( (string) $entry['time'] ) : false;
			if ( false === $entry_time || $entry_time >= $cutoff ) {
				$kept[] = $entry;
			}
		}

		$removed = count( $entries ) - count( $kept );
		if ( $removed > 0 ) {
			update_option( self::OPTION_KEY, array_values( $kept ), false );
		}

		return max( 0, $removed );
	}

	/**
	 * Returns normalized journal entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_entries(): array {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$entries = array();
		foreach ( $raw as $entry ) {
			if ( is_array( $entry ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}
}
