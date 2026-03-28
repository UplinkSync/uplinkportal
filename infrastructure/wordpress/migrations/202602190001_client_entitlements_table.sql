-- UplinkPortal client entitlements table
-- Generated for reference and manual audits.
-- Runtime creation/upgrade is handled by dbDelta in:
-- - src/Core/Support/EntitlementService.php
-- Current schema version: 2
--
-- Replace `wp_` with your WordPress table prefix if different.

CREATE TABLE IF NOT EXISTS wp_uplinkportal_client_entitlements (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	client_id bigint(20) unsigned NOT NULL,
	module_slug varchar(64) NOT NULL,
	provider_id varchar(100) NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'active',
	grace_until datetime NULL,
	subscription_ref varchar(191) NULL,
	notes text NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY client_module_provider (client_id, module_slug, provider_id),
	KEY module_provider (module_slug, provider_id),
	KEY status (status),
	KEY grace_expiry (grace_until)
);
