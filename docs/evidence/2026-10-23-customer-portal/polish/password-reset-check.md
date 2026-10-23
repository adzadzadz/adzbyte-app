# Password reset delivery check — 2026-10-23

Owner reported no admin forgot-password email and requested the customer flow be
checked too. Diagnosis only; production repair approval is pending.

## Confirmed production condition

At 09:59 UTC / 17:59 PHT, the owner reset token had been created at 09:46:26 UTC.
One Filament ResetPassword notification remained queued, attempts zero, no
reservation and no failed jobs. The existing user is an unverified super admin;
there are zero production customer accounts. No real customer was created for
this check. Both panels use the same database mail queue and SMTP transport.

The previously sent owner bootstrap notification was independently found in the
recipient Gmail INBOX at 08:34:36 UTC / 16:34:36 PHT. SPF/DKIM/DMARC pass.
The newly reported reset is still queued; this is not evidence of a Gmail spam
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

## Prepared operational repair

On app.adzbyte.com / hosting account u463458285, replace the two existing cron
entries with these every-minute commands, creating and proving replacements
before removing the recoverable old entries:

```text
/usr/bin/php /home/u463458285/domains/app.adzbyte.com/public_html/artisan schedule:run
/usr/bin/php /home/u463458285/domains/app.adzbyte.com/public_html/artisan queue:work database --stop-when-empty --tries=3 --backoff=10 --timeout=60 --max-time=50
```

Reservation retry-after stays 180 seconds. No application code, identity,
password, role or customer record changes are needed. Verify an actual scheduled
worker run, queue drained / failed jobs zero and recipient Gmail receipt. Use a
fresh owner reset request if the pending link has expired. Do not claim repair
or automatic delivery before these checks pass.
