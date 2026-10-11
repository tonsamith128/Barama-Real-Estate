# Barama System — Live Publisher License Validation v1.1.1

**Status:** locally validated release candidate; production aaPanel deployment **not yet performed**.

## Purpose

Upgrade the existing Barama `System → Auto Updater` dashboard with a real administrator-initiated **Check license** operation using Barama's established `Barama\\Services\\LicenseManagerClient::validateActive()` contract. No new publisher API endpoint is guessed.

**Trusted upstream:** existing `https://tonsamith.com/license/v1/validate` with product `barama-real-estate`; requests bind the current Barama domain and installation ID and are sent only after a logged-in administrator clicks Check license.

## Package identity

- **ZIP:** `Barama-Publisher-License-Validation-v1.1.1.zip`
- **SHA-256:** `5c7d4757294ce4df67ea35d8e412176474d62c7432275cc461bbb0ccdbde7e91`
- Self-contained ZIP: 11 files including installer, fixtures, PHP service, view, route fragment, tests and manifest.
- **QA:** 22/22 tests passed locally and from a clean ZIP extraction.
- Deployment baseline: exact v1.1.0 controller/view hashes and guarded existing Auto Updater route; actual live route is *patched*, never overwritten.
- Private pre-install backups and sha-verified changed-file rollback; fail-closed on existing file conflict, unknown source, symlinks or wrong app version.

## Security contract

- POST `/dashboard/system/auto-updater/check-license` protected by existing AuthMiddleware + `dashboard.access` + CsrfMiddleware.
- Explicit action only; no network or database mutation during GET, CLI preflight, or auto-install.
- License activation token is read locally by the server and sent only to the already configured trusted publisher; never shown in HTML, session status, logs, or exception errors.
- Validates active license, same product, domain and installation. Shows generic status and timestamp for this administrator session, with 90-second per-session throttling.
- **Active license != support period or permission to download/install updates.** These entitlement checks, release feeds, publisher signing keys, demos, remote package delivery, database migrations and one-click installation remain disabled/unconfigured.
- No Nginx, database, Cloudflare Turnstile, customer pages, or saved license-state changes.

## Required future release gates

1. Confirm publisher-side entitlement APIs and domain-bound status contract separately.
2. Design and publish signed release manifest and long-term signing-key rotation policy.
3. Verify private download, checksum/signature and exact CodeCanyon product compatibility.
4. Guard staging, database migrations, private backups, tested restore, and one-click upgrade behind acceptance.

## GitHub scope

This PR holds the verified package provenance and security contract for future work. The full runnable source remains in the attached ZIP until the exact deployed source snapshot is reconciled and separately checked in. Do not merge source replacement blindly into customer-facing main.
