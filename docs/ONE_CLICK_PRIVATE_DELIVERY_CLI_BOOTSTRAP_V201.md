# Publisher Private Delivery v2.0.1 — standalone CLI APP_VERSION hotfix

## Symptom

The v2.0.0 aaPanel staging source installation and checksum verification succeeded, but `bash install.sh readiness` exited with a PHP fatal: `Undefined constant "APP_VERSION"`. The standalone CLI loads `application/config/app.php`, which reads `APP_VERSION`, but did not initialize this constant as the normal `application/bootstrap/runtime.php` does.

## Correction

Define `APP_VERSION` from the existing read-only `VERSION` file **before** loading configuration, mirroring the standard application bootstrap, without starting a web session. The fallback if missing is `0.0.0`. No existing license files, user/customer records, database connections, publisher transport behavior, or application state are changed.

## Regression proof

`php tools/publisher-delivery-cli-bootstrap-smoke.php` executes the **real CLI entry point** inside an isolated temporary site with a PHP `config/app.php` fixture that requires `APP_VERSION`, checks its value is `0.9.22-dev`, and asserts read-only status, no database connections, no network requests, and no website mutations. The existing root-private publisher delivery CI fixture remains unchanged.

## Deployment contract

The v2.0.1 offline aaPanel hotfix updates **only** `application/bin/publisher-private-delivery.php` on staging. The installer verifies the **exact v2.0.0 file SHA-256** first, creates a root-private snapshot, atomically installs the GitHub main-verified corrected CLI source, checks PHP lint, preserves `0.9.22-dev`, and refuses unknown/altered files or symlinks. It also includes read-only `verify` and `readiness` commands and a hash-guarded `rollback-staging`.

The v2.0.0 class `PublisherPrivateDeliveryV1.php` remains intact. Production is not a deployment target.

## Safety

A successful `readiness` run does not mean a genuine publisher update has been published. No automatic download, live update, ZIP extraction, migration or SQL restore has been authorized. `Update Now` remains disabled pending genuine publisher entitlement, database recovery and full staging-update rollback tests.
