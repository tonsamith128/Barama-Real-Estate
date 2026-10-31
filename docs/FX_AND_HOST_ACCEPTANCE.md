# Barama — FX and Shared-Host Acceptance

**Development QA only: no live financial eligibility or host approval.**

## aaPanel/cPanel preflight

On each independent DISPOSABLE test installation, after verifying a separate database backup and restored copy, run:

    bash scripts/acceptance/shared-host-readiness.sh --check-only /absolute/test/application https://test.example.com

This is a read-only application and HTTPS check. It never deploys, runs migrations or changes secrets. It checks private .env, public webroot isolation, writable storage/cache, PHP CLI, read-only database/schema report, migration status and homepage response. A result of PASS_LOCAL_PREFLIGHT_ONLY is not evidence of actual Stripe sandbox acceptance, customer hosting upgrade, or completed backup restore. Collect sanitized logs, screenshots, version, artifact hash and independently signed acceptance reports from BOTH panel types.

MySQL CI has an isolated forward-upgrade check; that is not a customer environment.

## Merchant eligibility

As of October 31, 2026, Stripe's standard published supported-country list excludes Cambodia. A legitimate merchant onboarding requires legal entity and banking evidence in a supported jurisdiction. Never misrepresent domicile or enable live credentials because simulated payment tests passed. Sources: https://stripe.com/global and https://support.stripe.com/questions/requirements-to-open-a-stripe-account-in-another-country .

## FX validation (sandbox arithmetic only)

No rate feed or signed real provider quote is connected. FxQuoteContract validates an input contract from a hypothetical trusted quote adapter with an approved identifier, reference, issue/expiration timestamps, USD cents, target currency, integer rational rate and target currency exponent. It uses bounded, integer, half-up arithmetic and rejects stale, invalid or oversized inputs.

The implementation is not wired into checkout, invoices or payment collection. A real merchant/provider integration must independently authenticate an actual quote, bind it to invoice/checkout, enforce expiry and user acceptance, verify target currency support for the merchant and payment method, and reconcile settlement fees and rounding. Never accept client-entered FX rates as a valid gateway charge.

The following configuration is OFF by default and does not enable multi-currency charging:

    BILLING_FX_SANDBOX_PREVIEW_ENABLED=false
    BILLING_FX_PREVIEW_PROVIDER=

Stripe documents zero-decimal currencies such as JPY and special payout currency handling: https://docs.stripe.com/currencies .

## Localization and legal gates

Public informational pricing has allowlisted lang=en/km/zh/fr/es/de/ja/th variants and translated non-payment disclosures. Profile and confirmation views have eight-language strings. This does not translate the whole marketplace, arbitrary listings, auth, policies, notifications or legally significant terms. Native-speaker, legal and accessibility review is mandatory.

Tax review notes are not statutory invoices. Jurisdiction, VAT, registrations, applicable Cambodia e-invoicing rules and foreign consumer/tax obligations still require professional review. Never infer tax from a country selection.

Checkout and recurring billing default disabled. Main and production must not be changed until full acceptance.
