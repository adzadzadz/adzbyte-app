# O2.3c partial: sandbox refund qualification unavailable

2026-10-19. O2.3b qualified source46614f8 / CI39191936629 before this
contract-discovery card began. **O2.3c is not implemented or qualified.**
Successful partial/full refunds, claims, recovery/disputes and notifications are
unverified. O3/S2, R1 and Q remain pending under the owner's phase gate.

## Actual provider evidence

- Existing synthetic test payment pay_urY4hsSwbjmkDVW6FrKodS7V remains paid for
  PHP125.00, livemodefalse, disputedfalse. No live key/account or real funds used.
- One durably reserved PHP50.00 partial command to the
  [QRPh-specific endpoint](https://docs.paymongo.com/docs/qrph-refunds) returned
  HTTP422 with ref_dea043c94f124d61b90f58df, statusfailed, livemodefalse and no
  transfer_link or failure-cause field. No automatic POST retry occurred.
- Current payment GET confirms that exact failed refund in refunds[]. Native
  test-mode payment detail independently shows Refund failed/PHP50.00 and full
  PHP125.00 still refundable. QRPh-specific and generic refund-by-ID GETs each
  return404, so neither establishes a successful recovery/claim contract.
- After confirmed failure and a fresh unchanged payment GET, a separate reserved
  command to the [documented generic endpoint](https://docs.paymongo.com/docs/payment-acceptance-refunds)
  returned HTTP400 parameter_invalid: QRPh source type is not allowed. It
  returned no refund ID. No alternate provider POST endpoints were guessed.
- Test-mode Wallets shows No wallet found; documented GET /v2/wallets redirects
  to its same-host canonical /v2/wallets/ and returns HTTP200/data[] with the test
  secret. **Wallet count is zero.** No wallet/account was opened or funded.

[Sanitized API proof](refund-contract-discovery.json),
[provider failed refund](refund-provider-failed.png),
[test wallet unavailable](refund-wallet-unavailable.png).

The [QRPh guide](https://docs.paymongo.com/docs/qrph-refunds) requires available
merchant-wallet balance and says the refund succeeds only after the customer
claims its transfer link. Missing wallet is a plausible prerequisite issue,
**not the proven cause** of the unexplained422. Public money-movement guides
mention activated wallets/test funds, but no verified self-service provisioning
or QRPh refund test-claim path exists for this account in the observed UI/API.
The linked wallet test-case documentation currently returns404. Generic card or
wallet mocks cannot substitute for this merchant's actual QRPh outcome.

## Required next input

Ask PayMongo to provision/enable the merchant's sandbox wallet with test funds
and confirm a supported QRPh refund claim simulator, terminal success/failure
cases, and authoritative retrieval/webhook contracts. No real deposit or bank
information is needed for qualification. The [support-ready note](refund-support-note.md)
contains safe reproduction details; it has not been sent. Owner was asked
asynchronously about arranging sandbox access. Independent reviewer confirms the
blocker and the distinction between a plausible wallet issue and proven cause.

After provisioning, inspect both preserved test payments and failed refund
before issuing a new command. Then implement and qualify durable once-only
native Laravel refund operations, verified full/partial/pending/unknown outcomes,
bounded sums, exact Admin policies, native/REST contracts, notifications,
disputes and failure recovery. Complete actual sandbox/native/HTTP/schema/races,
independent review and PHP8.3 SQLite/MySQL CI before downstream work.

## Cleanup and recovery

Dedicated task test hook hook_6tzkg95oQ8ddAMayZyQUuBJX is disabled and GET-verified;
original WordPress/live hooks remain unchanged. Public tunnel, webhook ingress
and QA UI are stopped. Private raw captures, APP_KEY/test-key fixture and a
verified backup of the synthetic database (two distinct settlements) are retained
only in ignored mode0700 directory storage/app/private/paymongo-qa-recovery-2026-10-19;
all files0600. Its README describes guarded restoration to a new disposable
catalog-concurrency-* database. Temporary original secrets/captures/database and
downloaded tunnel runtime were removed. Owner-requested live/test credential
archives remain unchanged, ignored and0600. Existing development/production
DBs, .env and live provider configuration were untouched.
