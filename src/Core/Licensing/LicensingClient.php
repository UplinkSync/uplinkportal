<?php
/**
 * Licensing API client.
 *
 * @package UplinkPortal\Core\Licensing
 */

namespace UplinkPortal\Core\Licensing;

use UplinkPortal\Core\Support\Options;
use UplinkPortal\Core\Support\TransportSecurityPolicy;

defined( 'ABSPATH' ) || exit;

/**
 * Calls Cloudflare Worker licensing endpoints.
 */
class LicensingClient {
	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Free module features that do not require a license.
	 *
	 * @var array<int,string>
	 */
	private const FREE_FEATURES = array(
		'finance',
		'it_support',
		'projects',
		'custom_dummy',
	);

	/**
	 * Constructor.
	 *
	 * @param Options $options Options helper.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Validates license.
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array<string,mixed>
	 */
	public function validate_license( bool $force_refresh = false ): array {
		$cache_key = $this->validation_cache_key();

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$worker_base  = $this->options->get_licensing_worker_base_url();
		$license_key  = $this->options->get_license_key();
		$product_slug = $this->options->get_licensing_product_slug();
		$instance_id  = $this->options->get_instance_id();
		$domain       = $this->normalize_domain( site_url() );

		if ( '' === $worker_base || '' === $license_key || '' === $product_slug || '' === $instance_id || '' === $domain ) {
			return $this->cache_result(
				$cache_key,
				array(
					'valid'    => false,
					'status'   => 'configuration_required',
					'message'  => __( 'Licensing is not fully configured yet.', 'uplinkportal' ),
					'features' => array(),
				)
			);
		}

		$transport_check = TransportSecurityPolicy::validate_url( $worker_base, 'licensing_worker' );
		if ( is_wp_error( $transport_check ) ) {
			return $this->cache_result(
				$cache_key,
				array(
					'valid'    => false,
					'status'   => 'transport_policy_error',
					'message'  => $transport_check->get_error_message(),
					'features' => array(),
				)
			);
		}

		$response = wp_remote_post(
			$worker_base . '/validate-license',
			TransportSecurityPolicy::secure_remote_args(
				array(
					'timeout' => 20,
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'license_key'  => $license_key,
							'product_slug' => $product_slug,
							'instance_id'  => $instance_id,
							'domain'       => $domain,
						)
					),
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->cache_result(
				$cache_key,
				array(
					'valid'    => false,
					'status'   => 'request_failed',
					'message'  => $response->get_error_message(),
					'features' => array(),
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$decoded     = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded     = is_array( $decoded ) ? $decoded : array();

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->cache_result(
				$cache_key,
				array(
					'valid'    => false,
					'status'   => 'http_error',
					'message'  => isset( $decoded['error'] ) ? sanitize_text_field( (string) $decoded['error'] ) : __( 'License server returned an error.', 'uplinkportal' ),
					'features' => array(),
				)
			);
		}

		$result = array(
			'valid'               => ! empty( $decoded['valid'] ),
			'status'              => isset( $decoded['status'] ) ? sanitize_key( (string) $decoded['status'] ) : 'unknown',
			'message'             => isset( $decoded['message'] ) ? sanitize_text_field( (string) $decoded['message'] ) : '',
			'plan_slug'           => isset( $decoded['plan_slug'] ) ? sanitize_key( (string) $decoded['plan_slug'] ) : '',
			'features'            => isset( $decoded['features'] ) && is_array( $decoded['features'] ) ? array_values( array_map( 'sanitize_key', $decoded['features'] ) ) : array(),
			'max_activations'     => isset( $decoded['max_activations'] ) ? absint( $decoded['max_activations'] ) : 0,
			'current_activations' => isset( $decoded['current_activations'] ) ? absint( $decoded['current_activations'] ) : 0,
			'expires_at'          => isset( $decoded['expires_at'] ) ? sanitize_text_field( (string) $decoded['expires_at'] ) : null,
			'checked_at'          => gmdate( 'c' ),
		);

		return $this->cache_result( $cache_key, $result );
	}

	/**
	 * Returns whether feature is licensed.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public function is_feature_enabled( string $feature ): bool {
		$feature = sanitize_key( $feature );
		if ( '' === $feature ) {
			return false;
		}

		if ( in_array( $feature, self::FREE_FEATURES, true ) ) {
			return true;
		}

		$validation = $this->validate_license( false );
		if ( empty( $validation['valid'] ) || empty( $validation['features'] ) || ! is_array( $validation['features'] ) ) {
			return false;
		}

		$features = array_map( 'sanitize_key', $validation['features'] );
		return in_array( '*', $features, true ) || in_array( $feature, $features, true );
	}

	/**
	 * Clears cached validation response.
	 *
	 * @return void
	 */
	public function clear_validation_cache(): void {
		delete_transient( $this->validation_cache_key() );
	}

	/**
	 * Normalizes hostname from URL or host-like input.
	 *
	 * @param string $url URL or host.
	 * @return string
	 */
	public function normalize_domain( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			$host = $url;
		}

		$host = strtolower( trim( $host ) );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Builds transient cache key for license tuple.
	 *
	 * @return string
	 */
	private function validation_cache_key(): string {
		$raw = $this->options->get_license_key()
			. '|'
			. $this->options->get_instance_id()
			. '|'
			. $this->options->get_licensing_product_slug()
			. '|'
			. $this->normalize_domain( site_url() );

		return 'uplinkportal_license_' . md5( $raw );
	}

	/**
	 * Caches validation response.
	 *
	 * @param string              $cache_key Cache key.
	 * @param array<string,mixed> $result Result payload.
	 * @return array<string,mixed>
	 */
	private function cache_result( string $cache_key, array $result ): array {
		set_transient( $cache_key, $result, $this->options->get_licensing_cache_ttl() );
		return $result;
	}
}
