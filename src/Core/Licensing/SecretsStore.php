<?php
/**
 * Secure secret storage.
 *
 * @package UplinkPortal\Core\Licensing
 */

namespace UplinkPortal\Core\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * Stores sensitive integration secrets with optional libsodium encryption.
 */
class SecretsStore {
	/**
	 * Saves secret value.
	 *
	 * @param string $option_key Option key.
	 * @param string $secret Plain secret value.
	 * @return void
	 */
	public function save_secret( string $option_key, string $secret ): void {
		$option_key = sanitize_key( $option_key );
		$secret     = trim( $secret );

		if ( '' === $option_key || '' === $secret ) {
			return;
		}

		update_option( $option_key, $this->encrypt_payload( $secret ), false );
	}

	/**
	 * Returns secret value.
	 *
	 * @param string $option_key Option key.
	 * @return string
	 */
	public function get_secret( string $option_key ): string {
		$option_key = sanitize_key( $option_key );
		if ( '' === $option_key ) {
			return '';
		}

		$payload = get_option( $option_key, null );

		if ( is_array( $payload ) && isset( $payload['version'], $payload['value'] ) ) {
			return $this->decrypt_payload( $payload );
		}

		if ( is_string( $payload ) ) {
			return $payload;
		}

		return '';
	}

	/**
	 * Returns saved-at timestamp for a secret option, if available.
	 *
	 * @param string $option_key Option key.
	 * @return string
	 */
	public function get_secret_saved_at( string $option_key ): string {
		$option_key = sanitize_key( $option_key );
		if ( '' === $option_key ) {
			return '';
		}

		$payload = get_option( $option_key, null );
		if ( is_array( $payload ) && isset( $payload['saved_at'] ) ) {
			return sanitize_text_field( (string) $payload['saved_at'] );
		}

		return '';
	}

	/**
	 * Returns whether key has a stored secret.
	 *
	 * @param string $option_key Option key.
	 * @return bool
	 */
	public function has_secret( string $option_key ): bool {
		$option_key = sanitize_key( $option_key );
		if ( '' === $option_key ) {
			return false;
		}

		$payload = get_option( $option_key, null );

		if ( is_array( $payload ) && isset( $payload['value'] ) ) {
			return '' !== (string) $payload['value'];
		}

		if ( is_string( $payload ) ) {
			return '' !== $payload;
		}

		return false;
	}

	/**
	 * Saves validation API key for a module section.
	 *
	 * @param string $section Validation section.
	 * @param string $api_key API key.
	 * @return void
	 */
	public function save_validation_api_key( string $section, string $api_key ): void {
		$this->save_secret( self::validation_api_key_option( $section ), $api_key );
	}

	/**
	 * Returns validation API key for a module section.
	 *
	 * @param string $section Validation section.
	 * @return string
	 */
	public function get_validation_api_key( string $section ): string {
		return $this->get_secret( self::validation_api_key_option( $section ) );
	}

	/**
	 * Returns whether validation API key exists for section.
	 *
	 * @param string $section Validation section.
	 * @return bool
	 */
	public function has_validation_api_key( string $section ): bool {
		return $this->has_secret( self::validation_api_key_option( $section ) );
	}

	/**
	 * Returns validation API key saved-at timestamp for section.
	 *
	 * @param string $section Validation section.
	 * @return string
	 */
	public function get_validation_api_key_saved_at( string $section ): string {
		return $this->get_secret_saved_at( self::validation_api_key_option( $section ) );
	}

	/**
	 * Saves one of the third-party integration secrets.
	 *
	 * @param string $secret_name Supported secret name.
	 * @param string $secret Secret value.
	 * @return void
	 */
	public function save_third_party_secret( string $secret_name, string $secret ): void {
		$this->save_secret( self::third_party_secret_option( $secret_name ), $secret );
	}

	/**
	 * Returns third-party integration secret.
	 *
	 * @param string $secret_name Supported secret name.
	 * @return string
	 */
	public function get_third_party_secret( string $secret_name ): string {
		return $this->get_secret( self::third_party_secret_option( $secret_name ) );
	}

