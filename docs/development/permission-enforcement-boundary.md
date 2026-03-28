# Permission Enforcement Boundary

This document defines which permission and role decisions are enforced locally by the
public-core WordPress plugin and which decisions must be authoritative in the secure
backend service. Read this document alongside the
[Deployment Security Guide](deployment-security-guide.md) and the
[Public Core Policy](../reference/public-core-policy.md).

---

## Design Principle

The plugin enforces the **initial access gate** and **UI-layer capability checks** entirely
within WordPress. **Final policy validation** for sensitive, business-critical, or
compliance-sensitive operations is delegated to a secure backend service via the
`uplinkportal_backend_validation_result` filter. This separation keeps premium and
secret-dependent logic out of the public codebase while giving operators a verifiable
audit trail in both layers.

---

## Part 1 — What WordPress Enforces (Public-Core Layer)

All enforcement listed here happens inside `src/` and `includes/` and is verified by
the PHPUnit test suite.

### 1.1 Capability Checks

Every privileged admin operation, REST endpoint, and AJAX handler checks the calling
user's WordPress capability before executing. These checks are performed using
`current_user_can()`.

| Capability | Gating context |
|---|---|
| `msp_manage` | All `Configuration` settings saves, AJAX validation triggers, Tools and Diagnostics admin pages, role resync actions, document delete, portal customization saves |
| `msp_preview_client_portal` | Portal Preview tab render and save |
| `client_access` | REST `GET /uplinkportal/v1/portal-data` — base portal entry gate |
| `client_view_billing` | REST portal data: billing section |
| `client_view_tickets` | REST portal data: tickets section |
| `client_view_subscriptions` | REST portal data: subscriptions section |
| `activate_plugins` | Runtime role-sync integrity check (`CapabilityManager::register_caps_for_runtime_checks`) |

Source files: `src/Admin/SettingsPage.php`, `src/ClientPortal/Rest/PortalController.php`,
`src/Core/Security/CapabilityManager.php`, `includes/class-admin.php`.

### 1.2 Nonce Verification

All state-changing requests require a valid WordPress nonce.

| Action | Nonce check |
|---|---|
| Settings saves (admin-post) | `check_admin_referer( 'uplinkportal_save_settings', ... )` |
| Portal customization save | `check_admin_referer( 'uplinkportal_save_customization_defaults', 'uplinkportal_customization_nonce' )` |
| AJAX validation trigger | `check_ajax_referer( 'uplinkportal_validation_test', 'nonce' )` |
| REST endpoints (cookie auth) | `wp_verify_nonce( $nonce, 'wp_rest' )` via `uplinkportal_enforce_rest_nonce` filter |

Source files: `src/Admin/SettingsPage.php`, `src/ClientPortal/Rest/PortalController.php`.

### 1.3 Role and Capability Registration

`CapabilityManager` creates and maintains all plugin-defined WP roles at activation and
on a runtime integrity check. This is a local WP concern — role slugs, labels, and the
mapping of capabilities to roles are defined here and version-tracked via
`uplinkportal_role_sync_version`.

All role slugs follow the naming conventions:
- MSP operator roles: `uplinkportal_msp_*`
- Client portal roles: `uplinkportal_client_*`

All capability slugs follow the naming conventions:
- MSP capabilities: `msp_*`
- Client capabilities: `client_*`

Source file: `src/Core/Security/CapabilityManager.php`.

### 1.4 Module-Level Capability Enforcement

`ModuleManager::can_user_access_module()` resolves which WordPress capability is required
to read a module and calls `user_can()` against it. The resolution order is:

1. Model-level RBAC policy matrix (`Options::get_model_policy_matrix()`)
2. Module default action-capability map (`ModuleManager::MODULE_ACTION_CAPABILITIES`)
3. Filter override (`uplinkportal_module_required_capability`)

Results are cached in a per-request static array for the duration of the PHP request.

Source file: `src/Core/Modules/ModuleManager.php`.

### 1.5 Entitlement Enforcement

`EntitlementService` stores provider entitlement state (active / grace / suspended /
canceled) for each `(client_user_id, provider_id)` pair in a custom DB table.
`EntitlementEnforcer` reads this state and blocks automation execution when the
entitlement is suspended or a grace period has expired (seven-day grace window).

This is a **local WP enforcement layer** — it is authoritative for blocking or allowing
day-to-day automation execution based on plan/entitlement state managed by the MSP operator.

Source files: `src/Core/Support/EntitlementService.php`,
`src/Core/Support/EntitlementEnforcer.php`.

### 1.6 Input Sanitization

Every value received from `$_GET`, `$_POST`, or REST request bodies is sanitized before
use. All output is escaped. These are baseline WordPress coding standards enforced by
`composer lint` on every change.

---

## Part 2 — What the Backend Must Enforce (Secure Backend Layer)

The operations below are **not authoritatively enforced** by the public-core plugin.
Public core provides the delegation hooks and safe-fallback behavior, but final
enforcement must occur in the private backend service.

### 2.1 Module Connection and Compatibility Validation

