<?php
/**
 * Phase 1 hiring-trigger metrics service.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the Phase 1 hiring-gate metrics: recurring-client count and MRR.
 *
 * The gate is met when either 5 active recurring clients exist or MRR has
 * reached $10,000 within the current calendar month. MRR flows from billing
 * integrations via the `uplinkportal_hiring_trigger_mrr` filter and defaults
 * to 0 until one is connected.
 */
class HiringTriggerService {

	/**
	 * Recurring-client count required to meet the gate.
	 *
	 * @var int
	 */
	public const RECURRING_CLIENT_TARGET = 5;

	/**
	 * MRR (USD) required to meet the gate.
	 *
	 * @var float
	 */
	public const MRR_TARGET = 10000.0;

	/**
	 * Ratio of either target at which the gate reads "approaching".
	 *
	 * @var float
	 */
	private const APPROACHING_RATIO = 0.6;

	/**
	 * Option key storing the most recent 'YYYY-MM' month in which MRR met target.
	 *
	 * @var string
	 */
	private const MRR_SUSTAINED_OPTION = 'uplinkportal_mrr_sustained_month';

	/**
	 * Client profile store.
	 *
	 * @var ClientProfileStore
	 */
	private ClientProfileStore $client_profiles;

	/**
	 * Constructor.
	 *
	 * @param ClientProfileStore $client_profiles Client profile store.
	 */
	public function __construct( ClientProfileStore $client_profiles ) {
		$this->client_profiles = $client_profiles;
	}

	/**
	 * Returns the count of clients with an active recurring contract.
	 *
	 * @return int
	 */
	public function get_recurring_client_count(): int {
		$count = 0;

		foreach ( $this->client_profiles->get_client_workspace_users() as $user ) {
			$user_id = isset( $user->ID ) ? absint( $user->ID ) : 0;
			if ( $user_id <= 0 ) {
				continue;
			}

			$profile = $this->client_profiles->get_profile_for_user( $user_id );
			$type    = isset( $profile['contract_type'] ) ? $profile['contract_type'] : 'none';
			$status  = isset( $profile['contract_status'] ) ? $profile['contract_status'] : 'lapsed';

			if ( 'recurring' === $type && 'active' === $status ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Returns current MRR in USD from the connected billing integration.
	 *
	 * @return float
	 */
	public function get_current_mrr(): float {
		/**
		 * Filters the current MRR (USD) used by the Phase 1 hiring gate.
		 *
		 * Billing integrations supply the real value; defaults to 0 until connected.
		 *
		 * @param float $mrr Current monthly recurring revenue.
		 */
		$mrr = apply_filters( 'uplinkportal_hiring_trigger_mrr', 0.0 );

		return is_numeric( $mrr ) ? max( 0.0, (float) $mrr ) : 0.0;
	}

	/**
	 * Returns the hiring-gate status: 'met', 'approaching', or 'not_met'.
	 *
	 * @return string
	 */
	public function get_gate_status(): string {
		$count         = $this->get_recurring_client_count();
		$mrr           = $this->get_current_mrr();
		$current_month = gmdate( 'Y-m' );

		if ( $mrr >= self::MRR_TARGET ) {
			update_option( self::MRR_SUSTAINED_OPTION, $current_month );
		}

		$mrr_sustained = get_option( self::MRR_SUSTAINED_OPTION, '' ) === $current_month;

		if ( $count >= self::RECURRING_CLIENT_TARGET || $mrr_sustained ) {
			return 'met';
		}

		$count_ratio = $count / self::RECURRING_CLIENT_TARGET;
		$mrr_ratio   = $mrr / self::MRR_TARGET;

		if ( $count_ratio >= self::APPROACHING_RATIO || $mrr_ratio >= self::APPROACHING_RATIO ) {
			return 'approaching';
		}

		return 'not_met';
	}
}