	/**
	 * Returns whether third-party secret exists.
	 *
	 * @param string $secret_name Supported secret name.
	 * @return bool
	 */
	public function has_third_party_secret( string $secret_name ): bool {
		return $this->has_secret( self::third_party_secret_option( $secret_name ) );
	}

	/**
	 * Saves backend service token.
	 *
	 * Used for backend-to-backend internal operations such as document key exchange.
	 *
	 * @param string $token Service token.
	 * @return void
	 */
	public function save_backend_service_token( string $token ): void {
		$this->save_secret( self::backend_service_token_option(), $token );
	}

	/**
	 * Returns backend service token.
	 *
	 * @return string
	 */
	public function get_backend_service_token(): string {
		return $this->get_secret( self::backend_service_token_option() );
	}

	/**
	 * Returns whether backend service token exists.
	 *
	 * @return bool
	 */
	public function has_backend_service_token(): bool {
		return $this->has_secret( self::backend_service_token_option() );
	}

	/**
	 * Returns backend service token saved-at timestamp.
	 *
	 * @return string
	 */
	public function get_backend_service_token_saved_at(): string {
		return $this->get_secret_saved_at( self::backend_service_token_option() );
	}

	/**
	 * Saves Vault connection reference.
	 *
	 * @param string $reference Opaque reference from secure backend/add-on.
	 * @return void
	 */
	public function save_vault_connection_reference( string $reference ): void {
		$this->save_secret( self::vault_connection_reference_option(), $reference );
	}

	/**
	 * Returns Vault connection reference.
	 *
	 * @return string
	 */
	public function get_vault_connection_reference(): string {
		return $this->get_secret( self::vault_connection_reference_option() );
	}

	/**
	 * Returns whether Vault connection reference exists.
	 *
	 * @return bool
	 */
	public function has_vault_connection_reference(): bool {
		return $this->has_secret( self::vault_connection_reference_option() );
	}

	/**
	 * Returns Vault connection reference saved-at timestamp.
	 *
	 * @return string
	 */
	public function get_vault_connection_reference_saved_at(): string {
		return $this->get_secret_saved_at( self::vault_connection_reference_option() );
	}

	/**
	 * Saves OAuth connection/token vault reference for one model.
	 *
	 * @param string $model_id Canonical model id.
	 * @param string $reference Opaque reference from backend token vault.
	 * @return void
	 */
	public function save_oauth_connection_reference( string $model_id, string $reference ): void {
		$this->save_secret( self::oauth_connection_reference_option( $model_id ), $reference );
	}

	/**
	 * Returns OAuth connection/token vault reference for one model.
	 *
	 * @param string $model_id Canonical model id.
	 * @return string
	 */
	public function get_oauth_connection_reference( string $model_id ): string {
		return $this->get_secret( self::oauth_connection_reference_option( $model_id ) );
	}

	/**
	 * Returns whether OAuth connection reference exists for one model.
	 *
	 * @param string $model_id Canonical model id.
	 * @return bool
	 */
	public function has_oauth_connection_reference( string $model_id ): bool {
		return $this->has_secret( self::oauth_connection_reference_option( $model_id ) );
	}

	/**
	 * Returns OAuth connection reference saved-at timestamp for model.
	 *
	 * @param string $model_id Canonical model id.
	 * @return string
	 */
	public function get_oauth_connection_reference_saved_at( string $model_id ): string {
		return $this->get_secret_saved_at( self::oauth_connection_reference_option( $model_id ) );
	}

