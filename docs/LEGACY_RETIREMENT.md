# Barama v1 Pure PHP retirement — required Laravel v2 cutover policy

## Non-negotiable end state

After full feature and data migration, the **active Laravel source tree**, default branch, deployable build, installation package, and production runtime contain **no Barama v1 Pure PHP application code**.

- Do not ship or retain the old `application/`, standalone `installer/`, standalone `updater/`, root `index.php`, or legacy root `assets/` directories.
- Do not mix `Barama\\Core` custom framework classes with Laravel routing, HTTP, sessions, authentication, or database infrastructure.
- The final customer distribution must be **Laravel-only**. Compiled assets are allowed under `public/build/`, not copied old CSS patch bundles.
- Maintain exactly one frontend CSS source file and one admin CSS source file after the planned consolidation; each contains grouped, clearly labeled sections. JavaScript may have two area entry points with feature modules.
- No legacy function remains as an unused shim, shadow controller, duplicate route, fallback, hidden code copy, or deprecated "just in case" directory.
- Avoid retaining any version-suffixed feature patches in the final source.

## Preserve until migration is proved

The existing v1 Main and live sites must remain undisturbed while functionality is migrated and regression tested in the separate v2 development branch. Do not delete v1 or deploy v2 merely because this policy exists.

A **private, immutable backup of v1 source and related non-secret migration artifacts**, plus securely held database backups, must be available for disaster recovery. Archived evidence is **not part of the active application or customer delivery**. Git history may retain historical commits; cleaning the active branch does not rewrite repository history.

## Required sign-off before legacy retirement

- [ ] All legacy routes, workflows, user roles, tenant rules, database tables and assets inventoried and mapped.
- [ ] Frontend, Auth, Account, Developer, Admin, Appearance, Finance, Media Library, Policy Center and System features migrated with parity tests.
- [ ] Installer, License Manager, Demo Importer and signed Auto Updater rebuilt for Laravel and tested on representative shared hosting.
- [ ] Database conversion tested with production-shaped sanitized fixtures and real rollback rehearsal.
- [ ] URLs, authentication, permissions, sessions, media, notifications, payment integrations and localization independently verified.
- [ ] Production-like deployment, performance, accessibility, mobile/browser QA and clean-package installation passed.
- [ ] Recovery backups verified, rollback documented, migration and cutover explicitly approved.
- [ ] All v1 application paths deleted from the final Laravel tree and generated customer ZIP; no legacy fallback remains.
- [ ] `php scripts/verify-no-legacy.php` passes on the source **and on the extracted exact release archive**.
- [ ] Default branch and release artifacts are exclusively Laravel after accepted cutover; previous v1 branches are archived or retired under a separate repository housekeeping decision.

## Cutover order

1. Finish and prove Laravel feature and data parity.
2. Take and verify a recoverable backup; freeze v1 writes during the final data cutover window.
3. Execute the rehearsed data migration in staging, then with explicit approval in production.
4. Verify the Laravel release independently, with a documented rollback threshold.
5. Remove all old PHP framework files, legacy assets, duplicate views, temporary compatibility adapters and unused patch artifacts from active source and package.
6. Run the legacy-free check and package checks, verify production, and only then change the default branch/release.
7. Keep backups separate from the live codebase until the recovery retention window expires.

**Current status:** Not eligible for legacy retirement. Laravel v2 is a foundation; business modules and production migration are incomplete.
