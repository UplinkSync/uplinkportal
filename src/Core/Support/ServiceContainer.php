<?php
/**
 * Runtime service container for shared dependencies.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use UplinkPortal\Admin\ModuleMenuRegistrar;
use UplinkPortal\Admin\SettingsPage;
use UplinkPortal\Admin\UI\AgreementsRecordsUi;
use UplinkPortal\Admin\UI\ConfigClientsUi;
use UplinkPortal\Admin\UI\ConfigCompanyInfoUi;
use UplinkPortal\Admin\UI\ConfigModulesUi;
use UplinkPortal\Admin\UI\ConfigPortalPreviewUi;
use UplinkPortal\Admin\UI\ConfigSecurityUi;
use UplinkPortal\Admin\UI\OperationsDashboardUi;
use UplinkPortal\Admin\UI\ServiceCatalogUi;
use UplinkPortal\Core\Licensing\LicensingClient;
use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Security\CentralAuditJournal;
use UplinkPortal\Core\Security\EvidenceTrailStore;
use UplinkPortal\Core\Security\SecurityAlertMonitor;
use UplinkPortal\Core\Services\ProviderCatalogService;
use UplinkPortal\Core\Services\ServiceDefinitionStore;
use UplinkPortal\Core\Telemetry\NoOpTelemetry;
use UplinkPortal\Core\Telemetry\TelemetryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Provides lazily-created runtime services for main bootstrap flow.
 */
class ServiceContainer {
	/**
	 * Shared options service.
	 *
	 * @var Options|null
	 */
	private ?Options $options = null;

	/**
	 * Shared runtime services.
	 *
	 * @var array<string,object>
	 */
	private array $services = array();

	/**
	 * Returns options service.
	 *
	 * @return Options
	 */
	public function options(): Options {
		if ( null === $this->options ) {
			$this->options = new Options();
		}

		return $this->options;
	}

	/**
	 * Returns client documents store.
	 *
	 * @return ClientDocumentsStore
	 */
	public function client_documents(): ClientDocumentsStore {
		return $this->service( 'client_documents', fn() => new ClientDocumentsStore( $this->options() ) );
	}

	/**
	 * Returns client profile store.
	 *
	 * @return ClientProfileStore
	 */
	public function client_profiles(): ClientProfileStore {
		return $this->service( 'client_profiles', fn() => new ClientProfileStore( $this->options() ) );
	}

	/**
	 * Returns client access store.
	 *
	 * @return ClientAccessStore
	 */
	public function client_access_store(): ClientAccessStore {
		return $this->service( 'client_access_store', fn() => new ClientAccessStore() );
	}

	/**
	 * Returns portal customization store.
	 *
	 * @return PortalCustomizationStore
	 */
	public function portal_customization_store(): PortalCustomizationStore {
		return $this->service( 'portal_customization_store', fn() => new PortalCustomizationStore() );
	}

	/**
	 * Returns purchase request store.
	 *
	 * @return PurchaseRequestStore
	 */
	public function purchase_request_store(): PurchaseRequestStore {
		return $this->service( 'purchase_request_store', fn() => new PurchaseRequestStore() );
	}

	/**
	 * Returns entitlement service.
	 *
	 * @return EntitlementService
	 */
	public function entitlement_service(): EntitlementService {
		return $this->service( 'entitlement_service', fn() => new EntitlementService() );
	}

	/**
	 * Returns entitlement enforcer.
	 *
	 * @return EntitlementEnforcer
	 */
	public function entitlement_enforcer(): EntitlementEnforcer {
		return $this->service( 'entitlement_enforcer', fn() => new EntitlementEnforcer( $this->entitlement_service() ) );
	}

	/**
	 * Returns retention scheduler.
	 *
	 * @return DataRetentionScheduler
	 */
	public function retention_scheduler(): DataRetentionScheduler {
		return $this->service( 'retention_scheduler', fn() => new DataRetentionScheduler( $this->options(), $this->client_documents() ) );
	}

	/**
	 * Returns automation recipe runtime gate.
	 *
	 * @return AutomationRecipeRuntimeGate
	 */
	public function automation_recipe_runtime_gate(): AutomationRecipeRuntimeGate {
		return $this->service(
			'automation_recipe_runtime_gate',
			fn() => new AutomationRecipeRuntimeGate( $this->options(), $this->entitlement_enforcer() )
		);
	}

	/**
	 * Returns security alert monitor.
	 *
	 * @return SecurityAlertMonitor
	 */
	public function security_alert_monitor(): SecurityAlertMonitor {
		return $this->service( 'security_alert_monitor', fn() => new SecurityAlertMonitor( $this->options() ) );
	}

	/**
	 * Returns evidence trail store.
	 *
	 * @return EvidenceTrailStore
	 */
	public function evidence_trail_store(): EvidenceTrailStore {
		return $this->service( 'evidence_trail_store', fn() => new EvidenceTrailStore( $this->options() ) );
	}

	/**
	 * Returns central audit journal.
	 *
	 * @return CentralAuditJournal
	 */
	public function central_audit_journal(): CentralAuditJournal {
		return $this->service( 'central_audit_journal', fn() => new CentralAuditJournal() );
	}

	/**
	 * Returns licensing client.
	 *
	 * @return LicensingClient
	 */
	public function licensing_client(): LicensingClient {
		return $this->service( 'licensing_client', fn() => new LicensingClient( $this->options() ) );
	}

	/**
	 * Returns module manager.
	 *
	 * @return ModuleManager
	 */
	public function module_manager(): ModuleManager {
		return $this->service( 'module_manager', fn() => new ModuleManager( $this->options() ) );
	}

