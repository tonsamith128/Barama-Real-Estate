# Barama isolated operator release acceptance — draft

This milestone provides an OFFLINE evidence gate and no production deployment.

Original customer backups and media stay in a private encrypted operations vault, NEVER in GitHub, ChatGPT or a publicly accessible webroot. Original production Pure PHP website and database remain unchanged. A real two-restore rehearsal must be executed only by an explicitly authorized host administrator, using isolated localhost read-only legacy restores and separate fresh Laravel QA schema.

## Operator sequence

1. Independently verify original encrypted backup hash and provenance, two separately restored databases and a cloned media path. Keep all DB passwords in a local restricted secret file; grant source database users SELECT only, and disable cron, queues, mail, notifications, payments, webhook callbacks.
2. Run the existing safe preflight after checking its pinned SHA. Set the private 0700 evidence directory ending with barama-private-rehearsal-evidence, as defined by docs/REAL_DATA_CLONE_RELEASE_ACCEPTANCE.md. From the reviewed QA checkout invoke:
    bash support/barama_real_clone_readonly_preflight.sh --preflight
3. After independently checking the two restore identities and hashes, run:
    php support/barama_sensitive_restore_compare.php --compare-restores
   Require exit 0 with backup_consistency=TWO_RESTORES_MATCH.
4. Only after this succeeds set BARAMA_SENSITIVE_RESTORE_VERIFIED=TWO_RESTORES_MATCH and BARAMA_RELEASE_REVIEW_APPROVED=PRIVATE_READ_ONLY_ONLY and run:
    php support/barama_legacy_finance_security_audit.php --audit
   Require READ_ONLY, semantic_integrity=CHECKED_NO_KNOWN_ANOMALIES, and release=BLOCKED. This is not accounting or security approval.
5. Optional historical-to-Laravel staging requires a separate explicit approval and freshly restored EMPTY disposable target. Keep the real original data inaccessible to public QA hosts. Complete independent read-only post-stage reconciliation using:
    php support/barama_isolated_clone_reconcile.php
   Never run --stage as part of a basic preflight.
6. Independently perform actual aaPanel and cPanel QA-host probes and real physical iPhone/Android/tablet/calendar import tests with synthetic accounts, using docs/PHYSICAL_HOST_AND_DEVICE_ACCEPTANCE.md. Emulated CI success does not count as real device evidence.

## Independent acceptance manifest

The verifier support/barama_private_release_gate.php checks that the operator-provided acceptance matrix has all required control keys, different operator and reviewer labels, pinned Git SHA and backup digest, and uniquely hashed evidence files. It does not authenticate signatures or prove that evidence is genuine.

Create an operator-owned PRIVATE 0700 directory ending exactly in barama-private-rehearsal-evidence. Place manifest.json and sanitized local evidence files there. Manifest schema barama-private-acceptance-v1 requires:
- scope = isolated_qa_only
- commit = exact lowercase 40-digit Git SHA
- source_backup_sha256 = SHA-256 of a verified original backup
- operator_id and reviewer_id = distinct private labels
- checks = every control key in the same order as BARAMA_REQUIRED_ACCEPTANCE in support/barama_private_release_gate.php
- For each check, status PASS, FAIL, or NOT_TESTED. PASS must contain a unique evidence_file basename and evidence_sha256 matching its local file.

Set APP_ENV=testing, BARAMA_REHEARSAL_APPROVED=TWO_DISPOSABLE_CLONES_VERIFIED, BARAMA_EXPECTED_GIT_SHA to the pinned SHA. Run:

    php support/barama_private_release_gate.php --review --manifest=/private/secure/barama-private-rehearsal-evidence/manifest.json

Only structurally complete evidence yields exit 0, and EVEN THEN the tool reports PRODUCTION_RELEASE=BLOCKED because it does not verify signatures or physically inspect devices. An independent reviewer must validate the real evidence and sign off privately before release decisions.

Synthetic CI tests run:
    bash scripts/acceptance/private-release-gate-qa.sh

This confirms missing, falsified, duplicated or path-traversal evidence fails closed. The fixture uses explicitly synthetic claims; no genuine physical test or financial accounting acceptance is claimed.

## Remains blocked

Real original-database/media rehearsal, independently verified financial/security/privacy approvals, actual aaPanel/cPanel and physical-device tests, approved operational legacy adapters, branch reconciliation and separate authorized production rollout/rollback window.
