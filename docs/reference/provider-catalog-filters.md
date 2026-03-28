# Provider Catalog Filter Examples

This document demonstrates how to dynamically add providers to the UplinkPortal provider catalog using filter hooks.

## Filter Hooks

### Global Catalog Filter

```php
add_filter( 'uplinkportal_provider_catalog', function( $providers, $module_slug ) {
    // Add a provider to all modules or conditionally based on module_slug
    return $providers;
}, 10, 2 );
```

### Module-Specific Filter

```php
add_filter( 'uplinkportal_provider_catalog_module_finance', function( $providers ) {
    // Add providers specifically for the finance module
    return $providers;
}, 10, 1 );
```

## Example: Adding a Custom Provider

### Add Xero Provider to Finance Module

```php
add_filter( 'uplinkportal_provider_catalog_module_finance', function( $providers ) {
    $providers[] = array(
        'id'          => 'xero',
        'label'       => 'Xero',
        'module'      => 'finance',
        'locked'      => false, // Set to true if requires entitlement
        'auth_types'  => array( 'oauth' ),
        'defaults'    => array(
            'scopes'           => array(
                'accounting.transactions.read',
                'accounting.contacts.read',
                'accounting.reports.read',
            ),
            'endpoint_alias'   => 'xero_production',
            'retention_policy' => '7y',
        ),
        'helper_text' => 'Connect to Xero for accounting data.',
        'sort'        => 20,
    );

    return $providers;
} );
```

### Add Multiple IT Support Providers

```php
add_filter( 'uplinkportal_provider_catalog_module_it_support', function( $providers ) {
    $new_providers = array(
        array(
            'id'          => 'servicenow',
            'label'       => 'ServiceNow',
            'module'      => 'it_support',
            'locked'      => false,
            'auth_types'  => array( 'oauth', 'api_key' ),
            'defaults'    => array(
                'scopes'           => array(
                    'incidents.read',
                    'incidents.write',
                    'users.read',
                ),
                'endpoint_alias'   => 'servicenow_primary',
                'retention_policy' => '3y',
            ),
            'helper_text' => null,
            'sort'        => 15,
        ),
        array(
            'id'          => 'jira_cloud',
            'label'       => 'Jira Cloud',
            'module'      => 'it_support',
            'locked'      => true, // Requires entitlement
            'auth_types'  => array( 'oauth' ),
            'defaults'    => array(
                'scopes'           => array(
                    'read:jira-work',
                    'write:jira-work',
                ),
                'endpoint_alias'   => 'jira_cloud_primary',
                'retention_policy' => '3y',
            ),
            'helper_text' => 'Jira Cloud integration requires a premium add-on.',
            'sort'        => 25,
        ),
    );

    return array_merge( $providers, $new_providers );
} );
```

## Provider Schema

Each provider must include:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Unique provider identifier (slug format) |
| `label` | string | Yes | Display name for UI |
| `module` | string | No | Module slug (auto-set if using module-specific filter) |
| `profile` | string | No | Database profile scope (`licensing`, `file_storage`, `secrets`) when module is `database` |
| `locked` | bool | No | Whether provider requires entitlement (default: false) |
| `auth_types` | array | Yes | Supported auth types: `api_key`, `oauth`, `hybrid` |
| `defaults` | array | Yes | Default configuration values |
| `defaults.scopes` | array | No | Default OAuth scopes or permissions |
| `defaults.endpoint_alias` | string | No | Default endpoint identifier |
| `defaults.retention_policy` | string | No | Default data retention policy |
| `helper_text` | string\|null | No | Contextual help text shown in UI |
| `sort` | int | No | Display order (lower = earlier, default: 10) |

## Dynamic Provider Registration

For providers that should be registered by add-on plugins or Pro modules:

```php
/**
 * Register Pro providers when Pro module is active.
 */
add_action( 'plugins_loaded', function() {
    if ( ! function_exists( 'uplinkportal_pro_is_active' ) || ! uplinkportal_pro_is_active() ) {
        return;
    }

    add_filter( 'uplinkportal_provider_catalog', function( $providers, $module_slug ) {
        if ( 'finance' === $module_slug ) {
            $providers[] = array(
                'id'          => 'quickbooks_desktop',
                'label'       => 'QuickBooks Desktop',
                'module'      => 'finance',
                'locked'      => true,
                'auth_types'  => array( 'hybrid' ),
                'defaults'    => array(
                    'scopes'           => array(
                        'company.read',
                        'transactions.read',
                    ),
                    'endpoint_alias'   => 'qb_desktop_connector',
                    'retention_policy' => '7y',
                ),
                'helper_text' => 'Requires UplinkPortal Pro add-on.',
                'sort'        => 12,
            );
        }

        return $providers;
    }, 10, 2 );
} );
```

## Entitlement-Based Locking

The `locked` flag in the catalog is static. For dynamic, client-specific entitlement locking:

1. Use `EntitlementService` to manage client entitlements
2. Call `ProviderCatalogService::get_providers_for_client( $client_id, $module_slug )` instead of `get_providers_for_module()`
3. The service will overlay entitlement status automatically

Example:

```php
use UplinkPortal\Core\Support\EntitlementService;

$entitlement_service = new EntitlementService();
$client_id = 123;

// Grant entitlement
$entitlement_service->activate_entitlement(
    $client_id,
    'finance',
    'quickbooks_desktop',
    'subscription_12345'
);

// Later, provider catalog will show this provider as unlocked for this client
$catalog_service = /* get from ServiceContainer */;
$providers = $catalog_service->get_providers_for_client( $client_id, 'finance' );
// quickbooks_desktop will be unlocked for client 123
```

## Notes

- **Custom Provider** is always included automatically (cannot be locked)
- Providers with `id` = `custom` are reserved
- Use module-specific filters (`uplinkportal_provider_catalog_module_{$module_slug}`) for better performance
- For `database` module providers, set `profile` so each Database Configuration profile only receives relevant entries.
- Static `locked` flag is for providers that are never available in core (Pro-only features)
- Dynamic entitlement locking overrides static locked flag when client context is available
- Providers are sorted by `sort` value (ascending), then by label alphabetically
