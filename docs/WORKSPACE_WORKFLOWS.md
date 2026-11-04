# Workspace Workflows — Property Management, Company Access & Private Messages

**Implementation:** Laravel 13, native Blade and grouped admin/frontend CSS.
**Status:** development milestone; not a customer deployment.

## Developer property management
- Company-scoped search by property title and reference code.
- Allowlisted status filtering for draft, pending review, approved and rejected.
- Paginated results keep query parameters. Cross-company results cannot appear.
- Editing, approval and publication continue to use the existing validated
  state transitions and administrator review.
- No property ownership or status change is authorized through GET filters.

## Customer conversations
- Registered customers can send and read private replies to inquiries they own.
- Active company owners/managers can reply to inquiries belonging to approved
  companies they manage. Members and unrelated companies cannot read threads.
- Administrators can review and reply to all inquiries after super-admin checks.
- Inquiry messages are stored in inquiry_messages with sender, inquiry and timestamps.
- Sending a manager/admin reply sets the inquiry to responded. A customer reply
  reopens the inquiry as new for company/admin attention.
- Original guest inquiries remain reviewable but do not support in-app replies,
  because they have no authenticated recipient account.
- Message bodies are escaped by Blade; validation enforces 2–3000 characters.
- Writes are transactional and recorded in audit_events without copying
  message content into the audit log.

## Notification delivery
- New inquiry replies create durable account_notifications rows for the linked
  customer or active owner/manager recipients.
- Verified company member assignment, role changes and deactivation create
  an in-app notification for the affected account. Unchanged reassignments
  do not issue duplicate notifications.
- Notifications are not delivered externally by email, SMS, push or webhook.
  Those provider integrations require separate credential, consent, deliverability
  and retry/dead-letter acceptance. Do not claim external delivery.
- User access to notifications remains protected by account ownership checks.

## Deployment and acceptance
- Run only additive Laravel migration 2026_11_04_000001_create_inquiry_messages.php
  after full database backup and upgrade rehearsal.
- Existing customer systems are not altered by GitHub commits.
- Source guards, syntax checks, Laravel permission tests and asset builds
  are mandatory before merging.
- Real aaPanel/cPanel installation, upgrading and mobile browser acceptance
  remain independent release gates.
