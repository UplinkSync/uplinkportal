# UplinkPortal

UplinkPortal is a WordPress plugin for a single-site MSP portal deployment where the MSP provider hosts the site and directs clients to it.

## Product IA (Source of Truth)

Top-level admin sections:

- `Operations Dashboard`
- `Agreements & Records`
- `Service Catalog`
- `Configuration`
- `Tools & Diagnostics`

`Configuration` is a single hub page with server-rendered tabs (`admin.php?page=uplinkportal-settings&tab=<key>`):

1. `Client Access`
2. `Modules`
3. `Security`
4. `Company Info`
5. `Portal Preview`

Licensing is owned by `Configuration > Modules > Database > Licensing`.
Only canonical admin routes and submenu slugs are registered.

## Admin UI Style Direction

Admin pages follow an Enterprise / Modern SaaS direction while remaining WordPress-native:

- Scoped wrapper: `.uplinkportal-admin` / `.msp-admin` only.
- Shared tokens in `assets/css/admin-console.css` (`--msp-space-*`, `--msp-radius`, `--msp-border`, `--msp-surface`, `--msp-surface-muted`, `--msp-text`, `--msp-text-muted`, `--msp-accent`).
- WP patterns first (`nav-tab-wrapper`, `widefat`, inline notices, button classes).
- Module Catalog shows concise cards only: module name, short description, status, primary action.

## Public-Core Boundary

This repository is public scaffolding.

- Paid/proprietary logic is not implemented here.
- Entitlement-sensitive behavior is delegated to secure backend/private add-ons.
- Add-on module cards in this repo are UI scaffolds only.

## Key Runtime Components

- Entry point: `uplinkportal.php`
- Runtime includes: `includes/`
- Admin and settings: `src/Admin/`
- Client portal: `src/ClientPortal/`
- Core modules and support: `src/Core/`
- Worker and infra helpers: `infrastructure/`

## Client Access Data Model

Client access now uses custom WordPress tables (not user-meta/options for entitlement state):

- `*_uplinkportal_client_identity_map`
- `*_uplinkportal_client_module_access`
- `*_uplinkportal_client_feature_flags` (optional feature-flag granularity)

Runtime schema management:

- Activation hook: `src/Core/Activator.php`
- Upgrade/runtime assurance: `includes/class-main-module.php`
- Store: `src/Core/Support/ClientAccessStore.php`

Reference SQL:

- `infrastructure/wordpress/migrations/202602170001_client_access_tables.sql`

## Portal Preview Storage

Portal preview customization is stored in a private directory (not in public uploads):

- Store: `src/Core/Support/PortalCustomizationStore.php`
- Path: `wp-content/uplinkportal-private/portal-preview/`

## Development

Requirements:

- PHP `8.3+`
- WordPress `6.x`

Commands:

```bash
npm install --no-save cspell
composer lint
composer test
```

## CI

Workflows:

- `.github/workflows/ci.yml`
  - PHP lint
  - Composer lint (PHPCS + cspell)
  - PHPUnit
  - repository hygiene scan (secrets/pro-boundary checks)
- `.github/workflows/release-plugin.yml`
- `.github/workflows/db-migrate.yml`
- `.github/workflows/deploy-worker.yml`

## Documentation

- [Docs hub](docs/index.md)
- [Installation](docs/getting-started/installation.md)
- [Quickstart](docs/getting-started/quickstart.md)
- [Configuration](docs/getting-started/configuration.md)
- [Troubleshooting](docs/troubleshooting/index.md)
- [Contributing](CONTRIBUTING.md)

## CI/CD pipeline

- **CI**: shared template from [`***`](https://***/***) — php lint, phpcs, phpunit (composer scripts), gitleaks, esbuild asset build, per-MR review app with the plugin active.
- **Previews**: every MR gets a disposable WordPress at `https://<branch-slug>.***`
  ("View app" on the MR; basic-auth credentials in Vault `***`).
- **Merges**: `main` is protected; merging requires a green pipeline; humans merge, agents don't.
- **GitHub**: CI-verified `main` auto-mirrors to [`github.com/irwindoug/uplinkportal`](https://github.com/irwindoug/uplinkportal), where GitHub Actions can deploy the plugin to a WordPress site once `REMOTE_PLUGIN_PATH` is configured.

## Contributing

Branch from `main` (Paperclip agents: `paperclip/<task-id>`), open an MR, let the pipeline
run, use the preview link to check the result. Never commit literal secrets.

Full runbook: `***` → `docs/103-detail-gitlab-github-hostinger-pipeline.md`.
