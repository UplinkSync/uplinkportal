<?php
/**
 * EntitlementService per-request cache tests — D1.1.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\EntitlementService;

/**
 * Verifies that get_entitlement() uses per-request static caching.
 */
class EntitlementServiceCacheTest extends TestCase {
	/**
	 * Minimal wpdb mock that tracks get_row calls.
	 *
	 * @var object
	 */
	private object $wpdb_mock;

	/**
	 * Sets up a mock wpdb global and resets the cache.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		EntitlementService::reset_request_cache();

		$this->wpdb_mock = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public string $prefix = 'wp_';

			/**
			 * Number of get_row calls made.
			 *
			 * @var int
			 */
			public int $get_row_calls = 0;

			/**
			 * Minimal prepare stub — returns the query string (unchecked in tests).
			 *
			 * @param string $query  Query template.
			 * @param mixed  ...$args Bind values.
			 * @return string
			 */
			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			/**
			 * Returns a fixed active entitlement record and increments the call counter.
			 *
			 * @param string $query  SQL query.
			 * @param string $output Output format constant.
			 * @return array<string,mixed>
			 */
			public function get_row( string $query, string $output ): array {
				++$this->get_row_calls;

				return array(
					'id'               => 1,
					'client_id'        => 42,
					'module_slug'      => 'finance',
					'provider_id'      => 'quickbooks_online',
					'status'           => 'active',
					'grace_until'      => null,
					'subscription_ref' => null,
					'notes'            => null,
					'created_at'       => '2025-01-01 00:00:00',
					'updated_at'       => '2025-01-01 00:00:00',
				);
			}
		};

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->wpdb_mock;
	}

	/**
	 * Removes mock wpdb and resets the cache after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		EntitlementService::reset_request_cache();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Second call for the same key reads from cache and does not re-query the DB.
	 *
	 * @return void
	 */
	public function test_second_call_uses_cache_and_skips_db_query(): void {
		$service = new EntitlementService();

		$first  = $service->get_entitlement( 42, 'finance', 'quickbooks_online' );
		$second = $service->get_entitlement( 42, 'finance', 'quickbooks_online' );

		$this->assertSame( 1, $this->wpdb_mock->get_row_calls, 'DB should only be queried once for the same key' );
		$this->assertIsArray( $first );
		$this->assertSame( $first, $second, 'Both calls must return identical data' );
	}

	/**
	 * Different keys each trigger their own DB query.
	 *
	 * @return void
	 */
	public function test_different_keys_each_query_the_db(): void {
		$service = new EntitlementService();

		$service->get_entitlement( 42, 'finance', 'quickbooks_online' );
		$service->get_entitlement( 42, 'finance', 'xero' );

		$this->assertSame( 2, $this->wpdb_mock->get_row_calls, 'DB should be queried once per unique key' );
	}

	/**
	 * After reset_request_cache() a repeat call re-queries the DB.
	 *
	 * @return void
	 */
	public function test_reset_cache_forces_fresh_db_query(): void {
		$service = new EntitlementService();

		$service->get_entitlement( 42, 'finance', 'quickbooks_online' );

		EntitlementService::reset_request_cache();

		$service->get_entitlement( 42, 'finance', 'quickbooks_online' );

		$this->assertSame( 2, $this->wpdb_mock->get_row_calls, 'Cache reset must cause a fresh DB query' );
	}
}
