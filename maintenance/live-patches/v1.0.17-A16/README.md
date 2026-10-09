# Barama v1.0.17-A16 — Unified Admin Settings navigation

## Production request and root cause

On ordinary `/dashboard/*` pages, `DashboardController::navigation()` exposed only a generic Settings shortcut. On Settings pages, `WorkspaceSettingsController::navigation()` replaced that shortcut with eight sub-section links, while `workspace/settings.php` rendered a redundant horizontal settings tab bar and a decorative top-right “Admin Dashboard” label.

This led to inconsistent navigation and duplicate ways to reach each Settings page.

## Changes — three PHP files

1. `application/app/Http/Controllers/DashboardController.php`: put the eight real Settings links into the canonical Sidebar Navigation everywhere:
   - Basic — `/dashboard/settings/basic`
   - Profile — `/dashboard/settings/profile`
   - Privacy — `/dashboard/settings/privacy`
   - Security — `/dashboard/settings/security`
   - Storage — `/dashboard/settings/storage`
   - Locations — `/dashboard/settings/locations`
   - Cloud Storage — `/dashboard/settings/cloud-storage`
   - Mail Setup — `/dashboard/settings/mail`

2. `application/app/Http/Controllers/WorkspaceSettingsController.php`: reuse `DashboardController::settingsNavigation()` instead of maintaining a duplicate navigation list.

3. `application/resources/views/workspace/settings.php`: remove the duplicate horizontal tabs and the decorative Admin Dashboard badge **only on Admin Settings pages**. Preserve Customer/Developer tabs and badges.

No form, route, repository, security, database, Mapbox, mail setup, payment, Appearance, upload, password, session or A15 icon-rail behavior is modified.

## Source fingerprints

| File | Old (accepted production) | New |
| --- | --- | --- |
| `DashboardController.php` | `9e3886f0b0f9e01da76fd2c3ba9864d3e1f5bd4c132f2cb6c00949b9997b53b6` | `e834414dde683ce09f5555fecf68645e7bf3dd10d3091131b77e2ad7d65f5601` |
| `WorkspaceSettingsController.php` | `1bc461a94d27f6223746b34b84d89e47a9cdf1288fd98a9ee7fcb1a7785fd1b0` | `93e488f1bce6471827ae4ec499eb6687442fbf3c76c34c84a6bc3a406572635f` |
| `workspace/settings.php` | `64da95322647e053a0939e26d636814d8431f001da6856e04058bc6c8181bb4e` | `f608a665df6da96636cb162cdeef4e77ca70e546955bd574d9e7e096e82359d3` |

## Guarded aaPanel release

File: `Barama-Admin-Settings-Cleanup-v1.0.17A16-Complete-Kit.zip`

SHA-256: `1351cd9d36b2bef9eb873b416239e8c1db90c1855d9f61a56c8f98afe785cffb`

Actual updated source files are included in this separately distributed ZIP. The installer requires exact accepted live source fingerprints and supports `--check`, `--deploy`, `--rollback --backup ...`. A16 does not need Node.js and makes no DB writes.

## QA performed

- PHP 8.4 lint of all 3 updated files: PASS.
- Programmatic navigation equivalence (Dashboard and Settings identical, eight links once, no duplicate paths): PASS.
- Admin tabs gated off; Account/Developer tabs preserved: PASS.
- Admin decorative context badge gated off; Account/Developer badges preserved: PASS.
- Complete ZIP, payload and SHA manifest: PASS.
- Full simulated check/deploy/reinstall/rollback/unexpected-source refusal: PASS.
- Real authenticated live QA: **PENDING**.

## GitHub integration status

Current GitHub `main` predates the accepted live A1–A15 patches. This draft PR is an incremental release **record** to avoid overwriting newer live files with historical application sources. Do not merge historical `main` as a deployment source before reconciliation. Deployment is from the independently guarded full-source ZIP.
