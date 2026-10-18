# C3.1 public customer registration

Status: in progress after C2 qualification at backend 0c3d606 / Next 5467e0d.

Direct app registration accepts name and email only. A queued shared identity
action reuses trusted provisional customer creation and email activation. The
customer verifies control of the address and chooses a strong password through
the existing signed, single-use activation flow. Public callers cannot assign
roles, set verified state, choose user/cart IDs, or request authentication tokens.
No account/session is authenticated or cart attached by the registration request.

Unknown, existing, privileged and ineligible addresses receive the same accepted
HTTP response/page. Identity processing happens after that response through the
database queue, so synchronous existence checks cannot disclose an account.
Existing identity data/password/privileges remain unchanged. Only an unverified,
customer-only identity can receive public activation; verified identities use
the existing sign-in/password recovery routes. Repeated eligible requests may
resend activation within abuse limits. Queue retries must not create duplicates.

Expose app `/register` plus versioned bootstrap/submission APIs through the web
session, real CSRF, configured exact Origin and hashed email/IP rate limits.
Native and REST adapters call the same queue entry point. Keep trusted activation
and both Filament panels intact; add the registration link only to customer login.
Cart session data survives registration; attachment after verified authentication
and login-merge choices belong to C3.2, which cannot begin before these gates pass.

Acceptance: direct registration without cart; generic duplicate/privileged
outcomes; strict role/owner/input denial; real CSRF/Origin and abuse limits;
normalized case-insensitive identity, retry/concurrency safety, customer-only
activation, strong password and signed-link replay protection. Prove a real
database worker -> loopback SMTP test receipt -> activation -> verified session
with disposable data, plus native desktop/320px workflow and both PHP 8.3 database
CI jobs/full regression/metadata/audit/build/cache gates. No new real email,
SMTP/provider setting, production identity, migration or deployment operation.

## Local implementation checkpoint

Shared action/job, native app form/customer-login link and strict versioned
bootstrap/submission contract are implemented. Entry always uses the database
queue even if the default driver is sync; no synchronous existence check.
The existing activation greeting now avoids untrusted name Markdown.
No new schema, authentication-token issuance, SMTP setting or real identity.

Local PHP 8.5.11: `composer test -- --compact` **302 tests / 5,331 assertions**;
Pint, Composer strict validation, whitespace and production Vite build pass.
Real independent worker/race tests prove one normalized customer under four
processes and database queue -> credential-free loopback SMTP receipt -> signed
activation -> verified session; existing activation/identity/panel cases pass.
Initial SMTP harness failure came from empty MAIL_URL overriding transport;
explicit null and loopback/credential/URL guards correct the disposable harness.
No application/provider workaround or audit suppression was needed.

Actual Chrome 1440/320px passes ten checks, including customer-only login link,
public registration without cart, keyboard/generic accepted feedback, actual
email transport and activation, CSRF and role-injection denial, generic existing
identity response and used-link protection. No overflow/page/console errors.
Screenshots/results: `docs/evidence/2026-10-18-registration`. All private fixture
DB/key/mail/scripts are removed and HTTP/SMTP servers stopped. PHP 8.3 SQLite/
MySQL CI remains pending; C3.1 is partial and C3.2 must wait. Existing privacy/
terms links do not resolve the remaining D08 production policy decisions.
