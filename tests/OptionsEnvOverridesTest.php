<?php
/**
 * Options env override tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\Options;

/**
 * Verifies env precedence for backend connectivity settings.
 */
class OptionsEnvOverridesTest extends TestCase {
	/**
	 * Resets env and option stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
		$GLOBALS['uplinkportal_test_filters']    = array();
		$this->clear_backend_env();
	}

	/**
	 * Clears backend env keys after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->clear_backend_env();
		parent::tearDown();
	}

	/**
	 * Ensures env values take precedence over stored options.
	 *
	 * @return void
	 */
	public function test_env_backend_values_override_options(): void {
		$this->seed_option(
			array(
				'backend' => array(
					'mode'            => 'managed_service',
					'base_url'        => 'https://stored.example.test',
					'validation_path' => '/stored-validate',
					'health_path'     => '/stored-health',
				),
			)
		);

		$this->set_env( 'UPLINKPORTAL_BACKEND_MODE', 'staging' );
		$this->set_env( 'UPLINKPORTAL_BACKEND_BASE_URL', 'https://env.example.test/' );
		$this->set_env( 'UPLINKPORTAL_BACKEND_SERVICE_TOKEN', 'env-token-value' );
		$this->set_env( 'UPLINKPORTAL_BACKEND_VALIDATION_PATH', 'custom/validate' );
		$this->set_env( 'UPLINKPORTAL_BACKEND_HEALTH_PATH', '/custom/health' );

		$options = new Options();

		$this->assertSame( 'managed_service', $options->get_backend_mode() );
		$this->assertSame( 'staging', $options->get_backend_runtime_mode() );
		$this->assertSame( 'https://env.example.test', $options->get_backend_base_url() );
		$this->assertSame( 'env-token-value', $options->get_backend_service_token() );
		$this->assertSame( '/custom/validate', $options->get_backend_validation_path() );
		$this->assertSame( '/custom/health', $options->get_backend_health_path() );
		$this->assertSame( 'wp_options', $options->get_backend_mode_source() );
		$this->assertSame( 'env', $options->get_backend_runtime_mode_source() );
		$this->assertSame( 'env', $options->get_backend_base_url_source() );
		$this->assertSame( 'env', $options->get_backend_service_token_source() );
	}

	/**
	 * Ensures invalid env mode falls back to stored option mode.
	 *
	 * @return void
	 */
	public function test_invalid_env_mode_falls_back_to_option_mode(): void {
		$this->seed_option(
			array(
				'backend' => array(
					'mode'     => 'managed_service',
					'base_url' => 'https://stored.example.test',
				),
			)
		);

		$this->set_env( 'UPLINKPORTAL_BACKEND_MODE', 'INVALID MODE!' );

		$options = new Options();

		$this->assertSame( 'managed_service', $options->get_backend_mode() );
		$this->assertSame( 'wp_options', $options->get_backend_mode_source() );
		$this->assertSame( 'production', $options->get_backend_runtime_mode() );
		$this->assertSame( 'default', $options->get_backend_runtime_mode_source() );
	}

	/**
	 * Seeds the main options payload.
	 *
	 * @param array<string,mixed> $data Option data.
	 * @return void
	 */
	private function seed_option( array $data ): void {
		$options = new Options();
		$key     = $options->key();

		$GLOBALS['uplinkportal_test_wp_options'][ $key ] = $data;
	}

	/**
	 * Clears backend env variable values.
	 *
	 * @return void
	 */
	private function clear_backend_env(): void {
		$keys = array(
			'UPLINKPORTAL_BACKEND_MODE',
			'UPLINKPORTAL_BACKEND_BASE_URL',
			'UPLINKPORTAL_BACKEND_SERVICE_TOKEN',
			'UPLINKPORTAL_BACKEND_VALIDATION_PATH',
			'UPLINKPORTAL_BACKEND_HEALTH_PATH',
		);

		foreach ( $keys as $key ) {
			$this->set_env( $key );
			unset( $_ENV[ $key ], $_SERVER[ $key ] );
		}
	}

	/**
	 * Sets or clears one env var for test scope.
	 *
	 * @param string $key Env key.
	 * @param string $value Env value.
	 * @return void
	 */
	private function set_env( string $key, string $value = '' ): void {
		if ( '' === $value ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- required for env precedence tests.
			putenv( $key );
			return;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- required for env precedence tests.
		putenv( $key . '=' . $value );
	}
}
