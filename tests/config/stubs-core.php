<?php
/**
 * Core namespace function stubs for tests.
 *
 * Provides WordPress function shims scoped to UplinkPortal\Core so that
 * Activator and related classes can be exercised in a unit-test environment
 * without a live WordPress installation.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Core;

require_once __DIR__ . '/WpDieException.php';

if ( ! function_exists( __NAMESPACE__ . '\\get_bloginfo' ) ) {
	/**
	 * Minimal get_bloginfo stub.
	 *
	 * Driven by $GLOBALS['uplinkportal_test_bloginfo'] (associative array keyed by $show).
	 * Falls back to reasonable defaults when not explicitly set.
	 *
	 * @since 0.2.0
	 *
	 * @param string $show The info to retrieve.
	 * @return string
	 */
	function get_bloginfo( string $show = '' ): string {
		$info = isset( $GLOBALS['uplinkportal_test_bloginfo'] ) && is_array( $GLOBALS['uplinkportal_test_bloginfo'] )
			? $GLOBALS['uplinkportal_test_bloginfo']
			: array();

		if ( array_key_exists( $show, $info ) ) {
			return (string) $info[ $show ];
		}

		// Sensible default: report current stable WP version so gates pass.
		if ( 'version' === $show ) {
			return '6.7';
		}

		return '';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_die' ) ) {
	/**
	 * Stub for wp_die() — throws WpDieException instead of terminating the process.
	 *
	 * Allows PHPUnit tests to assert that wp_die() was called with expected messages.
	 *
	 * @since 0.2.0
	 *
	 * @param string       $message Error message.
	 * @param string       $title   Optional page title.
	 * @param array<mixed> $args    Optional extra args (ignored in tests).
	 * @throws WpDieException Always.
	 * @return never
	 */
	function wp_die( string $message = '', string $title = '', array $args = array() ): never { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args intentionally ignored in test stub.
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test stub; escaping not needed in PHPUnit context.
		throw new WpDieException( $message, $title );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_option' ) ) {
	/**
	 * Minimal get_option stub for tests.
	 *
	 * @since 0.2.0
	 *
	 * @param string $name          Option name.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	function get_option( string $name, $default_value = false ) {
		$options = isset( $GLOBALS['uplinkportal_test_wp_options'] ) && is_array( $GLOBALS['uplinkportal_test_wp_options'] )
			? $GLOBALS['uplinkportal_test_wp_options']
			: array();

		return array_key_exists( $name, $options ) ? $options[ $name ] : $default_value;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\update_option' ) ) {
	/**
	 * Minimal update_option stub for tests.
	 *
	 * @since 0.2.0
	 *
	 * @param string $name    Option name.
	 * @param mixed  $value   Option value.
	 * @param mixed  ...$extra Additional args (ignored).
	 * @return bool
	 */
	function update_option( string $name, $value, ...$extra ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $extra intentionally ignored in test stub.
		if ( ! isset( $GLOBALS['uplinkportal_test_wp_options'] ) || ! is_array( $GLOBALS['uplinkportal_test_wp_options'] ) ) {
			$GLOBALS['uplinkportal_test_wp_options'] = array();
		}

		$GLOBALS['uplinkportal_test_wp_options'][ $name ] = $value;
		return true;
	}
}
