# Customer creation and activation check — 2026-10-23

Owner requested proof that a manually created customer receives an invitation,
activates, can sign in, and sees assigned projects and services. Existing live
source remains `a3eeb5a96c80`, deploy `3bcc292e5931`; this follow-up adds test
coverage only, with no application, schema, mail credentials or production data
change.

## Combined journey

`CustomerOnboardingTransportTest` runs against a fresh disposable database.
It uses the actual admin Customers Livewire action to create a normalized,
unverified customer-only account and queues exactly one invitation. Actual
ClientWork actions create a project and service, including the customer selection
provided by the customer page. The resulting records retain the new customer's
identity, with no staff role or direct permissions.

An independent queue worker restores the serialized notification and delivers it
to a credential-free loopback SMTP receiver. The received message has the correct
recipient, Adzbyte Solutions activation subject/action and signed activation URL.
The test follows that captured URL rather than reconstructing an action URL.
Tampering is rejected. Activation sets the chosen synthetic password, verifies
email, and signs the customer in; replay is rejected. After logout, the real
Filament login action accepts the chosen credentials. Dashboard shows both
assigned records, Projects and Services lists show the correct types, and each
record's detail page shows its title and next step. Another customer's work
returns 404, admin access returns 403, and no redundant verification job appears.

## Results and boundary

- Combined journey: **1 test / 77 assertions**, exit 0.
- Focused combined onboarding, portal workflow, activation and ownership checks:
  **32 tests / 329 assertions**, exit 0, local PHP 8.5.11 SQLite.
- Source `a6a790bc7e8544f265e71844ccdf214a9bc496d3` passes
  [CI 39960905290](https://github.com/adzadzadz/adzbyte-app/actions/runs/39960905290):
  **678 tests / 8,930 assertions per database**, PHP 8.3.36 SQLite/MySQL,
  both jobs successful, dependency audits/build/cache gates pass. This adds
  test coverage only and requires no production code deployment.
- Focused Pint and whitespace checks pass. Disposable database, receiver and
  worker are cleaned up by test teardown. No external mailbox is used by tests.
- Read-only live revision and `operations:queue-health` confirm the expected
  release and healthy queue. Actual production SMTP-to-Gmail verification/reset
  delivery was observed earlier this day, as recorded in account lifecycle and
  password-reset evidence; this is shared transport evidence, not a production
  customer invitation receipt claim.
- The owner subsequently approved the production QA customer, sample work and
  invitation. Live creation/delivery is confirmed below; password choice and
  activation remain owner actions.

## Approved production inbox check

The owner explicitly selected **Create QA customer and send invitation**.
Actual signed-in administrator UI created **QA Portal Test Customer** (user 2)
for `adzbite+portal-test@gmail.com` at **21:26:26 PHT**. The account has only the
customer role, zero direct permissions and remains unverified pending activation.
The UI retained customer 2 through Assign project or service. It created:

- **QA SAMPLE — Website Project**, type project, work 1.
- **QA SAMPLE — Website Care Service**, type service, work 2.

Both are Not started, explicitly described as QA samples with no paid work,
billing or delivery commitment, and visible together on the customer's admin
record. No unrelated customer, owner identity or package/payment data changed.

The automatic scheduled queue worker delivered the actual invitation to Gmail
**INBOX at 21:27:04 PHT (13:27:04 UTC)**, from
`Adzbyte Solutions <support@adzbyte.com>`, subject
**Activate your Adzbyte Solutions account**. Gmail metadata and the visible
search row confirm recipient, subject and inbox placement. Only metadata and
search-row evidence were read; the signed link was not copied, opened or consumed.
At 13:29:44 UTC there were zero queued jobs, zero failed jobs, one QA customer
and exactly the two assigned work records. The owner remains responsible for
choosing the password through the invitation, preferably in a private window so
the administrator browser session remains separate.

## Owner activation and live customer pages

The owner reported completing activation. Read-only production confirmation at
**21:34:34 PHT** shows customer 2 verified, customer role only, zero direct
permissions, both original assignments, zero queued jobs and zero failed jobs.
Actual Chrome customer session identifies QA Portal Test Customer. Reloading
its protected project URL preserves authenticated access without a verification
prompt. Dashboard displays **1 active project and 1 active service**, both QA
cards and the verified identity. Projects lists only the sample project; Services
lists only the sample service. Both detail pages display the matching title,
summary, Not started status, next step and linked requests/reports section.
Customer screenshots were saved outside Git.

After this successful authenticated check, customer Sign out returned to `/login`.
The QA email is filled and the owner is asked to enter the password directly in
Chrome for a fresh login check. Fresh password-form login is pending that manual
step; activation-session access and work visibility are verified. The isolated
activation/logout/fresh-login regression already passes; no production password
was read, set or bypassed. Test records remain clearly labeled as approved.
