# Barama System Theme & Auto Updater v1.1.0

Production aaPanel guarded upgrade for **baramarealestate.tonsamith.com** only.

## Scope

- Repair About, System Updates and System Health to inherit canonical `--barama-*` light/dark design tokens. Remove hardcoded dark backgrounds and pale-on-light headings.
- Add **System → Auto Updater** to the existing administrator-only sidebar, using the existing authentication and `dashboard.access` route gate.
- Add the **read-only** Auto Updater dashboard with local version/record checks, product service descriptions for Ton Samith License Manager, Demo Importer, and Auto Updater, readiness gates, and a link to the existing **guarded manual updater**.
- No customer-facing routes, Cloudflare Turnstile, data, license secrets, web-server configuration, or database change.
- No remote update feed or demo-catalog endpoint is guessed. Remote entitlement, manifest checks, signature verification, safe package delivery, migrations, backup restoration and one-click automated update installation are **not implemented or enabled**. Disabled buttons tell operators why.

## Installation

Before use, keep a verified offsite system backup and check enough space in `/root` for a private backup.

```
python3 -B tests.py
python3 -B installer.py --check
python3 -B installer.py --install
```

`--check` only reads live source files, parses staged PHP and validates exact known contracts; it does not execute the application. `--install` backs up all five affected live files with SHA-256 verification and restores code on a failed staged write. Keep the printed `ROLLBACK_BACKUP_ID` for recovery.

To roll back this upgrade, only if its installed files have not since changed:

```
python3 -B installer.py --rollback YOUR_BACKUP_ID
```

The installer refuses unknown modified files, symlinks, duplicate route/sidebar entries, wrong application version, missing PHP 8.5, unverified backups or changed installed files on rollback.

## Next release contract for Ton Samith services

Future integrations must expose documented server-side contracts, authenticated domain/product-bound license entitlements, pinned publisher keys for signed release manifests, tightly scoped allowlisted HTTPS endpoints, non-public package staging, verified file and database backups, rollback rehearsals, and separate Demo Importer preview/isolation. **Do not reuse public demo keys or infer update entitlement from an 'active' locally saved license flag.**

Product version `v0.9.22-dev` is not changed by this design upgrade.

## Verified release artifact

- Filename: `Barama-System-Theme-and-Auto-Updater-v1.1.0.zip`
- ZIP SHA-256: `85c3f81234c19fde3341c690c56cacb11536f06cad539329a72ac184f3675fc3`
- Suite: **26/26 tests passed** from both the working package and a fresh extraction; the ZIP contains 11 checksum-verified source, test and fixture files.
- Package source pins: SystemCenterController.php `9e50e49597c5b8817827bd684842476f6050374086133ae030508b48aa24eacc`; view `b309886aadc8a6a2b9b12de0d83ef677cafebafc04ece4e7ded295f60ba73535`; CSS `0ac22c10a68909b7f9a3035c2f80888b4c93cad972d1ce22fbc6826d1eeae0e5`.
- Fresh extract test/manifest: **PASS**, no application files on aaPanel executed.

**Deployment contract:** pin exact v1.0.3 system controller, view and CSS; inspect canonical v1.0.2 System menu and route guards; stage all five files; private integrity-verified file backup; rollback refuses subsequent file changes. Do not add release downloads, outbound entitlement checks or auto-install before documented publisher APIs and signed recovery acceptance.

**Artifact location:** this version's ChatGPT conversation attachment (not a GitHub Release or customer package). Retain the ZIP separately in secure release storage; SHA above identifies exact bytes.
