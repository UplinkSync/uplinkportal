# Hooks and Filters

This page summarizes public extension points in the `uplinkportal_` namespace.

## Module Registration

- `uplinkportal_modules`
- `uplinkportal_custom_modules`
- `uplinkportal_module_menu_entries`
- `uplinkportal_feature_enabled`
- `uplinkportal_provider_catalog`
- `uplinkportal_provider_catalog_module_{$module_slug}`

## Security and Compliance

- `uplinkportal_integration_policy_findings`
- `uplinkportal_security_alert_window_seconds`
- `uplinkportal_security_alert_threshold`
- `uplinkportal_security_alert_endpoint`
- `uplinkportal_security_alert_triggered` (action)
- `uplinkportal_mask_audit_log_text`
- `uplinkportal_pseudonymized_message`
- `uplinkportal_secure_remote_args`
- `uplinkportal_allow_insecure_transport`

## Document Storage

- `uplinkportal_client_documents_storage_dir`
- `uplinkportal_client_documents_encryption_key`
- `uplinkportal_client_documents_key_endpoint`
- `uplinkportal_client_documents_max_upload_bytes`
- `uplinkportal_client_documents_soft_quota_bytes`

## REST and Runtime Controls

- `uplinkportal_rest_expose_email`
- `uplinkportal_enforce_rest_nonce`
- `uplinkportal_backend_validation_result`
- `uplinkportal_telemetry_service`
- `uplinkportal_secret_max_age_days`
- `uplinkportal_recipe_revision_limit`
- `uplinkportal_company_info_mirrored` (action)
- `uplinkportal_run_automation_recipe` (action)
- `uplinkportal_execute_automation_recipe` (action)
- `uplinkportal_recipe_execution_denied` (action)
- `uplinkportal_execution_blocked_entitlement` (action)
