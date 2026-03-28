<?php
/**
 * Modules namespace function stubs for tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Core\Modules;

if ( ! function_exists( __NAMESPACE__ . '\\sanitize_key' ) ) {
	/**
	 * Minimal sanitize_key stub for tests.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		$key = strtolower( $key );
		return preg_replace( '/[^a-z0-9_\\-]/', '', $key ) ?? '';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\absint' ) ) {
	/**
	 * Minimal absint stub for tests.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ): int {
		return abs( (int) $value );
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

if ( ! function_exists( __NAMESPACE__ . '\\user_can' ) ) {
	/**
	 * Minimal user_can stub for tests.
	 *
	 * @param int    $user_id User id.
	 * @param string $capability Capability.
	 * @return bool
	 */
	function user_can( int $user_id, string $capability ): bool {
		$caps = isset( $GLOBALS['uplinkportal_test_user_caps'][ $user_id ] ) && is_array( $GLOBALS['uplinkportal_test_user_caps'][ $user_id ] )
			? $GLOBALS['uplinkportal_test_user_caps'][ $user_id ]
			: array();

		return in_array( $capability, $caps, true );
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
