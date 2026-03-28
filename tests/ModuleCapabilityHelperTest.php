<?php
/**
 * Module capability helper tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Support\IntegrationModelRegistry;
use UplinkPortal\Core\Support\Options;

/**
 * Tests for module capability helper behavior.
 */
class ModuleCapabilityHelperTest extends TestCase {
	/**
	 * Resets capability stubs and module manager cache before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		ModuleManager::reset_request_cache();
		$GLOBALS['uplinkportal_test_current_user_id'] = 0;
		$GLOBALS['uplinkportal_test_user_caps']       = array();
		$GLOBALS['uplinkportal_test_filters']         = array();
	}

	/**
	 * Resets the module manager cache after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		ModuleManager::reset_request_cache();
		parent::tearDown();
	}

	/**
	 * Uses module-specific fallback capability when policy matrix is still default manage-only.
	 *
	 * @return void
	 */
	public function test_module_read_fallback_capability_is_used(): void {
		$GLOBALS['uplinkportal_test_user_caps'][25] = array( 'client_view_billing' );

		$manager = $this->build_manager(
			array(
				IntegrationModelRegistry::MODEL_FINANCE => array(
					'read' => 'msp_manage',
				),
			)
		);

		$this->assertTrue( $manager->can_user_access_module( 25, 'finance', 'read' ) );
	}

	/**
	 * Uses model-policy capability when configured to a non-default value.
	 *
	 * @return void
	 */
	public function test_model_policy_capability_overrides_fallback(): void {
		$GLOBALS['uplinkportal_test_user_caps'][25] = array( 'client_view_billing' );

		$manager = $this->build_manager(
			array(
				IntegrationModelRegistry::MODEL_FINANCE => array(
					'read' => 'custom_finance_read',
				),
			)
		);

		$this->assertFalse( $manager->can_user_access_module( 25, 'finance', 'read' ) );

		$GLOBALS['uplinkportal_test_user_caps'][25][] = 'custom_finance_read';
		ModuleManager::reset_request_cache();
		$this->assertTrue( $manager->can_user_access_module( 25, 'finance', 'read' ) );
	}

	/**
	 * Uses current user helper path.
	 *
	 * @return void
	 */
	public function test_current_user_helper_uses_stubbed_current_user(): void {
		$GLOBALS['uplinkportal_test_current_user_id'] = 77;
		$GLOBALS['uplinkportal_test_user_caps'][77]   = array( 'client_view_tickets' );

		$manager = $this->build_manager(
			array(
				IntegrationModelRegistry::MODEL_IT_SUPPORT => array(
					'read' => 'msp_manage',
				),
			)
		);

		$this->assertTrue( $manager->can_current_user_access_module( 'it_support', 'read' ) );
	}

	/**
	 * Denies access for invalid user/module/action input.
	 *
	 * @return void
	 */
	public function test_invalid_input_is_denied(): void {
		$manager = $this->build_manager( array() );

		$this->assertFalse( $manager->can_user_access_module( 0, 'finance', 'read' ) );
		$this->assertFalse( $manager->can_user_access_module( 9, '', 'read' ) );
		$this->assertFalse( $manager->can_user_access_module( 9, 'finance', '' ) );
	}

	/**
	 * Denies access when user is missing required capability.
	 *
	 * @return void
	 */
	public function test_missing_required_capability_is_denied(): void {
		$GLOBALS['uplinkportal_test_user_caps'][31] = array( 'client_access' );

		$manager = $this->build_manager(
			array(
				IntegrationModelRegistry::MODEL_FINANCE => array(
					'read' => 'custom_finance_read',
				),
			)
		);

		$this->assertFalse( $manager->can_user_access_module( 31, 'finance', 'read' ) );
	}

	/**
	 * Denies access when capability filter clears effective capability.
	 *
	 * @return void
	 */
	public function test_filter_can_force_capability_deny(): void {
		$GLOBALS['uplinkportal_test_user_caps'][44] = array( 'msp_manage', 'client_view_billing' );

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_module_required_capability'] = array(
			static function () {
				return '';
			},
		);

		$manager = $this->build_manager(
			array(
				IntegrationModelRegistry::MODEL_FINANCE => array(
					'read' => 'msp_manage',
				),
			)
		);

		$this->assertFalse( $manager->can_user_access_module( 44, 'finance', 'read' ) );
	}

	/**
	 * Builds module manager with mocked options.
	 *
	 * @param array<string,array<string,string>> $policy_matrix Matrix.
	 * @return ModuleManager
	 */
	private function build_manager( array $policy_matrix ): ModuleManager {
		$options = $this->createMock( Options::class );
		$options->method( 'get_model_policy_matrix' )->willReturn( $policy_matrix );
		return new ModuleManager( $options );
	}
}
