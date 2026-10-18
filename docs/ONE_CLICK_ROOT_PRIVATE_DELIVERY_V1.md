# One-Click Updater Phase 4D1: authenticated root-private artifact delivery

## Purpose

The existing product already has `TrustedReleaseFeed` (real Publisher V2 Ed25519 verification, authentic installation entitlement) and `PrivateArtifactHttpClientV2` (authenticated HTTPS ZIP transfer, streaming checksum, private receipt, no redirects). Phase 4D1 adds an **explicit manual root-CLI coordinator** for structure-only staging: `application/bin/publisher-private-delivery.php`.

## Operator commands

After the separate aaPanel source-only patch has installed the two additive PHP files on staging:

```bash
/www/server/php/85/bin/php /www/wwwroot/baramastaging.tonsamith.com/application/bin/publisher-private-delivery.php --readiness
```

This only checks local prerequisites. It makes **no network request and writes no application/data files**.

When and ONLY when the actual Ton Samith publisher has a newer authentic, entitled release available, the owner can separately authorize a root-private acquisition by invoking:

```bash
/www/server/php/85/bin/php /www/wwwroot/baramastaging.tonsamith.com/application/bin/publisher-private-delivery.php --download DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY
```

The coordinator rechecks entitlement with the live publisher, retrieves a fresh signed manifest from the same pinned HTTPS endpoint, validates the real Ed25519 envelope with `TrustedReleaseFeed::verifyEnvelope`, compares the returned digest and version against the original authenticated discovery result, and calls the existing `PrivateArtifactHttpClientV2::download` (separate authenticated artifact request). The ZIP and a redacted receipt are stored **only in root-private** `/root/Barama-Publisher-Private-Releases` (0700), with no replacement of previous downloads. The command prints no license token, purchase code or secrets.

The existing transfer class has its own digest and exact-byte-count check. The original `TrustedReleaseFeed` and production interfaces are unchanged.

## Strict safety boundaries

- The command accepts **only** the exact staging site and Unix root CLI. Production is rejected, and there is no web route.
- `--readiness` is non-mutating; only the owner's distinct `--download` confirmation permits root-private artifact writes.
- No application release staging, ZIP extraction, SQL access, customer data write, migration, source update, publishing or automated install is performed. The website remains at version `0.9.22-dev` until a separate tested rollout.
- The live publisher controls whether any artifact can be downloaded. Until it publishes a release and authorizes the staging installation, the command reports no available release or a denial; **never create a fake publisher release**.
- Root-CLI startup loads the application config and existing license state read-only, without starting a PHP web session. Its private vault is deliberately inaccessible to the web process.
- This phase is not a complete updater. A later phase must separately promote a verified root-private ZIP into safe staging, prove database recovery, journal/migration/health-check rollback and gate `Update Now` behind that evidence.

## Tests

The CI fixture checks read-only prereqs, explicit consent, production refusal, denied entitlement, no release, changed signed manifest, matching digest/version, root-private permissions, preservation of app VERSION and no application staged ZIP. It uses **stubbed network interactions**, never a fake production signing key; the existing real Ed25519/ZIP CI fixture remains independent in the same workflow.

## Release note

This change is additive: two PHP files and tests/docs. The aaPanel v2.0.0 source ZIP installs those two files on **staging only**; production needs no new code for the root-only CLI coordinator. The separate customer package includes the files as inert optional tooling. Installing v2.0.0 **does not trigger the network** or download a release.
