# Phase 4G — Offline Publisher V2 release signing

The author generated a new Ed25519 signing key on a separate Mac. The private PEM **must never be committed**, transferred to aaPanel, or placed on the publisher/web servers. Its public catalog and fingerprints are non-secret and can be independently reviewed.

- Public key ID: `barama-publisher-2026-rotated`
- Public-only catalog SHA-256: `16419879f9c7ce59726cade821085d4511a120e5639d1327d98d0a7d11593294`
- Raw public-key SHA-256: `f1c5bdd33d69e737e07ffc4c9671f1b8841e8466bb90969cde1052831e7f3a93`

## Tool

`tools/offline-release-envelope.py` is a Mac/local-only signer of a reviewed, existing **update ZIP** using the Publisher V2 canonical Ed25519 manifest. It refuses SSH/CI environments, unsafe paths, protected content, mismatched public/private keys, and existing outputs. It **does not** create a release ZIP, authenticate entitlement, register a release, download anything, or install updates.

On a trusted Mac, review the release ZIP and its version. Then run (replace the ZIP and release version with actual reviewed values):

```bash
python3 tools/offline-release-envelope.py \
  --private-key "$HOME/barama-publisher-key-ceremony-2026/publisher-signing-private.pem" \
  --catalog "$HOME/barama-publisher-key-ceremony-2026/update-publisher-v2-keys.json" \
  --expect-catalog-sha256 16419879f9c7ce59726cade821085d4511a120e5639d1327d98d0a7d11593294 \
  --expect-public-key-sha256 f1c5bdd33d69e737e07ffc4c9671f1b8841e8466bb90969cde1052831e7f3a93 \
  --archive /ABSOLUTE/PATH/TO/REVIEWED-UPDATE.zip \
  --version NEXT_VERSION \
  --release-id barama-release-NEXT_VERSION \
  --key-id barama-publisher-2026-rotated \
  --output /ABSOLUTE/PATH/TO/NEW/envelope.json
```

Never use placeholder paths or a release ZIP that has not been reviewed. The signing envelope is a *public* artifact; the private PEM is not.

## CI

`python3 tools/offline-release-envelope-self-test.py` creates only disposable keys under a private temporary directory and signs a disposable ZIP. It then verifies canonical bytes and the signature, runs the **actual PHP `TrustedReleaseFeed` and `SignedReleaseInspector`** against the same envelope, and rejects key mismatch, protected ZIP contents, and output replacement. CI has no access to the real author's signing key.

## Release blockers

This tool is **not** a real publisher key rotation or a verified release installation. Current publisher and production still use their original key, while staging lacks the trust catalog. First stage a controlled public-key transition and obtain a genuine publisher-registered signed release. Only after owner-approved staging SQL/code rollback, entitlement, health checks, and exact-package acceptance may `Update Now` be considered.

The signing workflow must not be confused with a root/private-key handoff: **keep signing on the trusted Mac**.
