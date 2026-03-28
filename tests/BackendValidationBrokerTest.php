<?php
/**
 * BackendValidationBroker tests — B4 public-core integration.
 *
 * Verifies that BackendValidationBroker correctly delegates to the
 * `uplinkportal_backend_validation_result` filter, returns a safe fallback
 * when no backend service is connected, and normalizes result payloads.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\BackendValidationBroker;
use UplinkPortal\Core\Support\Options;

/**
 * Tests for BackendValidationBroker filter delegation and result normalization.
 *
 * @since 0.2.0
 */
class BackendValidationBrokerTest extends TestCase {

	/**
	 * Resets test globals before each test.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_filters']    = array();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
	}

	/**
	 * Resets test globals after each test.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['uplinkportal_test_filters']    = array();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
		parent::tearDown();
	}

	/**
	 * Builds a minimal Options mock with backend method stubs.
	 *
	 * @since  0.2.0
	 * @return Options
	 */
	private function build_options_mock(): Options {
		$options = $this->createMock( Options::class );
		$options->method( 'get_backend_mode' )->willReturn( 'local' );
		$options->method( 'get_backend_runtime_mode' )->willReturn( 'live' );
		$options->method( 'get_backend_validation_path' )->willReturn( '/validate' );
		$options->method( 'get_backend_health_path' )->willReturn( '/health' );
		$options->method( 'get_policy_bundle' )->willReturn( 'gdpr_baseline' );
		$options->method( 'get_secret_manager_config' )->willReturn( array( 'provider' => 'none' ) );
		$options->method( 'get_governance_profile' )->willReturn( array( 'policy_bundle' => 'gdpr_baseline' ) );
		return $options;
	}

