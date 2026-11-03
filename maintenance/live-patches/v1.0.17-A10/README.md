# Barama v1.0.17-A10 — Catalog action fonts and Appearance frontend CSS priority

This release was built from the actual deployed A9 Catalog view, A8 Catalog stylesheet, and previously captured live site layout.

## Scope
- Normalize link/button typography within the Catalog three-dot menu (same 14 px size, weight, line height, focus).
- Bump only the Catalog stylesheet query-version.
- Move versioned dynamic `/appearance.css` to **the last public CSS link**, after optional mobile, header, and footer public styles. This prevents later public styles from unintentionally overriding enabled Appearance settings.
- Preserve the layout's existing `!$isWorkspacePage` guard, keeping Admin/Developer/Account UIs unchanged.
- No change to the AppearanceRepository, AppearanceManagementService, the 18 module definitions, the routes, Mapbox settings, location records, or database schema.
- Include a separate read-only production Appearance audit verifying database configuration read, 18 module defaults, generated CSS, 16-character configuration hash, route presence, and final public CSS link order. It does not change settings.

## Verified live-source hashes
| File | Before | After |
| --- | --- | --- |
| `application/resources/views/workspace/index.php` | `756134dfec6fffa90fdf916601421b347b9e8fbb10eb27986a9be0543f21ed5d` | `3855fc0bcdfef80340802f2d808d6dde48377a718c5484e12e9adf585fece3ae` |
| `application/resources/views/layouts/app.php` | `75cc372ed52015de32706c189cd3d7b3afef273b3d805c037c0f7f178f77c6c1` | `89b389c14000809a97038ddec8c1ec55854dc0837c9a16f7d1ac08cce92d3c48` |
| `assets/css/catalog-quick-actions-v117a6.css` | `d5dc7a9274a2b3fa40407abb4955f357260194c0cdff2044af449842614f6323` | `0a35d07483d73b7cb60c1833621bc1e5530d5bca643709bc1db1ae7ab91d1a62` |

## Release package
`Barama-Appearance-Catalog-v1.0.17A10-Complete-Kit.zip`
SHA256 `e964b2a0c8baaba52d24b67864a925f85ee363ada44fae6bdea157a87b3e0e95`.
Guarded installer with exact hash refusal, backup, rollback and no Node requirement. No database migration or writes during installation.

## QA
- PHP template lint: PASS.
- AppearanceManagementService: enabled sample modules generate CSS and change version hash: PASS, 18 modules.
- Check/install/recheck/idempotency/rollback: PASS in simulated production source.
- Unexpected source/payload modification refusal: PASS.
- ZIP and internal checksums: PASS.
- Live DB-backend read-only audit, authenticated Admin save/toggle, browser computed CSS: PENDING.

**GitHub baseline caution:** The main branch predates live A1–A9 patches. This draft PR records the actual incremental diff under maintenance. Do not merge old application files from main as a replacement for live source until reconciled.
