# Human Instructions — UplinkPortal Open Tasks

Step-by-step guidance for tasks that require human action, a live backend, or a real WordPress environment.
Generated from `docs/development/project-todo.md` (open `[ ]` and `[~]` items).

---

## Phase 1 — Backend Infrastructure Setup

These items are required before any backend-dependent feature can be verified.
All work in this phase happens in your **private backend repository / ops docs** — nothing goes in this public repo.

### Step 1 — Define Environments (B1.1)

1. Open your private backend ops doc (or create one if it doesn't exist).
2. Document three environment entries — `local`, `staging`, `production` — each with:
   - Base URL (e.g. `https://api.staging.example.com`)
   - Auth strategy: service token, OAuth reference key name, or Vault path prefix
   - Contact / ownership notes
3. Share or link the doc internally so the team can reference it.
4. Mark task B1.1 `[x]` in `docs/development/project-todo.md` and add an Evidence note pointing to the private doc.

### Step 2 — Define Required Backend Components (B1.2)

1. In the same private architecture spec doc, add a "Backend Components" section.
2. List at minimum:
   - Validation service (auth readiness, schema readiness, permission readiness, policy lint)
   - Secrets service (encryption at rest, key management)
   - Document key exchange endpoint (`/uplinkportal/v1/client-documents/key`)
   - Optional: SIEM forwarder / audit log receiver
3. For each component note: owner, runtime, auth mechanism, and SLA.
4. Mark task B1.2 `[x]` in `docs/development/project-todo.md`.

---

## Phase 2 — Authentication & Secret Handling

### Step 3 — Establish Backend Service Token Process (B2.1)

1. Choose a token format (e.g. 256-bit random hex, opaque string, JWT with short TTL).
2. Document the following in your private ops runbook:
   - Token format and minimum length
   - How to issue a new token (command or UI steps)
   - Rotation process: zero-downtime preferred (issue new → update secret manager → expire old)
   - Storage location: GitHub Actions secrets, AWS Secrets Manager, HashiCorp Vault path, etc.
3. Verify `UPLINKPORTAL_BACKEND_SERVICE_TOKEN` is set in all environments (not the placeholder).
   - See `docs/development/deployment-security-guide.md` for `wp-config.php` constants reference.
4. Mark task B2.1 `[x]` in `docs/development/project-todo.md` with the runbook link as evidence.

### Step 4 — Vault Routing Strategy (B2.2)

1. Decide: is HashiCorp Vault (or equivalent) enabled in your deployment?
2. **If Vault is enabled:**
   - Document: secret engine path, metadata fields expected by the plugin (key name, version, rotation policy).
   - Confirm that `SecretsStore` Vault integration reads from the correct path.
   - Reference `docs/development/deployment-security-guide.md` for SecretsStore verification steps.
3. **If Vault is not enabled:**
   - Add a one-line note to your private config spec: "Vault: disabled. libsodium-encrypted DB blobs used."
4. Mark task B2.2 `[x]` in `docs/development/project-todo.md`.

---

## Phase 3 — Document Encryption Key Exchange (Hard Production Gate)

> ⚠️ Document uploads are **fail-closed** until this phase is complete. No documents can be uploaded in production until the key endpoint is live.

### Step 5 — Define the Key Exchange Contract (B3.2)

1. In your private backend spec, create a "Document Key Exchange Contract" section with:
   - **Request:** HTTP method, path (`/uplinkportal/v1/client-documents/key`), auth header, required body fields (client ID, tenant ID, document purpose).
   - **Response (success):** JSON shape with key material or opaque key reference, version field, expiry.
   - **Response (error):** error code enum, retry guidance (is it retryable? back-off window?).
   - **Separation of duties:** which roles can rotate / revoke (ops-only, no client access).
2. Have a second team member review the contract before implementing.
3. Mark task B3.2 `[x]` in `docs/development/project-todo.md`.

### Step 6 — Implement the Key Exchange Endpoint (B3.3)

1. In your private backend repo, implement the endpoint defined in Step 5.
2. Checklist:
   - [ ] Endpoint requires a valid backend service token (from Phase 2, Step 3).
   - [ ] Returns key material or opaque key reference per the contract (Step 5).
   - [ ] No key material appears in application logs or error responses.
   - [ ] Returns a structured error with retry guidance on failure.
   - [ ] Unit tests cover: valid request → key returned, missing token → 401, invalid payload → 422.
3. Deploy to staging.
4. Test from the WordPress admin: upload a document via `Agreements & Records` and confirm it succeeds.
5. Record the staging curl output (redacted) as evidence.
6. Mark tasks B3.1 (`[~]` → `[x]`) and B3.3 `[x]` in `docs/development/project-todo.md`.

---

## Phase 4 — Backend Validation Service

### Step 7 — Define the Validation API Contract (B4.1)

1. In your private backend spec, create a "Validation API Contract" section:
   - **Endpoint:** path, method, auth.
   - **Request payload:** tenant ID, integration context (auth readiness, schema readiness, permission readiness, policy lint request fields).
   - **Response:** versioned JSON with:
     - `status`: `pass` | `warn` | `fail`
     - `sections`: array of `{ key, label, status, severity, guidance }` objects
     - `score`: 0–100 integer
     - `evidence`: array of finding strings
   - **Versioning strategy:** URI-versioned (`/v1/`) or `Accept-Version` header.
   - **Severity levels:** `critical`, `high`, `medium`, `low` — with remediation SLA guidance per level.
2. Mark task B4.1 `[x]` in `docs/development/project-todo.md`.

### Step 8 — Implement the Validation Service (B4.2)

1. In your private backend repo, implement the validation service per the contract from Step 7.
2. Checklist:
   - [ ] Service token auth enforced on all endpoints.
   - [ ] All responses are deterministic / idempotent for the same payload.
   - [ ] Auth readiness check validates that required WP capability checks are exercised.
   - [ ] Schema readiness check validates expected custom table columns exist.
   - [ ] Permission readiness check validates tenant capability assignments.
   - [ ] Policy lint results surface actionable items with severity codes.
   - [ ] Unit + integration tests.
3. Deploy to staging.
4. In WordPress admin, open `Configuration > Modules` and verify the Integration Policy Lint panel shows results from the backend.
   - The panel is rendered by `src/Admin/SettingsPage.php` via `BackendValidationBroker`.
   - Hook: `uplinkportal_backend_validation_result` (see `src/Core/Support/BackendValidationBroker.php`).
5. Capture staging evidence (redacted API response and UI screenshot notes).
6. Mark tasks B4.2 and B4 parent `[x]` in `docs/development/project-todo.md`.

---

## Phase 5 — Role & Policy Enforcement in Secure Backend

### Step 9 — Implement Backend Policy Engine Stub (B5.2)

1. In your private backend repo, create a policy engine stub endpoint:
   - **Input:** tenant ID, user role context (opaque identity reference), requested action.
   - **Output:** `{ decision: "allow" | "deny", reason_code: string, reason_message: string }`.
2. For the stub, hardcode `allow` for known valid role+action combinations; `deny` with `reason_code: "role_not_authorized"` otherwise.
3. Wire the stub to the `uplinkportal_backend_validation_result` filter (or a dedicated permission filter if you add one).
4. Deploy to staging. Verify in WordPress admin that a policy decision trace is visible.
5. Mark task B5.2 `[x]` in `docs/development/project-todo.md`.
6. When the full policy engine replaces the stub, mark B5 parent `[~]` → `[x]`.

---

## Phase 6 — External Verification (Requires Live WordPress Admin)

### Step 10 — Run the WP Integration Checklist (External Verification, item 1)

1. Stand up a real WordPress 6.x environment (local install or WordPress Playground with network access).
2. Activate the UplinkPortal plugin.
3. Follow every step in `docs/guides/wp-integration-checklist.md`, sections 1–8.
4. For each section, record: pass / fail and any observations.
5. Specifically confirm:
   - `Configuration > Security` shows the document storage path status (check `render_security_baseline_panel()` in `src/Admin/SettingsPage.php`).
   - The storage meter and path values are accurate for the environment.
6. Save screenshots or notes and attach them to the task entry.
7. Mark the `[~]` item `[x]` in `docs/development/project-todo.md` with the evidence.

### Step 11 — Staging Validation Run (External Verification, item 2)

> Requires a deployed backend (Phases 3–5 complete) and a WordPress admin session pointed at that backend.

1. Configure `wp-config.php` constants to point at the staging backend URL and service token.
   - Reference `docs/development/deployment-security-guide.md` for the required constants.
2. Run endpoint smoke tests (curl or Postman) — redact credentials before saving output:
   - `GET /wp-json/uplinkportal/v1/health` → expect `{ status: "ok" }`.
   - `POST /wp-json/uplinkportal/v1/portal/me` (with valid `client_access` user nonce) → expect user data shape.
   - Document upload → expect success with encrypted `.uplink` file written.
3. In WordPress admin, open `Configuration > Modules` and verify:
   - Integration Policy Lint panel shows "pass" or actionable warnings (not blank / no-backend fallback).
4. Record curl outputs (redacted) and UI state as evidence.
5. Mark task `[x]` in `docs/development/project-todo.md`.

---

## Phase 7 — Features (Parking Lot)

These items are not required for production launch. Tackle them after Phases 1–6 are complete.

### Step 12 — Cloud Document Storage Connectors (F1)

1. Design a connector abstraction (interface) in `src/Core/Support/` — one implementation per provider (S3, Azure Blob, GCS).
2. The Security UI in `src/Admin/SettingsPage.php` should expose a connector profile selector with encrypted credential reference handling (never plaintext).
3. Upload/download paths in `src/Core/Support/ClientDocumentsStore.php` must route through the selected connector.
4. A connector-disabled fallback (local filesystem) must remain functional.
5. Follow the public-core-only policy: connector credential storage via `SecretsStore`; no hardcoded keys.
6. Add PHPUnit tests. Run `composer lint` and `composer test`.
7. Mark `[ ]` → `[x]` with evidence.

### Step 13 — Model-Level Legal Basis Override Controls (F2)

1. In `src/Admin/SettingsPage.php`, add an optional freeform text field per model under `Configuration > Security > GDPR Legal Basis`.
2. Sanitize input with `sanitize_textarea_field`. Escape output with `esc_textarea` / `esc_html`.
3. Surface custom legal-basis values in the policy lint output section.
4. Ensure existing predefined legal basis selections are unaffected.
5. Add PHPUnit tests. Run `composer lint` and `composer test`.
6. Mark `[ ]` → `[x]` with evidence.

### Step 14 — Backend-Connected Data Tables for Module Admin Pages (F3)

1. Identify which module-specific admin pages currently render static placeholders.
   - Search: `grep -rn "placeholder\|TODO" src/Admin/` for placeholder markers.
2. For each page, add an admin-post handler (nonce + `current_user_can` protected).
3. Wire the handler to the backend validation service or a new backend data endpoint.
4. Render results using WordPress admin list tables or custom HTML (escaped output only).
5. Show success/error admin notices (no header warnings).
6. Add PHPUnit tests for the handler (capability + nonce enforcement). Run `composer lint` and `composer test`.
7. Mark `[ ]` → `[x]` with evidence.

---

## Quick Reference

| Command | Purpose |
|---|---|
| `composer lint` | PHPCS + cspell + UI placeholder scan |
| `composer test` | PHPUnit test suite |
| `composer phpcbf` | Auto-fix PHPCS violations |
| `./infrastructure/scripts/repo-hygiene-scan.sh` | Secrets / pro-boundary / payment terms scan |

Key files for backend integration:
- `src/Core/Support/BackendValidationBroker.php` — validation delegation + filter hook
- `src/Core/Support/ClientDocumentsStore.php` — document upload + key exchange
- `src/Core/Licensing/SecretsStore.php` — secrets storage (libsodium encrypted)
- `src/Admin/SettingsPage.php` — admin UI panels (Security, Modules, Configuration)
- `docs/development/deployment-security-guide.md` — required constants + production checklist
- `docs/development/permission-enforcement-boundary.md` — WP vs backend enforcement split
- `docs/troubleshooting/secret-rotation-runbook.md` — token + key rotation procedures
