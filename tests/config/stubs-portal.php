<?php
/**
 * ClientPortal REST namespace function stubs for tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\ClientPortal\Rest;

if ( ! function_exists( __NAMESPACE__ . '\\is_user_logged_in' ) ) {
	/**
	 * Minimal is_user_logged_in stub for tests.
	 *
	 * @return bool
	 */
	function is_user_logged_in(): bool {
		return ! empty( $GLOBALS['uplinkportal_test_user_logged_in'] );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\current_user_can' ) ) {
	/**
	 * Minimal current_user_can stub for tests.
	 *
	 * @param string $capability Capability to check.
	 * @return bool
	 */
	function current_user_can( string $capability ): bool {
		$caps = isset( $GLOBALS['uplinkportal_test_current_user_caps'] ) && is_array( $GLOBALS['uplinkportal_test_current_user_caps'] )
			? $GLOBALS['uplinkportal_test_current_user_caps']
			: array();
		return in_array( $capability, $caps, true );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_current_user_id' ) ) {
	/**
	 * Minimal get_current_user_id stub for tests.
	 *
	 * @return int
	 */
	function get_current_user_id(): int {
		return isset( $GLOBALS['uplinkportal_test_current_user_id'] ) ? (int) $GLOBALS['uplinkportal_test_current_user_id'] : 0;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_verify_nonce' ) ) {
	/**
	 * Minimal wp_verify_nonce stub for tests.
	 *
	 * @param string $nonce  Nonce to verify.
	 * @param string $action Action name.
	 * @return int|false
	 */
	function wp_verify_nonce( string $nonce, string $action ) {
		return 'valid-nonce' === $nonce ? 1 : false;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\apply_filters' ) ) {
	/**
	 * Minimal apply_filters stub for tests.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Additional hook args.
	 * @return mixed
	 */
	function apply_filters( string $hook, $value, ...$args ) {
		$callbacks = isset( $GLOBALS['uplinkportal_test_filters'][ $hook ] ) ? $GLOBALS['uplinkportal_test_filters'][ $hook ] : array();
		if ( is_callable( $callbacks ) ) {
			$callbacks = array( $callbacks );
		}

		if ( ! is_array( $callbacks ) ) {
			return $value;
		}

		foreach ( $callbacks as $callback ) {
			if ( ! is_callable( $callback ) ) {
				continue;
			}
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_get_current_user' ) ) {
	/**
	 * Minimal wp_get_current_user stub for tests.
	 *
	 * @return object
	 */
	function wp_get_current_user(): object {
		return (object) array(
			'ID'           => (int) ( $GLOBALS['uplinkportal_test_current_user_id'] ?? 0 ),
			'display_name' => 'Test User',
			'user_email'   => 'test@example.com',
		);
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_generate_uuid4' ) ) {
	/**
	 * Minimal wp_generate_uuid4 stub for tests.
	 *
	 * Returns the value seeded via $GLOBALS['uplinkportal_test_uuid4'] when
	 * set, otherwise a deterministic placeholder UUID.
	 *
	 * @return string
	 */
	function wp_generate_uuid4(): string {
		return isset( $GLOBALS['uplinkportal_test_uuid4'] )
			? (string) $GLOBALS['uplinkportal_test_uuid4']
			: 'aaaaaaaa-bbbb-4ccc-dddd-eeeeeeeeeeee';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\register_rest_route' ) ) {
	/**
	 * Minimal register_rest_route stub for tests.
	 *
	 * @param string $rest_namespace Route namespace.
	 * @param string $route          Route pattern.
	 * @param array  $args           Route arguments.
	 * @return void
	 */
	function register_rest_route( string $rest_namespace, string $route, array $args = array() ): void {
		// No-op in tests; routes are exercised by calling methods directly.
	}
}
