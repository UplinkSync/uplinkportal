# WP-CLI Diagnostics Evaluation

## Goal

Evaluate whether public core should expose WP-CLI diagnostics commands without leaking premium/backend-only logic.

## Candidate Commands

1. `wp uplinkportal diagnostics status`
- Output plugin version, enabled modules, backend URL configured flag, backend token configured flag.

2. `wp uplinkportal diagnostics security`
- Output strict REST nonce setting, evidence trail enabled flag, policy bundle, tenant boundary mode.

3. `wp uplinkportal diagnostics connectors`
- Output integration profile readiness summary (configured endpoint/auth state only, no secrets).

## Security Constraints

- Commands must be read-only in public core.
- No secret values or opaque token references should be printed.
- Capability-sensitive write/remediation actions must remain in secure backend/private add-ons.

## Recommendation

- `Implement later (defer)`: add WP-CLI diagnostics after current admin/runtime refactors stabilize.
- Reason: command output should reuse normalized service-layer summaries to avoid duplicating policy logic from admin rendering paths.

## Implementation Notes (When Started)

- Add a dedicated CLI command class under `src/Core/Cli/`.
- Gate registration to environments where WP-CLI is loaded.
- Keep command output machine-readable (`--format=json` support) and redact sensitive fields by default.
