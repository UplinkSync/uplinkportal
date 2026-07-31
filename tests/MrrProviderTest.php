<?php
/**
 * MRR provider tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\MrrProvider;

/**
 * Tests for the hiring-gate MRR provider source chain.
 */
class MrrProviderTest extends TestCase {
	/**
	 * Resets stub option and filter state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
		$GLOBALS['uplinkportal_test_filters']    = array();
		$GLOBALS['uplinkportal_test_post_types'] = array();
		$GLOBALS['uplinkportal_test_posts']      = array();
		$GLOBALS['uplinkportal_test_post_meta']  = array();
	}

	/**
	 * Builds a provider whose subscription source returns the given rows.
	 *
	 * @param array<int,object> $subscriptions Subscription doubles.
	 * @return MrrProvider
	 */
	private function make_provider( array $subscriptions = array() ): MrrProvider {
		return new class( $subscriptions ) extends MrrProvider {
			/**
			 * Injected subscription doubles.
			 *
			 * @var array<int,object>
			 */
			private array $subscriptions;

			/**
			 * Constructor.
			 *
			 * @param array<int,object> $subscriptions Subscription doubles.
			 */
			public function __construct( array $subscriptions ) {
				$this->subscriptions = $subscriptions;
			}

			/**
			 * Returns the injected doubles instead of querying WooCommerce.
			 *
			 * @return array<int,object>
			 */
			protected function get_active_woocommerce_subscriptions(): array {
				return $this->subscriptions;
			}
		};
	}

