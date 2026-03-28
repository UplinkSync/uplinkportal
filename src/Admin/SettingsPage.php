<?php
/**
 * Settings page.
 *
 * @package UplinkPortal\Admin
 */

namespace UplinkPortal\Admin;

use UplinkPortal\Admin\UI\IntegrationSecurityUi;
use UplinkPortal\Admin\UI\ClientPortfolioUi;
use UplinkPortal\Admin\UI\ModuleProfilesAutomationUi;
use UplinkPortal\Admin\UI\AgreementsRecordsUi;
use UplinkPortal\Admin\UI\ConfigClientsUi;
use UplinkPortal\Admin\UI\ConfigCompanyInfoUi;
use UplinkPortal\Admin\UI\ConfigModulesUi;
use UplinkPortal\Admin\UI\ConfigPortalPreviewUi;
use UplinkPortal\Admin\UI\ConfigSecurityUi;
use UplinkPortal\Admin\UI\OperationsDashboardUi;
use UplinkPortal\Admin\UI\ServiceCatalogUi;
use UplinkPortal\Core\Services\ServiceDefinitionStore;
use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\ClientPortal\TemplateLoader;
use UplinkPortal\Core\Licensing\LicensingClient;
use UplinkPortal\Core\Services\ProviderCatalogService;
use UplinkPortal\Core\Security\CentralAuditJournal;
use UplinkPortal\Core\Security\CapabilityManager;
use UplinkPortal\Core\Security\EvidenceTrailStore;
use UplinkPortal\Core\Security\SecurityAlertMonitor;
use UplinkPortal\Core\Support\BackendValidationBroker;
use UplinkPortal\Core\Support\AutomationRecipeCatalog;
use UplinkPortal\Core\Support\ClientAccessStore;
use UplinkPortal\Core\Support\ClientProfileStore;
use UplinkPortal\Core\Support\ClientDocumentsStore;
use UplinkPortal\Core\Support\CompliancePolicyProfiles;
use UplinkPortal\Core\Support\EntitlementService;
use UplinkPortal\Core\Support\IntegrationModelRegistry;
use UplinkPortal\Core\Support\IntegrationPolicyLinter;
use UplinkPortal\Core\Support\IntegrationProfileTestHarness;
use UplinkPortal\Core\Support\Options;
use UplinkPortal\Core\Support\PortalCustomizationStore;
use UplinkPortal\Core\Support\PurchaseRequestStore;
use UplinkPortal\Core\Support\TenantBoundaryScope;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin settings and fields.
 */
class SettingsPage {
	/**
	 * Top-level menu slug.
	 *
	 * @var string
	 */
	private const MENU_SLUG = 'uplinkportal';

	/**
	 * Configuration submenu slug.
	 *
	 * @var string
	 */
	private const SETTINGS_SLUG = 'uplinkportal-settings';

	/**
	 * Settings API page slug for Security tab internals.
	 *
	 * @var string
	 */
	private const SETTINGS_API_BACKEND_PAGE = 'uplinkportal-settings-tab-backend-database';

	/**
	 * Settings API page slug for Modules tab internals.
	 *
	 * @var string
	 */
	private const SETTINGS_API_MODULES_PAGE = 'uplinkportal-settings-tab-modules';

	/**
	 * Tools and diagnostics page slug.
	 *
	 * @var string
	 */
	private const TOOLS_DIAGNOSTICS_SLUG = 'uplinkportal-tools-diagnostics';

	/**
	 * Onboarding/customization defaults option key.
	 *
	 * @var string
	 */
	private const ONBOARDING_OPTION = 'uplinkportal_onboarding';

	/**
	 * Agreements and records page slug.
	 *
	 * @var string
	 */
	private const AGREEMENTS_RECORDS_SLUG = 'uplinkportal-agreements-records';

	/**
	 * Service catalog page slug.
	 *
	 * @var string
	 */
	private const SERVICE_CATALOG_SLUG = 'uplinkportal-service-catalog';

	/**
	 * Plan templates option key.
	 *
	 * @var string
	 */
	private const PLAN_TEMPLATES_OPTION = 'uplinkportal_plan_templates';

	/**
	 * Integration status metadata option key.
	 *
	 * @var string
	 */
	private const INTEGRATION_STATUS_OPTION = 'uplinkportal_integration_status';

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'uplinkportal/v1';

	/**
	 * Settings option group key.
	 *
	 * @var string
	 */
	private const SETTINGS_OPTION_GROUP = 'uplinkportal_settings';

	/**
	 * MSP management capability.
	 *
	 * @var string
	 */
	private const CAPABILITY_MSP_MANAGE = 'msp_manage';

	/**
	 * MSP portal preview capability.
	 *
	 * @var string
	 */
	private const CAPABILITY_MSP_PREVIEW_PORTAL = 'msp_preview_client_portal';

	/**
	 * Canonical operating day key order.
	 *
	 * @var array<int,string>
	 */
	private const OPERATING_DAY_KEYS = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Licensing client.
	 *
	 * @var LicensingClient
	 */
	private LicensingClient $licensing_client;

	/**
	 * Client documents store.
	 *
	 * @var ClientDocumentsStore
	 */
	private ClientDocumentsStore $client_documents;

	/**
	 * Client profile store.
	 *
	 * @var ClientProfileStore
	 */
	private ClientProfileStore $client_profiles;

	/**
	 * Client access custom-table store.
	 *
	 * @var ClientAccessStore
	 */
	private ClientAccessStore $client_access_store;

	/**
	 * Portal customization private storage.
	 *
	 * @var PortalCustomizationStore
	 */
	private PortalCustomizationStore $portal_customization_store;

	/**
	 * Module menu registrar.
	 *
	 * @var ModuleMenuRegistrar
	 */
	private ModuleMenuRegistrar $module_menu_registrar;

	/**
	 * Client portfolio UI renderer.
	 *
	 * @var ClientPortfolioUi
	 */
	private ClientPortfolioUi $client_portfolio_ui;

	/**
	 * Operations Dashboard UI renderer.
	 *
	 * @var OperationsDashboardUi
	 */
	private OperationsDashboardUi $operations_dashboard_ui;

	/**
	 * Agreements & Records UI renderer.
	 *
	 * @var AgreementsRecordsUi
	 */
	private AgreementsRecordsUi $agreements_records_ui;

	/**
	 * Service Catalog UI renderer.
	 *
	 * @var ServiceCatalogUi
	 */
	private ServiceCatalogUi $service_catalog_ui;

	/**
	 * Configuration > Client Access UI renderer.
	 *
	 * @var ConfigClientsUi
	 */
	private ConfigClientsUi $config_clients_ui;

	/**
	 * Configuration > Modules UI renderer.
	 *
	 * @var ConfigModulesUi
	 */
	private ConfigModulesUi $config_modules_ui;

	/**
	 * Configuration > Security UI renderer.
	 *
	 * @var ConfigSecurityUi
	 */
	private ConfigSecurityUi $config_security_ui;

	/**
	 * Configuration > Company Info UI renderer.
	 *
	 * @var ConfigCompanyInfoUi
	 */
	private ConfigCompanyInfoUi $config_company_info_ui;

	/**
	 * Configuration > Portal Preview UI renderer.
	 *
	 * @var ConfigPortalPreviewUi
	 */
	private ConfigPortalPreviewUi $config_portal_preview_ui;

	/**
	 * Purchase request storage.
	 *
	 * @var PurchaseRequestStore
	 */
	private PurchaseRequestStore $purchase_request_store;

	/**
	 * Provider catalog service.
	 *
	 * @var ProviderCatalogService
	 */
	private ProviderCatalogService $provider_catalog_service;

	/**
	 * Entitlement service.
	 *
	 * @var EntitlementService
	 */
	private EntitlementService $entitlement_service;

	/**
	 * Cached policy lint findings.
	 *
	 * @var array<int,mixed>|null
	 */
	private ?array $policy_lint_findings_cache = null;

	/**
	 * Cached plan templates.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private ?array $plan_templates_cache = null;

	/**
	 * Cached client workspace users.
	 *
	 * @var array<int,\WP_User>|null
	 */
	private ?array $client_workspace_users_cache = null;

	/**
	 * Cached entitlement catalog rows.
	 *
	 * @var array<int,array<string,string>>|null
	 */
	private ?array $entitlement_catalog_rows_cache = null;

	/**
	 * Cached SMTP plugin detection result.
	 *
	 * @var bool|null
	 */
	private ?bool $smtp_plugin_detected_cache = null;

	/**
	 * Constructor.
	 *
	 * @param Options                  $options Option helper.
	 * @param ModuleManager            $module_manager Module manager.
	 * @param LicensingClient          $licensing_client Licensing client.
	 * @param ClientDocumentsStore     $client_documents Client documents store.
	 * @param ClientProfileStore       $client_profiles Client profile store.
	 * @param ClientAccessStore        $client_access_store Client access store.
	 * @param PortalCustomizationStore $portal_customization_store Portal customization store.
	 * @param PurchaseRequestStore     $purchase_request_store Purchase request store.
	 * @param ModuleMenuRegistrar      $module_menu_registrar Module menu registrar.
	 * @param ProviderCatalogService   $provider_catalog_service Provider catalog service.
	 * @param OperationsDashboardUi    $operations_dashboard_ui Operations Dashboard renderer.
	 * @param AgreementsRecordsUi      $agreements_records_ui Agreements & Records renderer.
	 * @param ServiceCatalogUi         $service_catalog_ui Service Catalog renderer.
	 * @param ConfigClientsUi          $config_clients_ui Config > Client Access renderer.
	 * @param ConfigModulesUi          $config_modules_ui Config > Modules renderer.
	 * @param ConfigSecurityUi         $config_security_ui Config > Security renderer.
	 * @param ConfigCompanyInfoUi      $config_company_info_ui Config > Company Info renderer.
	 * @param ConfigPortalPreviewUi    $config_portal_preview_ui Config > Portal Preview renderer.
	 */
	public function __construct( Options $options, ModuleManager $module_manager, LicensingClient $licensing_client, ClientDocumentsStore $client_documents, ClientProfileStore $client_profiles, ClientAccessStore $client_access_store, PortalCustomizationStore $portal_customization_store, PurchaseRequestStore $purchase_request_store, ModuleMenuRegistrar $module_menu_registrar, ProviderCatalogService $provider_catalog_service, OperationsDashboardUi $operations_dashboard_ui, AgreementsRecordsUi $agreements_records_ui, ServiceCatalogUi $service_catalog_ui, ConfigClientsUi $config_clients_ui, ConfigModulesUi $config_modules_ui, ConfigSecurityUi $config_security_ui, ConfigCompanyInfoUi $config_company_info_ui, ConfigPortalPreviewUi $config_portal_preview_ui ) {
		$this->options                    = $options;
		$this->module_manager             = $module_manager;
		$this->licensing_client           = $licensing_client;
		$this->client_documents           = $client_documents;
		$this->client_profiles            = $client_profiles;
		$this->client_access_store        = $client_access_store;
		$this->portal_customization_store = $portal_customization_store;
		$this->purchase_request_store     = $purchase_request_store;
		$this->module_menu_registrar      = $module_menu_registrar;
		$this->provider_catalog_service   = $provider_catalog_service;
		$this->operations_dashboard_ui    = $operations_dashboard_ui;
		$this->agreements_records_ui      = $agreements_records_ui;
		$this->service_catalog_ui         = $service_catalog_ui;
		$this->config_clients_ui          = $config_clients_ui;
		$this->config_modules_ui          = $config_modules_ui;
		$this->config_security_ui         = $config_security_ui;
		$this->config_company_info_ui     = $config_company_info_ui;
		$this->config_portal_preview_ui   = $config_portal_preview_ui;
		$this->entitlement_service        = new EntitlementService();
		$this->client_portfolio_ui        = new ClientPortfolioUi();
	}

