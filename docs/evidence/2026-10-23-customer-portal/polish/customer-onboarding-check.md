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
- Focused Pint and whitespace checks pass. Disposable database, receiver and
  worker are cleaned up by test teardown. No external mailbox is used by tests.
- Read-only live revision and `operations:queue-health` confirm the expected
  release and healthy queue. Actual production SMTP-to-Gmail verification/reset
  delivery was observed earlier this day, as recorded in account lifecycle and
  password-reset evidence; this is shared transport evidence, not a production
  customer invitation receipt claim.
- A clearly labeled production QA customer plus sample project/service and
  invitation to the owner's Gmail alias was offered as an optional final inbox
  check. This requires the owner's separate authorization for production data.
  Until authorized and performed, this follow-up does not claim live invitation
  inbox placement or production customer activation/login. The owner chooses
  any live account password personally.
