# Admin IA and Route Map

## Canonical Menu Map

```text
UplinkPortal
├─ Operations Dashboard
├─ Agreements & Records
├─ Service Catalog
├─ Configuration
│  ├─ Client Access
│  ├─ Modules
│  ├─ Security
│  ├─ Company Info
│  └─ Portal Preview
└─ Tools & Diagnostics
```

## Configuration Hub (Server-Rendered Tabs)

URL pattern:
- `admin.php?page=uplinkportal-settings&tab=<key>`

Tabs:
1. `Client Access`
2. `Modules`
3. `Security`
4. `Company Info`
5. `Portal Preview`

Licensing ownership:
- Licensing is configured only in `Configuration > Modules > Database > Licensing`.
- Licensing is accessed only through the canonical Modules > Database path.

## Wireframes

### Modules Tab (Catalog-Focused)
```text
+--------------------------------------------------------------------------------+
| Configuration Center                                                           |
| [Client Access] [Modules*] [Security] [Company Info] [Portal Preview]         |
+--------------------------------------------------------------------------------+
| Modules                                                                        |
| Enable services and configure module settings                                  |
|                                                                                |
| +------------------------- Core Modules -------------------------------+       |
| | [Module icon] Finance                                                |       |
| |   Reporting and invoicing workflows                                  |       |
| |   Status: Active   [Enable/Disable]                                  |       |
| |                                                                       |       |
| | [Module icon] Database                                                |       |
| |   Licensing, file storage, secrets, users                             |       |
| |   Status: Active   [Configure]                                         |       |
| +---------------------------------------------------------------------+       |
|                                                                                |
| +------------------------- Add-on Modules -----------------------------+       |
| | Card grid only (public UI scaffolding; no paid logic)               |       |
| | Status shows Entitled/Unavailable only                               |       |
| +---------------------------------------------------------------------+       |
|                                                                                |
| +------------------------- Custom Modules -----------------------------+       |
| | Owner-uploaded module cards (same status/action layout)              |       |
| +---------------------------------------------------------------------+       |
|                                                                                |
| [Save Module & Integration Settings]                                           |
+--------------------------------------------------------------------------------+
```

### Tools & Diagnostics (Relocated Advanced Panels)
```text
+--------------------------------------------------------------------------------+
| Tools & Diagnostics                                                            |
| Integration health checks and troubleshooting for MSP technical staff          |
+--------------------------------------------------------------------------------+
| [Card] Integration Status Board                                                |
|   - At-a-glance readiness by model/module                                      |
|                                                                                |
| [Card] Policy and Connectivity Checks                                          |
|   - Open only when needed                                                      |
|   <details> Integration Policy Lint </details>                                 |
|   <details> Integration Profile Test Harness </details>                        |
|                                                                                |
| [Card] Secret Lease Alerts                                                     |
|   - Secret age/rotation warnings                                               |
|   - Links operators to Modules > Database > Secrets for remediation            |
+--------------------------------------------------------------------------------+
```

### Database Module Configuration Window
```text
+--------------------------------------------------------------------------------+
| Database Configuration                                                         |
| [Licensing*] [File Storage] [Secrets] [Users]                                 |
+--------------------------------------------------------------------------------+
| Licensing                                                                      |
| - License key                                                                  |
| - License worker URL                                                           |
| - Product slug                                                                 |
| - Cache TTL                                                                    |
| - [Validate License Now]                                                       |
|                                                                                |
| File Storage                                                                   |
| - Document storage integration profile                                         |
| - Storage posture + usage meter                                                |
|                                                                                |
| Secrets                                                                        |
| - Backend mode / backend URL / service token / secret manager                 |
| - External API key + API secret                                                |
|                                                                                |
| Users                                                                          |
| - WP users + client profile schema + tenant guardrails                         |
+--------------------------------------------------------------------------------+
```

### Security Tab (Monitoring Inline Notice in Context)
```text
+--------------------------------------------------------------------------------+
| Security                                                                       |
+--------------------------------------------------------------------------------+
| Monitoring & Alert Routing                                                     |
| ( ) No Monitoring (Default)                                                    |
| ( ) Generic Webhook                                                            |
| ( ) Hosted Centralized Logging                                                 |
|                                                                                |
| [inline warning shown only when "No Monitoring" is selected]                   |
| Monitoring mode is currently set to No Monitoring. External alert forwarding   |
| is disabled.                                                                   |
|                                                                                |
| Additional security/compliance sections...                                     |
+--------------------------------------------------------------------------------+
```

### UI Pattern Notes

- Use WordPress-native page structure with restrained enhancements (`nav-tab-wrapper`, `widefat`, WP notices/buttons).
- Use one consistent content width envelope (max ~1240px) and predictable spacing scale.
- Keep advanced diagnostics out of Modules catalog to reduce cognitive load.
- Add a quick-actions rail under each page hero for common cross-screen jumps, but keep canonical IA unchanged.

## Canonical Entry Points

| Area | Canonical destination | Notes |
| --- | --- | --- |
| Operations Dashboard | `admin.php?page=uplinkportal` | Central operations metrics and fulfillment queue. |
| Configuration hub | `admin.php?page=uplinkportal-settings&tab=<key>` | Client Access, Modules, Security, Company Info, Portal Preview. |
| Modules diagnostics | `admin.php?page=uplinkportal-tools-diagnostics` | Advanced diagnostics are separate from Modules catalog flow. |

## Client Access ERD (ASCII)

```text
+---------------------------------------------+
| wp_uplinkportal_client_identity_map         |
+---------------------------------------------+
| id (PK)                                     |
| wp_user_id (UNIQUE)                         |
| external_system                             |
| external_client_id                          |
| plan_key                                    |
| override_enabled                            |
| created_at                                  |
| updated_at                                  |
+----------------------+----------------------+
                       |
                       | wp_user_id
                       v
+---------------------------------------------+
| wp_uplinkportal_client_module_access        |
+---------------------------------------------+
| id (PK)                                     |
| wp_user_id                                  |
| module_key                                  |
| access_state (inherit/enabled/disabled)     |
| entitlement_source                          |
| updated_by                                  |
| updated_at                                  |
| UNIQUE (wp_user_id, module_key)             |
+----------------------+----------------------+
                       |
                       | wp_user_id + module_key
                       v
+---------------------------------------------+
| wp_uplinkportal_client_feature_flags        |
+---------------------------------------------+
| id (PK)                                     |
| wp_user_id                                  |
| module_key                                  |
| feature_key                                 |
| is_enabled                                  |
| feature_value (JSON text)                   |
| updated_by                                  |
| updated_at                                  |
| UNIQUE (wp_user_id, module_key, feature_key)|
+---------------------------------------------+
```

Schema implementation:
- Runtime/activation: `src/Core/Support/ClientAccessStore.php`
- Reference SQL: `infrastructure/wordpress/migrations/202602170001_client_access_tables.sql`
