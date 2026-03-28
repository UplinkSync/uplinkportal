# Automation Execution Entitlement Enforcement

This document explains how to enforce provider entitlements when executing automations, recipes, or integrations.

## Overview

The `EntitlementEnforcer` class provides a standardized way to check entitlements before executing any automation or integration that uses a provider.

## Central Runtime Entry Point

Core now exposes a centralized runtime gate action:

```php
do_action( 'uplinkportal_run_automation_recipe', $client_id, $recipe_id, $payload );
```

The runtime gate:
- resolves model/provider from recipe + saved integration profile,
- maps `licensing` and `documents` recipes to `database` entitlement module checks,
- blocks execution for `suspended`, `canceled`, or expired `grace`,
- allows execution when `active` or within grace window,
- forwards allowed executions to `uplinkportal_execute_automation_recipe`.

## Usage in Automation Executors

### Basic Enforcement Gate

```php
use UplinkPortal\Core\Support\EntitlementEnforcer;
use UplinkPortal\Core\Support\ServiceContainer;

$service_container = new ServiceContainer();
$enforcer = $service_container->entitlement_enforcer();

// Before executing automation/recipe
$result = $enforcer->enforce_execution_gate(
    $client_id,      // Client user ID
    $module_slug,    // e.g., 'finance', 'it_support'
    $provider_id,    // e.g., 'quickbooks_online'
    'recipe_sync'    // Context for logging
);

if ( is_wp_error( $result ) ) {
    // Execution blocked - handle denial
    error_log( 'Automation blocked: ' . $result->get_error_message() );
    return $result;
}

// Proceed with execution
execute_automation();
```

### Check Before Scheduling

```php
// Check before scheduling cron job
if ( ! $enforcer->is_execution_allowed( $client_id, $module_slug, $provider_id ) ) {
    // Don't schedule - entitlement missing or expired
    return;
}

// Schedule automation
wp_schedule_single_event( time() + 3600, 'my_automation_hook', array( $client_id ) );
```

### Grace Period Handling

During grace period (7 days after cancellation):
- Automations continue to run
- New configurations blocked (UI enforces Draft state)
- After grace expires, execution is blocked

```php
$status = $enforcer->get_status_summary( $client_id, $module_slug, $provider_id );

if ( $status['in_grace'] ) {
    $days_left = $status['days_remaining'];
    // Show warning: "Grace period expires in {$days_left} days"
}

if ( ! $status['entitled'] ) {
    // Block execution
    return new WP_Error( 'not_entitled', 'Provider access expired' );
}
```

## Entitlement Status Values

| Status | Description | Execution Allowed |
|--------|-------------|-------------------|
| `active` | Full entitlement | ✅ Yes |
| `grace` | In 7-day grace period | ✅ Yes (if not expired) |
| `suspended` | Temporarily blocked | ❌ No |
| `canceled` | Terminated | ❌ No (after grace) |
| `none` | No entitlement record | ❌ No |

## Custom Provider Exception

The "custom" provider is always allowed and bypasses entitlement checks:

```php
// This always returns true
$enforcer->is_execution_allowed( $client_id, $module_slug, 'custom' );
```

## Hooks

### Action: `uplinkportal_execution_blocked_entitlement`

Fires when execution is blocked due to entitlement.

**Parameters:**
- `int $client_id` - Client user ID
- `string $module_slug` - Module slug
- `string $provider_id` - Provider ID
- `string $context` - Execution context
- `array $grace_info` - Grace period info

**Example:**

```php
add_action( 'uplinkportal_execution_blocked_entitlement', function( $client_id, $module_slug, $provider_id, $context, $grace_info ) {
    // Send notification to MSP admin
    wp_mail(
        get_option( 'admin_email' ),
        'Automation Blocked - Entitlement Expired',
        "Client {$client_id} automation blocked for {$provider_id}"
    );
}, 10, 5 );
```

### Action: `uplinkportal_recipe_execution_denied`

Fires when the centralized recipe runtime gate denies execution.

**Parameters:**
- `int $client_id`
- `string $recipe_id`
- `string $model_id`
- `string $provider_id`
- `WP_Error $gate`
- `array $payload`

### Action: `uplinkportal_execute_automation_recipe`

Fires after centralized runtime checks pass. Backend/add-on execution listeners should run here.

## Example: Recipe Executor with Entitlement Gate

```php
class MyRecipeExecutor {
    private EntitlementEnforcer $enforcer;

    public function __construct( EntitlementEnforcer $enforcer ) {
        $this->enforcer = $enforcer;
    }

    public function execute_finance_sync( int $client_id ): void {
        $options = new Options();
        $profile = $options->get_integration_profile( 'finance' );
        $provider_id = $profile['provider'] ?? 'custom';

        // Enforce entitlement gate
        $gate = $this->enforcer->enforce_execution_gate(
            $client_id,
            'finance',
            $provider_id,
            'finance_sync_recipe'
        );

        if ( is_wp_error( $gate ) ) {
            $this->log_blocked_execution( $client_id, $gate );
            return;
        }

        // Execute sync
        $this->perform_finance_sync( $client_id, $provider_id );
    }

    private function log_blocked_execution( int $client_id, WP_Error $error ): void {
        error_log(
            sprintf(
                '[EntitlementBlock] Client %d: %s (%s)',
                $client_id,
                $error->get_error_message(),
                $error->get_error_code()
            )
        );
    }
}
```

## Managing Entitlements

### Grant Entitlement

```php
$entitlement_service = $service_container->entitlement_service();

$entitlement_service->activate_entitlement(
    $client_id,
    'finance',
    'quickbooks_online',
    'subscription_abc123'  // Optional reference
);
```

### Transition to Grace Period

```php
// Start 7-day grace period
$entitlement_service->transition_to_grace( $client_id, 'finance', 'quickbooks_online' );
```

### Suspend or Cancel

```php
// Suspend (can be reactivated)
$entitlement_service->suspend_entitlement( $client_id, 'finance', 'quickbooks_online' );

// Cancel (permanent)
$entitlement_service->cancel_entitlement( $client_id, 'finance', 'quickbooks_online' );
```

## Best Practices

1. **Always check at execution time** - Don't rely on configuration-time checks
2. **Log denials** - Track blocked executions for debugging
3. **Handle grace period gracefully** - Warn users before hard cutoff
4. **Use context strings** - Include recipe/automation name for debugging
5. **Check before scheduling** - Don't schedule cron jobs if not entitled
6. **Respect custom provider** - Always allow "custom" provider executions

## Integration with Existing Systems

### With WordPress Cron

```php
add_action( 'my_scheduled_automation', function( $client_id ) {
    $enforcer = ( new ServiceContainer() )->entitlement_enforcer();
    
    $gate = $enforcer->enforce_execution_gate(
        $client_id,
        'finance',
        'quickbooks_online',
        'scheduled_sync'
    );

    if ( is_wp_error( $gate ) ) {
        // Unschedule future runs
        wp_clear_scheduled_hook( 'my_scheduled_automation', array( $client_id ) );
        return;
    }

    // Execute
} );
```

### With REST API

```php
public function handle_automation_request( WP_REST_Request $request ) {
    $client_id = $request->get_param( 'client_id' );
    $provider_id = $request->get_param( 'provider_id' );
    
    $enforcer = $this->service_container->entitlement_enforcer();
    $gate = $enforcer->enforce_execution_gate(
        $client_id,
        'finance',
        $provider_id,
        'rest_api_trigger'
    );

    if ( is_wp_error( $gate ) ) {
        return new WP_REST_Response(
            array( 'error' => $gate->get_error_message() ),
            403
        );
    }

    // Process request
}
```
