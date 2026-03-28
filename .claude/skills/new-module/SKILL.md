---
name: new-module
description: Scaffold a new Core module for UplinkPortal — creates the PHP class under src/Core/Modules/Core/, registers it in ServiceContainer, and generates a PHPUnit test stub following the namespace-scoped stubs pattern. Accepts the module name as an argument (e.g. /new-module ReportingModule).
---

Scaffold a new Core module named `<ModuleName>` (taken from the argument provided, or ask if none given).

## Files to Create

### 1. `src/Core/Modules/Core/<ModuleName>.php`

Requirements:
- Namespace: `UplinkPortal\Core\Modules\Core`
- Implements: `UplinkPortal\Core\Modules\ModuleInterface`
- Class docblock: describe the module's purpose
- Constructor: accepts any dependencies injected from `ServiceContainer`
- `get_module_key()` — returns a snake_case string, e.g. `'reporting'`
- `get_display_name()` — returns a translated string using `__( '...', 'uplinkportal' )`
- `init()` — registers WordPress hooks; leave body as a `// TODO:` stub
- Registration note in docblock referencing `apply_filters( 'uplinkportal_modules', [] )`
- WordPress Coding Standards: docblocks on all public methods, `@since`, `@param`, `@return`

### 2. `src/Core/Support/ServiceContainer.php` — add a getter

Add a memoized getter for the new module, following the existing pattern in ServiceContainer:
```php
/**
 * Returns the <ModuleName> module instance.
 *
 * @since 0.2.0
 * @return \UplinkPortal\Core\Modules\Core\<ModuleName>
 */
public function get_<module_key>_module(): \UplinkPortal\Core\Modules\Core\<ModuleName> {
    if ( ! isset( $this->instances['<module_key>_module'] ) ) {
        $this->instances['<module_key>_module'] = new \UplinkPortal\Core\Modules\Core\<ModuleName>();
    }
    return $this->instances['<module_key>_module'];
}
```

### 3. `tests/<ModuleName>Test.php`

Requirements:
- Namespace: `UplinkPortal\Tests` (or match the pattern in nearby test files)
- Uses PHPUnit `TestCase`
- `setUp()` and `tearDown()` present
- At minimum, two tests:
  - `test_get_module_key_returns_expected_value()`
  - `test_get_display_name_returns_non_empty_string()`
- Uses the namespace-scoped stubs pattern: WordPress functions in the tested class's namespace are stubbed in `tests/config/stubs-*.php`. Do NOT duplicate stubs — check which stub file covers the class's namespace.

## Conventions to Follow

- PHP 8.3+ syntax only
- All user-facing strings use `__()` / `esc_html__()` with text domain `uplinkportal`
- Capability naming: `msp_*` for MSP operations, `client_*` for client operations
- No premium logic, no payment terms
- Hook names prefixed `uplinkportal_`

## After Scaffolding

Run `composer phpcs` to check for PHPCS violations, then `composer test` to confirm the new test passes.

Report which files were created and the `composer test` result.
