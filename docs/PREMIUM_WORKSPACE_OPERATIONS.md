# Premium Workspace Operations — Admin, Developer and Account

**Development scope:** Laravel 13, no new framework, no schema migration,
no payment processing, no production deployment.

## Administration dashboard

- Review queue for pending projects, listings, inquiries, viewing requests
  and unread contact requests, linked to existing moderation pages.
- Selectable 7-day or 30-day inquiry/viewing activity counts derived from
  the database. The day selector is allowlisted.
- Latest pending project/listing cards and upcoming pending-viewing cards.
- Optional-table schema guard retained to avoid 500s on code-first upgrades.

## Developer dashboard

- Portfolio totals for actively affiliated companies, projects, properties
  and pending listing submissions.
- Company cards showing role, approval status and per-company record counts.
- Management actions only for approved-company active owners/managers;
  team management only for active owners.
- Pending inquiry/viewing counts and recent inquiry list restricted to
  approved companies the user actively manages. Read-only members cannot
  see another company's leads.
- Bounded/paginated results and eager-loaded per-company counts.

## Customer account

- Upcoming pending/confirmed viewing appointments (future only).
- Recent unread notifications, scoped to the authenticated user.
- POST /account/notifications/read-all marks only the current user's unread
  notifications as read. It is idempotent and rate-limited; web middleware
  enforces CSRF and authenticated/verified account access.
- Existing saved homes, inquiries and saved-search workflows are preserved.

## Release acceptance

CI: php syntax; Laravel feature tests for administrator authorization,
data isolation, role boundaries and notification idempotency; Vite build;
Laravel-only source guard. Real browser/mobile checks and aaPanel/cPanel
shared-hosting upgrades remain separate release gates.

Do not copy the QA package over any existing customer installation.
