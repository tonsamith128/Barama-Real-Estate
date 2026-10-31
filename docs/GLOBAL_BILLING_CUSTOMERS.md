# Barama — Global Customer Billing Foundation

**Development-only; no live payment approval.**

## Included capabilities

- Company-owner-only, company-scoped billing profile containing billing name/email, two-character ISO 3166-1 billing country or territory, address, postal code, preferred language, preferred display currency, and timezone.
- Country/territory validation uses a fixed ISO 3166-1 alpha-2 list. A valid country code **does not establish a provider's ability to sell or pay out there**, compliance with sanctions, tax rules, payment-method availability, or merchant eligibility.
- Language preferences: English, Khmer, Chinese, French, Spanish, German, Japanese and Thai. **These are preferences for future localization; the billing interface is currently English.**
- Display-currency preferences: USD, EUR, GBP, KHR, AUD, CAD, JPY, SGD, THB, CNY and INR. **No FX rates or conversion are calculated**. Quoted, charged and accounted amounts remain USD minor units.
- Once a customer starts a checkout, the invoice records a billing-name and country snapshot that later profile edits do not rewrite.
- Stripe test checkout asks Stripe to collect billing address. Payment method details are not handled on Barama servers.
- A verified, provider-managed subscription can be scheduled for cancellation at period end by its company owner. The end-of-period request uses test-only credentials, a deterministic idempotency key and validates the returned subscription/customer. Already-paid access remains until its normal expiration, subject to other risk flags.
- An optional Stripe **test** billing portal session for payment-method management can be enabled with a pre-reviewed customer portal configuration. Configure its customer-facing actions appropriately; this application does not trust portal redirects as payment evidence.
- Existing recurring subscribers cannot open a second active recurring checkout for the same company. Plan upgrades are deliberately not supported pending proration and tax work.
- Signed Stripe subscription-update callbacks can record a scheduled cancellation, but cannot turn a revoked entitlement back into an active entitlement. Verification of actual funds remains mandatory.

## Configuration

All default **false** and live Stripe keys are rejected:

    BILLING_CHECKOUT_ENABLED=false
    BILLING_RECURRING_TEST_ENABLED=false
    BILLING_SUBSCRIPTION_MANAGEMENT_TEST_ENABLED=false
    BILLING_STRIPE_PORTAL_TEST_ENABLED=false
    BILLING_STRIPE_PORTAL_TEST_CONFIGURATION=
    BILLING_STRIPE_TEST_SECRET_KEY=
    BILLING_STRIPE_TEST_WEBHOOK_SECRET=

A test-mode billing portal configuration requires a legitimate bpc_ configuration identifier. Avoid allowing price, plan, or tax changes in the portal until supported by local ledger reconciliation and legal review. The profile does not include consumer tax IDs; tax evidence and statutory invoice generation require independent jurisdiction-specific review.

## Release boundaries

- No tax calculations, VAT determinations, legally compliant receipts, tax registration, currency exchange or multi-currency settlement.
- No full localization of billing UI, customer messaging, accessibility sign-off, tax ID handling or translated legal documents.
- Actual Stripe sandbox merchant configuration, real webhook delivery, API version compatibility, chargeback, refund, payment-method and customer portal acceptance are outstanding.
- Live payment and payout provider support must be confirmed for the operator's actual registered country. Do not use customer billing country as a proxy for merchant eligibility.
- Fresh installation, existing database upgrade and rollback still require separate aaPanel/cPanel testing.
- Never deploy this branch to live customers or activate live payment collection before independent acceptance.

Documentation: COMMERCIAL_RELEASE_ACCEPTANCE.md, BILLING_SUBSCRIPTIONS.md.
