<?php
/**
 * Data retention scheduler.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use UplinkPortal\Core\Security\CentralAuditJournal;
use UplinkPortal\Core\Security\CapabilityManager;

defined( 'ABSPATH' ) || exit;

/**
 * Applies retention policies to plugin-managed audit data.
 */
class DataRetentionScheduler {
	/**
	 * Daily purge cron hook.
	 *
	 * @var string
	 */
	private const CRON_HOOK = 'uplinkportal_retention_purge';

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Client documents store.
	 *
	 * @var ClientDocumentsStore
	 */
	private ClientDocumentsStore $client_documents;

	/**
	 * Constructor.
	 *
	 * @param Options              $options Options helper.
	 * @param ClientDocumentsStore $client_documents Client documents store.
	 */
	public function __construct( Options $options, ClientDocumentsStore $client_documents ) {
		$this->options          = $options;
		$this->client_documents = $client_documents;
	}

	/**
	 * Registers retention scheduler hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'schedule_if_needed' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_purge' ) );
	}

	/**
	 * Schedules daily retention task when missing.
	 *
	 * @return void
	 */
	public function schedule_if_needed(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
	}

	/**
	 * Clears scheduled retention tasks.
	 *
	 * @return void
	 */
	public static function clear_schedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Executes one retention purge cycle.
	 *
	 * @return void
	 */
	public function run_purge(): void {
		$model_retention_days = $this->resolve_model_retention_days();
		$audit_retention_days = $this->resolve_audit_retention_days( $model_retention_days );

		$deleted_capability_entries = CapabilityManager::purge_audit_log_older_than( $audit_retention_days );
		$deleted_document_entries   = $this->client_documents->purge_document_audit_entries_older_than( $audit_retention_days );
		$deleted_central_entries    = CentralAuditJournal::purge_older_than( $audit_retention_days );

		/**
		 * Fires after retention purge execution.
		 *
		 * @param array<string,mixed> $payload Purge summary payload.
		 */
		do_action(
			'uplinkportal_retention_purge_run',
			array(
				'model_retention_days'       => $model_retention_days,
				'audit_retention_days'       => $audit_retention_days,
				'capability_entries_deleted' => $deleted_capability_entries,
				'document_entries_deleted'   => $deleted_document_entries,
				'central_entries_deleted'    => $deleted_central_entries,
			)
		);
	}

	/**
	 * Resolves retention days by model from integration profile policy keys.
	 *
	 * @return array<string,int>
	 */
	private function resolve_model_retention_days(): array {
		$profiles = $this->options->get_integration_profiles();
		$days_map = array_fill_keys( IntegrationModelRegistry::ids(), 365 );

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$profile = isset( $profiles[ $model_id ] ) && is_array( $profiles[ $model_id ] )
				? $profiles[ $model_id ]
				: array();
			$policy  = isset( $profile['retention_policy'] ) ? sanitize_key( (string) $profile['retention_policy'] ) : 'default';

			$days_map[ $model_id ] = $this->retention_days_for_policy( $policy );
		}

		return $days_map;
	}

	/**
	 * Resolves retention days used for audit logs.
	 *
	 * @param array<string,int> $model_retention_days Model retention map.
	 * @return int
	 */
	private function resolve_audit_retention_days( array $model_retention_days ): int {
		$candidates = array_filter(
			$model_retention_days,
			static function ( $days ): bool {
				return is_int( $days ) && $days > 0;
			}
		);

		if ( empty( $candidates ) ) {
			return 365;
		}

		return max( 30, (int) min( $candidates ) );
	}

	/**
	 * Maps a retention policy key to day count.
	 *
	 * @param string $policy Retention policy key.
	 * @return int
	 */
	private function retention_days_for_policy( string $policy ): int {
		$policy = sanitize_key( $policy );
		$map    = array(
			'minimum'    => 30,
			'30d'        => 30,
			'90d'        => 90,
			'180d'       => 180,
			'1y'         => 365,
			'2y'         => 730,
			'3y'         => 1095,
			'5y'         => 1825,
			'7y'         => 2555,
			'default'    => 365,
			'indefinite' => 0,
			'none'       => 0,
		);

		return isset( $map[ $policy ] ) ? (int) $map[ $policy ] : 365;
	}
}