	/**
	 * Registers settings menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'UplinkPortal', 'uplinkportal' ),
			__( 'UplinkPortal', 'uplinkportal' ),
			self::CAPABILITY_MSP_MANAGE,
			self::MENU_SLUG,
			array( $this, 'render_operations_dashboard_page' ),
			'dashicons-screenoptions',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'UplinkPortal', 'uplinkportal' ),
			__( 'Operations Dashboard', 'uplinkportal' ),
			'msp_view_operations_dashboard',
			self::MENU_SLUG,
			array( $this, 'render_operations_dashboard_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Agreements & Records', 'uplinkportal' ),
			__( 'Agreements & Records', 'uplinkportal' ),
			self::CAPABILITY_MSP_MANAGE,
			self::AGREEMENTS_RECORDS_SLUG,
			array( $this, 'render_agreements_records_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Service Catalog', 'uplinkportal' ),
			__( 'Service Catalog', 'uplinkportal' ),
			self::CAPABILITY_MSP_MANAGE,
			self::SERVICE_CATALOG_SLUG,
			array( $this, 'render_service_catalog_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'UplinkPortal Configuration', 'uplinkportal' ),
			__( 'Configuration', 'uplinkportal' ),
			self::CAPABILITY_MSP_MANAGE,
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tools & Diagnostics', 'uplinkportal' ),
			__( 'Tools & Diagnostics', 'uplinkportal' ),
			self::CAPABILITY_MSP_MANAGE,
			self::TOOLS_DIAGNOSTICS_SLUG,
			array( $this, 'render_tools_diagnostics_page' )
		);

		$this->module_menu_registrar->register(
			self::MENU_SLUG,
			$this->get_configuration_tab_url( 'modules' ),
			array(
				self::MENU_SLUG,
				self::SETTINGS_SLUG,
				self::AGREEMENTS_RECORDS_SLUG,
				self::SERVICE_CATALOG_SLUG,
				self::TOOLS_DIAGNOSTICS_SLUG,
			),
			function ( string $module_key ): bool {
				return $this->is_service_module_enabled( $module_key );
			}
		);
	}

	/**
	 * Registers plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_OPTION_GROUP,
			$this->options->key(),
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->options, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		foreach ( $this->get_settings_api_tab_definitions() as $tab_definition ) {
			$section_callback = isset( $tab_definition['section_callback'] ) ? (string) $tab_definition['section_callback'] : '';
			$page_slug        = isset( $tab_definition['page_slug'] ) ? (string) $tab_definition['page_slug'] : '';
			$section_id       = isset( $tab_definition['section_id'] ) ? (string) $tab_definition['section_id'] : '';
			$section_title    = isset( $tab_definition['section_title'] ) ? (string) $tab_definition['section_title'] : '';
			$fields           = isset( $tab_definition['fields'] ) && is_array( $tab_definition['fields'] ) ? $tab_definition['fields'] : array();

			if ( '' === $section_callback || ! is_callable( array( $this, $section_callback ) ) || '' === $page_slug || '' === $section_id ) {
				continue;
			}

			add_settings_section(
				$section_id,
				$section_title,
				array( $this, $section_callback ),
				$page_slug
			);

			foreach ( $fields as $field_definition ) {
				if ( ! is_array( $field_definition ) ) {
					continue;
				}

				$field_callback = isset( $field_definition['callback'] ) ? (string) $field_definition['callback'] : '';
				$field_id       = isset( $field_definition['id'] ) ? (string) $field_definition['id'] : '';
				$field_title    = isset( $field_definition['title'] ) ? (string) $field_definition['title'] : '';

				if ( '' === $field_callback || ! is_callable( array( $this, $field_callback ) ) || '' === $field_id ) {
					continue;
				}

				add_settings_field(
					$field_id,
					$field_title,
					array( $this, $field_callback ),
					$page_slug,
					$section_id
				);
			}
		}
	}

	/**
	 * Registers admin REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/providers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_provider_catalog_rest' ),
				'permission_callback' => array( $this, 'can_access_provider_catalog_rest' ),
				'args'                => array(
					'module'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'required'          => true,
					),
					'client_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'required'          => false,
					),
					'profile'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'required'          => false,
					),
				),
			)
		);
	}

	/**
	 * Provider catalog REST permission callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_access_provider_catalog_rest( WP_REST_Request $request ) {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			return new WP_Error( 'uplinkportal_forbidden', __( 'You do not have permission to access provider catalogs.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		if ( ! $this->is_rest_nonce_valid( $request ) ) {
			return new WP_Error( 'uplinkportal_invalid_nonce', __( 'Invalid or missing REST nonce.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Returns provider catalog for one module.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_provider_catalog_rest( WP_REST_Request $request ): WP_REST_Response {
		$module_slug = sanitize_key( (string) $request->get_param( 'module' ) );
		$client_id   = absint( (int) $request->get_param( 'client_id' ) );
		$profile     = sanitize_key( (string) $request->get_param( 'profile' ) );
		$providers   = $client_id > 0 && $this->is_client_workspace_user( $client_id )
			? $this->provider_catalog_service->get_providers_for_client( $client_id, $module_slug )
			: $this->provider_catalog_service->get_providers_for_module( $module_slug );
		$providers   = $this->filter_provider_catalog_by_profile( $providers, $profile );

		$response = new WP_REST_Response(
			array(
				'module'    => $module_slug,
				'client_id' => $client_id,
				'profile'   => $profile,
				'providers' => $providers,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );

		return $response;
	}

	/**
	 * Registers contextual help tabs for plugin admin screens.
	 *
	 * @param \WP_Screen $screen Current admin screen.
	 * @return void
	 */
	public function register_contextual_help_tabs( \WP_Screen $screen ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen context.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';

		if ( self::MENU_SLUG !== $current_page && self::SETTINGS_SLUG !== $current_page ) {
			return;
		}

		if ( self::MENU_SLUG === $current_page ) {
			$screen->add_help_tab(
				array(
					'id'      => 'uplinkportal-operations-overview',
					'title'   => __( 'Overview', 'uplinkportal' ),
					'content' => $this->get_operations_help_overview_html(),
				)
			);
			$screen->add_help_tab(
				array(
					'id'      => 'uplinkportal-operations-workflow',
					'title'   => __( 'Workflow', 'uplinkportal' ),
					'content' => $this->get_operations_help_workflow_html(),
				)
			);
		}

		if ( self::SETTINGS_SLUG === $current_page ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab context.
			$current_tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'clients';
			$tabs        = $this->get_configuration_tabs();
			$tab_label   = isset( $tabs[ $current_tab ]['label'] ) ? (string) $tabs[ $current_tab ]['label'] : __( 'Client Access', 'uplinkportal' );

			$screen->add_help_tab(
				array(
					'id'      => 'uplinkportal-configuration-overview',
					'title'   => __( 'Overview', 'uplinkportal' ),
					'content' => $this->get_configuration_help_overview_html(),
				)
			);
			$screen->add_help_tab(
				array(
					'id'      => 'uplinkportal-configuration-current-tab',
					'title'   => __( 'Current Tab', 'uplinkportal' ),
					'content' => $this->get_configuration_help_current_tab_html( $tab_label ),
				)
			);
		}

		$screen->set_help_sidebar(
			wp_kses_post(
				'<p><strong>' . esc_html__( 'Quick Links', 'uplinkportal' ) . '</strong></p>' .
				'<p><a href="' . esc_url( $this->get_configuration_tab_url( 'modules' ) ) . '">' . esc_html__( 'Open Configuration > Modules', 'uplinkportal' ) . '</a></p>' .
				'<p><a href="' . esc_url( add_query_arg( array( 'page' => self::TOOLS_DIAGNOSTICS_SLUG ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open Tools & Diagnostics', 'uplinkportal' ) . '</a></p>'
			)
		);
	}

	/**
	 * Returns operations help overview HTML.
	 *
	 * @return string
	 */
	private function get_operations_help_overview_html(): string {
		return wp_kses_post(
			'<p>' . esc_html__( 'Operations Dashboard summarizes current module status, fulfillment queue volume, and integration readiness.', 'uplinkportal' ) . '</p>' .
			'<ul>' .
				'<li>' . esc_html__( 'Use module and plan metrics to confirm platform health after changes.', 'uplinkportal' ) . '</li>' .
				'<li>' . esc_html__( 'Review fulfillment queue counts after client requests are submitted.', 'uplinkportal' ) . '</li>' .
				'<li>' . esc_html__( 'Open related sections from the side menu for deeper configuration.', 'uplinkportal' ) . '</li>' .
			'</ul>'
		);
	}

	/**
	 * Returns operations help workflow HTML.
	 *
	 * @return string
	 */
	private function get_operations_help_workflow_html(): string {
		return wp_kses_post(
			'<p>' . esc_html__( 'Recommended order after a deployment:', 'uplinkportal' ) . '</p>' .
			'<ol>' .
				'<li>' . esc_html__( 'Check Operations Dashboard metrics for expected module state.', 'uplinkportal' ) . '</li>' .
				'<li>' . esc_html__( 'Validate integrations in Tools & Diagnostics.', 'uplinkportal' ) . '</li>' .
				'<li>' . esc_html__( 'Review Agreements & Records and Service Catalog for user-facing updates.', 'uplinkportal' ) . '</li>' .
			'</ol>'
		);
	}

	/**
	 * Returns configuration help overview HTML.
	 *
	 * @return string
	 */
	private function get_configuration_help_overview_html(): string {
		return wp_kses_post(
			'<p>' . esc_html__( 'Configuration is organized into task-focused tabs. Save each section after changes to persist updates.', 'uplinkportal' ) . '</p>' .
			'<ul>' .
				'<li>' . esc_html__( 'Client Access: role and client mapping behavior.', 'uplinkportal' ) . '</li>' .
				'<li>' . esc_html__( 'Modules: integrations, backend settings, and recipes.', 'uplinkportal' ) . '</li>' .
				'<li>' . esc_html__( 'Security: policy bundle, retention, and monitoring posture.', 'uplinkportal' ) . '</li>' .
				'<li>' . esc_html__( 'Company Info / Portal Preview: branding and portal presentation defaults.', 'uplinkportal' ) . '</li>' .
			'</ul>'
		);
	}

	/**
	 * Returns configuration current-tab help HTML.
	 *
	 * @param string $tab_label Current tab label.
	 * @return string
	 */
	private function get_configuration_help_current_tab_html( string $tab_label ): string {
		return wp_kses_post(
			'<p>' .
				sprintf(
					/* translators: %s current configuration tab label. */
					esc_html__( 'You are editing: %s', 'uplinkportal' ),
					esc_html( $tab_label )
				) .
			'</p>' .
			'<p>' . esc_html__( 'Use Save Changes at the bottom of this tab before navigating away.', 'uplinkportal' ) . '</p>'
		);
	}

	/**
	 * Builds diagnostics summary cards from integration status rows.
	 *
	 * @param array<int,array<string,mixed>> $status_rows Status board rows.
	 * @return array<int,array<string,string>>
	 */
	private function get_tools_status_snapshot( array $status_rows ): array {
		$connected_count = 0;
		$token_ok_count  = 0;
		$policy_ok_count = 0;

		foreach ( $status_rows as $row ) {
			if ( 'enabled' === $this->get_status_cell_state( $row, 'connected' ) ) {
				++$connected_count;
			}

			if ( 'enabled' === $this->get_status_cell_state( $row, 'token_health' ) ) {
				++$token_ok_count;
			}

			if ( 'enabled' === $this->get_status_cell_state( $row, 'policy_status' ) ) {
				++$policy_ok_count;
			}
		}

		$total = count( $status_rows );
		return array(
			array(
				'label' => __( 'Connected Models', 'uplinkportal' ),
				'value' => sprintf(
					/* translators: 1: connected model count, 2: total model count. */
					__( '%1$d of %2$d', 'uplinkportal' ),
					$connected_count,
					$total
				),
				'help'  => __( 'Models with required connection configuration in place.', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Healthy Tokens', 'uplinkportal' ),
				'value' => sprintf(
					/* translators: 1: healthy token count, 2: total model count. */
					__( '%1$d of %2$d', 'uplinkportal' ),
					$token_ok_count,
					$total
				),
				'help'  => __( 'Integrations that currently pass token/credential readiness checks.', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Policy Ready', 'uplinkportal' ),
				'value' => sprintf(
					/* translators: 1: policy-ready model count, 2: total model count. */
					__( '%1$d of %2$d', 'uplinkportal' ),
					$policy_ok_count,
					$total
				),
				'help'  => __( 'Models with policy posture marked ready for operational use.', 'uplinkportal' ),
			),
		);
	}

	/**
	 * Returns normalized state for one integration status cell.
	 *
	 * @param array<string,mixed> $row  One status row.
	 * @param string              $cell Cell key.
	 * @return string
	 */
	private function get_status_cell_state( array $row, string $cell ): string {
		$state = isset( $row[ $cell ]['state'] ) ? sanitize_key( (string) $row[ $cell ]['state'] ) : 'warning';
		if ( ! in_array( $state, array( 'enabled', 'disabled', 'warning' ), true ) ) {
			return 'warning';
		}

		return $state;
	}

	/**
	 * Renders operations dashboard page.
	 *
	 * @return void
	 */
	public function render_operations_dashboard_page(): void {
		if ( ! current_user_can( 'msp_view_operations_dashboard' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'uplinkportal' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view tab from query arg.
		$requested_view = isset( $_GET['view'] ) ? sanitize_key( (string) $_GET['view'] ) : null;
		?>
		<div class="wrap uplinkportal-admin msp-admin">
			<?php
			$this->operations_dashboard_ui->render(
				wp_get_current_user(),
				$requested_view
			);
			?>
		</div>
		<?php
	}

	/**
	 * Renders projects and service delivery tracking panel.
	 *
	 * @param array<int,array<string,mixed>> $portfolio_rows Portfolio rows.
	 * @return void
	 */
	private function render_operations_delivery_tracking_panel( array $portfolio_rows ): void {
		$this->client_portfolio_ui->render_operations_delivery_tracking_panel( $portfolio_rows, $this->get_client_portfolio_page_url() );
	}

	/**
	 * Renders settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'uplinkportal' ) );
		}

		$active_tab           = $this->get_active_configuration_tab();
		$tabs                 = $this->get_configuration_tabs();
		$active_tab_data      = isset( $tabs[ $active_tab ] ) ? $tabs[ $active_tab ] : array(
			'label'       => __( 'Configuration', 'uplinkportal' ),
			'description' => __( 'Manage plugin settings.', 'uplinkportal' ),
			'capability'  => self::CAPABILITY_MSP_MANAGE,
		);
		$settings_tab_map     = $this->get_settings_api_tab_definitions();
		$active_settings_tab  = isset( $settings_tab_map[ $active_tab ] ) && is_array( $settings_tab_map[ $active_tab ] ) ? $settings_tab_map[ $active_tab ] : array();
		$active_settings_page = isset( $active_settings_tab['page_slug'] ) ? (string) $active_settings_tab['page_slug'] : '';
		$submit_label         = isset( $active_settings_tab['submit_label'] ) ? (string) $active_settings_tab['submit_label'] : __( 'Save Settings', 'uplinkportal' );
		?>
		<div class="wrap uplinkportal-admin msp-admin uplinkportal-settings-screen">
			<section class="uplinkportal-admin-hero uplinkportal-settings-hero">
				<div>
					<h1><?php echo esc_html__( 'Configuration Center', 'uplinkportal' ); ?></h1>
					<p><?php echo esc_html__( 'Choose an area, review guided summaries, and open focused configuration windows only when you need advanced changes.', 'uplinkportal' ); ?></p>
				</div>
				<span class="uplinkportal-admin-badge"><?php echo esc_html__( 'Simplified Setup', 'uplinkportal' ); ?></span>
			</section>
			<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Configuration tabs', 'uplinkportal' ); ?>">
				<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
					<?php if ( ! current_user_can( $tab_data['capability'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<a href="<?php echo esc_url( $this->get_configuration_tab_url( $tab_key ) ); ?>" class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $this->get_configuration_tab_icon( $tab_key ) ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $tab_data['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="uplinkportal-tab-panel uplinkportal-admin-card">
				<header class="uplinkportal-settings-main-header">
					<h2><?php echo esc_html( isset( $active_tab_data['label'] ) ? (string) $active_tab_data['label'] : __( 'Configuration', 'uplinkportal' ) ); ?></h2>
					<p><?php echo esc_html( isset( $active_tab_data['description'] ) ? (string) $active_tab_data['description'] : __( 'Manage plugin settings.', 'uplinkportal' ) ); ?></p>
				</header>
				<?php $this->render_settings_validation_notices( $active_tab ); ?>

					<?php if ( '' !== $active_settings_page ) : ?>
						<?php $security_expanded = ! ( 'backend_database' === $active_tab && ! $this->is_security_compliance_module_active() ); ?>
						<?php if ( 'backend_database' === $active_tab ) : ?>
							<?php $this->render_roles_tab(); ?>
						<?php endif; ?>
						<?php if ( $security_expanded ) : ?>
							<form method="post" action="options.php" class="uplinkportal-settings-form uplinkportal-settings-form--<?php echo esc_attr( $active_tab ); ?>">
								<?php settings_fields( self::SETTINGS_OPTION_GROUP ); ?>
								<?php if ( 'modules' === $active_tab ) : ?>
									<input type="hidden" name="<?php echo esc_attr( $this->options->key() ); ?>[service_modules_submitted]" value="1" />
									<input type="hidden" name="<?php echo esc_attr( $this->options->key() ); ?>[entitlements_context_client_id]" value="<?php echo esc_attr( (string) $this->modules_entitlement_context_client_id() ); ?>" />
								<?php endif; ?>
								<?php do_settings_sections( $active_settings_page ); ?>
								<?php submit_button( $submit_label ); ?>
							</form>
						<?php endif; ?>
					<?php elseif ( 'customization' === $active_tab ) : ?>
						<?php $this->render_customization_tab(); ?>
				<?php elseif ( 'clients' === $active_tab ) : ?>
					<?php $this->render_clients_tab(); ?>
				<?php elseif ( 'portal_preview' === $active_tab ) : ?>
					<?php $this->render_client_portal_preview_tab(); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders inline validation notices for configuration settings.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	private function render_settings_validation_notices( string $active_tab ): void {
		$active_tab = sanitize_key( $active_tab );
		if ( '' === $active_tab ) {
			return;
		}

		$findings = $this->get_policy_lint_findings();
		if ( empty( $findings ) ) {
			return;
		}

		$grouped = array(
			'error'   => array(),
			'warning' => array(),
		);

		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}

			$severity = isset( $finding['severity'] ) ? sanitize_key( (string) $finding['severity'] ) : 'warning';
			if ( ! isset( $grouped[ $severity ] ) ) {
				continue;
			}

			$summary     = isset( $finding['summary'] ) ? sanitize_text_field( (string) $finding['summary'] ) : '';
			$remediation = isset( $finding['remediation'] ) ? sanitize_text_field( (string) $finding['remediation'] ) : '';
			if ( '' === $summary ) {
				continue;
			}

			$message = $summary;
			if ( '' !== $remediation ) {
				$message .= ' ' . $remediation;
			}

			$grouped[ $severity ][] = $message;
		}

		foreach ( array( 'error', 'warning' ) as $severity ) {
			if ( empty( $grouped[ $severity ] ) ) {
				continue;
			}

			$notice_class = 'error' === $severity ? 'notice-error' : 'notice-warning';
			$title        = 'error' === $severity ? __( 'Configuration validation errors detected', 'uplinkportal' ) : __( 'Configuration validation warnings detected', 'uplinkportal' );
			?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> inline">
				<p><strong><?php echo esc_html( $title ); ?></strong></p>
				<ul>
					<?php foreach ( array_slice( $grouped[ $severity ], 0, 3 ) as $message ) : ?>
						<li><?php echo esc_html( (string) $message ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}
	}

	/**
	 * Renders roles configuration tab content.
	 *
	 * @return void
	 */
	private function render_roles_tab(): void {
		$strict_nonce        = $this->options->get_strict_rest_nonce();
		$show_audit          = $this->options->get_show_security_audit_log();
		$mask_audit          = $this->options->should_mask_audit_logs();
		$trail_enabled       = $this->options->get_evidence_trail_enabled();
		$storage_metrics     = $this->client_documents->get_storage_usage_metrics();
		$private_doc_storage = ! empty( $storage_metrics['private_storage'] );
		$salt_is_placeholder = ! defined( 'UPLINKPORTAL_CRYPTO_SALT' ) || 'change-me-in-production' === UPLINKPORTAL_CRYPTO_SALT;
		$security_module     = $this->is_security_compliance_module_active();
		$alerts              = $security_module ? SecurityAlertMonitor::get_recent_alerts( 5 ) : array();
		$evidence            = $security_module ? EvidenceTrailStore::get_recent_entries( 5 ) : array();
		$trail_state         = $security_module ? EvidenceTrailStore::verify_chain() : array();
		$audit_entries       = $security_module ? CapabilityManager::get_recent_audit_log( 10 ) : array();
		$central_audit       = $security_module ? CentralAuditJournal::get_recent_entries( 10 ) : array();
		$resync_url          = $this->get_configuration_tab_url( 'backend_database' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status from query args.
		$resync_status = isset( $_GET['uplinkportal_role_sync'] ) ? sanitize_key( (string) $_GET['uplinkportal_role_sync'] ) : '';

		$this->config_security_ui->render(
			$strict_nonce,
			$show_audit,
			$mask_audit,
			$trail_enabled,
			$private_doc_storage,
			$salt_is_placeholder,
			$security_module,
			$alerts,
			$evidence,
			$trail_state,
			$audit_entries,
			$central_audit,
			$resync_url,
			$resync_status,
			$this->get_configuration_tab_url( 'clients' ),
			$this->get_configuration_tab_url( 'modules' )
		);
	}
	/**
	 * Renders integration readiness and status panels for modules tab.
	 *
	 * @return void
	 */
	private function render_security_baseline_panel(): void {
		$strict_nonce       = $this->options->get_strict_rest_nonce();
		$show_audit_log     = $this->options->get_show_security_audit_log();
		$storage_metrics    = $this->client_documents->get_storage_usage_metrics();
		$private_doc_status = ! empty( $storage_metrics['private_storage'] );
		/* translators: %s is enabled/disabled status for admin nonce checks. */
		$nonce_enforcement_label = sprintf( __( 'Admin nonce enforcement: %s', 'uplinkportal' ), $strict_nonce ? __( 'Enabled', 'uplinkportal' ) : __( 'Disabled', 'uplinkportal' ) );
		/* translators: %s is enabled/hidden status for audit visibility. */
		$audit_visibility_label = sprintf( __( 'Audit log visibility: %s', 'uplinkportal' ), $show_audit_log ? __( 'Enabled', 'uplinkportal' ) : __( 'Hidden', 'uplinkportal' ) );
		/* translators: %s is the document storage security mode label. */
		$document_storage_label = sprintf( __( 'Document storage path: %s', 'uplinkportal' ), $private_doc_status ? __( 'Private directory', 'uplinkportal' ) : __( 'Fallback hardened path', 'uplinkportal' ) );
		?>
		<div class="uplinkportal-admin-card uplinkportal-security-baseline-card">
			<h3><?php echo esc_html__( 'Core Security Posture', 'uplinkportal' ); ?></h3>
			<ul>
				<li><?php echo esc_html( $nonce_enforcement_label ); ?></li>
				<li><?php echo esc_html( $audit_visibility_label ); ?></li>
				<li><?php echo esc_html( $document_storage_label ); ?></li>
				<li><?php echo esc_html__( 'Secrets are stored in dedicated secret option keys and masked in admin views.', 'uplinkportal' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Renders guided role setup panel for non-technical owners.
	 *
	 * @return void
	 */
	private function render_guided_role_setup_panel(): void {
		$client_access_url = $this->get_configuration_tab_url( 'clients' );
		$modules_url       = $this->get_configuration_tab_url( 'modules' );
		$security_url      = $this->get_configuration_tab_url( 'backend_database' );
		?>
		<div class="uplinkportal-admin-card">
			<h3><?php echo esc_html__( 'Guided Role Setup (Recommended)', 'uplinkportal' ); ?></h3>
			<p><?php echo esc_html__( 'Use this quick setup if you are assigning access for the first time. Choose the closest role for each person, then refine later if needed.', 'uplinkportal' ); ?></p>
			<ol>
				<li><?php echo esc_html__( 'Decide who owns final approvals and billing decisions.', 'uplinkportal' ); ?></li>
					<li><?php echo esc_html__( 'Assign MSP daily operators (project, support, finance) to MSP manager-level roles.', 'uplinkportal' ); ?></li>
				<li><?php echo esc_html__( 'Assign clients to the least-privileged role that still lets them do their job.', 'uplinkportal' ); ?></li>
				<li><?php echo esc_html__( 'Run a quick access test with one account from each role.', 'uplinkportal' ); ?></li>
			</ol>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'If this person is...', 'uplinkportal' ); ?></th>
						<th><?php echo esc_html__( 'Start with this role', 'uplinkportal' ); ?></th>
						<th><?php echo esc_html__( 'Plain-language purpose', 'uplinkportal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html__( 'MSP business owner or final approver', 'uplinkportal' ); ?></td>
						<td><code>uplinkportal_msp_owner</code></td>
						<td><?php echo esc_html__( 'Full control over operations and configuration decisions.', 'uplinkportal' ); ?></td>
					</tr>
					<tr>
						<td><?php echo esc_html__( 'MSP team lead managing day-to-day delivery', 'uplinkportal' ); ?></td>
						<td><code>uplinkportal_msp_manager</code></td>
						<td><?php echo esc_html__( 'Can run operations, review queues, and manage common workflows.', 'uplinkportal' ); ?></td>
					</tr>
					<tr>
						<td><?php echo esc_html__( 'MSP finance or billing coordinator', 'uplinkportal' ); ?></td>
						<td><code>uplinkportal_msp_finance</code></td>
						<td><?php echo esc_html__( 'Focuses on invoices, billing records, and finance-related updates.', 'uplinkportal' ); ?></td>
					</tr>
					<tr>
						<td><?php echo esc_html__( 'MSP technical implementer/support specialist', 'uplinkportal' ); ?></td>
						<td><code>uplinkportal_msp_tech</code></td>
						<td><?php echo esc_html__( 'Handles technical delivery and troubleshooting tasks.', 'uplinkportal' ); ?></td>
					</tr>
					<tr>
						<td><?php echo esc_html__( 'Client contact with basic portal needs', 'uplinkportal' ); ?></td>
						<td><code>uplinkportal_client_user</code></td>
						<td><?php echo esc_html__( 'Can access assigned client workspace features with limited privileges.', 'uplinkportal' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: client access URL, 2: modules URL, 3: security URL. */
						__( 'Next steps: <a href="%1$s">Client Access</a> for assignments, <a href="%2$s">Modules</a> for feature visibility, then <a href="%3$s">Security</a> to confirm baseline posture.', 'uplinkportal' ),
						esc_url( $client_access_url ),
						esc_url( $modules_url ),
						esc_url( $security_url )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns whether Security & Compliance expansion module is active.
	 *
	 * @return bool
	 */
	private function is_security_compliance_module_active(): bool {
		return (bool) apply_filters( 'uplinkportal_feature_enabled', true, 'security_compliance' );
	}

	/**
	 * Renders tools and diagnostics screen.
	 *
	 * @return void
	 */
	public function render_tools_diagnostics_page(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'uplinkportal' ) );
		}
		$status_rows     = $this->get_integration_status_rows();
		$status_snapshot = $this->get_tools_status_snapshot( $status_rows );
		?>
		<div class="wrap uplinkportal-admin msp-admin uplinkportal-tools-screen">
			<section class="uplinkportal-admin-hero">
				<div>
					<h1><?php echo esc_html__( 'Tools & Diagnostics', 'uplinkportal' ); ?></h1>
					<p><?php echo esc_html__( 'Use this page for integration health checks and troubleshooting output. Day-to-day module configuration stays in Configuration > Modules.', 'uplinkportal' ); ?></p>
				</div>
				<span class="uplinkportal-admin-badge"><?php echo esc_html__( 'Advanced', 'uplinkportal' ); ?></span>
			</section>
			<section class="uplinkportal-stat-grid uplinkportal-stat-grid--tools">
				<?php foreach ( $status_snapshot as $metric ) : ?>
					<article class="uplinkportal-stat-card">
						<p class="uplinkportal-stat-label"><?php echo esc_html( (string) $metric['label'] ); ?></p>
						<p class="uplinkportal-stat-value"><?php echo esc_html( (string) $metric['value'] ); ?></p>
						<p class="uplinkportal-stat-help"><?php echo esc_html( (string) $metric['help'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</section>

				<div class="uplinkportal-admin-card">
					<h2><?php echo esc_html__( 'Integration Status Board', 'uplinkportal' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Simple at-a-glance readiness view for each integration area.', 'uplinkportal' ); ?></p>
					<?php $this->render_integration_status_board( $status_rows ); ?>
				</div>

				<div class="uplinkportal-admin-card">
					<h2><?php echo esc_html__( 'Backend Connectivity Effective Values', 'uplinkportal' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Shows active backend connectivity values and whether each value is coming from environment variables or saved WordPress settings.', 'uplinkportal' ); ?></p>
					<?php $this->render_backend_connectivity_diagnostics_panel(); ?>
				</div>

			<div class="uplinkportal-admin-card">
				<h2><?php echo esc_html__( 'Policy and Connectivity Checks', 'uplinkportal' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'These checks help technical staff confirm secure configuration before enabling production workflows.', 'uplinkportal' ); ?></p>
				<details>
					<summary><?php echo esc_html__( 'Integration Policy Lint', 'uplinkportal' ); ?></summary>
					<div class="uplinkportal-details-panel">
						<?php $this->render_integration_policy_lint_panel(); ?>
					</div>
				</details>
				<details>
					<summary><?php echo esc_html__( 'Integration Profile Test Harness', 'uplinkportal' ); ?></summary>
					<div class="uplinkportal-details-panel">
						<?php $this->render_integration_profile_harness_panel(); ?>
					</div>
				</details>
			</div>

			<div class="uplinkportal-admin-card">
				<h2><?php echo esc_html__( 'Secrets Lease Alerts', 'uplinkportal' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Secret age and rotation alerts are shown here for review. Rotate credentials in Configuration > Modules > Database > Secrets.', 'uplinkportal' ); ?></p>
				<?php $this->render_secret_lease_panel(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders integration status board for core models.
	 *
	 * @param array<int,array<string,mixed>>|null $rows Optional precomputed status rows.
	 * @return void
	 */
	private function render_integration_status_board( ?array $rows = null ): void {
		if ( ! is_array( $rows ) ) {
			$rows = $this->get_integration_status_rows();
		}
		IntegrationSecurityUi::render_integration_status_board( $rows );
	}

	/**
	 * Renders effective backend connectivity diagnostics.
	 *
	 * @return void
	 */
	private function render_backend_connectivity_diagnostics_panel(): void {
		$hosting_mode     = $this->options->get_backend_mode();
		$hosting_mode     = '' !== $hosting_mode ? $hosting_mode : __( '(not set)', 'uplinkportal' );
		$hosting_mode_src = $this->options->get_backend_mode_source();

		$runtime_mode     = $this->options->get_backend_runtime_mode();
		$runtime_mode     = '' !== $runtime_mode ? $runtime_mode : __( '(not set)', 'uplinkportal' );
		$runtime_mode_src = $this->options->get_backend_runtime_mode_source();

		$base_url     = $this->options->get_backend_base_url();
		$base_url     = '' !== $base_url ? $base_url : __( '(not set)', 'uplinkportal' );
		$base_url_src = $this->options->get_backend_base_url_source();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Setting', 'uplinkportal' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Effective Value', 'uplinkportal' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Source', 'uplinkportal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo esc_html__( 'Backend Hosting Mode', 'uplinkportal' ); ?></td>
					<td><code><?php echo esc_html( $hosting_mode ); ?></code></td>
					<td><code><?php echo esc_html( $hosting_mode_src ); ?></code></td>
				</tr>
				<tr>
					<td><?php echo esc_html__( 'Backend Runtime Mode', 'uplinkportal' ); ?></td>
					<td><code><?php echo esc_html( $runtime_mode ); ?></code></td>
					<td><code><?php echo esc_html( $runtime_mode_src ); ?></code></td>
				</tr>
				<tr>
					<td><?php echo esc_html__( 'Backend Base URL', 'uplinkportal' ); ?></td>
					<td><code><?php echo esc_html( $base_url ); ?></code></td>
					<td><code><?php echo esc_html( $base_url_src ); ?></code></td>
				</tr>
			</tbody>
		</table>
		<p class="description"><?php echo esc_html__( 'Backend service token values are intentionally never shown in diagnostics.', 'uplinkportal' ); ?></p>
		<?php
	}

	/**
	 * Renders selected compliance policy bundle panel.
	 *
	 * @return void
	 */
	private function render_policy_bundle_panel(): void {
		$current_bundle = $this->options->get_policy_bundle();
		$bundles        = CompliancePolicyProfiles::bundles();
		$bundle         = isset( $bundles[ $current_bundle ] ) && is_array( $bundles[ $current_bundle ] )
			? $bundles[ $current_bundle ]
			: array();
		$label          = isset( $bundle['label'] ) ? sanitize_text_field( (string) $bundle['label'] ) : $current_bundle;
		$description    = isset( $bundle['description'] ) ? sanitize_text_field( (string) $bundle['description'] ) : '';
		$controls       = isset( $bundle['controls'] ) && is_array( $bundle['controls'] ) ? $bundle['controls'] : array();
		IntegrationSecurityUi::render_policy_bundle_panel( $label, $description, $controls );
	}

	/**
	 * Returns cached policy lint findings, running the linter once per request.
	 *
	 * @return array<int,mixed>
	 */
	private function get_policy_lint_findings(): array {
		if ( null === $this->policy_lint_findings_cache ) {
			$this->policy_lint_findings_cache = ( new IntegrationPolicyLinter( $this->options ) )->lint();
		}

		return $this->policy_lint_findings_cache;
	}

	/**
	 * Renders integration policy lint panel.
	 *
	 * @return void
	 */
	private function render_integration_policy_lint_panel(): void {
		$findings = $this->get_policy_lint_findings();
		$grouped  = array(
			'error'   => array(),
			'warning' => array(),
			'info'    => array(),
		);

		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}

			$severity = isset( $finding['severity'] ) ? sanitize_key( (string) $finding['severity'] ) : 'warning';
			if ( ! isset( $grouped[ $severity ] ) ) {
				$severity = 'warning';
			}

			$grouped[ $severity ][] = $finding;
		}
		IntegrationSecurityUi::render_integration_policy_lint( $grouped );
	}

	/**
	 * Renders integration profile test harness panel.
	 *
	 * @return void
	 */
	private function render_integration_profile_harness_panel(): void {
		$harness = new IntegrationProfileTestHarness( $this->options );
		$rows    = $harness->run();
		IntegrationSecurityUi::render_integration_harness_panel( $rows );
	}

	/**
	 * Returns status rows for integration board.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_integration_status_rows(): array {
		$profiles           = $this->options->get_validation_profiles();
		$integration        = $this->options->get_integration_profiles();
		$strict_nonce       = $this->options->get_strict_rest_nonce();
		$show_audit         = $this->options->get_show_security_audit_log();
		$security_baseline  = $strict_nonce && $show_audit;
		$backend_base_url   = $this->options->get_backend_base_url();
		$licensing_worker   = $this->options->get_licensing_worker_base_url();
		$license_key        = $this->options->get_license_key();
		$license_validation = $this->licensing_client->validate_license( false );
		$storage_metrics    = $this->client_documents->get_storage_usage_metrics();
		$storage_private    = ! empty( $storage_metrics['private_storage'] );
		$key_endpoint       = apply_filters( 'uplinkportal_client_documents_key_endpoint', '/uplinkportal/v1/client-documents/key' );
		$key_endpoint       = is_string( $key_endpoint ) ? trim( $key_endpoint ) : '';
		$last_sync_map      = $this->get_integration_last_sync_map();
		$has_backend_token  = $this->options->has_backend_service_token();
		$has_vault_ref      = $this->options->has_vault_connection_reference();

		$billing_profile   = isset( $profiles['billing'] ) && is_array( $profiles['billing'] ) ? $profiles['billing'] : array();
		$helpdesk_profile  = isset( $profiles['helpdesk'] ) && is_array( $profiles['helpdesk'] ) ? $profiles['helpdesk'] : array();
		$pm_profile        = isset( $profiles['pm'] ) && is_array( $profiles['pm'] ) ? $profiles['pm'] : array();
		$finance_profile   = isset( $integration[ IntegrationModelRegistry::MODEL_FINANCE ] ) && is_array( $integration[ IntegrationModelRegistry::MODEL_FINANCE ] ) ? $integration[ IntegrationModelRegistry::MODEL_FINANCE ] : array();
		$support_profile   = isset( $integration[ IntegrationModelRegistry::MODEL_IT_SUPPORT ] ) && is_array( $integration[ IntegrationModelRegistry::MODEL_IT_SUPPORT ] ) ? $integration[ IntegrationModelRegistry::MODEL_IT_SUPPORT ] : array();
		$projects_profile  = isset( $integration[ IntegrationModelRegistry::MODEL_PROJECTS ] ) && is_array( $integration[ IntegrationModelRegistry::MODEL_PROJECTS ] ) ? $integration[ IntegrationModelRegistry::MODEL_PROJECTS ] : array();
		$licensing_profile = isset( $integration[ IntegrationModelRegistry::MODEL_LICENSING ] ) && is_array( $integration[ IntegrationModelRegistry::MODEL_LICENSING ] ) ? $integration[ IntegrationModelRegistry::MODEL_LICENSING ] : array();
		$documents_profile = isset( $integration[ IntegrationModelRegistry::MODEL_DOCUMENTS ] ) && is_array( $integration[ IntegrationModelRegistry::MODEL_DOCUMENTS ] ) ? $integration[ IntegrationModelRegistry::MODEL_DOCUMENTS ] : array();

		$finance_lifecycle   = $this->normalize_profile_lifecycle_state( isset( $finance_profile['lifecycle_state'] ) ? (string) $finance_profile['lifecycle_state'] : 'draft' );
		$support_lifecycle   = $this->normalize_profile_lifecycle_state( isset( $support_profile['lifecycle_state'] ) ? (string) $support_profile['lifecycle_state'] : 'draft' );
		$projects_lifecycle  = $this->normalize_profile_lifecycle_state( isset( $projects_profile['lifecycle_state'] ) ? (string) $projects_profile['lifecycle_state'] : 'draft' );
		$licensing_lifecycle = $this->normalize_profile_lifecycle_state( isset( $licensing_profile['lifecycle_state'] ) ? (string) $licensing_profile['lifecycle_state'] : 'draft' );
		$documents_lifecycle = $this->normalize_profile_lifecycle_state( isset( $documents_profile['lifecycle_state'] ) ? (string) $documents_profile['lifecycle_state'] : 'draft' );
		$finance_auth_type   = $this->normalize_profile_auth_type_for_ui( isset( $finance_profile['auth_type'] ) ? (string) $finance_profile['auth_type'] : 'api_key' );
		$support_auth_type   = $this->normalize_profile_auth_type_for_ui( isset( $support_profile['auth_type'] ) ? (string) $support_profile['auth_type'] : 'api_key' );
		$projects_auth_type  = $this->normalize_profile_auth_type_for_ui( isset( $projects_profile['auth_type'] ) ? (string) $projects_profile['auth_type'] : 'api_key' );
		$documents_auth_type = $this->normalize_profile_auth_type_for_ui( isset( $documents_profile['auth_type'] ) ? (string) $documents_profile['auth_type'] : 'api_key' );

		$billing_auth       = $this->build_model_auth_status( $finance_auth_type, (string) ( $billing_profile['api_key'] ?? '' ), (string) ( $finance_profile['oauth_connection_ref'] ?? '' ) );
		$helpdesk_auth      = $this->build_model_auth_status( $support_auth_type, (string) ( $helpdesk_profile['api_key'] ?? '' ), (string) ( $support_profile['oauth_connection_ref'] ?? '' ) );
		$projects_auth      = $this->build_model_auth_status( $projects_auth_type, (string) ( $pm_profile['api_key'] ?? '' ), (string) ( $projects_profile['oauth_connection_ref'] ?? '' ) );
		$billing_connected  = '' !== (string) ( $billing_profile['base_url'] ?? '' ) && ! empty( $billing_auth['ready'] );
		$helpdesk_connected = '' !== (string) ( $helpdesk_profile['base_url'] ?? '' ) && ! empty( $helpdesk_auth['ready'] );
		$pm_connected       = '' !== (string) ( $pm_profile['base_url'] ?? '' ) && ! empty( $projects_auth['ready'] );

		$license_connected = '' !== $licensing_worker && '' !== $license_key;
		$license_valid     = ! empty( $license_validation['valid'] );

		$documents_oauth_reference = isset( $documents_profile['oauth_connection_ref'] ) ? (string) $documents_profile['oauth_connection_ref'] : '';
		$documents_api_credential  = ( $has_backend_token || $has_vault_ref ) ? 'delegated_backend_token' : '';
		$documents_auth            = $this->build_model_auth_status( $documents_auth_type, $documents_api_credential, $documents_oauth_reference );
		$documents_auth_ready      = ! empty( $documents_auth['ready'] );
		if ( $documents_auth_ready && ( $has_backend_token || $has_vault_ref ) && 'oauth' !== $documents_auth_type ) {
			$documents_auth['label'] = __( 'Delegated Token', 'uplinkportal' );
			$documents_auth['state'] = 'enabled';
		}

		$documents_connected = '' !== $backend_base_url
			&& $documents_auth_ready
			&& '' !== $key_endpoint;

		/* translators: %s site name. */
		$default_welcome_message = sprintf( __( 'Welcome to %s Client Workspace.', 'uplinkportal' ), sanitize_text_field( (string) get_bloginfo( 'name' ) ) );

		return array(
			array(
				'model'         => __( 'Finance', 'uplinkportal' ),
				'connected'     => $this->build_status_cell( $billing_connected ? __( 'Connected', 'uplinkportal' ) : __( 'Missing Config', 'uplinkportal' ), $billing_connected ? 'enabled' : 'disabled' ),
				'token_health'  => $this->build_status_cell( (string) $billing_auth['label'], (string) $billing_auth['state'] ),
				'last_sync'     => $this->format_last_sync_for_display( isset( $last_sync_map['finance'] ) ? (string) $last_sync_map['finance'] : '' ),
				'policy_status' => $this->build_profile_policy_status( $security_baseline, $finance_lifecycle, __( 'Baseline Active', 'uplinkportal' ) ),
			),
			array(
				'model'         => __( 'IT Support', 'uplinkportal' ),
				'connected'     => $this->build_status_cell( $helpdesk_connected ? __( 'Connected', 'uplinkportal' ) : __( 'Missing Config', 'uplinkportal' ), $helpdesk_connected ? 'enabled' : 'disabled' ),
				'token_health'  => $this->build_status_cell( (string) $helpdesk_auth['label'], (string) $helpdesk_auth['state'] ),
				'last_sync'     => $this->format_last_sync_for_display( isset( $last_sync_map['it_support'] ) ? (string) $last_sync_map['it_support'] : '' ),
				'policy_status' => $this->build_profile_policy_status( $security_baseline, $support_lifecycle, __( 'Baseline Active', 'uplinkportal' ) ),
			),
			array(
				'model'         => __( 'Projects', 'uplinkportal' ),
				'connected'     => $this->build_status_cell( $pm_connected ? __( 'Connected', 'uplinkportal' ) : __( 'Missing Config', 'uplinkportal' ), $pm_connected ? 'enabled' : 'disabled' ),
				'token_health'  => $this->build_status_cell( (string) $projects_auth['label'], (string) $projects_auth['state'] ),
				'last_sync'     => $this->format_last_sync_for_display( isset( $last_sync_map['projects'] ) ? (string) $last_sync_map['projects'] : '' ),
				'policy_status' => $this->build_profile_policy_status( $security_baseline, $projects_lifecycle, __( 'Baseline Active', 'uplinkportal' ) ),
			),
			array(
				'model'         => __( 'Licensing', 'uplinkportal' ),
				'connected'     => $this->build_status_cell( $license_connected ? __( 'Connected', 'uplinkportal' ) : __( 'Missing Config', 'uplinkportal' ), $license_connected ? 'enabled' : 'disabled' ),
				'token_health'  => $this->build_status_cell( $license_connected && $license_valid ? __( 'Valid', 'uplinkportal' ) : ( $license_connected ? __( 'Needs Validation', 'uplinkportal' ) : __( 'Missing', 'uplinkportal' ) ), $license_connected && $license_valid ? 'enabled' : ( $license_connected ? 'warning' : 'disabled' ) ),
				'last_sync'     => $this->format_last_sync_for_display( isset( $last_sync_map['licensing'] ) ? (string) $last_sync_map['licensing'] : '' ),
				'policy_status' => $this->build_profile_policy_status( $security_baseline, $licensing_lifecycle, __( 'Feature-Gated', 'uplinkportal' ) ),
			),
			array(
				'model'         => __( 'Documents', 'uplinkportal' ),
				'connected'     => $this->build_status_cell( $documents_connected ? __( 'Connected', 'uplinkportal' ) : __( 'Missing Config', 'uplinkportal' ), $documents_connected ? 'enabled' : 'disabled' ),
				'token_health'  => $this->build_status_cell( (string) $documents_auth['label'], (string) $documents_auth['state'] ),
				'last_sync'     => $this->format_last_sync_for_display( isset( $last_sync_map['documents'] ) ? (string) $last_sync_map['documents'] : '' ),
				'policy_status' => $this->build_profile_policy_status(
					$security_baseline,
					$documents_lifecycle,
					$storage_private ? __( 'Private Storage', 'uplinkportal' ) : __( 'Fallback Hardened', 'uplinkportal' )
				),
			),
		);
	}

	/**
	 * Renders secret lease/expiry alert panel.
	 *
	 * @return void
	 */
	private function render_secret_lease_panel(): void {
		$alerts = $this->get_secret_lease_alerts();
		IntegrationSecurityUi::render_secret_lease_panel( $alerts );
	}

	/**
	 * Returns secret lease alerts for stale/unknown-age credentials.
	 *
	 * @return array<int,string>
	 */
	private function get_secret_lease_alerts(): array {
		$alerts          = array();
		$max_age_days    = absint( apply_filters( 'uplinkportal_secret_max_age_days', 90 ) );
		$max_age_days    = max( 30, $max_age_days );
		$max_age_seconds = $max_age_days * DAY_IN_SECONDS;
		$now             = time();

		$validation_sections = array(
			'billing'  => __( 'Finance API key', 'uplinkportal' ),
			'helpdesk' => __( 'IT Support API key', 'uplinkportal' ),
			'pm'       => __( 'Projects API key', 'uplinkportal' ),
		);

		foreach ( $validation_sections as $section => $label ) {
			$profile = $this->options->get_validation_profile( $section );
			if ( empty( $profile['api_key'] ) ) {
				continue;
			}

			$saved_at = $this->options->get_validation_api_key_saved_at( $section );
			$alerts   = array_merge( $alerts, $this->build_secret_age_alerts( $label, $saved_at, $now, $max_age_seconds, $max_age_days ) );
		}

		if ( $this->options->has_backend_service_token() ) {
			$saved_at = $this->options->get_backend_service_token_saved_at();
			$alerts   = array_merge( $alerts, $this->build_secret_age_alerts( __( 'Backend service token', 'uplinkportal' ), $saved_at, $now, $max_age_seconds, $max_age_days ) );
		}

		if ( $this->options->has_vault_connection_reference() ) {
			$saved_at = $this->options->get_vault_connection_reference_saved_at();
			$alerts   = array_merge( $alerts, $this->build_secret_age_alerts( __( 'Vault connection reference', 'uplinkportal' ), $saved_at, $now, $max_age_seconds, $max_age_days ) );
		}

		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			if ( ! $this->options->has_oauth_connection_reference( $model_id ) ) {
				continue;
			}

			$label = sprintf(
				/* translators: %s integration model label. */
				__( '%s OAuth connection reference', 'uplinkportal' ),
				$this->get_integration_model_label( $model_id )
			);
			$saved_at = $this->options->get_oauth_connection_reference_saved_at( $model_id );
			$alerts   = array_merge( $alerts, $this->build_secret_age_alerts( $label, $saved_at, $now, $max_age_seconds, $max_age_days ) );
		}

		return $alerts;
	}

	/**
	 * Builds alert text for one secret age check.
	 *
	 * @param string $label Secret label.
	 * @param string $saved_at Saved-at ISO timestamp.
	 * @param int    $now Current timestamp.
	 * @param int    $max_age_seconds Lease threshold in seconds.
	 * @param int    $max_age_days Lease threshold in days.
	 * @return array<int,string>
	 */
	private function build_secret_age_alerts( string $label, string $saved_at, int $now, int $max_age_seconds, int $max_age_days ): array {
		$saved_at = sanitize_text_field( $saved_at );
		if ( '' === $saved_at ) {
			return array(
				sprintf(
					/* translators: 1: secret label, 2: max age in days. */
					__( '%1$s has unknown age. Rotate within %2$d days.', 'uplinkportal' ),
					$label,
					$max_age_days
				),
			);
		}

		$timestamp = strtotime( $saved_at );
		if ( false === $timestamp ) {
			return array(
				sprintf(
					/* translators: %s secret label. */
					__( '%s has an invalid saved timestamp. Rotate and resave it.', 'uplinkportal' ),
					$label
				),
			);
		}

		$age_seconds = max( 0, $now - $timestamp );
		if ( $age_seconds <= $max_age_seconds ) {
			return array();
		}

		$age_days = (int) floor( $age_seconds / DAY_IN_SECONDS );
		return array(
			sprintf(
				/* translators: 1: secret label, 2: age in days, 3: max age in days. */
				__( '%1$s is %2$d days old (threshold: %3$d days). Rotate it.', 'uplinkportal' ),
				$label,
				$age_days,
				$max_age_days
			),
		);
	}

	/**
	 * Builds auth readiness + token health label for one integration model.
	 *
	 * @param string $auth_type Integration profile auth type.
	 * @param string $api_key Stored API key value.
	 * @param string $oauth_reference Stored OAuth/token-vault reference.
	 * @return array<string,mixed>
	 */
	private function build_model_auth_status( string $auth_type, string $api_key, string $oauth_reference ): array {
		$auth_type       = sanitize_key( $auth_type );
		$has_api_key     = '' !== sanitize_text_field( $api_key );
		$has_oauth_ref   = '' !== sanitize_text_field( $oauth_reference );
		$default_payload = array(
			'ready' => false,
			'label' => __( 'Missing', 'uplinkportal' ),
			'state' => 'disabled',
		);

		if ( false !== strpos( $auth_type, 'oauth' ) ) {
			if ( $has_oauth_ref ) {
				return array(
					'ready' => true,
					'label' => __( 'Delegated Reference', 'uplinkportal' ),
					'state' => 'enabled',
				);
			}

			return $default_payload;
		}

		if ( 'api_key' === $auth_type ) {
			if ( $has_api_key ) {
				return array(
					'ready' => true,
					'label' => __( 'API Key', 'uplinkportal' ),
					'state' => 'enabled',
				);
			}

			return $default_payload;
		}

		if ( $has_oauth_ref ) {
			return array(
				'ready' => true,
				'label' => __( 'Delegated Reference', 'uplinkportal' ),
				'state' => 'enabled',
			);
		}

		if ( $has_api_key ) {
			return array(
				'ready' => true,
				'label' => __( 'API Key Fallback', 'uplinkportal' ),
				'state' => 'warning',
			);
		}

		return $default_payload;
	}

	/**
	 * Normalizes integration profile lifecycle state.
	 *
	 * @param string $state Raw lifecycle state.
	 * @return string
	 */
	private function normalize_profile_lifecycle_state( string $state ): string {
		$state = sanitize_key( $state );
		if ( in_array( $state, array( 'draft', 'reviewed', 'active' ), true ) ) {
			return $state;
		}

		return 'draft';
	}

	/**
	 * Builds policy status using security baseline and lifecycle state.
	 *
	 * @param bool   $security_baseline Baseline state.
	 * @param string $lifecycle_state Lifecycle state.
	 * @param string $active_label Active state label.
	 * @return array<string,string>
	 */
	private function build_profile_policy_status( bool $security_baseline, string $lifecycle_state, string $active_label ): array {
		if ( ! $security_baseline ) {
			return $this->build_status_cell( __( 'Needs Review', 'uplinkportal' ), 'warning' );
		}

		$lifecycle_state = $this->normalize_profile_lifecycle_state( $lifecycle_state );
		if ( 'active' === $lifecycle_state ) {
			return $this->build_status_cell( $active_label, 'enabled' );
		}

		if ( 'reviewed' === $lifecycle_state ) {
			return $this->build_status_cell( __( 'Profile Reviewed', 'uplinkportal' ), 'warning' );
		}

		return $this->build_status_cell( __( 'Profile Draft', 'uplinkportal' ), 'warning' );
	}

	/**
	 * Masks likely sensitive values in audit log text.
	 *
	 * @param string $text Raw message.
	 * @return string
	 */
	private function mask_audit_log_text( string $text ): string {
		$text = sanitize_text_field( $text );
		if ( '' === $text ) {
			return '';
		}

		$pattern_map = array(
			'/\b(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*\b/i' => '$1********',
			'/\b(api[_-]?key|token|secret)\s*[:=]\s*([A-Za-z0-9\-\._~\+\/=]{6,})\b/i' => '$1=********',
			'/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i' => '********@********',
		);

		foreach ( $pattern_map as $pattern => $replacement ) {
			$text = preg_replace( $pattern, $replacement, $text );
		}

		/**
		 * Allows add-ons to apply stricter masking policies.
		 *
		 * @param string $text Masked text.
		 */
		$text = apply_filters( 'uplinkportal_mask_audit_log_text', $text );

		return is_string( $text ) ? $text : '';
	}

	/**
	 * Returns a formatted status cell payload.
	 *
	 * @param string $label Status label.
	 * @param string $state Status state.
	 * @return array<string,string>
	 */
	private function build_status_cell( string $label, string $state ): array {
		$state = sanitize_key( $state );
		if ( ! in_array( $state, array( 'enabled', 'disabled', 'warning' ), true ) ) {
			$state = 'disabled';
		}

		return array(
			'label' => sanitize_text_field( $label ),
			'state' => $state,
		);
	}

	/**
	 * Formats last sync timestamp for table display.
	 *
	 * @param string $value Stored ISO timestamp.
	 * @return string
	 */
	private function format_last_sync_for_display( string $value ): string {
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return __( 'Not recorded yet', 'uplinkportal' );
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return __( 'Not recorded yet', 'uplinkportal' );
		}

		return sprintf(
			/* translators: %s timestamp in UTC. */
			__( '%s UTC', 'uplinkportal' ),
			gmdate( 'Y-m-d H:i:s', $timestamp )
		);
	}

	/**
	 * Returns saved integration last-sync timestamps.
	 *
	 * @return array<string,string>
	 */
	private function get_integration_last_sync_map(): array {
		$stored = get_option( self::INTEGRATION_STATUS_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$map    = array_fill_keys( IntegrationModelRegistry::ids(), '' );

		foreach ( IntegrationModelRegistry::ids() as $model_key ) {
			if ( ! isset( $stored[ $model_key ] ) || ! is_string( $stored[ $model_key ] ) ) {
				continue;
			}

			$raw       = sanitize_text_field( (string) $stored[ $model_key ] );
			$timestamp = strtotime( $raw );
			if ( false === $timestamp ) {
				continue;
			}

			$map[ $model_key ] = gmdate( 'c', $timestamp );
		}

		return $map;
	}

	/**
	 * Records an integration model sync timestamp.
	 *
	 * @param string $model_key Integration model key.
	 * @return void
	 */
	private function record_integration_sync( string $model_key ): void {
		$model_key = sanitize_key( $model_key );
		if ( ! IntegrationModelRegistry::has( $model_key ) ) {
			return;
		}

		$map               = $this->get_integration_last_sync_map();
		$map[ $model_key ] = gmdate( 'c' );
		update_option( self::INTEGRATION_STATUS_OPTION, $map, false );
	}

	/**
	 * Maps validation section keys to integration model keys.
	 *
	 * @param string $section Validation section key.
	 * @return string
	 */
	private function get_model_key_for_validation_section( string $section ): string {
		return IntegrationModelRegistry::model_for_validation_section( $section );
	}

	/**
	 * Renders client document storage metrics and roadmap note.
	 *
	 * @return void
	 */
	private function render_document_storage_panel(): void {
		$metrics = $this->client_documents->get_storage_usage_metrics();
		$usage   = isset( $metrics['usage_bytes'] ) ? absint( $metrics['usage_bytes'] ) : 0;
		$quota   = isset( $metrics['quota_bytes'] ) ? absint( $metrics['quota_bytes'] ) : 0;
		$free    = isset( $metrics['free_bytes'] ) ? absint( $metrics['free_bytes'] ) : 0;
		$count   = isset( $metrics['document_count'] ) ? absint( $metrics['document_count'] ) : 0;
		$ratio   = isset( $metrics['usage_percent'] ) ? (float) $metrics['usage_percent'] : 0.0;
		$ratio   = max( 0.0, min( 100.0, $ratio ) );
		$path    = isset( $metrics['base_directory'] ) ? (string) $metrics['base_directory'] : '';
		$private = ! empty( $metrics['private_storage'] );
		?>
		<hr />
		<h2><?php echo esc_html__( 'Secure Document Storage', 'uplinkportal' ); ?></h2>
		<div class="uplinkportal-storage-meter" role="img" aria-label="<?php echo esc_attr__( 'Document storage usage meter', 'uplinkportal' ); ?>">
			<span class="uplinkportal-storage-meter-fill" style="width: <?php echo esc_attr( (string) $ratio ); ?>%;"></span>
		</div>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: used size, 2: quota size, 3: percentage. */
					__( 'Using %1$s of %2$s (%3$s%%).', 'uplinkportal' ),
					size_format( $usage, 1 ),
					size_format( $quota, 1 ),
					number_format_i18n( $ratio, 2 )
				)
			);
			?>
			</p>
			<ul class="uplinkportal-storage-stats">
				<?php /* translators: %d total number of encrypted client documents. */ ?>
				<li><?php echo esc_html( sprintf( __( 'Stored Documents: %d', 'uplinkportal' ), $count ) ); ?></li>
				<?php /* translators: %s available disk storage size. */ ?>
				<li><?php echo esc_html( sprintf( __( 'Disk Free: %s', 'uplinkportal' ), size_format( $free, 1 ) ) ); ?></li>
				<li>
				<?php
				echo esc_html(
					$private
						? __( 'Storage Mode: Private path outside uploads.', 'uplinkportal' )
						: __( 'Storage Mode: Fallback path (hardened with shields).', 'uplinkportal' )
				);
				?>
			</li>
			<li><code><?php echo esc_html( $path ); ?></code></li>
		</ul>
			<?php
	}

	/**
	 * Renders module catalog field with card sections.
	 *
	 * @return void
	 */
	public function render_modules_catalog_field(): void {
		$service_modules = $this->options->get_service_modules();
		$clients         = $this->get_client_workspace_users();
		$context_client  = $this->modules_entitlement_context_client_id();
		?>
			<?php if ( ! empty( $clients ) ) : ?>
				<div class="uplinkportal-module-section">
					<h3><?php echo esc_html__( 'Entitlement Context', 'uplinkportal' ); ?></h3>
					<p class="description"><?php echo esc_html__( 'Provider lock state is shown for the selected client context.', 'uplinkportal' ); ?></p>
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="uplinkportal-inline-action">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SETTINGS_SLUG ); ?>" />
						<input type="hidden" name="tab" value="modules" />
						<label for="uplinkportal-modules-entitlement-client"><strong><?php echo esc_html__( 'Client', 'uplinkportal' ); ?></strong></label>
						<select id="uplinkportal-modules-entitlement-client" name="entitlements_client">
							<?php foreach ( $clients as $client_user ) : ?>
								<?php $client_id = absint( $client_user->ID ); ?>
								<option value="<?php echo esc_attr( (string) $client_id ); ?>" <?php selected( $context_client, $client_id ); ?>>
									<?php echo esc_html( $client_user->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Switch Context', 'uplinkportal' ), 'secondary', '', false ); ?>
					</form>
				</div>
			<?php endif; ?>
		<?php
		$this->config_modules_ui->render( $service_modules, '', $this->options->key() );
	}

	/**
	 * Renders one module card and configuration window.
	 *
	 * @param string              $module_key Module key.
	 * @param array<string,mixed> $module_data Module metadata.
	 * @param bool                $is_enabled Whether module is enabled.
	 * @param bool                $is_required Whether module is required.
	 * @return void
	 */
	private function render_module_card( string $module_key, array $module_data, bool $is_enabled, bool $is_required ): void {
		$module_label       = isset( $module_data['label'] ) ? sanitize_text_field( (string) $module_data['label'] ) : $module_key;
		$module_description = isset( $module_data['description'] ) ? sanitize_text_field( (string) $module_data['description'] ) : '';
		$icon               = isset( $module_data['icon'] ) ? sanitize_key( (string) $module_data['icon'] ) : 'admin-generic';
		$modal_id           = 'uplinkportal-module-config-' . sanitize_html_class( $module_key );
		?>
		<article class="uplinkportal-module-card" data-module-card="<?php echo esc_attr( $module_key ); ?>">
			<header class="uplinkportal-module-card-header">
				<div class="uplinkportal-module-card-title">
					<span class="uplinkportal-icon-box" aria-hidden="true">
						<span class="dashicons <?php echo esc_attr( $this->get_plan_icon_dashicon( $icon ) ); ?>"></span>
					</span>
					<h4><?php echo esc_html( $module_label ); ?></h4>
				</div>
				<button
					type="button"
					class="uplinkportal-icon-action uplinkportal-module-gear uplinkportal-open-override"
					data-module-config-trigger="<?php echo esc_attr( $module_key ); ?>"
					data-override-target="<?php echo esc_attr( $modal_id ); ?>"
					<?php disabled( ! $is_enabled ); ?>
				>
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<span class="screen-reader-text">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s module label. */
								__( 'Configure %s', 'uplinkportal' ),
								$module_label
							)
						);
						?>
					</span>
				</button>
			</header>
			<p><?php echo esc_html( $module_description ); ?></p>
			<div class="uplinkportal-module-card-controls">
				<?php if ( $is_required ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $this->options->key() ); ?>[service_modules][<?php echo esc_attr( $module_key ); ?>]" value="1" />
					<span class="uplinkportal-module-required-note"><?php echo esc_html__( 'Always enabled', 'uplinkportal' ); ?></span>
				<?php else : ?>
					<label class="uplinkportal-module-switch">
						<input
							class="uplinkportal-module-checkbox"
							type="checkbox"
							name="<?php echo esc_attr( $this->options->key() ); ?>[service_modules][<?php echo esc_attr( $module_key ); ?>]"
							value="1"
							data-module-key="<?php echo esc_attr( $module_key ); ?>"
							<?php checked( $is_enabled ); ?>
						/>
						<span><?php echo esc_html__( 'Enabled', 'uplinkportal' ); ?></span>
					</label>
				<?php endif; ?>
			</div>
		</article>

		<?php $this->render_module_configuration_modal( $module_key, $module_label, $modal_id ); ?>
		<?php
	}

	/**
	 * Renders module configuration modal with optional tab menu.
	 *
	 * @param string $module_key Module key.
	 * @param string $module_label Module label.
	 * @param string $modal_id Modal identifier.
	 * @return void
	 */
	private function render_module_configuration_modal( string $module_key, string $module_label, string $modal_id ): void {
		$tabs        = $this->get_module_configuration_tabs( $module_key );
		$title       = $this->get_module_configuration_title( $module_key, $module_label );
		$description = $this->get_module_configuration_description( $module_key );
		?>
		<div id="<?php echo esc_attr( $modal_id ); ?>" class="uplinkportal-override-modal uplinkportal-settings-window-modal" hidden>
			<div class="uplinkportal-override-dialog uplinkportal-settings-window-dialog uplinkportal-module-config-window" data-uplinkportal-window-tabs>
				<div class="uplinkportal-settings-window-head">
					<div class="uplinkportal-settings-window-heading">
						<h3><?php echo esc_html( $title ); ?></h3>
						<p class="description"><?php echo esc_html( $description ); ?></p>
					</div>
					<button type="button" class="uplinkportal-icon-action uplinkportal-close-override" aria-label="<?php echo esc_attr__( 'Close configuration dialog', 'uplinkportal' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php echo esc_html__( 'Close', 'uplinkportal' ); ?></span>
					</button>
				</div>
				<?php if ( count( $tabs ) > 1 ) : ?>
					<nav class="uplinkportal-module-window-tabs" role="tablist">
						<?php foreach ( $tabs as $index => $tab ) : ?>
							<?php
							$tab_key = isset( $tab['key'] ) ? sanitize_key( (string) $tab['key'] ) : 'tab_' . $index;
							?>
							<button
								type="button"
								class="button uplinkportal-module-window-tab <?php echo 0 === $index ? 'is-active' : ''; ?>"
								data-uplinkportal-window-tab="<?php echo esc_attr( $tab_key ); ?>"
								aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
							>
								<?php echo esc_html( isset( $tab['label'] ) ? (string) $tab['label'] : $tab_key ); ?>
							</button>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
				<div class="uplinkportal-settings-window-body">
					<?php foreach ( $tabs as $index => $tab ) : ?>
						<?php
						$tab_key  = isset( $tab['key'] ) ? sanitize_key( (string) $tab['key'] ) : 'tab_' . $index;
						$callback = isset( $tab['callback'] ) && is_callable( $tab['callback'] ) ? $tab['callback'] : null;
						?>
						<section
							class="uplinkportal-module-window-panel"
							data-uplinkportal-window-panel="<?php echo esc_attr( $tab_key ); ?>"
							<?php if ( 0 !== $index ) : ?>
								hidden
							<?php endif; ?>
						>
							<?php if ( null !== $callback ) : ?>
								<?php call_user_func( $callback ); ?>
							<?php endif; ?>
						</section>
					<?php endforeach; ?>
				</div>
				<div class="uplinkportal-settings-window-foot">
					<p class="description"><?php echo esc_html__( 'Module changes are saved when you click Save Module & Integration Settings.', 'uplinkportal' ); ?></p>
					<button type="button" class="button button-primary uplinkportal-close-override"><?php echo esc_html__( 'Done', 'uplinkportal' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns module configuration modal title.
	 *
	 * @param string $module_key Module key.
	 * @param string $module_label Module label.
	 * @return string
	 */
	private function get_module_configuration_title( string $module_key, string $module_label ): string {
		if ( 'database' === $module_key ) {
			return __( 'Database Configurations', 'uplinkportal' );
		}

		/* translators: %s module display label. */
		return sprintf( __( '%s Configuration', 'uplinkportal' ), $module_label );
	}

	/**
	 * Returns module configuration inline description.
	 *
	 * @param string $module_key Module key.
	 * @return string
	 */
	private function get_module_configuration_description( string $module_key ): string {
		if ( 'database' === $module_key ) {
			return __( 'Configure database profiles, secrets routing, storage, and user schema mappings for this site.', 'uplinkportal' );
		}

		return __( 'Configure integration profile and automation settings for this module.', 'uplinkportal' );
	}

	/**
	 * Returns tab definitions for one module configuration window.
	 *
	 * @param string $module_key Module key.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_module_configuration_tabs( string $module_key ): array {
		$module_key = sanitize_key( $module_key );
		$model_id   = IntegrationModelRegistry::model_for_module( $module_key );

		if ( 'database' === $module_key ) {
			return array(
				array(
					'key'      => 'profile',
					'label'    => __( 'Profile', 'uplinkportal' ),
					'callback' => array( $this, 'render_database_module_profile_tab' ),
				),
			);
		}

		$tabs = array();

		if ( '' !== $model_id ) {
			$tabs[] = array(
				'key'      => 'profile',
				'label'    => __( 'Profile', 'uplinkportal' ),
				'callback' => function () use ( $model_id ) {
					$this->render_module_integration_profiles_for_models( array( $model_id ) );
				},
			);
			$tabs[] = array(
				'key'      => 'automation',
				'label'    => __( 'Automation', 'uplinkportal' ),
				'callback' => function () use ( $model_id ) {
					$this->render_module_automation_recipes_for_models( array( $model_id ) );
				},
			);
		}

		if ( 'custom_dummy' === $module_key ) {
			$tabs = array(
				array(
					'key'      => 'overview',
					'label'    => __( 'Overview', 'uplinkportal' ),
					'callback' => function () use ( $module_key ) {
						?>
						<p><?php echo esc_html__( 'Use this workspace to test module extension points and custom integrations.', 'uplinkportal' ); ?></p>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s list of enabled pages. */
									__( 'Enabled pages: %s', 'uplinkportal' ),
									implode( ', ', $this->get_module_section_labels( $module_key ) )
								)
							);
							?>
						</p>
						<?php
					},
				),
			);
		}

		if ( empty( $tabs ) ) {
			$tabs[] = array(
				'key'      => 'overview',
				'label'    => __( 'Overview', 'uplinkportal' ),
				'callback' => function () use ( $module_key ) {
					$this->render_module_validation_fields( $module_key );
				},
			);
		}

		return $tabs;
	}

	/**
	 * Renders database module profile tab with profile switcher.
	 *
	 * @return void
	 */
	private function render_database_module_profile_tab(): void {
		$profiles = array(
			array(
				'key'                 => 'file_storage',
				'label'               => __( 'File Storage', 'uplinkportal' ),
				'profile_callback'    => array( $this, 'render_database_module_file_storage_tab' ),
				'automation_callback' => function (): void {
					$this->render_module_automation_recipes_for_models( array( IntegrationModelRegistry::MODEL_DOCUMENTS ) );
				},
			),
			array(
				'key'                 => 'secrets',
				'label'               => __( 'Secrets', 'uplinkportal' ),
				'profile_callback'    => array( $this, 'render_database_module_secrets_tab' ),
				'automation_callback' => null,
			),
			array(
				'key'                 => 'users',
				'label'               => __( 'Users', 'uplinkportal' ),
				'profile_callback'    => array( $this, 'render_database_module_users_tab' ),
				'automation_callback' => null,
			),
		);
		?>
		<nav class="uplinkportal-module-window-tabs" role="tablist" data-uplinkportal-profile-switcher>
			<?php foreach ( $profiles as $index => $profile ) : ?>
				<?php
				$profile_key = isset( $profile['key'] ) ? sanitize_key( (string) $profile['key'] ) : 'profile_' . $index;
				?>
				<button
					type="button"
					class="button uplinkportal-module-window-tab <?php echo 0 === $index ? 'is-active' : ''; ?>"
					data-uplinkportal-profile-tab="<?php echo esc_attr( $profile_key ); ?>"
					aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
				>
					<?php echo esc_html( isset( $profile['label'] ) ? (string) $profile['label'] : $profile_key ); ?>
				</button>
			<?php endforeach; ?>
		</nav>
		<div class="uplinkportal-database-profile-panels">
				<?php foreach ( $profiles as $index => $profile ) : ?>
					<?php
					$profile_key = isset( $profile['key'] ) ? sanitize_key( (string) $profile['key'] ) : 'profile_' . $index;
					?>
						<section
						class="uplinkportal-module-window-panel"
						data-uplinkportal-profile-panel="<?php echo esc_attr( $profile_key ); ?>"
					<?php if ( 0 !== $index ) : ?>
						hidden
					<?php endif; ?>
				>
							<?php
							$profile_callback = isset( $profile['profile_callback'] ) && is_callable( $profile['profile_callback'] ) ? $profile['profile_callback'] : null;
							$automation_cb    = isset( $profile['automation_callback'] ) && is_callable( $profile['automation_callback'] ) ? $profile['automation_callback'] : null;
							?>
							<div class="uplinkportal-database-profile-inner">
								<nav class="uplinkportal-module-window-tabs" role="tablist" data-uplinkportal-database-view-switcher>
									<button type="button" class="button uplinkportal-module-window-tab is-active" data-uplinkportal-database-view-tab="<?php echo esc_attr( $profile_key ); ?>:profile" aria-selected="true">
										<?php echo esc_html__( 'Profile', 'uplinkportal' ); ?>
									</button>
									<button type="button" class="button uplinkportal-module-window-tab" data-uplinkportal-database-view-tab="<?php echo esc_attr( $profile_key ); ?>:automation" aria-selected="false">
										<?php echo esc_html__( 'Automation', 'uplinkportal' ); ?>
									</button>
								</nav>
								<section data-uplinkportal-database-view-panel="<?php echo esc_attr( $profile_key ); ?>:profile">
									<?php if ( null !== $profile_callback ) : ?>
										<?php call_user_func( $profile_callback ); ?>
									<?php endif; ?>
								</section>
								<section data-uplinkportal-database-view-panel="<?php echo esc_attr( $profile_key ); ?>:automation" hidden>
									<?php if ( null !== $automation_cb ) : ?>
										<?php call_user_func( $automation_cb ); ?>
									<?php else : ?>
										<p class="description"><?php echo esc_html__( 'No automation settings are mapped to this profile.', 'uplinkportal' ); ?></p>
									<?php endif; ?>
								</section>
							</div>
						</section>
					<?php endforeach; ?>
			</div>
		<?php
	}

	/**
	 * Renders database module file storage tab.
	 *
	 * @return void
	 */
	private function render_database_module_file_storage_tab(): void {
		$documents_profile = $this->options->get_integration_profile( IntegrationModelRegistry::MODEL_DOCUMENTS );
		$provider          = isset( $documents_profile['provider'] ) ? sanitize_key( (string) $documents_profile['provider'] ) : '';
		$is_private        = 'private_backend_storage' === $provider;

		echo '<p class="description">' . esc_html__( 'Manage document storage integration and storage posture for encrypted client files.', 'uplinkportal' ) . '</p>';
		$this->render_module_integration_profiles_for_models(
			array(
				IntegrationModelRegistry::MODEL_DOCUMENTS,
			)
		);
		?>
		<div data-uplinkportal-secure-storage-panel 
		<?php
		if ( ! $is_private ) :
			?>
			hidden<?php endif; ?>>
			<?php $this->render_document_storage_panel(); ?>
		</div>
		<?php
	}

	/**
	 * Renders database module secrets tab.
	 *
	 * @return void
	 */
	private function render_database_module_secrets_tab(): void {
		echo '<p class="description">' . esc_html__( 'Set backend endpoint, service token, and secrets routing provider for this site.', 'uplinkportal' ) . '</p>';
		$this->render_backend_base_url_field();
		$this->render_backend_service_token_field();
		$this->render_secret_manager_field();
	}

	/**
	 * Renders database module users tab.
	 *
	 * @return void
	 */
	private function render_database_module_users_tab(): void {
		$tenant_profile    = $this->options->get_tenant_boundary_profile();
		$mode              = isset( $tenant_profile['mode'] ) ? sanitize_key( (string) $tenant_profile['mode'] ) : 'single_site';
		$enforce_scope     = ! empty( $tenant_profile['enforce_site_scope'] );
		$require_sa        = ! empty( $tenant_profile['require_super_admin'] );
		$field_base        = $this->options->key() . '[governance][tenant_boundary]';
		$provider          = $this->client_profiles->get_default_provider();
		$schema            = $this->client_profiles->get_profile_schema();
		$scoped_client_cnt = count( $this->client_profiles->get_client_workspace_users() );
		?>
		<p class="description"><?php echo esc_html__( 'Edit tenant boundary and user schema mapping controls for the client profile store.', 'uplinkportal' ); ?></p>
		<div class="uplinkportal-module-required-fields">
			<p>
				<label>
					<?php echo esc_html__( 'Tenant Mode', 'uplinkportal' ); ?><br />
					<select name="<?php echo esc_attr( $field_base ); ?>[mode]">
						<option value="single_site" <?php selected( 'single_site', $mode ); ?>><?php echo esc_html__( 'Single Site', 'uplinkportal' ); ?></option>
						<option value="multisite_site_isolation" <?php selected( 'multisite_site_isolation', $mode ); ?>><?php echo esc_html__( 'Multisite Site Isolation', 'uplinkportal' ); ?></option>
						<option value="multisite_network_shared" <?php selected( 'multisite_network_shared', $mode ); ?>><?php echo esc_html__( 'Multisite Network Shared', 'uplinkportal' ); ?></option>
					</select>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $field_base ); ?>[enforce_site_scope]" value="1" <?php checked( $enforce_scope ); ?> />
					<?php echo esc_html__( 'Enforce current-site user scope in multisite', 'uplinkportal' ); ?>
				</label><br />
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $field_base ); ?>[require_super_admin]" value="1" <?php checked( $require_sa ); ?> />
					<?php echo esc_html__( 'Require super admin for network-shared access', 'uplinkportal' ); ?>
				</label>
			</p>
		</div>
		<div class="notice notice-info inline uplinkportal-inline-callout">
			<p><?php echo esc_html__( 'Profile reads/writes remain protected by capability checks, tenant scoping, and REST nonce enforcement.', 'uplinkportal' ); ?></p>
		</div>
		<div class="uplinkportal-integration-profile-grid uplinkportal-users-config-grid">
			<article class="uplinkportal-integration-profile-card">
				<h4><?php echo esc_html__( 'Database Provider', 'uplinkportal' ); ?></h4>
				<p><span class="uplinkportal-status-pill is-enabled uplinkportal-status-pill--compact"><?php echo esc_html__( 'Default', 'uplinkportal' ); ?></span></p>
				<p><strong><?php echo esc_html__( 'WordPress Users + UplinkPortal Client Profile Meta', 'uplinkportal' ); ?></strong></p>
				<p class="description"><?php echo esc_html__( 'This provider keeps identity/authentication in the native WordPress Users system and stores client public profile fields in dedicated namespaced meta keys.', 'uplinkportal' ); ?></p>
				<ul class="uplinkportal-storage-stats">
					<li><?php echo esc_html__( 'Provider ID:', 'uplinkportal' ) . ' ' . esc_html( $provider ); ?></li>
					<li><?php echo esc_html__( 'Scoped Client Users:', 'uplinkportal' ) . ' ' . esc_html( (string) $scoped_client_cnt ); ?></li>
				</ul>
			</article>
			<article class="uplinkportal-integration-profile-card">
				<h4><?php echo esc_html__( 'Client Profile Meta Schema', 'uplinkportal' ); ?></h4>
				<p class="description"><?php echo esc_html__( 'Dedicated metadata fields are validated and scoped per client user.', 'uplinkportal' ); ?></p>
				<ul class="uplinkportal-users-schema-list">
					<?php foreach ( $schema as $field_key => $field_data ) : ?>
						<?php
						$field_label       = isset( $field_data['label'] ) ? sanitize_text_field( (string) $field_data['label'] ) : $field_key;
						$field_description = isset( $field_data['description'] ) ? sanitize_text_field( (string) $field_data['description'] ) : '';
						?>
						<li>
							<strong><?php echo esc_html( $field_label ); ?></strong><br />
							<code><?php echo esc_html( ClientProfileStore::meta_key( (string) $field_key ) ); ?></code><br />
							<span class="description"><?php echo esc_html( $field_description ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</article>
			<article class="uplinkportal-integration-profile-card">
				<h4><?php echo esc_html__( 'Access Guardrails', 'uplinkportal' ); ?></h4>
				<ul class="uplinkportal-storage-stats">
					<li><?php echo esc_html__( 'Tenant Mode:', 'uplinkportal' ) . ' ' . esc_html( $mode ); ?></li>
					<li><?php echo esc_html__( 'Enforce Site Scope:', 'uplinkportal' ) . ' ' . esc_html( $enforce_scope ? __( 'Enabled', 'uplinkportal' ) : __( 'Disabled', 'uplinkportal' ) ); ?></li>
					<li><?php echo esc_html__( 'Require Super Admin:', 'uplinkportal' ) . ' ' . esc_html( $require_sa ? __( 'Enabled', 'uplinkportal' ) : __( 'Disabled', 'uplinkportal' ) ); ?></li>
					<li><?php echo esc_html__( 'REST Guard:', 'uplinkportal' ) . ' ' . esc_html__( 'Nonce + capability + tenant scope', 'uplinkportal' ); ?></li>
				</ul>
				<p class="description"><?php echo esc_html__( 'Profile routes only allow authorized users to read/update data for their in-scope client profile.', 'uplinkportal' ); ?></p>
			</article>
		</div>
		<?php
	}

	/**
	 * Renders integration profile cards for model ids.
	 *
	 * @param array<int,string> $model_ids Model ids.
	 * @return void
	 */
	private function render_module_integration_profiles_for_models( array $model_ids ): void {
		$profiles       = $this->options->get_integration_profiles();
		$allowed_models = array();

		foreach ( $model_ids as $model_id ) {
			$model_id = sanitize_key( $model_id );
			if ( IntegrationModelRegistry::has( $model_id ) ) {
				$allowed_models[] = $model_id;
			}
		}

		if ( empty( $allowed_models ) ) {
			echo '<p class="description">' . esc_html__( 'No integration profiles are mapped to this module.', 'uplinkportal' ) . '</p>';
			return;
		}

		ModuleProfilesAutomationUi::render_integration_profiles(
			$allowed_models,
			$profiles,
			function ( string $model_id, array $profile ): void {
				$this->render_model_integration_profile_card( $model_id, $profile );
			}
		);
	}

	/**
	 * Renders one integration profile card.
	 *
	 * @param string              $model_id Model id.
	 * @param array<string,mixed> $profile Profile payload.
	 * @return void
	 */
	private function render_model_integration_profile_card( string $model_id, array $profile ): void {
		$module_slug     = $this->provider_catalog_module_slug_for_model( $model_id );
		$module_profile  = $this->provider_catalog_profile_for_model( $model_id );
		$context_client  = $this->modules_entitlement_context_client_id();
		$catalog         = $context_client > 0
			? $this->provider_catalog_service->get_providers_for_client( $context_client, $module_slug )
			: $this->provider_catalog_service->get_providers_for_module( $module_slug );
		$catalog         = $this->filter_provider_catalog_by_profile( $catalog, $module_profile );
		$catalog_by_id   = $this->index_provider_catalog_by_id( $catalog );
		$field_base      = $this->options->key() . '[integration_profiles][' . $model_id . ']';
		$stored_provider = isset( $profile['provider'] ) ? sanitize_key( (string) $profile['provider'] ) : 'custom';
		$auth_type       = $this->normalize_profile_auth_type_for_ui( isset( $profile['auth_type'] ) ? (string) $profile['auth_type'] : 'api_key' );
		$alias           = isset( $profile['endpoint_alias'] ) ? sanitize_text_field( (string) $profile['endpoint_alias'] ) : '';
		$retention       = isset( $profile['retention_policy'] ) ? sanitize_key( (string) $profile['retention_policy'] ) : 'default';
		$lifecycle_state = $this->normalize_profile_lifecycle_state( isset( $profile['lifecycle_state'] ) ? (string) $profile['lifecycle_state'] : 'draft' );
		$scopes          = isset( $profile['scopes'] ) && is_array( $profile['scopes'] ) ? $profile['scopes'] : array();
		$has_oauth_ref   = ! empty( $profile['oauth_connection_ref'] );
		$scope_label     = implode( ', ', array_map( 'sanitize_text_field', $scopes ) );

		if ( 'custom' !== $stored_provider && '' !== $stored_provider && ! isset( $catalog_by_id[ $stored_provider ] ) ) {
			$recovered = array(
				'id'          => $stored_provider,
				'label'       => $stored_provider,
				'locked'      => true,
				'auth_types'  => array( $auth_type ),
				'defaults'    => array(
					'scopes'           => array_values( array_filter( array_map( 'sanitize_text_field', $scopes ) ) ),
					'endpoint_alias'   => $alias,
					'retention_policy' => $retention,
				),
				'helper_text' => $this->get_locked_provider_helper_text(),
			);

			$catalog[]                         = $recovered;
			$catalog_by_id[ $stored_provider ] = $recovered;
		}

		$provider = 'custom';
		if ( '' !== $stored_provider && 'custom' !== $stored_provider && isset( $catalog_by_id[ $stored_provider ] ) ) {
			$provider = $stored_provider;
		}

		$provider_custom = 'custom' === $provider && 'custom' !== $stored_provider ? $stored_provider : '';
		$selected_data   = 'custom' !== $provider && isset( $catalog_by_id[ $provider ] ) ? $catalog_by_id[ $provider ] : array();
		$is_locked       = ! empty( $selected_data['locked'] );
		if ( $is_locked ) {
			$lifecycle_state = 'draft';
		}

		$helper_text      = isset( $selected_data['helper_text'] ) ? sanitize_text_field( (string) $selected_data['helper_text'] ) : '';
		$provider_message = $is_locked ? ( '' !== $helper_text ? $helper_text : $this->get_locked_provider_helper_text() ) : '';
		$grace_info       = isset( $selected_data['grace_info'] ) && is_array( $selected_data['grace_info'] ) ? $selected_data['grace_info'] : array();
		$grace_until      = isset( $grace_info['grace_until'] ) ? sanitize_text_field( (string) $grace_info['grace_until'] ) : '';
		$grace_warning    = '';
		if ( ! empty( $grace_info['in_grace'] ) && '' !== $grace_until ) {
			$grace_warning = sprintf(
				/* translators: %s grace period end date in UTC. */
				__( 'Add-on in grace until %s. Existing automations continue during grace; profile stays Draft until reactivated.', 'uplinkportal' ),
				gmdate( 'M j, Y H:i', strtotime( $grace_until ) ) . ' UTC'
			);
		}
		$show_custom_slug   = 'custom' === $provider;
		$is_documents_model = IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id;
		$hide_endpoint_ui   = $is_documents_model && 'private_backend_storage' === $provider;
		$show_connection    = ( 'custom' === $provider && 'api_key' === $auth_type && '' !== IntegrationModelRegistry::validation_section_for_model( $model_id ) && ! $hide_endpoint_ui );
		?>
		<div class="uplinkportal-integration-profile-card" data-uplinkportal-profile-card data-uplinkportal-model="<?php echo esc_attr( $model_id ); ?>" data-uplinkportal-provider-module="<?php echo esc_attr( $module_slug ); ?>" data-uplinkportal-provider-profile="<?php echo esc_attr( $module_profile ); ?>" data-uplinkportal-client-id="<?php echo esc_attr( (string) $context_client ); ?>">
			<p>
				<label>
					<?php echo esc_html__( 'Integration Provider', 'uplinkportal' ); ?>
					<?php if ( $is_locked ) : ?>
						<span class="uplinkportal-admin-badge"><?php echo esc_html__( 'Locked', 'uplinkportal' ); ?></span>
					<?php endif; ?>
					<br />
					<select class="regular-text" name="<?php echo esc_attr( $field_base ); ?>[provider]" data-uplinkportal-provider-template>
						<?php foreach ( $catalog as $provider_data ) : ?>
							<?php
							if ( ! is_array( $provider_data ) ) {
								continue;
							}
							$provider_key      = isset( $provider_data['id'] ) ? sanitize_key( (string) $provider_data['id'] ) : '';
							$provider_defaults = isset( $provider_data['defaults'] ) && is_array( $provider_data['defaults'] ) ? $provider_data['defaults'] : array();
							$provider_scopes   = isset( $provider_defaults['scopes'] ) && is_array( $provider_defaults['scopes'] ) ? $provider_defaults['scopes'] : array();
							$provider_types    = isset( $provider_data['auth_types'] ) && is_array( $provider_data['auth_types'] ) ? array_values( array_filter( array_map( 'sanitize_key', $provider_data['auth_types'] ) ) ) : array();
							$provider_type     = isset( $provider_types[0] ) ? (string) $provider_types[0] : '';
							$provider_locked   = ! empty( $provider_data['locked'] );
							$provider_helper   = isset( $provider_data['helper_text'] ) ? sanitize_text_field( (string) $provider_data['helper_text'] ) : '';
							$option_label      = isset( $provider_data['label'] ) ? sanitize_text_field( (string) $provider_data['label'] ) : $provider_key;
							if ( '' === $provider_key ) {
								continue;
							}
							?>
							<option
								value="<?php echo esc_attr( $provider_key ); ?>"
								data-auth-type="<?php echo esc_attr( $provider_type ); ?>"
								data-auth-types="<?php echo esc_attr( implode( ',', $provider_types ) ); ?>"
								data-endpoint-alias="<?php echo esc_attr( isset( $provider_defaults['endpoint_alias'] ) ? (string) $provider_defaults['endpoint_alias'] : '' ); ?>"
								data-retention-policy="<?php echo esc_attr( isset( $provider_defaults['retention_policy'] ) ? (string) $provider_defaults['retention_policy'] : '' ); ?>"
								data-scopes="<?php echo esc_attr( implode( ', ', array_map( 'sanitize_text_field', $provider_scopes ) ) ); ?>"
								data-provider-locked="<?php echo $provider_locked ? '1' : '0'; ?>"
								data-helper-text="<?php echo esc_attr( $provider_helper ); ?>"
								data-grace-until="<?php echo esc_attr( isset( $provider_data['grace_info']['grace_until'] ) ? (string) $provider_data['grace_info']['grace_until'] : '' ); ?>"
								<?php selected( $provider, $provider_key ); ?>
								<?php disabled( $provider_locked && $provider !== $provider_key ); ?>
								>
									<?php
									if ( $provider_locked ) {
										/* translators: %s: Provider label. */
										$option_display_label = sprintf( __( '%s (Locked)', 'uplinkportal' ), $option_label );
									} else {
										$option_display_label = $option_label;
									}
									echo esc_html( $option_display_label );
									?>
								</option>
						<?php endforeach; ?>
						<option value="custom" data-auth-type="api_key" data-auth-types="api_key" data-endpoint-alias="" data-retention-policy="default" data-scopes="" data-provider-locked="0" data-helper-text="" data-grace-until="" <?php selected( $provider, 'custom' ); ?>><?php echo esc_html__( 'Custom Provider', 'uplinkportal' ); ?></option>
					</select>
				</label>
			</p>
			<p class="description" data-uplinkportal-provider-helper 
			<?php
			if ( '' === $provider_message ) :
				?>
				hidden<?php endif; ?>>
				<?php echo esc_html( $provider_message ); ?>
			</p>
			<div class="notice notice-warning inline uplinkportal-inline-callout" data-uplinkportal-grace-provider-warning 
			<?php
			if ( '' === $grace_warning ) :
				?>
				hidden<?php endif; ?>>
				<p><?php echo esc_html( $grace_warning ); ?></p>
			</div>
			<p class="uplinkportal-provider-custom-row" data-uplinkportal-provider-custom-row data-uplinkportal-endpoint-field 
			<?php
			if ( ! $show_custom_slug || $hide_endpoint_ui ) :
				?>
				hidden<?php endif; ?>>
				<label>
					<?php echo esc_html__( 'Custom Provider Slug', 'uplinkportal' ); ?><br />
					<input type="text" class="regular-text" name="<?php echo esc_attr( $field_base ); ?>[provider_custom]" value="<?php echo esc_attr( $provider_custom ); ?>" placeholder="<?php echo esc_attr__( 'custom_provider', 'uplinkportal' ); ?>" data-uplinkportal-provider-custom />
				</label>
			</p>
			<p data-uplinkportal-endpoint-field 
			<?php
			if ( $hide_endpoint_ui ) :
				?>
				hidden<?php endif; ?>>
				<span class="uplinkportal-field-label"><?php echo esc_html__( 'Auth Type', 'uplinkportal' ); ?></span><br />
				<span class="uplinkportal-auth-type-grid" data-uplinkportal-auth-type-grid>
					<label class="uplinkportal-auth-type-option">
						<input type="radio" name="<?php echo esc_attr( $field_base ); ?>[auth_type]" value="oauth" data-uplinkportal-profile-auth <?php checked( 'oauth', $auth_type ); ?> />
						<span><?php echo esc_html__( 'OAuth', 'uplinkportal' ); ?></span>
					</label>
					<label class="uplinkportal-auth-type-option">
						<input type="radio" name="<?php echo esc_attr( $field_base ); ?>[auth_type]" value="api_key" data-uplinkportal-profile-auth <?php checked( 'api_key', $auth_type ); ?> />
						<span><?php echo esc_html__( 'API Key', 'uplinkportal' ); ?></span>
					</label>
					<label class="uplinkportal-auth-type-option">
						<input type="radio" name="<?php echo esc_attr( $field_base ); ?>[auth_type]" value="hybrid" data-uplinkportal-profile-auth <?php checked( 'hybrid', $auth_type ); ?> />
						<span><?php echo esc_html__( 'Hybrid', 'uplinkportal' ); ?></span>
					</label>
				</span>
			</p>
				<p data-uplinkportal-endpoint-field 
				<?php
				if ( $hide_endpoint_ui ) :
					?>
					hidden<?php endif; ?>>
					<label>
						<?php echo esc_html__( 'Endpoint Alias', 'uplinkportal' ); ?><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr( $field_base ); ?>[endpoint_alias]" value="<?php echo esc_attr( $alias ); ?>" placeholder="<?php echo esc_attr__( 'primary', 'uplinkportal' ); ?>" data-uplinkportal-profile-alias />
					</label>
				</p>
				<p>
					<label>
						<?php echo esc_html__( 'Retention Policy', 'uplinkportal' ); ?><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr( $field_base ); ?>[retention_policy]" value="<?php echo esc_attr( $retention ); ?>" placeholder="<?php echo esc_attr__( 'default', 'uplinkportal' ); ?>" data-uplinkportal-profile-retention />
					</label>
				</p>
				<p>
					<label>
						<?php echo esc_html__( 'Profile State', 'uplinkportal' ); ?><br />
						<select name="<?php echo esc_attr( $field_base ); ?>[lifecycle_state]" data-uplinkportal-profile-state>
							<option value="draft" <?php selected( 'draft', $lifecycle_state ); ?>><?php echo esc_html__( 'Draft', 'uplinkportal' ); ?></option>
							<option value="reviewed" <?php selected( 'reviewed', $lifecycle_state ); ?> <?php disabled( $is_locked ); ?>><?php echo esc_html__( 'Reviewed', 'uplinkportal' ); ?></option>
							<option value="active" <?php selected( 'active', $lifecycle_state ); ?> <?php disabled( $is_locked ); ?>><?php echo esc_html__( 'Active', 'uplinkportal' ); ?></option>
						</select>
					</label>
				</p>
				<div class="notice notice-warning inline uplinkportal-inline-callout" data-uplinkportal-locked-provider-warning data-uplinkportal-locked-warning-model="<?php echo esc_attr( $model_id ); ?>" 
				<?php
				if ( ! $is_locked ) :
					?>
					hidden<?php endif; ?>>
					<p><?php echo esc_html__( "Provider is locked. Profile remains Draft and automations can't be enabled.", 'uplinkportal' ); ?></p>
				</div>
				<p data-uplinkportal-endpoint-field 
				<?php
				if ( $hide_endpoint_ui ) :
					?>
					hidden<?php endif; ?>>
					<label>
						<?php echo esc_html__( 'Scopes (comma or newline separated)', 'uplinkportal' ); ?><br />
						<textarea class="large-text" rows="2" name="<?php echo esc_attr( $field_base ); ?>[scopes_text]" placeholder="<?php echo esc_attr__( 'read:items, write:items', 'uplinkportal' ); ?>" data-uplinkportal-profile-scopes><?php echo esc_textarea( $scope_label ); ?></textarea>
					</label>
				</p>
			<p data-uplinkportal-endpoint-field 
			<?php
			if ( $hide_endpoint_ui ) :
				?>
				hidden<?php endif; ?>>
				<label>
					<?php echo esc_html__( 'OAuth Connection Reference (Opaque)', 'uplinkportal' ); ?><br />
					<input
						type="password"
						class="regular-text"
						name="<?php echo esc_attr( $field_base ); ?>[oauth_connection_ref]"
						value=""
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( $has_oauth_ref ? __( 'Stored. Enter a new reference to rotate.', 'uplinkportal' ) : __( 'conn_ref_xxx', 'uplinkportal' ) ); ?>"
					/>
				</label>
			</p>
			<p class="description" data-uplinkportal-endpoint-field 
			<?php
			if ( $hide_endpoint_ui ) :
				?>
				hidden<?php endif; ?>>
				<?php
				echo esc_html(
					$has_oauth_ref
						? __( 'Current value: ******** (masked). OAuth token lifecycle is delegated to your secure backend token vault.', 'uplinkportal' )
						: __( 'No reference stored. Add the opaque backend connection reference for delegated OAuth flows.', 'uplinkportal' )
				);
				?>
			</p>
			<div
				class="uplinkportal-module-required-fields"
				data-uplinkportal-profile-connection
				data-uplinkportal-endpoint-field
				<?php if ( ! $show_connection || $hide_endpoint_ui ) : ?>
					hidden
				<?php endif; ?>
				>
					<?php $this->render_profile_connection_fields_for_model( $model_id ); ?>
				</div>
			</div>
			<?php
	}

	/**
	 * Returns provider catalog module slug for one model id.
	 *
	 * @param string $model_id Model id.
	 * @return string
	 */
	private function provider_catalog_module_slug_for_model( string $model_id ): string {
		$model_id = sanitize_key( $model_id );
		if ( IntegrationModelRegistry::MODEL_LICENSING === $model_id || IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id ) {
			return 'database';
		}

		return $model_id;
	}

	/**
	 * Returns provider catalog profile key for one model id.
	 *
	 * @param string $model_id Model id.
	 * @return string
	 */
	private function provider_catalog_profile_for_model( string $model_id ): string {
		$model_id = sanitize_key( $model_id );
		if ( IntegrationModelRegistry::MODEL_LICENSING === $model_id ) {
			return 'licensing';
		}

		if ( IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id ) {
			return 'file_storage';
		}

		return '';
	}

	/**
	 * Filters provider catalog rows by profile key.
	 *
	 * Profile-specific entries are returned plus generic entries (no profile).
	 *
	 * @param array<int,array<string,mixed>> $catalog Provider rows.
	 * @param string                         $profile_key Profile key.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_provider_catalog_by_profile( array $catalog, string $profile_key ): array {
		$profile_key = sanitize_key( $profile_key );
		if ( '' === $profile_key ) {
			return $catalog;
		}

		$filtered = array();
		foreach ( $catalog as $provider ) {
			if ( ! is_array( $provider ) ) {
				continue;
			}

			$provider_profile = isset( $provider['profile'] ) ? sanitize_key( (string) $provider['profile'] ) : '';
			if ( '' !== $provider_profile && $profile_key !== $provider_profile ) {
				continue;
			}

			$filtered[] = $provider;
		}

		return $filtered;
	}

	/**
	 * Indexes provider catalog rows by provider id.
	 *
	 * @param array<int,array<string,mixed>> $catalog Provider catalog rows.
	 * @return array<string,array<string,mixed>>
	 */
	private function index_provider_catalog_by_id( array $catalog ): array {
		$indexed = array();

		foreach ( $catalog as $provider ) {
			if ( ! is_array( $provider ) ) {
				continue;
			}

			$provider_key = isset( $provider['id'] ) ? sanitize_key( (string) $provider['id'] ) : '';
			if ( '' === $provider_key ) {
				continue;
			}

			$indexed[ $provider_key ] = $provider;
		}

		return $indexed;
	}

	/**
	 * Returns locked-provider helper text.
	 *
	 * @return string
	 */
	private function get_locked_provider_helper_text(): string {
		return __( "This provider requires an active add-on. Your profile will remain Draft and automations can't be enabled.", 'uplinkportal' );
	}

	/**
	 * Renders connection fields under profile card for one model.
	 *
	 * @param string $model_id Model id.
	 * @return void
	 */
	private function render_profile_connection_fields_for_model( string $model_id ): void {
		$section = IntegrationModelRegistry::validation_section_for_model( $model_id );
		if ( '' === $section ) {
			return;
		}

		$profile      = $this->options->get_validation_profile( $section );
		$field_base   = $this->options->key() . '[validation_profiles][' . $section . ']';
		$input_suffix = sanitize_key( $section );
		?>
		<p class="description">
			<strong><?php echo esc_html__( 'Custom Provider Connection', 'uplinkportal' ); ?></strong>
			<?php echo esc_html__( 'Set these fields only when using a custom provider with API Key auth.', 'uplinkportal' ); ?>
		</p>
		<label for="uplinkportal-validation-base-url-<?php echo esc_attr( $input_suffix ); ?>">
			<?php echo esc_html__( 'API Base URL', 'uplinkportal' ); ?>
		</label>
		<input
			type="url"
			class="regular-text uplinkportal-validation-base-url"
			id="uplinkportal-validation-base-url-<?php echo esc_attr( $input_suffix ); ?>"
			name="<?php echo esc_attr( $field_base ); ?>[base_url]"
			value="<?php echo esc_attr( (string) $profile['base_url'] ); ?>"
			placeholder="<?php echo esc_attr__( 'https://api.example.com', 'uplinkportal' ); ?>"
		/>
		<p class="description"><?php echo esc_html__( 'Use HTTPS in production. Local non-HTTPS URLs are only intended for local/development hosts.', 'uplinkportal' ); ?></p>

		<label for="uplinkportal-validation-api-key-<?php echo esc_attr( $input_suffix ); ?>">
			<?php echo esc_html__( 'API Key', 'uplinkportal' ); ?>
		</label>
		<input
			type="password"
			class="regular-text uplinkportal-validation-api-key"
			id="uplinkportal-validation-api-key-<?php echo esc_attr( $input_suffix ); ?>"
			name="<?php echo esc_attr( $field_base ); ?>[api_key]"
			value=""
			autocomplete="off"
			placeholder="<?php echo esc_attr__( 'Enter new API key', 'uplinkportal' ); ?>"
		/>
		<p class="description">
			<?php
			echo esc_html(
				'' !== (string) $profile['api_key']
					? __( 'A key is already stored. Leave blank to keep the existing key.', 'uplinkportal' )
					: __( 'No API key stored yet.', 'uplinkportal' )
			);
			?>
		</p>

		<?php $allow_query_value = ! empty( $profile['allow_query_api_key'] ) ? '1' : '0'; ?>
		<input type="hidden" name="<?php echo esc_attr( $field_base ); ?>[allow_query_api_key]" value="<?php echo esc_attr( $allow_query_value ); ?>" data-uplinkportal-validation-allow-query-hidden />
		<label>
			<input
				type="checkbox"
				class="uplinkportal-validation-allow-query"
				value="1"
				<?php checked( ! empty( $profile['allow_query_api_key'] ) ); ?>
			/>
			<?php echo esc_html__( 'Allow api_key query parameter for this section (not recommended)', 'uplinkportal' ); ?>
		</label>

		<button
			type="button"
			class="button button-secondary uplinkportal-test-connection"
			data-section="<?php echo esc_attr( $section ); ?>"
		>
			<?php echo esc_html__( 'Test Connection', 'uplinkportal' ); ?>
		</button>
		<div class="uplinkportal-validation-result" data-section="<?php echo esc_attr( $section ); ?>" aria-live="polite"></div>
		<?php
	}

	/**
	 * Normalizes profile auth type into UI mode keys.
	 *
	 * @param string $auth_type Raw auth type.
	 * @return string
	 */
	private function normalize_profile_auth_type_for_ui( string $auth_type ): string {
		$auth_type = sanitize_key( $auth_type );
		if ( 'hybrid' === $auth_type ) {
			return 'hybrid';
		}
		if ( false !== strpos( $auth_type, 'oauth' ) ) {
			return 'oauth';
		}

		return 'api_key';
	}

	/**
	 * Renders automation recipe rows scoped to models.
	 *
	 * @param array<int,string> $model_ids Model ids.
	 * @return void
	 */
	private function render_module_automation_recipes_for_models( array $model_ids ): void {
		$model_ids        = array_values( array_unique( array_map( 'sanitize_key', $model_ids ) ) );
		$templates        = AutomationRecipeCatalog::templates();
		$recipe_state     = $this->options->get_automation_recipe_profiles();
		$profiles         = $this->options->get_integration_profiles();
		$field_base       = $this->options->key() . '[automation_recipes]';
		$recipe_templates = array();
		$locked_models    = array();
		$context_client   = $this->modules_entitlement_context_client_id();

		foreach ( $model_ids as $model_id ) {
			$model_id        = sanitize_key( $model_id );
			$profile         = isset( $profiles[ $model_id ] ) && is_array( $profiles[ $model_id ] ) ? $profiles[ $model_id ] : array();
			$provider        = isset( $profile['provider'] ) ? sanitize_key( (string) $profile['provider'] ) : '';
			$provider_module = $this->provider_catalog_module_slug_for_model( $model_id );
			if ( '' === $provider || 'custom' === $provider ) {
				continue;
			}

			if ( $this->provider_catalog_service->is_provider_locked( $provider_module, $provider, $context_client > 0 ? $context_client : null ) ) {
				$locked_models[] = $model_id;
			}
		}
		$locked_models = array_values( array_unique( $locked_models ) );

		foreach ( $templates as $recipe_id => $recipe ) {
			$model_id = isset( $recipe['model'] ) ? sanitize_key( (string) $recipe['model'] ) : '';
			if ( in_array( $model_id, $model_ids, true ) ) {
				$recipe_templates[ $recipe_id ] = $recipe;
			}
		}

		if ( empty( $recipe_templates ) ) {
			echo '<p class="description">' . esc_html__( 'No automation recipes are mapped to this module yet.', 'uplinkportal' ) . '</p>';
			return;
		}

		foreach ( $model_ids as $model_id ) {
			$model_id = sanitize_key( $model_id );
			?>
			<div class="notice notice-warning inline uplinkportal-inline-callout" data-uplinkportal-automation-lock-callout="<?php echo esc_attr( $model_id ); ?>" 
			<?php
			if ( ! in_array( $model_id, $locked_models, true ) ) :
				?>
				hidden<?php endif; ?>>
				<p><?php echo esc_html__( "Provider is locked. Profile remains Draft and automations can't be enabled.", 'uplinkportal' ); ?></p>
			</div>
			<?php
		}

		ModuleProfilesAutomationUi::render_automation_recipes(
			$recipe_templates,
			$recipe_state,
			$field_base,
			function ( string $recipe_id ): array {
				return $this->options->get_automation_recipe_revisions( $recipe_id, 8 );
			},
			$locked_models
		);
	}

	/**
	 * Renders clients planning tab content.
	 *
	 * @return void
	 */
	private function render_clients_tab(): void {
		$module_labels  = $this->get_template_module_labels();
		$clients        = $this->get_client_workspace_users();
		$plan_templates = $this->get_plan_templates();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status from query args.
		$saved = isset( $_GET['uplinkportal_clients_saved'] ) ? absint( (string) $_GET['uplinkportal_clients_saved'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status from query args.
		$entitlements_saved = isset( $_GET['uplinkportal_entitlements_saved'] ) ? absint( (string) $_GET['uplinkportal_entitlements_saved'] ) : 0;

		$matrix_rows = array();
		foreach ( $clients as $client_user ) {
			$client_id                 = absint( $client_user->ID );
			$matrix_rows[ $client_id ] = array(
				'plan'      => $this->get_client_plan( $client_id ),
				'overrides' => $this->get_client_override_data( $client_id, array_keys( $module_labels ) ),
				'identity'  => $this->client_access_store->get_identity_mapping( $client_id ),
			);
		}

		$this->config_clients_ui->render(
			$clients,
			$module_labels,
			$plan_templates,
			$matrix_rows,
			$saved,
			$entitlements_saved
		);
	}
	/**
	 * Renders client entitlement management panel.
	 *
	 * @param array<int,\WP_User> $clients Client users.
	 * @return void
	 */
	private function render_client_entitlements_panel( array $clients ): void {
		$selected_client_id = $this->selected_client_id_from_request( $clients, 'entitlements_client' );
		if ( $selected_client_id <= 0 ) {
			return;
		}

		$catalog      = $this->entitlement_catalog_rows();
		$entitlements = $this->entitlement_service->get_client_entitlements( $selected_client_id );
		$indexed      = array();

		foreach ( $entitlements as $entitlement ) {
			if ( ! is_array( $entitlement ) ) {
				continue;
			}
			$module_slug = isset( $entitlement['module_slug'] ) ? sanitize_key( (string) $entitlement['module_slug'] ) : '';
			$provider_id = isset( $entitlement['provider_id'] ) ? sanitize_key( (string) $entitlement['provider_id'] ) : '';
			if ( '' === $module_slug || '' === $provider_id ) {
				continue;
			}

			$indexed[ $module_slug . ':' . $provider_id ] = $entitlement;
		}
		?>
		<hr />
		<h2><?php echo esc_html__( 'Client Entitlements', 'uplinkportal' ); ?></h2>
		<p><?php echo esc_html__( 'Set module/provider add-on entitlements per client. Billing integrations can sync this automatically (future). Manual overrides are available here.', 'uplinkportal' ); ?></p>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="uplinkportal-inline-action">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SETTINGS_SLUG ); ?>" />
			<input type="hidden" name="tab" value="clients" />
			<label for="uplinkportal-entitlements-client"><strong><?php echo esc_html__( 'Client', 'uplinkportal' ); ?></strong></label>
			<select id="uplinkportal-entitlements-client" name="entitlements_client">
				<?php foreach ( $clients as $client_user ) : ?>
					<?php $client_id = absint( $client_user->ID ); ?>
					<option value="<?php echo esc_attr( (string) $client_id ); ?>" <?php selected( $selected_client_id, $client_id ); ?>>
						<?php echo esc_html( $client_user->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Switch Client', 'uplinkportal' ), 'secondary', '', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'uplinkportal_save_client_entitlements', 'uplinkportal_client_entitlements_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_save_client_entitlements" />
			<input type="hidden" name="entitlements_client_id" value="<?php echo esc_attr( (string) $selected_client_id ); ?>" />

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Module', 'uplinkportal' ); ?></th>
						<th><?php echo esc_html__( 'Provider', 'uplinkportal' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'uplinkportal' ); ?></th>
						<th><?php echo esc_html__( 'Grace Until (UTC)', 'uplinkportal' ); ?></th>
						<th><?php echo esc_html__( 'Subscription Ref', 'uplinkportal' ); ?></th>
						<th><?php echo esc_html__( 'Notes', 'uplinkportal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $catalog as $row ) : ?>
						<?php
						$module_slug = isset( $row['module_slug'] ) ? sanitize_key( (string) $row['module_slug'] ) : '';
						$provider_id = isset( $row['provider_id'] ) ? sanitize_key( (string) $row['provider_id'] ) : '';
						$row_key     = $module_slug . ':' . $provider_id;
						$current     = isset( $indexed[ $row_key ] ) && is_array( $indexed[ $row_key ] ) ? $indexed[ $row_key ] : array();
						$status      = isset( $current['status'] ) ? sanitize_key( (string) $current['status'] ) : 'none';
						$grace_until = isset( $current['grace_until'] ) ? sanitize_text_field( (string) $current['grace_until'] ) : '';
						$grace_local = '';
						if ( '' !== $grace_until ) {
							$grace_local = gmdate( 'Y-m-d\TH:i', strtotime( $grace_until ) );
						}
						$subscription_ref = isset( $current['subscription_ref'] ) ? sanitize_text_field( (string) $current['subscription_ref'] ) : '';
						$notes            = isset( $current['notes'] ) ? sanitize_textarea_field( (string) $current['notes'] ) : '';
						?>
						<tr>
							<td><code><?php echo esc_html( $module_slug ); ?></code></td>
							<td><?php echo esc_html( isset( $row['label'] ) ? (string) $row['label'] : $provider_id ); ?> <code>(<?php echo esc_html( $provider_id ); ?>)</code></td>
							<td>
								<select name="client_entitlements[<?php echo esc_attr( $module_slug ); ?>][<?php echo esc_attr( $provider_id ); ?>][status]">
									<option value="none" <?php selected( 'none', $status ); ?>><?php echo esc_html__( 'Not Entitled (Locked)', 'uplinkportal' ); ?></option>
									<option value="active" <?php selected( 'active', $status ); ?>><?php echo esc_html__( 'Active', 'uplinkportal' ); ?></option>
									<option value="grace" <?php selected( 'grace', $status ); ?>><?php echo esc_html__( 'Grace', 'uplinkportal' ); ?></option>
									<option value="suspended" <?php selected( 'suspended', $status ); ?>><?php echo esc_html__( 'Suspended', 'uplinkportal' ); ?></option>
									<option value="canceled" <?php selected( 'canceled', $status ); ?>><?php echo esc_html__( 'Canceled', 'uplinkportal' ); ?></option>
								</select>
							</td>
							<td>
								<input type="datetime-local" name="client_entitlements[<?php echo esc_attr( $module_slug ); ?>][<?php echo esc_attr( $provider_id ); ?>][grace_until]" value="<?php echo esc_attr( $grace_local ); ?>" />
							</td>
							<td>
								<input type="text" class="regular-text" name="client_entitlements[<?php echo esc_attr( $module_slug ); ?>][<?php echo esc_attr( $provider_id ); ?>][subscription_ref]" value="<?php echo esc_attr( $subscription_ref ); ?>" placeholder="<?php echo esc_attr__( 'optional reference', 'uplinkportal' ); ?>" />
							</td>
							<td>
								<input type="text" class="regular-text" name="client_entitlements[<?php echo esc_attr( $module_slug ); ?>][<?php echo esc_attr( $provider_id ); ?>][notes]" value="<?php echo esc_attr( $notes ); ?>" placeholder="<?php echo esc_attr__( 'optional notes', 'uplinkportal' ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php echo esc_html__( 'Grace defaults to 7 days when status is set to Grace and no date is supplied.', 'uplinkportal' ); ?></p>
			<?php submit_button( __( 'Save Client Entitlements', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Handles client entitlements save action.
	 *
	 * @return void
	 */
	public function handle_client_entitlements_save_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to update client entitlements.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_save_client_entitlements', 'uplinkportal_client_entitlements_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$client_id = isset( $_POST['entitlements_client_id'] ) ? absint( wp_unslash( (string) $_POST['entitlements_client_id'] ) ) : 0;
		if ( $client_id <= 0 || ! $this->is_client_workspace_user( $client_id ) ) {
			wp_die( esc_html__( 'Select a valid client to update entitlements.', 'uplinkportal' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$submitted = isset( $_POST['client_entitlements'] ) && is_array( $_POST['client_entitlements'] ) ? wp_unslash( $_POST['client_entitlements'] ) : array();
		$catalog   = $this->entitlement_catalog_rows();

		foreach ( $catalog as $row ) {
			$module_slug = isset( $row['module_slug'] ) ? sanitize_key( (string) $row['module_slug'] ) : '';
			$provider_id = isset( $row['provider_id'] ) ? sanitize_key( (string) $row['provider_id'] ) : '';
			if ( '' === $module_slug || '' === $provider_id ) {
				continue;
			}

			$entry  = isset( $submitted[ $module_slug ][ $provider_id ] ) && is_array( $submitted[ $module_slug ][ $provider_id ] )
				? $submitted[ $module_slug ][ $provider_id ]
				: array();
			$status = isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : 'none';
			if ( 'none' === $status ) {
				$this->entitlement_service->delete_entitlement( $client_id, $module_slug, $provider_id );
				continue;
			}

			$grace_until = isset( $entry['grace_until'] ) ? sanitize_text_field( (string) $entry['grace_until'] ) : '';
			if ( EntitlementService::STATUS_GRACE === $status && '' === $grace_until ) {
				$grace_until = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days', time() ) );
			}

			$subscription_ref = isset( $entry['subscription_ref'] ) ? sanitize_text_field( (string) $entry['subscription_ref'] ) : '';
			$notes            = isset( $entry['notes'] ) ? sanitize_textarea_field( (string) $entry['notes'] ) : '';

			$this->entitlement_service->upsert_entitlement(
				$client_id,
				$module_slug,
				$provider_id,
				$status,
				'' !== $grace_until ? $grace_until : null,
				'' !== $subscription_ref ? $subscription_ref : null,
				'' !== $notes ? $notes : null
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                            => self::SETTINGS_SLUG,
					'tab'                             => 'clients',
					'entitlements_client'             => $client_id,
					'uplinkportal_entitlements_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Returns entitlement catalog rows for admin editing.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function entitlement_catalog_rows(): array {
		if ( null !== $this->entitlement_catalog_rows_cache ) {
			return $this->entitlement_catalog_rows_cache;
		}

		$rows = array();
		foreach ( array( 'database', 'finance', 'it_support', 'projects' ) as $module_slug ) {
			$providers = $this->provider_catalog_service->get_providers_for_module( $module_slug );
			foreach ( $providers as $provider ) {
				if ( ! is_array( $provider ) ) {
					continue;
				}

				$provider_id = isset( $provider['id'] ) ? sanitize_key( (string) $provider['id'] ) : '';
				if ( '' === $provider_id || 'custom' === $provider_id ) {
					continue;
				}

				$rows[ $module_slug . ':' . $provider_id ] = array(
					'module_slug' => $module_slug,
					'provider_id' => $provider_id,
					'label'       => isset( $provider['label'] ) ? sanitize_text_field( (string) $provider['label'] ) : $provider_id,
				);
			}
		}

		ksort( $rows );
		$this->entitlement_catalog_rows_cache = array_values( $rows );
		return $this->entitlement_catalog_rows_cache;
	}

	/**
	 * Renders defaults customization tab.
	 *
	 * @return void
	 */
	private function render_customization_tab(): void {
		$defaults              = $this->get_customization_defaults();
		$logo_id               = isset( $defaults['company_logo_id'] ) ? absint( $defaults['company_logo_id'] ) : 0;
		$logo_url              = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
		$logo_url              = is_string( $logo_url ) ? $logo_url : '';
		$smtp_plugin_available = $this->is_smtp_plugin_detected();
		$day_labels            = $this->get_operating_day_short_labels();
		$time_options          = $this->get_operating_time_options();
		$state_options         = $this->get_us_state_options();
		$is_country_us         = $this->is_us_country( (string) $defaults['office_address_country'] );
		$show_custom_smtp      = 'custom_smtp' === (string) $defaults['email_delivery_method'];
		$operating_schedule    = isset( $defaults['operating_schedule'] ) && is_array( $defaults['operating_schedule'] ) ? $defaults['operating_schedule'] : $this->get_default_operating_schedule();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status from query args.
		$status = isset( $_GET['uplinkportal_customization'] ) ? sanitize_key( (string) $_GET['uplinkportal_customization'] ) : '';

		$this->config_company_info_ui->render(
			$defaults,
			$logo_url,
			$status,
			$state_options,
			$time_options,
			$day_labels,
			$operating_schedule,
			$is_country_us,
			$smtp_plugin_available,
			$show_custom_smtp
		);
	}

	/**
	 * Renders client portal preview tab.
	 *
	 * @return void
	 */
	private function render_client_portal_preview_tab(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_PREVIEW_PORTAL ) ) {
			wp_die( esc_html__( 'You do not have permission to preview the client portal.', 'uplinkportal' ) );
		}

		$defaults    = $this->get_customization_defaults();
		$preview_url = add_query_arg(
			array(
				'uplinkportal_preview' => '1',
				'uplinkportal_nonce'   => wp_create_nonce( 'uplinkportal_preview' ),
			),
			home_url( '/' )
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status from query args.
		$status = isset( $_GET['uplinkportal_customization'] ) ? sanitize_key( (string) $_GET['uplinkportal_customization'] ) : '';

		$portal_roles = array(
			'uplinkportal_client_owner'           => __( 'Client Owner', 'uplinkportal' ),
			'uplinkportal_client_manager'         => __( 'Client Manager', 'uplinkportal' ),
			'uplinkportal_client_tech'            => __( 'Client Tech', 'uplinkportal' ),
			'uplinkportal_client_project_manager' => __( 'Client Project Manager', 'uplinkportal' ),
			'uplinkportal_client_finance'         => __( 'Client Finance', 'uplinkportal' ),
			'uplinkportal_client_user'            => __( 'Client User', 'uplinkportal' ),
		);

		$this->config_portal_preview_ui->render( $defaults, $preview_url, $status, $portal_roles );
	}

	/**
	 * Renders the Agreements & Records page.
	 *
	 * @return void
	 */
	public function render_agreements_records_page(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'uplinkportal' ) );
		}

		$folders       = $this->get_agreements_folders();
		$base_redirect = add_query_arg(
			array( 'page' => self::AGREEMENTS_RECORDS_SLUG ),
			admin_url( 'admin.php' )
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status query args.
		$doc_status = isset( $_GET['uplinkportal_docs'] ) ? sanitize_key( (string) $_GET['uplinkportal_docs'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status query args.
		$doc_error = isset( $_GET['uplinkportal_docs_error'] ) ? sanitize_key( (string) $_GET['uplinkportal_docs_error'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status query args.
		$doc_removed = isset( $_GET['uplinkportal_docs_removed'] ) ? absint( (string) $_GET['uplinkportal_docs_removed'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status query args.
		$doc_retained = isset( $_GET['uplinkportal_docs_retained'] ) ? absint( (string) $_GET['uplinkportal_docs_retained'] ) : 0;
		?>
		<div class="wrap uplinkportal-admin msp-admin">
			<?php
			$this->agreements_records_ui->render(
				$folders,
				$base_redirect,
				$this->get_configuration_tab_url( 'backend_database' ),
				$this->get_configuration_tab_url( 'modules' ),
				$this->get_service_catalog_url(),
				$doc_status,
				$doc_error,
				$doc_removed,
				$doc_retained
			);
			?>
		</div>
		<?php
	}

	/**
	 * Renders the Service Catalog page.
	 *
	 * @return void
	 */
	public function render_service_catalog_page(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'uplinkportal' ) );
		}

		$templates = $this->get_plan_templates();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status from query args.
		$saved = isset( $_GET['uplinkportal_catalog_saved'] ) ? absint( (string) $_GET['uplinkportal_catalog_saved'] ) : 0;
		?>
		<div class="wrap uplinkportal-admin msp-admin">
			<?php
			$this->service_catalog_ui->render(
				$templates,
				$saved,
				$this->get_configuration_tab_url( 'modules' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Returns available tabs for configuration page.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function get_configuration_tabs(): array {
		return array(
			'clients'          => array(
				'label'       => __( 'Client Access', 'uplinkportal' ),
				'description' => __( 'Client plans and portal access rules', 'uplinkportal' ),
				'capability'  => self::CAPABILITY_MSP_MANAGE,
			),
			'modules'          => array(
				'label'       => __( 'Modules', 'uplinkportal' ),
				'description' => __( 'Enable services and configure module settings', 'uplinkportal' ),
				'capability'  => self::CAPABILITY_MSP_MANAGE,
			),
			'backend_database' => array(
				'label'       => __( 'Security', 'uplinkportal' ),
				'description' => __( 'Permissions, privacy, and compliance', 'uplinkportal' ),
				'capability'  => self::CAPABILITY_MSP_MANAGE,
			),
			'customization'    => array(
				'label'       => __( 'Company Info', 'uplinkportal' ),
				'description' => __( 'Brand, office profile, hours, and email', 'uplinkportal' ),
				'capability'  => self::CAPABILITY_MSP_MANAGE,
			),
			'portal_preview'   => array(
				'label'       => __( 'Portal Preview', 'uplinkportal' ),
				'description' => __( 'Preview client portal changes before save', 'uplinkportal' ),
				'capability'  => self::CAPABILITY_MSP_PREVIEW_PORTAL,
			),
		);
	}

	/**
	 * Returns admin URL for one configuration tab.
	 *
	 * @param string $tab_key Tab key.
	 * @return string
	 */
	private function get_configuration_tab_url( string $tab_key ): string {
		$tab_key = sanitize_key( $tab_key );
		$tabs    = $this->get_configuration_tabs();
		if ( ! isset( $tabs[ $tab_key ] ) ) {
			$tab_key = 'clients';
		}

		return add_query_arg(
			array(
				'page' => self::SETTINGS_SLUG,
				'tab'  => sanitize_key( $tab_key ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns canonical URL for Modules > Database route.
	 *
	 * @return string
	 */
	private function get_modules_database_licensing_url(): string {
		return add_query_arg(
			array(
				'page'   => self::SETTINGS_SLUG,
				'tab'    => 'modules',
				'module' => 'database',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns agreements and records URL.
	 *
	 * @return string
	 */
	private function get_agreements_records_url(): string {
		return add_query_arg(
			array(
				'page' => self::AGREEMENTS_RECORDS_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns service catalog URL.
	 *
	 * @return string
	 */
	private function get_service_catalog_url(): string {
		return add_query_arg(
			array(
				'page' => self::SERVICE_CATALOG_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns tools and diagnostics URL.
	 *
	 * @return string
	 */
	private function get_tools_diagnostics_url(): string {
		return add_query_arg(
			array(
				'page' => self::TOOLS_DIAGNOSTICS_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns operations dashboard URL.
	 *
	 * Kept as a portfolio-named helper for compatibility with existing UI calls.
	 *
	 * @return string
	 */
	private function get_client_portfolio_page_url(): string {
		return add_query_arg(
			array(
				'page' => self::MENU_SLUG,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns Settings API section/field definitions by tab.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_settings_api_tab_definitions(): array {
		return array(
			'backend_database' => array(
				'page_slug'        => self::SETTINGS_API_BACKEND_PAGE,
				'section_id'       => 'uplinkportal_backend',
				'section_title'    => __( 'Security & Compliance', 'uplinkportal' ),
				'section_callback' => 'render_backend_section_note',
				'submit_label'     => __( 'Save Security Settings', 'uplinkportal' ),
				'fields'           => array(
					array(
						'id'       => 'policy_bundle',
						'title'    => __( 'Compliance Policy Bundle', 'uplinkportal' ),
						'callback' => 'render_policy_bundle_field',
					),
					array(
						'id'       => 'model_policies',
						'title'    => __( 'Model RBAC Policies', 'uplinkportal' ),
						'callback' => 'render_model_policies_field',
					),
					array(
						'id'       => 'model_legal_basis',
						'title'    => __( 'Model Legal Basis (GDPR)', 'uplinkportal' ),
						'callback' => 'render_model_legal_basis_field',
					),
					array(
						'id'       => 'data_minimization',
						'title'    => __( 'Data Minimization', 'uplinkportal' ),
						'callback' => 'render_data_minimization_field',
					),
					array(
						'id'       => 'tenant_boundary',
						'title'    => __( 'Tenant Boundary Controls', 'uplinkportal' ),
						'callback' => 'render_tenant_boundary_field',
					),
					array(
						'id'       => 'monitoring_routing',
						'title'    => __( 'Monitoring & Alert Routing', 'uplinkportal' ),
						'callback' => 'render_monitoring_routing_field',
					),
					array(
						'id'       => 'evidence_trail',
						'title'    => __( 'Evidence Trail (Tamper-Evident)', 'uplinkportal' ),
						'callback' => 'render_evidence_trail_field',
					),
					array(
						'id'       => 'data_protection',
						'title'    => __( 'Data Protection At Rest', 'uplinkportal' ),
						'callback' => 'render_data_protection_field',
					),
				),
			),
			'modules'          => array(
				'page_slug'        => self::SETTINGS_API_MODULES_PAGE,
				'section_id'       => 'uplinkportal_modules',
				'section_title'    => __( 'Module Catalog', 'uplinkportal' ),
				'section_callback' => 'render_modules_section_note',
				'submit_label'     => __( 'Save Module & Integration Settings', 'uplinkportal' ),
				'fields'           => array(
					array(
						'id'       => 'service_modules',
						'title'    => __( 'Services & Integrations', 'uplinkportal' ),
						'callback' => 'render_modules_catalog_field',
					),
				),
			),
		);
	}

	/**
	 * Returns dashicon class for configuration tab.
	 *
	 * @param string $tab_key Tab key.
	 * @return string
	 */
	private function get_configuration_tab_icon( string $tab_key ): string {
		switch ( sanitize_key( $tab_key ) ) {
			case 'clients':
				return 'dashicons-groups';
			case 'modules':
				return 'dashicons-screenoptions';
			case 'backend_database':
				return 'dashicons-shield-alt';
			case 'customization':
				return 'dashicons-admin-customizer';
			case 'portal_preview':
				return 'dashicons-layout';
		}

		return 'dashicons-admin-generic';
	}

	/**
	 * Returns active configuration tab.
	 *
	 * @return string
	 */
	private function get_active_configuration_tab(): string {
		$tabs     = $this->get_configuration_tabs();
		$tab_keys = array_keys( $tabs );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection via query arg; no state-changing action.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'clients';

		if ( ! in_array( $active_tab, $tab_keys, true ) ) {
			return 'clients';
		}

		if ( ! current_user_can( $tabs[ $active_tab ]['capability'] ) ) {
			return 'clients';
		}

		return $active_tab;
	}

	/**
	 * Validates REST nonce for cookie-authenticated admin requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	private function is_rest_nonce_valid( WP_REST_Request $request ): bool {
		$enforce_nonce = (bool) apply_filters( 'uplinkportal_enforce_rest_nonce', true );
		if ( ! $enforce_nonce ) {
			return true;
		}

		if ( '' !== (string) $request->get_header( 'Authorization' ) ) {
			return true;
		}

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( '' === $nonce ) {
			return false;
		}

		return false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Verifies admin-post nonce for state-changing actions.
	 *
	 * This central helper keeps nonce enforcement consistent for existing and
	 * future admin-post handlers.
	 *
	 * @param string $action Nonce action key.
	 * @param string $field Nonce field key.
	 * @return void
	 */
	private function verify_admin_post_nonce( string $action, string $field ): void {
		check_admin_referer( $action, $field );
	}

	/**
	 * Handles client access matrix save.
	 *
	 * @return void
	 */
	public function handle_client_matrix_save_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to update client access.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_save_client_matrix', 'uplinkportal_client_matrix_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$posted_plans = isset( $_POST['client_plans'] ) && is_array( $_POST['client_plans'] ) ? wp_unslash( $_POST['client_plans'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$posted_override_modes = isset( $_POST['client_override_mode'] ) && is_array( $_POST['client_override_mode'] ) ? wp_unslash( $_POST['client_override_mode'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$posted_override_modules = isset( $_POST['client_override_modules'] ) && is_array( $_POST['client_override_modules'] ) ? wp_unslash( $_POST['client_override_modules'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$posted_external_systems = isset( $_POST['client_external_system'] ) && is_array( $_POST['client_external_system'] ) ? wp_unslash( $_POST['client_external_system'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$posted_external_ids = isset( $_POST['client_external_id'] ) && is_array( $_POST['client_external_id'] ) ? wp_unslash( $_POST['client_external_id'] ) : array();

		$allowed_plan_keys   = array_keys( $this->get_plan_templates() );
		$allowed_module_keys = array_keys( $this->get_template_module_labels() );
		$default_plan_key    = isset( $allowed_plan_keys[0] ) ? (string) $allowed_plan_keys[0] : 'starter';
		$all_client_ids      = array_unique(
			array_merge(
				array_map( 'absint', array_keys( $posted_plans ) ),
				array_map( 'absint', array_keys( $posted_override_modules ) ),
				array_map( 'absint', array_keys( $posted_override_modes ) ),
				array_map( 'absint', array_keys( $posted_external_systems ) ),
				array_map( 'absint', array_keys( $posted_external_ids ) )
			)
		);

		foreach ( $all_client_ids as $client_id ) {
			if ( $client_id <= 0 || ! $this->is_client_workspace_user( $client_id ) ) {
				continue;
			}

			$plan_key = isset( $posted_plans[ (string) $client_id ] ) ? sanitize_key( (string) $posted_plans[ (string) $client_id ] ) : $default_plan_key;
			if ( ! in_array( $plan_key, $allowed_plan_keys, true ) ) {
				$plan_key = $default_plan_key;
			}
			$override_enabled = ! empty( $posted_override_modes[ (string) $client_id ] );
			$module_map       = isset( $posted_override_modules[ (string) $client_id ] ) && is_array( $posted_override_modules[ (string) $client_id ] )
				? $posted_override_modules[ (string) $client_id ]
				: array();
			$module_states    = array();

			foreach ( $allowed_module_keys as $module_key ) {
				if ( ! $this->is_service_module_enabled( $module_key ) ) {
					$module_states[ $module_key ] = 'disabled';
					continue;
				}

				if ( ! $override_enabled ) {
					$module_states[ $module_key ] = 'inherit';
					continue;
				}

				$module_states[ $module_key ] = ! empty( $module_map[ $module_key ] ) ? 'enabled' : 'disabled';
			}

			$external_system = isset( $posted_external_systems[ (string) $client_id ] ) ? sanitize_key( (string) $posted_external_systems[ (string) $client_id ] ) : 'wordpress_users';
			$external_id     = isset( $posted_external_ids[ (string) $client_id ] ) ? sanitize_text_field( (string) $posted_external_ids[ (string) $client_id ] ) : '';

			$this->client_access_store->save_client_access_profile(
				$client_id,
				$plan_key,
				$override_enabled,
				$module_states,
				$external_system,
				$external_id,
				get_current_user_id()
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => self::SETTINGS_SLUG,
					'tab'                        => 'clients',
					'uplinkportal_clients_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handles plan templates save from Service Catalog page.
	 *
	 * @return void
	 */
	public function handle_plan_templates_save_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to update plan templates.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_save_plan_templates', 'uplinkportal_plan_templates_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$posted_templates = isset( $_POST['plan_templates'] ) && is_array( $_POST['plan_templates'] ) ? wp_unslash( $_POST['plan_templates'] ) : array();
		$module_keys      = array_keys( $this->get_template_module_labels() );
		$icon_options     = $this->get_plan_icon_options();
		$templates        = array();

		foreach ( $posted_templates as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
				continue;
			}

			$key   = isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : '';
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$icon  = isset( $row['icon'] ) ? sanitize_key( (string) $row['icon'] ) : 'portfolio';
			$desc  = isset( $row['description'] ) ? sanitize_text_field( (string) $row['description'] ) : '';
			if ( '' === $key || '' === $label ) {
				continue;
			}

			if ( ! isset( $icon_options[ $icon ] ) ) {
				$icon = 'portfolio';
			}

			$modules = array();
			if ( isset( $row['modules'] ) && is_array( $row['modules'] ) ) {
				foreach ( $module_keys as $module_key ) {
					if ( ! $this->is_service_module_enabled( $module_key ) ) {
						continue;
					}
					if ( ! empty( $row['modules'][ $module_key ] ) ) {
						$modules[] = $module_key;
					}
				}
			}

			$templates[ $key ] = array(
				'label'       => $label,
				'description' => $desc,
				'icon'        => $icon,
				'modules'     => array_values( $modules ),
			);
		}

		if ( empty( $templates ) ) {
			$templates = $this->get_default_plan_templates();
		}

		update_option( self::PLAN_TEMPLATES_OPTION, $templates, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => self::SERVICE_CATALOG_SLUG,
					'uplinkportal_catalog_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handles customization defaults save.
	 *
	 * @return void
	 */
	public function handle_customization_save_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to update customization defaults.', 'uplinkportal' ) );
		}

		check_admin_referer( 'uplinkportal_save_customization_defaults', 'uplinkportal_customization_nonce' );

		$stored = get_option( self::ONBOARDING_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$section = isset( $_POST['customization_section'] ) ? sanitize_key( wp_unslash( (string) $_POST['customization_section'] ) ) : '';
		if ( '' === $section ) {
			$section = 'company_information';
		}

		$valid_sections = array( 'company_information', 'company_logo', 'operating_hours', 'email', 'portal_configuration' );
		if ( ! in_array( $section, $valid_sections, true ) ) {
			$section = 'company_information';
		}

		$redirect_tab = 'portal_configuration' === $section ? 'portal_preview' : 'customization';
		$saved_status = array(
			'company_information'  => 'saved_company_information',
			'company_logo'         => 'saved_company_logo',
			'operating_hours'      => 'saved_operating_hours',
			'email'                => 'saved_email',
			'portal_configuration' => 'saved_portal_configuration',
		);

		if ( in_array( $section, array( 'company_information' ), true ) ) {
			$stored['company_name']  = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['company_name'] ) ) : '';
			$stored['support_phone'] = isset( $_POST['support_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['support_phone'] ) ) : '';

			$office_street  = isset( $_POST['office_address_street'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['office_address_street'] ) ) : '';
			$office_city    = isset( $_POST['office_address_city'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['office_address_city'] ) ) : '';
			$office_country = isset( $_POST['office_address_country'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['office_address_country'] ) ) : 'US';
			$office_country = '' !== trim( $office_country ) ? trim( $office_country ) : 'US';
			$office_postal  = isset( $_POST['office_address_postal_code'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['office_address_postal_code'] ) ) : '';

			$office_state = '';
			if ( $this->is_us_country( $office_country ) ) {
				$submitted_state = isset( $_POST['office_address_state'] ) ? strtoupper( sanitize_key( wp_unslash( (string) $_POST['office_address_state'] ) ) ) : '';
				if ( array_key_exists( $submitted_state, $this->get_us_state_options() ) ) {
					$office_state = $submitted_state;
				}
			} else {
				$office_state = isset( $_POST['office_address_region'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['office_address_region'] ) ) : '';
			}

			$stored['office_address_street']      = $office_street;
			$stored['office_address_city']        = $office_city;
			$stored['office_address_state']       = $office_state;
			$stored['office_address_country']     = $office_country;
			$stored['office_address_postal_code'] = $office_postal;
		}

		if ( 'company_logo' === $section ) {
			$company_logo_id = isset( $_POST['company_logo_id'] ) ? absint( wp_unslash( (string) $_POST['company_logo_id'] ) ) : 0;
			if ( $company_logo_id > 0 ) {
				$logo_validation = $this->validate_logo_attachment( $company_logo_id );
				if ( true !== $logo_validation ) {
					$this->redirect_customization_with_status( (string) $logo_validation, 'customization' );
				}
			}
			$stored['company_logo_id'] = $company_logo_id;
		}

		if ( 'operating_hours' === $section ) {
			$raw_schedule = isset( $_POST['operating_schedule'] ) && is_array( $_POST['operating_schedule'] ) ? wp_unslash( $_POST['operating_schedule'] ) : array();
			$schedule     = $this->sanitize_operating_schedule( $raw_schedule );
			if ( is_wp_error( $schedule ) ) {
				$this->redirect_customization_with_status( (string) $schedule->get_error_code(), 'customization' );
			}
			$stored['operating_schedule'] = $schedule;
		}

		if ( 'email' === $section ) {
			$stored['support_email'] = isset( $_POST['support_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['support_email'] ) ) : '';

			$email_method = isset( $_POST['email_delivery_method'] ) ? sanitize_key( wp_unslash( (string) $_POST['email_delivery_method'] ) ) : 'wordpress_mail';
			$allowed_mail = array( 'wordpress_mail', 'custom_smtp' );
			if ( $this->is_smtp_plugin_detected() ) {
				$allowed_mail[] = 'smtp_plugin';
			}
			if ( ! in_array( $email_method, $allowed_mail, true ) ) {
				$this->redirect_customization_with_status( 'error_email_method', 'customization' );
			}
			$stored['email_delivery_method'] = $email_method;

			$stored['smtp_host']       = isset( $_POST['smtp_host'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['smtp_host'] ) ) : ( isset( $stored['smtp_host'] ) ? sanitize_text_field( (string) $stored['smtp_host'] ) : '' );
			$stored['smtp_port']       = isset( $_POST['smtp_port'] ) ? absint( wp_unslash( (string) $_POST['smtp_port'] ) ) : ( isset( $stored['smtp_port'] ) ? absint( $stored['smtp_port'] ) : 0 );
			$stored['smtp_username']   = isset( $_POST['smtp_username'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['smtp_username'] ) ) : ( isset( $stored['smtp_username'] ) ? sanitize_text_field( (string) $stored['smtp_username'] ) : '' );
			$stored['smtp_password']   = isset( $_POST['smtp_password'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['smtp_password'] ) ) : ( isset( $stored['smtp_password'] ) ? sanitize_text_field( (string) $stored['smtp_password'] ) : '' );
			$stored['smtp_from_email'] = isset( $_POST['smtp_from_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['smtp_from_email'] ) ) : ( isset( $stored['smtp_from_email'] ) ? sanitize_email( (string) $stored['smtp_from_email'] ) : '' );

			if (
				'custom_smtp' === $email_method
				&& (
					'' === $stored['smtp_host']
					|| $stored['smtp_port'] <= 0
					|| '' === $stored['smtp_username']
					|| '' === $stored['smtp_password']
					|| '' === $stored['smtp_from_email']
					|| ! is_email( $stored['smtp_from_email'] )
				)
			) {
				$this->redirect_customization_with_status( 'error_smtp_fields', 'customization' );
			}
		}

		if ( 'portal_configuration' === $section ) {
			$portal_access_policy = isset( $_POST['portal_access_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['portal_access_policy'] ) ) : 'wp_login_required';
			if ( ! in_array( $portal_access_policy, array( 'wp_login_required', 'role_restricted', 'invite_only' ), true ) ) {
				$portal_access_policy = 'wp_login_required';
			}

			$registration_policy = isset( $_POST['client_registration_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['client_registration_policy'] ) ) : 'invite_only';
			if ( ! in_array( $registration_policy, array( 'invite_only', 'admin_approval', 'open_registration' ), true ) ) {
				$registration_policy = 'invite_only';
			}

			$brand_color = isset( $_POST['brand_primary_color'] ) ? sanitize_hex_color( (string) wp_unslash( $_POST['brand_primary_color'] ) ) : '';
			$brand_color = is_string( $brand_color ) && '' !== $brand_color ? $brand_color : '#1d4ed8';

			$portal_payload = array(
				'portal_access_policy'       => $portal_access_policy,
				'client_registration_policy' => $registration_policy,
				'brand_primary_color'        => $brand_color,
				'portal_welcome_message'     => isset( $_POST['portal_welcome_message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['portal_welcome_message'] ) ) : '',
				'portal_footer_note'         => isset( $_POST['portal_footer_note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['portal_footer_note'] ) ) : '',
			);

			if ( ! $this->portal_customization_store->save_settings( $portal_payload ) ) {
				$this->redirect_customization_with_status( 'error_portal_storage', 'portal_preview' );
			}
		}

		update_option( self::ONBOARDING_OPTION, $stored, false );

		if ( in_array( $section, array( 'company_information', 'email' ), true ) ) {
			$this->mirror_company_info_to_wordpress_settings( $stored, $section );
		}

		$this->redirect_customization_with_status( $saved_status[ $section ], $redirect_tab );
	}

	/**
	 * Returns customization defaults with WordPress-derived fallbacks.
	 *
	 * @return array<string,mixed>
	 */
	private function get_customization_defaults(): array {
		$stored = get_option( self::ONBOARDING_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$private_portal = $this->portal_customization_store->read_settings();

		$office_street  = isset( $stored['office_address_street'] ) ? sanitize_text_field( (string) $stored['office_address_street'] ) : '';
		$office_city    = isset( $stored['office_address_city'] ) ? sanitize_text_field( (string) $stored['office_address_city'] ) : '';
		$office_state   = isset( $stored['office_address_state'] ) ? sanitize_text_field( (string) $stored['office_address_state'] ) : '';
		$office_country = isset( $stored['office_address_country'] ) ? sanitize_text_field( (string) $stored['office_address_country'] ) : 'US';
		$office_country = '' !== trim( $office_country ) ? trim( $office_country ) : 'US';
		$office_postal  = isset( $stored['office_address_postal_code'] ) ? sanitize_text_field( (string) $stored['office_address_postal_code'] ) : '';

		$operating_schedule = $this->get_default_operating_schedule();
		if ( isset( $stored['operating_schedule'] ) && is_array( $stored['operating_schedule'] ) ) {
			$operating_schedule = $this->normalize_operating_schedule( $stored['operating_schedule'] );
		}
		$operating_hours = $this->format_operating_schedule_summary( $operating_schedule );

		$email_method = isset( $stored['email_delivery_method'] ) ? sanitize_key( (string) $stored['email_delivery_method'] ) : 'wordpress_mail';
		$allowed_mail = array( 'wordpress_mail', 'custom_smtp' );
		if ( $this->is_smtp_plugin_detected() ) {
			$allowed_mail[] = 'smtp_plugin';
		}
		if ( ! in_array( $email_method, $allowed_mail, true ) ) {
			$email_method = 'wordpress_mail';
		}

		$brand_primary_color = isset( $private_portal['brand_primary_color'] ) && '' !== (string) $private_portal['brand_primary_color']
			? sanitize_hex_color( (string) $private_portal['brand_primary_color'] )
			: ( isset( $stored['brand_primary_color'] ) ? sanitize_hex_color( (string) $stored['brand_primary_color'] ) : '#1d4ed8' );
		if ( ! is_string( $brand_primary_color ) || '' === $brand_primary_color ) {
			$brand_primary_color = '#1d4ed8';
		}

		return array(
			'company_name'               => isset( $stored['company_name'] ) ? sanitize_text_field( (string) $stored['company_name'] ) : sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'company_logo_id'            => isset( $stored['company_logo_id'] ) ? absint( $stored['company_logo_id'] ) : 0,
			'support_email'              => isset( $stored['support_email'] ) ? sanitize_email( (string) $stored['support_email'] ) : sanitize_email( (string) get_option( 'admin_email', '' ) ),
			'support_phone'              => isset( $stored['support_phone'] ) ? sanitize_text_field( (string) $stored['support_phone'] ) : '',
			'office_address'             => $this->format_office_address( $office_street, $office_city, $office_state, $office_country, $office_postal ),
			'office_address_street'      => $office_street,
			'office_address_city'        => $office_city,
			'office_address_state'       => $office_state,
			'office_address_country'     => $office_country,
			'office_address_postal_code' => $office_postal,
			'operating_hours'            => $operating_hours,
			'operating_schedule'         => $operating_schedule,
			'portal_access_policy'       => isset( $private_portal['portal_access_policy'] ) && '' !== (string) $private_portal['portal_access_policy']
				? sanitize_key( (string) $private_portal['portal_access_policy'] )
				: 'wp_login_required',
			'client_registration_policy' => isset( $private_portal['client_registration_policy'] ) && '' !== (string) $private_portal['client_registration_policy']
				? sanitize_key( (string) $private_portal['client_registration_policy'] )
				: 'invite_only',
			'email_delivery_method'      => $email_method,
			'smtp_host'                  => isset( $stored['smtp_host'] ) ? sanitize_text_field( (string) $stored['smtp_host'] ) : '',
			'smtp_port'                  => isset( $stored['smtp_port'] ) ? absint( $stored['smtp_port'] ) : 587,
			'smtp_username'              => isset( $stored['smtp_username'] ) ? sanitize_text_field( (string) $stored['smtp_username'] ) : '',
			'smtp_password'              => isset( $stored['smtp_password'] ) ? sanitize_text_field( (string) $stored['smtp_password'] ) : '',
			'smtp_from_email'            => isset( $stored['smtp_from_email'] ) ? sanitize_email( (string) $stored['smtp_from_email'] ) : sanitize_email( (string) get_option( 'admin_email', '' ) ),
			'brand_primary_color'        => $brand_primary_color,
			'portal_welcome_message'     => isset( $private_portal['portal_welcome_message'] ) && '' !== trim( (string) $private_portal['portal_welcome_message'] )
				? sanitize_textarea_field( (string) $private_portal['portal_welcome_message'] )
				: ( isset( $stored['portal_welcome_message'] ) ? sanitize_textarea_field( (string) $stored['portal_welcome_message'] ) : $default_welcome_message ),
			'portal_footer_note'         => isset( $private_portal['portal_footer_note'] ) && '' !== trim( (string) $private_portal['portal_footer_note'] )
				? sanitize_textarea_field( (string) $private_portal['portal_footer_note'] )
				: ( isset( $stored['portal_footer_note'] ) ? sanitize_textarea_field( (string) $stored['portal_footer_note'] ) : '' ),
		);
	}

	/**
	 * Mirrors company profile fields into WordPress core settings.
	 *
	 * Source-of-truth policy:
	 * - UplinkPortal Company Info tab is authoritative for these values.
	 * - Selected values are mirrored to WordPress General Settings on save.
	 *
	 * @param array<string,mixed> $stored Stored customization payload.
	 * @param string              $section Saved section key.
	 * @return void
	 */
	private function mirror_company_info_to_wordpress_settings( array $stored, string $section ): void {
		$section = sanitize_key( $section );

		if ( 'company_information' === $section ) {
			$company_name = isset( $stored['company_name'] ) ? sanitize_text_field( (string) $stored['company_name'] ) : '';
			if ( '' !== $company_name ) {
				update_option( 'blogname', $company_name, false );
			}
		}

		if ( 'email' === $section ) {
			$support_email = isset( $stored['support_email'] ) ? sanitize_email( (string) $stored['support_email'] ) : '';
			if ( '' !== $support_email && is_email( $support_email ) ) {
				update_option( 'admin_email', $support_email, false );
			}
		}

		/**
		 * Fires after Company Info values are mirrored to WordPress settings.
		 *
		 * @param string              $section Saved section key.
		 * @param array<string,mixed> $stored Stored customization payload.
		 */
		do_action( 'uplinkportal_company_info_mirrored', $section, $stored );
	}

	/**
	 * Validates a selected company logo attachment.
	 *
	 * @param int $logo_id Attachment ID.
	 * @return true|string
	 */
	private function validate_logo_attachment( int $logo_id ) {
		if ( $logo_id <= 0 ) {
			return true;
		}

		$mime_type = (string) get_post_mime_type( $logo_id );
		if ( ! in_array( $mime_type, array( 'image/png', 'image/svg+xml' ), true ) ) {
			return 'error_logo_format';
		}

		$file_path = get_attached_file( $logo_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return 'error_logo_missing';
		}

		$file_size = filesize( $file_path );
		if ( false === $file_size || $file_size > 204800 ) {
			return 'error_logo_size';
		}

		$dimensions = wp_getimagesize( $file_path );
		if ( 'image/png' === $mime_type && ! is_array( $dimensions ) ) {
			return 'error_logo_dimensions';
		}
		if ( is_array( $dimensions ) && ( (int) $dimensions[0] > 200 || (int) $dimensions[1] > 200 ) ) {
			return 'error_logo_dimensions';
		}

		return true;
	}

	/**
	 * Redirects customization tab with status code.
	 *
	 * @param string $status Status key.
	 * @param string $tab    Destination tab key.
	 * @return void
	 */
	private function redirect_customization_with_status( string $status, string $tab = 'customization' ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => self::SETTINGS_SLUG,
					'tab'                        => sanitize_key( $tab ),
					'uplinkportal_customization' => sanitize_key( $status ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Returns whether country value should use US state list.
	 *
	 * @param string $country Country value.
	 * @return bool
	 */
	private function is_us_country( string $country ): bool {
		$country = strtoupper( trim( $country ) );
		return in_array( $country, array( 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA' ), true );
	}

	/**
	 * Returns supported US states.
	 *
	 * @return array<string,string>
	 */
	private function get_us_state_options(): array {
		return array(
			'AL' => __( 'Alabama', 'uplinkportal' ),
			'AK' => __( 'Alaska', 'uplinkportal' ),
			'AZ' => __( 'Arizona', 'uplinkportal' ),
			'AR' => __( 'Arkansas', 'uplinkportal' ),
			'CA' => __( 'California', 'uplinkportal' ),
			'CO' => __( 'Colorado', 'uplinkportal' ),
			'CT' => __( 'Connecticut', 'uplinkportal' ),
			'DE' => __( 'Delaware', 'uplinkportal' ),
			'FL' => __( 'Florida', 'uplinkportal' ),
			'GA' => __( 'Georgia', 'uplinkportal' ),
			'HI' => __( 'Hawaii', 'uplinkportal' ),
			'ID' => __( 'Idaho', 'uplinkportal' ),
			'IL' => __( 'Illinois', 'uplinkportal' ),
			'IN' => __( 'Indiana', 'uplinkportal' ),
			'IA' => __( 'Iowa', 'uplinkportal' ),
			'KS' => __( 'Kansas', 'uplinkportal' ),
			'KY' => __( 'Kentucky', 'uplinkportal' ),
			'LA' => __( 'Louisiana', 'uplinkportal' ),
			'ME' => __( 'Maine', 'uplinkportal' ),
			'MD' => __( 'Maryland', 'uplinkportal' ),
			'MA' => __( 'Massachusetts', 'uplinkportal' ),
			'MI' => __( 'Michigan', 'uplinkportal' ),
			'MN' => __( 'Minnesota', 'uplinkportal' ),
			'MS' => __( 'Mississippi', 'uplinkportal' ),
			'MO' => __( 'Missouri', 'uplinkportal' ),
			'MT' => __( 'Montana', 'uplinkportal' ),
			'NE' => __( 'Nebraska', 'uplinkportal' ),
			'NV' => __( 'Nevada', 'uplinkportal' ),
			'NH' => __( 'New Hampshire', 'uplinkportal' ),
			'NJ' => __( 'New Jersey', 'uplinkportal' ),
			'NM' => __( 'New Mexico', 'uplinkportal' ),
			'NY' => __( 'New York', 'uplinkportal' ),
			'NC' => __( 'North Carolina', 'uplinkportal' ),
			'ND' => __( 'North Dakota', 'uplinkportal' ),
			'OH' => __( 'Ohio', 'uplinkportal' ),
			'OK' => __( 'Oklahoma', 'uplinkportal' ),
			'OR' => __( 'Oregon', 'uplinkportal' ),
			'PA' => __( 'Pennsylvania', 'uplinkportal' ),
			'RI' => __( 'Rhode Island', 'uplinkportal' ),
			'SC' => __( 'South Carolina', 'uplinkportal' ),
			'SD' => __( 'South Dakota', 'uplinkportal' ),
			'TN' => __( 'Tennessee', 'uplinkportal' ),
			'TX' => __( 'Texas', 'uplinkportal' ),
			'UT' => __( 'Utah', 'uplinkportal' ),
			'VT' => __( 'Vermont', 'uplinkportal' ),
			'VA' => __( 'Virginia', 'uplinkportal' ),
			'WA' => __( 'Washington', 'uplinkportal' ),
			'WV' => __( 'West Virginia', 'uplinkportal' ),
			'WI' => __( 'Wisconsin', 'uplinkportal' ),
			'WY' => __( 'Wyoming', 'uplinkportal' ),
			'DC' => __( 'District of Columbia', 'uplinkportal' ),
		);
	}

	/**
	 * Returns display labels for operating days.
	 *
	 * @return array<string,string>
	 */
	private function get_operating_day_labels(): array {
		return array_combine(
			self::OPERATING_DAY_KEYS,
			array(
				__( 'Monday', 'uplinkportal' ),
				__( 'Tuesday', 'uplinkportal' ),
				__( 'Wednesday', 'uplinkportal' ),
				__( 'Thursday', 'uplinkportal' ),
				__( 'Friday', 'uplinkportal' ),
				__( 'Saturday', 'uplinkportal' ),
				__( 'Sunday', 'uplinkportal' ),
			)
		);
	}

	/**
	 * Returns short display labels for operating day controls.
	 *
	 * @return array<string,string>
	 */
	private function get_operating_day_short_labels(): array {
		return array_combine(
			self::OPERATING_DAY_KEYS,
			array(
				__( 'Mon', 'uplinkportal' ),
				__( 'Tue', 'uplinkportal' ),
				__( 'Wed', 'uplinkportal' ),
				__( 'Thu', 'uplinkportal' ),
				__( 'Fri', 'uplinkportal' ),
				__( 'Sat', 'uplinkportal' ),
				__( 'Sun', 'uplinkportal' ),
			)
		);
	}

	/**
	 * Returns default operating schedule.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_default_operating_schedule(): array {
		$schedule = array();
		foreach ( self::OPERATING_DAY_KEYS as $day_key ) {
			$schedule[ $day_key ] = array(
				'enabled' => in_array( $day_key, array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ), true ) ? 1 : 0,
				'open'    => '09:00',
				'close'   => '17:00',
			);
		}
		return $schedule;
	}

	/**
	 * Normalizes stored operating schedule without validation errors.
	 *
	 * @param mixed $raw_schedule Raw schedule.
	 * @return array<string,array<string,mixed>>
	 */
	private function normalize_operating_schedule( $raw_schedule ): array {
		if ( ! is_array( $raw_schedule ) ) {
			return $this->get_default_operating_schedule();
		}

		$normalized = $this->get_default_operating_schedule();
		foreach ( array_keys( $this->get_operating_day_labels() ) as $day_key ) {
			$row   = isset( $raw_schedule[ $day_key ] ) && is_array( $raw_schedule[ $day_key ] ) ? $raw_schedule[ $day_key ] : array();
			$open  = isset( $row['open'] ) ? $this->sanitize_time_slot_value( (string) $row['open'], '09:00' ) : '09:00';
			$close = isset( $row['close'] ) ? $this->sanitize_time_slot_value( (string) $row['close'], '17:00' ) : '17:00';

			$normalized[ $day_key ] = array(
				'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
				'open'    => $open,
				'close'   => $close,
			);
		}

		return $normalized;
	}

	/**
	 * Sanitizes and validates operating schedule from POST.
	 *
	 * @param mixed $raw_schedule Raw schedule payload.
	 * @return array<string,array<string,mixed>>|\WP_Error
	 */
	private function sanitize_operating_schedule( $raw_schedule ) {
		if ( ! is_array( $raw_schedule ) ) {
			$raw_schedule = array();
		}

		$schedule = $this->normalize_operating_schedule( $raw_schedule );
		foreach ( array_keys( $this->get_operating_day_labels() ) as $day_key ) {
			$entry = $schedule[ $day_key ];
			if ( empty( $entry['enabled'] ) ) {
				continue;
			}

			if ( $this->time_slot_to_minutes( (string) $entry['open'] ) >= $this->time_slot_to_minutes( (string) $entry['close'] ) ) {
				return new \WP_Error( 'error_operating_hours_range', __( 'Opening time must be earlier than closing time.', 'uplinkportal' ) );
			}
		}

		return $schedule;
	}

	/**
	 * Returns available operating time options (30-minute increments).
	 *
	 * @return array<string,string>
	 */
	private function get_operating_time_options(): array {
		$options = array();
		for ( $hour = 0; $hour < 24; $hour++ ) {
			for ( $minute = 0; $minute < 60; $minute += 30 ) {
				$value        = sprintf( '%02d:%02d', $hour, $minute );
				$display_hour = $hour % 12;
				if ( 0 === $display_hour ) {
					$display_hour = 12;
				}
				/* translators: 1: hour, 2: minute, 3: AM/PM marker. */
				$options[ $value ] = sprintf( __( '%1$d:%2$02d %3$s', 'uplinkportal' ), $display_hour, $minute, $hour < 12 ? __( 'AM', 'uplinkportal' ) : __( 'PM', 'uplinkportal' ) );
			}
		}
		return $options;
	}

	/**
	 * Sanitizes a single HH:MM timeslot string.
	 *
	 * @param string $value    Raw value.
	 * @param string $fallback Fallback time.
	 * @return string
	 */
	private function sanitize_time_slot_value( string $value, string $fallback ): string {
		return preg_match( '/^(?:[01]\d|2[0-3]):(?:00|30)$/', $value ) ? $value : $fallback;
	}

	/**
	 * Converts HH:MM slot to minutes after midnight.
	 *
	 * @param string $slot Time slot.
	 * @return int
	 */
	private function time_slot_to_minutes( string $slot ): int {
		$parts = explode( ':', $slot );
		if ( 2 !== count( $parts ) ) {
			return 0;
		}

		return ( absint( $parts[0] ) * 60 ) + absint( $parts[1] );
	}

	/**
	 * Formats a human-friendly schedule summary.
	 *
	 * @param array<string,array<string,mixed>> $schedule Schedule map.
	 * @return string
	 */
	private function format_operating_schedule_summary( array $schedule ): string {
		$summary      = array();
		$labels       = $this->get_operating_day_labels();
		$time_options = $this->get_operating_time_options();

		foreach ( $labels as $day_key => $day_label ) {
			$entry = isset( $schedule[ $day_key ] ) && is_array( $schedule[ $day_key ] ) ? $schedule[ $day_key ] : array();
			if ( empty( $entry['enabled'] ) ) {
				continue;
			}

			$open      = isset( $entry['open'] ) ? (string) $entry['open'] : '09:00';
			$close     = isset( $entry['close'] ) ? (string) $entry['close'] : '17:00';
			$summary[] = sprintf(
				/* translators: 1: Day label, 2: opening time label, 3: closing time label. */
				__( '%1$s %2$s-%3$s', 'uplinkportal' ),
				$day_label,
				isset( $time_options[ $open ] ) ? $time_options[ $open ] : $open,
				isset( $time_options[ $close ] ) ? $time_options[ $close ] : $close
			);
		}

		return ! empty( $summary ) ? implode( '; ', $summary ) : __( 'Closed all week', 'uplinkportal' );
	}

	/**
	 * Formats structured office address into multiline string.
	 *
	 * @param string $street  Street.
	 * @param string $city    City.
	 * @param string $state   State or region.
	 * @param string $country Country.
	 * @param string $postal  Zip/postal.
	 * @return string
	 */
	private function format_office_address( string $street, string $city, string $state, string $country, string $postal ): string {
		$lines   = array();
		$street  = trim( $street );
		$city    = trim( $city );
		$state   = trim( $state );
		$country = trim( $country );
		$postal  = trim( $postal );

		if ( '' !== $street ) {
			$lines[] = $street;
		}

		$line_two_parts = array_filter(
			array(
				$city,
				$state,
			),
			'strlen'
		);
		$line_two       = implode( ', ', $line_two_parts );
		if ( '' !== $postal ) {
			$line_two = '' !== $line_two ? $line_two . ' ' . $postal : $postal;
		}
		if ( '' !== $line_two ) {
			$lines[] = $line_two;
		}

		if ( '' !== $country ) {
			$lines[] = $country;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Detects compatible SMTP plugins in WordPress environment.
	 *
	 * @return bool
	 */
	private function is_smtp_plugin_detected(): bool {
		if ( null !== $this->smtp_plugin_detected_cache ) {
			return $this->smtp_plugin_detected_cache;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			$this->smtp_plugin_detected_cache = false;
			return false;
		}

		$plugins     = get_plugins();
		$known_files = array(
			'wp-mail-smtp/wp_mail_smtp.php',
			'fluent-smtp/fluent-smtp.php',
			'post-smtp/postman-smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
			'gmail-smtp/main.php',
		);
		foreach ( $known_files as $plugin_file ) {
			if ( isset( $plugins[ $plugin_file ] ) ) {
				$this->smtp_plugin_detected_cache = true;
				return true;
			}
		}

		foreach ( $plugins as $plugin_header ) {
			$name = isset( $plugin_header['Name'] ) ? strtolower( (string) $plugin_header['Name'] ) : '';
			if ( false !== strpos( $name, 'smtp' ) ) {
				$this->smtp_plugin_detected_cache = true;
				return true;
			}
		}

		$this->smtp_plugin_detected_cache = false;
		return false;
	}

	/**
	 * Renders backend section message in plain language.
	 *
	 * @return void
	 */
	/**
	 * Renders the introductory note for the Module Catalog settings section.
	 *
	 * @return void
	 */
	public function render_modules_section_note(): void {
		echo '<p>' . esc_html__( 'Enable or disable modules to control which services appear in your Service Catalog. Use each Configure window to set up backend connections.', 'uplinkportal' ) . '</p>';
	}

	/**
	 * Renders the introductory note for the Security & Compliance settings section.
	 *
	 * @return void
	 */
	public function render_backend_section_note(): void {
		echo '<p>' . esc_html__( 'Use this area for baseline security posture and, when entitled, expanded compliance controls.', 'uplinkportal' ) . '</p>';
		echo '<p>' . esc_html__( 'Connection setup has moved to Modules, inside each Configure window. Use the Database module for backend URL/token and secret-manager setup.', 'uplinkportal' ) . '</p>';
	}

	/**
	 * Renders backend mode field.
	 *
	 * @return void
	 */
	public function render_backend_mode_field(): void {
		$current_mode = $this->options->get_backend_mode();
		?>
		<label>
			<input
				type="radio"
				name="<?php echo esc_attr( $this->options->key() ); ?>[backend][mode]"
				value="self_hosted"
				<?php checked( 'managed_service' !== $current_mode ); ?>
			/>
			<?php echo esc_html__( 'Connect my own backend server/database', 'uplinkportal' ); ?>
		</label>
		<br />
		<div class="uplinkportal-upgrade-locked" aria-label="<?php echo esc_attr__( 'Managed backend upgrade required', 'uplinkportal' ); ?>">
			<label>
				<input
					type="radio"
					name="<?php echo esc_attr( $this->options->key() ); ?>[backend][mode]"
					value="managed_service"
					<?php checked( 'managed_service', $current_mode ); ?>
					disabled="disabled"
				/>
				<span class="dashicons dashicons-lock" aria-hidden="true"></span>
				<?php echo esc_html__( 'Use managed backend', 'uplinkportal' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Renders backend base URL field.
	 *
	 * @return void
	 */
	public function render_backend_base_url_field(): void {
		$backend_url  = $this->options->get_backend_base_url();
		$backend_mode = $this->options->get_backend_mode();
		?>
		<input
			type="url"
			class="regular-text"
			id="uplinkportal-backend-base-url"
			name="<?php echo esc_attr( $this->options->key() ); ?>[backend][base_url]"
			value="<?php echo esc_attr( $backend_url ); ?>"
			placeholder="<?php echo esc_attr__( 'https://api.example.com', 'uplinkportal' ); ?>"
			<?php disabled( 'managed_service', $backend_mode ); ?>
		/>
		<p class="description">
			<?php echo esc_html__( 'Enter your backend API base URL only. Keep credentials and database logins in your secure backend environment. HTTPS is required in production.', 'uplinkportal' ); ?>
		</p>
			<?php
	}

	/**
	 * Renders backend service token field.
	 *
	 * @return void
	 */
	public function render_backend_service_token_field(): void {
		$has_token = $this->options->has_backend_service_token();
		?>
		<input
			type="password"
			class="regular-text"
			name="<?php echo esc_attr( $this->options->key() ); ?>[backend_secrets][service_token]"
			value=""
			autocomplete="new-password"
			placeholder="<?php echo esc_attr( $has_token ? __( 'Stored. Enter a new value to rotate.', 'uplinkportal' ) : '' ); ?>"
		/>
		<p class="description">
			<?php
			echo esc_html(
				$has_token
					? __( 'Current value: ******** (masked). Used for backend-to-backend requests (for example, document key retrieval).', 'uplinkportal' )
					: __( 'No value stored. Recommended for internal backend API authorization.', 'uplinkportal' )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders monitoring and alert routing settings.
	 *
	 * @return void
	 */
	public function render_monitoring_routing_field(): void {
		$profile      = $this->options->get_monitoring_profile();
		$mode         = isset( $profile['mode'] ) ? sanitize_key( (string) $profile['mode'] ) : 'none';
		$webhook_url  = isset( $profile['webhook_url'] ) ? esc_url_raw( (string) $profile['webhook_url'] ) : '';
		$field_prefix = $this->options->key() . '[security][monitoring]';
		?>
		<div class="uplinkportal-monitoring-field">
			<fieldset class="uplinkportal-monitoring-options">
				<label>
					<input
						type="radio"
						name="<?php echo esc_attr( $field_prefix ); ?>[mode]"
						value="none"
						<?php checked( 'none', $mode ); ?>
						data-uplinkportal-monitoring-mode
					/>
					<?php echo esc_html__( 'No Monitoring (Default)', 'uplinkportal' ); ?>
				</label>
				<br />
				<label>
					<input
						type="radio"
						name="<?php echo esc_attr( $field_prefix ); ?>[mode]"
						value="generic_webhook"
						<?php checked( 'generic_webhook', $mode ); ?>
						data-uplinkportal-monitoring-mode
					/>
					<?php echo esc_html__( 'Generic Webhook (existing SIEM)', 'uplinkportal' ); ?>
				</label>
				<br />
				<label>
					<input
						type="radio"
						name="<?php echo esc_attr( $field_prefix ); ?>[mode]"
						value="hosted_subscription"
						<?php checked( 'hosted_subscription', $mode ); ?>
						data-uplinkportal-monitoring-mode
					/>
					<?php echo esc_html__( 'Hosted Centralized Logging (Subscription)', 'uplinkportal' ); ?>
				</label>
			</fieldset>
			<?php if ( 'none' === $mode ) : ?>
				<div class="notice notice-warning inline uplinkportal-inline-callout">
					<p>
						<?php echo esc_html__( 'Monitoring mode is currently set to No Monitoring. External alert forwarding is disabled.', 'uplinkportal' ); ?>
					</p>
				</div>
			<?php endif; ?>
			<p class="description"><?php echo esc_html__( 'Choose how security alerts are routed. No monitoring keeps alerts local to WordPress only.', 'uplinkportal' ); ?></p>

			<p data-uplinkportal-monitoring-webhook-wrap <?php echo 'generic_webhook' === $mode ? '' : 'hidden'; ?>>
				<label>
					<?php echo esc_html__( 'Generic Webhook URL', 'uplinkportal' ); ?><br />
					<input
						type="url"
						class="regular-text"
						name="<?php echo esc_attr( $field_prefix ); ?>[webhook_url]"
						value="<?php echo esc_attr( $webhook_url ); ?>"
						placeholder="<?php echo esc_attr__( 'https://siem.example.com/ingest', 'uplinkportal' ); ?>"
						data-uplinkportal-monitoring-webhook-input
						<?php disabled( 'generic_webhook' !== $mode ); ?>
					/>
				</label>
			</p>
			<p class="description" data-uplinkportal-monitoring-hosted-note <?php echo 'hosted_subscription' === $mode ? '' : 'hidden'; ?>>
				<?php echo esc_html__( 'Hosted centralized logging requires a managed backend/add-on subscription.', 'uplinkportal' ); ?>
			</p>
			<p class="description" data-uplinkportal-monitoring-none-note <?php echo 'none' === $mode ? '' : 'hidden'; ?>>
				<?php echo esc_html__( 'Warning: No external monitoring means unauthorized activity may be harder to detect quickly. You will be asked to confirm this on save.', 'uplinkportal' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders evidence trail setting.
	 *
	 * @return void
	 */
	public function render_evidence_trail_field(): void {
		$enabled = $this->options->get_evidence_trail_enabled();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $this->options->key() ); ?>[security][evidence_trail_enabled]"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php echo esc_html__( 'Enable tamper-evident evidence trail chain for security audit events', 'uplinkportal' ); ?>
		</label>
		<p class="description"><?php echo esc_html__( 'Stores hash-chained, pseudonymized event records for forensic integrity checks. This is append-only and may increase option storage usage over time.', 'uplinkportal' ); ?></p>
		<?php
	}

	/**
	 * Renders data protection at-rest and key policy settings.
	 *
	 * @return void
	 */
	public function render_data_protection_field(): void {
		$profile        = $this->options->get_data_protection_profile();
		$at_rest_status = isset( $profile['at_rest_status'] ) ? sanitize_key( (string) $profile['at_rest_status'] ) : 'disabled';
		$db_mode        = isset( $profile['database_encryption'] ) ? sanitize_key( (string) $profile['database_encryption'] ) : 'none';
		$doc_mode       = isset( $profile['document_encryption'] ) ? sanitize_key( (string) $profile['document_encryption'] ) : 'plugin_local_encrypted';
		$kms_provider   = isset( $profile['kms_provider'] ) ? sanitize_key( (string) $profile['kms_provider'] ) : 'none';
		$rotation_days  = isset( $profile['key_rotation_days'] ) ? max( 30, absint( $profile['key_rotation_days'] ) ) : 90;
		$separation     = ! empty( $profile['separation_of_duties'] );
		$runbook_url    = isset( $profile['emergency_revoke_runbook_url'] ) ? esc_url_raw( (string) $profile['emergency_revoke_runbook_url'] ) : '';
		$field_prefix   = $this->options->key() . '[security][data_protection]';
		$this->render_settings_window(
			'uplinkportal-window-data-protection',
			__( 'Data Protection At Rest', 'uplinkportal' ),
			__( 'Manage encryption-at-rest posture and key-management policy.', 'uplinkportal' ),
			function () use ( $at_rest_status, $db_mode, $doc_mode, $field_prefix, $kms_provider, $rotation_days, $runbook_url, $separation ) {
				?>
				<p class="description"><?php echo esc_html__( 'Track and enforce at-rest encryption posture for database and documents, plus key-management policy controls.', 'uplinkportal' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Recommended self-hosted baseline: Vault Transit + encrypted storage volumes + documented key revoke runbook.', 'uplinkportal' ); ?></p>
				<fieldset>
					<legend><strong><?php echo esc_html__( 'At-Rest Control Status', 'uplinkportal' ); ?></strong></legend>
					<label>
						<input type="radio" name="<?php echo esc_attr( $field_prefix ); ?>[at_rest_status]" value="disabled" <?php checked( 'disabled', $at_rest_status ); ?> />
						<?php echo esc_html__( 'Disabled (not enforced)', 'uplinkportal' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="<?php echo esc_attr( $field_prefix ); ?>[at_rest_status]" value="planned" <?php checked( 'planned', $at_rest_status ); ?> />
						<?php echo esc_html__( 'Planned (migration in progress)', 'uplinkportal' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="<?php echo esc_attr( $field_prefix ); ?>[at_rest_status]" value="enforced" <?php checked( 'enforced', $at_rest_status ); ?> />
						<?php echo esc_html__( 'Enforced', 'uplinkportal' ); ?>
					</label>
				</fieldset>

				<p>
					<label for="uplinkportal-data-protection-db-mode"><strong><?php echo esc_html__( 'Database Encryption', 'uplinkportal' ); ?></strong></label><br />
					<select id="uplinkportal-data-protection-db-mode" name="<?php echo esc_attr( $field_prefix ); ?>[database_encryption]">
						<option value="none" <?php selected( 'none', $db_mode ); ?>><?php echo esc_html__( 'Not configured', 'uplinkportal' ); ?></option>
						<option value="provider_tde" <?php selected( 'provider_tde', $db_mode ); ?>><?php echo esc_html__( 'Provider TDE / managed database encryption', 'uplinkportal' ); ?></option>
						<option value="kms_managed" <?php selected( 'kms_managed', $db_mode ); ?>><?php echo esc_html__( 'Customer-managed key (KMS/TDE)', 'uplinkportal' ); ?></option>
						<option value="self_hosted_encrypted_volume" <?php selected( 'self_hosted_encrypted_volume', $db_mode ); ?>><?php echo esc_html__( 'Self-hosted encrypted volumes', 'uplinkportal' ); ?></option>
					</select>
				</p>

				<p>
					<label for="uplinkportal-data-protection-doc-mode"><strong><?php echo esc_html__( 'Document Storage Encryption', 'uplinkportal' ); ?></strong></label><br />
					<select id="uplinkportal-data-protection-doc-mode" name="<?php echo esc_attr( $field_prefix ); ?>[document_encryption]">
						<option value="plugin_local_encrypted" <?php selected( 'plugin_local_encrypted', $doc_mode ); ?>><?php echo esc_html__( 'Plugin-managed local encryption', 'uplinkportal' ); ?></option>
						<option value="object_storage_sse_kms" <?php selected( 'object_storage_sse_kms', $doc_mode ); ?>><?php echo esc_html__( 'Object storage SSE-KMS', 'uplinkportal' ); ?></option>
						<option value="backend_managed" <?php selected( 'backend_managed', $doc_mode ); ?>><?php echo esc_html__( 'Delegated backend/add-on managed storage', 'uplinkportal' ); ?></option>
					</select>
				</p>

				<p>
					<label for="uplinkportal-data-protection-kms"><strong><?php echo esc_html__( 'Key Management Provider', 'uplinkportal' ); ?></strong></label><br />
					<select id="uplinkportal-data-protection-kms" name="<?php echo esc_attr( $field_prefix ); ?>[kms_provider]">
						<option value="none" <?php selected( 'none', $kms_provider ); ?>><?php echo esc_html__( 'None', 'uplinkportal' ); ?></option>
						<option value="vault_transit" <?php selected( 'vault_transit', $kms_provider ); ?>><?php echo esc_html__( 'HashiCorp Vault Transit (Recommended)', 'uplinkportal' ); ?></option>
						<option value="self_hosted_hsm" <?php selected( 'self_hosted_hsm', $kms_provider ); ?>><?php echo esc_html__( 'Self-hosted HSM/KMS', 'uplinkportal' ); ?></option>
						<option value="aws_kms" <?php selected( 'aws_kms', $kms_provider ); ?>><?php echo esc_html__( 'AWS KMS', 'uplinkportal' ); ?></option>
						<option value="azure_key_vault" <?php selected( 'azure_key_vault', $kms_provider ); ?>><?php echo esc_html__( 'Azure Key Vault', 'uplinkportal' ); ?></option>
						<option value="gcp_kms" <?php selected( 'gcp_kms', $kms_provider ); ?>><?php echo esc_html__( 'GCP Cloud KMS', 'uplinkportal' ); ?></option>
					</select>
				</p>

				<p>
					<label for="uplinkportal-data-protection-rotation-days"><strong><?php echo esc_html__( 'Key Rotation Interval (days)', 'uplinkportal' ); ?></strong></label><br />
					<input
						type="number"
						min="30"
						max="3650"
						step="1"
						class="small-text"
						id="uplinkportal-data-protection-rotation-days"
						name="<?php echo esc_attr( $field_prefix ); ?>[key_rotation_days]"
						value="<?php echo esc_attr( (string) $rotation_days ); ?>"
					/>
				</p>

				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $field_prefix ); ?>[separation_of_duties]" value="1" <?php checked( $separation ); ?> />
						<?php echo esc_html__( 'Enforce separation of duties for key operations (recommended for regulated deployments)', 'uplinkportal' ); ?>
					</label>
				</p>

				<p>
					<label for="uplinkportal-data-protection-runbook"><strong><?php echo esc_html__( 'Emergency Key Revoke Runbook URL', 'uplinkportal' ); ?></strong></label><br />
					<input
						type="url"
						class="regular-text"
						id="uplinkportal-data-protection-runbook"
						name="<?php echo esc_attr( $field_prefix ); ?>[emergency_revoke_runbook_url]"
						value="<?php echo esc_attr( $runbook_url ); ?>"
						placeholder="<?php echo esc_attr__( 'https://internal.example.com/runbooks/key-revoke', 'uplinkportal' ); ?>"
					/>
					<?php if ( '' !== $runbook_url ) : ?>
					<br /><a
						href="<?php echo esc_url( $runbook_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						class="uplinkportal-runbook-link"
					><?php echo esc_html__( 'Open Emergency Revoke Runbook', 'uplinkportal' ); ?></a>
					<?php endif; ?>
				</p>
				<?php
			}
		);
	}

	/**
	 * Renders secret manager configuration field.
	 *
	 * @return void
	 */
	public function render_secret_manager_field(): void {
		$config             = $this->options->get_secret_manager_config();
		$provider_mode      = isset( $config['provider_mode'] ) ? sanitize_key( (string) $config['provider_mode'] ) : '';
		$legacy_provider    = isset( $config['provider'] ) ? sanitize_key( (string) $config['provider'] ) : 'none';
		$provider_mode      = '' !== $provider_mode ? $provider_mode : ( 'vault' === $legacy_provider ? 'vault' : 'secrets_disabled' );
		$has_vault_ref      = $this->options->has_vault_connection_reference();
		$context_client_id  = $this->modules_entitlement_context_client_id();
		$database_providers = $context_client_id > 0
			? $this->provider_catalog_service->get_providers_for_client( $context_client_id, 'database' )
			: $this->provider_catalog_service->get_providers_for_module( 'database' );
		$secrets_providers  = array();

		foreach ( $database_providers as $provider_data ) {
			if ( ! is_array( $provider_data ) ) {
				continue;
			}
			$profile = isset( $provider_data['profile'] ) ? sanitize_key( (string) $provider_data['profile'] ) : '';
			if ( 'secrets' !== $profile ) {
				continue;
			}
			$provider_id = isset( $provider_data['id'] ) ? sanitize_key( (string) $provider_data['id'] ) : '';
			if ( '' === $provider_id ) {
				continue;
			}
			$secrets_providers[ $provider_id ] = $provider_data;
		}

		if ( ! isset( $secrets_providers[ $provider_mode ] ) ) {
			$provider_mode = 'secrets_disabled';
		}

		$selected_provider = isset( $secrets_providers[ $provider_mode ] ) && is_array( $secrets_providers[ $provider_mode ] )
			? $secrets_providers[ $provider_mode ]
			: array();
		$is_locked         = ! empty( $selected_provider['locked'] );
		$helper_text       = isset( $selected_provider['helper_text'] ) ? sanitize_text_field( (string) $selected_provider['helper_text'] ) : '';
		$grace_until       = isset( $selected_provider['grace_info']['grace_until'] ) ? sanitize_text_field( (string) $selected_provider['grace_info']['grace_until'] ) : '';
		$provider_message  = $is_locked
			? ( '' !== $helper_text ? $helper_text : __( "This provider requires an active add-on. Your profile will remain Draft and automations can't be enabled.", 'uplinkportal' ) )
			: '';
		$grace_warning     = '';
		if ( '' !== $grace_until ) {
			$grace_warning = sprintf(
				/* translators: %s grace period end date in UTC. */
				__( 'Add-on in grace until %s. Existing automations continue during grace; profile stays Draft until reactivated.', 'uplinkportal' ),
				gmdate( 'M j, Y H:i', strtotime( $grace_until ) ) . ' UTC'
			);
		}
		$is_vault     = 'vault' === $provider_mode;
		$legacy_value = 'vault' === $provider_mode ? 'vault' : 'none';
		?>
		<input type="hidden" name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][provider]" value="<?php echo esc_attr( $legacy_value ); ?>" />
		<p>
			<label>
				<?php echo esc_html__( 'Integration Provider', 'uplinkportal' ); ?><br />
				<select class="regular-text" name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][provider_mode]" data-uplinkportal-secret-manager-provider>
					<?php foreach ( $secrets_providers as $provider_id => $provider_data ) : ?>
						<?php
						$label          = isset( $provider_data['label'] ) ? sanitize_text_field( (string) $provider_data['label'] ) : $provider_id;
						$is_option_lock = ! empty( $provider_data['locked'] );
						$option_helper  = isset( $provider_data['helper_text'] ) ? sanitize_text_field( (string) $provider_data['helper_text'] ) : '';
						$display_label  = $label;
						if ( $is_option_lock ) {
							/* translators: %s integration provider label. */
							$display_label = sprintf( __( '%s (Locked)', 'uplinkportal' ), $label );
						}
						?>
						<option
							value="<?php echo esc_attr( $provider_id ); ?>"
							data-provider-locked="<?php echo $is_option_lock ? '1' : '0'; ?>"
							data-helper-text="<?php echo esc_attr( $option_helper ); ?>"
							data-grace-until="<?php echo esc_attr( isset( $provider_data['grace_info']['grace_until'] ) ? (string) $provider_data['grace_info']['grace_until'] : '' ); ?>"
							<?php selected( $provider_mode, $provider_id ); ?>
							<?php disabled( $is_option_lock && $provider_mode !== $provider_id ); ?>
						>
							<?php echo esc_html( $display_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
		<p class="description" data-uplinkportal-secret-provider-helper 
		<?php
		if ( '' === $provider_message ) :
			?>
			hidden<?php endif; ?>>
			<?php echo esc_html( $provider_message ); ?>
		</p>

		<div class="notice notice-warning inline uplinkportal-inline-callout" data-uplinkportal-secret-locked-provider-warning 
		<?php
		if ( ! $is_locked ) :
			?>
			hidden<?php endif; ?>>
			<p><?php echo esc_html__( "Provider is locked. Profile remains Draft and automations can't be enabled.", 'uplinkportal' ); ?></p>
		</div>
		<div class="notice notice-warning inline uplinkportal-inline-callout" data-uplinkportal-secret-grace-provider-warning 
		<?php
		if ( '' === $grace_warning ) :
			?>
			hidden<?php endif; ?>>
			<p><?php echo esc_html( $grace_warning ); ?></p>
		</div>

		<div class="uplinkportal-vault-config-panel" data-uplinkportal-vault-config-wrap <?php echo $is_vault ? '' : 'hidden'; ?>>
			<p>
				<label>
					<?php echo esc_html__( 'Vault Address', 'uplinkportal' ); ?><br />
					<input
						type="url"
						class="regular-text"
						name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][vault_addr]"
						value="<?php echo esc_attr( isset( $config['vault_addr'] ) ? (string) $config['vault_addr'] : '' ); ?>"
						placeholder="<?php echo esc_attr__( 'https://vault.example.com', 'uplinkportal' ); ?>"
						data-uplinkportal-vault-input
						<?php disabled( ! $is_vault ); ?>
					/>
				</label>
			</p>
			<p>
				<label>
					<?php echo esc_html__( 'Vault Namespace (optional)', 'uplinkportal' ); ?><br />
					<input
						type="text"
						class="regular-text"
						name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][vault_namespace]"
						value="<?php echo esc_attr( isset( $config['vault_namespace'] ) ? (string) $config['vault_namespace'] : '' ); ?>"
						placeholder="<?php echo esc_attr__( 'admin', 'uplinkportal' ); ?>"
						data-uplinkportal-vault-input
						<?php disabled( ! $is_vault ); ?>
					/>
				</label>
			</p>
			<div class="uplinkportal-vault-config-grid">
				<p>
					<label>
						<?php echo esc_html__( 'KV Mount', 'uplinkportal' ); ?><br />
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][vault_kv_mount]"
							value="<?php echo esc_attr( isset( $config['vault_kv_mount'] ) ? (string) $config['vault_kv_mount'] : 'kv' ); ?>"
							placeholder="kv"
							data-uplinkportal-vault-input
							<?php disabled( ! $is_vault ); ?>
						/>
					</label>
				</p>
				<p>
					<label>
						<?php echo esc_html__( 'Path Prefix', 'uplinkportal' ); ?><br />
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][vault_path_prefix]"
							value="<?php echo esc_attr( isset( $config['vault_path_prefix'] ) ? (string) $config['vault_path_prefix'] : 'uplinkportal' ); ?>"
							placeholder="uplinkportal"
							data-uplinkportal-vault-input
							<?php disabled( ! $is_vault ); ?>
						/>
					</label>
				</p>
				<p>
					<label>
						<?php echo esc_html__( 'Auth Mount', 'uplinkportal' ); ?><br />
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][vault_auth_mount]"
							value="<?php echo esc_attr( isset( $config['vault_auth_mount'] ) ? (string) $config['vault_auth_mount'] : 'approle' ); ?>"
							placeholder="approle"
							data-uplinkportal-vault-input
							<?php disabled( ! $is_vault ); ?>
						/>
					</label>
				</p>
				<p>
					<label>
						<?php echo esc_html__( 'Vault Role', 'uplinkportal' ); ?><br />
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][vault_role]"
							value="<?php echo esc_attr( isset( $config['vault_role'] ) ? (string) $config['vault_role'] : '' ); ?>"
							placeholder="<?php echo esc_attr__( 'uplinkportal-backend', 'uplinkportal' ); ?>"
							data-uplinkportal-vault-input
							<?php disabled( ! $is_vault ); ?>
						/>
					</label>
				</p>
			</div>
			<p>
				<label>
					<?php echo esc_html__( 'Vault Connection Reference (Opaque)', 'uplinkportal' ); ?><br />
					<input
						type="password"
						class="regular-text"
						name="<?php echo esc_attr( $this->options->key() ); ?>[secret_manager][vault_connection_ref]"
						value=""
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( $has_vault_ref ? __( 'Stored. Enter a new reference to rotate.', 'uplinkportal' ) : __( 'vault_ref_xxx', 'uplinkportal' ) ); ?>"
						data-uplinkportal-vault-input
						<?php disabled( ! $is_vault ); ?>
					/>
				</label>
			</p>
			<p class="description">
					<?php
					echo esc_html(
						$has_vault_ref
							? __( 'Current value: ******** (masked). Vault auth and lease lifecycle are handled by your secure backend/add-on.', 'uplinkportal' )
							: __( 'No Vault reference stored. Add the opaque backend-managed reference to enable delegated Vault lookups.', 'uplinkportal' )
					);
					?>
				</p>
			</div>
			<?php
	}

	/**
	 * Renders a settings window launcher with modal content.
	 *
	 * @param string   $window_id Window id.
	 * @param string   $title Window title.
	 * @param string   $summary Summary text.
	 * @param callable $content_callback Modal content renderer.
	 * @return void
	 */
	private function render_settings_window( string $window_id, string $title, string $summary, callable $content_callback ): void {
		$window_id = sanitize_html_class( $window_id );
		?>
		<div class="uplinkportal-settings-window-card">
			<h4><?php echo esc_html( $title ); ?></h4>
			<p class="description"><?php echo esc_html( $summary ); ?></p>
			<button
				type="button"
				class="button button-secondary uplinkportal-open-override"
				data-override-target="<?php echo esc_attr( $window_id ); ?>"
			>
				<?php echo esc_html__( 'Configure', 'uplinkportal' ); ?>
			</button>
		</div>

		<div id="<?php echo esc_attr( $window_id ); ?>" class="uplinkportal-override-modal uplinkportal-settings-window-modal" hidden>
			<div class="uplinkportal-override-dialog uplinkportal-settings-window-dialog">
				<div class="uplinkportal-settings-window-head">
					<h3><?php echo esc_html( $title ); ?></h3>
					<button type="button" class="uplinkportal-icon-action uplinkportal-close-override" aria-label="<?php echo esc_attr__( 'Close settings dialog', 'uplinkportal' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php echo esc_html__( 'Close', 'uplinkportal' ); ?></span>
					</button>
				</div>
				<div class="uplinkportal-settings-window-body">
					<?php call_user_func( $content_callback ); ?>
				</div>
				<div class="uplinkportal-settings-window-foot">
					<p class="description"><?php echo esc_html__( 'Changes are saved when you click the section Save button.', 'uplinkportal' ); ?></p>
					<button type="button" class="button button-primary uplinkportal-close-override"><?php echo esc_html__( 'Done', 'uplinkportal' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders compliance policy bundle field.
	 *
	 * @return void
	 */
	public function render_policy_bundle_field(): void {
		$current_bundle = $this->options->get_policy_bundle();
		$bundles        = CompliancePolicyProfiles::bundles();
		?>
		<select
			name="<?php echo esc_attr( $this->options->key() ); ?>[governance][policy_bundle]"
			id="uplinkportal-policy-bundle"
			class="regular-text"
		>
			<?php foreach ( $bundles as $bundle_key => $bundle ) : ?>
				<option value="<?php echo esc_attr( (string) $bundle_key ); ?>" <?php selected( $current_bundle, $bundle_key ); ?>>
					<?php echo esc_html( isset( $bundle['label'] ) ? (string) $bundle['label'] : (string) $bundle_key ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html__( 'Choose the baseline policy profile used for governance guidance and lint expectations.', 'uplinkportal' ); ?></p>
		<?php
	}

	/**
	 * Renders model-level RBAC policy matrix field.
	 *
	 * @return void
	 */
	public function render_model_policies_field(): void {
		$matrix       = $this->options->get_model_policy_matrix();
		$action_keys  = array(
			'connect' => __( 'Connect', 'uplinkportal' ),
			'read'    => __( 'Read', 'uplinkportal' ),
			'sync'    => __( 'Sync', 'uplinkportal' ),
			'export'  => __( 'Export', 'uplinkportal' ),
			'delete'  => __( 'Delete', 'uplinkportal' ),
		);
		$field_prefix = $this->options->key() . '[governance][model_policies]';
		$this->render_settings_window(
			'uplinkportal-window-model-policies',
			__( 'Model RBAC Policies', 'uplinkportal' ),
			__( 'Control which WordPress capability can perform each model action.', 'uplinkportal' ),
			function () use ( $action_keys, $field_prefix, $matrix ) {
				?>
				<p class="description"><?php echo esc_html__( 'Use capability keys (for example, msp_manage).', 'uplinkportal' ); ?></p>
				<table class="widefat striped uplinkportal-model-policy-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Model', 'uplinkportal' ); ?></th>
							<?php foreach ( $action_keys as $action_label ) : ?>
								<th><?php echo esc_html( $action_label ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( IntegrationModelRegistry::ids() as $model_id ) : ?>
							<?php
							$model_label = $this->get_integration_model_label( $model_id );
							$row         = isset( $matrix[ $model_id ] ) && is_array( $matrix[ $model_id ] ) ? $matrix[ $model_id ] : array();
							?>
							<tr>
								<td><strong><?php echo esc_html( $model_label ); ?></strong></td>
								<?php foreach ( array_keys( $action_keys ) as $action_key ) : ?>
									<?php
									$value = isset( $row[ $action_key ] ) ? sanitize_key( (string) $row[ $action_key ] ) : self::CAPABILITY_MSP_MANAGE;
									?>
									<td>
										<input
											type="text"
											class="regular-text"
											name="<?php echo esc_attr( $field_prefix ); ?>[<?php echo esc_attr( $model_id ); ?>][<?php echo esc_attr( $action_key ); ?>]"
											value="<?php echo esc_attr( $value ); ?>"
										/>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}
		);
	}

	/**
	 * Renders model legal-basis settings.
	 *
	 * @return void
	 */
	public function render_model_legal_basis_field(): void {
		$basis_map    = $this->options->get_model_legal_basis_map();
		$basis_labels = array(
			'consent'              => __( 'Consent', 'uplinkportal' ),
			'contract'             => __( 'Contract', 'uplinkportal' ),
			'legal_obligation'     => __( 'Legal Obligation', 'uplinkportal' ),
			'vital_interests'      => __( 'Vital Interests', 'uplinkportal' ),
			'public_task'          => __( 'Public Task', 'uplinkportal' ),
			'legitimate_interests' => __( 'Legitimate Interests', 'uplinkportal' ),
		);
		$field_prefix = $this->options->key() . '[governance][model_legal_basis]';
		$this->render_settings_window(
			'uplinkportal-window-legal-basis',
			__( 'GDPR Legal Basis', 'uplinkportal' ),
			__( 'Set the default legal basis per model using standard GDPR options.', 'uplinkportal' ),
			function () use ( $basis_labels, $basis_map, $field_prefix ) {
				?>
					<table class="widefat striped uplinkportal-model-policy-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Model', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Legal Basis', 'uplinkportal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( IntegrationModelRegistry::ids() as $model_id ) : ?>
							<?php
							$model_label = $this->get_integration_model_label( $model_id );
							$current     = isset( $basis_map[ $model_id ] ) ? sanitize_key( (string) $basis_map[ $model_id ] ) : 'contract';
							?>
							<tr>
								<td><strong><?php echo esc_html( $model_label ); ?></strong></td>
								<td>
									<select name="<?php echo esc_attr( $field_prefix ); ?>[<?php echo esc_attr( $model_id ); ?>]">
										<?php foreach ( $basis_labels as $basis_key => $basis_label ) : ?>
											<option value="<?php echo esc_attr( $basis_key ); ?>" <?php selected( $current, $basis_key ); ?>>
												<?php echo esc_html( $basis_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}
		);
	}

	/**
	 * Renders data minimization settings.
	 *
	 * @return void
	 */
	public function render_data_minimization_field(): void {
		$profile      = $this->options->get_data_minimization_profile();
		$allowlist    = isset( $profile['model_allowlist'] ) && is_array( $profile['model_allowlist'] ) ? $profile['model_allowlist'] : array();
		$overrides    = isset( $profile['provider_overrides'] ) && is_array( $profile['provider_overrides'] ) ? $profile['provider_overrides'] : array();
		$mask_logs    = ! empty( $profile['mask_logs'] );
		$field_prefix = $this->options->key() . '[governance][data_minimization]';
		$this->render_settings_window(
			'uplinkportal-window-data-minimization',
			__( 'Data Minimization', 'uplinkportal' ),
			__( 'Define allowed fields and provider overrides to reduce collected data.', 'uplinkportal' ),
			function () use ( $allowlist, $field_prefix, $mask_logs, $overrides ) {
				?>
				<p class="description"><?php echo esc_html__( 'Provider override format: provider_slug: field_one, field_two (one provider per line).', 'uplinkportal' ); ?></p>
				<table class="widefat striped uplinkportal-model-policy-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Model', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Default Field Allowlist', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Provider Overrides', 'uplinkportal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( IntegrationModelRegistry::ids() as $model_id ) : ?>
							<?php
							$model_label     = $this->get_integration_model_label( $model_id );
							$model_fields    = isset( $allowlist[ $model_id ] ) && is_array( $allowlist[ $model_id ] ) ? $allowlist[ $model_id ] : array();
							$model_overrides = isset( $overrides[ $model_id ] ) && is_array( $overrides[ $model_id ] ) ? $overrides[ $model_id ] : array();
							$override_lines  = array();
							foreach ( $model_overrides as $provider_slug => $fields ) {
								if ( ! is_array( $fields ) ) {
									continue;
								}
								$override_lines[] = sanitize_key( (string) $provider_slug ) . ': ' . implode( ', ', array_map( 'sanitize_key', $fields ) );
							}
							?>
							<tr>
								<td><strong><?php echo esc_html( $model_label ); ?></strong></td>
								<td>
									<textarea class="large-text" rows="3" name="<?php echo esc_attr( $field_prefix ); ?>[model_allowlist_text][<?php echo esc_attr( $model_id ); ?>]"><?php echo esc_textarea( implode( ', ', array_map( 'sanitize_key', $model_fields ) ) ); ?></textarea>
								</td>
								<td>
									<textarea class="large-text" rows="3" name="<?php echo esc_attr( $field_prefix ); ?>[provider_overrides_text][<?php echo esc_attr( $model_id ); ?>]"><?php echo esc_textarea( implode( "\n", $override_lines ) ); ?></textarea>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $field_prefix ); ?>[mask_logs]"
							value="1"
							<?php checked( $mask_logs ); ?>
						/>
						<?php echo esc_html__( 'Mask sensitive values in audit-log UI', 'uplinkportal' ); ?>
					</label>
				</p>
				<?php
			}
		);
	}

	/**
	 * Renders tenant boundary controls.
	 *
	 * @return void
	 */
	public function render_tenant_boundary_field(): void {
		$profile      = $this->options->get_tenant_boundary_profile();
		$mode         = isset( $profile['mode'] ) ? sanitize_key( (string) $profile['mode'] ) : 'single_site';
		$enforce      = ! empty( $profile['enforce_site_scope'] );
		$require_sa   = ! empty( $profile['require_super_admin'] );
		$is_multisite = is_multisite();
		$field_prefix = $this->options->key() . '[governance][tenant_boundary]';
		$this->render_settings_window(
			'uplinkportal-window-tenant-boundary',
			__( 'Tenant Boundary Controls', 'uplinkportal' ),
			__( 'Set how tenant access is scoped, especially in multisite environments.', 'uplinkportal' ),
			function () use ( $enforce, $field_prefix, $is_multisite, $mode, $require_sa ) {
				?>
				<fieldset>
					<label>
						<input type="radio" name="<?php echo esc_attr( $field_prefix ); ?>[mode]" value="single_site" <?php checked( 'single_site', $mode ); ?> <?php disabled( $is_multisite ); ?> />
						<?php echo esc_html__( 'Single Site Mode', 'uplinkportal' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="<?php echo esc_attr( $field_prefix ); ?>[mode]" value="multisite_site_isolation" <?php checked( 'multisite_site_isolation', $mode ); ?> <?php disabled( ! $is_multisite ); ?> />
						<?php echo esc_html__( 'Multisite Site Isolation (Recommended)', 'uplinkportal' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="<?php echo esc_attr( $field_prefix ); ?>[mode]" value="multisite_network_shared" <?php checked( 'multisite_network_shared', $mode ); ?> <?php disabled( ! $is_multisite ); ?> />
						<?php echo esc_html__( 'Multisite Network Shared', 'uplinkportal' ); ?>
					</label>
				</fieldset>
				<p class="description"><?php echo esc_html__( 'Site Isolation keeps tenant operations scoped to the current site in a multisite network.', 'uplinkportal' ); ?></p>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $field_prefix ); ?>[enforce_site_scope]" value="1" <?php checked( $enforce ); ?> <?php disabled( ! $is_multisite ); ?> />
						<?php echo esc_html__( 'Enforce current-site user scope in multisite', 'uplinkportal' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $field_prefix ); ?>[require_super_admin]" value="1" <?php checked( $require_sa ); ?> <?php disabled( ! $is_multisite ); ?> />
						<?php echo esc_html__( 'Require super admin for network-shared access', 'uplinkportal' ); ?>
					</label>
				</p>
				<?php if ( $is_multisite ) : ?>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d current site/blog id. */
								__( 'Current site context ID: %d', 'uplinkportal' ),
								(int) get_current_blog_id()
							)
						);
						?>
					</p>
				<?php endif; ?>
				<?php
			}
		);
	}

	/**
	 * Returns integration model label for settings UI.
	 *
	 * @param string $model_id Canonical model id.
	 * @return string
	 */
	private function get_integration_model_label( string $model_id ): string {
		switch ( sanitize_key( $model_id ) ) {
			case IntegrationModelRegistry::MODEL_FINANCE:
				return __( 'Finance', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_IT_SUPPORT:
				return __( 'IT Support', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_PROJECTS:
				return __( 'Projects', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_LICENSING:
				return __( 'Licensing', 'uplinkportal' );
			case IntegrationModelRegistry::MODEL_DOCUMENTS:
				return __( 'Documents', 'uplinkportal' );
		}

		return sanitize_text_field( $model_id );
	}

	/**
	 * Enqueues portal styles and scripts for admin preview page.
	 *
	 * @return void
	 */
	private function enqueue_portal_assets(): void {
		wp_enqueue_style(
			'uplinkportal-portal',
			UPLINKPORTAL_URL . 'assets/css/portal.css',
			array(),
			UPLINKPORTAL_VERSION
		);

		wp_enqueue_script(
			'uplinkportal-portal',
			UPLINKPORTAL_URL . 'assets/js/portal.js',
			array(),
			UPLINKPORTAL_VERSION,
			true
		);
	}

	/**
	 * Enqueues admin console assets.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'uplinkportal' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab state from query arg.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab state from query arg.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : '';

		if ( self::SETTINGS_SLUG === $current_page && 'modules' === $current_tab ) {
			wp_enqueue_script(
				'uplinkportal-admin-modules',
				uplinkportal_js_url( 'assets/js/admin-modules.js' ),
				array(),
				uplinkportal_js_ver( 'assets/js/admin-modules.js' ),
				true
			);
			wp_enqueue_script(
				'uplinkportal-admin-validation',
				uplinkportal_js_url( 'assets/js/admin-validation.js' ),
				array(),
				uplinkportal_js_ver( 'assets/js/admin-validation.js' ),
				true
			);
			wp_localize_script(
				'uplinkportal-admin-validation',
				'UplinkPortalValidation',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'uplinkportal_validation_test' ),
					'i18n'     => array(
						'testing' => __( 'Testing connection…', 'uplinkportal' ),
						'error'   => __( 'Connection test failed.', 'uplinkportal' ),
					),
				)
			);
			$this->enqueue_admin_backend_script();
		}

		if ( self::SETTINGS_SLUG === $current_page && 'backend_database' === $current_tab ) {
			$this->enqueue_admin_backend_script();
		}

		if ( self::SETTINGS_SLUG === $current_page ) {
			$this->enqueue_admin_configuration_script();
		}

		if ( self::SETTINGS_SLUG === $current_page && 'customization' === $current_tab ) {
			wp_enqueue_media();
		}

		if ( self::SERVICE_CATALOG_SLUG === $current_page || self::AGREEMENTS_RECORDS_SLUG === $current_page ) {
			$this->enqueue_admin_configuration_script();
		}

		if ( self::MENU_SLUG === $current_page ) {
			wp_enqueue_script(
				'uplinkportal-admin-dashboard',
				uplinkportal_js_url( 'assets/js/admin-dashboard.js' ),
				array(),
				uplinkportal_js_ver( 'assets/js/admin-dashboard.js' ),
				true
			);
		}
	}

	/**
	 * Enqueues integration backend script and localized wizard payload.
	 *
	 * @return void
	 */
	private function enqueue_admin_backend_script(): void {
		wp_enqueue_script(
			'uplinkportal-admin-backend',
			uplinkportal_js_url( 'assets/js/admin-backend.js' ),
			array(),
			uplinkportal_js_ver( 'assets/js/admin-backend.js' ),
			true
		);

		$wizard_models  = array();
		$context_client = $this->modules_entitlement_context_client_id();
		foreach ( IntegrationModelRegistry::ids() as $model_id ) {
			$providers        = array();
			$provider_module  = $this->provider_catalog_module_slug_for_model( $model_id );
			$provider_profile = $this->provider_catalog_profile_for_model( $model_id );
			$provider_catalog = $context_client > 0
				? $this->provider_catalog_service->get_providers_for_client( $context_client, $provider_module )
				: $this->provider_catalog_service->get_providers_for_module( $provider_module );
			$provider_catalog = $this->filter_provider_catalog_by_profile( $provider_catalog, $provider_profile );
			foreach ( $provider_catalog as $provider_data ) {
				if ( ! is_array( $provider_data ) ) {
					continue;
				}

				$provider_key      = isset( $provider_data['id'] ) ? sanitize_key( (string) $provider_data['id'] ) : '';
				$provider_types    = isset( $provider_data['auth_types'] ) && is_array( $provider_data['auth_types'] ) ? array_values( array_filter( array_map( 'sanitize_key', $provider_data['auth_types'] ) ) ) : array();
				$provider_type     = isset( $provider_types[0] ) ? (string) $provider_types[0] : '';
				$provider_locked   = ! empty( $provider_data['locked'] );
				$provider_helper   = isset( $provider_data['helper_text'] ) ? sanitize_text_field( (string) $provider_data['helper_text'] ) : '';
				$provider_defaults = isset( $provider_data['defaults'] ) && is_array( $provider_data['defaults'] ) ? $provider_data['defaults'] : array();
				$provider_scopes   = isset( $provider_defaults['scopes'] ) && is_array( $provider_defaults['scopes'] )
					? array_map( 'sanitize_text_field', $provider_defaults['scopes'] )
					: array();

				if ( '' === $provider_key ) {
					continue;
				}

				$providers[ $provider_key ] = array(
					'label'            => isset( $provider_data['label'] ) ? sanitize_text_field( (string) $provider_data['label'] ) : sanitize_text_field( (string) $provider_key ),
					'auth_type'        => $provider_type,
					'auth_types'       => $provider_types,
					'endpoint_alias'   => isset( $provider_defaults['endpoint_alias'] ) ? sanitize_text_field( (string) $provider_defaults['endpoint_alias'] ) : '',
					'retention_policy' => isset( $provider_defaults['retention_policy'] ) ? sanitize_key( (string) $provider_defaults['retention_policy'] ) : '',
					'scopes'           => implode( ', ', $provider_scopes ),
					'locked'           => $provider_locked,
					'helper_text'      => $provider_helper,
				);
			}

			$wizard_models[ $model_id ] = array(
				'label'     => $this->get_integration_model_label( $model_id ),
				'providers' => $providers,
			);
		}

		wp_localize_script(
			'uplinkportal-admin-backend',
			'UplinkPortalIntegrationWizard',
			array(
				'models'                 => $wizard_models,
				'providers_endpoint'     => rest_url( self::REST_NAMESPACE . '/providers' ),
				'entitlements_client_id' => $context_client,
				'rest_nonce'             => wp_create_nonce( 'wp_rest' ),
				'i18n'                   => array(
					'no_models'                   => __( 'No integration models are registered.', 'uplinkportal' ),
					'no_providers'                => __( 'No templates available for this model.', 'uplinkportal' ),
					'applied'                     => __( 'Template applied. Review and save the profile card.', 'uplinkportal' ),
					'incomplete_selection'        => __( 'Select both a model and provider template.', 'uplinkportal' ),
					'monitoring_none_confirm'     => __( 'External monitoring is disabled. This can increase security and compliance risk. Do you want to continue saving with No Monitoring selected?', 'uplinkportal' ),
					'monitoring_webhook_required' => __( 'Enter a valid webhook URL or select a different monitoring mode before saving.', 'uplinkportal' ),
					'locked_provider_helper'      => $this->get_locked_provider_helper_text(),
					'locked_provider_warning'     => __( "Provider is locked. Profile remains Draft and automations can't be enabled.", 'uplinkportal' ),
					/* translators: %s grace period end date in UTC. */
					'grace_provider_warning'      => __( 'Add-on in grace until %s. Existing automations continue during grace; profile stays Draft until reactivated.', 'uplinkportal' ),
				),
			)
		);
	}

	/**
	 * Enqueues and localizes admin configuration script.
	 *
	 * @return void
	 */
	private function enqueue_admin_configuration_script(): void {
		wp_enqueue_script(
			'uplinkportal-admin-configuration',
			uplinkportal_js_url( 'assets/js/admin-configuration.js' ),
			array(),
			uplinkportal_js_ver( 'assets/js/admin-configuration.js' ),
			true
		);

		wp_localize_script(
			'uplinkportal-admin-configuration',
			'UplinkPortalAdminConfig',
			array(
				'i18n' => array(
					'untitled_plan'            => __( 'Untitled Plan', 'uplinkportal' ),
					'plan_default_desc'        => __( 'No short description set for this plan yet.', 'uplinkportal' ),
					'new_plan_name'            => __( 'New Plan', 'uplinkportal' ),
					'select_document'          => __( 'Select a document', 'uplinkportal' ),
					'folder_empty'             => __( 'No records found in this folder.', 'uplinkportal' ),
					'doc_preview_placeholder'  => __( 'Choose a record to view its summary preview.', 'uplinkportal' ),
					'doc_preview_unavailable'  => __( 'Preview unavailable for this file. Open it in a new tab.', 'uplinkportal' ),
					'logo_frame_title'         => __( 'Select Company Logo', 'uplinkportal' ),
					'logo_frame_button'        => __( 'Use This Logo', 'uplinkportal' ),
					'logo_no_selection'        => __( 'No logo selected yet.', 'uplinkportal' ),
					'logo_bad_type'            => __( 'Please choose a PNG or SVG logo.', 'uplinkportal' ),
					'logo_bad_size'            => __( 'That image is too large. Please choose a file under 200KB.', 'uplinkportal' ),
					'logo_bad_dimensions'      => __( 'Logo dimensions cannot exceed 200x200 pixels.', 'uplinkportal' ),
					'hours_bad_range'          => __( 'Opening time must be earlier than closing time.', 'uplinkportal' ),
					'preview_welcome_fallback' => __( 'Welcome to your unified MSP portal.', 'uplinkportal' ),
				),
			)
		);
	}

	/**
	 * Handles connection validation AJAX request.
	 *
	 * @return void
	 */
	public function handle_validation_ajax(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to run validation.', 'uplinkportal' ),
				),
				403
			);
		}

		check_ajax_referer( 'uplinkportal_validation_test', 'nonce' );

		$user_id = get_current_user_id();
		$lock    = 'uplinkportal_validation_lock_' . $user_id;
		if ( get_transient( $lock ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please wait before running another validation test.', 'uplinkportal' ),
				),
				429
			);
		}
		set_transient( $lock, 1, 20 );

		$base_url            = isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['base_url'] ) ) : '';
		$api_key             = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['api_key'] ) ) : '';
		$section             = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( (string) $_POST['section'] ) ) : 'billing';
		$allow_query_api_key = isset( $_POST['allow_query_api_key'] ) ? absint( wp_unslash( (string) $_POST['allow_query_api_key'] ) ) : 0;
		$oauth_reference     = isset( $_POST['oauth_connection_ref'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['oauth_connection_ref'] ) ) : '';
		$auth_type           = 'api_key';

		$profile = $this->options->get_validation_profile( $section );
		$model   = $this->get_model_key_for_validation_section( $section );
		if ( '' !== $model && '' === $oauth_reference ) {
			$integration_profile = $this->options->get_integration_profile( $model );
			$oauth_reference     = isset( $integration_profile['oauth_connection_ref'] ) ? sanitize_text_field( (string) $integration_profile['oauth_connection_ref'] ) : '';
			$auth_type           = $this->normalize_profile_auth_type_for_ui( isset( $integration_profile['auth_type'] ) ? (string) $integration_profile['auth_type'] : 'api_key' );
		}

		if ( '' === $base_url ) {
			$base_url = (string) $profile['base_url'];
		}
		if ( '' === $api_key ) {
			$api_key = (string) $profile['api_key'];
		}

		if ( 0 === $allow_query_api_key ) {
			$allow_query_api_key = $this->options->allow_query_api_key_for_section( $section ) ? 1 : 0;
		}

		$broker = new BackendValidationBroker( $this->options );
		$result = $broker->validate( $section, $base_url, $api_key, 1 === $allow_query_api_key, $oauth_reference, $auth_type );
		if ( '' !== $model ) {
			$this->record_integration_sync( $model );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handles manual license validation action.
	 *
	 * @return void
	 */
	public function handle_validate_license_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to validate licenses.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_validate_license', 'uplinkportal_validate_license_nonce' );

		$result = $this->licensing_client->validate_license( true );
		$this->record_integration_sync( 'licensing' );
		$notice = ! empty( $result['valid'] ) ? 'license_valid' : 'license_invalid';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                        => self::SETTINGS_SLUG,
					'tab'                         => 'modules',
					'module'                      => 'database',
					'uplinkportal_license_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handles client-initiated portal service request action.
	 *
	 * @return void
	 */
	public function handle_purchase_request_action(): void {
		$current_user_id = get_current_user_id();
		if ( ! is_user_logged_in() || ! $this->is_client_workspace_user( $current_user_id ) ) {
			wp_die( esc_html__( 'You do not have permission to submit a fulfillment request.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_create_purchase_request', 'uplinkportal_purchase_request_nonce' );

		$current_user = wp_get_current_user();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$payload = array(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce helper above.
			'request_type'           => isset( $_POST['request_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['request_type'] ) ) : 'service_request',
			'request_context'        => 'client_portal',
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce helper above.
			'requested_item_key'     => isset( $_POST['requested_item_key'] ) ? sanitize_key( wp_unslash( (string) $_POST['requested_item_key'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce helper above.
			'requested_item_label'   => isset( $_POST['requested_item_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['requested_item_label'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce helper above.
			'notes'                  => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['notes'] ) ) : '',
			'requester_user_id'      => $current_user_id,
			'requester_display_name' => $current_user instanceof \WP_User ? sanitize_text_field( (string) $current_user->display_name ) : '',
			'requester_email'        => $current_user instanceof \WP_User ? sanitize_email( (string) $current_user->user_email ) : '',
		);
		if ( '' === $payload['requested_item_label'] ) {
			$label_map                       = array(
				'finance'      => __( 'Finance Services', 'uplinkportal' ),
				'it_support'   => __( 'IT Support Services', 'uplinkportal' ),
				'projects'     => __( 'Project Services', 'uplinkportal' ),
				'custom_dummy' => __( 'Custom Services', 'uplinkportal' ),
				'general'      => __( 'General Service Request', 'uplinkportal' ),
			);
			$payload['requested_item_label'] = isset( $label_map[ $payload['requested_item_key'] ] ) ? $label_map[ $payload['requested_item_key'] ] : __( 'Client Portal Service Request', 'uplinkportal' );
		}
		$request_id = $this->purchase_request_store->create_request( $payload );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
		$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );

		$redirect_to = add_query_arg(
			array(
				'uplinkportal_purchase_request' => $request_id > 0 ? 'submitted' : 'error',
			),
			$redirect_to
		);

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Renders purchase request action notice.
	 *
	 * @return void
	 */
	private function render_purchase_request_action_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query arg.
		$status = isset( $_GET['uplinkportal_purchase_request'] ) ? sanitize_key( (string) $_GET['uplinkportal_purchase_request'] ) : '';
		if ( '' === $status ) {
			return;
		}

		if ( 'submitted' === $status ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Fulfillment request created. The MSP provider team can now review it in Operations Dashboard.', 'uplinkportal' ) . '</p></div>';
			return;
		}

		if ( 'error' === $status ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Fulfillment request could not be created. Please review your request details and try again.', 'uplinkportal' ) . '</p></div>';
		}
	}

	/**
	 * Handles client portfolio save action.
	 *
	 * @return void
	 */
	public function handle_client_portfolio_save_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to update client portfolio details.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_save_client_portfolio', 'uplinkportal_save_client_portfolio_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
		$redirect_to = $this->get_client_portfolio_redirect_url( $redirect_to );

		if ( $client_id <= 0 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'uplinkportal_client_portfolio' => 'error',
						'uplinkportal_portfolio_error'  => 'invalid_client',
					),
					$redirect_to
				)
			);
			exit;
		}

		if ( ! $this->client_profiles->can_current_user_access_profile( $client_id, true ) ) {
			wp_die( esc_html__( 'You do not have permission to update this client profile.', 'uplinkportal' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$payload = array(
			'company_name'  => isset( $_POST['company_name'] ) ? wp_unslash( (string) $_POST['company_name'] ) : '',
			'department'    => isset( $_POST['department'] ) ? wp_unslash( (string) $_POST['department'] ) : '',
			'title'         => isset( $_POST['title'] ) ? wp_unslash( (string) $_POST['title'] ) : '',
			'phone_number'  => isset( $_POST['phone_number'] ) ? wp_unslash( (string) $_POST['phone_number'] ) : '',
			'email_address' => isset( $_POST['email_address'] ) ? wp_unslash( (string) $_POST['email_address'] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = $this->client_profiles->update_profile_for_user( $client_id, $payload );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'uplinkportal_client_portfolio' => 'error',
						'uplinkportal_portfolio_error'  => sanitize_key( (string) $result->get_error_code() ),
					),
					$redirect_to
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'uplinkportal_client_portfolio' => 'saved',
				),
				$redirect_to
			)
		);
		exit;
	}

	/**
	 * Handles client document upload action.
	 *
	 * @return void
	 */
	public function handle_upload_client_document_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to upload client documents.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_upload_client_document', 'uplinkportal_upload_client_document_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
		$redirect_to = $this->get_document_action_redirect_url( $redirect_to );

		$file = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		if ( isset( $_FILES['client_document'] ) && is_array( $_FILES['client_document'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- trusted admin-post payload validated by nonce helper; uploaded file arrays are validated by WP upload + store checks.
			$file = $_FILES['client_document'];
		}

		$result = $this->client_documents->upload_document_for_client( $client_id, $file, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'uplinkportal_docs'       => 'error',
						'uplinkportal_docs_error' => sanitize_key( (string) $result->get_error_code() ),
					),
					$redirect_to
				)
			);
			exit;
		}
		$this->record_integration_sync( 'documents' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'uplinkportal_docs' => 'uploaded',
				),
				$redirect_to
			)
		);
		exit;
	}

	/**
	 * Handles bulk client document deletion action.
	 *
	 * @return void
	 */
	public function handle_delete_client_documents_action(): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to delete client documents.', 'uplinkportal' ) );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_delete_client_documents', 'uplinkportal_delete_client_documents_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( (string) $_POST['client_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin-post payload validated by nonce.
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
		$redirect_to = $this->get_document_action_redirect_url( $redirect_to );

		if ( $client_id <= 0 || ! $this->is_client_workspace_user( $client_id ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'uplinkportal_docs'       => 'error',
						'uplinkportal_docs_error' => 'invalid_client',
					),
					$redirect_to
				)
			);
			exit;
		}

		$result   = $this->client_documents->delete_documents_for_client( $client_id, get_current_user_id() );
		$removed  = isset( $result['removed'] ) ? absint( $result['removed'] ) : 0;
		$retained = isset( $result['retained'] ) ? absint( $result['retained'] ) : 0;

		$status = 'deleted';
		if ( 0 === $removed && 0 === $retained ) {
			$status = 'delete_none';
		} elseif ( $retained > 0 ) {
			$status = 'delete_partial';
		}

		if ( $removed > 0 || $retained > 0 ) {
			$this->record_integration_sync( 'documents' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'uplinkportal_docs'          => $status,
					'uplinkportal_docs_removed'  => $removed,
					'uplinkportal_docs_retained' => $retained,
				),
				$redirect_to
			)
		);
		exit;
	}

	/**
	 * Handles secure client document streaming action.
	 *
	 * @return void
	 */
	public function handle_stream_client_document_action(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- validated immediately below.
		$document_id = isset( $_GET['document_id'] ) ? absint( (string) $_GET['document_id'] ) : 0;
		if ( $document_id <= 0 ) {
			wp_die( esc_html__( 'Invalid document requested.', 'uplinkportal' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verification for signed stream URL.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( (string) $_GET['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'uplinkportal_stream_client_document_' . $document_id ) ) {
			wp_die( esc_html__( 'Document link is invalid or expired.', 'uplinkportal' ) );
		}

		$result = $this->client_documents->stream_document( $document_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_die( esc_html__( 'Unable to stream document.', 'uplinkportal' ) );
	}

	/**
	 * Renders license-action notices.
	 *
	 * @return void
	 */
	public function render_license_action_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args.
		$current_module = isset( $_GET['module'] ) ? sanitize_key( (string) $_GET['module'] ) : '';

		if ( self::SETTINGS_SLUG !== $current_page || 'modules' !== $current_tab || 'database' !== $current_module ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args.
		$notice = isset( $_GET['uplinkportal_license_notice'] ) ? sanitize_key( (string) $_GET['uplinkportal_license_notice'] ) : '';
		if ( '' === $notice ) {
			return;
		}

		$messages = array(
			'license_valid'   => array( 'success', __( 'License validation succeeded.', 'uplinkportal' ) ),
			'license_invalid' => array( 'error', __( 'License validation failed.', 'uplinkportal' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		$message = $messages[ $notice ];
		echo '<div class="notice notice-' . esc_attr( $message[0] ) . ' inline"><p>' . esc_html( $message[1] ) . '</p></div>';
	}

	/**
	 * Handles manual role resync action.
	 *
	 * @return void
	 */
	public function handle_resync_roles_action(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			$this->safe_resync_redirect( 'denied' );
		}

		$this->verify_admin_post_nonce( 'uplinkportal_resync_roles_action', 'uplinkportal_resync_roles_nonce' );
		CapabilityManager::manual_resync_roles();
		$this->safe_resync_redirect( 'success' );
	}

	/**
	 * Renders a service-module-gated admin page.
	 *
	 * @param string $module_key Module key.
	 * @param string $title Page title.
	 * @param string $description Page description.
	 * @return void
	 */
	private function render_module_page( string $module_key, string $title, string $description ): void {
		if ( ! current_user_can( self::CAPABILITY_MSP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'uplinkportal' ) );
		}

		if ( ! $this->is_service_module_enabled( $module_key ) ) {
			wp_die( esc_html__( 'This section is unavailable until its related module is enabled in Configuration > Modules.', 'uplinkportal' ) );
		}
		?>
		<div class="wrap uplinkportal-admin msp-admin">
			<section class="uplinkportal-admin-hero">
				<div>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p><?php echo esc_html( $description ); ?></p>
				</div>
				<span class="uplinkportal-admin-badge"><?php echo esc_html__( 'Module Enabled', 'uplinkportal' ); ?></span>
			</section>

				<div class="uplinkportal-admin-card">
					<p><?php echo esc_html__( 'This section is module-enabled and ready for backend-connected data tables and edit workflows.', 'uplinkportal' ); ?></p>
				</div>
		</div>
		<?php
	}

	/**
	 * Returns whether a service module is enabled.
	 *
	 * @param string $module_key Module key.
	 * @return bool
	 */
	private function is_service_module_enabled( string $module_key ): bool {
		$module_key = sanitize_key( $module_key );
		if ( $this->is_required_module( $module_key ) ) {
			return true;
		}

		if ( ! $this->options->is_service_module_enabled( $module_key ) ) {
			return false;
		}

		return (bool) apply_filters( 'uplinkportal_feature_enabled', true, $module_key );
	}

	/**
	 * Returns whether a module key is required and always enabled.
	 *
	 * @param string $module_key Module key.
	 * @return bool
	 */
	private function is_required_module( string $module_key ): bool {
		return in_array( sanitize_key( $module_key ), array( 'database' ), true );
	}

	/**
	 * Returns key metrics shown on operations dashboard.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_operations_dashboard_metrics(): array {
		$service_modules   = $this->options->get_service_modules();
		$enabled_count     = 0;
		$configured_apis   = 0;
		$validation_config = $this->options->get_validation_profiles();
		$license_status    = $this->licensing_client->validate_license( false );
		$open_requests     = $this->purchase_request_store->count_open_requests();

		foreach ( array_keys( $service_modules ) as $module_key ) {
			if ( $this->is_service_module_enabled( (string) $module_key ) ) {
				++$enabled_count;
			}
		}

		foreach ( $validation_config as $profile ) {
			if ( is_array( $profile ) && ! empty( $profile['base_url'] ) ) {
				++$configured_apis;
			}
		}

		return array(
			array(
				'label' => __( 'Enabled Modules', 'uplinkportal' ),
				'value' => (string) $enabled_count,
				'help'  => __( 'Currently active from your module catalog.', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Plan Templates', 'uplinkportal' ),
				'value' => (string) count( $this->get_plan_templates() ),
				'help'  => __( 'Service plans available for client assignments.', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Client Accounts', 'uplinkportal' ),
				'value' => (string) count( $this->get_client_workspace_users() ),
				'help'  => __( 'Users mapped to UplinkPortal client roles.', 'uplinkportal' ),
			),
			array(
				'label' => __( 'API Connections Ready', 'uplinkportal' ),
				'value' => (string) $configured_apis,
				'help'  => __( 'Validation endpoints configured in module settings.', 'uplinkportal' ),
			),
			array(
				'label' => __( 'License Health', 'uplinkportal' ),
				'value' => ! empty( $license_status['valid'] ) ? __( 'Valid', 'uplinkportal' ) : __( 'Needs Attention', 'uplinkportal' ),
				'help'  => __( 'Based on latest worker validation result.', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Open Fulfillment Requests', 'uplinkportal' ),
				'value' => (string) $open_requests,
				'help'  => __( 'Manual service/add-on tasks waiting for provider action.', 'uplinkportal' ),
			),
		);
	}

	/**
	 * Returns module-focused metrics for dashboard.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_operations_module_metrics(): array {
		$profiles = $this->options->get_validation_profiles();
		$modules  = $this->get_template_module_labels();
		$rows     = array();

		foreach ( $modules as $module_key => $module_data ) {
			$enabled = $this->is_service_module_enabled( $module_key );
			$metric  = __( 'Enable this module to show operational status.', 'uplinkportal' );

			if ( $enabled ) {
				if ( 'finance' === $module_key ) {
					$metric = ! empty( $profiles['billing']['base_url'] )
						? __( 'Billing connector endpoint is configured.', 'uplinkportal' )
						: __( 'Billing connector endpoint is not configured.', 'uplinkportal' );
				} elseif ( 'it_support' === $module_key ) {
					$metric = ! empty( $profiles['helpdesk']['base_url'] )
						? __( 'Help desk endpoint is configured.', 'uplinkportal' )
						: __( 'Help desk endpoint is not configured.', 'uplinkportal' );
				} elseif ( 'projects' === $module_key ) {
					$metric = ! empty( $profiles['pm']['base_url'] )
						? __( 'Project endpoint is configured.', 'uplinkportal' )
						: __( 'Project endpoint is not configured.', 'uplinkportal' );
				} elseif ( 'custom_dummy' === $module_key ) {
					$metric = __( 'Custom workspace is active for extension testing.', 'uplinkportal' );
				}
			}

			$rows[] = array(
				'label'   => (string) $module_data['label'],
				'metric'  => $metric,
				'enabled' => $enabled,
			);
		}

		return $rows;
	}

	/**
	 * Returns Client Portfolio table rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_client_portfolio_rows(): array {
		$clients = $this->get_client_workspace_users();

		$plan_templates = $this->get_plan_templates();

		$ticket_counts = $this->purchase_request_store->get_ticket_counts_by_requester();

		$project_counts = $this->purchase_request_store->get_active_project_counts_by_requester();

		$rows = array();

		foreach ( $clients as $client_user ) {
			$client_id = absint( $client_user->ID );
			if ( $client_id <= 0 ) {
				continue;
			}

			$plan_key = $this->get_client_plan( $client_id );

			$plan_label = isset( $plan_templates[ $plan_key ]['label'] )
				? sanitize_text_field( (string) $plan_templates[ $plan_key ]['label'] )
				: $plan_key;

			$rows[] = array(
				'client_id'            => $client_id,
				'client_name'          => sanitize_text_field( (string) $client_user->display_name ),
				'user_email'           => sanitize_email( (string) $client_user->user_email ),
				'plan_key'             => $plan_key,
				'plan_label'           => $plan_label,
				'ticket_count'         => isset( $ticket_counts[ $client_id ] ) ? absint( $ticket_counts[ $client_id ] ) : 0,
				'active_project_count' => isset( $project_counts[ $client_id ] ) ? absint( $project_counts[ $client_id ] ) : 0,
				'profile'              => $this->client_profiles->get_profile_for_user( $client_id ),
			);
		}

		return $rows;
	}

	/**
	 * Returns client folder tree for Agreements & Records.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_agreements_folders(): array {
		$folders = array();

		foreach ( $this->get_client_workspace_users() as $client ) {
			$client_id = absint( $client->ID );
			if ( $client_id <= 0 ) {
				continue;
			}

			$label = sanitize_text_field( (string) $client->display_name );
			if ( '' === $label ) {
				$label = sanitize_user( (string) $client->user_login );
			}
			if ( '' === $label ) {
				$label = sprintf(
					/* translators: %d client user id. */
					__( 'Client %d', 'uplinkportal' ),
					$client_id
				);
			}

			$folders[ 'client-' . $client_id ] = array(
				'client_id' => $client_id,
				'label'     => $label,
				'documents' => $this->client_documents->get_documents_for_client( $client_id ),
			);
		}

		return $folders;
	}

	/**
	 * Returns user-facing upload error messages.
	 *
	 * @param string $error_code Error code.
	 * @return string
	 */
	private function get_document_upload_error_message( string $error_code ): string {
		$messages = array(
			'invalid_client'  => __( 'Select a valid client folder before continuing.', 'uplinkportal' ),
			'missing_file'    => __( 'Choose a PDF file to upload.', 'uplinkportal' ),
			'upload_error'    => __( 'Upload failed before encryption. Please try again.', 'uplinkportal' ),
			'invalid_upload'  => __( 'Uploaded file could not be validated.', 'uplinkportal' ),
			'invalid_type'    => __( 'Only PDF files are allowed.', 'uplinkportal' ),
			'invalid_content' => __( 'Uploaded file content is not a valid PDF.', 'uplinkportal' ),
			'invalid_size'    => __( 'Uploaded file size is outside the allowed limit.', 'uplinkportal' ),
			'read_error'      => __( 'The uploaded file could not be read.', 'uplinkportal' ),
			'missing_key'     => __( 'No client encryption key is available from backend connection settings.', 'uplinkportal' ),
			'encrypt_failed'  => __( 'Document encryption failed on this server.', 'uplinkportal' ),
			'no_encryption'   => __( 'No encryption library is available for secure document storage.', 'uplinkportal' ),
			'write_error'     => __( 'Encrypted document could not be written to storage.', 'uplinkportal' ),
			'db_insert_error' => __( 'Document index update failed after upload.', 'uplinkportal' ),
		);

		return isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : __( 'Document upload failed. Check server configuration and try again.', 'uplinkportal' );
	}

	/**
	 * Returns validated redirect target for document actions.
	 *
	 * @param string $candidate Candidate redirect URL.
	 * @return string
	 */
	private function get_document_action_redirect_url( string $candidate ): string {
		$default = add_query_arg(
			array(
				'page' => self::AGREEMENTS_RECORDS_SLUG,
			),
			admin_url( 'admin.php' )
		);

		if ( '' === $candidate ) {
			return $default;
		}

		return wp_validate_redirect( $candidate, $default );
	}

	/**
	 * Returns validated redirect target for client portfolio actions.
	 *
	 * @param string $candidate Candidate redirect URL.
	 * @return string
	 */
	private function get_client_portfolio_redirect_url( string $candidate ): string {
		$default = $this->get_client_portfolio_page_url();
		if ( '' === $candidate ) {
			return $default;
		}

		return wp_validate_redirect( $candidate, $default );
	}

	/**
	 * Returns available icon options for plan cards.
	 *
	 * @return array<string,string>
	 */
	private function get_plan_icon_options(): array {
		return array(
			'database'      => __( 'Database', 'uplinkportal' ),
			'portfolio'     => __( 'Briefcase', 'uplinkportal' ),
			'chart-line'    => __( 'Growth Chart', 'uplinkportal' ),
			'building'      => __( 'Building', 'uplinkportal' ),
			'shield'        => __( 'Shield', 'uplinkportal' ),
			'cloud'         => __( 'Cloud', 'uplinkportal' ),
			'admin-generic' => __( 'Gear', 'uplinkportal' ),
		);
	}

	/**
	 * Converts plan icon key into Dashicons class.
	 *
	 * @param string $icon_key Icon key.
	 * @return string
	 */
	private function get_plan_icon_dashicon( string $icon_key ): string {
		$map = array(
			'database'      => 'dashicons-database',
			'portfolio'     => 'dashicons-portfolio',
			'chart-line'    => 'dashicons-chart-line',
			'building'      => 'dashicons-building',
			'shield'        => 'dashicons-shield',
			'cloud'         => 'dashicons-cloud',
			'admin-generic' => 'dashicons-admin-generic',
		);

		$icon_key = sanitize_key( $icon_key );
		return isset( $map[ $icon_key ] ) ? $map[ $icon_key ] : 'dashicons-portfolio';
	}

	/**
	 * Returns enabled module-linked section labels.
	 *
	 * @return array<int,string>
	 */
	private function get_enabled_module_page_labels(): array {
		$labels = array();

		foreach ( array_keys( $this->options->get_service_modules() ) as $module_key ) {
			if ( ! $this->is_service_module_enabled( $module_key ) ) {
				continue;
			}

			foreach ( $this->get_module_section_labels( $module_key ) as $label ) {
				$labels[] = $label;
			}
		}

		if ( empty( $labels ) ) {
			$labels = array( __( 'No module sections are currently enabled.', 'uplinkportal' ) );
		}

		$labels[] = __( 'Agreements & Records', 'uplinkportal' );
		$labels[] = __( 'Service Catalog', 'uplinkportal' );
		$labels[] = __( 'Documents & Agreements', 'uplinkportal' );
		$labels[] = __( 'My Services', 'uplinkportal' );

		return $labels;
	}

	/**
	 * Renders module-scoped API validation controls.
	 *
	 * @param string $module_key Module key.
	 * @return void
	 */
	private function render_module_validation_fields( string $module_key ): void {
		$section = $this->get_validation_section_for_module( $module_key );
		if ( '' === $section ) {
			return;
		}

		$profile        = $this->options->get_validation_profile( $section );
		$field_base     = $this->options->key() . '[validation_profiles][' . $section . ']';
		$input_suffix   = sanitize_key( $section );
		$has_stored_key = '' !== (string) $profile['api_key'];
		?>
		<div class="uplinkportal-validation-group">
			<h4><?php echo esc_html__( 'API Connection & Compatibility Validation', 'uplinkportal' ); ?></h4>
			<p class="description">
				<?php echo esc_html__( 'Validation checks run through your secure backend service or managed addon. Public-core plugin code does not include bypassable validation logic.', 'uplinkportal' ); ?>
			</p>

			<label for="uplinkportal-validation-base-url-<?php echo esc_attr( $input_suffix ); ?>">
				<?php echo esc_html__( 'API Base URL', 'uplinkportal' ); ?>
			</label>
			<input
				type="url"
				class="regular-text uplinkportal-validation-base-url"
				id="uplinkportal-validation-base-url-<?php echo esc_attr( $input_suffix ); ?>"
				name="<?php echo esc_attr( $field_base ); ?>[base_url]"
				value="<?php echo esc_attr( (string) $profile['base_url'] ); ?>"
				placeholder="<?php echo esc_attr__( 'https://api.example.com', 'uplinkportal' ); ?>"
			/>
			<p class="description"><?php echo esc_html__( 'Use HTTPS in production. Local non-HTTPS URLs are only intended for local/development hosts.', 'uplinkportal' ); ?></p>

			<label for="uplinkportal-validation-api-key-<?php echo esc_attr( $input_suffix ); ?>">
				<?php echo esc_html__( 'API Key', 'uplinkportal' ); ?>
			</label>
			<input
				type="password"
				class="regular-text uplinkportal-validation-api-key"
				id="uplinkportal-validation-api-key-<?php echo esc_attr( $input_suffix ); ?>"
				name="<?php echo esc_attr( $field_base ); ?>[api_key]"
				value=""
				autocomplete="off"
				placeholder="<?php echo esc_attr__( 'Enter new API key', 'uplinkportal' ); ?>"
			/>
				<p class="description">
					<?php
					echo esc_html(
						$has_stored_key
							? __( 'A key is already stored. Leave blank to keep the existing key.', 'uplinkportal' )
							: __( 'No API key stored yet.', 'uplinkportal' )
					);
					?>
				</p>

			<label>
				<input
					type="checkbox"
					class="uplinkportal-validation-allow-query"
					name="<?php echo esc_attr( $field_base ); ?>[allow_query_api_key]"
					value="1"
					<?php checked( ! empty( $profile['allow_query_api_key'] ) ); ?>
				/>
				<?php echo esc_html__( 'Allow api_key query parameter for this section (not recommended)', 'uplinkportal' ); ?>
			</label>

			<button
				type="button"
				class="button button-secondary uplinkportal-test-connection"
				data-section="<?php echo esc_attr( $section ); ?>"
			>
				<?php echo esc_html__( 'Test Connection', 'uplinkportal' ); ?>
			</button>
			<div class="uplinkportal-validation-result" data-section="<?php echo esc_attr( $section ); ?>" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Returns section labels mapped to a module.
	 *
	 * @param string $module_key Module key.
	 * @return array<int,string>
	 */
	private function get_module_section_labels( string $module_key ): array {
		$map = array(
			'finance'      => array(
				__( 'Revenue & Billing', 'uplinkportal' ),
				__( 'Billing Center', 'uplinkportal' ),
			),
			'it_support'   => array(
				__( 'Support Queue', 'uplinkportal' ),
				__( 'Help Desk', 'uplinkportal' ),
			),
			'projects'     => array(
				__( 'Client Portfolio', 'uplinkportal' ),
				__( 'My Projects', 'uplinkportal' ),
			),
			'custom_dummy' => array(
				__( 'Custom Workspace', 'uplinkportal' ),
				__( 'My Custom Workspace', 'uplinkportal' ),
			),
		);

		return isset( $map[ $module_key ] ) ? $map[ $module_key ] : array();
	}

	/**
	 * Maps module keys to validation sections.
	 *
	 * @param string $module_key Module key.
	 * @return string
	 */
	private function get_validation_section_for_module( string $module_key ): string {
		$model_key = IntegrationModelRegistry::model_for_module( $module_key );
		if ( '' === $model_key ) {
			return '';
		}

		return IntegrationModelRegistry::validation_section_for_model( $model_key );
	}

	/**
	 * Returns client workspace sections mapped from enabled modules.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_client_workspace_sections(): array {
		$service_modules = $this->options->get_service_modules();
		$sections        = array();

		if ( ! empty( $service_modules['projects'] ) ) {
			$sections[] = array(
				'id'          => 'my-projects',
				'label'       => __( 'My Projects', 'uplinkportal' ),
				'description' => __( 'Track project progress, milestones, and delivery updates.', 'uplinkportal' ),
			);
		}

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
				'description' => __( 'Check invoices, account balances, and billing history.', 'uplinkportal' ),
			);
		}

		if ( ! empty( $service_modules['custom_dummy'] ) ) {
			$sections[] = array(
				'id'          => 'my-custom-workspace',
				'label'       => __( 'My Custom Workspace', 'uplinkportal' ),
				'description' => __( 'Dummy custom workspace section to validate module toggle visibility.', 'uplinkportal' ),
			);
		}

		return $sections;
	}

	/**
	 * Returns module labels and IDs used by plan templates.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_template_module_labels(): array {
		return array(
			'finance'      => array(
				'id'    => 101,
				'label' => __( 'Finance', 'uplinkportal' ),
			),
			'it_support'   => array(
				'id'    => 102,
				'label' => __( 'IT Support', 'uplinkportal' ),
			),
			'projects'     => array(
				'id'    => 103,
				'label' => __( 'Projects', 'uplinkportal' ),
			),
			'custom_dummy' => array(
				'id'    => 901,
				'label' => __( 'Dummy Custom Module', 'uplinkportal' ),
			),
		);
	}

	/**
	 * Returns stored plan templates.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_plan_templates(): array {
		if ( null !== $this->plan_templates_cache ) {
			return $this->plan_templates_cache;
		}

		$stored = get_option( self::PLAN_TEMPLATES_OPTION, array() );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			$this->plan_templates_cache = $this->get_default_plan_templates();
			return $this->plan_templates_cache;
		}

		$templates    = array();
		$module_keys  = array_keys( $this->get_template_module_labels() );
		$icon_options = $this->get_plan_icon_options();

		foreach ( $stored as $plan_key => $plan_data ) {
			if ( ! is_array( $plan_data ) ) {
				continue;
			}

			$key   = sanitize_key( (string) $plan_key );
			$label = isset( $plan_data['label'] ) ? sanitize_text_field( (string) $plan_data['label'] ) : '';
			$icon  = isset( $plan_data['icon'] ) ? sanitize_key( (string) $plan_data['icon'] ) : 'portfolio';
			$desc  = isset( $plan_data['description'] ) ? sanitize_text_field( (string) $plan_data['description'] ) : '';
			if ( '' === $key || '' === $label ) {
				continue;
			}

			if ( ! isset( $icon_options[ $icon ] ) ) {
				$icon = 'portfolio';
			}

			$modules = isset( $plan_data['modules'] ) && is_array( $plan_data['modules'] ) ? $plan_data['modules'] : array();
			$allowed = array_values(
				array_filter(
					array_map( 'sanitize_key', $modules ),
					static function ( string $module_key ) use ( $module_keys ): bool {
						return in_array( $module_key, $module_keys, true );
					}
				)
			);

			$templates[ $key ] = array(
				'label'       => $label,
				'description' => $desc,
				'icon'        => $icon,
				'modules'     => $allowed,
			);
		}

		if ( empty( $templates ) ) {
			$this->plan_templates_cache = $this->get_default_plan_templates();
			return $this->plan_templates_cache;
		}

		$this->plan_templates_cache = $templates;
		return $this->plan_templates_cache;
	}

	/**
	 * Returns default plan template map.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_default_plan_templates(): array {
		return array(
			'starter'  => array(
				'label'       => __( 'Starter', 'uplinkportal' ),
				'description' => __( 'Core help desk support for lean teams.', 'uplinkportal' ),
				'icon'        => 'portfolio',
				'modules'     => array( 'it_support' ),
			),
			'growth'   => array(
				'label'       => __( 'Growth', 'uplinkportal' ),
				'description' => __( 'Support and project visibility for scaling operations.', 'uplinkportal' ),
				'icon'        => 'chart-line',
				'modules'     => array( 'it_support', 'projects' ),
			),
			'business' => array(
				'label'       => __( 'Business', 'uplinkportal' ),
				'description' => __( 'Full operations coverage with billing insights.', 'uplinkportal' ),
				'icon'        => 'building',
				'modules'     => array( 'it_support', 'projects', 'finance' ),
			),
		);
	}

	/**
	 * Returns plan key for client user.
	 *
	 * @param int $client_id User id.
	 * @return string
	 */
	private function get_client_plan( int $client_id ): string {
		$templates = $this->get_plan_templates();
		$keys      = array_keys( $templates );
		$fallback  = isset( $keys[0] ) ? (string) $keys[0] : 'starter';
		$plan_key  = $this->client_access_store->get_client_plan( $client_id, $fallback );

		if ( ! array_key_exists( $plan_key, $templates ) ) {
			return isset( $keys[0] ) ? (string) $keys[0] : 'starter';
		}

		return $plan_key;
	}

	/**
	 * Returns override settings for a client.
	 *
	 * @param int               $client_id User id.
	 * @param array<int,string> $module_keys Module keys.
	 * @return array<string,mixed>
	 */
	private function get_client_override_data( int $client_id, array $module_keys ): array {
		return $this->client_access_store->get_override_data( $client_id, $module_keys );
	}

	/**
	 * Returns effective modules for a client.
	 *
	 * @param string              $plan_key Plan key.
	 * @param array<string,mixed> $override_data Override payload.
	 * @return array<int,string>
	 */
	private function get_client_effective_modules( string $plan_key, array $override_data ): array {
		$templates = $this->get_plan_templates();
		$baseline  = isset( $templates[ $plan_key ]['modules'] ) && is_array( $templates[ $plan_key ]['modules'] )
			? $templates[ $plan_key ]['modules']
			: array();

		if ( empty( $override_data['enabled'] ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', $baseline ) ) );
		}

		$modules = isset( $override_data['modules'] ) && is_array( $override_data['modules'] ) ? $override_data['modules'] : array();
		return array_values( array_filter( array_map( 'sanitize_key', $modules ) ) );
	}

	/**
	 * Summarizes module exceptions from plan baseline.
	 *
	 * @param string            $plan_key Plan key.
	 * @param array<int,string> $effective_modules Effective modules.
	 * @return array<int,array<string,string>>
	 */
	private function summarize_client_exceptions( string $plan_key, array $effective_modules ): array {
		$templates       = $this->get_plan_templates();
		$baseline        = isset( $templates[ $plan_key ]['modules'] ) && is_array( $templates[ $plan_key ]['modules'] ) ? $templates[ $plan_key ]['modules'] : array();
		$template_ids    = array_values( array_filter( array_map( 'sanitize_key', $baseline ) ) );
		$current_ids     = array_values( array_filter( array_map( 'sanitize_key', $effective_modules ) ) );
		$module_labels   = $this->get_template_module_labels();
		$added_modules   = array_values( array_diff( $current_ids, $template_ids ) );
		$removed_modules = array_values( array_diff( $template_ids, $current_ids ) );
		$exceptions      = array();

		foreach ( $added_modules as $module_key ) {
			if ( ! isset( $module_labels[ $module_key ] ) ) {
				continue;
			}
			$exceptions[] = array(
				'state' => 'is-added',
				'label' => '+' . (string) $module_labels[ $module_key ]['id'],
			);
		}

		foreach ( $removed_modules as $module_key ) {
			if ( ! isset( $module_labels[ $module_key ] ) ) {
				continue;
			}
			$exceptions[] = array(
				'state' => 'is-removed',
				'label' => '-' . (string) $module_labels[ $module_key ]['id'],
			);
		}

		return $exceptions;
	}

	/**
	 * Returns client users in UplinkPortal client roles.
	 *
	 * @return array<int,\WP_User>
	 */
	private function get_client_workspace_users(): array {
		if ( null !== $this->client_workspace_users_cache ) {
			return $this->client_workspace_users_cache;
		}

		$query_args = array(
			'role__in' => array(
				'uplinkportal_client_owner',
				'uplinkportal_client_manager',
				'uplinkportal_client_project_manager',
				'uplinkportal_client_finance',
				'uplinkportal_client_tech',
				'uplinkportal_client_user',
			),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'fields'   => 'all',
		);
		$query_args = TenantBoundaryScope::apply_user_query_scope( $query_args, $this->options );
		$users      = get_users( $query_args );

		if ( ! is_array( $users ) ) {
			$this->client_workspace_users_cache = array();
			return $this->client_workspace_users_cache;
		}

		$this->client_workspace_users_cache = array_values(
			array_filter(
				$users,
				static function ( $user ): bool {
					return $user instanceof \WP_User;
				}
			)
		);
		return $this->client_workspace_users_cache;
	}

	/**
	 * Resolves selected client id from request with safe fallback.
	 *
	 * @param array<int,\WP_User> $clients Client list.
	 * @param string              $query_key Query-string key.
	 * @return int
	 */
	private function selected_client_id_from_request( array $clients, string $query_key ): int {
		$client_ids = array();
		foreach ( $clients as $client_user ) {
			if ( ! $client_user instanceof \WP_User ) {
				continue;
			}
			$client_ids[] = absint( $client_user->ID );
		}
		$client_ids = array_values( array_filter( $client_ids ) );
		if ( empty( $client_ids ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context selector from query args.
		$selected_client_id = isset( $_GET[ $query_key ] ) ? absint( (string) $_GET[ $query_key ] ) : 0;
		if ( $selected_client_id > 0 && in_array( $selected_client_id, $client_ids, true ) ) {
			return $selected_client_id;
		}

		return (int) $client_ids[0];
	}

	/**
	 * Returns entitlement client context for modules UI.
	 *
	 * @return int
	 */
	private function modules_entitlement_context_client_id(): int {
		$clients = $this->get_client_workspace_users();
		return $this->selected_client_id_from_request( $clients, 'entitlements_client' );
	}

	/**
	 * Whether a user belongs to any UplinkPortal client role.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	private function is_client_workspace_user( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		if ( ! TenantBoundaryScope::is_user_in_scope( $user_id, $this->options ) ) {
			return false;
		}

		foreach ( array( 'uplinkportal_client_owner', 'uplinkportal_client_manager', 'uplinkportal_client_project_manager', 'uplinkportal_client_finance', 'uplinkportal_client_tech', 'uplinkportal_client_user' ) as $role_slug ) {
			if ( in_array( $role_slug, $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns core module labels.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_core_module_labels(): array {
		return array(
			'database'   => array(
				'label'         => __( 'Database', 'uplinkportal' ),
				'description'   => __( 'Licensing, file storage, secrets, user mapping, and backend connection controls.', 'uplinkportal' ),
				'icon'          => 'database',
				'required'      => true,
				'audience'      => 'msp',
				'menu_location' => 'configuration',
			),
			'finance'    => array(
				'label'         => __( 'Finance', 'uplinkportal' ),
				'description'   => __( 'Financial reporting, invoicing workflows, and finance automation recipes.', 'uplinkportal' ),
				'icon'          => 'chart-line',
				'required'      => false,
				'audience'      => 'both',
				'menu_location' => 'operations',
			),
			'it_support' => array(
				'label'         => __( 'IT Support', 'uplinkportal' ),
				'description'   => __( 'Help desk endpoint validation, profile setup, and escalation automations.', 'uplinkportal' ),
				'icon'          => 'shield',
				'required'      => false,
				'audience'      => 'both',
				'menu_location' => 'operations',
			),
			'projects'   => array(
				'label'         => __( 'Projects', 'uplinkportal' ),
				'description'   => __( 'Project system connectors, profile lifecycle controls, and update automations.', 'uplinkportal' ),
				'icon'          => 'portfolio',
				'required'      => false,
				'audience'      => 'both',
				'menu_location' => 'operations',
			),
		);
	}

	/**
	 * Returns custom module labels.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_custom_module_labels(): array {
		return array(
			'custom_dummy' => array(
				'label'         => __( 'Dummy Custom Module', 'uplinkportal' ),
				'description'   => __( 'Sandbox module for testing custom extension points.', 'uplinkportal' ),
				'icon'          => 'admin-generic',
				'required'      => false,
				'audience'      => 'both',
				'menu_location' => 'configuration',
			),
		);
	}

	/**
	 * Redirect helper for role resync action.
	 *
	 * @param string $status Result status.
	 * @return void
	 */
	private function safe_resync_redirect( string $status ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Internal admin-post redirect target.
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) ) : '';
		if ( empty( $redirect_to ) ) {
			$redirect_to = $this->get_configuration_tab_url( 'backend_database' );
		}

		$redirect_to = add_query_arg( 'uplinkportal_role_sync', sanitize_key( $status ), $redirect_to );
		wp_safe_redirect( $redirect_to );
		exit;
	}
}
