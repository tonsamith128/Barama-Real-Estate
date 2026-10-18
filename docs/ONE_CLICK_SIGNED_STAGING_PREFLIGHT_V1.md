# One-Click Updater — Phase 4B signed staging inspection

## Practical next enabling step

Use the installed Publisher V2 Ed25519 trust chain on a **real staged release candidate** and verify its archive SHA-256 and read-only file change plan before any application files are modified. The new class `StagingSignedReleasePreflightV1` reuses `SignedReleaseInspector`, `TrustedReleaseFeed` and `VerifiedReleaseFilePlanV2`. It never invents a second signature or publishing authority.

Paths checked (staging ONLY): `application/storage/app/updates/staged/envelope.json` and `application/storage/app/updates/staged/release.zip`. Signed envelope and ZIP must be created by the actual publisher's release workflow; **do not generate a fake or self-signed release on staging**.

CLI diagnostic `signed-preflight` checks for a real signed release, refuses symlinks, signature changes, altered archives, mismatched versions and unsafe targets, and safely reports blockers if a genuine release is missing. A verified signature alone is not commercial entitlement, MySQL recoverability or authorization to replace customer files.

## CI evidence

The test creates a **disposable Ed25519 keypair only in a temporary test folder**, signs a dummy ZIP, verifies the existing real signature verifier, rejects a modified signature, rejected ZIP digest and symlink, and proves staging version and test customer data stay unchanged. No real publisher signing key is created or exported.

## Two-site safety agreement

- Staging: `/www/wwwroot/baramastaging.tonsamith.com` — structure only, without production customer data.
- Production: `/www/wwwroot/baramarealestate.tonsamith.com` — customer accounts, property records, uploads, storage and database MUST stay intact.

The aaPanel installer adds only the inert PHP class on staging and production. The `signed-preflight` diagnostic runs on staging only and makes **no database connections, no site writes and no downloads**. Application version remains `0.9.22-dev`.

## Mandatory remaining blockers

1. A genuine publisher-issued signed update envelope and signed ZIP is not currently staged in the existing v1.7.0 preflight.
2. Authenticated publisher entitlement must be rechecked when the update is initiated; a signed public package alone does not prove customer entitlement.
3. Complete SQL and code snapshot + *real* recovery against a disposable full installation, never production data.
4. Durable lock, crash-safe journal, migration policy, and verified rollback of both code and database.
5. Actual staging-only update and end-to-end health checks before enabling the administrator's **Update Now** button. Production updates require a separate owner approval gate.

**This phase does NOT enable Update Now**, download artifacts or perform a real upgrade. The current deliberately fail-closed status is correct.
