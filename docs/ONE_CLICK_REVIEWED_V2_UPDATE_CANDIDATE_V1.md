# Phase 4H: Reproducible Barama V2 code-only candidate

The customer-package builder (`tools/build-package.php`) creates the installer distribution and **MUST NOT** be given to the V2 signed updater. Its ZIP contains directories and configuration not allowed by `SignedReleaseInspector` and `VerifiedReleaseFilePlanV2`.

`tools/build-v2-update-candidate.php` builds a distinct, restricted and deterministic update ZIP from a *clean committed Git checkout*, an **explicit reviewed change manifest**, and each target's claimed installed baseline fingerprint. The output is **unsigned and unregistered**. A matching recipe does not prove a staging baseline, a release's safety, or an install authorization.

## Review recipe

Example schema below is a **template only**, not a genuine release. Do not insert fake hashes or sign until each real changed source file and the installed staging baseline are independently checked.

```json
{
  "schema": "barama-reviewed-v2-update-recipe-v1",
  "product_slug": "barama-real-estate",
  "source_commit": "REPLACE_WITH_ACTUAL_40_CHARACTER_GIT_COMMIT",
  "source_version": "0.9.22-dev",
  "target_version": "0.9.23",
  "files": [
    {
      "path": "application/app/Services/Updates/CHANGED_CLASS.php",
      "source_sha256": "REPLACE_WITH_SHA256_OF_COMMITTED_FILE",
      "installed_sha256": "REPLACE_WITH_SHA256_FROM_INSTALLED_STAGING_FILE",
      "bytes": 1234
    }
  ]
}
```

The example values are **not** valid inputs. The source hash and byte length are validated from the Git tree, and the prior hash is required to be different. Before promoting the candidate, compare `installed_sha256` against the **actual staging target** again. No migrations, configuration, storage, licensing private state, uploads, vendor code, symlinks, or untracked files are permitted.

From a trusted Git checkout with the exact reviewed commit and a completed real recipe:

```bash
php tools/build-v2-update-candidate.php --recipe=/ABSOLUTE/PATH/TO/REAL_REVIEWED_RECIPE.json
php tools/build-v2-update-candidate.php --recipe=/ABSOLUTE/PATH/TO/REAL_REVIEWED_RECIPE.json --build --output=/ABSOLUTE/PRIVATE/OUTPUT/barama-v2-reviewed.zip
```

Default is read-only and reports its source and target versions. `--build` requires a new output path **outside** the checkout, writes a code-only ZIP containing `VERSION` with the reviewed new version and precisely the approved changed runtime files, and writes a SHA-256 sidecar. Rebuilds must produce identical ZIP hashes. It never signs, registers, publishes, checks entitlement, uses production/staging website paths, or installs an update.

## Remaining genuine acceptance

1. Approve meaningful code changes and source commit, and verify real staging installed file hashes against the recipe.
2. Verify restricted ZIP with Barama's `SignedReleaseInspector` and `VerifiedReleaseFilePlanV2` using a locally generated V2 envelope in disposable CI fixtures (no owner private keys in CI).
3. Sign **exact ZIP bytes** on the owner's offline Mac. Keep the private Ed25519 PEM off aaPanel, publisher, GitHub, and ChatGPT.
4. Use already deployed publisher intake for the Mac-signed envelope and ZIP; then implement/verify managed *draft-only* release registration with exact `manifest.release_id` matching publisher database `public_id`. Existing non-Baksey commercial upload currently generates a random public ID and must not be used as a shortcut.
5. Confirm authenticated live publisher manifest/artifact delivery and entitlement, isolated staging code+database restore and crash recovery; only then assess enabling `Update Now`.

All current installer and updater blocks remain in force. This phase delivers reproducible candidate tooling and explicit proof requirements, **not a complete auto updater release**.
