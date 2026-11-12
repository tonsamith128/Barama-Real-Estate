# Laravel 13 company review and notification parity

## Legacy reference
Exact historical Git blobs retrieved from the connected repository:
- `ApprovedCompanyMediaEditPolicy.php` (`7302b6c4332150b60568f2aad0d461a4cac6298f`)
- `CompanyWorkflowNotificationService.php` (`894b375988da186f406c8004543b05bc5bc229ef`)

The 2026-11-10 owner-run corrected aaPanel audit found no literal references
to three **separate** live updater worker/receipt files. It does **not**
prove they are unused. They remain in the installed Pure PHP site unchanged.

## Implemented (independent Laravel development branch)
- A dedicated `CompanyReviewService` makes **pending → approved/rejected**
  decisions under an administrator-only, row-locked DB transaction.
- Each successful transition writes a `moderation_decisions` record and
  an `audit_events` record with the authenticated reviewer.
- `CompanyWorkflowNotifier` records in-app alerts to **active, email-verified
  company owners** only, inside the same transaction. Managers, deactivated
  members, unverified accounts and unrelated owners receive nothing.
- Notifications deduplicate per recipient and moderation decision via a
  nullable `dedupe_key`, retaining all existing account notification rows.
- The existing Admin Catalog workspace gains a pending-company review queue,
  forms with CSRF protection, reason validation and an administrator-only
  decision route; frontend page layouts remain untouched.
- Tests cover authorized approval, rejection, history, role isolation,
  notification isolation, reason validation, replay conflicts and deduplication.

## Deliberately not included
- No rewrite, removal, migration or deployment of the installed Pure PHP
  updater or its five extra files.
- No generic override for editing approved company media. The legacy special
  case explicitly required multiple administrative capabilities; Laravel's
  current root-administrator check is not an equivalent full RBAC system.
  Implement company-specific media corrections only after creating explicit,
  testable `company.manage` and `media.manage` authorization policies,
  approved media attachment rules and revision history.
- No automatic pending-company submission endpoint; existing Admin
  `Create and approve company` functionality is left unchanged.
- No outbound email; only durable in-app account notifications.
- No new CSS entry point or redesign of approved frontend layouts.

## Release requirements
1. GitHub PHP syntax, Laravel structure, PHPUnit, installer and asset build.
2. Database migration rehearsal on isolated MySQL 8 and MariaDB (NULL unique
   index compatibility, rollback and existing notification preservation).
3. Review of per-company owner authorization, concurrent approval conflicts
   and notification deduplication under concurrent requests.
4. Browser and mobile admin UI acceptance, company onboarding workflow design.
5. Legacy database/UUID/media migration dry run; encrypted backups, host-level
   rollback and explicit release approval.

**Draft only. No deployment to the live Pure PHP installation.**
