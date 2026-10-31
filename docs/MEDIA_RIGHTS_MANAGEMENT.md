# Media Library: Image Metadata and Usage Rights

The administrator Media Library at `/dashboard/media` now supports searchable image inventory, alt-text descriptions and recorded usage-rights information, without changing the original file, checksum, physical location or listing attachments.

## Supported operations

- Search by filename or alt text, filter by usage-rights status and paginate the results.
- View protected thumbnails and reference counts.
- Edit alt text, declare rights status, provide a rights holder and license/evidence reference.
- Require a rights holder for **Owned** or **Licensed** declarations and supporting reference for **Licensed** declarations.
- Restrict all changes to verified super administrators; enforce form CSRF, validation and action throttles.
- Record metadata updates in the audit-events log.
- Display a migration-required notice if the schema has not yet been upgraded.

## Rights statuses

- **Not reviewed:** No usage conclusion has been recorded.
- **Owned:** An administrator has recorded a purported rights holder.
- **Licensed:** An administrator has provided the purported rights holder and a license reference.
- **Restricted:** Additional rights review is needed.

These values are **declarations, not external verification of licensing or permission**. They do not automatically authorize a sale, redistribution or CodeCanyon demo use. Published listing galleries continue using their independent existing visibility checks.

## Security and data integrity

Image metadata is saved transactionally, does not move or delete private files and does not alter media attachments. Pre-existing images default to `unreviewed`. No file deletion workflow is introduced in this milestone. Displayed text is escaped by Blade templates.

## Remains incomplete

- Actual rights/license evidence attachment and independent verification workflow.
- Image thumbnail variants, resizing, watermarks, compression and image editing.
- Project-wide asset picker for the Homepage Builder.
- Bulk actions, asset folders, advanced tags, safe unused-file cleanup.
- Third-party media rights audits and end-user cPanel acceptance.

## Development workflow

Ordinary commits run PHP/Laravel feature tests and asset build. Full installer/ZIP/MySQL acceptance is deliberately manual until a unified customer release is ready. Do not deploy this migration to cPanel without a verified database backup.
