# Data Processing Inventory

Purpose: baseline GDPR/HIPAA-oriented processing inventory for UplinkPortal core models.

Scope: public core plugin configuration + delegated backend/add-on integrations.

## Model Inventory

| Model | Purpose | Example Data Elements | Default Legal Basis | Default Retention Signal | Primary Processor Boundary |
| --- | --- | --- | --- | --- | --- |
| `finance` | Billing and invoice lifecycle updates | `customer_id`, `invoice_id`, `status`, `amount_due`, `currency` | `contract` | `integration_profiles.finance.retention_policy` | Delegated billing connector/backend |
| `it_support` | Service ticket routing and SLA workflows | `ticket_id`, `priority`, `status`, `requester_id`, `assignee_id` | `contract` | `integration_profiles.it_support.retention_policy` | Delegated helpdesk connector/backend |
| `projects` | Project milestone/state visibility | `project_id`, `milestone_id`, `status`, `owner_id` | `legitimate_interests` | `integration_profiles.projects.retention_policy` | Delegated PM connector/backend |
| `licensing` | License validation and entitlement status | `license_key`, `instance_id`, `product_slug`, `status` | `legal_obligation` | `integration_profiles.licensing.retention_policy` | Cloudflare worker + delegated license backend |
| `documents` | Agreement/document access and retention | `document_id`, `client_id`, `mime_type`, `updated_at` | `contract` | `integration_profiles.documents.retention_policy` | Private storage path + delegated key backend |

## Data Subject Rights Coverage

| Request Type | Public Core Capability | Backend/Add-on Requirement |
| --- | --- | --- |
| Access | Auditable model-level records available in plugin UI and logs | Export full connector-side records and identity joins |
| Rectification | Supports model/profile configuration updates | Apply source-of-truth corrections in external systems |
| Erasure | Supports document deletion and retention purge scheduling hooks | Execute cross-system erasure workflows and attest completion |
| Portability/Export | Baseline data views only | Generate machine-readable export bundles from delegated systems |

## Processor/Subprocessor Register (Template)

| Service | Role | Region | Legal Instrument | BAA/DPA Status | Notes |
| --- | --- | --- | --- | --- | --- |
| WordPress Host | Processor | `<REGION>` | `<DPA/SCC>` | `<STATUS>` | Stores plugin options + local encrypted docs |
| Secure Backend/Add-on | Processor | `<REGION>` | `<DPA/SCC>` | `<STATUS>` | OAuth, KMS, connector execution, SIEM forwarding |
| Cloudflare Workers | Subprocessor | `<REGION>` | `<DPA/SCC>` | `<STATUS>` | Licensing validation and entitlement refresh |
| Supabase/Postgres | Subprocessor | `<REGION>` | `<DPA/SCC>` | `<STATUS>` | Licensing schema persistence |
| SIEM Provider | Subprocessor | `<REGION>` | `<DPA/SCC>` | `<STATUS>` | Optional monitoring webhook/hosted mode |

## Operational Notes

- Keep this inventory synchronized with:
  - `connector-schemas-v1.md`
  - `../development/integration-compliance-migration-plan.md`
  - current Security tab settings (policy bundle, legal basis, retention, monitoring, data protection).
- For multisite deployments, review tenant boundary mode and record whether site isolation or network-shared mode is active.
- For HIPAA workflows, mark PHI-bearing connectors explicitly and confirm BAA coverage before live enablement.
