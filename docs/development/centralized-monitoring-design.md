# Centralized Monitoring Feature Design

## Purpose

Define a hosted, multi-tenant monitoring feature for security/integration telemetry with search, retention controls, and compliance reporting dashboards.

## Scope

- In scope:
  - Hosted log intake from plugin/backend events
  - Tenant-scoped log search and filtering
  - Retention policy controls and automated purge
  - Compliance dashboards for baseline posture and audit trends
- Out of scope (public core):
  - Proprietary hosted infrastructure implementation
  - Vendor-specific SIEM internals
  - Billing/subscription enforcement logic

## Public-Core vs Private Backend

- Public core (this repository):
  - Emits normalized security/integration events.
  - Supports local monitoring mode selection and optional webhook routing.
  - Forwards structured alerts to delegated backend endpoints.
- Private backend/add-on:
  - Authenticates tenant ingestion.
  - Stores, indexes, and retains centralized events.
  - Provides hosted search and compliance dashboard APIs.
  - Manages subscription entitlements and hosted capacity.

## High-Level Architecture

1. Event Producers
- WordPress plugin audit/security events
- Backend validator/integration events
- Optional worker/licensing telemetry

2. Ingestion Layer (private backend)
- Authenticated endpoint per tenant (Bearer token or signed key)
- Schema validation and enrichment (tenant_id, environment, model_id)
- Dead-letter queue for malformed events

3. Storage + Indexing
- Hot index: 7-30 days for fast query/search
- Warm archive: 30-365+ days for compliance retention
- Immutable evidence linkage for tamper-evident investigations

4. Query + Dashboard API
- Tenant-scoped search API (time range, severity, source, model)
- Aggregation API (alerts by severity, denied actions, top failing controls)
- Compliance summary API (policy baseline coverage, control status trends)

## Data Model (Canonical Event Envelope)

Required fields:
- `event_id` (UUID)
- `tenant_id`
- `occurred_at` (UTC ISO8601)
- `severity` (`info|warning|error|critical`)
- `source` (`client_documents|security_alert_monitor|integration_policy|...`)
- `event` (machine key)
- `message` (sanitized)
- `result` (`success|denied|error|partial`)

Recommended fields:
- `model_id` (`finance|it_support|projects|licensing|documents`)
- `actor_user_id` (if applicable)
- `client_user_id` (if applicable)
- `document_id` (if applicable)
- `trace_id` for cross-system correlation

## Search and Retention

Search requirements:
- Time range + severity filter
- Free-text message search (sanitized)
- Source/model facet filters
- Saved views for common operational queries

Retention policy requirements:
- Tenant-selectable policy profile (`30d`, `90d`, `365d`, `custom`)
- Automatic purge job and purge audit records
- Legal hold flag to pause purge for investigations

## Compliance Dashboards

Minimum dashboard panels:
- Security posture summary (current policy bundle + open critical findings)
- Access control trends (denied/failed events over time)
- Data-protection posture (at-rest policy status by model)
- Incident readiness metrics (alert response latency, unresolved critical alerts)
- Retention compliance status (policy target vs actual retained ranges)

## Security Requirements

- Strict tenant isolation at query and storage boundaries.
- Pseudonymization support for sensitive log fields.
- Signed audit trail exports for legal/compliance workflows.
- Encryption in transit and at rest for centralized log data.

## Rollout Plan

1. Phase 1: Hosted ingestion + basic search
- Tenant-authenticated ingestion
- Severity/source search and export

2. Phase 2: Retention policy engine
- Policy profiles and purge scheduling
- Legal hold controls

3. Phase 3: Compliance dashboards
- Baseline KPI widgets and trend reporting
- Policy drift and unresolved finding tracking

4. Phase 4: Enterprise operations
- Saved queries, alerts, and role-based dashboard views
- Cross-tenant operator tooling (provider-only)

## Open Questions

- Hosted storage region strategy per tenant (single-region vs region-per-tenant)
- Compliance export format requirements (CSV, JSON, signed bundles)
- Dashboard role matrix (owner/manager/auditor read scopes)
