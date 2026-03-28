<?php
/**
 * ProviderCatalogService transient cache tests — D2.1.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Services\ProviderCatalogService;
use UplinkPortal\Core\Support\Options;

/**
 * Verifies that get_providers_for_module() uses transient caching.
 */
class ProviderCatalogServiceCacheTest extends TestCase {
	/**
	 * Resets transient globals and filters before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_transients'] = array();
		$GLOBALS['uplinkportal_test_filters']    = array();
	}

	/**
	 * Resets transient globals and filters after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['uplinkportal_test_transients'] = array();
		$GLOBALS['uplinkportal_test_filters']    = array();
		parent::tearDown();
	}

	/**
	 * Pre-seeded transient data is merged into the provider list.
	 *
	 * @return void
	 */
	public function test_cached_remote_providers_appear_in_module_list(): void {
		$cache_key = 'uplinkportal_provider_catalog_finance';

		$GLOBALS['uplinkportal_test_transients'][ $cache_key ] = array(
			array(
				'id'          => 'acme_finance',
				'label'       => 'Acme Finance',
				'locked'      => false,
				'auth_types'  => array( 'api_key' ),
				'defaults'    => array(
					'scopes'           => array(),
					'endpoint_alias'   => '',
					'retention_policy' => 'default',
				),
				'helper_text' => null,
			),
		);

		$options = $this->createMock( Options::class );
		$service = new ProviderCatalogService( $options );

		$providers    = $service->get_providers_for_module( 'finance' );
		$provider_ids = array_column( $providers, 'id' );

		$this->assertContains( 'acme_finance', $provider_ids, 'Cached remote provider must appear in the result list' );
	}

	/**
	 * Calling invalidate_catalog_cache() removes the transient for the given module.
	 *
	 * @return void
	 */
	public function test_invalidate_catalog_cache_removes_transient(): void {
		$cache_key = 'uplinkportal_provider_catalog_finance';
		$GLOBALS['uplinkportal_test_transients'][ $cache_key ] = array( array( 'id' => 'stale_provider' ) );

		$options = $this->createMock( Options::class );
		$service = new ProviderCatalogService( $options );

		$service->invalidate_catalog_cache( 'finance' );

		$this->assertArrayNotHasKey( $cache_key, $GLOBALS['uplinkportal_test_transients'], 'Transient must be removed after invalidation' );
	}

	/**
	 * Cache key includes the module slug so different modules are cached independently.
	 *
	 * @return void
	 */
	public function test_cache_keys_are_module_specific(): void {
		$GLOBALS['uplinkportal_test_transients']['uplinkportal_provider_catalog_finance']    = array(
			array(
				'id'          => 'finance_remote',
				'label'       => 'Finance Remote',
				'locked'      => false,
				'auth_types'  => array( 'api_key' ),
				'defaults'    => array(
					'scopes'           => array(),
					'endpoint_alias'   => '',
					'retention_policy' => 'default',
				),
				'helper_text' => null,
			),
		);
		$GLOBALS['uplinkportal_test_transients']['uplinkportal_provider_catalog_it_support'] = array(
			array(
				'id'          => 'it_remote',
				'label'       => 'IT Remote',
				'locked'      => false,
				'auth_types'  => array( 'api_key' ),
				'defaults'    => array(
					'scopes'           => array(),
					'endpoint_alias'   => '',
					'retention_policy' => 'default',
				),
				'helper_text' => null,
			),
		);

		$options = $this->createMock( Options::class );
		$service = new ProviderCatalogService( $options );

		$finance_ids    = array_column( $service->get_providers_for_module( 'finance' ), 'id' );
		$it_support_ids = array_column( $service->get_providers_for_module( 'it_support' ), 'id' );

		$this->assertContains( 'finance_remote', $finance_ids );
		$this->assertNotContains( 'it_remote', $finance_ids, 'Finance cache must not bleed into it_support results' );
		$this->assertContains( 'it_remote', $it_support_ids );
		$this->assertNotContains( 'finance_remote', $it_support_ids, 'it_support cache must not bleed into finance results' );
	}

	/**
	 * Custom provider is always appended regardless of cache contents.
	 *
	 * @return void
	 */
	public function test_custom_provider_always_present(): void {
		$GLOBALS['uplinkportal_test_transients']['uplinkportal_provider_catalog_finance'] = array();

		$options = $this->createMock( Options::class );
		$service = new ProviderCatalogService( $options );

		$providers    = $service->get_providers_for_module( 'finance' );
		$provider_ids = array_column( $providers, 'id' );

		$this->assertContains( 'custom', $provider_ids, 'Custom provider must always be present' );
	}
}
