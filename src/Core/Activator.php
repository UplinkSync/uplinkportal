<?php
/**
 * Activation/deactivation routines.
 *
 * @package UplinkPortal\Core
 */

namespace UplinkPortal\Core;

use UplinkPortal\Core\Security\CapabilityManager;
use UplinkPortal\Core\Support\ClientAccessStore;
use UplinkPortal\Core\Support\ClientDocumentsStore;
use UplinkPortal\Core\Support\DataRetentionScheduler;
use UplinkPortal\Core\Support\EntitlementService;
use UplinkPortal\Core\Support\Options;
use UplinkPortal\Core\Support\PortalCustomizationStore;
use UplinkPortal\Core\Support\PurchaseRequestStore;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin lifecycle hooks.
 */
class Activator {
	/**
	 * Runs on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			wp_die(
				esc_html__( 'UplinkPortal requires WordPress 6.0 or higher. Please upgrade WordPress.', 'uplinkportal' ),
				esc_html__( 'Plugin Activation Error', 'uplinkportal' ),
				array( 'back_link' => true )
			);
		}

		if ( PHP_VERSION_ID < 80300 ) {
			wp_die(
				esc_html__( 'UplinkPortal requires PHP 8.3 or higher. Please upgrade PHP.', 'uplinkportal' ),
				esc_html__( 'Plugin Activation Error', 'uplinkportal' ),
				array( 'back_link' => true )
			);
		}

		CapabilityManager::add_default_caps();
		$options = new Options();
		$options->ensure_schema();
		$options->ensure_instance_id();
		( new ClientDocumentsStore( $options ) )->ensure_schema();
		( new ClientAccessStore() )->ensure_schema();
		( new EntitlementService() )->ensure_schema();
		( new PortalCustomizationStore() )->ensure_storage_ready();
		( new PurchaseRequestStore() )->ensure_schema();
		update_option( 'uplinkportal_onboarding_required', 1, false );
		update_option( 'uplinkportal_onboarding_progress_step', 1, false );

		$stored_version = (string) get_option( 'uplinkportal_db_version', '0.0.0' );
		if ( version_compare( $stored_version, UPLINKPORTAL_VERSION, '<' ) ) {
			( new ClientDocumentsStore( $options ) )->ensure_schema();
			( new ClientAccessStore() )->ensure_schema();
			( new EntitlementService() )->ensure_schema();
			( new PurchaseRequestStore() )->ensure_schema();
			update_option( 'uplinkportal_db_version', UPLINKPORTAL_VERSION, false );
		}
	}

	/**
	 * Runs on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		CapabilityManager::remove_default_caps();
		DataRetentionScheduler::clear_schedule();
	}
}
