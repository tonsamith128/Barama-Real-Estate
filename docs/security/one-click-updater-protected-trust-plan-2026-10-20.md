# Barama One-click Updater: protected publisher trust and installation gates

Status: DESIGN / NOT DEPLOYABLE. Scope: staging only. Date: 2026-10-20.

## Goal
One manual administrator action to install an authenticated, independently verified release, with an explicit confirmation screen and recovery safeguards. **No unattended auto-install** until all gates have passed in staging.

## Grounded current-state findings
- `TrustedReleaseFeed` reads `application/config/update-feed-v2.json` and `update-publisher-v2-keys.json`.
- `PublisherPrivateDeliveryV1` checks those same paths during readiness and reads feed channel from `application/config`.
- `SignedReleaseInspector` checks the independently pinned key catalog and a staged archive.
- `PublisherTrustBootstrapV1` currently writes its two trust JSON files beneath `application/config`.
- Staging root, `application`, and `application/config` are owned by `www:www` and mode 0755; the `www` user can replace their children. Existing v1.0.0 bootstrap must stay blocked.
- Site `.user.ini` currently sets `open_basedir` to `/www/wwwroot/baramastaging.tonsamith.com/:/tmp/`; CLI php.ini is not equivalent to PHP-FPM effective settings.
- SHA-256-checked publisher catalog candidate on server: `d9cb86f4dbc0ac3754ff0ed35d385078730553c30eaa90418543f61cc056f50f`. Never incorporate private signing material.

## Security architecture decision to evaluate
Proposed staging trust path: `/etc/barama/staging/publisher-trust/` under root-owned, non-web-writable ancestors, with two root-owned, PHP-readable public JSON files. **Do not create it before runtime compatibility checks.**

A single fixed-path resolver shared by all four consumers must:
1. Reject symlinks in every trust-path component, unexpected ownership or write permissions, mismatched hashes, malformed or unknown key catalogs, and file size drift.
2. Never use a web request, editable application config, or environment override to choose the trust root.
3. Preserve existing signatures, published manifest contract, eligibility/domain/installation binding, active-versus-retired key policy and anti-rollback checks.
4. Resolve fixture test trust paths separately under explicit test-only gating, not by weakening the production resolver.
5. Add regression tests proving all four consumers use the same path and fail closed on missing/altered public trust.

## PHP-FPM boundary
Record *effective* PHP-FPM `open_basedir` from an authenticated temporary, non-public runtime probe or configuration inspection; no persistent public phpinfo endpoint. If broadening required, append only the precise approved trust path, keeping website-root and /tmp restrictions. Reverify and remove any temporary probes. Do not change production FPM settings.

## Runtime code-integrity boundary
Currently PHP runtime can edit staging application code because directories are web-user-owned. This defeats trust-store isolation if an attacker changes the verifier. Plan root-controlled, immutable-at-runtime code deployment with narrowly separated writable runtime/storage directories, reviewed aaPanel ownership workflows, and tested rollback before allowing any install capability.

## Manual one-click flow and gates
1. Admin check -> publisher authenticated discovery; bound active license, canonical domain, installation ID, product slug and channel.
2. Independently pinned Ed25519 signature verification; confirm key ID status policy, publisher/product/version compatibility, manifest expiration and replay constraints.
3. Authenticated download to root-controlled staging; exact archive bytes/SHA256; reject symlinks, unsafe archive paths, unexpected files, duplicate names, ZIP bombs; verify release version and package content.
4. Acquire exclusive installation lock; confirm installed version, source hashes, disk capacity, maintenance readiness and no running update.
5. Generate application and **transactionally consistent database** backups in root-controlled storage. Verify restoration/recovery using a disposable staging clone, not only the presence of a backup file.
6. Present explicit approval; privileged update worker rechecks package and backup evidence directly, not supplied browser flags.
7. Stage on same filesystem, preserve customer uploads/config/secrets; guarded file swap and version/migrations with bounded failure states. Never delete customer data.
8. Post-install verification: PHP syntax, application health, DB migration status, licence binding, version, signed release identity, logs.
9. On failure, halt traffic and perform tested compatible code/database rollback; do not silently rollback irreversible migrations.
10. Write tamper-evident audit of release, publisher key ID, hashes, approval, backup IDs, outcome and recovery operation.

## Test matrix
- Good signed release; altered signature/catalog/manifest/archive; key status transition; revoked/unknown key; expired or mismatched product/domain/version.
- Symlink, ACL, web-writable ancestor, ownership drift, incorrect PHP-FPM open_basedir.
- Interrupted two-file provisioning, replay/reinstall denial, exact-hash rollback.
- Missing/corrupt backup, low disk, failed restore drill, concurrent install, interrupted transfer/swap, failed PHP syntax/migration/health.
- Admin CSRF/authz checks, server-side approval binding and download URL restrictions/SSRF.
- CLI and PHP-FPM parity. Staging-only fixture tests; CI green before deployment.

## Hard stop / acceptance
- No new site trust files, code writes, database writes, updater activation, or production change from this document.
- The original first-time trust bootstrap remains blocked.
- Require independent code review and fixture security tests before releasing a revised staging-only installer.
- Do not enable the Install Update control until key-trust isolation, immutable-at-runtime verifier, confirmed backups and restoration, and positive genuine signed-release test all pass.
