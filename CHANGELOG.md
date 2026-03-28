# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-02-23

### Added
- Centralized monitoring design document covering architecture, data flow, and compliance dashboard scope.
- Entitlement enforcement (single-site) with 7-day grace support and runtime recipe gate.
- Catalog-driven provider selection with entitlement-aware client context.
- Database Configurations profile switcher flow (`Licensing`, `File Storage`, `Secrets`, `Users`).
- Recipe revision snapshots with rollback-on-save controls in Modules Automation Recipes.
- Baseline no-code automation recipe catalog with compliance labels and dry-run/live mode.
- Security alert monitor with backend-forwardable SIEM pipeline.
- Tamper-evident evidence trail chain with integrity status in Security UI.
- Deterministic pseudonymization for audit messages, IPs, and user agents.
- Centralized security audit journal panel with retention purge integration.
- Multisite tenant-boundary controls (site isolation / network-shared mode with super-admin gate).
- Data protection at-rest/key-management policy controls (KMS provider, rotation interval, etc.).
- Daily retention purge scheduler for plugin audit stores.
- GDPR legal-basis controls per model.
- Model-level RBAC policy matrix for integration actions.
- Compliance policy bundle selection (`msp_default`, `gdpr_baseline`, `hipaa_baseline`).
- Integration profile promotion state workflow (`draft`, `reviewed`, `active`).
- Modules integration profile test harness summary.
- Modules integration policy lint checks with severity-grouped findings.
- Vault-first secret-manager configuration in Modules.
- Secret lease/expiry alert panel in Tools & Diagnostics.
- Delegated OAuth migration controls with opaque OAuth connection reference storage.
- Versioned connector schema baseline docs.
- Guided integration setup workflow with model/provider templates.
- Per-model integration profile abstraction with Modules settings UI.
- Backend service token decoupled from billing connector API key.
- Canonical integration model registry reused across modules and admin flows.
- Admin action/UI to delete client documents with capability checks and nonce.
- Guided role setup screen with plain-language explanations.
- Settings validation/admin error notices for invalid config.
- Contextual help tabs for Operations Dashboard and Configuration screens.
- Client Portfolio list with edit flow gated on backend prerequisites.
- No-JS fallback notices and accessibility improvements to portal.
- Loading/error/empty states for portal module data rendering.
- Service container/factory abstraction for improved testability.
- Module capability enforcement helper in ModuleManager.
- Security regression tests for access control.
- WP-CLI diagnostics evaluation document.
- No-op telemetry interface.
- SettingsPage split into dedicated Admin UI classes for maintainability.

### Changed
- Module connection/compatibility validation moved to secure backend delegation (`uplinkportal_backend_validation_result`).
- Secrets provider options are catalog-driven; legacy database license sections removed from Database Configurations.

## [0.1.0] - 2025-12-01

### Added
- Initial public core scaffolding release.
- Bootstrap plugin entrypoint and service wiring.
- Module contract and module manager scaffolding.
- Core REST scaffolding under `uplinkportal/v1`.
- Free core modules: `finance`, `it_support`, `projects`.
- Custom module scaffolding (`src/Core/Modules/Custom/`).
- Top-level admin menu with `Operations Dashboard` and `Configuration` submenus.
- Configuration tabs: `Client Access`, `Modules`, `Security`, `Company Info`, `Portal Preview`.
- `[uplinkportal]` shortcode and shell template with theme override support.
- Connector interfaces for ticketing, billing, and subscriptions with dummy connectors.
- REST permission callbacks, capability checks, and nonce helper abstraction.
- PHPCS, PHPUnit scaffolding, and GitHub Actions CI workflow.
- Pro placeholder interfaces/classes (no premium logic).
- Public-core-policy documentation.

[Unreleased]: https://github.com/example/uplinkportal/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/example/uplinkportal/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/example/uplinkportal/releases/tag/v0.1.0
