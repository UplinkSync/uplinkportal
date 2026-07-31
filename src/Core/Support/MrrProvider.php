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
 * Source chain, first match wins:
 * 1. A value already supplied by an earlier billing integration on the filter
 *    (e.g. WooClover Sync hooking before priority 10).
 * 2. Active WooCommerce Subscriptions — the owner-designated source of truth
 *    (decision 2026-07-30) — normalized to a monthly amount. Rows come from the
 *    paid extension's `wcs_get_subscriptions()` when present, or from the
 *    `uplinkportal_woocommerce_subscriptions` filter (free plugin / adapter).
 * 3. The `uplinkportal_manual_mrr` option — an MSP-entered interim value.
 */
class MrrProvider {

	/**
	 * Option storing the MSP-entered interim MRR (USD).
	 *
	 * @var string
	 */
	public const MANUAL_MRR_OPTION = 'uplinkportal_manual_mrr';

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

		$subscriptions_mrr = $this->get_subscriptions_mrr();
		if ( $subscriptions_mrr > 0.0 ) {
			return $subscriptions_mrr;
		}

		return $this->get_manual_mrr();
	}

	/**
	 * Returns the machine key of the source currently supplying MRR.
	 *
	 * @return string 'integration', 'woocommerce_subscriptions', 'manual', or 'none'.
	 */
	public function get_active_source(): string {
		if ( $this->get_subscriptions_mrr() > 0.0 ) {
			return 'woocommerce_subscriptions';
		}

		if ( $this->get_manual_mrr() > 0.0 ) {
			return 'manual';
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
	 * Returns active subscription objects for the MRR sum. Overridable for tests.
	 *
	 * WooCommerce Subscriptions is the source of truth (owner decision
	 * 2026-07-30). The paid extension's `wcs_get_subscriptions()` is used when
	 * present, but the result is passed through the
	 * `uplinkportal_woocommerce_subscriptions` filter so a free subscriptions
	 * plugin — or any billing adapter — can supply the active-subscription rows
	 * instead. Each row must expose `get_total()`, `get_billing_period()`, and
	 * `get_billing_interval()`; rows missing those accessors are skipped.
	 *
	 * @return array<int,object>
	 */
	protected function get_active_woocommerce_subscriptions(): array {
		$subscriptions = array();

		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$result        = \wcs_get_subscriptions(
				array(
					'subscription_status'    => 'active',
					'subscriptions_per_page' => -1,
				)
			);
			$subscriptions = is_array( $result ) ? array_values( $result ) : array();
		}

		/**
		 * Filters the active subscription rows summed into MRR.
		 *
		 * Lets a free WooCommerce Subscriptions plugin or billing adapter supply
		 * rows when the paid extension is absent. Each row must expose
		 * `get_total()`, `get_billing_period()`, and `get_billing_interval()`.
		 *
		 * @param array<int,object> $subscriptions Active subscription objects.
		 */
		$subscriptions = apply_filters( 'uplinkportal_woocommerce_subscriptions', $subscriptions );

		return is_array( $subscriptions ) ? array_values( $subscriptions ) : array();
	}

	/**
	 * Returns the MSP-entered interim MRR option value.
	 *
	 * @return float
	 */
	private function get_manual_mrr(): float {
		$value = get_option( self::MANUAL_MRR_OPTION, 0 );

		return is_numeric( $value ) ? max( 0.0, (float) $value ) : 0.0;
	}
}
