<?php
/**
 * ModuleManager per-request cache tests — D1.2.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Support\Options;

/**
 * Verifies that can_user_access_module() uses per-request static caching.
 */
class ModuleManagerCacheTest extends TestCase {
	/**
	 * Resets the cache and capability globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		ModuleManager::reset_request_cache();
		$GLOBALS['uplinkportal_test_user_caps'] = array();
		$GLOBALS['uplinkportal_test_filters']   = array();
	}

	/**
	 * Resets the cache and capability globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		ModuleManager::reset_request_cache();
		$GLOBALS['uplinkportal_test_user_caps'] = array();
		$GLOBALS['uplinkportal_test_filters']   = array();
		parent::tearDown();
	}

	/**
	 * Cached result is returned even after the underlying capability is revoked.
	 *
	 * @return void
	 */
	public function test_cached_result_survives_capability_revocation(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get_model_policy_matrix' )->willReturn( array() );

		$manager = new ModuleManager( $options );

		// User 42 has 'client_access', which maps to 'custom_dummy' → 'read'.
		$GLOBALS['uplinkportal_test_user_caps'][42] = array( 'client_access' );

		$result_before = $manager->can_user_access_module( 42, 'custom_dummy', 'read' );
		$this->assertTrue( $result_before );

		// Revoke the capability.
		$GLOBALS['uplinkportal_test_user_caps'][42] = array();

		// Must still return true — served from cache.
		$result_cached = $manager->can_user_access_module( 42, 'custom_dummy', 'read' );
		$this->assertTrue( $result_cached, 'Cached true result must be returned even after capability revocation' );
	}

	/**
	 * After reset_request_cache() the fresh capability state is consulted.
	 *
	 * @return void
	 */
	public function test_reset_cache_reflects_revoked_capability(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get_model_policy_matrix' )->willReturn( array() );

		$manager = new ModuleManager( $options );

		$GLOBALS['uplinkportal_test_user_caps'][42] = array( 'client_access' );
		$manager->can_user_access_module( 42, 'custom_dummy', 'read' );

		// Revoke capability then clear cache.
		$GLOBALS['uplinkportal_test_user_caps'][42] = array();
		ModuleManager::reset_request_cache();

		$result_after_reset = $manager->can_user_access_module( 42, 'custom_dummy', 'read' );
		$this->assertFalse( $result_after_reset, 'After cache reset, revoked capability must return false' );
	}

	/**
	 * Different user IDs have separate cache entries.
	 *
	 * @return void
	 */
	public function test_different_users_have_independent_cache_entries(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get_model_policy_matrix' )->willReturn( array() );

		$manager = new ModuleManager( $options );

		$GLOBALS['uplinkportal_test_user_caps'][10] = array( 'client_access' );
		$GLOBALS['uplinkportal_test_user_caps'][20] = array();

		$this->assertTrue( $manager->can_user_access_module( 10, 'custom_dummy', 'read' ) );
		$this->assertFalse( $manager->can_user_access_module( 20, 'custom_dummy', 'read' ) );
	}
}
