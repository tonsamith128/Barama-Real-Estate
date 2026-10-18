# Barama Auto Updater — CodeCanyon release gate

**Customer goal:** check for a release, read concise version notes, and approve the update with one click. All license validation, authenticated delivery, archive integrity, backup, migration and recovery steps are behind that action.

## Current implementation

- Admin-only, CSRF-protected `Check for updates` calls the signed publisher V2 discovery route.
- The customer page contains one compact status card, current/available version, one check action, and unobtrusive manual-upgrade/support links.
- The existing signature/pinned-key, license-binding and session freshness checks remain untouched.
- Direct update downloads from CodeCanyon are **not implemented**. For eligible CodeCanyon purchases, Ton Samith License Manager is intended to verify entitlement and serve signed releases. Do not label the feed as direct CodeCanyon download.
- The private artifact HTTP consumer is explicitly **CLI-only**; the existing `/updater/` applies package files already deployed separately. They are **not yet connected** to a safe one-click installation controller.
- Hence `Update now` remains disabled even when signed discovery finds a new version. The UI must not imply files were installed or that automatic rollback exists.

## Must pass before CodeCanyon publication

1. Define and test the author-side release entitlement for actual CodeCanyon purchase codes and the author-direct license path; do not infer update eligibility merely from active license status.
2. Upload and publish a real, production-versioned Barama release in Ton Samith License Manager; sign with the root-private key and verify it against the customer's pinned public key.
3. Complete a tested web-initiated **one-click** workflow that calls a trusted, least-privilege install worker (not a web call to root-only CLI), with single-use action authorization, server-side download, hash/signature checks, private staging, archived rollback copies, database backup, maintenance lock, migration policy enforcement, health check and recovery. Reject unsafe major upgrades with a friendly message.
4. Test the full click-to-completion workflow using an approved staging installation and both valid CodeCanyon and author-test entitlement scenarios. Inject failed downloads, wrong signatures, disk-full, corrupted ZIP, incompatible PHP and failed migrations to confirm no partial production upgrades.
5. Verify customer ZIP packaging includes the simple UI, all required assets, clean-URL routing, installer/upgrader, and no publisher signing keys, backup SQL, local tokens, root-only operations, development fixtures or test credentials.
6. Maintain one customer-facing page: **Check for updates**, then **Update now** and a brief confirmation when installation is genuinely ready. Hide operational details in private publisher/admin logs.

## Boundaries of this PR

**UI preparation only.** The signed release check is functional, but automated install remains disabled. This PR neither creates publisher grants nor uploads/releases any ZIP, triggers a backup, changes the production server, or changes the package-upgrade contract.

Release readiness: **NOT APPROVED** until all above gates pass.