	/**
	 * When no filter returns a result, validate() returns a safe warning fallback.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_no_backend_connected_returns_safe_warning_fallback(): void {
		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'billing', 'https://api.example.com', 'key123', false );

		$this->assertIsArray( $result, 'Result must be an array' );
		$this->assertSame( 'warning', $result['status'], 'Fallback status must be warning' );
		$this->assertFalse( $result['compatible'], 'Fallback compatible must be false' );
		$this->assertSame( 0, $result['score'], 'Fallback score must be 0' );
		$this->assertNotEmpty( $result['evidence'], 'Fallback must include at least one evidence item' );
		$this->assertNotEmpty( $result['guidance'], 'Fallback must include at least one guidance item' );
	}

	/**
	 * When no filter returns a result, the result does not throw or cause a fatal error.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_no_backend_does_not_throw(): void {
		$broker = new BackendValidationBroker( $this->build_options_mock() );

		$threw = false;
		try {
			$broker->validate( 'helpdesk', '', '', false );
		} catch ( \Throwable $e ) {
			$threw = true;
		}

		$this->assertFalse( $threw, 'validate() must not throw when no backend is connected' );
	}

	/**
	 * Filter receives a payload array with all expected keys.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_filter_receives_complete_payload(): void {
		$captured_payload = null;

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function ( $result, array $payload ) use ( &$captured_payload ) {
			$captured_payload = $payload;
			return null; // Let the fallback run so broker stays safe.
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$broker->validate( 'pm', 'https://pm.example.com', 'api-key-abc', true, 'oauth-ref-xyz', 'oauth' );

		$this->assertIsArray( $captured_payload, 'Filter must receive a payload array' );

		$expected_keys = array(
			'section',
			'base_url',
			'api_key',
			'allow_query_api_key',
			'oauth_connection_ref',
			'auth_type',
			'backend_mode',
			'backend_runtime_mode',
			'backend_validation_path',
			'backend_health_path',
			'policy_bundle',
			'secret_manager',
			'governance',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $captured_payload, "Payload must contain key '{$key}'" );
		}
	}

	/**
	 * Payload section key is sanitized before being passed to the filter.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_filter_payload_section_is_sanitized(): void {
		$captured_section = null;

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function ( $result, array $payload ) use ( &$captured_section ) {
			$captured_section = $payload['section'];
			return null;
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$broker->validate( 'Billing_Module!', 'https://api.example.com', '', false );

		// sanitize_key strips uppercase and non-alphanumeric-underscore-hyphen characters.
		$this->assertSame( 'billing_module', $captured_section, 'Section must be sanitized via sanitize_key' );
	}

	/**
	 * When filter returns a valid result, it is normalized and returned.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_filter_result_is_returned_when_valid(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function () {
			return array(
				'status'     => 'success',
				'compatible' => true,
				'score'      => 95,
				'evidence'   => array(
					array(
						'type'        => 'auth_probe',
						'message'     => 'Auth check passed',
						'http_status' => 200,
					),
				),
				'guidance'   => array( 'All checks passed.' ),
			);
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'billing', 'https://api.example.com', 'key', false );

		$this->assertSame( 'success', $result['status'] );
		$this->assertTrue( $result['compatible'] );
		$this->assertSame( 95, $result['score'] );
		$this->assertCount( 1, $result['evidence'] );
		$this->assertCount( 1, $result['guidance'] );
		$this->assertSame( 'All checks passed.', $result['guidance'][0] );
	}

	/**
	 * Invalid status from filter is normalized to 'warning'.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_invalid_status_normalizes_to_warning(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function () {
			return array(
				'status'     => 'unknown_status',
				'compatible' => false,
				'score'      => 50,
				'evidence'   => array(),
				'guidance'   => array(),
			);
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'billing', 'https://api.example.com', '', false );

		$this->assertSame( 'warning', $result['status'], 'Unknown status must be normalized to warning' );
	}

	/**
	 * Score above 100 is clamped to 100.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_score_above_100_is_clamped_to_100(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function () {
			return array(
				'status'     => 'success',
				'compatible' => true,
				'score'      => 150,
				'evidence'   => array(),
				'guidance'   => array(),
			);
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'billing', 'https://api.example.com', '', false );

		$this->assertSame( 100, $result['score'], 'Score must be clamped to 100 maximum' );
	}

	/**
	 * Evidence items are individually normalized (type, message, endpoint, http_status, auth).
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_evidence_items_are_normalized(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function () {
			return array(
				'status'     => 'error',
				'compatible' => false,
				'score'      => 0,
				'evidence'   => array(
					array(
						'type'        => 'connection_check',
						'message'     => 'Connection refused',
						'endpoint'    => 'https://api.example.com/health',
						'http_status' => 503,
						'auth'        => 'bearer',
					),
				),
				'guidance'   => array(),
			);
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'helpdesk', 'https://api.example.com', '', false );

		$this->assertCount( 1, $result['evidence'] );
		$item = $result['evidence'][0];
		$this->assertSame( 'connection_check', $item['type'] );
		$this->assertSame( 'Connection refused', $item['message'] );
		$this->assertSame( 503, $item['http_status'] );
		$this->assertSame( 'bearer', $item['auth'] );
	}

	/**
	 * Non-array evidence items are silently dropped during normalization.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_non_array_evidence_items_are_dropped(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function () {
			return array(
				'status'     => 'warning',
				'compatible' => false,
				'score'      => 10,
				'evidence'   => array( 'not-an-array', null, 42 ),
				'guidance'   => array(),
			);
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'billing', 'https://api.example.com', '', false );

		$this->assertEmpty( $result['evidence'], 'Non-array evidence items must be dropped' );
	}

	/**
	 * Empty guidance strings are dropped during normalization.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_empty_guidance_strings_are_dropped(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function () {
			return array(
				'status'     => 'success',
				'compatible' => true,
				'score'      => 80,
				'evidence'   => array(),
				'guidance'   => array( 'Valid guidance', '', '  ', 'Another line' ),
			);
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'pm', 'https://pm.example.com', '', false );

		$this->assertCount( 2, $result['guidance'], 'Empty guidance strings must be dropped' );
		$this->assertContains( 'Valid guidance', $result['guidance'] );
		$this->assertContains( 'Another line', $result['guidance'] );
	}

	/**
	 * Filter returning a non-array value is treated as no-backend and falls back to warning.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function test_non_array_filter_result_falls_back_to_warning(): void {
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_backend_validation_result'] = static function () {
			return 'not-an-array';
		};

		$broker = new BackendValidationBroker( $this->build_options_mock() );
		$result = $broker->validate( 'billing', 'https://api.example.com', '', false );

		$this->assertSame( 'warning', $result['status'], 'Non-array filter result must fall back to warning' );
		$this->assertFalse( $result['compatible'] );
	}
}
