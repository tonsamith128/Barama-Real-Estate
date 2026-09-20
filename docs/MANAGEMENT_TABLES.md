# Management Tables and Bulk Moderation

Extended in v0.6.0-dev.

## Server-side filters

Developer and Admin management tables support section-aware filters without duplicating section queries.

Depending on the section, controls can include:

- status or pipeline stage
- resource / offer / company / project / property type
- location
- sort order
- text search
- pagination

The query layer wraps the existing scoped read query and applies only internally allow-listed columns and sort clauses. User values remain prepared parameters.

Developer filters remain inside active company membership scope.

## Sorting

Allow-listed sort choices include, where relevant:

- recently updated / oldest updated
- name or title A–Z / Z–A
- price low / high
- budget low / high
- newest / oldest inquiry
- newest / oldest media

Unsupported sort values fall back to the section's safe default.

## Bulk moderation

The Admin approval queue supports selecting multiple pending:

- companies
- projects
- properties
- listings

One request is limited to 50 unique records.

Available decisions are:

- approve
- reject

Approval processing uses dependency order:

1. company
2. project
3. property
4. listing

A listing approval transitions directly to published, matching the existing initial listing workflow. Other resources transition to approved.

Each selected item still goes through DomainWorkflowService, so dependency checks, actor authorization, transition rules, and status history are not bypassed.

Bulk processing reports succeeded and failed counts. A failure on one selected record does not hide or misreport successful records.

## Progressive enhancement

Selection counting, select-all state, and rejection confirmation live in the existing canonical assets/js/app.js.

No page-specific JavaScript or stylesheet was added.
