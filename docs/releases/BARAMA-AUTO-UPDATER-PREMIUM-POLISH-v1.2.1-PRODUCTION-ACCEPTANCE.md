# Barama Auto Updater v1.2.1 — Production Installation Acceptance

## Operator-provided aaPanel results

- Package: `Barama-Auto-Updater-Premium-Polish-v1.2.1.zip`
- Outer SHA-256: `daa926b5b2825db57f7a234e22c6e60886c248a033bf1f520cda4190f47de98a`
- ZIP outer SHA check: PASS
- Package internal `SHA256SUMS.txt`: PASS
- Portable QA: **44/44 PASS**
- `BARAMA_PREMIUM_POLISH_V121_PREFLIGHT=PASS`
- `PHP85_STAGED_VIEW_SYNTAX=PASS`
- `LIVE_VERSION=0.9.22-dev`
- `PINNED_V120_UI_SOURCES=PASS`
- `PUBLISHER_VERIFICATION_ROUTE_AND_SCRIPT=UNCHANGED`
- `PRIORITY_AND_DETAIL_UI=PASS`
- `VERIFIED_PRIVATE_PREINSTALL_BACKUP=PASS`
- `BARAMA_PREMIUM_POLISH_V121_INSTALL=PASS`
- `BARAMA_PREMIUM_POLISH_V121_COMPLETED`
- Private `ROLLBACK_BACKUP_ID=20261011T191413Z-878268b4`
- `LICENSE_DATABASE_NGINX_TURNSTILE_CHANGED=NO`
- `AUTO_INSTALL_ENTITLEMENT=NOT_ENABLED`

## Acceptance boundary

The results above are from the owner-supplied server console log. They establish successful install of the 3-file UI upgrade and passing automated tests, **not yet visual or behavioral acceptance in the production browser**.

Complete user-visible acceptance by opening `https://baramarealestate.tonsamith.com/dashboard/system/auto-updater`, verifying the shield in both themes, performing a real publisher validation, checking that completed progress is hidden and verified rows move beneath outstanding items, and expanding/collapsing Detail rows with pointer and keyboard.

This record does not signify successful signed-update entitlement or enable automatic releases. Current functioning license credentials must be preserved. Do not run rollback absent a demonstrated regression.
