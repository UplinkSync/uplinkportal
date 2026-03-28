<?php
/**
 * Security namespace function stubs for tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Core\Security;

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

if ( ! function_exists( __NAMESPACE__ . '\\get_option' ) ) {
	/**
	 * Minimal get_option stub.
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
	 * Minimal update_option stub.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Option value.
	 * @param mixed  ...$extra Additional args (ignored).
	 * @return bool
	 */
	function update_option( string $name, $value, ...$extra ): bool {
		if ( ! isset( $GLOBALS['uplinkportal_test_wp_options'] ) || ! is_array( $GLOBALS['uplinkportal_test_wp_options'] ) ) {
			$GLOBALS['uplinkportal_test_wp_options'] = array();
		}

		$GLOBALS['uplinkportal_test_wp_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\do_action' ) ) {
	/**
	 * Minimal do_action stub — no-op in unit tests.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Hook arguments.
	 * @return void
	 */
	function do_action( string $hook, ...$args ): void {
		// No-op in unit tests.
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

if ( ! function_exists( __NAMESPACE__ . '\\wp_json_encode' ) ) {
	/**
	 * Minimal wp_json_encode stub.
	 *
	 * @param mixed $data    Data to encode.
	 * @param int   $options JSON encode options.
	 * @param int   $depth   Max depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\esc_url_raw' ) ) {
	/**
	 * Minimal esc_url_raw stub.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	function esc_url_raw( string $url ): string {
		$url      = trim( $url );
		$filtered = filter_var( $url, FILTER_SANITIZE_URL );
		return is_string( $filtered ) ? $filtered : '';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_role' ) ) {
	/**
	 * Minimal get_role stub — returns null (role not found) so role-sync calls are no-ops.
	 *
	 * @since 0.2.0
	 *
	 * @param string $role_name Role slug.
	 * @return null
	 */
	function get_role( string $role_name ) {
		return null;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\add_role' ) ) {
	/**
	 * Minimal add_role stub — no-op; returns null.
	 *
	 * @since 0.2.0
	 *
	 * @param string             $role_slug    Role slug.
	 * @param string             $display_name Display name.
	 * @param array<string,bool> $capabilities Capability map.
	 * @return null
	 */
	function add_role( string $role_slug, string $display_name, array $capabilities = array() ) {
		return null;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\delete_option' ) ) {
	/**
	 * Minimal delete_option stub — removes key from the test options store.
	 *
	 * @since 0.2.0
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	function delete_option( string $name ): bool {
		if ( isset( $GLOBALS['uplinkportal_test_wp_options'][ $name ] ) ) {
			unset( $GLOBALS['uplinkportal_test_wp_options'][ $name ] );
		}
		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\home_url' ) ) {
	/**
	 * Minimal home_url stub — returns test global or default.
	 *
	 * @param string $path Optional path.
	 * @return string
	 */
	function home_url( string $path = '' ): string {
		$base = isset( $GLOBALS['uplinkportal_test_home_url'] ) ? (string) $GLOBALS['uplinkportal_test_home_url'] : 'https://example.test';
		return rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_get_current_user' ) ) {
	/**
	 * Minimal wp_get_current_user stub — returns a guest-like anonymous object.
	 *
	 * @since 0.2.0
	 *
	 * @return object{ID:int,user_login:string}
	 */
	function wp_get_current_user(): object {
		return (object) array(
			'ID'         => 0,
			'user_login' => '',
		);
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_unslash' ) ) {
	/**
	 * Minimal wp_unslash stub — strips slashes from a string or array.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		}

		if ( is_array( $value ) ) {
			return array_map( __NAMESPACE__ . '\\wp_unslash', $value );
		}

		return $value;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\untrailingslashit' ) ) {
	/**
	 * Minimal untrailingslashit stub for Security namespace tests.
	 *
	 * @since 0.2.0
	 *
	 * @param string $value String to strip trailing slashes from.
	 * @return string
	 */
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_remote_post' ) ) {
	/**
	 * Minimal wp_remote_post stub — captures the call for test assertions.
	 *
	 * Stores ['url' => ..., 'args' => ...] in $GLOBALS['uplinkportal_test_last_remote_post'].
	 *
	 * @param string              $url  Request URL.
	 * @param array<string,mixed> $args Request args.
	 * @return array<string,mixed>
	 */
	function wp_remote_post( string $url, array $args = array() ): array {
		$GLOBALS['uplinkportal_test_last_remote_post'] = array(
			'url'  => $url,
			'args' => $args,
		);

		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}
}
