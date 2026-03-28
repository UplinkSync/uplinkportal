<?php
/**
 * Portal access flow integration tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\ClientPortal\Rest\PortalController;
use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Support\ClientProfileStore;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Tests for PortalController access permission callbacks.
 */
class PortalAccessFlowTest extends TestCase {
	/**
	 * Resets global state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_user_logged_in']    = false;
		$GLOBALS['uplinkportal_test_current_user_caps'] = array();
		$GLOBALS['uplinkportal_test_current_user_id']   = 0;
		$GLOBALS['uplinkportal_test_filters']           = array();
		$GLOBALS['uplinkportal_test_uuid4']             = null;
		// Disable nonce enforcement so tests don't need a real wp_verify_nonce.
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_enforce_rest_nonce'] = static fn() => false;
	}

	// -----------------------------------------------------------------------
	// check_portal_access() — authentication
	// -----------------------------------------------------------------------

	/**
	 * Unauthenticated request returns 401 WP_Error.
	 *
	 * @return void
	 */
	public function test_unauthenticated_request_returns_401(): void {
		$GLOBALS['uplinkportal_test_user_logged_in'] = false;
		$controller                                  = $this->build_controller();
		$request                                     = new WP_REST_Request();

		$result = $controller->check_portal_access( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'uplinkportal_unauthenticated', $result->get_error_code() );
	}

	// -----------------------------------------------------------------------
	// check_portal_access() — authorisation
	// -----------------------------------------------------------------------

	/**
	 * Authenticated user without client_access capability returns 403 WP_Error.
	 *
	 * @return void
	 */
	public function test_authenticated_without_capability_returns_403(): void {
		$GLOBALS['uplinkportal_test_user_logged_in']    = true;
		$GLOBALS['uplinkportal_test_current_user_caps'] = array(); // No client_access capability.
		$controller                                     = $this->build_controller();
		$request                                        = new WP_REST_Request();

		$result = $controller->check_portal_access( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'uplinkportal_forbidden', $result->get_error_code() );
	}

	/**
	 * Authenticated user WITH client_access capability returns true.
	 *
	 * @return void
	 */
	public function test_authenticated_with_capability_returns_true(): void {
		$GLOBALS['uplinkportal_test_user_logged_in']    = true;
		$GLOBALS['uplinkportal_test_current_user_caps'] = array( 'client_access' );
		$controller                                     = $this->build_controller();
		$request                                        = new WP_REST_Request();

		$result = $controller->check_portal_access( $request );

		$this->assertTrue( $result );
	}

	// -----------------------------------------------------------------------
	// check_health_access() — authentication and authorisation
	// -----------------------------------------------------------------------

	/**
	 * Unauthenticated request to health endpoint returns 401 WP_Error.
	 *
	 * @return void
	 */
	public function test_health_unauthenticated_returns_401(): void {
		$GLOBALS['uplinkportal_test_user_logged_in'] = false;
		$controller                                  = $this->build_controller();
		$request                                     = new WP_REST_Request();

		$result = $controller->check_health_access( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'uplinkportal_unauthenticated', $result->get_error_code() );
	}

	/**
	 * Authenticated user without msp_manage capability returns 403 WP_Error.
	 *
	 * @return void
	 */
	public function test_health_authenticated_without_msp_manage_returns_403(): void {
		$GLOBALS['uplinkportal_test_user_logged_in']    = true;
		$GLOBALS['uplinkportal_test_current_user_caps'] = array( 'client_access' );
		$controller                                     = $this->build_controller();
		$request                                        = new WP_REST_Request();

		$result = $controller->check_health_access( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'uplinkportal_forbidden', $result->get_error_code() );
	}

	/**
	 * Authenticated user with msp_manage capability passes the health access check.
	 *
	 * @return void
	 */
	public function test_health_authenticated_with_msp_manage_returns_true(): void {
		$GLOBALS['uplinkportal_test_user_logged_in']    = true;
		$GLOBALS['uplinkportal_test_current_user_caps'] = array( 'msp_manage' );
		$controller                                     = $this->build_controller();
		$request                                        = new WP_REST_Request();

		$result = $controller->check_health_access( $request );

		$this->assertTrue( $result );
	}

	// -----------------------------------------------------------------------
	// get_health() response shape
	// -----------------------------------------------------------------------

	/**
	 * Get_health() returns 200 with required fields and no secrets.
	 *
	 * @return void
	 */
	public function test_get_health_returns_expected_shape(): void {
		$GLOBALS['uplinkportal_test_uuid4'] = 'test-uuid-1234-5678-abcd';
		$controller                         = $this->build_controller();

		$response = $controller->get_health();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'request_id', $data );
		$this->assertSame( 'ok', $data['status'] );
		$this->assertSame( UPLINKPORTAL_VERSION, $data['version'] );
		$this->assertSame( 'test-uuid-1234-5678-abcd', $data['request_id'] );
	}

	/**
	 * Get_health() response includes Cache-Control and X-UplinkPortal-Request-Id headers.
	 *
	 * @return void
	 */
	public function test_get_health_includes_request_id_header(): void {
		$GLOBALS['uplinkportal_test_uuid4'] = 'header-request-id-xyz';
		$controller                         = $this->build_controller();

		$response = $controller->get_health();

		$this->assertSame( 'no-store, private, max-age=0', $response->headers['Cache-Control'] );
		$this->assertSame( 'header-request-id-xyz', $response->headers['X-UplinkPortal-Request-Id'] );
	}

	/**
	 * Get_health() request_id matches the X-UplinkPortal-Request-Id header value.
	 *
	 * @return void
	 */
	public function test_get_health_request_id_body_matches_header(): void {
		unset( $GLOBALS['uplinkportal_test_uuid4'] );
		$controller = $this->build_controller();

		$response = $controller->get_health();

		$data = $response->get_data();
		$this->assertSame( $data['request_id'], $response->headers['X-UplinkPortal-Request-Id'] );
	}

	// -----------------------------------------------------------------------
	// get_me() response shape
	// -----------------------------------------------------------------------

	/**
	 * Get_me() returns a 200 response with required fields.
	 *
	 * @return void
	 */
	public function test_get_me_returns_expected_shape(): void {
		$GLOBALS['uplinkportal_test_current_user_id'] = 42;
		$controller                                   = $this->build_controller();

		$response = $controller->get_me();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'display_name', $data );
		$this->assertSame( 42, $data['id'] );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Builds a PortalController with mocked dependencies.
	 *
	 * @return PortalController
	 */
	private function build_controller(): PortalController {
		$module_manager  = $this->createMock( ModuleManager::class );
		$client_profiles = $this->createMock( ClientProfileStore::class );

		return new PortalController( $module_manager, $client_profiles );
	}
}
