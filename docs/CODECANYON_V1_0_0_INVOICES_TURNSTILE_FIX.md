# Barama Real Estate v1.0.0 — Invoices and Turnstile shared-hosting fix

This additive pre-CodeCanyon release patch addresses two issues observed after a successful independent cPanel reinstall on `codecanyon.salabandet.com`.

## Invoice page: blank Admin layout and "Something went wrong"

Cause: the existing Invoice Template UI queries the `invoice_templates` table on GET, but the original v1.0.0 database migration set never created that table. The exception interrupted view output and left the page unstyled.

Resolution:
- `0032_create_invoice_templates.php` creates the missing table in the normal installer/updater migration catalog. It uses `CREATE TABLE IF NOT EXISTS`; no existing invoices, plans, users or demo records are deleted.
- The repository now catches **only** SQLSTATE `42S02` (missing table) for a read, allowing the standard Admin shell and invoice ledger to render while showing a clear notice. It never runs DDL during a GET.
- Until the migration is applied, the Invoice Template editor's **Save** and **Reset** actions are disabled; all other database errors continue to surface normally.
- A release smoke check validates the template's 25 INSERT columns against the new migration.

## Security settings: Cloudflare Turnstile light-mode contrast

The Turnstile table row previously used dark-theme hardcoded pale text. New styles under `:root[data-bs-theme="light"] .barama-settings--flat` provide readable feature name, help copy, status badges, icon and Configure button. Dark-theme modal styles are unchanged.

## Update an **already installed** v1.0.0 site (cPanel)

1. **Back up the full database and website files first**, including application storage and licence settings. Verify the backup exists.
2. Copy these exact files from the corrected v1.0.0 ZIP to the existing installation, preserving directories:
   - `application/database/migrations/0032_create_invoice_templates.php` (new)
   - `application/app/Repositories/InvoiceTemplateRepository.php`
   - `application/resources/views/workspace/partials/invoice-template.php`
   - `assets/css/finance-invoices-focus-v1827.css`
   - `assets/css/turnstile-admin-modal.css`
3. Visit `https://YOUR-SITE/updater/` over HTTPS and sign in with the administrator credentials. The updater recognizes pending migrations even when the application version remains `1.0.0`. Complete its confirmation **after** creating the backup. Confirm that only `0032_create_invoice_templates.php` is pending before submitting. If other unexpected migrations appear, stop and inspect them before proceeding.
4. Refresh `/dashboard/invoices`. Verify the Admin header/sidebar, empty ledger, Invoice Template dialog, template Save/Reset, and the normal activity after reopening the page. The migration-warning banner should disappear.
5. Visit `/dashboard/settings/security` in both light and dark themes; confirm Turnstile feature text, description, status and Configure button remain readable.
6. Validate zero PHP errors, zero missing CSS responses, and repeat smoke tests for Admin/Account/Developer permissions. **Do not reinstall the website or import Demo Content again.**

No production databases or customer accounts were accessed or modified to prepare this patch. A fresh installation automatically runs migration 0032 during its normal installer.

## Release gates

CI must verify migration presence, PHP syntax, repository QA, MySQL fresh-install and upgrade tests, CodeCanyon compliance and the exact ZIP under Apache/Nginx and browser/accessibility QA. Live cPanel verification still requires explicit operator acceptance. Product version stays `1.0.0`.
