# Developer and Admin Workspace UI

Introduced in v0.5.0-dev.

## Purpose

The management UI is a server-rendered PHP workspace layered on the existing service and repository architecture.

It does not introduce a second frontend application, a Node.js build requirement, page-specific stylesheets, or page-specific JavaScript bundles.

## Canonical surfaces

Developer workspace:

- /developer
- /developer/company
- /developer/projects
- /developer/properties
- /developer/listings
- /developer/inquiries
- /developer/leads
- /developer/team
- /developer/media

Admin workspace:

- /dashboard
- /dashboard/approvals
- /dashboard/companies
- /dashboard/projects
- /dashboard/properties
- /dashboard/listings
- /dashboard/inquiries
- /dashboard/leads
- /dashboard/catalog
- /dashboard/media

Both areas use the same reusable workspace view and the same canonical application layout.

## Read boundaries

WorkspaceReadRepository is read-only.

Developer queries always scope company-owned resources through active company membership. The developer media list currently shows media uploaded by the authenticated user; attachment mutations remain company-scoped in MediaStorageService.

Admin queries provide platform-wide operational visibility and the moderation queue.

Search is server-side and paginated. Search terms are bound parameters and section/table choices are selected only from internal match statements.

## Write boundaries

Forms never write SQL directly.

Management forms submit to the v0.4.0-dev service endpoints already protected by:

- authentication
- permissions
- CSRF
- company membership scope
- lifecycle transition rules
- media validation

The canonical app.js progressively enhances those forms with same-origin fetch requests, status notices, and refresh after success.

## UI rules

- left sidebar is the only workspace section navigation
- responsive mobile sidebar drawer
- compact KPI surfaces
- clean tables with search and pagination
- inline lifecycle actions
- Light/Dark Mode through the existing theme system
- one first-party stylesheet: assets/css/app.css
- one first-party JavaScript file: assets/js/app.js

The workspace intentionally avoids large decorative dashboard cards around entire sections.
