<?php
/**
 * Admin module.
 *
 * @package UplinkPortal
 */

use UplinkPortal\Admin\Notices;
use UplinkPortal\Admin\OnboardingPage;
use UplinkPortal\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin actions and notices.
 */
class UplinkPortal_Admin_Module extends UplinkPortal_Base_Module {
	/**
	 * Settings page dependency.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Onboarding page dependency.
	 *
	 * @var OnboardingPage
	 */
	private OnboardingPage $onboarding_page;

	/**
	 * Notices handler.
	 *
	 * @var Notices
	 */
	private Notices $notices;

	/**
	 * Constructor.
	 *
	 * @param SettingsPage   $settings_page Settings handler.
	 * @param OnboardingPage $onboarding_page Onboarding handler.
	 * @param Notices        $notices Notices handler.
	 */
	public function __construct( SettingsPage $settings_page, OnboardingPage $onboarding_page, Notices $notices ) {
		$this->settings_page   = $settings_page;
		$this->onboarding_page = $onboarding_page;
		$this->notices         = $notices;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_action( 'admin_notices', array( $this->notices, 'render_crypto_salt_notice' ) );
		$this->register_action( 'admin_menu', array( $this->settings_page, 'register_menu' ) );
		$this->register_action( 'admin_menu', array( $this->onboarding_page, 'register_menu' ), 20 );
		$this->register_action( 'admin_init', array( $this->settings_page, 'register_settings' ) );
		$this->register_action( 'admin_init', array( $this->onboarding_page, 'maybe_redirect_to_onboarding' ), 99 );
		$this->register_action( 'admin_post_uplinkportal_resync_roles', array( $this->settings_page, 'handle_resync_roles_action' ) );
		$this->register_action( 'admin_post_uplinkportal_save_client_matrix', array( $this->settings_page, 'handle_client_matrix_save_action' ) );
		$this->register_action( 'admin_post_uplinkportal_save_client_entitlements', array( $this->settings_page, 'handle_client_entitlements_save_action' ) );
		$this->register_action( 'admin_post_uplinkportal_save_customization_defaults', array( $this->settings_page, 'handle_customization_save_action' ) );
		$this->register_action( 'admin_post_uplinkportal_save_plan_templates', array( $this->settings_page, 'handle_plan_templates_save_action' ) );
		$this->register_action( 'admin_post_uplinkportal_save_client_portfolio', array( $this->settings_page, 'handle_client_portfolio_save_action' ) );
		$this->register_action( 'admin_post_uplinkportal_upload_client_document', array( $this->settings_page, 'handle_upload_client_document_action' ) );
		$this->register_action( 'admin_post_uplinkportal_delete_client_documents', array( $this->settings_page, 'handle_delete_client_documents_action' ) );
		$this->register_action( 'admin_post_uplinkportal_stream_client_document', array( $this->settings_page, 'handle_stream_client_document_action' ) );
		$this->register_action( 'admin_post_uplinkportal_validate_license', array( $this->settings_page, 'handle_validate_license_action' ) );
		$this->register_action( 'admin_post_uplinkportal_create_purchase_request', array( $this->settings_page, 'handle_purchase_request_action' ) );
		$this->register_action( 'admin_post_uplinkportal_onboarding_step_one', array( $this->onboarding_page, 'handle_step_one' ) );
		$this->register_action( 'admin_post_uplinkportal_onboarding_step_two', array( $this->onboarding_page, 'handle_step_two' ) );
		$this->register_action( 'admin_post_uplinkportal_onboarding_step_three', array( $this->onboarding_page, 'handle_step_three' ) );
		$this->register_action( 'admin_post_uplinkportal_onboarding_step_four', array( $this->onboarding_page, 'handle_step_four' ) );
		$this->register_action( 'admin_post_uplinkportal_onboarding_step_five', array( $this->onboarding_page, 'handle_step_five' ) );
		$this->register_action( 'admin_post_uplinkportal_onboarding_step_six', array( $this->onboarding_page, 'handle_step_six' ) );
			$this->register_action( 'admin_post_uplinkportal_onboarding_step_seven', array( $this->onboarding_page, 'handle_step_seven' ) );
			$this->register_action( 'admin_post_uplinkportal_onboarding_step_eight', array( $this->onboarding_page, 'handle_step_eight' ) );
			$this->register_action( 'current_screen', array( $this->settings_page, 'register_contextual_help_tabs' ) );
			$this->register_action( 'admin_enqueue_scripts', array( $this->settings_page, 'enqueue_admin_assets' ) );
			$this->register_action( 'admin_enqueue_scripts', array( $this->onboarding_page, 'enqueue_assets' ) );
			$this->register_action( 'rest_api_init', array( $this->settings_page, 'register_rest_routes' ) );
	}
}
