# Barama — International Commerce Evidence Register

**Release class: Development QA only. External sign-offs are NOT VERIFIED.**

This register distinguishes source/CI tests from real payment processor, tax adviser, merchant account and shared-hosting evidence. Passing CI never activates billing.

## Read-only on-host commands

    php artisan billing:release-check --json
    php artisan billing:audit --json
    php artisan migrate:status

The release-check command checks local PHP extensions, HTTPS origin configuration, debug flags, public-source exposure, read-only database connectivity and billing schema. It never changes data; it always reports live_ready=false and all external gates not_verified. The --strict option returns exit code 2 intentionally to block a live release. Do not copy secrets or raw customer data into reports.

## Independent release sign-offs

| Gate | Required independent evidence | Status |
|---|---|---|
| Legal merchant eligibility | Provider approval for actual registered legal entity, domicile, tax ID, payout bank | NOT VERIFIED |
| Genuine processor sandbox | Matching Stripe test Checkout, signed invoice, recurring renewal, failed payment, dispute, refund, replay | NOT VERIFIED |
| MySQL 8 CI | Isolated MySQL 8 finance migration and feature test workflow | AUTOMATED, subject to actual CI result; no host acceptance |
| aaPanel | Clean install, forward upgrade, database restore, URLs, HTTPS and filesystem permissions | NOT VERIFIED |
| cPanel | Clean install, forward upgrade, database restore, URLs, HTTPS and filesystem permissions | NOT VERIFIED |
| Regional tax and receipts | Tax/legal adviser sign-off on issuer, registration, VAT, invoice numbering, electronic invoicing, returns, credit notes | NOT VERIFIED |
| Languages | Native-speaker, accessibility and legal review, complete-site content | NOT VERIFIED |
| Foreign exchange | Authorized quote source, timestamp/expiry, fees, integer currency units, rounding and settlement reconciliation | NOT IMPLEMENTED |
| Settlement/chargeback | Provider settlement exports reconciled with ledger and bank payouts | NOT VERIFIED |
| Customer ZIP and security | Audited distributable checksum, deployed copy, shared-hosting permissions, penetration review | NOT VERIFIED |

## Processor test-account evidence

1. Keep live keys forbidden. On isolated, private staging, set only valid Stripe test keys and verified webhook secret.
2. Generate Checkout from the actual Stripe sandbox, not merely a synthetic fixture. Confirm a redirect NEVER activates access.
3. Use a real subscription test clock to generate paid, failed, renewal and canceled cycles; retain sanitized event IDs, invoice IDs, UTC times, local order references and the resulting ledger state. Stripe CLI fixtures alone do not establish correlation with Barama orders.
4. Verify signed refunds, dispute events, out-of-order delivery, replay, idempotency and ambiguous timeouts, then reconcile the provider dashboard against the ledger.
5. Verify the actual webhook object schema and Stripe API version in a controlled environment before publishing a customer gateway.
6. Maintain an evidence table with reviewer and completion date; no password, secret or financial personal data in logs.

Sources: https://stripe.com/global ; https://github.com/stripe/stripe-cli ; https://stripe.com/blog/test-clocks-how-we-made-it-easier-to-test-stripe-billing-integrations ; https://docs.stripe.com/currencies .

## Shared-hosting acceptance

Use TWO disposable domains: one aaPanel and one cPanel. Never run against a customer production database.

1. Build and verify exact archive checksum and compare the checked-out Git commit.
2. Fresh install through the five-step installer; verify homepage, login, developer, administrator screens and assets.
3. For upgrade, create and verify a database + file backup, restore it to isolated test space, execute only forward migrations, and verify previous company/account records remain.
4. Verify the PHP binary/version/extensions, MySQL version and locks, public webroot, private .env permissions, HTTPS/proxy correctness, server clock drift, outbound TLS and writable storage/cache.
5. Deliver genuine signed webhooks through real hosting middleware; record retries and duplicate-event results.
6. Confirm a rollback by RESTORING the verified backup in disposable test space, not by blindly reversing schema migrations.
7. Capture sanitized logs, checksum, test results and independent reviewer sign-off for BOTH environments.

## Legal and localization boundaries

Tax review statuses unreviewed, needs_specialist and reviewed_nonstatutory are work-queue assessments only, not tax approval and not legal invoices. Customer billing country does not establish taxation or merchant eligibility. Professional review must consider Cambodia's applicable Law on Taxation and the e-invoicing rollout, as well as foreign jurisdictions.

USD remains the only charge and ledger currency. Display preferences never create FX quotes or automatic conversion. Eight languages cover only selected billing profile and confirmation UI strings in this milestone; other interfaces and legal text still require independent translation and review.

**No code path in this milestone authorizes live-money transactions.**
