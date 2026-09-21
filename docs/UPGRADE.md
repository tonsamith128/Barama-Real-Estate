# Upgrade Workflow

Barama Real Estate includes one canonical browser updater at `/updater/`.

## Customer flow

For a supported upgrade, the customer experience is:

1. create a current backup of the website files and database
2. upload and extract the new Barama Real Estate package over the existing installation
3. open the main domain
4. Barama detects the installed-version mismatch before normal application boot
5. the browser redirects to `/updater/`
6. the customer authenticates with an active administrator email and password
7. the updater validates the installed license state
8. only pending ordered migrations are applied
9. installation metadata and `installation.version` are advanced to the package version
10. the customer returns to the website

The updater never asks the customer to re-enter the original purchase code.

## Safety rules

- the installer and updater are separate canonical entry points
- an uninstalled application redirects to `/installer/`
- an installed version mismatch redirects to `/updater/`
- the updater requires CSRF validation
- backup confirmation is required server-side
- active administrator re-authentication is required
- an upgrade lock prevents concurrent migration runs
- downgrades are rejected
- the existing activation token, installation ID, product slug, and domain binding must be valid
- live web upgrades validate the installed activation state with Ton Samith License Manager
- temporary License Manager transport/service outages may use the configured offline grace period only when the previously validated local state is still recent
- offline grace never overrides an explicit revoked, suspended, invalid, deactivated, expired, wrong-product, wrong-domain, or wrong-installation license result
- original purchase codes are never read or stored by the updater
- only pending migration files are executed
- migration replay must be a clean no-op
- successful upgrades update protected installation metadata and write a security audit event
- schema upgrades are not described as automatically reversible; customers must keep the backup until the upgraded site has been validated

## Current compatibility gate

The 0.9.5 development line reconstructs a real `0.9.3-dev` database by applying migrations 0001 through 0009, then upgrades it with the new package.

The automated upgrade gate verifies:

- the old installation is detected as `0.9.3-dev`
- only `0010_release_security_hardening.php` is pending
- missing backup confirmation is rejected
- incorrect administrator credentials are rejected
- an explicitly revoked license is rejected even when the local last-validation timestamp is inside the offline grace window
- a classified temporary License Manager outage can use the configured grace window when the previously validated local state is still recent
- the pending migration runs successfully
- `users.auth_version` is created
- `installation.version` advances to the package version
- protected installation metadata records the previous and current versions
- a `system.upgrade_completed` security audit event is written
- a second updater run is a clean no-op

The same scenario is executed once from the repository source and again after extracting the exact generated customer ZIP.
