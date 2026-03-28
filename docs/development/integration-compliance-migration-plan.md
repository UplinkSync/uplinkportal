# UplinkPortal Integration & Compliance Migration Plan TODO

Purpose: transition the current core-model integrations from developer-heavy custom wiring into a non-technical, low-code, security-first, and compliance-ready ecosystem.

Scope: `finance` (reporting/invoicing), `it_support` (helpdesk), `projects` (pm), licensing/entitlements, client documents.

## 1) Current-State Integration Analysis

### Current core-model mapping
- `finance` -> validation section `billing` and connector contract in public core.
- `it_support` -> validation section `helpdesk` and connector contract in public core.
- `projects` -> validation section `pm` and connector contract in public core.
- licensing -> Cloudflare Worker endpoints for license validation and entitlement refresh.
- documents -> encrypted file storage with backend key endpoint dependency.

### Current integration strengths
- Core integration contracts and module boundaries exist.
- Backend URL + per-section validation profiles are configurable in admin.
- Sensitive keys are handled via plugin secret storage abstraction.
- Licensing and document storage already enforce capability-based access paths.

### Current integration gaps for non-technical operation
- Integration setup is still endpoint/key centric, not wizard/template driven.
- No reusable connector catalog with one-click provider onboarding.
- No OAuth 2.0 token exchange/refresh management in the integration layer.
- No admin-facing policy engine for RBAC + data minimization by model.
- Monitoring/audit is split; no centralized connector security telemetry.

## 2) Low-Code / iPaaS Recommendation Matrix

### Recommended target split
- Primary self-hosted iPaaS for MSP control: `n8n` (hardened deployment + guided templates)
- Managed low-code option for non-technical teams: `Workato`
- SMB/fast adoption iPaaS: `Make` or `Zapier`
- Regulated/complex enterprise and hybrid: `MuleSoft`

### Platform fit for UplinkPortal
- Workato:
  - Best fit when handling many SaaS systems, governance, and lifecycle controls.
  - Strong recipe governance, environment separation, and enterprise connectors.
- Zapier:
  - Best for quick non-technical automations and lightweight workflows.
  - Not ideal as the only platform for strict HIPAA-heavy enterprise controls.
- MuleSoft:
  - Best for complex canonical APIs, heavy transformation, and strict enterprise architecture.
  - Higher cost/complexity; suitable for larger MSPs with integration teams.
- n8n:
  - Best if you want self-hosted control and lower ongoing platform cost.
  - Requires stronger in-house operational/security ownership.

### Decision recommendation by maturity
- Phase 1-2: n8n (self-hosted) with starter blueprints for non-technical admins.
- Phase 1-2 fallback: Workato (managed) when hosted operations are not available.
- Phase 3+: MuleSoft for large enterprise customers with strict governance requirements.

## 3) Multi-Phase Migration TODO

## Phase 0: Foundation Hardening (Immediate)
- [x] Freeze direct third-party API credential usage in module business logic.
- [x] Define canonical integration model registry:
  - `finance`, `it_support`, `projects`, `licensing`, `documents`.
- [x] Add admin-visible integration status board per model:
  - Connected, token health, last sync, policy status.
- [x] Document data classification matrix per model:
  - Public/internal/confidential/regulated (GDPR/HIPAA scoped).

## Phase 1: Configurable Integration Layer
- [x] Create `Integration Profile` abstraction per model:
  - provider, auth type, scopes, endpoint alias, retention policy.
- [x] Add UI-driven provider templates:
  - Billing providers, helpdesk systems, project systems, storage targets.
- [x] Replace manual URL/key setup with guided setup wizard:
  - pick provider -> authorize -> map fields -> test -> activate.
- [x] Add versioned connector schema docs for each model payload.

## Phase 2: Authentication & Secrets Modernization
- [~] Move from static API keys to OAuth 2.0 (Authorization Code + PKCE) where supported.
  - Public core now supports delegated OAuth references + per-profile auth type (`oauth*`, `hybrid`, `api_key`).
- [~] Implement token vault pattern:
  - access token (short-lived), refresh token (encrypted), automatic rotation.
  - Public core stores opaque OAuth connection references only; token issuance/refresh/rotation is delegated to secure backend/add-on.
- [~] Integrate external secret manager:
  - Preferred: HashiCorp Vault.
  - Alternatives: AWS Secrets Manager, Azure Key Vault, GCP Secret Manager.
  - Public core now includes Vault-first Security-tab settings (provider + non-sensitive Vault routing fields + opaque Vault connection reference).
  - Final Vault auth flow, lease renewal, and secret retrieval remain delegated to secure backend/add-on services.
