# Barama Publisher Response Shape Diagnostics v1.1.5

## Observed production baseline

The read-only v1.1.4a diagnostic completed without modifying Barama and received `DIAGNOSTIC_RESULT=HTTP_NON_SUCCESS`, `HTTP_STATUS=422`, `PUBLISHER_SUCCESS_FLAG=FALSE`, `LICENSE_OBJECT=MISSING`, `ERROR_CODE_CATEGORY=UNRECOGNIZED_ERROR_CODE` from the existing Ton Samith License Manager validation endpoint.

This proves an HTTP response was received. It **does not** prove the license is revoked, expired, invalid, or bound to a different domain. Do not change publisher license records or production domain bindings based only on HTTP 422.

## Verified release artifact

- Self-contained ZIP: `Barama-License-Publisher-Response-Shape-Diagnostics-v1.1.5.zip`
- SHA-256: `077cc8856a3996e1f84fb214c813526a8da1c9fd9621ad550489d5c64c45c57c`
- 32/32 offline local tests PASS and 32/32 PASS from a clean ZIP extraction.
- All internal SHA256SUMS PASS after extraction.
- Source: `diagnose.py`, `bin/publisher-probe.php`, `lib/response-classifier.php`, `tests.py`, `README.md`, integrity manifest (complete runnable kit available as the conversation ZIP, not committed as product source).

## Exact diagnostic scope

- `python3 -B diagnose.py --check` is offline and read-only, confirms the exact domain/version/PHP runtime and package integrity.
- `python3 -B diagnose.py --diagnose` makes **one** HTTPS POST to the existing fixed `https://tonsamith.com/license/v1/validate` using Barama's stored activation token. No new API endpoint or guessed request fields.
- Prints only strict safe enums: HTTP status, success flag, license-object presence, previously recognized error codes, plus `RESPONSE_SHAPE`, `ERRORS_CONTAINER`, `ERROR_FIELD_COUNT`, `UNRECOGNIZED_FIELDS` and allowlisted request-field flags such as `FIELD_ACTIVATION_TOKEN=REPORTED`.
- No raw response, arbitrary error messages, unknown field names, purchase code, license token, installation identifier, exception text, private addresses, database data or logs are printed.
- No license activation, revocation, domain replacement, migration, database writes, Nginx/Turnstile changes, release download or installation.
- If the publisher response is generic, the diagnostic says so; no invalid-license inference.

## Release gate

This package is **not** an in-place application upgrade. Do not overwrite the working v1.1.3a License Center. Operator must verify the ZIP SHA-256 and internal manifest, run 32 tests, then offline `--check` before optionally running explicit `--diagnose`. Production execution and specific output remain pending.

Next step after receiving safe result: inspect the actual Ton Samith License Manager validator/route contract with authorized read-only access if a generic HTTP 422 response remains; do not invent mutation endpoints or expose stored credentials.
