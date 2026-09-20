# Domain Write and Management Infrastructure

Introduced in v0.4.0-dev.

## Architectural rule

HTTP controllers do not perform SQL and do not decide company ownership.

Controllers validate transport-level input, rely on route middleware for global authentication/permission gates, and delegate to application services.

Application services enforce:

- company membership and ownership scope
- entity validation
- canonical lifecycle transitions
- public inquiry target availability
- inquiry throttling
- media MIME/size validation
- safe private storage
- inquiry-to-lead conversion rules

Repositories are the only layer that directly persists domain records.

## Management endpoints

Developer mutation endpoints are under /developer and require authentication, the relevant permission, and CSRF protection.

Admin mutation endpoints are under /dashboard and require authentication, administrator permissions, and CSRF protection.

The public inquiry endpoint is POST /inquiries and requires a CSRF token. It also uses a honeypot field named website plus an IP-derived one-way throttle key.

These endpoints currently return JSON so future server-rendered forms or progressive JavaScript interfaces can use the same canonical write services without duplicating business rules.

## Workflow history

Every status/stage transition writes an entity_status_history record containing:

- entity type
- entity ID
- actor user ID
- previous state
- new state
- optional note
- timestamp

This history is append-only at the application layer.

## Company isolation

Global developer permissions are not sufficient to mutate a company resource.

The authenticated user must also be an active member of the owning company. Company owner or manager membership is required to manage team membership.

Administrators bypass company membership scope only through their explicit administrator role.

## Media security

Uploaded media is not stored under the public assets tree.

MediaStorageService verifies:

- PHP upload status
- real MIME type using finfo
- configured allow-list
- maximum file size
- image integrity and dimensions for image types
- unguessable generated filename
- SHA-256 checksum

Files are stored under application/storage/uploads/private/YYYY/MM and Apache rules deny direct access to application, tools, docs, and repository metadata.

Nginx protection will be generated/validated by the final installer/deployment layer. No public media delivery route is exposed in this phase.

## Editing policy

Projects and properties can currently be edited by developers only while draft or rejected. Listings can be edited while draft, rejected, or paused.

This deliberately avoids silently changing already-approved marketplace content. A later revision workflow can support post-publication edits with re-approval without weakening the canonical approval history.