- [~] Remove long-lived secrets from WP options except opaque references.
  - OAuth connection references are now stored in separate secret options, not in the unified settings payload.
- [x] Add secret lease/expiry checks and alerting.
  - Security tab now surfaces stale/unknown-age secret alerts with configurable lease threshold filter.

## Phase 3: RBAC + Compliance Control Plane
- [x] Define model-level RBAC policies:
  - who can connect, read, sync, export, delete per model.
- [x] Add policy bundles:
  - GDPR baseline, HIPAA baseline, MSP-default baseline.
- [x] Add consent/legal basis metadata for data pull/push automations.
- [x] Add data minimization controls:
  - field-level allowlists and masking in logs/UI.
- [x] Add tenant boundary controls for MSP multi-client isolation.

## Phase 4: Data Protection Architecture
- [~] Enforce encryption in transit:
  - TLS 1.2+, cert validation, pinning where feasible for critical endpoints.
- [~] Enforce encryption at rest:
  - Admin policy controls now track/enforce at-rest status plus database/document encryption mode (`src/Core/Support/Options.php`, `src/Admin/SettingsPage.php`).
  - Lint + harness now fail/warn when at-rest posture is missing for regulated policy bundles.
  - Final infrastructure enforcement (database TDE/KMS + object storage SSE-KMS execution) remains delegated to secure backend/add-on/hosting environment.
- [~] Add key management policy:
  - Added key-management settings for provider, rotation interval, separation of duties, and emergency revoke runbook URL.
  - Lint checks now flag weak/absent key policy posture by baseline.
  - Final key rotation/revoke execution and attestations remain delegated to secure backend/add-on.
- [x] Add tokenization/pseudonymization for sensitive identifiers in logs and analytics.
  - Public core now pseudonymizes audit message patterns, IP addresses, and user-agent identifiers before storage.
- [x] Add retention + purge scheduler per model and legal policy.
  - Public core now runs a daily retention purge for plugin audit stores using model retention profile keys as policy input.

## Phase 5: Monitoring, Audit, and Incident Readiness
- [x] Centralize audit logs:
  - integration connects/disconnects, token events, sync jobs, access denials, exports, deletions.
  - Public core now emits normalized security audit events across capability and document subsystems.
  - Added centralized journal store + Backend/Database admin panel visibility + retention purge integration.
- [~] Send telemetry to SIEM:
  - Datadog/Splunk/Elastic/Sentinel.
  - Public core now supports backend alert forwarding (`/uplinkportal/v1/security-alerts`) for SIEM ingestion by your secure backend/add-on.
  - Generic webhook forwarding is available for existing SIEM endpoints.
- [~] Configure alerts:
  - anomalous login/token use, high-volume exports, repeated auth failures, unauthorized access attempts.
  - Public core now detects repeated failed/denied audit events and stores local security alerts for admin review.
  - Monitoring mode defaults to `No Monitoring` with save-time disclaimer confirmation and optional external routing modes.
- [x] Add immutable evidence trail option for legal/forensic disputes.
  - Public core now supports an optional tamper-evident hash-chain evidence trail with integrity verification status in admin.
- [x] Implement incident response runbooks:
  - credential compromise, unauthorized data access, connector abuse.
  - Added `../troubleshooting/incident-response-runbooks.md`.

## Phase 6: Low-Code Operations for Non-Technical Admins
- [~] Build no-code "Automation Recipes" by core model:
  - `finance`: invoice-created -> notify client workspace.
  - `it_support`: ticket-escalated -> trigger SLA workflow.
  - `projects`: milestone-complete -> push portal update.
  - `documents`: agreement-uploaded -> notify client + retention timer.
- [~] Add one-click recipe library with compliance labels.
- [~] Add dry-run/simulation mode for safe non-technical testing.
- [~] Add rollback/versioning for recipes and mappings.
  - Public core now stores bounded recipe revision snapshots and supports rollback-on-save in Automation Recipes settings.
  - Mapping-level version promotion/rollback orchestration remains delegated to secure backend/add-on.

## 4) GDPR & HIPAA Control Checklist

## GDPR-oriented controls
- [~] Data processing inventory by model and integration.
  - Added baseline inventory template: `../reference/data-processing-inventory.md`.
  - Environment-specific processor register and final legal review still required before production.
