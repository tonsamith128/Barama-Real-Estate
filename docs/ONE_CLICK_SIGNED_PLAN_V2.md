# Barama One-Click Update — Signed Plan (Step 1)

Source commit: branch from main e7f193e3fa4f1d3177c434e4dad1bc250f28726d.

## What is implemented

- \`VerifiedReleaseFilePlanV2::inspect($envelopePath, $archivePath, $installedVersion)\` produces a read-only list of prospective code file changes from a signed Publisher V2 archive.
- Uses the existing \`SignedReleaseInspector::inspectPaths()\` for Ed25519 signature verification, product and version binding, archive SHA-256 and ZIP member security.
- Produces exact candidate filenames and SHA-256 digests, and SHA-256 hashes of installed files for comparison.
- Refuses traversal, malicious paths, symlinks, protected runtime/config/upload locations, case-fold duplicate filenames and database migration files.
- Makes **no site file changes**, database connections or installs. It does not download the release. Its result has \`installation_enabled=false\`.

## Staging and production agreement

- **Staging:** \`baramastaging.tonsamith.com\` is structure-only (no production customer data). Use it first for install rehearsals and destructive tests, only after isolated backup/rollback verification.
- **Production:** \`baramarealestate.tonsamith.com\` retains real user data, properties, media, license binding, configuration and database. This planner is safe to install as inert source but may not be used to perform live upgrades or migrations.
- Never copy production customer records into staging merely to exercise the update plan.

## Requirements before enabling "Update Now"

1. Signed and entitled release acquisition and root-private download staging.
2. Pinned file-change plan, verification immediately before replacement and protected-path checks.
3. Independently verified file and database backups; demonstrate an isolated test restore with no production database access.
4. Update lock, journal, atomic file replacement, migration gate, health probes, and reliable rollback.
5. Only then offer an admin action with CSRF, reauthentication, clear progress and truthful failure statuses.

## Offline test

\`\`\`bash
php -l application/app/Services/Updates/VerifiedReleaseFilePlanV2.php
php tools/verified-release-file-plan-smoke.php
\`\`\`

The smoke test mocks the signature-verifier boundary but checks read-only plan logic and failure behavior. GitHub CI uses the actual ZIP PHP extension when available.

This first stage is explicitly **not a full one-click updater**. Existing customer updater controls remain in their current safe state. No live database or website updates occur by adding this class.
