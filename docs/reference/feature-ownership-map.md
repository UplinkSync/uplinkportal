# Feature Ownership Map

This map defines the single owner for each feature area and removes duplicate surfaces.

| Feature Area | Canonical UI Owner | Responsible Module | Code Entrypoint |
| --- | --- | --- | --- |
| Licensing (configure/validate status) | `Configuration > Modules > Database > Licensing` | `database` (core) | `src/Admin/SettingsPage.php` (`render_database_module_licensing_tab`, `handle_validate_license_action`) |
| Module catalog and enablement | `Configuration > Modules` | Module registry core | `src/Admin/SettingsPage.php` (`render_modules_catalog_field`) |
| Service purchase/manual request creation | `Client Portal > My Services` | Purchase request support | `src/ClientPortal/templates/portal-shell.php`, `src/Admin/SettingsPage.php` (`handle_purchase_request_action`) |
| Provider fulfillment queue | `Operations Dashboard` | Purchase request support | `src/Admin/SettingsPage.php` (`render_operations_dashboard_page`), `src/Core/Support/PurchaseRequestStore.php` |
| Security baseline posture | `Configuration > Security` | Security core | `src/Admin/SettingsPage.php` (`render_security_baseline_panel`) |
| Monitoring mode + alert routing | `Configuration > Security` | Security core | `src/Admin/SettingsPage.php` (`render_monitoring_routing_field`) |
| Monitoring warning notice | Inline in monitoring field (not global banner) | Security core | `src/Admin/SettingsPage.php` (`render_monitoring_routing_field`) |
| Integrations diagnostics (status/lint/harness) | `Tools & Diagnostics` | Integration diagnostics | `src/Admin/SettingsPage.php` (`render_tools_diagnostics_page`) |
| Secret lease alerts (diagnostic view) | `Tools & Diagnostics` | Secrets/compliance diagnostics | `src/Admin/SettingsPage.php` (`render_secret_lease_panel`) |
| Secret storage/configuration | `Configuration > Modules > Database > Secrets` | `database` (core) | `src/Admin/SettingsPage.php` (`render_database_module_secrets_tab`), `src/Core/Support/Options.php` |
| File storage integration/posture | `Configuration > Modules > Database > File Storage` | `database` (core) | `src/Admin/SettingsPage.php` (`render_database_module_file_storage_tab`) |
| User identity/data provider guardrails | `Configuration > Modules > Database > Users` | `database` (core) | `src/Admin/SettingsPage.php` (`render_database_module_users_tab`) |
| Shared admin UI style tokens/layout | Plugin admin wrappers only (`.uplinkportal-admin` / `.msp-admin`) | Admin UI layer | `assets/css/admin-console.css` |
| Client access entitlements | `Configuration > Client Access > Client Entitlements` | Entitlement store + client access store | `src/Admin/SettingsPage.php` (`render_client_entitlements_panel`, `handle_client_entitlements_save_action`), `src/Core/Support/EntitlementService.php`, `src/Core/Support/ClientAccessStore.php` |
| Company profile + WP settings mirror | `Configuration > Company Info` | Core settings | `src/Admin/SettingsPage.php` (`render_customization_tab`, `sync_company_info_to_wp_options`) |
| Portal preview and branding draft storage | `Configuration > Portal Preview` | Portal customization store | `src/Admin/SettingsPage.php` (`render_client_portal_preview_tab`), `src/Core/Support/PortalCustomizationStore.php` |
| Dynamic module menu placement policy | Central registrar (not module-local menu calls) | Module menu registrar | `src/Admin/ModuleMenuRegistrar.php` |

Notes:
- Payment collection functionality is not present in public core.
- Finance scope in public core is reporting and invoicing visibility, not payment collection.
