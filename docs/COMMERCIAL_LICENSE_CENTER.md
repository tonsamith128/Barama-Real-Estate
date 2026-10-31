# Commercial License Center — Current Scope

Barama now uses the shared premium administrator shell for `/dashboard/license` and provides a manual, server-side **Verify license with publisher** action.

## Implemented

- Existing genuine purchase-code activation is preserved; no code generation, bypass or fallback entitlement.
- Correct domain identification from the configured Laravel application URL, with a domain-mismatch warning.
- Clear distinction between **last locally recorded active receipt** and a **current publisher verification**.
- Authenticated super-administrator action `POST /dashboard/license/revalidate` with CSRF and conservative rate limiting.
- Revalidation sends the existing private activation token, normalized domain and installation ID to the official publisher API via `PublisherLicenseClient::verify()`, which already validates the expected product, domain, installation and active status.
- Successful confirmation atomically replaces the private receipt and updates the validated-at time.
- Rejected/revoked service responses, domain mismatches and installation ID mismatches do not create a new active receipt. A connection problem never claims verification succeeded.
- The purchase code is never exposed in HTML, included in status summaries, or saved in the local receipt. Tokens are held privately in `storage/app`.
- Unit tests use a deterministic in-memory verification transport; tests never contact the real publisher.

## Deployment and operational limits

The existing activation receipt is **not** proof of current entitlement and a locally cached status must not be treated as a live subscription check. Live activation and revalidation on a customer domain require real publisher availability and customer-provided credentials.

Still pending: complete customer license portal, upgrade/renewal lifecycle, safe deactivation/reactivation, offline-grace policies, purchase-code revocation synchronization, signed auto-updater, billing, payment gateways, and CodeCanyon production acceptance. These require separate implementation and staging QA.

This is development source, not a production release. Do not overwrite existing `.env`, `storage`, activation receipts, or customer database.
