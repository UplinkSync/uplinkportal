<?php
/**
 * Private portal customization storage.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Stores portal preview/customization settings in a private filesystem location.
 */
class PortalCustomizationStore {
	/**
	 * Storage directory.
	 *
	 * @var string
	 */
	private string $storage_dir;

	/**
	 * JSON settings file.
	 *
	 * @var string
	 */
	private string $settings_file;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : trailingslashit( ABSPATH ) . 'wp-content';
		$base_dir    = trailingslashit( $content_dir ) . 'uplinkportal-private/portal-preview';

		$this->storage_dir   = $base_dir;
		$this->settings_file = trailingslashit( $base_dir ) . 'settings.json';
	}

	/**
	 * Ensures private storage directory and shield files exist.
	 *
	 * @return bool
	 */
	public function ensure_storage_ready(): bool {
		if ( ! file_exists( $this->storage_dir ) ) {
			wp_mkdir_p( $this->storage_dir );
		}

		if ( ! is_dir( $this->storage_dir ) || ! is_writable( $this->storage_dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Verifies plugin-managed private directory is writable before persisting settings.
			return false;
		}

		$this->write_shield_file( trailingslashit( $this->storage_dir ) . 'index.php', "<?php\n// Silence is golden.\n" );
		$this->write_shield_file(
			trailingslashit( $this->storage_dir ) . '.htaccess',
			"Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
		);

		return true;
	}

	/**
	 * Saves portal settings payload to private JSON file.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return bool
	 */
	public function save_settings( array $payload ): bool {
		if ( ! $this->ensure_storage_ready() ) {
			return false;
		}

		$sanitized = $this->sanitize_payload( $payload );
		$encoded   = wp_json_encode( $sanitized, JSON_PRETTY_PRINT );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return false;
		}

		$result = file_put_contents( $this->settings_file, $encoded ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes sanitized JSON to plugin-managed private directory.
		return false !== $result;
	}

	/**
	 * Returns sanitized stored portal settings with defaults.
	 *
	 * @return array<string,string>
	 */
	public function read_settings(): array {
		$defaults = $this->default_payload();

		if ( ! file_exists( $this->settings_file ) ) {
			return $defaults;
		}

		$raw = file_get_contents( $this->settings_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads plugin-managed private JSON file.
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return $defaults;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return $defaults;
		}

		return $this->sanitize_payload( $decoded );
	}

	/**
	 * Returns private directory path for admin transparency.
	 *
	 * @return string
	 */
	public function get_storage_dir(): string {
		return $this->storage_dir;
	}

	/**
	 * Sanitizes payload shape.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,string>
	 */
	private function sanitize_payload( array $payload ): array {
		$defaults = $this->default_payload();

		$portal_access_policy = isset( $payload['portal_access_policy'] ) ? sanitize_key( (string) $payload['portal_access_policy'] ) : $defaults['portal_access_policy'];
		if ( ! in_array( $portal_access_policy, array( 'wp_login_required', 'role_restricted', 'invite_only' ), true ) ) {
			$portal_access_policy = $defaults['portal_access_policy'];
		}

		$registration_policy = isset( $payload['client_registration_policy'] ) ? sanitize_key( (string) $payload['client_registration_policy'] ) : $defaults['client_registration_policy'];
		if ( ! in_array( $registration_policy, array( 'invite_only', 'admin_approval', 'open_registration' ), true ) ) {
			$registration_policy = $defaults['client_registration_policy'];
		}

		$brand_color = isset( $payload['brand_primary_color'] ) ? sanitize_hex_color( (string) $payload['brand_primary_color'] ) : $defaults['brand_primary_color'];
		if ( ! is_string( $brand_color ) || '' === $brand_color ) {
			$brand_color = $defaults['brand_primary_color'];
		}

		return array(
			'portal_access_policy'       => $portal_access_policy,
			'client_registration_policy' => $registration_policy,
			'brand_primary_color'        => $brand_color,
			'portal_welcome_message'     => isset( $payload['portal_welcome_message'] ) ? sanitize_textarea_field( (string) $payload['portal_welcome_message'] ) : $defaults['portal_welcome_message'],
			'portal_footer_note'         => isset( $payload['portal_footer_note'] ) ? sanitize_textarea_field( (string) $payload['portal_footer_note'] ) : $defaults['portal_footer_note'],
		);
	}

	/**
	 * Writes a shield file if missing.
	 *
	 * @param string $path File path.
	 * @param string $contents File contents.
	 * @return void
	 */
	private function write_shield_file( string $path, string $contents ): void {
		if ( file_exists( $path ) ) {
			return;
		}

		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes minimal shield file to plugin-managed private directory.
	}

	/**
	 * Returns default payload.
	 *
	 * @return array<string,string>
	 */
	private function default_payload(): array {
		return array(
			'portal_access_policy'       => 'wp_login_required',
			'client_registration_policy' => 'invite_only',
			'brand_primary_color'        => '#1d4ed8',
			'portal_welcome_message'     => '',
			'portal_footer_note'         => '',
		);
	}
}
