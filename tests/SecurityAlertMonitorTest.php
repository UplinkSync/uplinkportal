<?php
/**
 * SecurityAlertMonitor tests — A1.3 HMAC request signing.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Security\SecurityAlertMonitor;
use UplinkPortal\Core\Support\Options;

/**
 * Tests that security alert webhooks include a valid HMAC signature.
 */
class SecurityAlertMonitorTest extends TestCase {
	/**
	 * Resets test globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_transients']       = array();
		$GLOBALS['uplinkportal_test_wp_options']       = array();
		$GLOBALS['uplinkportal_test_filters']          = array();
		$GLOBALS['uplinkportal_test_last_remote_post'] = null;
	}

	/**
	 * Resets test globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['uplinkportal_test_transients']       = array();
		$GLOBALS['uplinkportal_test_wp_options']       = array();
		$GLOBALS['uplinkportal_test_filters']          = array();
		$GLOBALS['uplinkportal_test_last_remote_post'] = null;
		parent::tearDown();
	}

	/**
	 * HMAC signature header is included in generic webhook POST request.
	 *
	 * @return void
	 */
	public function test_webhook_post_includes_hmac_signature_header(): void {
		$service_token = 'test-service-token-' . bin2hex( random_bytes( 8 ) );

		$options = $this->createMock( Options::class );
		$options->method( 'get_monitoring_profile' )->willReturn(
			array(
				'mode'        => 'generic_webhook',
				'webhook_url' => 'https://hook.example.com/alerts',
			)
		);
		$options->method( 'get_backend_service_token' )->willReturn( $service_token );
		$options->method( 'get_instance_id' )->willReturn( 'test-instance-001' );

		$monitor = new SecurityAlertMonitor( $options );

		// Lower the threshold to 2 (minimum) so we can trigger on second call.
		$GLOBALS['uplinkportal_test_filters']['uplinkportal_security_alert_threshold'] = static function () {
			return 2;
		};

		$event = array(
			'result'        => 'failed',
			'source'        => 'test_source',
			'event'         => 'login',
			'message'       => 'Invalid credentials',
			'actor_user_id' => 0,
			'ip'            => '192.0.2.1',
		);

		// First call — count reaches 1, below threshold.
		$monitor->handle_event( $event );
		$this->assertNull( $GLOBALS['uplinkportal_test_last_remote_post'], 'No POST on first event (below threshold)' );

		// Second call — count reaches 2 = threshold, fires alert.
		$monitor->handle_event( $event );

		$call = $GLOBALS['uplinkportal_test_last_remote_post'];
		$this->assertNotNull( $call, 'wp_remote_post should have been called after threshold reached' );
		$this->assertSame( 'https://hook.example.com/alerts', $call['url'] );

		$headers = $call['args']['headers'] ?? array();
		$this->assertArrayHasKey( 'X-UplinkPortal-Signature', $headers, 'Signature header must be present' );

		$actual_sig = $headers['X-UplinkPortal-Signature'];
		$this->assertStringStartsWith( 'sha256=', $actual_sig, 'Signature must be sha256= prefixed' );

		// Recompute the expected HMAC from the actual body sent.
		$actual_body  = (string) ( $call['args']['body'] ?? '' );
		$expected_sig = 'sha256=' . hash_hmac( 'sha256', $actual_body, $service_token );

		$this->assertSame( $expected_sig, $actual_sig, 'HMAC signature must match sha256=hash_hmac(body, token)' );
	}

	/**
	 * No POST is made when mode is 'none'.
	 *
	 * @return void
	 */
	public function test_no_webhook_post_when_mode_is_none(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get_monitoring_profile' )->willReturn( array( 'mode' => 'none' ) );

		$monitor = new SecurityAlertMonitor( $options );

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_security_alert_threshold'] = static function () {
			return 2;
		};

		$event = array(
			'result'        => 'failed',
			'source'        => 'login',
			'event'         => 'auth',
			'actor_user_id' => 0,
			'ip'            => '192.0.2.2',
		);

		$monitor->handle_event( $event );
		$monitor->handle_event( $event );

		$this->assertNull( $GLOBALS['uplinkportal_test_last_remote_post'], 'No POST when mode is none' );
	}

	/**
	 * HMAC signature header is included in backend-mode (non-generic-webhook) POST request.
	 *
	 * @return void
	 */
	public function test_backend_mode_post_includes_hmac_signature_header(): void {
		$service_token = 'backend-service-token-' . bin2hex( random_bytes( 8 ) );
		$backend_url   = 'https://backend.example.com';

		$options = $this->createMock( Options::class );
		$options->method( 'get_monitoring_profile' )->willReturn( array( 'mode' => 'hosted_subscription' ) );
		$options->method( 'get_backend_base_url' )->willReturn( $backend_url );
		$options->method( 'get_backend_service_token' )->willReturn( $service_token );
		$options->method( 'get_instance_id' )->willReturn( 'test-instance-002' );

		$monitor = new SecurityAlertMonitor( $options );

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_security_alert_threshold'] = static function () {
			return 2;
		};

		$event = array(
			'result'        => 'denied',
			'source'        => 'rest_auth',
			'event'         => 'permission_check',
			'message'       => 'Capability check failed',
			'actor_user_id' => 0,
			'ip'            => '192.0.2.10',
		);

		// First call — count reaches 1, below threshold.
		$monitor->handle_event( $event );
		$this->assertNull( $GLOBALS['uplinkportal_test_last_remote_post'], 'No POST on first event (below threshold)' );

		// Second call — count reaches 2 = threshold, fires alert.
		$monitor->handle_event( $event );

		$call = $GLOBALS['uplinkportal_test_last_remote_post'];
		$this->assertNotNull( $call, 'wp_remote_post should have been called after threshold reached' );
		$this->assertStringStartsWith( $backend_url, $call['url'], 'POST URL must use backend base URL' );

		$headers = $call['args']['headers'] ?? array();
		$this->assertArrayHasKey( 'X-UplinkPortal-Signature', $headers, 'Signature header must be present in backend mode' );
		$this->assertArrayHasKey( 'Authorization', $headers, 'Authorization header must be present in backend mode' );

		$actual_sig = $headers['X-UplinkPortal-Signature'];
		$this->assertStringStartsWith( 'sha256=', $actual_sig, 'Signature must be sha256= prefixed' );

		$actual_body  = (string) ( $call['args']['body'] ?? '' );
		$expected_sig = 'sha256=' . hash_hmac( 'sha256', $actual_body, $service_token );
		$this->assertSame( $expected_sig, $actual_sig, 'HMAC signature must match sha256=hash_hmac(body, token)' );

		$this->assertSame(
			'Bearer ' . $service_token,
			$headers['Authorization'],
			'Authorization header must be Bearer <service_token>'
		);
	}

	/**
	 * Non-failure events are silently ignored by handle_event().
	 *
	 * @return void
	 */
	public function test_non_failure_event_is_ignored(): void {
		$options = $this->createMock( Options::class );
		$monitor = new SecurityAlertMonitor( $options );

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_security_alert_threshold'] = static function () {
			return 2;
		};

		$event = array(
			'result'        => 'success',
			'source'        => 'login',
			'event'         => 'auth',
			'actor_user_id' => 1,
			'ip'            => '192.0.2.3',
		);

		$monitor->handle_event( $event );
		$monitor->handle_event( $event );

		$this->assertNull( $GLOBALS['uplinkportal_test_last_remote_post'], 'Successful events must not trigger alert' );
	}
}
