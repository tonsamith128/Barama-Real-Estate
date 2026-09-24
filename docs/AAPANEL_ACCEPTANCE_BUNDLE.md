# aaPanel Acceptance Bundle

Barama Real Estate uses a separate internal aaPanel acceptance bundle for the final live-server RC1 session. This bundle is QA infrastructure only. It must never be uploaded to CodeCanyon or extracted into a different website.

## Fixed scope

The generated bundle is hard-scoped to:

- domain: baramarealestate.tonsamith.com
- HTTPS URL: https://baramarealestate.tonsamith.com
- website root: /www/wwwroot/baramarealestate.tonsamith.com
- PHP CLI: /www/server/php/84/bin/php
- evidence directory: /root/barama-real-estate-acceptance-evidence

It does not contain a generic root switch and its helper scripts must not be repurposed for another site.

## Build contract

The repository builds the internal bundle only after the exact customer ZIP exists and its RC candidate artifact manifest has been generated.

The builder re-runs the canonical artifact verifier against:

- source VERSION
- full 40-character source commit
- exact ZIP filename
- ZIP SHA-256
- ZIP size
- SHA-256 sidecar
- artifact-manifest schema

A mismatch stops the build before an acceptance ZIP is created.

Default output:

    build/acceptance/Barama-Real-Estate-aaPanel-Acceptance-<version>.zip
    build/acceptance/Barama-Real-Estate-aaPanel-Acceptance-<version>.zip.sha256

## Bundle contents

The bundle contains:

- INTERNAL-QA-ONLY.txt
- README.md
- ROLLBACK.md
- BUNDLE_MANIFEST.json
- BUNDLE_CONTENTS.sha256
- candidate/<exact customer ZIP>
- candidate/<exact customer ZIP>.sha256
- candidate/<artifact manifest>.artifact.json
- tools/rc1-acceptance-lib.php
- tools/rc1-artifact.php
- tools/live-server-preflight.php
- tools/rc1-live-session.php
- bin/verify-bundle.sh
- bin/preinstall-readonly.sh
- bin/installed-readonly.sh

It intentionally excludes the RC1 acceptance JSON, evidence writer, CI workflow source, smoke-test source and unpacked application/customer runtime.

## Server handoff

Copy the internal acceptance ZIP and its outer .sha256 sidecar to /root on the aaPanel server.

Verify the outer ZIP before extraction:

    cd /root
    sha256sum -c Barama-Real-Estate-aaPanel-Acceptance-<version>.zip.sha256

Extract the verified bundle under /root, enter its directory, then run:

    bash bin/verify-bundle.sh

This verifies BUNDLE_CONTENTS.sha256 and independently re-verifies the exact customer ZIP against the expected version and source commit.

## Backup requirement

The read-only helper scripts do not deploy or delete files. Before a fresh-install or updater test changes the Barama installation, create:

1. a complete aaPanel website-files backup for exactly /www/wwwroot/baramarealestate.tonsamith.com
2. a complete database backup for the Barama acceptance database

Confirm both backup files exist outside the public web root before continuing.

Do not use /www/wwwroot as a backup/restore target and do not operate on another domain.

## Pre-install evidence

After the bound customer ZIP has been deployed only to the Barama website root and before entering installer credentials, run:

    bash bin/preinstall-readonly.sh

The script validates the fixed root/PHP runtime and writes a sanitized JSON report under:

    /root/barama-real-estate-acceptance-evidence

It does not enter installer credentials, change the database, activate/deactivate a license, or modify another website.

## Installer

Complete the normal five-step installer manually so Direct and Envato licensing, Terms behavior, requirements, database modes, website configuration and final health can be observed by a human.

Do not place license keys, purchase codes, database passwords, administrator passwords, SMTP credentials or activation tokens into acceptance reports.

## Installed evidence

After Step 5 completes, run:

    bash bin/installed-readonly.sh

Review the resulting JSON session. A passed result has no failures or unresolved warnings. review_required must not be converted into a passed RC1 gate without resolving or explicitly reviewing the warning.

## RC1 evidence

The aaPanel bundle does not contain tools/rc1-evidence.php and cannot modify docs/RC1_ACCEPTANCE.json.

Return to the repository after reviewing the session result. Record only genuinely passed gates through the controlled evidence workflow.

## Rollback

The bundle intentionally provides rollback instructions rather than a destructive rollback script.

If a live acceptance change fails:

1. stop the acceptance exercise
2. restore only the Barama website-files backup in aaPanel
3. restore only the Barama database backup
4. verify the Barama domain and installed application health
5. re-run the read-only installed preflight after recovery

Never delete /www/wwwroot, never restore another site's backup, and never infer a database name from another aaPanel website.
