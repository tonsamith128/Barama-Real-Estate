# Barama one-click Auto Updater — Phase 1: signed file-change preflight

## Customer contract

The customer interface remains the simple Auto Updater introduced by PR #81. No extra form, server setting, upgrade wizard, CLI command or administrative table is required from customers.

## Implemented in this branch

`VerifiedReleaseFilePlanV2::inspect($envelopePath, $archivePath, $installedVersion)` produces a **read-only** deterministic list of file targets from an independently verified publisher-signed release ZIP. The established `SignedReleaseInspector` remains the authority for Ed25519 signature, release binding, version compatibility, archive SHA-256 and ZIP security. No new cryptographic format or duplicate publisher API is introduced.

For each approved target the plan records the archive entry SHA-256, the existing installed-file SHA-256 when present, and the byte count. The archive SHA-256 is rechecked before and after scanning. The planner refuses protected application runtime locations, path traversal, duplicate paths (including case collisions), directory symlink targets, malformed entries and any migration file. All code is pure PHP with no additional dependencies.

The planner always returns `installation_enabled=false`. It has no network calls, SQL queries, output filesystem writes or installation endpoint.

## Before a genuine Update Now action can be enabled

- Implement authenticated server-to-server verified package acquisition and private staging using the exact publisher-issued signed manifest; never accept a customer-supplied URL or ZIP.
- Prepare private file and database backups, ensure sufficient disk space and verify restore readiness on a disposable installation.
- Use one shared upgrade lock, allow only verified file baselines, and reverify signatures/checksums just before replacement.
- Implement a controlled code replacement worker, migration policy, health check and crash-safe rollback/recovery. Refuse incompatible major upgrades.
- Test the entire installed customer ZIP in a staging website with bad signature, wrong installation/domain, failed download, unsafe symlink, disk full, failed migration and interrupted replacement cases.
- Only then enable Update Now, after a separate owner-approved release and controlled staging acceptance.

Direct CodeCanyon download is not implemented; CodeCanyon buyers should obtain author-verified updates through Ton Samith License Manager after legitimate purchase-code entitlement is supported.

## QA

`php -l application/app/Services/Updates/VerifiedReleaseFilePlanV2.php`

`php tools/verified-release-file-plan-smoke.php`

The smoke test simulates the signing boundary but exercises the real ZIP extension in GitHub CI (and a hermetic ZIP double where ext-zip is unavailable). Existing signed-manifest verification is deliberately unchanged.

## Deployment boundary

Draft-only. No grant, signed release, database migration, production file change, publishing, or live installation has occurred. The current `Update now` control must remain disabled.