	/**
	 * Builds a subscription double.
	 *
	 * @param float  $total    Renewal total.
	 * @param string $period   Billing period.
	 * @param int    $interval Billing interval.
	 * @return object
	 */
	private function make_subscription( float $total, string $period = 'month', int $interval = 1 ): object {
		return new class( $total, $period, $interval ) {
			/**
			 * Constructor.
			 *
			 * @param float  $total    Renewal total.
			 * @param string $period   Billing period.
			 * @param int    $interval Billing interval.
			 */
			public function __construct( private float $total, private string $period, private int $interval ) {}

			/**
			 * Returns the renewal total.
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
	 * Ensures an upstream integration value wins over local sources.
	 *
	 * @return void
	 */
	public function test_upstream_integration_value_takes_precedence(): void {
		$provider = $this->make_provider( array( $this->make_subscription( 500.0 ) ) );

		$this->assertSame( 12345.0, $provider->filter_mrr( 12345.0 ) );
		$this->assertSame( 12345.5, $provider->filter_mrr( '12345.5' ) );
	}

	/**
	 * Ensures subscriptions are normalized to monthly amounts and summed.
	 *
	 * @return void
	 */
	public function test_subscriptions_normalized_to_monthly(): void {
		$provider = $this->make_provider(
			array(
				$this->make_subscription( 1000.0, 'month', 1 ),
				$this->make_subscription( 1200.0, 'year', 1 ),
				$this->make_subscription( 600.0, 'month', 3 ),
				$this->make_subscription( 100.0, 'week', 1 ),
			)
		);

		$this->assertEqualsWithDelta( 1000.0 + 100.0 + 200.0 + 434.8125, $provider->filter_mrr( 0.0 ), 0.001 );
		$this->assertSame( 'woocommerce_subscriptions', $provider->get_active_source() );
	}

	/**
	 * Ensures malformed, zero, and unknown-period subscriptions are skipped.
	 *
	 * @return void
	 */
	public function test_invalid_subscriptions_are_skipped(): void {
		$provider = $this->make_provider(
			array(
				new \stdClass(),
				$this->make_subscription( 0.0 ),
				$this->make_subscription( 50.0, 'fortnight' ),
				$this->make_subscription( 250.0 ),
			)
		);

		$this->assertSame( 250.0, $provider->filter_mrr( 0.0 ) );
	}
	/**
	 * Ensures the manual option is the fallback and non-numeric values clamp to zero.
	 *
	 * @return void
	 */
	public function test_manual_option_fallback_and_sanitization(): void {
		$provider = $this->make_provider();

		$this->assertSame( 0.0, $provider->filter_mrr( 0.0 ) );
		$this->assertSame( 'none', $provider->get_active_source() );

		$GLOBALS['uplinkportal_test_wp_options'][ MrrProvider::MANUAL_MRR_OPTION ] = '4200.75';
		$this->assertSame( 4200.75, $provider->filter_mrr( 0.0 ) );
		$this->assertSame( 'manual', $provider->get_active_source() );

		$GLOBALS['uplinkportal_test_wp_options'][ MrrProvider::MANUAL_MRR_OPTION ] = 'not-a-number';
		$this->assertSame( 0.0, $provider->filter_mrr( 0.0 ) );

		$GLOBALS['uplinkportal_test_wp_options'][ MrrProvider::MANUAL_MRR_OPTION ] = -900;
		$this->assertSame( 0.0, $provider->filter_mrr( 0.0 ) );
	}

	/**
	 * Ensures a free subscriptions plugin can supply rows via the
	 * `uplinkportal_woocommerce_subscriptions` filter when the paid extension's
	 * `wcs_get_subscriptions()` is absent (owner decision 2026-07-30).
	 *
	 * @return void
	 */
	public function test_free_plugin_supplies_subscriptions_via_filter(): void {
		$provider = new MrrProvider();

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_woocommerce_subscriptions'] =
			fn() => array( $this->make_subscription( 750.0, 'month', 1 ) );

		$this->assertSame( 750.0, $provider->filter_mrr( 0.0 ) );
		$this->assertSame( 'woocommerce_subscriptions', $provider->get_active_source() );
	}

	/**
	 * Ensures the built-in free-plugin adapter reads active subscriptions from an
	 * installed free plugin (WebToffee) and normalizes them to monthly USD when
	 * the paid `wcs_get_subscriptions()` is absent (***-406).
	 *
	 * @return void
	 */
	public function test_free_plugin_adapter_reads_installed_subscriptions(): void {
		$GLOBALS['uplinkportal_test_post_types'] = array( 'subscrpt_order' );
		$GLOBALS['uplinkportal_test_posts']      = array(
			'subscrpt_order' => array(
				101 => true,
				102 => true,
			),
		);
		$GLOBALS['uplinkportal_test_post_meta']  = array(
			101 => array(
				'_subscrpt_recurring_amount' => '1000',
				'_subscrpt_timing_per'       => 'month',
				'_subscrpt_timing_option'    => '1',
			),
			102 => array(
				'_subscrpt_recurring_amount' => '1200',
				'_subscrpt_timing_per'       => 'year',
				'_subscrpt_timing_option'    => '1',
			),
		);

		$provider = new MrrProvider();

		$this->assertEqualsWithDelta( 1000.0 + 100.0, $provider->filter_mrr( 0.0 ), 0.001 );
		$this->assertSame( 'woocommerce_subscriptions', $provider->get_active_source() );
	}

	/**
	 * Ensures the free-plugin adapter is skipped when no supported plugin is
	 * installed, so the manual option remains the fallback.
	 *
	 * @return void
	 */
	public function test_free_plugin_adapter_absent_falls_back_to_manual(): void {
		$GLOBALS['uplinkportal_test_wp_options'][ MrrProvider::MANUAL_MRR_OPTION ] = '3300';

		$provider = new MrrProvider();

		$this->assertSame( 3300.0, $provider->filter_mrr( 0.0 ) );
		$this->assertSame( 'manual', $provider->get_active_source() );
	}

	/**
	 * Ensures a registered provider feeds live MRR through the real filter path
	 * and advances the sustained-month gate option.
	 *
	 * @return void
	 */
	public function test_registered_provider_advances_sustained_month_gate(): void {
		$store = $this->createMock( \UplinkPortal\Core\Support\ClientProfileStore::class );
		$store->method( 'get_client_workspace_users' )->willReturn( array() );
		$service = new \UplinkPortal\Core\Support\HiringTriggerService( $store );

		$provider = $this->make_provider( array( $this->make_subscription( 12000.0 ) ) );
		$provider->register_hooks();

		$this->assertSame( 12000.0, $service->get_current_mrr() );
		$this->assertSame( 'met', $service->get_gate_status() );
		$this->assertSame(
			gmdate( 'Y-m' ),
			$GLOBALS['uplinkportal_test_wp_options']['uplinkportal_mrr_sustained_month']
		);
	}
}
