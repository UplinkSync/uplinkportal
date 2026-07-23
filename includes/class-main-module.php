<?php
/**
 * Main runtime module.
 *
 * @package UplinkPortal
 */

use UplinkPortal\Admin\Notices;
use UplinkPortal\Admin\OnboardingPage;
use UplinkPortal\Admin\SettingsPage;
use UplinkPortal\ClientPortal\AccessGate;
use UplinkPortal\ClientPortal\Shortcode;
use UplinkPortal\Core\Modules\Core\FinanceModule;
use UplinkPortal\Core\Modules\Core\ItSupportModule;
use UplinkPortal\Core\Modules\Core\ProjectsModule;
use UplinkPortal\Core\Modules\Custom\DummyCustomModule;
use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\ClientPortal\Rest\PortalController;
use UplinkPortal\Core\Licensing\LicensingClient;
use UplinkPortal\Core\Security\CentralAuditJournal;
use UplinkPortal\Core\Security\CapabilityManager;
use UplinkPortal\Core\Security\EvidenceTrailStore;
use UplinkPortal\Core\Security\SecurityAlertMonitor;
use UplinkPortal\Core\Support\ClientProfileStore;
use UplinkPortal\Core\Support\ClientDocumentsStore;
use UplinkPortal\Core\Support\ClientAccessStore;
use UplinkPortal\Core\Support\DataRetentionScheduler;
use UplinkPortal\Core\Support\EntitlementService;
use UplinkPortal\Core\Support\Options;
use UplinkPortal\Core\Support\PortalCustomizationStore;
use UplinkPortal\Core\Support\PurchaseRequestStore;
use UplinkPortal\Core\Support\ServiceContainer;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps plugin services in modular layers.
 */
class UplinkPortal_Main_Module extends UplinkPortal_Base_Module {
	/**
	 * Runtime service container.
	 *
	 * @var ServiceContainer
	 */
	private ServiceContainer $service_container;

	/**
	 * Shared options instance.
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
	 * Settings page module.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

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
	 * Client access store.
	 *
	 * @var ClientAccessStore
	 */
	private ClientAccessStore $client_access_store;

	/**
	 * Portal customization private store.
	 *
	 * @var PortalCustomizationStore
	 */
	private PortalCustomizationStore $portal_customization_store;

	/**
	 * Purchase request store.
	 *
	 * @var PurchaseRequestStore
	 */
	private PurchaseRequestStore $purchase_request_store;

	/**
	 * Entitlement service.
	 *
	 * @var EntitlementService
	 */
	private EntitlementService $entitlement_service;

	/**
	 * Data retention scheduler.
	 *
	 * @var DataRetentionScheduler
	 */
	private DataRetentionScheduler $retention_scheduler;

	/**
	 * Security alert monitor.
	 *
	 * @var SecurityAlertMonitor
	 */
	private SecurityAlertMonitor $security_alert_monitor;

	/**
	 * Evidence trail store.
	 *
	 * @var EvidenceTrailStore
	 */
	private EvidenceTrailStore $evidence_trail_store;

	/**
	 * Central audit journal.
	 *
	 * @var CentralAuditJournal
	 */
	private CentralAuditJournal $central_audit_journal;

