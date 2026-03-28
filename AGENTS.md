# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Single source of truth** for all AI coding assistants (Claude Code, GitHub Copilot, OpenAI Codex, etc.).
> Symlinked as `CLAUDE.md`, `CODEX.md`, and `.github/copilot-instructions.md`.

---

## Agent Protocol

- Analyze requests for ambiguity before executing. Ask clarifying questions when the goal is unclear.
- If the user provides new constraints or project-specific rules, update `AGENTS.md` to maintain continuity.
- Do not hallucinate — if unsure, ask.

---

## Scope Guardrails — Public-Core-Only Policy

- This repository is **public core only**.
- Never implement, infer, or backfill premium/paywalled functionality.
- Pro functionality must be represented only by placeholder contracts/stubs in `src/Core/Modules/Pro/`
  with clear `TODO: Implement in private/pro repository only.` markers.
- Core modules in `src/Core/Modules/Core/` are free and must remain public-safe.
- Pro modules require a separate private add-on and license key flow; do not implement them here.
- Custom modules in `src/Core/Modules/Custom/` are MSP-owner controlled; do not imply liability
  coverage from the root plugin owner/company.
- Do not add licensing enforcement, premium unlock logic, proprietary API clients, or
  secret-dependent flows.
- Never commit secrets, tokens, or private URLs. Keep `.env` untracked; commit only
  placeholder-only values in `.env.example`.

---

## Security-by-Default Rules

- Enforce capability checks (`current_user_can`) for every privileged admin and REST operation.
- Enforce authentication and authorization in all REST permission callbacks.
- Require nonces for every state-changing request.
- Sanitize all input (`sanitize_text_field`, `sanitize_key`, `absint`, etc.).
- Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Never render `TODO:`/`FIXME` placeholder text in user-facing UI.

---

## Coding Standards

- Follow WordPress Coding Standards (WPCS) and maintain clear docblocks.
- Target **PHP 8.3+** and **WordPress 6.x**.
- Use i18n for all user-facing strings (`__()`, `esc_html__()`, etc.) with text domain `uplinkportal`.
- Capability naming convention:
  - `msp_*` for MSP operator capabilities.
  - `client_*` for client portal capabilities.
- Role display labels: `MSP * (UplinkPortal)` / `Client * (UplinkPortal)`.

---

## Repository Structure

Use this layout for all new runtime code:

| Path | Purpose |
| ------ | --------- |
| `uplinkportal.php` | Main plugin bootstrap |
| `/includes` | Runtime hook modules (`class-main-module.php`, `class-admin.php`, `class-frontend.php`, `class-ajax.php`, `class-rest.php`, `class-assets.php`) |
| `src/Core` | Activation/deactivation and core support classes |
| `src/Admin` | wp-admin pages, settings, notices |
| `src/ClientPortal` | Shortcode/template/client-facing portal logic |

