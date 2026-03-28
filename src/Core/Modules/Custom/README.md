# Custom Modules Directory

This directory is reserved for optional custom modules created by MSP owners or their developers.

Important notes:
- Use this only if you trust the module source code.
- The UplinkPortal plugin/company is not liable for issues caused by custom module code.
- Validate custom modules in a secure development/staging environment before production use.
- Custom modules must implement `UplinkPortal\Core\Modules\ModuleInterface`.

Public-core policy:
- Keep premium/paywalled logic out of this repository.
- Pro features must remain in private add-ons with license checks.

Sample implementation:
- `SecureSampleCustomModule.php` demonstrates production-style patterns:
  - capability check (`current_user_can`)
  - nonce verification (`check_admin_referer`)
  - sanitized redirect handling (`wp_validate_redirect` + `wp_safe_redirect`)