	/**
	 * Initializes plugin modules and hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_action( 'init', array( $this, 'load_textdomain' ), 0 );

		$this->service_container = new ServiceContainer();
		$this->options           = $this->service_container->options();
		$this->options->ensure_schema();
		$this->options->ensure_instance_id();
		$this->service_container->telemetry()->record_event(
			'plugin_bootstrap',
			array(
				'module'  => 'uplinkportal_main',
				'version' => UPLINKPORTAL_VERSION,
			)
		);
		$this->client_documents           = $this->service_container->client_documents();
		$this->client_profiles            = $this->service_container->client_profiles();
		$this->client_access_store        = $this->service_container->client_access_store();
		$this->portal_customization_store = $this->service_container->portal_customization_store();
		$this->purchase_request_store     = $this->service_container->purchase_request_store();
		$this->entitlement_service        = $this->service_container->entitlement_service();
		$this->client_documents->ensure_schema();
		$this->client_access_store->ensure_schema();
		$this->entitlement_service->ensure_schema();
		$this->portal_customization_store->ensure_storage_ready();
		$this->purchase_request_store->ensure_schema();
		$this->client_documents->register_hooks();
		$this->client_profiles->register_hooks();
		$this->retention_scheduler = $this->service_container->retention_scheduler();
		$this->retention_scheduler->register_hooks();
		$this->service_container->automation_recipe_runtime_gate()->register_hooks();
		$this->security_alert_monitor = $this->service_container->security_alert_monitor();
		$this->security_alert_monitor->register_hooks();
		$this->evidence_trail_store = $this->service_container->evidence_trail_store();
		$this->evidence_trail_store->register_hooks();
		$this->central_audit_journal = $this->service_container->central_audit_journal();
		$this->central_audit_journal->register_hooks();
		$this->service_container->mrr_provider()->register_hooks();
		$this->licensing_client = $this->service_container->licensing_client();
		$this->module_manager   = $this->service_container->module_manager();
		$this->settings_page    = $this->service_container->settings_page();

		$this->register_filter( 'uplinkportal_modules', array( $this, 'register_builtin_modules' ) );
		$this->register_filter( 'uplinkportal_custom_modules', array( $this, 'register_builtin_custom_modules' ) );
		$this->module_manager->register_default_modules();

		$admin_module    = new UplinkPortal_Admin_Module( $this->settings_page, new OnboardingPage(), new Notices() );
		$frontend_module = new UplinkPortal_Frontend_Module(
			new Shortcode( $this->module_manager, $this->options, $this->client_documents, $this->portal_customization_store ),
			new AccessGate( $this->options )
		);
		$ajax_module     = new UplinkPortal_Ajax_Module( $this->settings_page );
		$rest_module     = new UplinkPortal_Rest_Module( new PortalController( $this->module_manager, $this->client_profiles ) );
		$assets_module   = new UplinkPortal_Assets_Module();

		$admin_module->register();
		$frontend_module->register();
		$ajax_module->register();
		$rest_module->register();
		$assets_module->register();

		$this->register_action( 'init', array( CapabilityManager::class, 'register_caps_for_runtime_checks' ) );
		$this->register_filter( 'uplinkportal_enforce_rest_nonce', array( $this, 'filter_rest_nonce_enforcement' ) );
		$this->register_filter( 'uplinkportal_feature_enabled', array( $this, 'filter_feature_enabled' ), 10, 2 );
	}

	/**
	 * Registers main module hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->init();
	}

	/**
	 * Loads plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			UPLINKPORTAL_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( UPLINKPORTAL_FILE ) ) . '/languages'
		);
	}

	/**
	 * Registers built-in free modules.
	 *
	 * @param array<int,mixed> $modules Existing modules.
	 * @return array<int,mixed>
	 */
	public function register_builtin_modules( array $modules ): array {
		$modules[] = new FinanceModule();
		$modules[] = new ItSupportModule();
		$modules[] = new ProjectsModule();
		return $modules;
	}

	/**
	 * Registers dummy custom module in public core.
	 *
	 * @param array<int,mixed> $modules Existing custom modules.
	 * @return array<int,mixed>
	 */
	public function register_builtin_custom_modules( array $modules ): array {
		$modules[] = new DummyCustomModule();
		return $modules;
	}

	/**
	 * Uses saved configuration to enforce strict REST nonce checks by default.
	 *
	 * @param bool $enforce Default state from filter chain.
	 * @return bool
	 */
	public function filter_rest_nonce_enforcement( bool $enforce ): bool {
		return $this->options->get_strict_rest_nonce() && $enforce;
	}

	/**
	 * Feature gating callback.
	 *
	 * @param bool   $enabled Existing state.
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public function filter_feature_enabled( bool $enabled, string $feature ): bool {
		if ( ! $enabled ) {
			return false;
		}

		return $this->licensing_client->is_feature_enabled( $feature );
	}
}
