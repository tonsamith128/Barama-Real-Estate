# Barama One-Click Updater — Phase 4C: publisher-to-staged-ZIP binding

This phase integrates the pre-existing authenticated, CSRF-protected **Check for updates** POST with the independently signed ZIP inspection. It does **not** enable installing an update.

## What changed
- `TrustedReleaseFeed::check()`, after independently verifying a genuine Publisher V2 Ed25519 envelope and entitlement, supplies the manifest's signed `package_sha256` (non-secret) with the available version.
- The existing `SystemCenterController::checkUpdateEntitlement()` POST calls `StagingPublisherCandidateV1::inspect()` immediately on the returned publisher result.
- The bridge operates only for `/www/wwwroot/baramastaging.tonsamith.com`; production returns `STAGING_ONLY` without inspecting a release or connecting to a database.
- If a genuine publisher release has been received and the ZIP has been staged by the existing authenticated publisher delivery process, the code compares its separately verified SHA-256 and version with the publisher's signed discovery result. The user sees **a verification-only feedback message**. Missing, expired, tampered, nonmatching or unpublished results are refused.
- On failed publisher requests the controller clears the prior candidate status. No activation token, purchase code, API secret, endpoint response, source path, SQL or customer record is logged to the UI.

## Safety gates still required
The bridge always sets `ready_to_install=false` and `auto_install_enabled=false`. The real MySQL restore, code/database rollback, failure-safe migration policy, update lock, health checks, owner approval and an end-to-end staging trial are still mandatory.

## CI
`php tools/staging-publisher-candidate-smoke.php` covers matching release SHA/version, mismatched ZIP, denied entitlement, rejected/invalid ZIP, production refusal and disabled installation. The independent signed ZIP and real SQL disposable CI tests remain part of the same workflow.

## Safe aaPanel deployment
The v1.9.0 ZIP will update exactly these three **PHP source files** on staging first and production second, with checksums, original-file snapshots and atomic file replacement:

- `application/app/Services/Updates/StagingPublisherCandidateV1.php` (new)
- `application/app/Services/Updates/TrustedReleaseFeed.php` (signed SHA field)
- `application/app/Http/Controllers/SystemCenterController.php` (existing admin POST integration)

This is a source-only, safe update to the already authenticated administrator workflow. The application version stays `0.9.22-dev`. No schema migration or actual package installation is performed.

There is still **no confirmed genuine publisher-issued update ZIP** staged on the current server, based on the v1.8.0 diagnostic output. Do not fabricate one.
