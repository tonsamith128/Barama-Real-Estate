# Ton Samith License Manager QA

Barama Real Estate supports two customer verification methods through the same installer license field:

1. Ton Samith direct license
2. CodeCanyon / Envato purchase code

Both methods use the canonical LicenseManagerClient and the same Ton Samith License Manager service.

## Deterministic contract QA

The normal Quality workflow runs `tools/license-contract-smoke.php` without network access or secrets.

It verifies:

- the Direct and Envato verification method payloads
- Barama product binding
- domain normalization and binding
- installation-ID binding
- active-status validation with only explicit `active` accepted
- rejection of every non-active status and incomplete binding response
- temporary-vs-permanent HTTP failure classification for updater grace
- wrong-product rejection
- wrong-domain rejection
- wrong-installation rejection
- license deactivation
- no second licensing implementation is introduced

The injected QA transport exists only to make the client contract deterministic. Production requests continue to use HTTPS with cURL certificate verification.

## Live QA

Live validation is intentionally separate from normal push CI because it depends on external service availability and secret Barama test credentials.

Run the `Live License Manager QA` workflow manually after configuring these repository Actions secrets:

- `BARAMA_LIVE_DIRECT_LICENSE_KEY`
- `BARAMA_LIVE_DIRECT_DOMAIN`
- `BARAMA_LIVE_ENVATO_PURCHASE_CODE`
- `BARAMA_LIVE_ENVATO_DOMAIN`

The secrets must belong to Barama-specific test licenses. Do not reuse another product's purchase code or license key.

The live workflow:

- activates a disposable Direct installation
- validates the returned activation token and explicit product/domain/installation/status binding
- confirms wrong-domain validation is explicitly rejected
- confirms wrong-installation validation is explicitly rejected
- revalidates the active token immediately before final installation in the browser installer
- deactivates the disposable activation
- confirms the deactivated token can no longer validate
- repeats the same lifecycle for Envato
- confirms syntactically valid but unknown Direct and Envato credentials are explicitly rejected
- fails instead of counting a temporary License Manager outage as a successful rejection
- serializes live-license runs so the same test credentials are not activated/deactivated concurrently

The workflow does not print the original credentials or activation tokens.

## Release gate

The CodeCanyon v1.0.0 live-license gate remains open until the manual workflow completes successfully against the production Ton Samith License Manager with Barama-specific test credentials.

A deterministic contract pass is necessary but is not a substitute for the production service test.
