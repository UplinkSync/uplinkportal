<?php
/**
 * ServiceDefinitionStore tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Core\Services;

use PHPUnit\Framework\TestCase;

/**
 * Tests for ServiceDefinitionStore.
 */
class ServiceDefinitionStoreTest extends TestCase {

	/**
	 * Sets up globals used as WP option/filter/transient stores.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['uplinkportal_test_options']    = array();
		$GLOBALS['uplinkportal_test_filters']    = array();
		$GLOBALS['uplinkportal_test_transients'] = array();
	}

	/**
	 * Resets globals used as WP option/filter/transient stores.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['uplinkportal_test_options']    = array();
		$GLOBALS['uplinkportal_test_filters']    = array();
		$GLOBALS['uplinkportal_test_transients'] = array();
	}

	/**
	 * Default services are returned for the it_support module.
	 *
	 * @return void
	 */
	public function test_default_services_returns_array_for_it_support_module(): void {
		$store    = new ServiceDefinitionStore();
		$services = $store->get_services_for_module( 'it_support' );

		$this->assertIsArray( $services );
		$this->assertNotEmpty( $services );

		$slugs = array_column( $services, 'slug' );
		$this->assertContains( 'help_desk_support', $slugs );
		$this->assertContains( 'remote_desktop_support', $slugs );
	}

	/**
	 * Default services are returned for finance and projects modules.
	 *
	 * @return void
	 */
	public function test_default_services_for_finance_and_projects(): void {
		$store = new ServiceDefinitionStore();

		$finance  = $store->get_services_for_module( 'finance' );
		$projects = $store->get_services_for_module( 'projects' );

		$this->assertContains( 'invoice_management', array_column( $finance, 'slug' ) );
		$this->assertContains( 'project_tracking', array_column( $projects, 'slug' ) );
	}

	/**
	 * Empty array is returned for an unknown module slug.
	 *
	 * @return void
	 */
	public function test_unknown_module_slug_returns_empty_array(): void {
		$store    = new ServiceDefinitionStore();
		$services = $store->get_services_for_module( 'nonexistent_module_xyz' );

		$this->assertIsArray( $services );
		$this->assertEmpty( $services );
	}

	/**
	 * The uplinkportal_module_services filter allows adding custom service definitions.
	 *
	 * @return void
	 */
	public function test_filter_allows_custom_module_services(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_module_services'] = function ( array $defs, string $module_slug ): array {
			if ( 'custom_module' === $module_slug ) {
				$defs[] = array(
					'slug'        => 'custom_service',
					'name'        => 'Custom Service',
					'description' => 'A custom service added via filter.',
					'icon'        => 'dashicons-admin-generic',
				);
			}
			return $defs;
		};

		$store    = new ServiceDefinitionStore();
		$services = $store->get_services_for_module( 'custom_module' );

		$this->assertNotEmpty( $services );
		$this->assertContains( 'custom_service', array_column( $services, 'slug' ) );
	}

	/**
	 * MSP-saved overrides replace the hard-coded defaults when stored in options.
	 *
	 * @return void
	 */
	public function test_msp_overrides_replace_defaults(): void {
		$GLOBALS['uplinkportal_test_options']['uplinkportal_module_services_finance'] = array(
			array(
				'slug'        => 'custom_invoicing',
				'name'        => 'Custom Invoicing',
				'description' => 'Replaced default.',
				'icon'        => 'dashicons-money-alt',
			),
		);

		$store    = new ServiceDefinitionStore();
		$services = $store->get_services_for_module( 'finance' );

		$slugs = array_column( $services, 'slug' );
		$this->assertContains( 'custom_invoicing', $slugs );
		$this->assertNotContains( 'invoice_management', $slugs );
	}

	/**
	 * Merging service definitions works across multiple module slugs.
	 *
	 * @return void
	 */
	public function test_get_all_enabled_services_merges_across_slugs(): void {
		$store    = new ServiceDefinitionStore();
		$services = $store->get_all_enabled_services( array( 'finance', 'it_support' ) );

		$this->assertIsArray( $services );
		$this->assertNotEmpty( $services );

		$slugs = array_column( $services, 'slug' );
		$this->assertContains( 'invoice_management', $slugs );
		$this->assertContains( 'help_desk_support', $slugs );

		// Each item includes a module_slug attribution key.
		foreach ( $services as $service ) {
			$this->assertArrayHasKey( 'module_slug', $service );
		}
	}

	/**
	 * Sanitized service definitions are persisted to options.
	 *
	 * @return void
	 */
	public function test_save_services_for_module_stores_sanitized_data(): void {
		$store = new ServiceDefinitionStore();
		$raw   = array(
			array(
				'slug'        => 'my-service!',
				'name'        => '<b>Bold Service</b>',
				'description' => 'A description.',
				'icon'        => 'dashicons-admin-generic',
			),
		);

		$result = $store->save_services_for_module( 'finance', $raw );

		$this->assertTrue( $result );

		$stored = $GLOBALS['uplinkportal_test_options']['uplinkportal_module_services_finance'] ?? null;
		$this->assertIsArray( $stored );
		$this->assertCount( 1, $stored );
		$this->assertSame( 'my-service', $stored[0]['slug'] );
		$this->assertSame( 'Bold Service', $stored[0]['name'] );
	}

	/**
	 * Empty array is returned when no module slugs are provided.
	 *
	 * @return void
	 */
	public function test_get_all_enabled_services_returns_empty_for_no_slugs(): void {
		$store    = new ServiceDefinitionStore();
		$services = $store->get_all_enabled_services( array() );

		$this->assertIsArray( $services );
		$this->assertEmpty( $services );
	}
}
