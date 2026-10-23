# Customer portal API

The portal is independent of checkout. Projects and services are manually
assigned by authorized staff; they are operational records, not purchased
entitlements or proof of payment. Canonical schemas are in [OpenAPI](openapi.json).

All routes below use `/api/v1/portal`, Sanctum authentication, verified customer
role and ownership, the customer 120/minute throttle, private/no-store responses,
and a 64 KiB request limit. Another customer's opaque reference returns 404.
The `data` envelope never includes internal notes, actor IDs, customer IDs,
permission data, or private audit payloads.

| Method/path | Behavior |
|---|---|
| GET `/works?type=project&page=1&search=website` | Customer-owned project/service summaries; `type` and search optional |
| GET `/works/{reference}?page=1` | Milestones, next step, dates, assigned contact and paginated public updates |
| POST `/works/{reference}/read` | Mark work activity read; empty body |
| GET `/categories` | Active support categories; category is optional when creating a case |
| GET `/cases?page=1&search=design` | Owned requests/reports |
| POST `/cases` | Create request/report, optionally linked to an owned work record |
| GET `/cases/{reference}?page=1` | Customer conversation, safe status history and reply availability |
| POST `/cases/{reference}/messages` | Reply to an active owned case |
| POST `/cases/{reference}/read` | Mark case activity read; empty body |

Browser sessions need the normal first-party Sanctum session/CSRF flow. Read
the CSRF token from the authenticated panel's `csrf-token` HTML meta tag. POSTs
require the exact configured app `Origin` (including Bearer commands); session
POSTs also require `X-CSRF-TOKEN`. Foreign origins without a stateful session
are unauthenticated; authenticated commands with a mismatched Origin are denied.

Case creation accepts only `type` (`request` or `report`), `subject` (1–255),
`description` (1–10,000), optional `work` and optional `category` ULIDs. No owner,
priority, status, assignment or attachment upload field is accepted. Account,
payment, privacy and security reports do not require a work record or purchase.

Creation and reply require a UUID `Idempotency-Key`. UUID casing is normalized.
Retries with the same actor/key and exact payload return the existing result;
changed input conflicts with 409. Replies accept a native JSON integer
`expected_revision` and `body` (1–10,000). Stale revisions and replies to
resolved/closed cases return 409. Staff manually reopen cases. Query parameters
and payloads reject unknown fields. Missing/invalid keys or content return 422.

Lists contain 12 items. Work updates contain 20 and conversations 30 per page;
page 1 contains the newest records, with conversation messages displayed
chronologically within that page. Work requiring customer input comes first;
completed/cancelled work and resolved/closed cases follow active records.
Read markers do not increment revisions or public modification timestamps.
Staff-only notes do not invalidate customer replies, create unread customer
activity, or enter customer status history. In-app activity indicators are
implemented; outbound portal email notifications are not part of this slice.
