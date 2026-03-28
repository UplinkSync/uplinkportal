# Integration Data Classification Matrix

Purpose: provide a baseline classification map for UplinkPortal core integration models.

Important:
- This matrix is a baseline for technical controls, not legal advice.
- MSP operators must review and adjust classifications with legal/compliance counsel per tenant, contract, and jurisdiction.

## Classification Levels

- `public`: intended for broad disclosure.
- `internal`: operational data that should remain in trusted business systems.
- `confidential`: sensitive business/client data requiring strict access controls and auditability.
- `regulated`: data with potential GDPR/HIPAA/contractual handling obligations.

## Core Model Matrix

| Model | Primary Data Types | Baseline Classification | Example Controls |
|---|---|---|---|
| `finance` | invoices, invoice status, customer billing references | `confidential` | least-privilege access, encryption at rest/in transit, export controls, audit logs |
| `it_support` | tickets, incident notes, troubleshooting artifacts | `confidential` | RBAC, secure logging, retention policy, support staff segregation |
| `projects` | milestones, delivery updates, implementation notes | `internal` | role-scoped visibility, change history, integrity checks |
| `licensing` | license status, activation identifiers, entitlement metadata | `internal` | signed requests, endpoint hardening, idempotent validation |
| `documents` | agreements, records, uploaded client PDFs | `regulated` | private storage path, envelope encryption, access audit trail, deletion/export workflows |

## Compliance Mapping Guidance

- GDPR:
  - Treat `documents`, `finance`, and `it_support` as likely personal-data-bearing.
  - Ensure data subject workflows (access/export/erasure) are documented and tested.
- HIPAA:
  - Treat `documents` and `it_support` as potential PHI carriers depending on tenant use.
  - Require BAA coverage and minimum-necessary access controls before PHI processing.

## Operational Requirements

- Every integration profile should declare:
  - `model_id`
  - classification (`public|internal|confidential|regulated`)
  - retention/purge policy
  - allowed roles and export permissions
  - audit logging requirement
- Any model upgraded to `regulated` at tenant level should trigger stricter policy bundles and alerting.
