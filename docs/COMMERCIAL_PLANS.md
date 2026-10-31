# Barama — Commercial pricing plan catalog and company inquiries

Status: **development foundation**. This milestone has been extended with a disabled-by-default Stripe test-mode billing foundation. See [Billing and subscriptions](BILLING_SUBSCRIPTIONS.md). No live payments are available.

## Features

- Administrators manage USD pricing options in Dashboard → Finance → Pricing plans.
- Prices are stored in **integer cents**, not floating-point currency; the interface accepts at most two decimal places.
- Plans have stable slugs, descriptions, monthly/yearly/one-time billing descriptions, proposal listing limits, display order and published/hidden status.
- The public `/pricing` page displays **only published** plans, and the safe Homepage Builder navigation can include Pricing.
- Active **company owners only** can open their company plan page and submit a plan inquiry; no unrelated company member may read or alter it.
- One current inquiry per company may be updated on a subsequent selection; administrators can review inquiries in Finance. Audit entries are written for plan changes and requests.
- Existing unentitled legacy companies retain existing listing behavior. Current verified paid entitlements now enforce their listing allowance; no browser checkout or inquiry alone activates this entitlement.
- Live money cannot be charged by the test-only gateway. Test checkout is disabled unless explicitly configured, and administrator forms cannot mark invoices paid.

## Database tables

- `commercial_plans`: public-offering metadata, exact `price_cents`, currency USD, status.
- `commercial_plan_inquiries`: company-scoped contact intent, plan selection and requester. This table is **not** subscriptions or paid orders.

Run standard forward migrations on the staging installation; never use `migrate:fresh` on an existing customer database. Publish plans only after reviewing commercial terms and prices.

## Required next commercial milestones

1. Subscription and entitlement state machines with safe seat/listing quotas and expiration.
2. Gateway abstraction, verified signed/idempotent webhooks, trusted transaction capture and reconciliation.
3. Immutable order/invoice/payment records, tax jurisdiction rules, receipts, refund and chargeback audit.
4. Sandbox payment acceptance, cross-tenant authorization and shared-hosting rollback tests before customer-facing checkout.
5. Administrators must review consumer protection, pricing disclosures and refunds before payment collection.

Keep this code on a development branch pending CI and cPanel migration acceptance. Merging GitHub source does not deploy to any customer website.
