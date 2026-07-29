<?php
/**
 * MRR provider for the Phase 1 hiring gate.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies live monthly recurring revenue to `uplinkportal_hiring_trigger_mrr`.
 *
 * Per owner decision (Doug, 2026-07-28) the single source of truth for MRR is
 * active WooCommerce Subscriptions. No manual/interim entry is used.
 *
 * Source chain, first match wins:
 * 1. A value already supplied by an earlier billing integration on the filter
 *    (e.g. WooClover Sync hooking before priority 10). Standard filter-chain
 *    courtesy so an authoritative upstream value is never discarded.
 * 2. Active WooCommerce Subscriptions, normalized to a monthly amount.
 */
class MrrProvider {

	/**
	 * Filter priority; billing integrations hooking earlier take precedence.
	 *
	 * @var int
	 */
	public const FILTER_PRIORITY = 10;

	/**
	 * Billing periods normalized to occurrences per month.
	 *
	 * @var array<string,float>
	 */
	private const PERIODS_PER_MONTH = array(
		'day'   => 30.4375,
		'week'  => 4.348125,
		'month' => 1.0,
		'year'  => 1.0 / 12.0,
	);

	/**
	 * Registers the MRR filter hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'uplinkportal_hiring_trigger_mrr', array( $this, 'filter_mrr' ), self::FILTER_PRIORITY );
	}

	/**
	 * Resolves the current MRR through the source chain.
	 *
	 * @param mixed $mrr Value from earlier filter callbacks (0.0 when none).
	 * @return float
	 */
	public function filter_mrr( $mrr ): float {
		if ( is_numeric( $mrr ) && (float) $mrr > 0.0 ) {
			return (float) $mrr;
		}

		return $this->get_subscriptions_mrr();
	}

	/**
	 * Returns the machine key of the source currently supplying MRR.
	 *
	 * @return string 'woocommerce_subscriptions' or 'none'.
	 */
	public function get_active_source(): string {
		if ( $this->get_subscriptions_mrr() > 0.0 ) {
			return 'woocommerce_subscriptions';
		}

		return 'none';
	}

	/**
	 * Sums active WooCommerce Subscriptions normalized to monthly USD.
	 *
	 * @return float
	 */
	private function get_subscriptions_mrr(): float {
		$total = 0.0;

		foreach ( $this->get_active_woocommerce_subscriptions() as $subscription ) {
			if ( ! is_object( $subscription )
				|| ! method_exists( $subscription, 'get_total' )
				|| ! method_exists( $subscription, 'get_billing_period' )
				|| ! method_exists( $subscription, 'get_billing_interval' ) ) {
				continue;
			}

			$amount   = (float) $subscription->get_total();
			$period   = strtolower( (string) $subscription->get_billing_period() );
			$interval = max( 1, (int) $subscription->get_billing_interval() );

			if ( $amount <= 0.0 || ! isset( self::PERIODS_PER_MONTH[ $period ] ) ) {
				continue;
			}

			$total += ( $amount / $interval ) * self::PERIODS_PER_MONTH[ $period ];
		}

		return $total;
	}

	/**
	 * Returns active WooCommerce Subscription objects, or an empty array when
	 * WooCommerce Subscriptions is not installed. Overridable for tests.
	 *
	 * @return array<int,object>
	 */
	protected function get_active_woocommerce_subscriptions(): array {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return array();
		}

		$subscriptions = \wcs_get_subscriptions(
			array(
				'subscription_status'    => 'active',
				'subscriptions_per_page' => -1,
			)
		);

		return is_array( $subscriptions ) ? array_values( $subscriptions ) : array();
	}
}
