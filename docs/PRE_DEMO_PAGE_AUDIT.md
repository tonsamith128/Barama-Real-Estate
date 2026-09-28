# Barama Real Estate — Pre-Demo Page-by-Page Audit

The demo site must not be reinstalled until every mandatory surface below is accepted.

## Status model

- `TODO` — not audited yet
- `CODE READY` — source/UI correction committed; visual/live verification still required
- `ACCEPTED` — responsive, Light/Dark, functional, route and regression checks passed

## Global constraints

- Keep one canonical `assets/css/app.css` and one canonical `assets/js/app.js`.
- No Node.js production dependency.
- Public pages use the global public header/footer.
- Auth pages remain standalone and must not render the global header/footer.
- Account, Developer and Admin workspaces use their own workspace shell, never the public header/footer.
- Guest Sign in/Create account stay inside the user-icon dropdown.
- Use clean routes; do not reintroduce query-routing links.
- Preserve Light/Dark behavior, keyboard focus, responsive layouts and existing security middleware.
- Featured cards must be premium, concise, non-flat, and use restrained transparent modern-color surfaces.
- Do not reinstall the test/demo site until this tracker is fully accepted.

## Audit sequence

| # | Surface | Route(s) | Status | Notes |
|---:|---|---|---|---|
| 0 | Shared public shell | Header / footer / mobile nav | CODE READY | Guest legal links repaired; Legal footer group added; mobile footer kept two-column. |
| 1 | Homepage | `/` | CODE READY | Search-first structure preserved; full Hero background supports Plain/Color/Image/Slider/Video; independent Hero/Search/Summary/Quick Discovery/section toggles added. |
| 1A | Homepage Manager | `/dashboard/homepage` | CODE READY | Transparent premium feature table; every homepage feature uses ON/OFF switch + Configure dialog; uploaded hero media is stored in Media Library. |
| 1B | Navigation Manager | `/dashboard/navigation` | CODE READY | Split from Homepage; add/edit/delete/reorder/toggle/configure functions; safe internal-path validation and last-enabled protection. |
| 1C | Appearance Manager | `/dashboard/appearance` | CODE READY | 17-module global frontend design system with independent toggles/configure dialogs; CSP-safe dynamic CSS wired to all public/auth pages; operational workspaces excluded. |
| 2 | Property marketplace | `/properties` | CODE READY | Filter state/counts repaired; search accessibility fixed; filtered SEO guard completed; result cards refined. |
| 3 | Property detail | `/properties/{slug}` | CODE READY | Price-on-request fallback fixed; summary completed with bedrooms/bathrooms/parking/furnishing; contain-fit image/video/YouTube media and 2–6 related-card density verified. |
| 4 | Projects marketplace | `/projects` | CODE READY | Search accessibility fixed; active filter chips added; filtered result state and pagination labeling aligned with Properties. |
| 5 | Project detail | `/projects/{slug}` | TODO | Featured media, business information, publisher contact, related projects. |
| 6 | Companies directory | `/company` | TODO | Search/filter, company cards, responsive directory. |
| 7 | Company detail | `/company/{slug}` | TODO | Featured media, company information, publisher contact, properties/projects. |
| 8 | Public CMS + legal | `/pages/{slug}` | TODO | Privacy, Terms, Disclaimer and other managed pages. |
| 9 | Authentication | `/login`, `/register`, `/forgot-password`, reset/verify routes | TODO | Standalone premium shell; no public header/footer; password UX and validation. |
| 10 | Customer account | `/account/**` | TODO | Workspace header/sidebar, profile, activity, saved/viewing/inquiry/settings/privacy flows. |
| 11 | Developer / Company | `/developer/**` | TODO | Workspace shell, listings, projects, properties, leads, team, subscription/settings. |
| 12 | Admin dashboard | `/dashboard/**` | TODO | Shared admin shell followed by module-by-module audit. |
| 13 | Errors | 404 / 500 | TODO | Global layout rules, useful recovery actions, no raw errors. |
| 14 | Installer | `/installer/` | TODO | Exactly five steps, license flow, SVG fields, responsive Light/Dark, no server-config edits. |
| 15 | Updater | `/updater/` | TODO | Re-auth, license, backup/rollback, migration and downgrade protection. |
| 16 | Final customer package | ZIP + fresh demo install | TODO | Exact-package QA only after all pages above are ACCEPTED. |

## Current release boundary

The page audit branch is based on the clean Communications v1.19.2 candidate commit:

`00ad5729aab91b995a9b02fa01d1edf79cebe1af`

Branch:

`fix/pre-demo-page-audit`

The Communications release PR remains separate and must not be merged/deployed merely because page cleanup has started.
