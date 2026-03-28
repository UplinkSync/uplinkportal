# Backend Observability Contract

This document defines the health endpoint contract, request correlation strategy, and
rate-limiting response contract between the public-core WordPress plugin and the private
backend service.

---

## Health Endpoint

### Plugin-side: `GET /wp-json/uplinkportal/v1/health`

The public core exposes a WordPress REST endpoint for MSP operators.

- **Authentication required**: `msp_manage` capability.
- **Response** (200):
  ```json
  {
    "status": "ok",
    "version": "0.2.0",
    "request_id": "<uuid4>"
  }
  ```
- **Response headers**:
  - `Cache-Control: no-store, private, max-age=0`
  - `X-UplinkPortal-Request-Id: <uuid4>` — matches `request_id` in the body.
- **No secrets or environment details are ever included in this response.**

The `request_id` is a random UUID4 generated per request. It must be logged by the caller
alongside any error context so that support engineers can correlate plugin-side and
backend-side entries without exposing internal state.

### Backend service: `GET {backend_base_url}/health`

The private backend service exposes its own `/health` endpoint at the path configured via
`Options::get_backend_health_path()` (default `/health`).

- **Response** (200):
  ```json
  {
    "status": "ok",
    "version": "<backend-semver>"
  }
  ```
- The backend health path is included in `BackendValidationBroker` payloads as
  `backend_health_path` so the private add-on can self-report its reachability.
- **No secrets, tokens, or environment configuration are returned from this endpoint.**

---

## Request Correlation

Every response from `uplinkportal/v1/health` includes an `X-UplinkPortal-Request-Id`
header and a matching `request_id` field in the JSON body.

Correlation rules:

1. The plugin generates a fresh UUID4 per request (`wp_generate_uuid4()`).
2. The ID is included in both the HTTP response header and the body.
3. Callers that encounter an error must log the request ID alongside the error message.
4. The backend service must propagate the plugin request ID when it receives calls
   originating from the same WordPress request (pass through via `X-Request-Id` header
   or equivalent).
5. No secrets, user data, or internal paths are ever included in the request ID or error
   responses.

---

## Rate Limiting Contract

### Documented Limits

The private backend enforces the following rate limits. These values are illustrative
minimums; the deployed backend may raise them but must not lower them without updating
this document.

| Endpoint category            | Limit                    | Window   |
| ---------------------------- | ------------------------ | -------- |
| Validation (`/validate`)     | 60 requests per tenant   | 1 minute |
| Health (`/health`)           | 120 requests per tenant  | 1 minute |
| Document key exchange        | 30 requests per client   | 1 minute |
| OAuth token lifecycle        | 30 requests per tenant   | 1 minute |
| All other authenticated APIs | 300 requests per tenant  | 1 minute |

Limits are applied per tenant (identified by the service token or Bearer credential).
Burst headroom of up to 2x the per-minute limit is allowed for a window of up to 5
seconds to accommodate momentary spikes without false throttling.

### Throttling Response

When a rate limit is exceeded the backend returns:

```
HTTP/1.1 429 Too Many Requests
Content-Type: application/json
Retry-After: <seconds>
X-RateLimit-Limit: <limit>
X-RateLimit-Remaining: 0
X-RateLimit-Reset: <unix-epoch-seconds>

{
  "code": "uplinkportal_rate_limit_exceeded",
  "message": "Request rate limit exceeded. Retry after the indicated number of seconds.",
  "data": {
    "status": 429,
    "retry_after": <seconds>
  }
}
```

Field semantics:

- `Retry-After`: whole seconds the client must wait before retrying.
- `X-RateLimit-Limit`: the maximum requests allowed in the window.
- `X-RateLimit-Remaining`: always `0` when a `429` is returned.
- `X-RateLimit-Reset`: UTC Unix timestamp when the window resets.
- The JSON error code `uplinkportal_rate_limit_exceeded` is stable across backend versions
  and safe to check programmatically.

### Plugin Behavior on 429

The public-core plugin must not silently swallow `429` responses. When the private backend
returns `429`, the plugin must:

1. Surface a `WP_Error` with code `uplinkportal_rate_limit_exceeded` and include the
   `retry_after` value in the error data.
2. Never automatically retry within the same WordPress request lifecycle.
3. Display a safe, actionable admin notice to MSP operators when a `429` is encountered
   during a settings-save or diagnostic operation.

---

## Abuse Controls

The private backend additionally enforces the following abuse protections:

- **IP-level throttling**: aggressive request spikes from a single IP that exceed tenant
  limits by more than 10x within 10 seconds are temporarily blocked (60 seconds).
- **Credential stuffing protection**: repeated authentication failures (5+ within 60
  seconds for a tenant) trigger a temporary lockout (300 seconds) with `403 Forbidden`
  and error code `uplinkportal_auth_lockout`.
- **Payload size limit**: request bodies exceeding 64 KB are rejected with `413 Content
  Too Large` before processing.

These controls are enforced entirely within the private backend and require no public-core
plugin changes.

---

## Related

- [Architecture](architecture.md)
- [Permission Enforcement Boundary](permission-enforcement-boundary.md)
- [Deployment Security Guide](deployment-security-guide.md)
- [Centralized Monitoring Design](centralized-monitoring-design.md)
