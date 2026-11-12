# Barama historical finance/security review — isolated draft

Status: DRAFT, synthetic CI only. No original customer database has been restored, and no legacy credential, notification, permission or payment mechanism is activated.

## Two independent read-only audits

The source schema is the pinned historical Pure PHP archive at a0af1e5d4d29f38441e3357ae6001b263f1c79cd, migrations 0001, 0013–0017, 0023–0024. The original two-restore comparator verifies complete rows and schema. The NEW semantic audit checks invoice item arithmetic, subtotal/discount/tax/total relationships, transaction currency/company consistency, subscriptions, broken role and permission links, and MFA/passcode/token review counts. The semantic tool privately compares monetary aggregates by currency/status across both restored databases but does not print any financial values.

Both tools are READ ONLY and cannot approve cutover, issue bills, migrate personal data, grant historical permissions, reactivate passwords/MFA or send notifications.

## Private operator workflow

1. Obtain explicit original-data access approval, immutable backup hashes and distinct operator/reviewer custody witnesses. Restore the same historical backup into TWO distinct, disposable localhost MySQL schemas. Keep customer data, evidence, SQL, source media and credentials in a private encrypted vault, never GitHub or ChatGPT.
2. Source and restored database users must be SELECT-only. Use local secret storage / a protected 0600 environment file, not shell history or public logs. Set APP_ENV=testing, BARAMA_REHEARSAL_APPROVED=TWO_DISPOSABLE_CLONES_VERIFIED, BARAMA_RESTORED_OFFLINE_VERIFIED=yes and unique BARAMA_SOURCE_* and BARAMA_RESTORED_* connection variables.
3. Run the existing full-table integrity comparator first:

       php support/barama_sensitive_restore_compare.php --compare-restores

   Require exit 0, backup_consistency=TWO_RESTORES_MATCH, distinct restored schemas and independent backup hash verification. Neither a check on a single clone nor aggregate counts is enough.
4. Only after a verified comparison result, explicitly declare the private review:

       export BARAMA_SENSITIVE_RESTORE_VERIFIED=TWO_RESTORES_MATCH
       export BARAMA_RELEASE_REVIEW_APPROVED=PRIVATE_READ_ONLY_ONLY
       php support/barama_legacy_finance_security_audit.php --audit

5. Investigate every nonzero blocking anomaly and every missing required column. Accounting review must independently reconcile line amounts, invoice totals, currencies, partial payments, refunds/reversals and tax treatment. Review queues for invoice records without items, unlinked transactions, MFA/passcode records, legacy privileges and inactive/active subscriptions remain unauthorised.
6. Security owners must manually translate roles and permissions into new *inactive* access review records, require separate owner approval, fresh verification, password reset and MFA re-enrollment. Do not reuse legacy authentication hashes, factors, live payment configs, saved tokens, webhooks or communications.
7. Independent reviewer and operator must be distinct. Record privately: source backup SHA, both restore fingerprints, exact Git commit, checksum of sanitized results, environment, date, tested controls, PASS/FAIL/NOT_TESTED, accountant, security owner and privacy reviewer approval. No checklist is automatically a passing result.

## Synthetic CI and release conditions

The CI fixture uses fabricated records and independently restored MySQL databases. It validates a clean semantic baseline, then deliberate invoice total and MFA-state divergence, plus rejection when run under APP_ENV=production. Run:

    bash scripts/acceptance/sensitive-restore-replica-qa.sh

This script is deliberately gated to an isolated GitHub Actions CI MySQL environment and cannot run against a normal server.

A semantic_integrity result of CHECKED_NO_KNOWN_ANOMALIES is NOT a production release approval; release=BLOCKED is unconditional. Required outstanding gates are human financial/security/privacy sign-off, independent real customer/media clone rehearsal, physical aaPanel/cPanel and device tests, all historical table dispositions, stacked PR reconciliation and separately authorized release window.
