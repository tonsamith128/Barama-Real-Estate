# Barama Homepage and Main Navigation Builder

The **Homepage Builder** is available at `/dashboard/appearance/homepage` for verified super administrators. It builds on the existing Laravel Appearance Manager at `/dashboard/appearance`.

## Implemented

- Add up to 12 custom homepage sections of type **Text**, **Feature Card**, or **Call to Action**.
- Edit, show/hide, delete and move sections up or down. All changes are **unpublished drafts**.
- Manage the desktop/mobile header navigation with safe, preconfigured internal route destinations. Add, remove and reorder menu links.
- Use the existing Appearance Manager **Publish website appearance** action to atomically publish navigation, sections, branding, hero text, footer copy, SEO description and primary color.
- Reset to the currently published settings through Appearance Manager without losing public content.
- All changes are recorded as audit events. Forms enforce authentication, super-admin checks, CSRF validation, throttling, allowed section types, and strictly selected internal routes. Public text is Blade-escaped.
- The homepage safely uses default sections and menu settings if the appearance table has not been migrated. No additional database migration is needed for this builder because section/navigation drafts are stored in the existing `site_appearances` JSON fields.

## Important boundaries

The builder does **not** accept raw HTML, remote JavaScript, arbitrary URL destinations, or arbitrary Blade templates. This is intentional for a secure shared-hosting product.

**Not yet included:** drag-and-drop canvas, arbitrary page layouts, uploaded hero/media placement, reusable visual blocks, menu submenu hierarchy, independent footer link management, typography presets, translations, or per-page appearance configuration. These remain feature-parity work.

## Development vs release QA

- Automatic GitHub **Laravel v2 Quality** workflow runs PHP lint, code structure checks, Composer install, Laravel feature tests, installer unit tests, and Vite build on pushes.
- **Barama Manual Release Acceptance** performs the expensive MySQL fresh installation, Apache smoke tests, ZIP packaging, and installed-package tests **only when manually started**, avoiding repeated development ZIPs.
- No branch commit or merge deploys code to cPanel. Never overwrite an existing customer's `.env`, `vendor/`, `storage/`, installation lock, or database.

## User acceptance

1. Sign in as a verified super administrator; open `/dashboard/appearance/homepage`.
2. Add a Text section and a CTA section; move, edit, hide and reorder them.
3. Verify the public homepage has not changed before publication.
4. Add FAQ to the main navigation and reorder it. Confirm desktop/mobile are unchanged before publication.
5. Publish from the builder or Appearance Manager. Confirm the homepage renders the enabled sections, and the header links show the new menu order.
6. Edit and remove a section but do not publish; verify the live homepage still shows the published snapshot.
7. Attempt a URL outside the allowed list and verify validation blocks it.
8. As a customer, verify builder routes return 403. Confirm zero custom sections still shows a working catalog.
