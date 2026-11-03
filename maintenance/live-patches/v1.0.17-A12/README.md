# Barama v1.0.17-A12 — Appearance toggle server-state confirmation

**Production target:** `/dashboard/appearance`. This is an exact-source, two-file fix built from accepted v1.0.17-A11 frontend and v1.0.17-A1 AdminManagementController.

## Issue
The A11 UI showed a success message based on the local switch value after any HTTP 200 JSON `ok=true`, without comparing the backend-confirmed `enabled` state. An attempted OFF could therefore be reported as saved without reliably verifying the actual persisted value.

## Fix
- `assets/js/app.js`: toggling from the server-confirmed state produces an explicit `enabled=0` or `enabled=1` form body; success requires response `data.feature` and boolean `data.enabled` to match the requested state; restore switch and show error for mismatch/network failure; after reload, compare requested state with server-rendered checked state before confirming success; new storage key avoids stale A11 messages.
- `application/app/Http/Controllers/AdminManagementController.php`: after `setFeatureEnabled()`, read saved JSON from `application_settings` directly, verify the actual boolean `enabled` field matches the request, and only then return it in the JSON response. Avoid false success even if application cache is stale.
- Existing A11 upper-right toast, CSRF/authorization, backend services, Appearance UI, 18 modules, and public styles remain unchanged.
- Installer makes **no database modifications**. Toggle actions continue to update Appearance settings.

## Guarded package
`Barama-Appearance-Toggle-Verified-v1.0.17A12-Complete-Kit.zip`
SHA-256 `06b99fcb14b8f982d172a644280e4aa8f5ccd985f9d9e24772bfdfe444fa11bf`

| File | Old (A11 baseline) | New |
|--|--|--|
| `assets/js/app.js` | `3d60a85cd5d70d4c36b7ee2edd6a119b42fd55ef44d19ed4658a3c84538aca04` | `d5fe5ea5c2c9d6bf4767b5854a33af39356ea9c98c39ff55f1b861b3e6313cc2` |
| `application/app/Http/Controllers/AdminManagementController.php` | `2a756afb35c0a3b7e76056114a1ace51a7e02a4cc5e25f0724cc2bf9def06cbd` | `26f3e88465b1945e09d87c6c37400958b094f3c459d40cf77cf2de36e6437a35` |

## QA performed
- Node.js syntax and PHP 8.4 syntax: PASS
- Chromium mocked on/off toggle with `enabled=0`: PASS
- Chromium server-mismatch, HTTP failure, network error recovery: PASS
- ZIP SHA256 and contents: PASS
- Simulated installer check/deploy/repeat/rollback/conflict rejection: PASS
- **Authenticated live-server toggle and DB-backed readback: PENDING**.

## GitHub source note
GitHub `main` is behind manually deployed v1.0.17-A1–A11. This branch is a **release record**; the deployable source is included in the separately delivered guarded aaPanel ZIP. Do not merge older `main` application source into live production before reconciling it.
