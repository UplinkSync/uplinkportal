-- UplinkPortal WordPress custom tables
-- Generated for reference and manual audits.
-- Runtime creation/upgrade is handled by dbDelta in:
-- - src/Core/Support/ClientAccessStore.php
--
-- Replace `wp_` with your WordPress table prefix if different.

CREATE TABLE IF NOT EXISTS wp_uplinkportal_client_identity_map (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	wp_user_id bigint(20) unsigned NOT NULL,
	external_system varchar(64) NOT NULL DEFAULT 'wordpress_users',
	external_client_id varchar(191) NOT NULL DEFAULT '',
	plan_key varchar(64) NOT NULL DEFAULT 'starter',
	override_enabled tinyint(1) NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY wp_user_id (wp_user_id),
	KEY external_lookup (external_system, external_client_id)
);

CREATE TABLE IF NOT EXISTS wp_uplinkportal_client_module_access (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	wp_user_id bigint(20) unsigned NOT NULL,
	module_key varchar(64) NOT NULL,
	access_state varchar(20) NOT NULL DEFAULT 'inherit',
	entitlement_source varchar(20) NOT NULL DEFAULT 'local',
	updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
	updated_at datetime NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY client_module (wp_user_id, module_key),
	KEY module_state (module_key, access_state)
);

CREATE TABLE IF NOT EXISTS wp_uplinkportal_client_feature_flags (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	wp_user_id bigint(20) unsigned NOT NULL,
	module_key varchar(64) NOT NULL,
	feature_key varchar(100) NOT NULL,
	is_enabled tinyint(1) NOT NULL DEFAULT 0,
	feature_value longtext NULL,
	updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
	updated_at datetime NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY client_module_feature (wp_user_id, module_key, feature_key),
	KEY module_feature (module_key, feature_key)
);