	/**
	 * Returns module menu registrar.
	 *
	 * @return ModuleMenuRegistrar
	 */
	public function module_menu_registrar(): ModuleMenuRegistrar {
		return $this->service( 'module_menu_registrar', fn() => new ModuleMenuRegistrar() );
	}

	/**
	 * Returns provider catalog service.
	 *
	 * @return ProviderCatalogService
	 */
	public function provider_catalog_service(): ProviderCatalogService {
		return $this->service( 'provider_catalog_service', fn() => new ProviderCatalogService( $this->options(), $this->entitlement_service() ) );
	}

	/**
	 * Returns service definition store.
	 *
	 * @return ServiceDefinitionStore
	 */
	public function service_definition_store(): ServiceDefinitionStore {
		return $this->service( 'service_definition_store', fn() => new ServiceDefinitionStore() );
	}

	/**
	 * Returns telemetry implementation.
	 *
	 * Defaults to no-op. External integrations can override through filter.
	 *
	 * @return TelemetryInterface
	 */
	public function telemetry(): TelemetryInterface {
		$service = $this->service(
			'telemetry',
			function (): object {
				$telemetry = apply_filters( 'uplinkportal_telemetry_service', null );
				if ( $telemetry instanceof TelemetryInterface ) {
					return $telemetry;
				}

				return new NoOpTelemetry();
			}
		);

		if ( $service instanceof TelemetryInterface ) {
			return $service;
		}

		return new NoOpTelemetry();
	}

	/**
	 * Returns Operations Dashboard UI renderer.
	 *
	 * @return OperationsDashboardUi
	 */
	public function operations_dashboard_ui(): OperationsDashboardUi {
		return $this->service( 'operations_dashboard_ui', fn() => new OperationsDashboardUi( $this->hiring_trigger_service() ) );
	}

	/**
	 * Returns hiring trigger metrics service.
	 *
	 * @return HiringTriggerService
	 */
	public function hiring_trigger_service(): HiringTriggerService {
		return $this->service( 'hiring_trigger_service', fn() => new HiringTriggerService( $this->client_profiles() ) );
	}

	/**
	 * Returns the hiring-gate MRR provider.
	 *
	 * @return MrrProvider
	 */
	public function mrr_provider(): MrrProvider {
		return $this->service( 'mrr_provider', fn() => new MrrProvider() );
	}

	/**
	 * Returns Agreements & Records UI renderer.
	 *
	 * @return AgreementsRecordsUi
	 */
	public function agreements_records_ui(): AgreementsRecordsUi {
		return $this->service( 'agreements_records_ui', fn() => new AgreementsRecordsUi() );
	}

	/**
	 * Returns Service Catalog UI renderer.
	 *
	 * @return ServiceCatalogUi
	 */
	public function service_catalog_ui(): ServiceCatalogUi {
		return $this->service( 'service_catalog_ui', fn() => new ServiceCatalogUi( $this->service_definition_store(), $this->module_manager() ) );
	}

	/**
	 * Returns Configuration > Client Access UI renderer.
	 *
	 * @return ConfigClientsUi
	 */
	public function config_clients_ui(): ConfigClientsUi {
		return $this->service( 'config_clients_ui', fn() => new ConfigClientsUi() );
	}

	/**
	 * Returns Configuration > Modules UI renderer.
	 *
	 * @return ConfigModulesUi
	 */
	public function config_modules_ui(): ConfigModulesUi {
		return $this->service( 'config_modules_ui', fn() => new ConfigModulesUi( $this->module_manager() ) );
	}

	/**
	 * Returns Configuration > Security UI renderer.
	 *
	 * @return ConfigSecurityUi
	 */
	public function config_security_ui(): ConfigSecurityUi {
		return $this->service( 'config_security_ui', fn() => new ConfigSecurityUi() );
	}

	/**
	 * Returns Configuration > Company Info UI renderer.
	 *
	 * @return ConfigCompanyInfoUi
	 */
	public function config_company_info_ui(): ConfigCompanyInfoUi {
		return $this->service( 'config_company_info_ui', fn() => new ConfigCompanyInfoUi() );
	}

	/**
	 * Returns Configuration > Portal Preview UI renderer.
	 *
	 * @return ConfigPortalPreviewUi
	 */
	public function config_portal_preview_ui(): ConfigPortalPreviewUi {
		return $this->service( 'config_portal_preview_ui', fn() => new ConfigPortalPreviewUi( $this->portal_customization_store() ) );
	}

	/**
	 * Returns settings page.
	 *
	 * @return SettingsPage
	 */
	public function settings_page(): SettingsPage {
		return $this->service(
			'settings_page',
			fn() => new SettingsPage(
				$this->options(),
				$this->module_manager(),
				$this->licensing_client(),
				$this->client_documents(),
				$this->client_profiles(),
				$this->client_access_store(),
				$this->portal_customization_store(),
				$this->purchase_request_store(),
				$this->module_menu_registrar(),
				$this->provider_catalog_service(),
				$this->operations_dashboard_ui(),
				$this->agreements_records_ui(),
				$this->service_catalog_ui(),
				$this->config_clients_ui(),
				$this->config_modules_ui(),
				$this->config_security_ui(),
				$this->config_company_info_ui(),
				$this->config_portal_preview_ui()
			)
		);
	}

	/**
	 * Returns one memoized service.
	 *
	 * @param string   $key Service key.
	 * @param callable $builder Service builder.
	 * @return object
	 */
	private function service( string $key, callable $builder ) {
		if ( ! isset( $this->services[ $key ] ) ) {
			$this->services[ $key ] = $builder();
		}

		return $this->services[ $key ];
	}
}
