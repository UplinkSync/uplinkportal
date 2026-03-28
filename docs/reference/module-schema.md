# Module Registry and Schema

This repository contains public module UI scaffolding only. Entitled behavior and proprietary feature logic must come from secure backend/private add-ons.

## Public Module Contract

Interface:
- `src/Core/Modules/ModuleInterface.php`

Required methods:
- `id(): string`
- `label(): string`
- `register(): void`
- `is_enabled(): bool`
- `capabilities_required(): array`

## Registry Sources

1. Core/custom module registration
- `uplinkportal_modules`
- `uplinkportal_custom_modules`

2. Dynamic admin menu registration (strict registrar)
- `uplinkportal_module_menu_entries`
- Processed by: `src/Admin/ModuleMenuRegistrar.php`

3. Add-on module card scaffolding
- `uplinkportal_addon_module_cards`

## Module Menu Entry Schema (`uplinkportal_module_menu_entries`)

```php
array(
  'module_key' => 'finance',          // optional but recommended
  'menu_slug'  => 'uplinkportal-x',   // required
  'menu_title' => 'X Integration',    // required
  'page_title' => 'X Integration',    // optional
  'capability' => 'msp_manage', // optional
  'audience'   => 'msp|client|both',  // optional, defaults to msp
  'location'   => 'operations|agreements|service_catalog|configuration|hidden',
  'callback'   => callable            // optional
)
```

Registrar guardrails:
- Reserved slugs are blocked.
- Duplicate slugs are blocked.
- Disabled modules are skipped.
- Unknown locations are routed to `Configuration > Modules` via hidden submenu registration.

## Add-on Card Schema (`uplinkportal_addon_module_cards`)

```php
array(
  'label'       => 'Managed Security Center',
  'description' => '...',
  'icon'        => 'shield-alt',
  'coming_soon' => true,
)
```

## Entitlement and Backend Flags

Public repo behavior:
- Shows module scaffolding and placeholders.
- Uses feature gating hooks/filters for runtime decisions.

Private backend/add-on responsibilities:
- authoritative entitlement payload
- paid feature execution logic
- private feature tags / coming-soon rollout flags

Do not embed private entitlement payloads or paid decision trees in this repository.
