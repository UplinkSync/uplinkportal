<?php
/**
 * Tamper-evident evidence trail store.
 *
 * @package UplinkPortal\Core\Security
 */

namespace UplinkPortal\Core\Security;

use UplinkPortal\Core\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Stores a hash-chained audit evidence trail.
 */
class EvidenceTrailStore {
	/**
	 * Option key for evidence payload.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'uplinkportal_evidence_trail';

	/**
	 * Maximum retained entries before rolling anchor is advanced.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_ENTRIES = 2000;

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
		add_action( 'uplinkportal_security_audit_event', array( $this, 'append_from_event' ) );
	}

	/**
	 * Appends one evidence entry from security audit event payload.
	 *
	 * @param array<string,mixed> $event Audit event payload.
	 * @return void
	 */
	public function append_from_event( array $event ): void {
		if ( ! $this->options->get_evidence_trail_enabled() ) {
			return;
		}

		$payload   = self::get_payload();
		$entries   = $payload['entries'];
		$anchor    = $payload['anchor_hash'];
		$max       = max( 100, absint( apply_filters( 'uplinkportal_evidence_trail_max_entries', self::DEFAULT_MAX_ENTRIES ) ) );
		$prev_hash = ! empty( $entries ) ? (string) ( $entries[ count( $entries ) - 1 ]['hash'] ?? '' ) : $anchor;

		$message        = isset( $event['message'] ) ? sanitize_text_field( (string) $event['message'] ) : '';
		$record         = array(
			'time'            => isset( $event['time'] ) ? sanitize_text_field( (string) $event['time'] ) : gmdate( 'c' ),
			'source'          => isset( $event['source'] ) ? sanitize_key( (string) $event['source'] ) : 'unknown',
			'event'           => isset( $event['event'] ) ? sanitize_key( (string) $event['event'] ) : 'unknown',
			'result'          => isset( $event['result'] ) ? sanitize_key( (string) $event['result'] ) : 'unknown',
			'actor_user_id'   => isset( $event['actor_user_id'] ) ? absint( $event['actor_user_id'] ) : 0,
			'message_digest'  => $this->digest_token( $message ),
			'context_digest'  => $this->digest_token( wp_json_encode( $this->extract_context( $event ) ) ),
			'prev_hash'       => $prev_hash,
			'recorded_at_utc' => gmdate( 'c' ),
		);
		$record['hash'] = $this->record_hash( $record );
		$entries[]      = $record;

		$entry_count = count( $entries );
		while ( $entry_count > $max ) {
			$removed = array_shift( $entries );
			if ( is_array( $removed ) && ! empty( $removed['hash'] ) ) {
				$anchor = sanitize_text_field( (string) $removed['hash'] );
			}
			--$entry_count;
		}

		update_option(
			self::OPTION_KEY,
			array(
				'anchor_hash' => $anchor,
				'entries'     => array_values( $entries ),
			),
			false
		);
	}

	/**
	 * Returns recent evidence entries (newest first).
	 *
	 * @param int $limit Maximum items.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent_entries( int $limit = 10 ): array {
		$limit   = max( 1, absint( $limit ) );
		$payload = self::get_payload();
		$entries = $payload['entries'];
		if ( empty( $entries ) ) {
			return array();
		}

		return array_reverse( array_slice( $entries, -1 * $limit ) );
	}

	/**
	 * Verifies hash-chain integrity.
	 *
	 * @return array<string,mixed>
	 */
	public static function verify_chain(): array {
		$payload  = self::get_payload();
		$entries  = $payload['entries'];
		$anchor   = $payload['anchor_hash'];
		$issues   = array();
		$previous = $anchor;

		foreach ( $entries as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				$issues[] = 'invalid_entry_' . absint( $index );
				continue;
			}

			$entry_prev = isset( $entry['prev_hash'] ) ? sanitize_text_field( (string) $entry['prev_hash'] ) : '';
			if ( $entry_prev !== $previous ) {
				$issues[] = 'chain_break_' . absint( $index );
			}

			$expected = self::record_hash_static( $entry );
			$current  = isset( $entry['hash'] ) ? sanitize_text_field( (string) $entry['hash'] ) : '';
			if ( '' === $current || ! hash_equals( $expected, $current ) ) {
				$issues[] = 'hash_mismatch_' . absint( $index );
			}

			$previous = $current;
		}

