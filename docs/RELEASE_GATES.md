# Release Gates

Barama Real Estate uses explicit release gates so development does not accumulate unfinished or duplicate implementations.

## Automated gate

Run:

    php tools/qa.php

The automated gate currently verifies:

- canonical foundation files exist
- exactly one first-party CSS file exists
- exactly one first-party JavaScript file exists
- obvious old, backup, copy, and legacy filenames are absent
- every PHP source file passes PHP syntax lint

The same gate runs in GitHub Actions for main-branch pushes and pull requests.

## Development rules

1. Extend the canonical implementation; do not create versioned source copies.
2. Keep page styling in assets/css/app.css unless a future split has an explicit architectural reason.
3. Keep browser behavior in assets/js/app.js unless a future split has an explicit architectural reason.
4. Add database changes through ordered migrations rather than editing production schemas manually.
5. Do not commit secrets, runtime logs, cache files, uploads, generated packages, or local editor state.
6. Do not advertise a feature as complete until its routes, permissions, validation, UI states, error states, and persistence path are implemented.
7. Before v1.0.0, add installer, upgrade, compatibility, security, accessibility, packaging, and manual browser/device gates.

## Manual gates before CodeCanyon v1.0.0

Automated QA is necessary but not sufficient. The final release must also be validated for:

- installation on supported Apache and Nginx/shared-hosting environments
- database creation and upgrade behavior
- authentication and authorization isolation
- upload security
- payment and subscription flows where enabled
- responsive behavior across desktop, tablet, and mobile
- Light/Dark Mode
- accessibility keyboard/focus behavior
- production error handling
- email delivery
- localization
- browser compatibility
- clean CodeCanyon package contents
- documentation completeness
- license and third-party notices
