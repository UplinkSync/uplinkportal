<?php
/**
 * PHPUnit bootstrap.
 *
 * @package UplinkPortal\Tests
 */

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$core_stubs = __DIR__ . '/config/stubs-core.php';
if ( file_exists( $core_stubs ) ) {
	require_once $core_stubs;
}

$support_stubs = __DIR__ . '/config/stubs-support.php';
if ( file_exists( $support_stubs ) ) {
	require_once $support_stubs;
}

$module_stubs = __DIR__ . '/config/stubs-modules.php';
if ( file_exists( $module_stubs ) ) {
	require_once $module_stubs;
}

$portal_stubs = __DIR__ . '/config/stubs-portal.php';
if ( file_exists( $portal_stubs ) ) {
	require_once $portal_stubs;
}

$admin_stubs = __DIR__ . '/config/stubs-admin.php';
if ( file_exists( $admin_stubs ) ) {
	require_once $admin_stubs;
}

$security_stubs = __DIR__ . '/config/stubs-security.php';
if ( file_exists( $security_stubs ) ) {
	require_once $security_stubs;
}

$services_stubs = __DIR__ . '/config/stubs-services.php';
if ( file_exists( $services_stubs ) ) {
	require_once $services_stubs;
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Minimal translation stub for unit tests outside WordPress.
	 *
	 * @param string $text Text to translate.
	 * @return string
	 */
	function __( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Minimal esc_html__ stub for tests.
	 *
	 * @param string $text   Text to escape and translate.
	 * @param string $domain Text domain (unused).
	 * @return string
	 */
	function esc_html__( string $text, string $domain = '' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Minimal esc_html stub for tests.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for tests.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private string $code;
		/**
		 * Error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Optional extra data (ignored).
		 */
		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Returns the first error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Returns the first error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Minimal is_wp_error stub for tests.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
}

// version_compare() is a PHP built-in — no stub needed.

if ( ! defined( 'UPLINKPORTAL_VERSION' ) ) {
	define( 'UPLINKPORTAL_VERSION', '0.2.0' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'UPLOAD_ERR_OK' ) ) {
	define( 'UPLOAD_ERR_OK', 0 );
}

if ( ! defined( 'UPLOAD_ERR_NO_FILE' ) ) {
	define( 'UPLOAD_ERR_NO_FILE', 4 );
}

if ( ! defined( 'UPLINKPORTAL_CRYPTO_SALT' ) ) {
	define( 'UPLINKPORTAL_CRYPTO_SALT', 'replace-with-project-specific-crypto-salt' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Global sanitize_text_field stub for tests.
	 *
	 * Used by string callbacks in array_map() calls (e.g. IntegrationProviderTemplates).
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

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Global sanitize_key stub for tests.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		$key = strtolower( $key );
		return preg_replace( '/[^a-z0-9_\\-]/', '', $key ) ?? '';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal WP_REST_Request stub for tests.
	 */
	class WP_REST_Request {
		/**
		 * Request headers keyed by lowercase name.
		 *
		 * @var array<string,string>
		 */
		private array $headers = array();
		/**
		 * Request parameters (query/body).
		 *
		 * @var array<string,mixed>
		 */
		private array $params = array();

		/**
		 * Returns a request header value.
		 *
		 * @param string $header Header name (lowercase).
		 * @return string
		 */
		public function get_header( string $header ): string {
			return $this->headers[ strtolower( $header ) ] ?? '';
		}

		/**
		 * Sets a request header.
		 *
		 * @param string $header Header name.
		 * @param string $value  Header value.
		 * @return void
		 */
		public function set_header( string $header, string $value ): void {
			$this->headers[ strtolower( $header ) ] = $value;
		}

		/**
		 * Returns a query/body parameter.
		 *
		 * @param string $key Parameter name.
		 * @return mixed
		 */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * Sets a query/body parameter.
		 *
		 * @param string $key   Parameter name.
		 * @param mixed  $value Parameter value.
		 * @return void
		 */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal WP_REST_Response stub for tests.
	 */
	class WP_REST_Response {
		/**
		 * Response data.
		 *
		 * @var mixed
		 */
		public $data;
		/**
		 * HTTP status code.
		 *
		 * @var int
		 */
		public int $status;
		/**
		 * Response headers.
		 *
		 * @var array<string,string>
		 */
		public array $headers = array();

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Response data.
		 * @param int   $status HTTP status code.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Adds a response header.
		 *
		 * @param string $header Header name.
		 * @param string $value  Header value.
		 * @return void
		 */
		public function header( string $header, string $value ): void {
			$this->headers[ $header ] = $value;
		}

		/**
		 * Returns the status code.
		 *
		 * @return int
		 */
		public function get_status(): int {
			return $this->status;
		}

		/**
		 * Returns the response data.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}
	}
}
