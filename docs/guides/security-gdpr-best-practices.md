# Security and GDPR Best Practices

Not legal advice. This document describes technical best practices and safe defaults for UplinkPortal operators.

## Baseline Security Defaults

- Enforce capability checks on every privileged route/action.
- Require nonces for state-changing admin actions.
- Keep sensitive values out of generic option payloads.
- Store secrets in dedicated secret keys via `SecretsStore`.
- Use encrypted/private storage for client documents.
- Keep portal preview customization in private storage (`wp-content/uplinkportal-private/portal-preview`).

## Data Minimization

- Collect only fields needed for the operational purpose.
- Prefer model-specific allowlists.
- Mask sensitive values in logs and audit surfaces.
- Avoid storing unnecessary identifiers in plugin-managed data.

## Logging Hygiene

- Log events, not secret material.
- Pseudonymize message/IP fields where practical.
- Bound retention windows.
- Use centralized review channels (local + optional backend/SIEM forwarding).

## Retention Guidance

- Define retention by model/use-case (finance, support, projects, licensing, documents).
- Purge expired records on a schedule.
- Keep retention behavior documented and auditable.

## Access Control Guidance

- Use least privilege roles.
- Review role mappings on every major release.
- Use only canonical role slugs and active role definitions.
- For client/module access decisions, use custom tables rather than ad-hoc option/meta state.

## GDPR-Oriented Operational Practices

- Maintain a data processing inventory.
- Record legal basis per processing model.
- Support export/erase workflows where applicable.
- Track processor/subprocessor relationships and transfer mechanisms.
- Ensure DPA/SCC/legal controls are handled by the MSP/operator process.

## Public Repository Safety Rules

- Do not commit:
  - private keys
  - production secrets/tokens
  - proprietary paid feature logic
  - hard-coded backend credentials
- Keep pro module code as explicit placeholders only (`src/Core/Modules/Pro/*`).
- Run CI hygiene checks before merge.
