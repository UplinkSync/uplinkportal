# Multisite Deployment

UplinkPortal supports WordPress Multisite (Network) installation. This document covers network activation behaviour, per-site configuration, and caveats to be aware of when running across multiple sites.

---

## Network Activation

When the plugin is **Network Activated** (Plugins → Network Admin → Plugins → Network Activate), the activation hook runs `Activator::activate()` for every site in the network at the time of activation.

```php
// From uplinkportal.php
register_activation_hook(
    UPLINKPORTAL_FILE,
    static function ( bool $network_wide ): void {
        if ( $network_wide && function_exists( 'get_sites' ) ) {
            foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
                switch_to_blog( $blog_id );
                \UplinkPortal\Core\Activator::activate();
                restore_current_blog();
            }
        } else {
            \UplinkPortal\Core\Activator::activate();
        }
    }
);
```

### What Activation Does Per Site

For each site, `Activator::activate()`:

1. Enforces PHP 8.3+ and WordPress 6.0+ version gates.
2. Creates or upgrades the plugin's custom database tables:
   - `{prefix}uplinkportal_client_documents`
   - `{prefix}uplinkportal_client_document_audit`
   - `{prefix}uplinkportal_client_access`
   - `{prefix}uplinkportal_client_entitlements`
   - `{prefix}uplinkportal_purchase_requests`
3. Registers UplinkPortal roles and capabilities.
4. Sets the `uplinkportal_db_version` option for the site.

### Sites Created After Network Activation

Sites added to the network **after** network activation do not automatically receive the plugin tables or roles. You have two options:

**Option A — Per-site activation:**
Visit the site's wp-admin and activate the plugin there, which triggers the single-site activation path.

**Option B — WP-CLI (recommended for automation):**
```bash
wp --url=https://newsite.example.com plugin activate uplinkportal
```

Or, to run activation for all sites programmatically:
```bash
wp site list --field=url | xargs -I {} wp --url={} uplinkportal activate
```

---

## Tenant Boundary Scope

`TenantBoundaryScope` controls which WordPress users are considered "in scope" for the current site's portal on a multisite network. It applies when querying client users for the portal access store, client document store, and settings pages.

### Configuration

Go to **Configuration → Security → Governance** to set the tenant boundary options.

| Option | Description |
|---|---|
| **Mode** | `per_site` (default) — Only users who are members of the current blog are in scope. `multisite_network_shared` — All network users are in scope (network-wide portal). |
| **Enforce Site Scope** | When enabled, restricts user queries to the current blog. Disable only if you explicitly want cross-site access. |
| **Require Super Admin for Network Shared** | When using `multisite_network_shared` mode, only super-admins see the full cross-site client list; regular MSP operators are scoped to the current site. |

### How It Works

- **`per_site` mode (default):** `is_user_in_scope()` returns `true` only if the user is a member of the current blog (via `is_user_member_of_blog()`). User queries are scoped to `blog_id = get_current_blog_id()`.
- **`multisite_network_shared` mode:** `is_user_in_scope()` returns `true` for all users (or only super-admins if `require_super_admin` is set). User queries use `blog_id = 0` to span the full network.
- **Single-site installs:** All scope checks are bypassed — `is_user_in_scope()` always returns `true`.

---

## Per-Site vs. Network-Wide Configuration

UplinkPortal uses standard WordPress options (`get_option` / `update_option`) which are **per-site** in a multisite environment. This means:

| Configuration | Scope |
|---|---|
| Backend service URL and token | Per-site |
| Monitoring profile (webhook) | Per-site |
| Module enable/disable state | Per-site |
| Client Access store | Per-site (separate table rows keyed by `{prefix}`) |
| Entitlements | Per-site (separate table rows) |
| Roles and capabilities | Per-site |

> There is intentionally no network-wide shared configuration store. Each site in a multisite network is treated as an independent MSP portal deployment.

---

## Database Tables

Each site in the network has its own table prefix (e.g. `wp_2_`, `wp_3_`). The schema upgrade logic in `Activator::activate()` uses `$wpdb->prefix`, which is already set to the correct per-site prefix when called inside `switch_to_blog()`.

### Manual Schema Upgrade for All Sites

If you need to re-run the schema upgrade across all sites (e.g. after a major version update):

```php
// In a must-use plugin or WP-CLI script
foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
    switch_to_blog( $blog_id );
    \UplinkPortal\Core\Activator::activate();
    restore_current_blog();
}
```

Or via WP-CLI:
```bash
wp site list --field=url | xargs -I {} wp --url={} eval '\UplinkPortal\Core\Activator::activate();'
```

---

## Deactivation

Network deactivation removes roles and capabilities for every site via the same `switch_to_blog()` loop pattern as activation. Custom tables are **not dropped** on deactivation — data is preserved for re-activation.

To fully uninstall including table removal, use the uninstall routine (triggered by deleting the plugin):

```bash
wp plugin delete uplinkportal --network
```

---

## Version Upgrade Path (DB Migration)

`Activator::activate()` implements an idempotent upgrade path:

```php
$stored_version = (string) get_option( 'uplinkportal_db_version', '0.0.0' );
if ( version_compare( $stored_version, UPLINKPORTAL_VERSION, '<' ) ) {
    // Re-run dbDelta for each schema.
    update_option( 'uplinkportal_db_version', UPLINKPORTAL_VERSION, false );
}
```

`dbDelta()` is safe to run on already-existing tables — it only adds missing columns and indexes, never drops them. Running activation again on an existing site is safe.

---

## WordPress Playground

For local Multisite testing with WordPress Playground, use the filter-driven mock approach rather than embedding backend logic:

```php
// Simulate a validated backend connection for testing
add_filter( 'uplinkportal_backend_validation_result', static function () {
    return true;
} );
```

See [Local Dev](local-dev.md) for the full Playground setup guide.

---

## Caveats

- **Large networks (100+ sites):** Network activation iterates synchronously over all sites. For very large networks, consider activating site-by-site via WP-CLI to avoid request timeouts.
- **Object cache:** If a persistent object cache (Redis, Memcached) is in use, flush it after network activation to ensure all sites pick up the new option values.
- **Sunrise drop-in:** If your multisite uses a custom `sunrise.php` that remaps blog IDs, test activation in staging before production.

---

## See Also

- [Deployment Security Guide](deployment-security-guide.md)
- [Local Dev](local-dev.md)
- [Architecture Overview](architecture.md)
