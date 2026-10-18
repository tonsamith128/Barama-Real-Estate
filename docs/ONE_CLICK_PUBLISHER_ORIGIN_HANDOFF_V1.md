# Phase 4D3 — Independent publisher origin and public-only handoff

The owner-supplied audits found a strict V2 public-only JSON registry with one active Ed25519 verification key. The publisher path is /www/wwwroot/tonsamith.com/storage/private/commercial-updates/v2/keys.json.

Discovered same-host fingerprints (NOT independent attestation):

- Public-key SHA-256: a79735b477a56e942330277d4988f4ab3359705ee6aaa0ddf1a0e822f4d5bfdf
- Catalog file SHA-256: 2def98c06b5503f63e53054861260e0c48a13834f942ee51b107cafe23f70901

These fingerprints are discovery results only. Do not claim independent authenticity by copying these fingerprints to another file or re-reading them from the same host.

## Offline operator workflow

The standalone Python CLI is tools/publisher-trust-handoff.py. It cannot establish independent trust; it can only compare a public-key catalog with two independently supplied hashes. It is root-only on the production publisher host and does not modify Barama application trust automatically.

First, run the strictly read-only audit:

    python3 -B tools/publisher-trust-handoff.py audit

It prints public-key and catalog SHA-256 only, without key values, license tokens, private keys, or database access. The result explicitly reports INDEPENDENT_ORIGIN_AUTHENTICATED=NO.

The optional root-private copy is permitted ONLY after the owner verifies both SHA-256 fingerprints against a trusted **off-server original signing-key setup record**:

    python3 -B tools/publisher-trust-handoff.py prepare \
      --expected-catalog-sha256 '<VALUE_FROM_INDEPENDENT_SOURCE>' \
      --expected-active-public-sha256 '<VALUE_FROM_INDEPENDENT_SOURCE>' \
      --confirm PREPARE_PUBLIC_ONLY_CATALOG_AFTER_INDEPENDENT_VERIFICATION

This writes only /root/verified-publisher/update-publisher-v2-keys.json with mode 0600 in a 0700 private directory. It refuses symlinks, changed fingerprints, unknown/private JSON fields, revoked-only catalogs, group-writable source files, missing approval, and an already existing destination. If the independent record is unavailable, STOP after the audit.

The existing Barama staging-only trust bootstrap can then verify the root-private catalog without installing it:

    /www/server/php/85/bin/php \
      /www/wwwroot/baramastaging.tonsamith.com/application/bin/publisher-trust-bootstrap.php \
      --verify /root/verified-publisher/update-publisher-v2-keys.json \
      '<VALUE_FROM_INDEPENDENT_SOURCE>'

Any --install operation is separately owner-authorized and NOT performed by this handoff. Signed release availability and entitlement remain independent prerequisites. Real MySQL restore and live staging rollback must pass before Update Now can be enabled.

## Test

Run python3 -B tools/publisher-trust-handoff-smoke.py; it executes 14 isolated tests without reading publisher files, modifying site files, downloading a release, or contacting a database.
