<?php
/**
 * Sensitive-data pseudonymization helpers.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Applies deterministic pseudonymization for audit-safe storage.
 */
final class SensitiveDataPseudonymizer {
	/**
	 * Pseudonymizes a free-form message by masking common sensitive patterns.
	 *
	 * @param string $message Message text.
	 * @return string
	 */
	public static function pseudonymize_message( string $message ): string {
		$message = sanitize_text_field( $message );
		if ( '' === $message ) {
			return '';
		}

		$patterns = array(
			'/\b(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*\b/i' => '$1[masked]',
			'/\b(api[_-]?key|token|secret|password)\s*[:=]\s*[^\s,;]+/i' => '$1=[masked]',
			'/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i' => '[email-masked]',
		);

		foreach ( $patterns as $pattern => $replacement ) {
			$message = (string) preg_replace( $pattern, $replacement, $message );
		}

		/**
		 * Filters pseudonymized audit message text.
		 *
		 * @param string $message Pseudonymized message.
		 */
		return (string) apply_filters( 'uplinkportal_pseudonymized_message', $message );
	}

	/**
	 * Pseudonymizes an IP address into deterministic hash token.
	 *
	 * @param string $ip Raw IP.
	 * @return string
	 */
	public static function pseudonymize_ip( string $ip ): string {
		$ip = sanitize_text_field( trim( $ip ) );
		if ( '' === $ip ) {
			return '';
		}

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return 'ip_' . substr( self::hash_token( $ip ), 0, 16 );
	}

	/**
	 * Pseudonymizes user agent string into deterministic hash token.
	 *
	 * @param string $agent User agent.
	 * @return string
	 */
	public static function pseudonymize_user_agent( string $agent ): string {
		$agent = sanitize_text_field( trim( $agent ) );
		if ( '' === $agent ) {
			return '';
		}

		return 'ua_' . substr( self::hash_token( $agent ), 0, 20 );
	}

	/**
	 * Returns deterministic HMAC hash token for one value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function hash_token( string $value ): string {
		$key = wp_salt( 'auth' ) . '|uplinkportal_pseudonymizer_v1';
		return hash_hmac( 'sha256', $value, $key );
	}
}
