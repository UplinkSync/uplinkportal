# Deployment Security Guide

This guide covers the security-critical configuration steps required before taking an UplinkPortal installation into production. Follow every section before exposing the portal to real clients.

---

## 1. Crypto Salt

UplinkPortal uses a project-specific cryptographic salt for signing and key-derivation operations. The default placeholder **must** be replaced.

### Configure the Salt

Add the constant to `wp-config.php` (or your must-use plugin / deployment secrets manager):

```php
define( 'UPLINKPORTAL_CRYPTO_SALT', 'your-project-specific-random-string-here' );
```

**Requirements:**

- Minimum 32 characters.
- Generated with a cryptographically secure random source (e.g. `openssl rand -hex 32`).
- **Unique per WordPress installation.** Never reuse across environments.
- Never committed to version control.

> The plugin displays a persistent error notice in wp-admin until this is changed.

### Verification

Run the repo hygiene scan to confirm the default salt is not present in any committed file:

```bash
./infrastructure/scripts/repo-hygiene-scan.sh
```

---

## 2. Backend Service Token

The `UPLINKPORTAL_BACKEND_SERVICE_TOKEN` (or the equivalent option stored via `Options::set_backend_service_token()`) is used to:

- Sign outgoing HMAC-SHA256 request signatures on security alert webhooks (`X-UplinkPortal-Signature`).
- Authenticate calls to the private backend API.

### Requirements

- Minimum 32 characters, high entropy (use `openssl rand -hex 32`).
- Rotated on any suspected compromise (see [Secret Rotation Runbook](../troubleshooting/secret-rotation-runbook.md)).
- Stored in `wp-config.php` or injected via deployment secrets tooling — **never in the database or version control**.

### HMAC Signature Verification (Webhook Consumer Side)

Consumers of the generic webhook can verify inbound alerts with:

```php
$raw_body       = file_get_contents( 'php://input' );
$expected_token = 'your-shared-secret';
$expected_sig   = 'sha256=' . hash_hmac( 'sha256', $raw_body, $expected_token );
$actual_sig     = $_SERVER['HTTP_X_UPLINKPORTAL_SIGNATURE'] ?? '';

if ( ! hash_equals( $expected_sig, $actual_sig ) ) {
    http_response_code( 403 );
    exit;
}
```

---

## 3. SecretsStore Encryption

UplinkPortal uses `SecretsStore` (backed by libsodium `sodium_crypto_secretbox`) to encrypt sensitive integration secrets at rest in the WordPress options table. If libsodium is unavailable, secrets fall back to plaintext storage with a persistent admin warning.

### Verify Encryption Is Active

1. Navigate to **Configuration → Security → Secrets** in wp-admin.
2. The **Encryption Status** field must read:
   > `Encryption active (libsodium sodium_secretbox_v1)`
3. If it reads "libsodium is not available …", enable the PHP `ext-sodium` extension:

   ```ini
   ; php.ini
   extension=sodium
   ```

4. After enabling `ext-sodium`, existing plaintext secrets are re-encrypted on the next write. Rotate any secrets that were stored unencrypted as a precaution.

### Rotate Secrets After Enabling Encryption

If libsodium was previously unavailable, rotate all secrets stored in that period. See [Secret Rotation Runbook](../troubleshooting/secret-rotation-runbook.md) for step-by-step procedures.

---

## 4. Transport Security

All outbound HTTP requests from UplinkPortal enforce HTTPS by default (`TransportSecurityPolicy`). To preserve this:

- **Do not set** `uplinkportal_allow_insecure_transport` to `true` in production.
- Ensure all backend URLs (service token endpoint, monitoring webhook, licensing worker) use `https://`.
- The `reject_unsafe_urls` flag is always `true` in outbound requests and must not be overridden.

### Verify TLS Configuration

```bash
# Check backend base URL
wp option get uplinkportal_backend_base_url

# Confirm the URL starts with https://
```

---

## 4. PHP and WordPress Version Gates

UplinkPortal's activator enforces minimum versions at activation time:

| Requirement | Minimum Version |
|---|---|
| PHP | 8.3 |
| WordPress | 6.0 |

If the environment does not meet these requirements, activation is blocked with a clear message. Never attempt to bypass these checks in production environments.

---

## 5. Capability Model

UplinkPortal defines two capability namespaces:

| Prefix | Scope |
|---|---|
| `msp_*` | MSP operator — full plugin management |
| `client_*` | Client portal user — limited to portal features |

### Roles Registered

- `MSP Administrator (UplinkPortal)` — receives all `msp_*` capabilities.
- `Client Portal User (UplinkPortal)` — receives `client_access` and read-only `client_view_*` capabilities.

### Best Practices

- Never assign `msp_manage` to client-facing accounts.
- Audit role assignments regularly: **Configuration → Security → Role Assignments**.
- Restrict `wp-admin` access for `Client Portal User` roles — the portal is designed for frontend-only access.

---

## 6. Nonce Enforcement

All REST endpoints enforce WP REST nonces for cookie-authenticated requests. This is controlled by the `uplinkportal_enforce_rest_nonce` filter.

**Never set this filter to `false` in production.** It exists only for isolated unit testing.

---

## 7. Input Sanitisation and Output Escaping

All plugin code follows the WPCS rules:

- Every user input is sanitised (`sanitize_text_field`, `sanitize_key`, `absint`, etc.).
- Every output is escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Never render raw `$_GET`/`$_POST` values without sanitisation.

---

## 8. Secret Storage Checklist

Before go-live, confirm each item:

- [ ] `UPLINKPORTAL_CRYPTO_SALT` changed from placeholder.
- [ ] `UPLINKPORTAL_BACKEND_SERVICE_TOKEN` set with high-entropy value.
- [ ] Backend base URL is HTTPS.
- [ ] Monitoring webhook URL (if configured) is HTTPS.
- [ ] Licensing worker base URL (if configured) is HTTPS.
- [ ] `.env` is listed in `.gitignore` and not committed.
- [ ] `repo-hygiene-scan.sh` passes with no findings.
- [ ] `composer lint` passes.
- [ ] `composer test` passes.

---

## See Also

- [Secret Rotation Runbook](../troubleshooting/secret-rotation-runbook.md)
- [Multisite Deployment](multisite-deployment.md)
- [Architecture Overview](architecture.md)
