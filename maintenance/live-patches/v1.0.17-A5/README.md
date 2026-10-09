# Barama v1.0.17-A5 — Premium field labels and radio-choice typography

Target: `/account/developer/apply`

This release is based on **the exact already-deployed v1.0.17-A4 account view and application stylesheet**, not the older `main` source.

## Changes

- Inline accessible, decorative SVG icons **before** the visible label text for all nine real business fields (company name, business type, registered name, registration number, email, phone, location, website, and description). Icons appear above the inputs, not in them.
- Separate the title and helper description in both Developer and Company / Agency radio cards; preserve the real checked state and keyboard support.
- Keep the existing premium hero, application form names, options, CSRF, POST `/account/company/onboard`, email verification, administrator review, acceptance checkbox, and Cancel link.
- Scope all CSS to this form. No database, routes, controller, or other website changes.

## Exact source fingerprints

| File | Expected deployed A4 SHA256 | New A5 SHA256 |
|--|--|--|
| `application/resources/views/account/index.php` | `29c5f3a92aa6f96fa83586d335f447b7325c6504a021771b557acc64ca353001` | `5809055ad3d1819d8ac994426cd937a8b812f3ef12056af5477cbaccbee3d44f` |
| `assets/css/developer-application-v115.css` | `5035d8dfa0210b1f3c2a8f0a3e2efa7962ef881c86a99fc50c9075749626fda1` | `9bfbe8e8f888530ed3b04ec08f506d7b480cab238cc9e759ecfb2e47b8f6ddb1` |

Guarded ZIP `Barama-Developer-Application-Field-Icons-v1.0.17A5-Complete-Kit.zip`

SHA256: `7babec201c1b37ead98f422174e4c50737d3f032f3870fe9467bd8a5694fea8d`

The complete updated source and guarded installer are inside the separate aaPanel ZIP, not on this old `main` branch.

## Local QA
- PHP 8.4 syntax / form structure: PASS.
- Exactly nine accessible labels with decorative SVGs: PASS.
- Input names, four business-type options, existing radio selection, application action preserved: PASS.
- ZIP and checksum manifest: PASS.
- Simulated check, deploy, recheck, repeat-deploy, rollback, and refusal of unexpected source edits: PASS.
- Authenticated live visual and interaction QA: PENDING.

## GitHub baseline warning

Do not merge this release record into production as though GitHub `main` were current. It is behind the live v1.0.17-A4 deployment. Reconcile `main` with the accepted live application source before proposing real source-file changes in the repository. No production site is modified by creating this record.
