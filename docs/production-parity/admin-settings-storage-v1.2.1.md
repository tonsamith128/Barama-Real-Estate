# Admin Settings Storage v1.2.1 production parity

This commit imports the accepted production runtime from `Barama-Admin-Settings-Workspace-v1.2.1.zip`.

- Accepted package SHA-256: `63f61c0497ca8c09505a23a5e33956eb742db6de6b43e6b11c21b4b2d37ab051`
- Production deployment: PASS
- Basic / Profile / Privacy / Security / Storage: PASS
- Storage diagnostics, write test, and guarded temporary cleanup: PASS
- Hero/site-media upload-cap enforcement: PASS
- No database migration
- No web-server configuration change
- No service restart

Runtime parity files:
- `application/app/Http/Controllers/WorkspaceSettingsController.php`
- `application/app/Services/SiteMediaStorageService.php`
- `application/app/Services/AdminSettingsService.php`
- `application/resources/views/workspace/settings.php`
- `assets/css/admin-settings-v120.css`
- `assets/js/admin-settings-v120.js`

`tools/qa.php` and `tools/package-smoke.php` change only to register the two accepted scoped v1.2.1 assets in their controlled first-party asset allowlists.