- Namespace root: `UplinkPortal\`.
- Use PSR-4 structure under `src/` (as defined in `composer.json`).
- Keep plugin bootstrap in `uplinkportal.php`.
- Reuse `class-base-module.php` as the shared base for include-layer modules to avoid duplicated
  hook/filter boilerplate.
- REST namespace is `uplinkportal/v1`. Hook names and function prefixes must begin with `uplinkportal_`.

---

## Architecture Conventions

### Plugin Bootstrap Flow

`uplinkportal.php` → `UplinkPortal_Bootstrap::run()` (on `plugins_loaded`) → `UplinkPortal_Main_Module::init()` → `ServiceContainer` (lazy singleton factory).

`ServiceContainer` (`src/Core/Support/ServiceContainer.php`) is the central dependency factory. All shared services (`Options`, stores, `ModuleManager`, `LicensingClient`, etc.) are created once and memoized there. Add new shared services to `ServiceContainer` rather than constructing them inline.

The include-layer modules (`includes/class-*.php`) handle WordPress hook registration in layers: Admin, Frontend, Ajax, REST, Assets. They extend `UplinkPortal_Base_Module` and are instantiated inside `Main_Module::init()`.

### Conventions

- Register hooks in constructors or explicit `init()`/`register()` methods.
- Module registration via `apply_filters( 'uplinkportal_modules', [] )`.
- Custom module registration via `apply_filters( 'uplinkportal_custom_modules', [] )`.
- Modules must implement `UplinkPortal\Core\Modules\ModuleInterface`.
- Dynamic module admin menu entries must go through `uplinkportal_module_menu_entries` and the
  centralized registrar (`src/Admin/ModuleMenuRegistrar.php`).
- Store client access and external client mappings in custom tables via
  `src/Core/Support/ClientAccessStore.php` — not option/meta blobs.
- Connection validation logic must run in secure backend/add-on services via
  `uplinkportal_backend_validation_result`.
- Private backend flows (validation, document key exchange, OAuth token lifecycle, Vault execution)
  stay out of this repo.
- For local or WordPress Playground simulation, prefer filter-driven mocks over embedding backend
  logic:
  - `uplinkportal_backend_validation_result`
  - `uplinkportal_client_documents_key_endpoint`
  - `uplinkportal_client_documents_encryption_key`

---

## Admin UX Conventions

- Admin information architecture must remain centred on:
  - `Operations Dashboard`
  - `Agreements & Records`
  - `Service Catalog`
  - `Configuration`
  - `Tools & Diagnostics`
- Configuration tabs (exact labels, exact order):
  `Client Access` → `Modules` → `Security` → `Company Info` → `Portal Preview`
- Keep licensing only in `Configuration > Modules > Database > Licensing`.
- Keep plan templates in Service Catalog; keep per-client exception overrides in Client Access.
- Core always-visible admin sections: `Agreements & Records`, `Service Catalog`.
- Core always-visible client sections: `Documents & Agreements`, `My Services`.

---

## Commands

```bash
# Lint (PHPCS + cspell + UI placeholder scan)
composer lint

# Auto-fix PHPCS violations
composer phpcbf

# Run all PHPUnit tests
composer test

# Run a single test file
vendor/bin/phpunit -c tests/phpunit.xml.dist tests/SpecificTest.php

# UI placeholder scan only
composer check-ui-placeholders

# Repository hygiene scan (secrets, pro-boundary, payment terms)
./infrastructure/scripts/repo-hygiene-scan.sh

# Worker TypeScript typecheck
cd infrastructure/worker && npm run typecheck
```

`composer install` is required before running any composer scripts if `vendor/` is absent.
The test suite mocks WordPress functions via `tests/config/stubs-support.php` — no live WP install needed for unit tests.

---

## Development Workflow

- Primary editing workflow: **VS Code**.
- For interactive WordPress checks, use a local WP environment or **WordPress Playground**.
- Keep Playground/testing helpers in public-core-safe filter hooks; never embed private backend code.
- `.env` is untracked. Copy `.env.example` and inject values via `wp-config.php`, a must-use plugin, or deployment tooling — the plugin does not auto-load `.env`.

---

## Contribution & Review Requirements

- Add or update tests for every behavioral change.
- Ensure the following pass before submitting:
  - `composer lint` (PHPCS + cspell + UI placeholder scan)
  - `composer test` (PHPUnit)
  - `./infrastructure/scripts/repo-hygiene-scan.sh`
  - Worker typecheck (`infrastructure/worker`, `npm run typecheck`)
  - `composer check-ui-placeholders`
- Update affected documentation when interfaces, hooks, or security behavior changes
  (`README.md`, `docs/index.md`, `docs/development/architecture.md`, relevant domain docs).
- Keep `docs/development/project-todo.md` synchronized: mark tasks `[x]` only after objective
  verification and include a `Verified:` evidence note.
- If a requested change is premium in nature: provide placeholder-only public scaffolding.

---

## Refactoring Guidelines

- Prefer moving existing classes over rewriting behavior.
- Update namespace imports and docblocks when file paths or class names change.
- Update documentation when file paths or class names change.
- Run `composer phpcs`, `composer test`, and `composer check-ui-placeholders` before finalizing.

---

*This file is the canonical source. `CLAUDE.md`, `CODEX.md`, and `.github/copilot-instructions.md`
are symlinks pointing here. Run `setup_agents_md.sh` to (re-)create those symlinks in a new
environment.*
