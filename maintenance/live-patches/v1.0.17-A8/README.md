# Barama v1.0.17-A8 — Clean Catalog creation actions

Production target: `/dashboard/catalog`. Exact deployed source baseline: **v1.0.17-A6 Catalog assets** (unchanged by A7 Mapbox release).

## Changes

- Render **Add Location** (left) and **Add Amenity** (right) as two equal premium buttons in a single header row on desktop and mobile.
- Remove the *duplicate closed creation cards* from the default visual layout. Clicking a header button expands the existing genuine PHP `<details>` creation form in the content region, focuses the name field, and hides the other form. Click again to close; all existing form action URLs, CSRF and fields remain unchanged.
- Preserve progressive enhancement: native creation cards are available without JavaScript.
- Change only the Catalog stylesheet, JavaScript, and its two cache-busting references inside the existing Workspace view (`?v=117a8`). No new APIs or backend code.
- Keep installed A7 Mapbox configuration, location data, parent relationships, and provider search code untouched.

## Source fingerprints

| File | Before SHA256 | After SHA256 |
| --- | --- | --- |
| `application/resources/views/workspace/index.php` | `8b1e0306e162c4b03bb9d8bdc9a263e8716c2b3f8a7dde290ff335528dd7af83` | `df49f62ddd4c977900c3d4ea3bcc2f638ab58be6ae6ebc005c3b8563c75e45ac` |
| `assets/css/catalog-quick-actions-v117a6.css` | `58064a84d2b45f0b830aaf2d3b69998274b49302c2fbce3ddc91bea8847fcc00` | `d5dc7a9274a2b3fa40407abb4955f357260194c0cdff2044af449842614f6323` |
| `assets/js/catalog-quick-actions-v117a6.js` | `362d39de2250c56d0ac2768fae4749d02230ed65a6c82c6c391b14b277eb7ea2` | `9a7ed87b86ec54c1848015417c7b41fc94a115465cde9988da04189b930a33d7` |

## Guarded deployment artifact

`Barama-Catalog-Actions-Cleanup-v1.0.17A8-Complete-Kit.zip`

SHA-256: `5765715d0bb81c2cae8dd453a34b33d0cff34f8e07f2209b3b77165a2768159d`

Contains real source files, private backup-and-rollback installer, preflight `--check`, per-file SHA-256 manifest, and instructions. This artifact is distributed to the owner separately; **this branch does not replace the old `main` application source**.

## QA

- PHP view syntax PASS
- JavaScript syntax PASS
- Chrome/Chromium UI PASS at 1440 px, 620 px, 375 px: both buttons on one row; closed cards hidden; Location opens; Amenity switch works; close works
- Payload and complete ZIP checksums PASS
- Simulated source check, installation, postinstall hash check, repeat-install, rollback PASS
- Unexpected source and tampered payload refusal PASS
- Authenticated live browser acceptance PENDING

## Repository caution

GitHub `main` is behind the source manually deployed to aaPanel. Do not merge a replacement view from historical `main`. This is a release record; reconcile accepted production source before applying this patch to the repository's actual app files.
