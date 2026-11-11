# Barama Real Estate — Viewing Lifecycle and Private Customer Recovery

**Workstream:** stacked Laravel 13 development branch, on top of draft PR #191.
**As of:** November 11, 2026.
**Release status:** DRAFT / NO-GO. Original Pure PHP production remains unchanged.

## Reused architecture

This milestone extends existing `viewing_requests`, `ViewingDecisionService`,
Customer Account, Developer and Admin viewings controllers, original workspace
routes, and Blade layouts. No second, unrelated booking platform was built.

### State transitions

| Current status | Actor | Action | Result |
|---|---|---|---|
| Pending | Active company owner/manager or admin | Confirm | Confirmed, customer notified locally |
| Pending | Active company owner/manager or admin | Decline | Declined, customer notified locally |
| Pending or confirmed | Active company owner/manager or admin | Propose new time | Reschedule proposed, customer must accept |
| Reschedule proposed | Owning customer | Accept before expiry | Confirmed at proposed time |
| Reschedule proposed | Owning customer | Decline | Cancelled; company notified locally |
| Pending / confirmed / reschedule proposed | Owning customer, authorized company or admin | Cancel | Cancelled |
| Any, including historical review | System admin | Place/release review hold | Audit-only; no activation |
| Historical `legacy_review` | Anyone | Confirm, reschedule, cancel | **Denied** |

Proposals must be between 30 minutes and six months in the future. A proposed
time expires after 72 hours and does not modify the confirmed/requested time
until the customer explicitly accepts. Declining a new proposal cancels
the viewing; it does not silently reconfirm a stale original time. Expired
proposals cannot be accepted. Customer cancellation remains available while
an administrative review hold is in force.

All transitions acquire a row lock on the current viewing record and write
an append-only `viewing_events` entry plus internal audit evidence in the same
database transaction. Local account notifications are stored in the database;
**no external email, SMS, payment or calendar booking is implied or activated**.

Company writes require an **active owner/manager membership in an approved
company**, rechecked inside the transaction. Cross-company record IDs are
hidden with 404 responses. Customer updates require the original account
owner. System administrators can flag/clear review holds with reasons; holds
block ordinary company approvals and customer reschedule acceptance.

## Historical lead and inquiry parity

The original Pure PHP schema allows company-only inquiries and contact details
that are absent from the current native Laravel inquiry contract. To prevent
information loss and accidental outreach, the isolated import writes **all**
original `inquiries` and `leads` to two new private tables:

- `legacy_customer_inquiries`: historical UUID, associated company/project/
  property/listing, original user and assignee, contact preferences, subject,
  message, original status, response/closure timestamps.
- `legacy_customer_leads`: historical UUID, company, linked archived inquiry,
  assignee, preferred location, contact and budget, source/stage, follow-up
  dates, won/lost dates, notes and prior deletion state.

The original-to-target ID mapping remains in `legacy_identity_maps`.
Unrelated UUIDs, property assignments and customer identifiers must never be
guessed. The independent read-only reconcile job checks actual mapped
relationships, not just aggregate counts. Original status and customer
contacts remain private: **no imported lead becomes a live CRM action and
no imported inquiry becomes a public/in-app conversation**.

Administrators have a separate historical customer review queue under
`/dashboard/customer-recovery`, with status filters, restricted access,
review notes and `audit_events`. Review choices are evidence only:
`reviewed` or `needs_followup` are not permission to contact customers.
Only independently authorized, privacy-compliant workflows may activate
customer communications later.

Both new migrations forbid destructive rollback when imported historical
evidence or recorded viewing decisions exist.

## Acceptance matrix

The branch's synthetic CI verifies:
- Laravel authorization: customer ownership, active company membership,
  forbidden cross-company edits and revoked company access.
- Confirm/decline, customer-accepted reschedule and refusal of duplicate
  or expired acceptance, cancellation, review holds and releases.
- Imported `legacy_review` records cannot be confirmed or cancelled through
  the new workflow, even after administrative review.
- Original Pure PHP customer fixtures: historical saved searches, comparisons,
  viewing requests, leads and linked **company-only** inquiries with null
  contact email.
- Isolated source/target MySQL mapping, status quarantine, archived company/
  customer relationships, zero live inquiry activation and private media hashes.
- **Eleven** additive Laravel migrations with isolated SQL backup,
  rollback/reapply, restoration and QA installer tests.

The code-level CI is not evidence of compatibility with actual production
customer records. Keep all relevant encrypted original backups unchanged.

## Still outstanding

1. Authorized, encrypted disposable clone of the **real** Pure PHP database
   and private media, with end-to-end customer/permissions/UUID reconciliation.
2. Some historical financial, payments, tax, licensed media, security-factor
   and notification history still lack accepted conversion adapters; none
   may be discarded or silently activated.
3. Physical aaPanel Nginx/PHP-FPM and cPanel/LiteSpeed hosting acceptance,
   installer and encrypted backup/restore rehearsals.
4. Physical iPhone, Android and tablet testing.
5. Reconciliation of independent geography PR #184, earlier catalog PR
   #182/#183 and stacked drafts #185–#192, with no lost features.
6. Owner review, privacy/legal acceptance and explicit production release
   authorization.

**No production merge, deployment, backup retrieval, email, calendar booking
or migration against the original Pure PHP database is authorized here.**
