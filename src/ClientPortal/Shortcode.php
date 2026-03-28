<?php
/**
 * Portal shortcode handler.
 *
 * @package UplinkPortal\ClientPortal
 */

namespace UplinkPortal\ClientPortal;

use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Support\ClientDocumentsStore;
use UplinkPortal\Core\Support\Options;
use UplinkPortal\Core\Support\PortalCustomizationStore;

defined( 'ABSPATH' ) || exit;

/**
 * Registers [uplinkportal] shortcode.
 */
class Shortcode {
	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Template loader.
	 *
	 * @var TemplateLoader
	 */
	private TemplateLoader $templates;

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Client documents service.
	 *
	 * @var ClientDocumentsStore
	 */
	private ClientDocumentsStore $client_documents;

	/**
	 * Private portal customization store.
	 *
	 * @var PortalCustomizationStore
	 */
	private PortalCustomizationStore $portal_customization_store;

	/**
	 * Constructor.
	 *
	 * @param ModuleManager            $module_manager Module manager.
	 * @param Options                  $options Options helper.
	 * @param ClientDocumentsStore     $client_documents Client document store.
	 * @param PortalCustomizationStore $portal_customization_store Portal customization store.
	 */
	public function __construct( ModuleManager $module_manager, Options $options, ClientDocumentsStore $client_documents, PortalCustomizationStore $portal_customization_store ) {
		$this->module_manager             = $module_manager;
		$this->templates                  = new TemplateLoader();
		$this->options                    = $options;
		$this->client_documents           = $client_documents;
		$this->portal_customization_store = $portal_customization_store;
	}

	/**
	 * Hook shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'uplinkportal', array( $this, 'render' ) );
	}

	/**
	 * Renders portal shell.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return esc_html__( 'Please sign in to access the portal.', 'uplinkportal' );
		}

		if ( ! current_user_can( 'client_access' ) ) {
			return esc_html__( 'You are not allowed to access this portal.', 'uplinkportal' );
		}

		$module_payload     = array();
		$workspace_sections = $this->get_workspace_sections();

		foreach ( $this->module_manager->enabled() as $module ) {
			$module_payload[] = array(
				'id'    => $module->id(),
				'label' => $module->label(),
			);
		}

		$portal_defaults = $this->get_portal_defaults();
		$documents       = $this->client_documents->get_documents_for_client( get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status query param.
		$request_status = isset( $_GET['uplinkportal_purchase_request'] ) ? sanitize_key( (string) $_GET['uplinkportal_purchase_request'] ) : '';
		$request_items  = $this->get_client_request_items();

		return $this->templates->render(
			'portal-shell.php',
			array(
				'modules'                 => $module_payload,
				'workspace_sections'      => $workspace_sections,
				'client_documents'        => $documents,
				'brand_primary_color'     => $portal_defaults['brand_primary_color'],
				'portal_welcome_message'  => $portal_defaults['portal_welcome_message'],
				'portal_footer_note'      => $portal_defaults['portal_footer_note'],
				'purchase_request_status' => $request_status,
				'request_items'           => $request_items,
			)
		);
	}

	/**
	 * Returns selectable request targets for client-initiated fulfillment requests.
	 *
	 * @return array<string,string>
	 */
	private function get_client_request_items(): array {
		$service_modules = $this->options->get_service_modules();
		$item_labels     = array(
			'finance'      => __( 'Finance Services', 'uplinkportal' ),
			'it_support'   => __( 'IT Support Services', 'uplinkportal' ),
			'projects'     => __( 'Project Services', 'uplinkportal' ),
			'custom_dummy' => __( 'Custom Services', 'uplinkportal' ),
		);
		$items           = array();

		foreach ( $item_labels as $item_key => $item_label ) {
			if ( ! empty( $service_modules[ $item_key ] ) ) {
				$items[ $item_key ] = $item_label;
			}
		}

		if ( empty( $items ) ) {
			$items['general'] = __( 'General Service Request', 'uplinkportal' );
		}

		return $items;
	}

	/**
	 * Returns saved portal presentation defaults.
	 *
	 * @return array<string,string>
	 */
	private function get_portal_defaults(): array {
		$private_portal_defaults = $this->portal_customization_store->read_settings();

		$stored = get_option( 'uplinkportal_onboarding', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$brand_color = isset( $private_portal_defaults['brand_primary_color'] ) && '' !== $private_portal_defaults['brand_primary_color']
			? sanitize_hex_color( (string) $private_portal_defaults['brand_primary_color'] )
			: ( isset( $stored['brand_primary_color'] ) ? sanitize_hex_color( (string) $stored['brand_primary_color'] ) : '#1d4ed8' );
		if ( ! is_string( $brand_color ) || '' === $brand_color ) {
			$brand_color = '#1d4ed8';
		}

		$default_welcome = sprintf(
			/* translators: %s site name. */
			__( 'Welcome to %s Client Workspace.', 'uplinkportal' ),
			sanitize_text_field( (string) get_bloginfo( 'name' ) )
		);
		$welcome_message = isset( $private_portal_defaults['portal_welcome_message'] ) && '' !== trim( (string) $private_portal_defaults['portal_welcome_message'] )
			? sanitize_textarea_field( (string) $private_portal_defaults['portal_welcome_message'] )
			: ( isset( $stored['portal_welcome_message'] ) ? sanitize_textarea_field( (string) $stored['portal_welcome_message'] ) : $default_welcome );
		if ( '' === trim( $welcome_message ) ) {
			$welcome_message = $default_welcome;
		}

		$footer_note = isset( $private_portal_defaults['portal_footer_note'] ) && '' !== trim( (string) $private_portal_defaults['portal_footer_note'] )
			? sanitize_textarea_field( (string) $private_portal_defaults['portal_footer_note'] )
			: ( isset( $stored['portal_footer_note'] ) ? sanitize_textarea_field( (string) $stored['portal_footer_note'] ) : '' );

		return array(
			'brand_primary_color'    => $brand_color,
			'portal_welcome_message' => $welcome_message,
			'portal_footer_note'     => $footer_note,
		);
	}

	/**
	 * Returns client workspace sections based on enabled service modules.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_workspace_sections(): array {
		$service_modules = $this->options->get_service_modules();
		$sections        = array();

		if ( ! empty( $service_modules['projects'] ) ) {
			$sections[] = array(
				'id'          => 'my-projects',
				'label'       => __( 'My Projects', 'uplinkportal' ),
				'description' => __( 'Track project progress, milestones, and delivery updates.', 'uplinkportal' ),
			);
			$sections[] = array(
				'id'          => 'documents-agreements',
				'label'       => __( 'Documents & Agreements', 'uplinkportal' ),
				'description' => __( 'View shared documents, contracts, and agreement history.', 'uplinkportal' ),
			);
			$sections[] = array(
				'id'          => 'my-services',
				'label'       => __( 'My Services', 'uplinkportal' ),
				'description' => __( 'Review active services and current plan details.', 'uplinkportal' ),
			);
		}

		if ( ! empty( $service_modules['it_support'] ) ) {
			$sections[] = array(
				'id'          => 'help-desk',
				'label'       => __( 'Help Desk', 'uplinkportal' ),
				'description' => __( 'Create support requests and review the status of your tickets.', 'uplinkportal' ),
			);
		}

		if ( ! empty( $service_modules['finance'] ) ) {
			$sections[] = array(
				'id'          => 'billing-center',
				'label'       => __( 'Billing Center', 'uplinkportal' ),
				'description' => __( 'Check invoices, invoice status, and billing history.', 'uplinkportal' ),
			);
		}

		return $sections;
	}
}
