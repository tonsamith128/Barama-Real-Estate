# Financial, security and communication migration — release disposition matrix

**Source:** pinned Pure PHP source archive, including original migrations
`0013`–`0017`, `0023`–`0027`, plus account/notification migrations.

**Current status:** READ-ONLY DATA/RESTORE VERIFICATION BUILT.
**Full operational finance and security adapters:** NOT YET ACCEPTED.

There is no safe rule that historical payment-provider records, login
credentials, MFA secrets, expired tokens or email outbox rows can simply
be copied into a fresh Laravel 13 installation and reactivated.

## Billing and financial records

| Legacy tables | Release disposition | Required operational adapter acceptance |
|---|---|---|
| `subscription_plans`, `company_subscriptions` | Private, read-only reconciliation | Map companies, plans and subscription periods; independently approve entitlement effects |
| `billing_invoices`, `billing_invoice_items` | Immutable financial evidence pending review | Verify UUID/invoice-number uniqueness, exact decimal totals, currency, dates, company and line items |
| `billing_transactions` | Immutable ledger pending review | Map legacy invoice/subscription relationships and payment/reversal status; never manufacture new charges |
| `billing_coupons`, `billing_coupon_redemptions` | Review-only | Preserve issuance/redemption history; do not automatically activate old discounts |
| `billing_tax_rules` | Review-only | Jurisdiction, date, rate and legal correctness independently approved |
| `billing_payment_providers`, `billing_webhook_events` | Sensitive offline archive only | Rotate credentials; never replay old webhook payloads; re-onboard providers in sandbox |
| `billing_payment_methods`, `invoice_templates` | Sensitive/private archival copy | No automatic payment method activation; inspect data minimization and historical document rendering |

For every financial table, verify complete source row counts and order-stable
row fingerprints across **two independent original-database restores**.
The new `support/barama_sensitive_restore_compare.php` performs that check
without printing or migrating rows. A passing count alone is insufficient.
Financial adapters require row-level mapping, sum reconciliation and legal/
accounting sign-off in a separate tested draft.

## Account and security records

| Legacy tables | Release disposition | Required operational adapter acceptance |
|---|---|---|
| `users`, `user_profiles` | Private account data mapped under original UUID | New unusable passwords, re-verification, ownership and profile-consent review |
| `roles`, `permissions`, `user_roles`, `role_permissions` | Preserve old grants as evidence only | Independent role translation/approval; default-deny on all imported accounts |
| `company_members`, `company_invitations` | Mapped memberships inactive; invitations quarantined | Owner/manager manual approval and acceptance flow |
| `user_security_factors` | Do **not** transfer active secrets | MFA and change-passcode re-enrollment; old secrets remain in encrypted historical vault only |
| `password_reset_tokens`, `email_change_tokens`, `email_verification_tokens` | Do **not** activate | Expire/revoke old links; use Laravel-native tokens only |
| `login_attempts`, `security_audit_events` | Preserve private historical evidence | Retention, timestamps, old UUID mapping and admin-only access |
| `notification_preferences`, `notifications`, `email_outbox`, `email_outbox_events` | Archive without delivery | No resends, queue jobs or campaign activation |
| `saved_search_matches`, `newsletter_subscribers` | Consent review required | Old notification preferences do not constitute re-enrollment |
| `communication_email_templates` and variants/revisions | Internal review | Native-language policy, data security and editor hardening approval |

## What the release checker verifies now

- **Actual code foundation:** read-only SHA/HMAC-style comparison of two
  isolated historic MySQL restores, table schema and every row in each
  covered financial, security and communication table.
- **Synthetic CI validation:** two matching copies pass; a modified copy
  fails. A production-mode invocation is rejected.
- **Source/target importer:** every original source table not yet adapted is
  counted and reported; staging is never a production green light.
- **Private accounts:** old roles are not activated; old password hashes and
  MFA secrets are not introduced as working Laravel credentials.
- **Confidentiality:** hashes and row contents remain inside the verifier,
  which prints only table names, aggregate counts and PASS/BLOCKED.

## Release blockers and ownership

A real private clone comparison must be independently executed before
operational finance/security adapters can be approved. Every nonempty
source table needs either a verified destination mapping or a documented
encrypted-retention exception approved by the product owner and legal/
privacy reviewer. Missing full transaction semantics, financial audit
trail, security-factor re-enrollment, backup restoration or actual hosting
acceptance remains an explicit **NO-GO**.

This document does **not** claim that the original customer database has
been restored, that financial ledgers have been migrated, that MFA is
re-enrolled, or that production is ready.

## Second financial/security semantic review (draft)

The independent offline tool `support/barama_legacy_finance_security_audit.php --audit` runs only after the existing two-restore comparison. It checks invoice, line, discount, tax and total arithmetic; cross-company transaction and subscription references; orphaned role/permission assignments; historical active company memberships; and MFA/passcode and reset-token review counts. It compares monetary aggregates **in memory without printing amounts**. Every historical grant, credential and provider record remains review-only. The audit always reports `release=BLOCKED` even after a clean read-only check. See `docs/FINANCE_SECURITY_PRIVATE_REVIEW_ACCEPTANCE.md`.
