<?php
/**
 * Entitlement service tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\EntitlementService;

/**
 * Tests for EntitlementService.
 */
class EntitlementServiceTest extends TestCase {
	/**
	 * Ensures status constants are stable.
	 *
	 * @return void
	 */
	public function test_status_constants_are_stable(): void {
		$this->assertSame( 'active', EntitlementService::STATUS_ACTIVE );
		$this->assertSame( 'grace', EntitlementService::STATUS_GRACE );
		$this->assertSame( 'suspended', EntitlementService::STATUS_SUSPENDED );
		$this->assertSame( 'canceled', EntitlementService::STATUS_CANCELED );
	}

	/**
	 * Ensures statuses() exposes all supported status keys.
	 *
	 * @return void
	 */
	public function test_statuses_returns_expected_values(): void {
		$this->assertSame(
			array( 'active', 'grace', 'suspended', 'canceled' ),
			EntitlementService::statuses()
		);
	}

	/**
	 * Ensures is_entitled_now returns false for non-existent entitlements.
	 *
	 * @return void
	 */
	public function test_is_entitled_now_returns_false_for_missing_entitlement(): void {
		$service = new EntitlementService();

		$this->assertFalse( $service->is_entitled_now( 999999, 'finance', 'quickbooks_online' ) );
	}

	/**
	 * Ensures is_locked_now returns true for non-existent entitlements.
	 *
	 * @return void
	 */
	public function test_is_locked_now_returns_true_for_missing_entitlement(): void {
		$service = new EntitlementService();

		$this->assertTrue( $service->is_locked_now( 999999, 'finance', 'quickbooks_online' ) );
	}

	/**
	 * Ensures grace_info returns default for missing entitlement.
	 *
	 * @return void
	 */
	public function test_get_grace_info_returns_default_for_missing_entitlement(): void {
		$service = new EntitlementService();
		$info    = $service->get_grace_info( 999999, 'finance', 'quickbooks_online' );

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'in_grace', $info );
		$this->assertArrayHasKey( 'grace_until', $info );
		$this->assertArrayHasKey( 'days_remaining', $info );
		$this->assertFalse( $info['in_grace'] );
		$this->assertNull( $info['grace_until'] );
		$this->assertNull( $info['days_remaining'] );
	}

	/**
	 * Ensures get_client_entitlements returns empty array for invalid client.
	 *
	 * @return void
	 */
	public function test_get_client_entitlements_returns_empty_for_invalid_client(): void {
		$service      = new EntitlementService();
		$entitlements = $service->get_client_entitlements( 0 );

		$this->assertIsArray( $entitlements );
		$this->assertEmpty( $entitlements );
	}

	/**
	 * Ensures status summary defaults are returned when entitlement is missing.
	 *
	 * @return void
	 */
	public function test_status_summary_defaults_when_missing(): void {
		$service = new EntitlementService();
		$status  = $service->get_status_summary( 999999, 'finance', 'quickbooks_online' );

		$this->assertSame( 'none', $status['status'] );
		$this->assertFalse( $status['entitled'] );
		$this->assertFalse( $status['in_grace'] );
		$this->assertNull( $status['grace_until'] );
		$this->assertNull( $status['days_remaining'] );
		$this->assertSame( 'missing', $status['blocked_reason'] );
	}

	/**
	 * Ensures active status is entitled and unblocked.
	 *
	 * @return void
	 */
	public function test_status_summary_active_is_entitled(): void {
		$service = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_ACTIVE,
				'grace_until' => null,
			)
		);

		$status = $service->get_status_summary( 42, 'finance', 'quickbooks_online' );

		$this->assertTrue( $status['entitled'] );
		$this->assertFalse( $status['in_grace'] );
		$this->assertSame( '', $status['blocked_reason'] );
	}

	/**
	 * Ensures grace status within window stays entitled.
	 *
	 * @return void
	 */
	public function test_status_summary_grace_within_window_is_entitled(): void {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$service = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_GRACE,
				'grace_until' => gmdate( 'Y-m-d H:i:s', time() + 2 * 86400 ),
			)
		);

		$status = $service->get_status_summary( 42, 'finance', 'quickbooks_online' );

		$this->assertTrue( $status['entitled'] );
		$this->assertTrue( $status['in_grace'] );
		$this->assertSame( '', $status['blocked_reason'] );
		$this->assertIsInt( $status['days_remaining'] );
		$this->assertGreaterThanOrEqual( 1, $status['days_remaining'] );
	}

	/**
	 * Ensures expired grace status is blocked.
	 *
	 * @return void
	 */
	public function test_status_summary_grace_expired_is_blocked(): void {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$service = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_GRACE,
				'grace_until' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
			)
		);

		$status = $service->get_status_summary( 42, 'finance', 'quickbooks_online' );

		$this->assertFalse( $status['entitled'] );
		$this->assertFalse( $status['in_grace'] );
		$this->assertSame( 'grace_expired', $status['blocked_reason'] );
	}

	/**
	 * Builds an entitlement service with a fixed entitlement payload.
	 *
	 * @param array<string,mixed>|null $entitlement Entitlement payload.
	 * @return EntitlementService
	 */
	private function build_service_with_entitlement( ?array $entitlement ): EntitlementService {
		return new class( $entitlement ) extends EntitlementService {
			/**
			 * Mock entitlement payload.
			 *
			 * @var array<string,mixed>|null
			 */
			private ?array $entitlement;

			/**
			 * Constructor.
			 *
			 * @param array<string,mixed>|null $entitlement Entitlement payload.
			 */
			public function __construct( ?array $entitlement ) {
				parent::__construct();
				$this->entitlement = $entitlement;
			}

			/**
			 * Returns fixed entitlement payload for tests.
			 *
			 * @param int    $client_id Client id.
			 * @param string $module_slug Module slug.
			 * @param string $provider_id Provider id.
			 * @return array<string,mixed>|null
			 */
			public function get_entitlement( int $client_id, string $module_slug, string $provider_id ): ?array {
				return $this->entitlement;
			}
		};
	}
}
