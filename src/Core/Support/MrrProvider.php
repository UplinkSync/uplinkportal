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
 *    paid extension's `wcs_get_subscriptions()` when present, from a built-in
 *    adapter for common free subscriptions plugins (WebToffee, YITH), or from
 *    the `uplinkportal_woocommerce_subscriptions` filter (custom adapter).
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

		if ( array() === $subscriptions ) {
			$subscriptions = $this->get_free_plugin_subscriptions();
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
	 * Free WooCommerce subscription plugins mapped to their active-subscription
	 * custom post type and the meta keys holding total, period, and interval.
	 *
	 * Each entry: [ plugin sentinel (function/class), post_type, active statuses,
	 * total meta, period meta, interval meta ]. Sentinels keep the adapter
	 * public-core-safe — it only reads data a locally installed free plugin
	 * already exposes; nothing premium or secret-dependent.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private const FREE_PLUGIN_MAP = array(
		// WebToffee "Subscriptions for WooCommerce".
		'webtoffee'          => array(
			'sentinel'  => 'subscrpt_order',
			'sentinel_kind' => 'post_type',
			'post_type' => 'subscrpt_order',
			'statuses'  => array( 'active' ),
			'total'     => '_subscrpt_recurring_amount',
			'period'    => '_subscrpt_timing_per',
			'interval'  => '_subscrpt_timing_option',
		),
		// YITH WooCommerce Subscription (free).
		'yith'               => array(
			'sentinel'      => 'YITH_WC_Subscription',
			'sentinel_kind' => 'class',
			'post_type'     => 'ywsbs_subscription',
			'statuses'      => array( 'active' ),
			'total'         => '_price_is',
			'period'        => '_price_time_option',
			'interval'      => '_max_length',
		),
	);

	/**
	 * Builds normalized subscription rows from an installed free plugin.
	 *
	 * Detects a supported free subscriptions plugin, pulls its active
	 * subscription records via the overridable query seam, and wraps each into a
	 * row exposing `get_total()`, `get_billing_period()`, and
	 * `get_billing_interval()` — matching the accessors `get_subscriptions_mrr()`
	 * already normalizes. Used only when the paid extension is absent.
	 *
	 * @return array<int,object>
	 */
	private function get_free_plugin_subscriptions(): array {
		$rows = array();

		foreach ( self::FREE_PLUGIN_MAP as $config ) {
			if ( ! $this->free_plugin_active( $config ) ) {
				continue;
			}

			foreach ( $this->get_free_plugin_subscription_records( $config ) as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}

				$rows[] = $this->normalize_free_plugin_record(
					(float) ( $record['total'] ?? 0.0 ),
					(string) ( $record['period'] ?? '' ),
					(int) ( $record['interval'] ?? 1 )
				);
			}

			if ( array() !== $rows ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * Reports whether a mapped free plugin is present via its sentinel symbol.
	 *
	 * @param array<string,mixed> $config Plugin map entry.
	 * @return bool
	 */
	private function free_plugin_active( array $config ): bool {
		$sentinel = (string) ( $config['sentinel'] ?? '' );

		if ( '' === $sentinel ) {
			return false;
		}

		if ( 'class' === ( $config['sentinel_kind'] ?? '' ) ) {
			return class_exists( $sentinel );
		}

		return post_type_exists( $sentinel );
	}

	/**
	 * Queries active subscription records from an installed free plugin.
	 *
	 * Overridable seam for tests. Returns a list of records shaped as
	 * `[ 'total' => float, 'period' => string, 'interval' => int ]`. Reads only
	 * data the locally installed free plugin already stores — public-core-safe.
	 *
	 * @param array<string,mixed> $config Plugin map entry.
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_free_plugin_subscription_records( array $config ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => (string) ( $config['post_type'] ?? '' ),
				'post_status'    => (array) ( $config['statuses'] ?? array( 'active' ) ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		if ( ! is_array( $posts ) ) {
			return array();
		}

		$records = array();

		foreach ( $posts as $post_id ) {
			$records[] = array(
				'total'    => get_post_meta( (int) $post_id, (string) ( $config['total'] ?? '' ), true ),
				'period'   => get_post_meta( (int) $post_id, (string) ( $config['period'] ?? '' ), true ),
				'interval' => get_post_meta( (int) $post_id, (string) ( $config['interval'] ?? '' ), true ),
			);
		}

		return $records;
	}

	/**
	 * Wraps a free-plugin record in a row with the accessors MRR expects.
	 *
	 * @param float  $total    Recurring total.
	 * @param string $period   Billing period (day/week/month/year).
	 * @param int    $interval Billing interval.
	 * @return object
	 */
	private function normalize_free_plugin_record( float $total, string $period, int $interval ): object {
		return new class( $total, $period, max( 1, $interval ) ) {
			/**
			 * Constructor.
			 *
			 * @param float  $total    Recurring total.
			 * @param string $period   Billing period.
			 * @param int    $interval Billing interval.
			 */
			public function __construct( private float $total, private string $period, private int $interval ) {}

			/**
			 * Returns the recurring total.
			 *
			 * @return float
			 */
			public function get_total(): float {
				return $this->total;
			}

			/**
			 * Returns the billing period.
			 *
			 * @return string
			 */
			public function get_billing_period(): string {
				return $this->period;
			}

			/**
			 * Returns the billing interval.
			 *
			 * @return int
			 */
			public function get_billing_interval(): int {
				return $this->interval;
			}
		};
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
