<?php
/**
 * Services namespace function stubs for tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Core\Services;

if ( ! function_exists( __NAMESPACE__ . '\\sanitize_key' ) ) {
	/**
	 * Minimal sanitize_key stub.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		$key = strtolower( $key );
		return preg_replace( '/[^a-z0-9_\\-]/', '', $key ) ?? '';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\sanitize_text_field' ) ) {
	/**
	 * Minimal sanitize_text_field stub.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( string $value ): string {
		$value = preg_replace( '/<[^>]*>/', '', $value );
		$value = is_string( $value ) ? $value : '';
		return trim( $value );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\absint' ) ) {
	/**
	 * Minimal absint stub.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\apply_filters' ) ) {
	/**
	 * Minimal apply_filters stub.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Additional args.
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

if ( ! function_exists( __NAMESPACE__ . '\\get_transient' ) ) {
	/**
	 * Minimal get_transient stub.
	 *
	 * @param string $key Transient key.
	 * @return mixed
	 */
	function get_transient( string $key ) {
		$store = isset( $GLOBALS['uplinkportal_test_transients'] ) && is_array( $GLOBALS['uplinkportal_test_transients'] )
			? $GLOBALS['uplinkportal_test_transients']
			: array();

		return array_key_exists( $key, $store ) ? $store[ $key ] : false;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\set_transient' ) ) {
	/**
	 * Minimal set_transient stub.
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Expiration (ignored in stubs).
	 * @return bool
	 */
	function set_transient( string $key, $value, int $expiration = 0 ): bool {
		if ( ! isset( $GLOBALS['uplinkportal_test_transients'] ) || ! is_array( $GLOBALS['uplinkportal_test_transients'] ) ) {
			$GLOBALS['uplinkportal_test_transients'] = array();
		}

		$GLOBALS['uplinkportal_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\delete_transient' ) ) {
	/**
	 * Minimal delete_transient stub.
	 *
	 * @param string $key Transient key.
	 * @return bool
	 */
	function delete_transient( string $key ): bool {
		if ( isset( $GLOBALS['uplinkportal_test_transients'][ $key ] ) ) {
			unset( $GLOBALS['uplinkportal_test_transients'][ $key ] );
		}
		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\gmdate' ) ) {
	/**
	 * Minimal gmdate stub — delegates to PHP built-in.
	 *
	 * @param string   $format    Format string.
	 * @param int|null $timestamp Optional timestamp.
	 * @return string
	 */
	function gmdate( string $format, ?int $timestamp = null ): string {
		return null === $timestamp ? \gmdate( $format ) : \gmdate( $format, $timestamp );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\strtotime' ) ) {
	/**
	 * Minimal strtotime stub — delegates to PHP built-in.
	 *
	 * @param string $datetime Datetime string.
	 * @return int|false
	 */
	function strtotime( string $datetime ) {
		return \strtotime( $datetime );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_option' ) ) {
	/**
	 * Minimal get_option stub.
	 *
	 * @param string $key      Option name.
	 * @param mixed  $fallback Fallback value when option is not set.
	 * @return mixed
	 */
	function get_option( string $key, $fallback = null ) {
		$store = isset( $GLOBALS['uplinkportal_test_options'] ) && is_array( $GLOBALS['uplinkportal_test_options'] )
			? $GLOBALS['uplinkportal_test_options']
			: array();

		return array_key_exists( $key, $store ) ? $store[ $key ] : $fallback;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\update_option' ) ) {
	/**
	 * Minimal update_option stub.
	 *
	 * @param string $key    Option name.
	 * @param mixed  $value  New value.
	 * @param mixed  $autoload Ignored.
	 * @return bool
	 */
	function update_option( string $key, $value, $autoload = null ): bool {
		if ( ! isset( $GLOBALS['uplinkportal_test_options'] ) || ! is_array( $GLOBALS['uplinkportal_test_options'] ) ) {
			$GLOBALS['uplinkportal_test_options'] = array();
		}
		$GLOBALS['uplinkportal_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
	/**
	 * Minimal __() translation stub.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
