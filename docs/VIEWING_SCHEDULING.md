# Viewing Request Scheduling

Extended in v0.7.0-dev.

## Customer flow

A signed-in customer can request a viewing only for a currently published marketplace listing.

The request stores:

- owning customer
- listing and company
- preferred date and time window
- optional alternate date and time window
- optional customer message
- company scheduling fields
- lifecycle status

Only one active request per customer/listing is allowed.

Customer cancellation is permitted from:

- pending
- confirmed
- rescheduled

Customer cancellation is recorded in entity_status_history with the customer as actor.

## Developer workspace

Company members can access viewing requests only through active membership of the owning company.

Developer routes:

- /developer/viewings
- /developer/manage/viewing/{id}
- POST /developer/viewings/{id}

The list supports search, status filtering, sorting, and pagination through the existing workspace read model.

The detail screen provides the customer, property, requested preferences, scheduled appointment, company note, and complete viewing history.

## Company lifecycle

Company-side transitions are intentionally bounded:

- pending -> confirmed
- pending -> rescheduled
- pending -> declined
- confirmed -> rescheduled
- confirmed -> completed
- confirmed -> declined
- rescheduled -> confirmed
- rescheduled -> rescheduled
- rescheduled -> completed
- rescheduled -> declined

Confirmed and rescheduled states require a future scheduled date/time.

Declining clears scheduled_for. Completing preserves the scheduled appointment for historical context.

## Concurrency and audit

Company updates include the expected current status in the SQL update.

If another staff member changes the request after the page was loaded, the stale update fails rather than silently overwriting the newer decision.

The status update and entity_status_history entry run in the same database transaction.

## Ownership

ViewingManagementService resolves the viewing's company and uses CompanyAccessService before any mutation.

A developer from another company cannot read or mutate the viewing request.

No separate appointment table or parallel customer workflow is introduced.
