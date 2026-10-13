# Barama Real Estate — Auto Updater & CodeCanyon Release Contract
Date: 2026-10-13
Status: PLANNING / SOURCE-VERIFIED PARTIAL; NO LIVE ACCEPTANCE
Release baseline: private aaPanel overlay 0.9.22-dev (629-file nonstate SHA-256 900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687). GitHub reference: fc617c12790f7075ccfd0e418a1bba3443450941. The two sources are **not equivalent**.

## Objective
A CodeCanyon customer buys Barama, installs its full PHP application, activates through Ton Samith License Manager, and receives complete, verified Barama releases published by the external Ton Samith Update Center.

## Verified from GitHub reference source (NOT necessarily identical to private overlay)
- `updater/index.php` dispatches `UpgradeController`.
- `application/app/Http/Controllers/UpgradeController.php` performs CSRF and administrator-input handling and delegates to `UpgradeService`.
- `application/app/Services/UpgradeService.php` requires administrator authentication, backup confirmation and license validation; it applies pending ordered migrations and advances installed metadata.
- The current upgrade handler expects a **new package's files to have been uploaded already**. It does not itself establish remote release listing, download, signed ZIP verification, atomic file deployment or automatic restore. On failure the controller asks the operator to restore from backup; **automatic rollback is not established**.
- `application/app/Services/LicenseManagerClient.php` implements the Barama-side license activation/validation integration, not proof of a live end-to-end connection.
- `tools/build-package.php` builds an exact customer ZIP with installer/updater/application/assets/documentation and a SHA256 sidecar. `tools/qa.php` and `docs/RELEASE_GATES.md` define structure, migrations and package QA. Build/execution of current private source is NOT yet verified.
- README and `docs/RELEASE_GATES.md` report hosted runner allocation failures and incomplete live acceptance. Do not treat implemented tests as passed until actually executed.

## Canonical architecture (NO second product tree)
1. CodeCanyon is the **sale and purchase-code source**. Envato purchase validation is performed server-side via Ton Samith License Manager.
2. Ton Samith License Manager is the **entitlement authority**, checking product/item, domain, installation ID, status, and customer update eligibility; credentials/tokens and author API keys remain server-side or in protected installed state, not in distributable PHP source.
3. Ton Samith Update Center is the **private release publisher**: immutable version + compatibility manifest, per-file manifest, signed payload and protected download authorization. Signing private keys never ship in Barama.
4. Barama customer app is **the only installer/updater runtime**: one existing installer and one existing upgrade flow. Extend the canonical service/route/UI rather than introducing versioned duplicate copies. Verify signed manifest and package against a pinned public key, package paths, entry count, size, hash, version monotonicity and PHP/database prerequisites.
5. Before mutation, run preflight, secure snapshot of application files and customer database, maintenance/lock, staged extraction and verification. Never overwrite protected runtime state: customer uploads, installed license, site config, logs, sessions, backups or secrets. Apply ordered migrations with explicit pre-tested recoverability, only then set version and release maintenance.
6. On any failed step: restore files and database from the verified snapshot or stop in a clearly recoverable manual state; never label a failed or partial deployment as successful. Preserve a repair/retry path without deleting customer data. On update success, clean temporary stage only after verification.
7. All release interfaces clearly distinguish `NOT_IMPLEMENTED`, `IMPLEMENTED_NOT_TESTED`, `PASS`, `FAIL`, `BLOCKED`. No fake PASS markers or stub UIs.

## CodeCanyon customer package protection invariants
- One canonical root: index.php, installer/, updater/, application/, assets/, documentation/, version/legal/changelog files, with no legacy duplicate source trees.
- No private Ton Samith publisher/MCP code or author Envato API secrets inside the customer download.
- Keep controllers thin, services isolated, ordered migrations; protect existing dashboard/account/developer routes and authentication.
- Package build must include every autoloaded class and referenced resource; missing methods/classes, TODO/FIXME placeholders, fatal PHP syntax, empty stub handlers and unused orphan code are release blockers.
- Test the **exact produced customer ZIP**, not only the repository. Preserve unchanged PHP files/hashes unless intentionally changed; verify source tree and per-file delta and list every added/moved/removed path. Never automatically repin known divergences.
- PHP lint all customer PHP, validate route/controller/class references, asset linkage and migration order, dependency/notice licensing, HTML help, clean fresh install, upgrade, safe retry, corruption rejection and rollback.
- Must work on supported PHP hosting without requiring Node.js or Composer install at customer runtime.
- CodeCanyon documentation and file structure comply with Envato code-item preparation requirements; review by CodeCanyon itself cannot be guaranteed.

## Focused work order, not another Router audit
A. Reconcile only updater/installer/license/package-builder files between private aaPanel source and GitHub baseline, using exact SHA256 and bounded allowed reads; record differences and decide which source is canonical. NO auto-edit on mismatch.
B. Implement missing Ton Samith Update Center protocol and verified release delivery *as additive modules* and through the current upgrade path; use disconnected mocks in QA. Keep signing secrets external.
C. Test exact customer ZIP clean install and updating in a disposable isolated environment. Test valid update and invalid signature/hash, version downgrade, hostile ZIP path, interrupted download/install, license rejection/network outage, migration error, interrupted rollback and replay.
D. Human customer acceptance: buy/activate test code, check update, safely apply, confirm protected files/user data, verify rollback. Runtime execution requires separate isolated rehearsal approval; no production writes by this audit.
E. Final CodeCanyon submission readiness after all above and existing `docs/RC1_CHECKLIST.md` and `docs/RELEASE_GATES.md` gates pass.

## Immediate decision
The **functional gap** verified in GitHub is remote updater delivery and rollback—not demonstrated codebase corruption. The private aaPanel source may have additional functionality; compare it before declaring features missing or shipping replacements. No CodeCanyon submission or production deployment is authorized by this document.
