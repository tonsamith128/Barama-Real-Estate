# Barama One-click: Publisher-Signed Release Source Handoff (2026-10-20)

**State:** IMPLEMENTATION / REVIEW ONLY. Staging is `0.9.22-dev` (aaPanel owner audit); next update version **not approved**. No published signed update artifact has been located. The existing publisher private key and separate owner approval private key must never be copied to GitHub, ChatGPT, the web root, the server, or this document.

## Gates before any real install

1. Review/approve a specific set of customer-safe source files and the exact source commit. The current one-click PR contains **unfinished live-worker functionality**; do not automatically publish the whole branch or call it a release.
2. Read-only compare the exact selected source files against the **actual** installed staging `0.9.22-dev` file baselines. The current deterministic candidate builder only accepts existing files with a different, independently reviewed `installed_sha256`. **New file paths are not supported by that recipe builder**; it must not invent a baseline.
3. Review the entire file-level recipe (source SHA/size + independently verified installed SHA), exact intended version, migrations, compatibility and customer media separation. Build a deterministic **unsigned** restricted ZIP with the repository's existing `tools/build-v2-update-candidate.php` from a **clean commit**.
4. Sign the reviewed ZIP on the publisher's *offline* trusted computer with the **existing genuine** publisher Ed25519 signing identity and independently verified public catalog, using `tools/offline-release-envelope.py`. Never generate a temporary fixture key for a genuine update, place a private key on the server, or invoke the signer over SSH/CI.
5. Independently verify exact ZIP SHA-256, signed `envelope.json`, publisher public catalog, license entitlement, file plan and current baseline. Explicit publisher registration/delivery is separate from merely signing an envelope. The signer output alone does not prove a release was published.
6. Prove **one matching consistent full-site + MySQL checkpoint**, independently restore both components in isolation and compare data/schema/files, enforce short-lived single-use independent owner approval bound to release/DB/checkpoint, and finish/accept the privileged install worker with maintenance, crash journaling and SQL failure review.
7. Only after gates (1–6), request an owner approval naming exact signed ZIP hash, source/target versions, database identity, backup and full rollback procedure **before modifying staging**. Complete end-to-end staging recovery. Do not merge PR #108 to main until current-head CI and real update/restore acceptance all pass.

## Source inventory (development Mac or controlled clean source clone)

The two added tools are deliberately **read-only** and do not create an installable ZIP.

```bash
cd /path/to/clean/reviewed/Barama-Real-Estate
python3 tools/oneclick-release-source-inventory.py \
  --source-root="$PWD" \
  --target-version=0.9.23 \
  --file=application/app/Services/Updates/OneClickDashboardGateV1.php \
  > /path/to/private/review-source-inventory.json
```

The example path and target version are **illustrative only**; release file selection and version must be independently reviewed. Choose only code that belongs in the release, not the unfinished automatic installer. The script validates a clean committed Git tree, allowed protected-path policy, source bytes and exact commit; all installed SHA-256 fields remain `null` and every readiness flag remains false.

## Read-only staging baseline comparison

Copy only `review-source-inventory.json` (no secrets) and `tools/oneclick-staging-baseline-comparison.py` onto the aaPanel server. Run:

```bash
python3 /root/oneclick-staging-baseline-comparison.py \
  --inventory=/root/review-source-inventory.json \
  > /root/read-only-baseline-comparison.json
```

Only the fixed staging root `/www/wwwroot/baramastaging.tonsamith.com` is inspected. The script refuses protected paths, symlinks, version mismatch, malformed inventories and unrelated site roots; it does **not** access database credentials or write files. Redirecting stdout creates a report under `/root` **only at the operator's instruction**; omit the redirect for strictly no writes. Installed digest `null` means **FILE_ABSENT_BUILDER_UNSUPPORTED**. `NO_CONTENT_CHANGE` means there is no update for that file. A changed file requires owner review of the preexisting installation.

## Existing reviewed builder / offline signer

Once a reviewer creates an exact `barama-reviewed-v2-update-recipe-v1` JSON from trustworthy source and staging evidence:

```bash
php tools/build-v2-update-candidate.php --recipe=/private/reviewed-recipe.json
php tools/build-v2-update-candidate.php --recipe=/private/reviewed-recipe.json \
  --build --output=/private/new-release-candidate.zip
```

The builder refuses dirty Git checkouts, untracked source, mismatched baseline digests, protected paths, new files (without an existing baseline SHA) and overwrite of output. Build is **not** signing, publishing or staging.

On the genuine offline publisher signing machine, after independently verifying **both** the existing public catalog fingerprint and the active public key fingerprint:

```bash
python3 tools/offline-release-envelope.py \
  --private-key=/OFFLINE-PRIVATE/PUBLISHER-ED25519.pem \
  --catalog=/OFFLINE-PUBLIC/keys.json \
  --archive=/private/new-release-candidate.zip \
  --version=0.9.23 \
  --release-id=OWNER-APPROVED-RELEASE-ID \
  --key-id=OWNER-APPROVED-EXISTING-KEY-ID \
  --expect-catalog-sha256=INDEPENDENTLY_VERIFIED_64_HEX_DIGEST \
  --expect-public-key-sha256=INDEPENDENTLY_VERIFIED_64_HEX_DIGEST \
  --min-php=8.5.0 \
  --minimum-app-version=0.9.22-dev \
  --output=/private/new-release-envelope.json
```

**DO NOT run this signing command literally** with placeholders. Existing key identity, the release ID, file selection, application compatibility and target version require review. Keep signing keys offline and out of user support logs, chat and GitHub.

## Current blockers explicitly recorded

- AaPanel `STAGING_VERSION=0.9.22-dev`, PHP 8.5.11 + sodium/zip/PDO MySQL.
- Root-private vault directories were owner-authorized and successfully provisioned (no backups created).
- `SIGNED_RELEASE_DIR=MISSING` at audit; no genuine signed ZIP or `envelope.json`.
- Independent owner public-key trust catalog is NOT provisioned.
- SQL-consistent site+database restore and the live privileged worker remain incomplete.
- PR #108 is draft/unmerged. `Install Update` stays disabled.

**Never treat synthetic fixture signers, a passed independent file restore, a non-coherent snapshot pair, or green CI by themselves as proof of production installation readiness.**
