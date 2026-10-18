# Auto Updater Phase 4D2 — Publisher V2 Trust Bootstrap (staging only)

## Scope

This phase safely provisions the existing Barama V2 signed-release consumer with an independently authenticated **public-key catalog** and a fixed, allowlisted publisher feed contract. It **does not fetch or create a signing key** or claim that an authentic publisher catalog already exists. There is currently **no genuine key catalog in the Barama GitHub application/config directory**; the aaPanel staging preflight reports both files missing.

The valid publisher catalog must come from the trusted owner-operated publisher system by a channel **independent of the consumer website's update API**. Before the first trust installation, the owner must separately verify the original SHA-256 fingerprint (for example, from a protected publisher administration console or a signed and authenticated release procedure). A checksum copied from the *same unverified file* does not establish the key's provenance.

## Source data

Place the verified **public** catalog under root-only, non-symlinked `/root/...`. Keep signing private keys, API tokens, passwords, and product/customer purchase codes **OUT** of this catalog.

Schema expected by existing `TrustedReleaseFeed`:
```json
{
  "format": "tonsamith-publisher-keys-v2",
  "keys": {
    "<actual-publisher-key-id>": {
      "public": "<base64-encoded genuine 32-byte Ed25519 PUBLIC key>",
      "status": "active"
    }
  }
}
```
This example describes **field names only**; placeholders are NOT a usable real key. Never generate or substitute a signing key on the customer/staging server. The installer rejects private key fields and requires at least one active public key.

The fixed feed config is:
```json
{"enabled":true,"contract":"tonsamith-signed-discovery-v2","channel":"stable"}
```
Do not enable beta releases unless author policy expressly requires them and code is reviewed accordingly.

## Commands (after deploying only the additive source files)

Read-only inventory:

```bash
/www/server/php/85/bin/php /www/wwwroot/baramastaging.tonsamith.com/application/bin/publisher-trust-bootstrap.php --check
```

Verify a candidate WITHOUT INSTALLING:

```bash
/www/server/php/85/bin/php /www/wwwroot/baramastaging.tonsamith.com/application/bin/publisher-trust-bootstrap.php \
  --verify /root/verified-publisher/update-publisher-v2-keys.json <INDEPENDENT_SHA256>
```

**Install only after the owner separately verified the fingerprint and approved the exact catalog**:

```bash
/www/server/php/85/bin/php /www/wwwroot/baramastaging.tonsamith.com/application/bin/publisher-trust-bootstrap.php \
  --install /root/verified-publisher/update-publisher-v2-keys.json <INDEPENDENT_SHA256> \
  APPROVE_INDEPENDENTLY_VERIFIED_PUBLISHER_PUBLIC_KEYS
```

The CLI requires root, rejects all production paths, validates exact JSON shape and Ed25519 public-key size, compares a SHA-256 digest against the owner-provided independent value and atomically creates exactly TWO configuration files. Existing files are **never overwritten**, even if they match. It never makes a network request or accesses the SQL database.

## After successful provisioning

Run `--check` and the existing `publisher-private-delivery.php --readiness` under staging. A successful local readiness report does not mean the publisher has released an update or that this installation is entitled. Live publisher release discovery is **separate, authenticated, and optional**. Do not run the separate ZIP download until entitlement and genuine published release are confirmed.

Production website, customer accounts, properties, uploads, licensing records and application `VERSION` must remain unchanged. **Update Now** and automatic installation remain disabled until complete real database restore and live rollback acceptances are proven.

## CI fixture

`sudo -E php tools/publisher-trust-bootstrap-smoke.php` uses a disposable keypair for schema validation **only in GitHub CI**; it does not create or distribute a publisher signing key. Tests verify missing configuration, correct independent digest, mismatched fingerprint, unwanted private fields, revoked-only catalogs, symlinks, lack of approval, no-clobber installation, publisher settings validation, staging/production isolation, and preservation of customer data.

## Recovery and safe re-provisioning

If a trust config is already present but invalid, this tool **refuses replacement**; investigate the source rather than overwriting it. Changes or key rotation require a separate reviewed, owner-approved trust-transition workflow.
