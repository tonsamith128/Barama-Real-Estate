# Authentication and Authorization

## Account model

Barama Real Estate uses one canonical user identity table and role/permission mappings.

Default system roles:

- user
- developer
- administrator

Default access permissions:

- account.access
- developer.access
- dashboard.access

Public registration assigns only the user role.

Developer and administrator access is never granted from the public registration form. The CodeCanyon installer will create the first administrator from customer-supplied credentials. Later administration workflows may grant or revoke privileged roles.

## Access boundaries

/account requires authentication plus account.access.

/developer requires authentication plus developer.access.

/dashboard requires authentication plus dashboard.access.

The router executes middleware before the route controller, so a controller cannot accidentally expose a protected workspace by relying only on navigation visibility.

## Password security

Passwords use PHP password_hash() with PASSWORD_DEFAULT and are checked with password_verify(). Successful logins automatically rehash passwords when PHP's current default changes.

Password reset tokens are random 256-bit values. Only a SHA-256 hash is stored in the database. Tokens expire and are marked used after a successful reset.

## Login throttling

Failed login attempts are keyed by a one-way hash of normalized email address plus client IP. Repeated failures are temporarily locked according to application/config/auth.php.

## CSRF

State-changing authentication routes use CsrfMiddleware and the session CSRF token primitive.

## Email delivery

The current foundation uses PHP mail() through the canonical MailService. The installer must set APP_URL, MAIL_FROM_ADDRESS, and MAIL_FROM_NAME. Password-reset responses never reveal whether an account exists.

A future SMTP/provider layer may replace MailService internally without changing controllers or reset-token storage.
