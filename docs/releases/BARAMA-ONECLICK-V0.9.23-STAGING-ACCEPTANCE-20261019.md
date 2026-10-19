# Barama One-click Updater — v0.9.23 staging acceptance (2026-10-19)

## Outcome: guarded native **staging** installation accepted

The owner-operated, signed two-file native upgrade was tested on
`baramastaging.tonsamith.com` using the private aaPanel package
`Barama-OneClick-Native-Integration-v1.0.0`. The package is held outside the
GitHub application source and is not a customer-facing or unattended updater.

- Prior native upgrade rejection traced to use of an unverified administrator
  identity. The verified staging administrator's authentication passed an
  independent read-only account/password check. Never record or commit the
  administrator password.
- Local license installation ID, staging host/domain, product identity, recorded
  status, and activation-token presence were verified independently.
- The existing `LicenseManagerClient::validateActive()` remote check returned
  `REMOTE_LICENSE_VALIDATION=PASS`. No token was printed.
- The existing `UpgradeService::upgrade()` completed via the privileged,
  separately approved staging command; the transaction reported
  `MODE=STAGING_NATIVE_UPGRADE_INSTALLED`,
  `NATIVE_UPGRADE_COMPLETED=YES` and
  `STAGING_TRANSACTION_STATE=INSTALLED_HEALTHY`.
- Independent post-install verification:
  `STAGING_TWO_FILE_SHA256=POST_MATCH`,
  `STAGING_TARGET_OWNER_MODES=MATCH`, PHP 8.5 FPM active,
  homepage HTTP 200, login HTTP 200.
- Independent read-only three-way version audit:
  `VERSION_FILE=0.9.23`,
  `INSTALL_METADATA_VERSION=0.9.23`,
  `DATABASE_INSTALLATION_VERSION=0.9.23`,
  `APPLIED_MIGRATIONS=30`, `PENDING_MIGRATIONS=0`,
  `VERSION_CONSISTENCY=PASS`,
  `DATABASE_TRANSACTION=READ_ONLY_ROLLED_BACK`.
- Native integration package integrity, PHP/Python syntax and 30 disposable
  contract tests passed in the run reported by the owner.
- Publisher release `barama-2026-0-9-23-receiver-racefix`
  remained `draft` (`PUBLISHED_AT=NULL`). Automatic installation was
  disabled. No production deployment, release publication, or GitHub-triggered
  deployment was authorized.

### Source provenance

The fix for the private V2 receiver's racing archive/receipt cleanup is already
merged to `main` via PR #105 (merge commit
`16bf89a239e15a60e1b892a7474cac071c4b4322`). This acceptance record
does not replace the unsigned application source, private pre-signed artifact,
offline signing ceremony, prepared backups, receipts, or root-private journals.
Neither the private publisher signing key nor credentials belong in GitHub.

### Explicitly **NOT** accepted or enabled

**This is not evidence of an end-to-end customer dashboard One-click Update.**
The tested operation used an owner-approved interactive root CLI and a
pre-staged signed artifact. The application's Auto Updater controller/routes
currently provide license checks, publisher entitlement discovery and signed
release inspection, **not an enabled customer `Update now` install endpoint**.
The publisher release remains draft, and customers cannot download or install
the draft release through the dashboard.

Do **not** merge older stacked/draft partial updater PRs (#81–#83) as a
substitute for this missing integration. Do not enable the `Update now` button,
create a privileged HTTP-to-root execution shortcut, publish a release, or
operate on production merely because the CLI acceptance passed.

### Remaining release gate (same feature, no expansion)

1. Reconcile the **exact** staging-tested worker source and dependencies into
   the reviewed repository/packaging workflow. Keep any privileged work
   outside the web process and retain signed package, lock, backup, native
   version/migration, interruption/recovery and post-health checks.
2. Review an authenticated, CSRF-protected, least-privileged
   dashboard-to-worker handoff; fail closed when entitlement, artifact,
   backups, or native upgrade prerequisites are missing.
3. Stage and test a **newer** independently signed release end to end from
   dashboard to completed install, including failures, admin authorization,
   audit and recovery; verify source/version/database and HTTP post-health.
4. Publish or deploy only after separate owner approval and acceptance.

This documentation-only change intentionally alters **no executable runtime
file**, production instance, staging instance, release status or installation
flag. It records a verified staging milestone, not customer feature completion.
