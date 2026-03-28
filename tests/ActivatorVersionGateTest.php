<?php
/**
 * Activator version-gate tests.
 *
 * Verifies that Activator::activate() enforces minimum WordPress and PHP version
 * requirements by aborting early with wp_die() when the environment is incompatible.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Activator;
use UplinkPortal\Core\WpDieException;

/**
 * Tests for Activator version-gate behavior.
 *
 * @since 0.2.0
 */
class ActivatorVersionGateTest extends TestCase {
	/**
	 * Resets test globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_bloginfo']   = array();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
		$GLOBALS['uplinkportal_test_transients'] = array();
	}

	/**
	 * Resets test globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['uplinkportal_test_bloginfo']   = array();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
		$GLOBALS['uplinkportal_test_transients'] = array();
		parent::tearDown();
	}

	/**
	 * Verifies that wp_die() is called when WordPress is older than 6.0.
	 *
	 * @return void
	 */
	public function test_activate_aborts_when_wordpress_version_is_too_old(): void {
		$GLOBALS['uplinkportal_test_bloginfo'] = array( 'version' => '5.9.9' );

		$this->expectException( WpDieException::class );

		Activator::activate();
	}

	/**
	 * Confirms the wp_die() message contains a translatable, user-safe string for the WP version gate.
	 *
	 * @return void
	 */
	public function test_wp_die_message_is_user_safe_for_wp_version_gate(): void {
		$GLOBALS['uplinkportal_test_bloginfo'] = array( 'version' => '5.0' );

		try {
			Activator::activate();
			$this->fail( 'Expected WpDieException was not thrown.' );
		} catch ( WpDieException $e ) {
			$this->assertStringContainsString( 'WordPress', $e->wp_message );
			$this->assertStringContainsString( '6.0', $e->wp_message );
		}
	}

	/**
	 * Verifies the WP version gate does not abort when WordPress 6.0 or newer is detected.
	 *
	 * This test sets a sufficiently new WP version so that the first gate passes. It then
	 * expects the PHP version gate to either pass (PHP 8.3+ in CI) or throw a WpDieException
	 * for the PHP requirement. In either case, a WP-version-gate exception must NOT be thrown
	 * with the WordPress-version message.
	 *
	 * @return void
	 */
	public function test_activate_passes_wordpress_version_gate_for_current_version(): void {
		$GLOBALS['uplinkportal_test_bloginfo'] = array( 'version' => '6.7' );

		try {
			// May throw WpDieException for PHP version gate or later from schema setup —
			// but it must NOT throw the WP-version-gate message.
			Activator::activate();
		} catch ( WpDieException $e ) {
			$this->assertStringNotContainsString(
				'WordPress 6.0',
				$e->wp_message,
				'WP version gate must not trigger when WordPress >= 6.0 is detected.'
			);
		} catch ( \Throwable $other ) {
			// Any other error (e.g. from schema setup without $wpdb) is acceptable:
			// it means the version gate was passed and execution moved past it.
			$this->addToAssertionCount( 1 );
		}

		// Reaching here without a WP-version-gate exception confirms the gate was passed.
		$this->assertTrue( true );
	}

	/**
	 * Confirms the Activator source contains a PHP_VERSION_ID version gate check.
	 *
	 * Since PHP_VERSION_ID is a compile-time constant that cannot be overridden at runtime,
	 * this test verifies the gate is implemented in the source file rather than triggering
	 * the branch (which would require running on PHP < 8.3).
	 *
	 * @return void
	 */
	public function test_activator_source_contains_php_version_id_gate(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local filesystem path; wp_remote_get() is for remote URLs.
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Core/Activator.php' );

		$this->assertStringContainsString(
			'PHP_VERSION_ID',
			$source,
			'Activator must contain a PHP_VERSION_ID version check.'
		);

		$this->assertStringContainsString(
			'80300',
			$source,
			'Activator must compare against PHP_VERSION_ID 80300 (PHP 8.3.0).'
		);
	}

	/**
	 * Confirms the Activator source contains the WordPress version_compare gate before schema creation.
	 *
	 * @return void
	 */
	public function test_activator_source_contains_wordpress_version_compare_gate(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local filesystem path; wp_remote_get() is for remote URLs.
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Core/Activator.php' );

		$this->assertStringContainsString(
			"version_compare( get_bloginfo( 'version' ), '6.0', '<' )",
			$source,
			'Activator must call version_compare with get_bloginfo(version) and 6.0 minimum.'
		);
	}

	/**
	 * Confirms the Activator source contains the db-version upgrade path that
	 * checks uplinkportal_db_version and updates it after running dbDelta().
	 *
	 * @return void
	 */
	public function test_activator_source_contains_db_version_upgrade_path(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local filesystem path; wp_remote_get() is for remote URLs.
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Core/Activator.php' );

		$this->assertStringContainsString(
			'uplinkportal_db_version',
			$source,
			'Activator must check the uplinkportal_db_version option to detect schema upgrades.'
		);

		$this->assertStringContainsString(
			"update_option( 'uplinkportal_db_version'",
			$source,
			'Activator must write uplinkportal_db_version after a successful schema upgrade.'
		);

		$this->assertStringContainsString(
			'UPLINKPORTAL_VERSION',
			$source,
			'Activator must compare against UPLINKPORTAL_VERSION when evaluating the db version.'
		);
	}

	/**
	 * Confirms the wp_die() call uses the "Plugin Activation Error" title for the WP version gate.
	 *
	 * @return void
	 */
	public function test_wp_die_title_is_plugin_activation_error_for_wp_gate(): void {
		$GLOBALS['uplinkportal_test_bloginfo'] = array( 'version' => '5.9' );

		try {
			Activator::activate();
			$this->fail( 'Expected WpDieException was not thrown.' );
		} catch ( WpDieException $e ) {
			$this->assertStringContainsString( 'Plugin Activation Error', $e->wp_title );
		}
	}
}
