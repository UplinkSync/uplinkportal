# Connector Schemas v1

Version: `v1`  
Status: baseline public-core schema contract for integration profile and validation flows.

Public-core boundary reminder:
- This document describes public schema/contracts only.
- Real entitlement payloads, paid feature behavior, and secret/token execution belong to secure backend/private add-ons.

## 1) Integration Profile Object

Used by `uplinkportal_settings[integration_profiles][<model_id>]`.

```json
{
  "provider": "finance_reporting",
  "auth_type": "oauth2_pkce",
  "scopes": ["customers.read", "invoices.read"],
  "endpoint_alias": "billing_primary",
  "retention_policy": "7y",
  "lifecycle_state": "draft",
  "oauth_connection_ref": "conn_ref_opaque"
}
```

Field notes:
- `provider`: provider template key or custom provider slug.
- `auth_type`: integration auth method (`api_key`, `oauth2_pkce`, `bearer_token`, etc.).
- `scopes`: logical permission scopes.
- `endpoint_alias`: stable alias for backend routing/mapping.
- `retention_policy`: operator-defined policy key.
  - Common keys in public core: `minimum`, `30d`, `90d`, `180d`, `1y`, `2y`, `3y`, `5y`, `7y`, `default`, `indefinite`.
- `lifecycle_state`: profile promotion state (`draft`, `reviewed`, `active`).
- `oauth_connection_ref`: opaque reference to backend token-vault/OAuth connection (plugin does not store access/refresh tokens).

## 2) Canonical Model IDs

- `finance`
- `it_support`
- `projects`
- `licensing`
- `documents`

## 2.1) Profile Auth Type

Stored per model in `uplinkportal_settings[integration_profiles][<model_id>][auth_type]`:

- `oauth*` values: OAuth/delegated reference flow.
- `hybrid`: OAuth reference preferred with API key fallback.
- `api_key`: API key credential flow.

## 3) Model-to-Validation Mapping (v1)

- `finance` -> `billing`
- `it_support` -> `helpdesk`
- `projects` -> `pm`
- `licensing` -> no validation section (worker-based)
- `documents` -> no validation section (backend key endpoint)

## 4) Backend Validation Payload (public-core delegated)

Sent through `uplinkportal_backend_validation_result` filter contract:

```json
{
  "section": "billing",
  "base_url": "https://api.example.com",
  "api_key": "********",
  "oauth_connection_ref": "conn_ref_opaque",
  "auth_type": "hybrid",
  "allow_query_api_key": false,
  "backend_mode": "self_hosted",
  "policy_bundle": "msp_default",
  "secret_manager": {
    "provider": "vault",
    "vault_addr": "https://vault.example.com",
    "vault_namespace": "admin",
    "vault_kv_mount": "kv",
    "vault_path_prefix": "uplinkportal",
    "vault_auth_mount": "approle",
    "vault_role": "uplinkportal-backend",
    "vault_connection_ref": "vault_ref_opaque"
  },
  "governance": {
    "policy_bundle": "msp_default",
    "model_policies": {
      "finance": {
        "connect": "msp_manage",
        "read": "msp_manage",
        "sync": "msp_manage",
        "export": "msp_manage",
        "delete": "msp_manage"
      }
    },
    "model_legal_basis": {
      "finance": "contract"
    },
    "data_minimization": {
      "model_allowlist": {
        "finance": ["customer_id", "invoice_id", "status"]
      },
      "provider_overrides": {
        "finance": {
          "finance_reporting": ["customer_id", "invoice_id", "status"]
        }
      },
      "mask_logs": 1
    },
    "tenant_boundaries": {
      "mode": "multisite_site_isolation",
      "enforce_site_scope": 1,
      "require_super_admin": 1
    },
    "monitoring": {
      "mode": "none",
      "webhook_url": ""
    },
    "evidence_trail": 1
  }
}
```

Expected normalized result shape:

```json
{
  "status": "success",
  "compatible": true,
  "score": 100,
  "evidence": [
    {
      "type": "probe",
      "message": "Connection succeeded",
      "endpoint": "https://api.example.com/health",
      "http_status": 200,
      "auth": "header"
    }
  ],
  "guidance": ["Connection validated."]
}
```

## 5) Connector Response Schemas (Portal Data)

These responses are returned by `GET /uplinkportal/v1/data/{module}` in public-core dummy connectors.

### 5.1 Tickets (`module=tickets`)

```json
{
  "module": "tickets",
  "data": [
    {
      "id": "TCK-1001",
      "subject": "Example support ticket",
      "status": "open",
      "user_id": 123,
      "provider": "dummy"
    }
  ]
}
```

Required keys per row:
- `id` (string)
- `subject` (string)
- `status` (string)

Optional keys:
- `user_id` (number)
- `provider` (string)

### 5.2 Billing (`module=billing`)

```json
{
  "module": "billing",
  "data": [
    {
      "id": "INV-1001",
      "amount": 29.0,
      "currency": "USD",
      "status": "paid",
      "user_id": 123,
      "provider": "dummy"
    }
  ]
}
```

Required keys per row:
- `id` (string)
- `amount` (number)
- `currency` (string)
- `status` (string)

Optional keys:
- `user_id` (number)
- `provider` (string)

### 5.3 Subscriptions (`module=subscriptions`)

```json
{
  "module": "subscriptions",
  "data": [
    {
      "id": "SUB-1001",
      "product": "Managed Hosting Plan",
      "status": "active",
      "renewalDate": "2026-03-20",
      "user_id": 123,
      "provider": "dummy"
    }
  ]
}
```

Required keys per row:
- `id` (string)
- `product` (string)
- `status` (string)

Optional keys:
- `renewalDate` (string, `YYYY-MM-DD`)
- `user_id` (number)
- `provider` (string)

### 5.4 Error Shape

Connector errors follow standard WP REST error payloads. Typical shape:

```json
{
  "code": "uplinkportal_forbidden",
  "message": "You are not allowed to view tickets.",
  "data": {
    "status": 403
  }
}
```

## 6) Compatibility Rules

- Clients/add-ons should treat unknown fields as optional and ignore safely.
- Additive fields are allowed in minor updates while preserving v1 keys.
- Breaking changes require a new schema document (`v2.md`).

## 7) Admin IA Alignment

- Integration profile configuration belongs in `Configuration > Modules`.
- Security posture and compliance controls belong in `Configuration > Security`.
- Per-client module access/entitlements belong in `Configuration > Client Access` and are stored in custom tables, not user meta/options.
