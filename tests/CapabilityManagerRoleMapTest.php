<?php
/**
 * Capability manager role map tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Security\CapabilityManager;

/**
 * Verifies role and capability naming conventions.
 */
class CapabilityManagerRoleMapTest extends TestCase {
	/**
	 * Ensures all active capability slugs use MSP/Client prefixes.
	 *
	 * @return void
	 */
	public function test_capability_slugs_use_msp_or_client_prefixes(): void {
		foreach ( CapabilityManager::capabilities() as $capability ) {
			$this->assertMatchesRegularExpression( '/^(msp|client)_/', $capability );
		}
	}

	/**
	 * Ensures role map capabilities use current naming.
	 *
	 * @return void
	 */
	public function test_role_map_uses_current_capability_slugs(): void {
		$method   = new \ReflectionMethod( CapabilityManager::class, 'role_capability_map' );
		$role_map = $method->invoke( null );

		foreach ( $role_map as $role_data ) {
			$caps = isset( $role_data['caps'] ) && is_array( $role_data['caps'] ) ? $role_data['caps'] : array();
			foreach ( $caps as $capability ) {
				$this->assertMatchesRegularExpression( '/^(msp|client)_/', $capability );
			}
		}
	}

	/**
	 * Ensures role map uses only current role slugs.
	 *
	 * @return void
	 */
	public function test_role_map_uses_current_role_slug_groups(): void {
		$method   = new \ReflectionMethod( CapabilityManager::class, 'role_capability_map' );
		$role_map = $method->invoke( null );

		foreach ( array_keys( $role_map ) as $role_slug ) {
			$this->assertMatchesRegularExpression( '/^uplinkportal_(client|msp)_/', (string) $role_slug );
		}
	}

	/**
	 * All four new dashboard view capabilities exist in the capabilities list.
	 *
	 * @return void
	 */
	public function test_new_dashboard_view_caps_exist(): void {
		$caps = CapabilityManager::capabilities();

		$this->assertContains( 'msp_view_operations_dashboard', $caps );
		$this->assertContains( 'msp_view_executive_dashboard', $caps );
		$this->assertContains( 'msp_view_financial_dashboard', $caps );
		$this->assertContains( 'msp_view_technician_dashboard', $caps );
	}

	/**
	 * The uplinkportal_msp_executive role exists in the role map.
	 *
	 * @return void
	 */
	public function test_new_executive_role_exists(): void {
		$method   = new \ReflectionMethod( CapabilityManager::class, 'role_capability_map' );
		$role_map = $method->invoke( null );

		$this->assertArrayHasKey( 'uplinkportal_msp_executive', $role_map );

		$exec_caps = $role_map['uplinkportal_msp_executive']['caps'] ?? array();
		$this->assertContains( 'msp_manage', $exec_caps );
		$this->assertContains( 'msp_view_operations_dashboard', $exec_caps );
		$this->assertContains( 'msp_view_executive_dashboard', $exec_caps );
	}

	/**
	 * Finance and tech roles include the msp_view_operations_dashboard capability.
	 *
	 * @return void
	 */
	public function test_existing_finance_and_tech_roles_extended(): void {
		$method   = new \ReflectionMethod( CapabilityManager::class, 'role_capability_map' );
		$role_map = $method->invoke( null );

		$finance_caps = $role_map['uplinkportal_msp_finance']['caps'] ?? array();
		$this->assertContains( 'msp_view_operations_dashboard', $finance_caps );
		$this->assertContains( 'msp_view_financial_dashboard', $finance_caps );

		$tech_caps = $role_map['uplinkportal_msp_tech']['caps'] ?? array();
		$this->assertContains( 'msp_view_operations_dashboard', $tech_caps );
		$this->assertContains( 'msp_view_technician_dashboard', $tech_caps );
	}
}
