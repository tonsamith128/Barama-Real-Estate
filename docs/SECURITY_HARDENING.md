# Security Hardening

Barama Real Estate uses one canonical account identity model for customers, company/vendor users, and administrators.

## Email verification

Public registration creates a customer account and queues a one-time email verification link.

- verification tokens are random 32-byte values
- only SHA-256 token hashes are stored
- tokens are bound to the current account email
- tokens expire after the configured verification window
- recently-issued tokens are rate-limited before resend
- email verification is required before first-company onboarding
- email verification is required before accepting a company invitation
- administrator promotion is blocked for unverified accounts

The installer-created administrator is verified during installation because the buyer supplies the administrator email directly during the protected installer flow.

## Password policy

Registration, password reset, and account password change all use the same PasswordPolicy.

The release rule is at least 12 characters with uppercase, lowercase, number, and symbol.

## Session invalidation

users.auth_version is the canonical server-side session invalidation counter.

Browser sessions store:

- authenticated user ID
- auth_version
- login timestamp
- last-activity timestamp

A session is rejected when its auth_version no longer matches the user record, the account is not active, the idle lifetime has expired, or the absolute lifetime has expired.

Credential changes and administrator security actions advance auth_version. This invalidates older sessions without introducing a separate session database.

## Security audit

security_audit_events records security-sensitive activity, including authentication, email verification, password changes, administrator role changes, and administrator account-status changes.

Raw IP addresses and raw user-agent strings are not stored. When available, they are stored as SHA-256 hashes. Event metadata is bounded and JSON encoded.

Audit rows are append-only through the application service layer.

## Administrator safety

Administrator user management is part of the existing Admin Dashboard.

Safeguards include:

- an administrator cannot suspend or deactivate their own account
- an administrator cannot remove their own administrator role
- the final active administrator cannot be disabled
- the final active administrator role cannot be removed
- unverified accounts cannot be promoted to administrator
- status and administrator-role changes invalidate the target user's existing sessions

## Release validation

tools/security-hardening-smoke.php verifies the security lifecycle against MySQL.

The exact customer ZIP validation also confirms that the release security schema and verification/admin-management code are present after a clean installer run.
