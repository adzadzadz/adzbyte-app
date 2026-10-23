# PayMongo sandbox QRPh refund assistance

Prepared for the owner to send; **not sent**. Contains synthetic test references
only, no credentials, signatures, client keys, bank details or real customer data.

Please help enable our sandbox wallet and a supported QRPh refund test-claim flow.
The test dashboard shows “No wallet found”; GET /v2/wallets/ returns200 with data[].

Our synthetic QRPh payment pay_urY4hsSwbjmkDVW6FrKodS7V is paid for PHP125.00,
livemodefalse, disputedfalse. On2026-10-19, POST
https://refunds-api.paymongo.com/v1/refunds with amount5000, reason
requested_by_customer and this payment_id returned422 and refund
ref_dea043c94f124d61b90f58df with statusfailed/livemodefalse, no transfer_link or
failure reason. Payment GET and Dashboard confirm that failed PHP50.00 refund;
the full PHP125.00 remains refundable. GET /v1/refunds/{id} returns404 at both
refunds-api.paymongo.com and api.paymongo.com.

After verifying the earlier failure, a separate POST to the documented generic
https://api.paymongo.com/v1/refunds returned400 parameter_invalid:
“Refunds are not allowed for payments with source type qrph.”

Please confirm:
1. How to provision/enable our test-mode wallet and add simulated funds.
2. How to simulate successful full/partial QRPh refunds and claim/expiry/failure
   outcomes without real bank details, deposits or transfers.
3. The authoritative retrieval endpoint and webhook event names/contracts for
   the QRPh-specific refund service, including failed records.
4. Why this test request failed with422 and no explanatory field.

We have not enabled live refunding or retried either command automatically.
