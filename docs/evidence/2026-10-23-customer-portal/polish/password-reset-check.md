# Password reset delivery check — 2026-10-23

Owner reported no admin forgot-password email and requested the customer flow be
checked too. The owner subsequently approved the production cron repair and
automatic delivery verification; it is now complete.

## Confirmed production condition before repair

At 09:59 UTC / 17:59 PHT, the owner reset token had been created at 09:46:26 UTC.
One Filament ResetPassword notification remained queued, attempts zero, no
reservation and no failed jobs. The existing user is an unverified super admin;
there are zero production customer accounts. No real customer was created for
this check. Both panels use the same database mail queue and SMTP transport.

The previously sent owner bootstrap notification was independently found in the
recipient Gmail INBOX at 08:34:36 UTC / 16:34:36 PHT. SPF/DKIM/DMARC pass.
At diagnosis the newly reported reset was still queued; this was not evidence of a Gmail spam
or SMTP rejection. No token, password, message body or credential is retained.

hPanel has two every-minute commands beginning with `cd ... && /usr/bin/php
artisan`: scheduler and database worker. Worker output is empty. The queue
condition proves the automatic worker has not attempted the new notification.
The shell command format is a suspected cause, pending a controlled repair and
scheduled execution proof. Hostinger documents limitations around special
characters in cron commands:
https://www.hostinger.com/support/1583514-troubleshooting-cron-jobs-at-hostinger/

## Scoped verification

- Existing focused authentication tests pass: 2 tests / 12 assertions.
- Isolated diagnostic tests pass: 3 tests / 40 assertions. Both unverified admin
  and customer forms create a real database notification job; a worker drains it
  into the local array transport with the correct panel reset URL. Admin-only
  identities receive no reset email through the customer panel. Synthetic
  isolated databases are removed by teardown; no real email is sent by tests.
- Live customer login → Forgot password opens the expected reset form.
- The proposed absolute-path worker executable loads successfully from the
  hosting home directory with `--help`; no job processing or cron mutation was
  performed during diagnosis.

## Approved operational repair

On app.adzbyte.com / hosting account u463458285, replace the two existing cron
entries with these every-minute commands. Replacements were created and proved
before the recoverable old entries were removed:

```text
/usr/bin/php /home/u463458285/domains/app.adzbyte.com/public_html/artisan schedule:run
/usr/bin/php /home/u463458285/domains/app.adzbyte.com/public_html/artisan queue:work database --stop-when-empty --tries=3 --backoff=10 --timeout=60 --max-time=50
```

Reservation retry-after remains 180 seconds. Application code, customer records,
passwords, roles and identities were unchanged. Only cron definitions and normal
password-reset request/token/job processing changed.

## Completed verification — 18:58–19:01 PHT

- The corrected absolute-path worker ran automatically. At 10:58:45 UTC,
  queued jobs and failed jobs were both zero. No manual worker invocation was
  used during this repair. hPanel worker output at 10:59:05 UTC reports
  `Worker STOPPED Queue empty`.
- The original queued reset had expired during the delay. A fresh request was
  submitted through the actual admin password-reset form at 10:58:01 UTC.
  Its email arrived in the owner Gmail **INBOX at 18:58 PHT**. Gmail groups
  the bootstrap, delayed and fresh reset into one conversation; use the newest
  message. No reset link was opened or password changed by the agent.
- Gmail connector search was temporarily rate-limited, so receipt was verified
  in a separate browser search tab. The result shows Adzbyte Solutions,
  Reset your password, Inbox and 6:58 PM. No token/body was captured.
- Replacement scheduler output at 10:59:02–10:59:04 UTC shows native package
  delivery recovery, order expiry and queue monitoring all DONE.
- Both obsolete `cd ... && php artisan ...` cron entries were removed only
  after successful replacement runs. Exactly the two corrected every-minute
  entries remain. The old command format is confirmed as the operational
  failure addressed by this repair; no Laravel password-reset code change
  was required.
- `operations:queue-health` passes. Deployed HEAD remains
  `230f2cd90d2b11ea12a03a00640fe35df66f260b`, with no tracked application diff.
- Customer form/queue/delivery-link tests remain the isolated proof recorded
  above; no production customer account was created solely for testing.
