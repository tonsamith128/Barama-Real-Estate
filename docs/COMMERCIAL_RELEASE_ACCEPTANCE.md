# Barama Commercial Release — Independent Acceptance Checklist

**Status: NOT APPROVED FOR LIVE PAYMENTS.** All work here is for code review and sandbox acceptance; code quality CI is not proof of merchant onboarding or tax compliance.

## Merchant eligibility: external gate

The official Stripe [global support list](https://stripe.com/global) does not include Cambodia for standard merchant account onboarding as checked on 2026-10-31. Stripe's [foreign-jurisdiction account conditions](https://support.stripe.com/questions/requirements-to-open-a-stripe-account-in-another-country) require legal-entity, address, tax and bank evidence for the supported country. Do not misrepresent company domicile or use someone else's merchant account. Select a legitimately accessible processor and obtain **written eligibility confirmation** before any live integration.

No live Stripe API keys, webhook keys or payouts are supported. For another processor (including KHQR/Bakong), implement and test a distinct gateway and webhook signature protocol; never reuse Stripe signatures.

## Subscription and dispute simulation

1. Run forward migrations on an isolated database; existing production installations must be backed up and restored from a tested backup in a separate staging environment. Never migrate:fresh on live data.
2. Set only `BILLING_CHECKOUT_ENABLED=true`, `BILLING_RECURRING_TEST_ENABLED=true`, `BILLING_STRIPE_TEST_SECRET_KEY=sk_test_...`, `BILLING_STRIPE_TEST_WEBHOOK_SECRET=whsec_...` on a **private staging test host**.
3. Confirm owner-only checkout creates a pending order; returning from the hosted checkout does not mark anything paid.
4. Deliver a genuine Stripe test Checkout subscription session and verify that only the matched `invoice.paid` with a collected PaymentIntent activates the entitlement.
5. Use Stripe test clocks (subject to provider support) to simulate renewal and failed payment; ensure renewal yields a new invoice and extends the period once, and failures suspend access. Cancellations do not reactivate.
6. Replay and reorder callbacks: duplicate event IDs, duplicate invoices under different event IDs, mismatched amount, wrong customer, out-of-band/zero-value payment, unknown subscription, old service period, live-mode data, stale signatures and cross-company reference mismatch all fail closed.
7. Exercise refunds (including async reconciliation), open/won/lost disputes and unmatched charge-only disputes.
8. Run `php artisan billing:audit --json`; reconcile all nonzero queues against the **actual provider dashboard**.
9. Confirm company owner-only access to payment confirmations. These are **not tax invoices**.

## International customer billing acceptance

- Company owners can enter billing name/email, country/territory, address, timezone, and language/currency display preferences. The country picker uses ISO 3166-1 alpha-2 labels, not payment gateway country eligibility.
- Verify tenant isolation and exact billing identity snapshot at each invoice: updating the billing profile must not rewrite earlier invoices.
- Test negative validation for unsupported country codes, invalid timezones, non-owner members and unsolicited profile changes.
- Test recurring subscription cancellation via Stripe **test mode** with a company-specific subscription/customer ID, cancellation at period end, and a confirmed provider response; a browser redirect is not proof of cancellation.
- Test Stripe customer portal configuration in test mode with payment-method management allowed and plan changes/proration disabled until the local ledger supports them.
- Verify duplicate subscriptions/overlapping checkouts are refused.
- **Display currency is a preference only**. Prices/settlement remain USD; no exchange-rate conversion or local tax is implied.
- Confirm translated interface and legal documents, tax IDs, consumer disclosures, accessibility and cancellation requirements independently before international selling is approved.
- Reference: `docs/GLOBAL_BILLING_CUSTOMERS.md`.

## Tax and receipt legal gate

Before issuing legal VAT/tax invoices, obtain an appropriately qualified professional's documented assessment of merchant/operator jurisdiction, tax registration/TIN, taxable location, invoice schema, sequence, supply date, customer identity, VAT/withholding, currency conversion, exemptions/zero-rating, refunds/credit notes, record retention and data privacy. Cambodia's [Law on Taxation](https://cambodiantr.gov.kh/en/document/?title=law-on-taxation) is a starting point, not sufficient proof that any particular rate or treatment applies to this service.

Current code records unreviewed tax documents and shows only a non-tax payment confirmation. No arbitrary VAT rate is applied or represented as compliant. **Do not issue the local payment confirmation as a statutory invoice.**

## Shared hosting acceptance

- Fresh install and existing-db forward migration on both **aaPanel** and **cPanel** test domains (not customer production).
- Verify required PHP extensions, cron/queue strategy, HTTPS origin/proxy settings, Stripe webhook raw body signatures, NTP/time drift, outbound TLS connectivity, file permissions, DB locks and transaction isolation.
- Confirm retries and concurrent duplicate events on MySQL 8 (SQLite-only CI is insufficient).
- Confirm signed webhook route CSRF exception is scoped exactly to `/webhooks/stripe`.
- Test checkout under throttle, mail failures, 419 responses, expired session, credential rotation, provider outage and gateway timeout.
- Test failed migration rollback using **database restore**, not arbitrary destructive schema rollback on customer data.
- Produce evidence: sanitized logs, CI run, migration status, test-case results, deployed checksum and verified restore.
- Only after security, merchant, accounting/tax/legal and hosting sign-off may a separate live gateway implementation be scoped. It must remain off by default.

## Current blockers

- Live merchant eligibility and legally supported onboarding
- Actual Stripe test account API / sandbox delivery validation and MySQL concurrency
- Processor-side event-order, invoice object version and disputed charge correlation validation
- Jurisdiction-specific tax invoices and statutory receipt sign-off
- Recurring cancellation/payment method update UX and provider portal
- Automatic reconciliation of ambiguous timeouts, payouts, chargebacks and settlement reports
- cPanel and aaPanel acceptance, official CodeCanyon customer ZIP

**Nothing in this milestone approves processing real money.**