- [~] Lawful basis + purpose tagging for each sync flow.
  - Model-level lawful basis controls are implemented in Security-tab settings.
  - Flow-level purpose tagging still needs backend/add-on workflow metadata propagation.
- [~] Data subject request support:
  - access, export, erasure, rectification workflows.
  - Requires production workflow + legal process integration outside public-core plugin scope.
- [~] DPA templates and subprocesser tracking for each iPaaS/provider.
  - Requires legal artifact ownership and vendor-by-vendor review outside this repository.
- [~] Cross-border transfer controls and SCC support where needed.
  - Requires jurisdiction-specific legal controls and contract management outside plugin code.

## HIPAA-oriented controls
- [~] Determine if PHI is in-scope per model and per connector.
  - Requires customer/legal data-classification decisions per deployment.
- [~] Ensure BAA coverage with all PHI-touching vendors.
  - Requires legal/vendor agreements outside plugin source.
- [~] Implement minimum necessary access policy by role and workflow.
  - Model-level RBAC matrix exists in public core; final PHI workflow policy enforcement still requires backend/legal validation.
- [~] Enforce audit controls, integrity checks, and transmission security.
  - Public core includes audit journal, pseudonymization, and transport-policy checks; full HIPAA attestation requires external infrastructure/process controls.
- [~] Enable break-glass access protocol with full logging.
  - Requires incident workflow + privileged-access governance outside current public-core runtime.

## 5) Core Model Configuration Blueprint (Target)

Goal: make each core model configurable without custom code.

### Config object shape (target)
- `model_id`: `finance|it_support|projects|licensing|documents`
- `provider`: selected SaaS/system template
- `auth_profile`: OAuth2 client reference + scopes
- `data_contract_version`: schema version used by connector
- `sync_mode`: pull/push/bidirectional
- `field_map`: low-code mapping set
- `retention_policy`: duration + purge behavior
- `compliance_profile`: GDPR/HIPAA/default
- `monitoring_profile`: log level + alert thresholds

### Implementation TODO
- [x] Add config persistence for model integration profiles.
  - Implemented via integration profile storage in `src/Core/Support/Options.php` and Settings UI flows in `src/Admin/SettingsPage.php`.
- [x] Add model profile validation and policy lint checks.
- [x] Add profile test harness:
  - auth test, schema test, permission test, dry-run test.
- [x] Add profile promotion workflow:
  - draft -> reviewed -> active.

## 6) Practical Platform Recommendation

### Recommended baseline
- Self-hosted `n8n` (primary) + Vault (secret management) + SIEM integration.
- Managed fallback: Workato + Vault + SIEM when self-hosting is not feasible.

### SMB alternative
- Make/Zapier for recipe simplicity, with strict separation:
  - keep regulated/PHI workflows on controlled backend stack.

### Enterprise/hybrid
- MuleSoft for canonical API orchestration when integration estate becomes large and regulated.

## 7) Rollout Governance
- [~] Pilot with one provider per core model.
  - Requires live environment/provider credentials and operator execution.
- [~] Define security sign-off gates before production activation.
  - Requires organizational release governance outside repository code.
- [~] Define legal/compliance sign-off gates per policy profile.
  - Requires legal review process ownership outside repository code.
- [~] Measure outcomes:
  - setup time reduction, incident reduction, audit pass rate, admin usability.
  - Requires production telemetry/process metrics collection.

## 8) Deliverables
- [x] Integration profile UI/UX for non-technical admins.
  - Guided Modules setup workflow, template prefill, profile lifecycle, and harness summaries are implemented in admin UI.
- [x] Connector catalog + provider templates.
  - Provider templates implemented via `src/Core/Support/IntegrationProviderTemplates.php` and Modules UI integration flows.
- [~] OAuth2 + external secret-manager integration.
  - Public core supports delegated OAuth refs and Vault-first routing metadata; final token-vault execution remains backend/add-on scoped.
- [x] Compliance policy bundles (GDPR/HIPAA baseline).
  - Implemented via policy bundle controls and model policy matrix in Security settings.
- [x] Centralized audit + alerting dashboards.
  - Implemented via security alert monitor + central audit journal panels in admin.
- [x] Design centralized monitoring feature:
  - hosted multi-tenant log pipeline, search/retention controls, and compliance reporting UI.
- [x] End-user/operator documentation and runbooks.
  - Runbooks and reference docs are present under `docs/troubleshooting/` and `docs/reference/`.
