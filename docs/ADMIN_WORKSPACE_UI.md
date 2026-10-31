# Premium Admin & Developer Workspace Shell

Barama now uses one shared Blade layout `resources/views/components/layouts/admin.blade.php` for the administrator and developer workspaces. The administrator overview also uses this component instead of a separate HTML document.

## Interface features

- Left navigation organized into **Overview**, **Content & Experience**, **Customers & Support**, **System**, and **Quick Links**.
- Role-specific navigation: developer workspace pages never render administration module links.
- Consistent Barama branding and compact authenticated account identity.
- Responsive topbar with functional site/account links and mobile navigation.
- Professional overview with a branded hero, compact metric tiles, actionable module shortcuts, installation/migration status and activity panels.
- Keyboard-accessible skip link, semantic navigation labels, visible current-route indicator and reduced-motion support.
- Retains backup-first missing-table recovery instructions and existing underlying authorization middleware.
- Consolidated CSS in `resources/css/admin/app.css`, with no page-specific release filenames or extra frontend dependencies.

## Feature boundary

This milestone improves user experience and navigation; it does **not** claim feature parity for licensing, billing, updates, demo importing, or rich visual-page editing. The administrator dashboard remains a live data view on current application tables, not simulated metrics.

## Development-only acceptance

Feature tests check admin authorization, developer navigation privacy, shared layout across catalog/media/appearance pages and migration warning behavior. Normal PHP/Laravel/Vite CI runs on the branch. Full installer acceptance remains manual, and no source-control merge deploys to customer hosting.
