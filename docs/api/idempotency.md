# API Idempotency

Authenticated API mutations that can be retried attach the `idempotent`
middleware. No current route uses it yet; it is a reusable boundary for future
checkout, brief, message, approval, and correction mutations.

## Client contract

- Send an `Idempotency-Key` header containing 8–255 URL-safe characters.
- Reuse the same key only for an identical HTTP method, route, query, body, and
  file set. Object key order and multipart boundaries do not affect the request
  fingerprint.
- Keys are scoped to the authenticated principal, HTTP method, and route.
- The first completed response returns `Idempotency-Replayed: false`.
- A retry returns the stored status, body, content type, location, and ETag with
  `Idempotency-Replayed: true`; application code does not run again.
- Reusing a key for different request data returns `409
  idempotency_key_reused`. Retrying while the original request is still active
  returns `409 idempotency_in_progress` with `Retry-After: 1`.
- Server errors and uncaught exceptions are not stored, so clients can retry.

## Persistence and operations

Only SHA-256 hashes of the caller scope, client key, and canonical request are
stored. Completed response data is retained for 24 hours by default. Expired
records are removed lazily on reuse and by the daily
`api:idempotency:prune` scheduled command.

Laravel cache locks serialize matching requests before the database record is
read or created; the table also has a unique scope/key constraint. Pending
records are considered abandoned after 15 minutes by default, longer than the
five-minute lock lease. The settings can be overridden with:

- `API_IDEMPOTENCY_TTL_HOURS`
- `API_IDEMPOTENCY_PENDING_TIMEOUT_SECONDS`
- `API_IDEMPOTENCY_LOCK_SECONDS`
- `API_IDEMPOTENCY_LOCK_WAIT_SECONDS`

The default database cache store supports the required distributed atomic
locks. A deployment that changes `CACHE_STORE` must choose another store with
atomic-lock support.