	/**
	 * Returns whether libsodium is available.
	 *
	 * @return bool
	 */
	public function sodium_available(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'sodium_crypto_secretbox_open' );
	}

	/**
	 * Warning message for plaintext fallback mode.
	 *
	 * @return string
	 */
	public function get_encryption_warning(): string {
		if ( $this->sodium_available() ) {
			return '';
		}

		return __( 'libsodium is not available. Secrets are stored in plaintext. Enable ext-sodium and harden DB access and backup controls immediately.', 'uplinkportal' );
	}

	/**
	 * Option key for a validation section key.
	 *
	 * @param string $section Validation section.
	 * @return string
	 */
	public static function validation_api_key_option( string $section ): string {
		return 'uplinkportal_secret_validation_api_key_' . sanitize_key( $section );
	}

	/**
	 * Option key for supported third-party secret names.
	 *
	 * @param string $secret_name Secret name.
	 * @return string
	 */
	public static function third_party_secret_option( string $secret_name ): string {
		$secret_name = sanitize_key( $secret_name );
		$allowed     = array( 'api_key', 'api_secret' );

		if ( ! in_array( $secret_name, $allowed, true ) ) {
			return 'uplinkportal_secret_third_party_unknown';
		}

		return 'uplinkportal_secret_third_party_' . $secret_name;
	}

	/**
	 * Option key for backend service token.
	 *
	 * @return string
	 */
	public static function backend_service_token_option(): string {
		return 'uplinkportal_secret_backend_service_token';
	}

	/**
	 * Option key for Vault connection reference.
	 *
	 * @return string
	 */
	public static function vault_connection_reference_option(): string {
		return 'uplinkportal_secret_vault_connection_ref';
	}

	/**
	 * Option key for OAuth connection/token vault reference by model.
	 *
	 * @param string $model_id Canonical model id.
	 * @return string
	 */
	public static function oauth_connection_reference_option( string $model_id ): string {
		$model_id = sanitize_key( $model_id );
		$allowed  = array( 'finance', 'it_support', 'projects', 'licensing', 'documents' );

		if ( ! in_array( $model_id, $allowed, true ) ) {
			return 'uplinkportal_secret_oauth_connection_unknown';
		}

		return 'uplinkportal_secret_oauth_connection_' . $model_id;
	}

	/**
	 * Encrypts payload when sodium is available.
	 *
	 * @param string $plaintext Secret value.
	 * @return array<string,mixed>
	 */
	private function encrypt_payload( string $plaintext ): array {
		if ( ! $this->sodium_available() ) {
			return array(
				'version'   => 'plaintext_v1',
				'value'     => $plaintext,
				'encrypted' => false,
				'saved_at'  => gmdate( 'c' ),
			);
		}

		try {
			$key        = $this->derive_key();
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

			return array(
				'version'   => 'sodium_secretbox_v1',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for ciphertext transport, not code obfuscation.
				'value'     => base64_encode( $nonce . $ciphertext ),
				'encrypted' => true,
				'saved_at'  => gmdate( 'c' ),
			);
		} catch ( \Exception $exception ) {
			return array(
				'version'   => 'plaintext_v1',
				'value'     => $plaintext,
				'encrypted' => false,
				'saved_at'  => gmdate( 'c' ),
			);
		}
	}

	/**
	 * Decrypts payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	private function decrypt_payload( array $payload ): string {
		$version = isset( $payload['version'] ) ? (string) $payload['version'] : '';
		$value   = isset( $payload['value'] ) ? (string) $payload['value'] : '';

		if ( 'plaintext_v1' === $version ) {
			return $value;
		}

		if ( 'sodium_secretbox_v1' !== $version || '' === $value || ! $this->sodium_available() ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Used for ciphertext transport, not code obfuscation.
		$decoded = base64_decode( $value, true );
		if ( ! is_string( $decoded ) || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $this->derive_key() );

		if ( false === $plaintext ) {
			return '';
		}

		return $plaintext;
	}

	/**
	 * Derives encryption key from WordPress salts and plugin salt.
	 *
	 * @return string
	 */
	private function derive_key(): string {
		$material = wp_salt( 'auth' )
			. wp_salt( 'secure_auth' )
			. wp_salt( 'logged_in' )
			. wp_salt( 'nonce' )
			. ( defined( 'UPLINKPORTAL_CRYPTO_SALT' ) ? UPLINKPORTAL_CRYPTO_SALT : 'uplinkportal-fallback-salt' );

		return hash( 'sha256', $material, true );
	}
}
