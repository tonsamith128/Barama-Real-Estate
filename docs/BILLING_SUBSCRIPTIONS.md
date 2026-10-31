# Barama — Subscription entitlements, billing ledger and Stripe test-mode integration

**Status: development/sandbox only. Live payment collection is not supported by this release.**

This work builds on the commercial-plan catalog. It intentionally does not turn on paid checkout for installed customers. Keep an explicit merchant/payment-provider eligibility and legal review before enabling even sandbox payment flows on a public staging domain.

## Environment and safety

- Checkout/webhook/refund routes are disabled by default: `BILLING_CHECKOUT_ENABLED=false`. Stripe recurring checkout is independently disabled by default: `BILLING_RECURRING_TEST_ENABLED=false`.
- Sandbox credentials are configured with `BILLING_STRIPE_TEST_SECRET_KEY=sk_test_...` and `BILLING_STRIPE_TEST_WEBHOOK_SECRET=whsec_...`. No secrets are committed to Git.
- **Live keys are actively rejected**. Never store PAN/CVV or expose gateway secret keys in views, logs or responses.
- Customer-domain checkout requires HTTPS, configured webhook endpoint and tested Stripe test keys.
- The adapter boundary is `App\Contracts\Billing\PaymentGateway`. Only the Stripe test implementation exists in this milestone; PayPal/Bakong/other processors are not enabled.
- Payment checkout uses Stripe-hosted checkout; card information never reaches Barama.
- Refund APIs also use sandbox credentials; signed `refund.created` or `refund.updated` events finalize refunds.
- Provider API timeouts are **uncertain**, not definitive declines. Pending orders and reserved refund amounts require reconciliation; no blind retry.

## Evidence and entitlements

- Company-owner-only checkout creates an immutable order snapshot and open invoice before redirecting to the gateway.
- Stripe webhook is received at `POST /webhooks/stripe` and verifies **raw request body**, `Stripe-Signature` HMAC-SHA256, a five-minute timestamp tolerance and test mode.
- `checkout.session.completed` / `checkout.session.async_payment_succeeded` only settle with `payment_status=paid`, matching local checkout ID, order reference, integer amount, USD and PaymentIntent.
- Browser redirects and plan inquiries never activate service.
- The webhook event ID is unique in the receipt table, processed under transaction/row lock, so retry/duplicate events are idempotent.
- Verified payment records grant a company-specific entitlement. Monthly/yearly plans have bounded expiration; one-time plans have no scheduled expiry. When both sandbox flags are explicitly enabled, monthly/yearly checkout uses provider-managed **test-mode Stripe subscriptions**. Checkout completion only binds a provider subscription reference; a separate matching `invoice.paid` callback with collected PaymentIntent and a valid service period is required to activate the entitlement. Renewal invoices create separate local order/invoice/payment records, and duplicate invoice IDs are idempotent.
- A newer purchased plan is not overwritten by a late webhook from an earlier order.
- Company listing allowance is checked only for current verified entitlements. Unsubscribed legacy companies keep their existing draft-creation behavior pending a separate approved policy; we do not retroactively lock customer data.
- Admin-only refund requests reserve the amount, then await a signed refund webhook. Partial refunds update the invoice/payment, and verified full refunds revoke the corresponding current subscription.
- Signed subscription payment failures and cancellations suspend/revoke access; a subscription `updated=active` event alone cannot activate it. Signed disputes suspend a matched entitlement, a won dispute may restore it, and a lost dispute revokes it. Unmatched charge-only disputes are queued for operator reconciliation.
- No human-facing form or controller can manually set an invoice to paid.

## Database and audit trail

- `billing_orders`: customer, plan, price/limit snapshots and provider references.
- `billing_invoices`: sequential local references; open → paid → partially_refunded/refunded.
- `billing_payments`: gateway PaymentIntent references, captured amounts and refund totals.
- `company_subscriptions`: latest verified entitlement snapshot, expiration and revocation state.
- `billing_refunds`: admin requester, reason, reserved amount and provider verification status.
- `billing_webhook_events`: signature-verified event IDs, processing status and replay protection.
- `billing_renewals`: unique gateway invoice binding for every verified paid cycle.
- `billing_disputes`: provider dispute status and unmatched evidence queue.
- `billing_tax_reviews`: explicit unreviewed tax documents; no statutory tax invoice is generated.
- `audit_events`: human or machine actor, event, nonsecret references.

**Known boundaries (not CodeCanyon ready):** real-provider subscription auto-renewal acceptance (code and mocks alone are insufficient), tax calculation and tax-inclusive pricing, localized statutory tax/legal invoices, reconciled chargebacks and payouts, automated settlement recovery, cancellation/payment method UX, correct live merchant eligibility, cross-provider webhooks, multi-currency, and live payment handling. Customer-visible payment confirmations are explicitly **not tax invoices**. Read-only reconciliation is available through `php artisan billing:audit --json` and the administrator Finance dashboard.

See [Commercial Release Acceptance](COMMERCIAL_RELEASE_ACCEPTANCE.md) for independent Stripe test clocks, dispute correlation, tax review and aaPanel/cPanel checklists. Stripe's published onboarding region list does not include Cambodia for standard merchant accounts as checked 2026-10-31. Never bypass merchant jurisdiction controls. No production payment credentials should be entered or used.

## Global billing customer extension

- Company owners can record country/territory, address, billing name/email, preferred language, display currency and timezone. None of this implies local payment method availability or tax eligibility.
- USD remains the **only charged and settled currency**. Currency preferences never perform foreign exchange.
- Invoices include a customer billing-name/country snapshot. A changed profile cannot change historical invoice identity.
- Existing recurring subscribers cannot start overlapping plan checkouts.
- A separately gated test-only cancellation endpoint requests `cancel_at_period_end=true`, checks provider subscription/customer identities, and keeps already-paid entitlements through the verified expiry. An optional test portal for payment-method updates uses an approved configuration and strict provider redirect host validation.
- See `docs/GLOBAL_BILLING_CUSTOMERS.md` for validation, setup and remaining global-market release gates.

## Acceptance before release

1. Run forward migrations (never `migrate:fresh` on customer data).
2. Confirm checkout disabled produces 503 and no ledger mutation; owner-only routes return 403 for other tenants.
3. With Stripe test-mode keys, create a hosted checkout and verify a matching signed paid webhook is the only activation path.
4. Confirm wrong signatures, expired timestamps, wrong amount/currency/order, live-mode events and duplicate IDs fail closed.
5. Validate partial/full refund callbacks, expiration, tenant quota, and reversal on full refund.
6. Confirm server clocks, delivery retries and idempotency under simultaneous payment events on MySQL 8.
7. Execute fresh-install and upgrade rollback on separate aaPanel/cPanel staging copies, then independent financial/tax/legal review.

**GitHub changes are source-only**, not website deployment. No live merchant capability is enabled.
