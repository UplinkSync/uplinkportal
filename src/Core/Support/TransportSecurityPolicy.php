<?php
/**
 * Transport security policy helper.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces secure transport defaults for external HTTP calls.
 */
final class TransportSecurityPolicy {
	/**
	 * Validates an endpoint URL against transport policy.
	 *
	 * @param string $url Endpoint URL.
	 * @param string $context Context key for diagnostics.
	 * @return true|WP_Error
	 */
	public static function validate_url( string $url, string $context = '' ) {
		$url     = trim( esc_url_raw( $url ) );
		$context = sanitize_key( $context );

		if ( '' === $url ) {
			return new WP_Error(
				'uplinkportal_transport_url_missing',
				__( 'Transport endpoint URL is missing.', 'uplinkportal' )
			);
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$scheme = is_string( $scheme ) ? strtolower( sanitize_key( $scheme ) ) : '';

		if ( 'https' === $scheme ) {
			return true;
		}

		if ( self::allow_insecure_transport( $url, $context ) ) {
			return true;
		}

		return new WP_Error(
			'uplinkportal_transport_https_required',
			__( 'HTTPS is required for this endpoint under current transport policy.', 'uplinkportal' )
		);
	}

	/**
	 * Normalizes secure defaults for wp_remote_* request args.
	 *
	 * @param array<string,mixed> $args Request args.
	 * @return array<string,mixed>
	 */
	public static function secure_remote_args( array $args ): array {
		$normalized = wp_parse_args(
			$args,
			array(
				'timeout'            => 15,
				'sslverify'          => true,
				'reject_unsafe_urls' => true,
				'redirection'        => 2,
				'httpversion'        => '1.1',
			)
		);

		$normalized['timeout']            = max( 1, absint( $normalized['timeout'] ) );
		$normalized['sslverify']          = true;
		$normalized['reject_unsafe_urls'] = true;

		/**
		 * Filters secure transport defaults for remote requests.
		 *
		 * @param array<string,mixed> $normalized Normalized request args.
		 * @param array<string,mixed> $args Original request args.
		 */
		return apply_filters( 'uplinkportal_secure_remote_args', $normalized, $args );
	}

	/**
	 * Returns whether insecure transport is allowed for this environment.
	 *
	 * @param string $url Endpoint URL.
	 * @param string $context Context key.
	 * @return bool
	 */
	private static function allow_insecure_transport( string $url, string $context ): bool {
		$host         = wp_parse_url( $url, PHP_URL_HOST );
		$host         = is_string( $host ) ? strtolower( trim( $host ) ) : '';
		$environment  = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$is_dev_env   = in_array( $environment, array( 'local', 'development' ), true );
		$is_localhost = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| ( '' !== $host && str_ends_with( $host, '.test' ) )
			|| ( '' !== $host && str_ends_with( $host, '.local' ) );
		$default      = $is_dev_env && $is_localhost;

		/**
		 * Filters whether insecure transport may be used.
		 *
		 * @param bool   $default Allow flag.
		 * @param string $url Endpoint URL.
		 * @param string $context Context key.
		 */
		return (bool) apply_filters( 'uplinkportal_allow_insecure_transport', $default, $url, $context );
	}
}
