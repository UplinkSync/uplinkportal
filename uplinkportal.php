<?php
/**
 * Plugin Name: UplinkPortal
 * Plugin URI:  https://example.com/uplinkportal
 * Description: Public core framework for an MSP client portal with modular integrations.
 * Version:     0.2.0
 * Author:      UplinkPortal Contributors
 * Author URI:  https://example.com
 * License:     AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: uplinkportal
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.3
 *
 * @package UplinkPortal
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'UPLINKPORTAL_VERSION' ) ) {
	define( 'UPLINKPORTAL_VERSION', '0.2.0' );
}

if ( ! defined( 'UPLINKPORTAL_FILE' ) ) {
	define( 'UPLINKPORTAL_FILE', __FILE__ );
}

if ( ! defined( 'UPLINKPORTAL_PATH' ) ) {
	define( 'UPLINKPORTAL_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'UPLINKPORTAL_URL' ) ) {
	define( 'UPLINKPORTAL_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'UPLINKPORTAL_TEXT_DOMAIN' ) ) {
	define( 'UPLINKPORTAL_TEXT_DOMAIN', 'uplinkportal' );
}

if ( ! defined( 'UPLINKPORTAL_PRO_AVAILABLE_SEPARATELY' ) ) {
	define( 'UPLINKPORTAL_PRO_AVAILABLE_SEPARATELY', true );
}

if ( ! defined( 'UPLINKPORTAL_CRYPTO_SALT' ) ) {
	define( 'UPLINKPORTAL_CRYPTO_SALT', 'replace-with-project-specific-crypto-salt' );
}

if ( file_exists( UPLINKPORTAL_PATH . 'vendor/autoload.php' ) ) {
	require_once UPLINKPORTAL_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix   = 'UplinkPortal\\';
			$base_dir = UPLINKPORTAL_PATH . 'src/';

			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $prefix ) );
			$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

require_once UPLINKPORTAL_PATH . 'includes/class-base-module.php';
require_once UPLINKPORTAL_PATH . 'includes/functions-assets.php';
require_once UPLINKPORTAL_PATH . 'includes/class-bootstrap.php';
require_once UPLINKPORTAL_PATH . 'includes/class-main-module.php';
require_once UPLINKPORTAL_PATH . 'includes/class-admin.php';
require_once UPLINKPORTAL_PATH . 'includes/class-frontend.php';
require_once UPLINKPORTAL_PATH . 'includes/class-ajax.php';
require_once UPLINKPORTAL_PATH . 'includes/class-rest.php';
require_once UPLINKPORTAL_PATH . 'includes/class-assets.php';

register_activation_hook(
	UPLINKPORTAL_FILE,
	static function ( bool $network_wide ): void {
		if ( $network_wide && function_exists( 'get_sites' ) ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				\UplinkPortal\Core\Activator::activate();
				restore_current_blog();
			}
		} else {
			\UplinkPortal\Core\Activator::activate();
		}
	}
);
register_deactivation_hook( UPLINKPORTAL_FILE, array( 'UplinkPortal\\Core\\Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'UplinkPortal_Bootstrap', 'run' ) );
