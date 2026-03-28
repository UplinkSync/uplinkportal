<?php
/**
 * Admin namespace function stubs for tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Admin;

if ( ! function_exists( __NAMESPACE__ . '\\current_user_can' ) ) {
	/**
	 * Minimal current_user_can stub for tests.
	 *
	 * Driven by $GLOBALS['uplinkportal_test_current_user_caps'] (array of capability strings).
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

if ( ! function_exists( __NAMESPACE__ . '\\admin_url' ) ) {
	/**
	 * Minimal admin_url stub for tests.
	 *
	 * Returns a fake admin URL with the given path appended.
	 *
	 * @since 0.2.0
	 *
	 * @param string $path Optional path relative to admin root.
	 * @return string
	 */
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\esc_url' ) ) {
	/**
	 * Minimal esc_url stub for tests.
	 *
	 * Passes the URL through as-is (no escaping needed in unit-test context).
	 *
	 * @since 0.2.0
	 *
	 * @param string $url URL to escape.
	 * @return string
	 */
	function esc_url( string $url ): string {
		return $url;
	}
}