		return array(
			'valid'       => empty( $issues ),
			'issues'      => $issues,
			'entry_count' => count( $entries ),
			'anchor_hash' => $anchor,
			'head_hash'   => $previous,
		);
	}

	/**
	 * Returns normalized option payload.
	 *
	 * @return array{anchor_hash:string,entries:array<int,array<string,mixed>>}
	 */
	private static function get_payload(): array {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			return array(
				'anchor_hash' => '',
				'entries'     => array(),
			);
		}

		$anchor = isset( $raw['anchor_hash'] ) ? sanitize_text_field( (string) $raw['anchor_hash'] ) : '';
		$items  = isset( $raw['entries'] ) && is_array( $raw['entries'] ) ? $raw['entries'] : array();
		$list   = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$list[] = $item;
			}
		}

		return array(
			'anchor_hash' => $anchor,
			'entries'     => $list,
		);
	}

	/**
	 * Extracts non-sensitive context dimensions for digesting.
	 *
	 * @param array<string,mixed> $event Event payload.
	 * @return array<string,int>
	 */
	private function extract_context( array $event ): array {
		return array(
			'client_user_id' => isset( $event['client_user_id'] ) ? absint( $event['client_user_id'] ) : 0,
			'document_id'    => isset( $event['document_id'] ) ? absint( $event['document_id'] ) : 0,
		);
	}

	/**
	 * Returns deterministic digest token.
	 *
	 * @param string $value Source value.
	 * @return string
	 */
	private function digest_token( string $value ): string {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}

		return hash_hmac( 'sha256', $value, wp_salt( 'secure_auth' ) . '|uplinkportal_evidence_digest_v1' );
	}

	/**
	 * Returns record hash for one entry.
	 *
	 * @param array<string,mixed> $record Entry payload.
	 * @return string
	 */
	private function record_hash( array $record ): string {
		return self::record_hash_static( $record );
	}

	/**
	 * Returns record hash for one entry (static helper).
	 *
	 * @param array<string,mixed> $record Entry payload.
	 * @return string
	 */
	private static function record_hash_static( array $record ): string {
		$canonical = array(
			'time'            => isset( $record['time'] ) ? sanitize_text_field( (string) $record['time'] ) : '',
			'source'          => isset( $record['source'] ) ? sanitize_key( (string) $record['source'] ) : '',
			'event'           => isset( $record['event'] ) ? sanitize_key( (string) $record['event'] ) : '',
			'result'          => isset( $record['result'] ) ? sanitize_key( (string) $record['result'] ) : '',
			'actor_user_id'   => isset( $record['actor_user_id'] ) ? absint( $record['actor_user_id'] ) : 0,
			'message_digest'  => isset( $record['message_digest'] ) ? sanitize_text_field( (string) $record['message_digest'] ) : '',
			'context_digest'  => isset( $record['context_digest'] ) ? sanitize_text_field( (string) $record['context_digest'] ) : '',
			'prev_hash'       => isset( $record['prev_hash'] ) ? sanitize_text_field( (string) $record['prev_hash'] ) : '',
			'recorded_at_utc' => isset( $record['recorded_at_utc'] ) ? sanitize_text_field( (string) $record['recorded_at_utc'] ) : '',
		);

		return hash_hmac( 'sha256', wp_json_encode( $canonical ), wp_salt( 'logged_in' ) . '|uplinkportal_evidence_chain_v1' );
	}
}