Whether an integration profile is genuinely connected, compatible, and compliant with
the selected policy bundle is validated by the backend via the
`uplinkportal_backend_validation_result` filter (`BackendValidationBroker::validate()`).

Public-core behavior when no backend is attached:
- Returns a `warning` status with `compatible: false`.
- Guidance message says "No secure backend validation service is connected."
- The UI displays this warning without blocking access — **it does not silently pass**.

Source file: `src/Core/Support/BackendValidationBroker.php`.

### 2.2 Final Role Assignment and Approval Policy

Role assignment decisions that carry business or compliance implications (for example
approving a new client portal user for a specific access tier, or granting elevated MSP
sub-roles) must be validated by the backend policy service before taking effect. The
backend must be the authoritative source for:

- Whether a proposed role grant is within policy limits for the tenant.
- Whether the requesting user (MSP operator) has approval authority for that role tier.
- Whether the assignment passes any compliance policy bundle restrictions in effect.

Public core only registers WordPress roles and capabilities locally. It does not validate
whether the business/policy decision to assign those roles is approved.

Delegation hook: `uplinkportal_backend_validation_result` with `section: 'roles'`.

### 2.3 Document Encryption Key Exchange

Retrieving document encryption key material for client document uploads is delegated to
a backend endpoint (`uplinkportal_client_documents_key_endpoint`). The backend must:

- Authenticate the requesting plugin instance with a backend service token.
- Return key material only to authorized installations.
- Log every key retrieval event server-side.

Public-core behavior when key endpoint is unreachable: upload fails closed with a
`WP_Error( 'uplinkportal_key_retrieval_failed' )`. No file is written to disk.

Source file: `src/Core/Support/ClientDocumentsStore.php`.

### 2.4 OAuth Token Lifecycle

OAuth token acquisition, refresh, and revocation are delegated to the private backend.
Public core stores only **opaque connection reference strings** — never raw bearer tokens.
The backend is the authoritative holder of token material.

Source file: `src/Core/Licensing/SecretsStore.php` (`save_oauth_connection_reference()`).

### 2.5 Security Alert Webhook Receiver Validation

The security alert monitor sends HMAC-signed webhook payloads to the configured endpoint.
Whether those alerts result in automated policy enforcement actions (for example
auto-revoking access on a critical alert) is a backend responsibility. Public core sends
the event and awaits no policy callback — enforcement logic must live in the backend
webhook receiver.

Source file: `src/Core/Security/SecurityAlertMonitor.php`.

### 2.6 Private Backend Validation of Licensing State

Whether a license is genuine, active, and within plan limits is not validated in public
core. Public core only consumes opaque license state from the backend via filter-driven
responses. It does not implement license verification logic.

---

## Part 3 — Boundary Summary Table

| Decision | Enforced by WP | Authoritative in Backend |
|---|---|---|
| User has WordPress capability | Yes | No |
| Nonce is valid for request | Yes | No |
| Role slug / label registration | Yes | No |
| Module-level capability check | Yes | No |
| Entitlement active/grace/suspended status | Yes (local DB) | No |
| Integration profile connected + compatible | No (warning fallback) | Yes |
| Role assignment approval policy | No | Yes |
| Document encryption key issuance | No | Yes |
| OAuth token storage and refresh | No | Yes |
| Security alert enforcement actions | No | Yes |
| License authenticity and plan limits | No | Yes |

---

## Part 4 — Restricted Actions Requiring Backend Authority

The following actions must not proceed unless the backend returns an explicit allow
decision. If the backend is unreachable, public core must fail closed:

1. **Document upload** — blocked with `uplinkportal_key_retrieval_failed` if key endpoint
   is unreachable.
2. **Integration validation run** — reports `warning / compatible: false` if no backend
   handler is registered.
3. **Policy lint** — reports unevaluated findings with a "no backend connected" advisory.

For role assignment approval, the backend must be consulted before any elevated role is
granted to a user. MSP operators must configure the backend service token and policy
service endpoint before relying on role decisions for compliance-sensitive workflows.

---

## Part 5 — Filter Reference

| Filter | Direction | Purpose |
|---|---|---|
| `uplinkportal_backend_validation_result` | WP → Backend | Module connection and compatibility validation; role assignment policy |
| `uplinkportal_client_documents_key_endpoint` | WP → Backend | Document encryption key retrieval URL override |
| `uplinkportal_client_documents_encryption_key` | WP → Backend | Encryption key material injection (Playground/test mock) |
| `uplinkportal_backend_validation_result` (section: `roles`) | WP → Backend | Role assignment approval policy query |
| `uplinkportal_module_required_capability` | Internal | Override required WP capability per module/action |
| `uplinkportal_feature_enabled` | Internal | Module enable/disable state override |
| `uplinkportal_enforce_rest_nonce` | Internal | REST nonce enforcement toggle (must be `true` in production) |

---

## See Also

- [Deployment Security Guide](deployment-security-guide.md)
- [Public Core Policy](../reference/public-core-policy.md)
- [Hooks and Filters Reference](../reference/hooks-filters.md)
- [Architecture Overview](architecture.md)
- [Secret Rotation Runbook](../troubleshooting/secret-rotation-runbook.md)
