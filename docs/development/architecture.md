# UplinkPortal Architecture

## Product Model

UplinkPortal is designed for a single WordPress site operated by one MSP provider, with multiple client users/accounts on that site.

Public repository boundary:
- Public repo contains UI/framework scaffolding and extension contracts.
- Paid or proprietary behavior must be delegated to secure backend/private add-ons.
- Entitlement-sensitive runtime decisions should use backend flags and filters, not hard-coded paid logic in this repo.

## Admin Information Architecture

Top-level admin menu:
- `Operations Dashboard`
- `Agreements & Records`
- `Service Catalog`
- `Configuration`

Configuration hub is server-rendered (`admin.php?page=uplinkportal-settings&tab=<key>`) with tab labels:
1. `Client Access`
2. `Modules`
3. `Security`
4. `Company Info`
5. `Portal Preview`

Additional admin destination:
- `Tools & Diagnostics` (integration status, policy lint, test harness, secret lease alerts)

Licensing ownership:
- Licensing is configured only in `Configuration > Modules > Database > Licensing`.
- Only canonical settings routes and submenu slugs are registered.

## Admin UI System

Shared admin UI styling is centralized in `assets/css/admin-console.css` and scoped to plugin wrappers only (`.uplinkportal-admin` / `.msp-admin`).

Design constraints:
- Enterprise / Modern SaaS presentation, still native to WordPress admin.
- Neutral surfaces with subtle borders/elevation.
- Shared spacing/radius/color tokens (`--msp-space-*`, `--msp-radius`, `--msp-border`, `--msp-surface`, `--msp-surface-muted`, `--msp-text`, `--msp-text-muted`, `--msp-accent`).
- No global WP re-skin or dark-mode override blocks.
- Tables must remain readable at narrow widths via local overflow containers (`.uplinkportal-table-scroll`).
- Every core admin screen includes a compact quick-actions rail for cross-screen routing (`Client Access`, `Modules`, `Security`, `Service Catalog`, `Tools & Diagnostics`).
- Tools & Diagnostics includes a status snapshot strip that summarizes connected models, token health, and policy readiness before detailed tables.

## Module and Menu System

Module registration:
- `uplinkportal_modules` (core/public modules)
- `uplinkportal_custom_modules` (owner-installed custom modules)

Module menu registration:
- `uplinkportal_module_menu_entries`
- centralized enforcement in `src/Admin/ModuleMenuRegistrar.php`

Guardrails:
- duplicate/reserved slugs are blocked
- invalid locations are normalized
- non-MSP module menu entries are not added to admin menu
- unknown routes can redirect to `Configuration > Modules`

## Client Access and Identity Data Model

Client access state is stored in custom WP tables via `src/Core/Support/ClientAccessStore.php`:
- `*_uplinkportal_client_identity_map`
- `*_uplinkportal_client_module_access`
- `*_uplinkportal_client_feature_flags`

This supports:
- per-client plan assignment
- per-client module enable/disable/inherit state
- optional per-feature flags
- external client system mapping (`external_system`, `external_client_id`)

Runtime schema:
- activation: `src/Core/Activator.php`
- runtime assurance: `includes/class-main-module.php`
- reference SQL: `infrastructure/wordpress/migrations/202602170001_client_access_tables.sql`

## Security and Storage Boundaries

- Client document storage remains encrypted and private.
- Portal preview customization is stored in private filesystem path via `src/Core/Support/PortalCustomizationStore.php`:
  - `wp-content/uplinkportal-private/portal-preview/settings.json`
- No sensitive backend secrets or paid feature implementation details should be committed.

## Source of Truth Docs

- `README.md`
- `../guides/admin-information-architecture.md`
- `../reference/module-schema.md`
- `../guides/security-gdpr-best-practices.md`
- `ci.md`
