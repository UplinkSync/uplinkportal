<?php
/**
 * EntitlementEnforcer integration tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\EntitlementEnforcer;
use UplinkPortal\Core\Support\EntitlementService;

/**
 * Tests for EntitlementEnforcer.
 */
class EntitlementEnforcerTest extends TestCase {
	/**
	 * Resets static caches and global state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		EntitlementService::reset_request_cache();
		$GLOBALS['uplinkportal_test_filters'] = array();
	}

	// -----------------------------------------------------------------------
	// is_execution_allowed()
	// -----------------------------------------------------------------------

	/**
	 * Custom provider is always allowed regardless of entitlement state.
	 *
	 * @return void
	 */
	public function test_custom_provider_always_allowed(): void {
		$service  = $this->build_service_with_entitlement( null );
		$enforcer = new EntitlementEnforcer( $service );

		$this->assertTrue( $enforcer->is_execution_allowed( 1, 'finance', 'custom' ) );
	}

	/**
	 * Missing entitlement blocks execution.
	 *
	 * @return void
	 */
	public function test_missing_entitlement_blocks_execution(): void {
		$service  = $this->build_service_with_entitlement( null );
		$enforcer = new EntitlementEnforcer( $service );

		$this->assertFalse( $enforcer->is_execution_allowed( 1, 'finance', 'quickbooks_online' ) );
	}

	/**
	 * Active entitlement allows execution.
	 *
	 * @return void
	 */
	public function test_active_entitlement_allows_execution(): void {
		$service  = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_ACTIVE,
				'grace_until' => null,
			)
		);
		$enforcer = new EntitlementEnforcer( $service );

		$this->assertTrue( $enforcer->is_execution_allowed( 1, 'finance', 'quickbooks_online' ) );
	}

	/**
	 * Suspended entitlement blocks execution.
	 *
	 * @return void
	 */
	public function test_suspended_entitlement_blocks_execution(): void {
		$service  = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_SUSPENDED,
				'grace_until' => null,
			)
		);
		$enforcer = new EntitlementEnforcer( $service );

		$this->assertFalse( $enforcer->is_execution_allowed( 1, 'finance', 'quickbooks_online' ) );
	}

	/**
	 * Grace entitlement within the 7-day window allows execution.
	 *
	 * @return void
	 */
	public function test_grace_within_window_allows_execution(): void {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$service  = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_GRACE,
				'grace_until' => gmdate( 'Y-m-d H:i:s', time() + 3 * 86400 ),
			)
		);
		$enforcer = new EntitlementEnforcer( $service );

		$this->assertTrue( $enforcer->is_execution_allowed( 1, 'finance', 'quickbooks_online' ) );
	}

	/**
	 * Grace entitlement past the 7-day window blocks execution.
	 *
	 * @return void
	 */
	public function test_grace_past_window_blocks_execution(): void {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$service  = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_GRACE,
				'grace_until' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
			)
		);
		$enforcer = new EntitlementEnforcer( $service );

		$this->assertFalse( $enforcer->is_execution_allowed( 1, 'finance', 'quickbooks_online' ) );
	}

	// -----------------------------------------------------------------------
	// enforce_execution_gate()
	// -----------------------------------------------------------------------

	/**
	 * Enforce_execution_gate() returns true for active entitlement.
	 *
	 * @return void
	 */
	public function test_enforce_gate_returns_true_for_active(): void {
		$service  = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_ACTIVE,
				'grace_until' => null,
			)
		);
		$enforcer = new EntitlementEnforcer( $service );
		$result   = $enforcer->enforce_execution_gate( 1, 'finance', 'quickbooks_online', 'test' );

		$this->assertTrue( $result );
	}

	/**
	 * Enforce_execution_gate() returns WP_Error for suspended entitlement.
	 *
	 * @return void
	 */
	public function test_enforce_gate_returns_wp_error_for_suspended(): void {
		$service  = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_SUSPENDED,
				'grace_until' => null,
			)
		);
		$enforcer = new EntitlementEnforcer( $service );
		$result   = $enforcer->enforce_execution_gate( 1, 'finance', 'quickbooks_online', 'test' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'entitlement_suspended', $result->get_error_code() );
	}

	/**
	 * Enforce_execution_gate() returns WP_Error with grace_period_expired code for expired grace.
	 *
	 * @return void
	 */
	public function test_enforce_gate_returns_grace_expired_error(): void {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$service  = $this->build_service_with_entitlement(
			array(
				'status'      => EntitlementService::STATUS_GRACE,
				'grace_until' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
			)
		);
		$enforcer = new EntitlementEnforcer( $service );
		$result   = $enforcer->enforce_execution_gate( 1, 'finance', 'quickbooks_online', 'test' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'grace_period_expired', $result->get_error_code() );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Builds an EntitlementService subclass that returns a fixed entitlement payload.
	 *
	 * @param array<string,mixed>|null $entitlement Fixed entitlement or null for missing.
	 * @return EntitlementService
	 */
	private function build_service_with_entitlement( ?array $entitlement ): EntitlementService {
		return new class( $entitlement ) extends EntitlementService {
			/**
			 * Fixed entitlement data or null if missing.
			 *
			 * @var array<string,mixed>|null
			 */
			private ?array $entitlement;

			/**
			 * Constructor.
			 *
			 * @param array<string,mixed>|null $entitlement Fixed entitlement.
			 */
			public function __construct( ?array $entitlement ) {
				parent::__construct();
				$this->entitlement = $entitlement;
			}

			/**
			 * Returns fixed entitlement for all queries.
			 *
			 * @param int    $client_id   Client id.
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
