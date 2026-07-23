<?php
/**
 * Support namespace function stubs for tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Core\Support;

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

if ( ! function_exists( __NAMESPACE__ . '\\sanitize_text_field' ) ) {
	/**
	 * Minimal sanitize_text_field stub for tests.
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

if ( ! function_exists( __NAMESPACE__ . '\\esc_url_raw' ) ) {
	/**
	 * Minimal esc_url_raw stub for tests.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	function esc_url_raw( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$filtered = filter_var( $url, FILTER_SANITIZE_URL );
		return is_string( $filtered ) ? $filtered : '';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\untrailingslashit' ) ) {
	/**
	 * Minimal untrailingslashit stub for tests.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
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

if ( ! function_exists( __NAMESPACE__ . '\\is_multisite' ) ) {
	/**
	 * Minimal is_multisite stub for tests.
	 *
	 * @return bool
	 */
	function is_multisite(): bool {
		return false;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_generate_uuid4' ) ) {
	/**
	 * Minimal wp_generate_uuid4 stub for tests.
	 *
	 * @return string
	 */
	function wp_generate_uuid4(): string {
		return '00000000-0000-4000-8000-000000000000';
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_option' ) ) {
	/**
	 * Minimal get_option stub for tests.
	 *
	 * @param string $name Option name.
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
	 * @param string $name Option name.
	 * @param mixed  $value Option value.
	 * @return bool
	 */
	function update_option( string $name, $value ): bool {
		if ( ! isset( $GLOBALS['uplinkportal_test_wp_options'] ) || ! is_array( $GLOBALS['uplinkportal_test_wp_options'] ) ) {
			$GLOBALS['uplinkportal_test_wp_options'] = array();
		}

		$GLOBALS['uplinkportal_test_wp_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_transient' ) ) {
	/**
	 * Minimal get_transient stub for tests.
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
	 * Minimal set_transient stub for tests.
	 *
	 * @param string $key Transient key.
	 * @param mixed  $value Value.
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
	 * Minimal delete_transient stub for tests.
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

if ( ! function_exists( __NAMESPACE__ . '\\apply_filters' ) ) {
	/**
	 * Minimal apply_filters stub for tests.
	 *
	 * @param string $hook Hook name.
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

if ( ! function_exists( __NAMESPACE__ . '\\add_filter' ) ) {
	/**
	 * Minimal add_filter stub for tests — registers into the same global the
	 * apply_filters stub reads from. Priority and arg count are ignored.
	 *
	 * @param string   $hook Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority (ignored in stubs).
	 * @param int      $accepted_args Accepted args (ignored in stubs).
	 * @return bool
	 */
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		if ( ! isset( $GLOBALS['uplinkportal_test_filters'][ $hook ] ) ) {
			$GLOBALS['uplinkportal_test_filters'][ $hook ] = array();
		} elseif ( ! is_array( $GLOBALS['uplinkportal_test_filters'][ $hook ] ) ) {
			$GLOBALS['uplinkportal_test_filters'][ $hook ] = array( $GLOBALS['uplinkportal_test_filters'][ $hook ] );
		}

		$GLOBALS['uplinkportal_test_filters'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\is_uploaded_file' ) ) {
	/**
	 * Minimal is_uploaded_file stub for tests — always returns true for non-empty paths.
	 *
	 * @param string $filename File path.
	 * @return bool
	 */
	function is_uploaded_file( string $filename ): bool {
		return '' !== $filename && file_exists( $filename );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\sanitize_file_name' ) ) {
	/**
	 * Minimal sanitize_file_name stub for tests.
	 *
	 * @param string $filename File name.
	 * @return string
	 */
	function sanitize_file_name( string $filename ): string {
		return basename( $filename );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_basename' ) ) {
	/**
	 * Minimal wp_basename stub for tests.
	 *
	 * @param string $path File path.
	 * @param string $suffix Optional suffix to remove.
	 * @return string
	 */
	function wp_basename( string $path, string $suffix = '' ): string {
		return basename( $path, $suffix );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\current_time' ) ) {
	/**
	 * Minimal current_time stub for tests.
	 *
	 * @param string $type  Type ('mysql', 'timestamp', etc.).
	 * @param bool   $gmt   Whether to use GMT time.
	 * @return string|int
	 */
	function current_time( string $type, bool $gmt = false ) {
		if ( 'timestamp' === $type ) {
			return time();
		}
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\do_action' ) ) {
	/**
	 * Minimal do_action stub for tests.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Hook arguments.
	 * @return void
	 */
	function do_action( string $hook, ...$args ): void {
		// No-op in unit tests.
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_json_encode' ) ) {
	/**
	 * Minimal wp_json_encode stub for tests.
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

if ( ! function_exists( __NAMESPACE__ . '\\sanitize_textarea_field' ) ) {
	/**
	 * Minimal sanitize_textarea_field stub for tests.
	 *
	 * @param string $str Raw text.
	 * @return string
	 */
	function sanitize_textarea_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\gmdate' ) ) {
	/**
	 * Minimal gmdate stub — delegates to PHP built-in.
	 *
	 * @param string   $format Format string.
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
	 * @param string   $datetime       Datetime string.
	 * @param int|null $base_timestamp Optional base timestamp.
	 * @return int|false
	 */
	function strtotime( string $datetime, ?int $base_timestamp = null ) {
		return null === $base_timestamp ? \strtotime( $datetime ) : \strtotime( $datetime, $base_timestamp );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_parse_url' ) ) {
	/**
	 * Minimal wp_parse_url stub — delegates to PHP parse_url.
	 *
	 * @param string $url       URL to parse.
	 * @param int    $component Component constant (-1 = all).
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_parse_args' ) ) {
	/**
	 * Minimal wp_parse_args stub — merges args over defaults.
	 *
	 * @param mixed $args     User-defined arguments.
	 * @param mixed $defaults Default arguments.
	 * @return array<mixed,mixed>
	 */
	function wp_parse_args( $args, $defaults = array() ): array {
		if ( is_string( $args ) ) {
			parse_str( $args, $args );
		}

		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}

		return array_merge( (array) $defaults, (array) $args );
	}
}
