# Barama Demo Importer — aaPanel Preview v1.0.0

This is a **guarded preview** for installed Production Barama **v0.9.24**.

- Provides a new **System → Demo Importer** menu and premium **Manual Import / Auto Import** tabs.
- **Manual Import** can upload/inspect a correctly publisher-signed ZIP safely. Upload never installs.
- **Auto Import and database installation are disabled** by a verified server-side release gate because the actual commercial Barama demo publisher package/endpoint have not been published and end-to-end validated.
- **No data, licensing state, application release, or active Auto Updater is changed** by deployment.
- DO NOT claim the feature fully works for paying customers. It is for owner UI/hand-off acceptance only.
- The authentic full-feature release must follow the TonSamith.com publisher implementation and isolated import/rollback acceptance, with an independently reviewed scope.

Usage on aaPanel, after checking the ZIP SHA256 shown on the download:
```bash
cd /root/Barama-Demo-Importer-Preview-aaPanel-v1.0.0
python3 -B tools/aaPanel-demo-importer-preview-v100.py --check
python3 -B tools/aaPanel-demo-importer-preview-v100.py --deploy --confirm INSTALL_BARAMA_DEMO_IMPORTER_PREVIEW_ONLY
python3 -B tools/aaPanel-demo-importer-preview-v100.py --check
```

Rollback the code by using the specific `BACKUP_ID` returned from deployment:
```bash
python3 -B tools/aaPanel-demo-importer-preview-v100.py --rollback BACKUP_ID --confirm ROLLBACK_BARAMA_DEMO_IMPORTER_PREVIEW
```

The provided utility fails closed if Production is not 0.9.24, the pinned updater checksum differs, the System Tool is missing, any router/menu anchor has changed, the release gate is absent, or there is already a partial install. It does not attempt to modify non-matching source.
