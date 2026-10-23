# Live linked report and admin reply — 2026-10-23

The owner explicitly requested one QA project-linked submission, an admin reply,
and confirmation that the customer can read it on the project and Requests &
reports pages. Existing deploy `3bcc292e5931` remains unchanged. No application
fix, schema change or new deployment was necessary.

## Live customer submission

Using the activated QA customer's existing Chrome session, opened
**QA SAMPLE — Website Project** and selected **New request or report**. The form
preselected and locked that project. Selected **Report an issue** and submitted
**QA TEST — Project-linked report and admin reply**, with an explicit QA-only
description and no real work request.

The modal closed, the report expanded inline and the browser remained on the
same `/projects?record=01M5MF3NHNP4HXJAG5W3BWA4NY` URL. The report reference is
`01M5MGYBRP2B9K52Y6ZAS83QGV`, owned by QA customer 2 and linked to work 1.
Its initial state is Open, General category, Normal priority.

## Public staff reply

At **22:00:12 PHT**, posted one explicitly labeled QA admin reply as the existing
verified owner super administrator (actor 1). Used the same shared
`PortalActions::reply` service invoked by the admin panel, through SSH, with
staff=true, note=false, expected revision 1 and a fixed UUID operation key.
Normal capability, policy, row-lock, revision, audit and duplicate protection
remain enforced. No browser admin session was fabricated or customer password
requested; the admin browser adapter was not exercised in this check.

The public message confirms the project link and names both customer views.
Case revision advanced to 2, a message_sent audit event records actor 1, and
customer_read_at became null. No private note was created.

## Customer confirmation

- Reloaded the project page: its report row displayed **New update**.
- Expanded that row: the customer sees the original report, correct project
  link, and **Adzbyte Solutions · Team** public reply inline on the project.
- Opened **Requests & reports**: the same report is listed with the project name.
- Expanded it: the same reference, original description and admin reply appear.
  The page remains on `/support?record=01M5MGYBRP2B9K52Y6ZAS83QGV`.
- Reloaded that expanded Requests thread: the same public reply remains visible.
- Read-only production check at **22:01:50 PHT** confirms one matching report,
  one public message from actor 1, zero private notes, correct QA owner/project,
  revision 2 and customer read acknowledgement. Queue/failure counts remain zero.

Both customer surfaces pass. The report remains Open and clearly labeled for
further manual testing. The signed-in customer is left on the expanded Requests
thread. Screenshots are saved outside Git as `qa-report-project-reply.png` and
`qa-report-requests-reply.png`. Existing automated workflow/ownership coverage is
unchanged; this live check adds no code and did not rerun the full test suite.
