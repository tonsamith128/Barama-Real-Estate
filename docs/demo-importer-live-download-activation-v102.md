# Barama Demo Importer — Live Publisher Download Activation (aaPanel v1.0.2)

**Target:** exactly Production `baramarealestate.tonsamith.com` installed version **0.9.24**, with System → Demo Importer preview v1.0.0 and the v1.0.1 animated ring already installed.

**Activation scope:** Publisher API connection, independently pinned Ed25519 signing-key verification, authenticated licensed listing, Manual ZIP matching against the published catalog, short-lived authorized Auto Download, exact publisher-signed SHA-256 validation, native `manifest.json` package inspection, and private ZIP staging.

**Database/media/frontend installation remains DISABLED.** The real package is now published, but the full fresh-site installer and restore/rollback have not been accepted on isolated staging. Merely downloading the signed ZIP must never overwrite the live Production installation.

This package does not update license tables, key catalogs, release metadata, MySQL, signed Auto Updater, System Tool, or existing ring assets. No publisher package publication or download is initiated by deploying it.

### Installation
1. Transfer the workflow artifact's **inner ZIP** to `/root`.
2. Verify the ZIP's exact SHA-256 as printed by GitHub Actions.
3. Unzip into a private directory and execute:

```bash
python3 -B tools/aaPanel-demo-importer-activation-v102.py --check
python3 -B tools/aaPanel-demo-importer-activation-v102.py \
  --deploy --confirm ENABLE_VERIFIED_DEMO_DOWNLOAD_ONLY
python3 -B tools/aaPanel-demo-importer-activation-v102.py --check
```

The guarded installer will refuse unknown hashes of the previously installed controller, rotating publisher keys, unexpected signed Auto Updater hashes, missing ring UI, or changed routes/view anchors. A private source-code rollback receipt is written under `/root/Barama-Demo-API-Activation-Backups`. No update or ZIP download starts during installation.

After deployment, sign in as administrator, open `/dashboard/system/demo-importer?mode=auto`, and click **Download & verify official ZIP** to test the published package.

### Important acceptance boundaries

- The site's existing activation may be a **tester** license: the publisher's API currently validates active domain-bound activation, not commercial Envato purchase proof. A commercial-only entitlement policy must be implemented server-side before public customer release.
- The ZIP must match an actual **published** package in the publisher catalog and be signed by the independently pinned full fingerprint `be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287`.
- The package is 33.10 MB; PHP 8.4 and Nginx timeouts/upload limits may need review for the Manual mode. Auto mode streams to private storage.
- The downloaded package is **not installed** until isolated fresh staging `baramastaging.tonsamith.com` has passed import, checksum, UI, media, and backup/rollback acceptance.
- Never use this package to reinstall or reset staging. Staging replacement is a separate high-impact operation requiring a new scoped backup and installation plan.

