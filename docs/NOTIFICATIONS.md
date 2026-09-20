# Notification & Alert Delivery Infrastructure

Introduced in v0.8.0-dev.

## Architecture

Barama uses one canonical notification subsystem:

- notification_preferences — per-user channel and event controls
- notifications — durable in-app inbox records
- email_outbox — retryable email delivery records
- saved_search_matches — prevents the same listing from being alerted repeatedly for one saved search

Web requests queue delivery records. They do not wait for SMTP.

## Notification categories

Current user-controlled categories:

- saved_search_alerts
- viewing_updates
- inquiry_updates

Users can independently enable or disable:

- in-app notifications
- email notifications
- each event category

If a category is disabled, both in-app and email delivery for that category are suppressed.

## Event integration

The canonical workflows emit notifications transactionally.

New marketplace inquiry:
- active members of the owning company are notified

Inquiry replied/closed:
- a signed-in customer receives in-app/email according to preferences
- a guest inquiry with a valid contact email receives a transactional email update

Viewing requested:
- active company members are notified

Viewing confirmed/rescheduled/completed/declined:
- the customer is notified

Viewing cancelled by customer:
- active company members are notified

Notification/outbox inserts happen in the same database transaction as the corresponding domain mutation.

## Saved-search digests

Daily and weekly saved searches are processed by SavedSearchAlertService.

The service reuses MarketplaceRepository filters instead of implementing another listing query.

Matching criteria include:

- keywords
- sale/rent
- property type
- location
- minimum bedrooms
- minimum/maximum price
- currency

Each newly matched listing is recorded in saved_search_matches.

A digest is queued only when previously unseen listings are found. Re-running the job is therefore idempotent and does not repeatedly notify the same search/listing pair.

## Email outbox

Outbox records move through:

- pending
- processing
- sent
- failed

Workers claim a record atomically before sending.

Failed records receive a delayed retry time. A stale processing lock can be reclaimed after MAIL_PROCESSING_STALE_MINUTES.

Default retry controls:

- MAIL_MAX_ATTEMPTS=5
- MAIL_RETRY_MINUTES=10
- MAIL_PROCESSING_STALE_MINUTES=15

## Mail drivers

MAIL_DRIVER supports:

- smtp — production SMTP transport
- mail — PHP mail() compatibility
- log — deterministic testing/development delivery

SMTP settings:

- MAIL_HOST
- MAIL_PORT
- MAIL_ENCRYPTION: tls, ssl, none
- MAIL_USERNAME
- MAIL_PASSWORD
- MAIL_TIMEOUT
- MAIL_FROM_ADDRESS
- MAIL_FROM_NAME

The SMTP transport supports AUTH LOGIN and TLS/SSL without a production Node.js or Composer dependency.

Do not use the log driver for production customer delivery.

## Cron entrypoint

Use the PHP CLI entrypoint:

php tools/notifications.php run --limit=100

This performs due saved-search matching and then drains the email outbox.

Other supported commands:

php tools/notifications.php alerts daily --limit=100
php tools/notifications.php alerts weekly --limit=100
php tools/notifications.php alerts all --force --limit=100
php tools/notifications.php outbox 100

The --force option bypasses the daily/weekly due-time check, but saved_search_matches still prevents duplicate property alerts.

The CLI uses a MySQL advisory lock so overlapping cron invocations exit cleanly instead of processing the same job concurrently.

A typical production cron can run every 5–15 minutes. Due-time rules decide whether a daily or weekly saved search should actually be processed.

## Customer account

The account workspace includes:

- /account/notifications

Customers can:

- review notification history
- see unread state
- mark one notification read
- mark all notifications read
- control in-app/email channels
- enable/disable saved-search, viewing, and inquiry updates

All mutations require authentication, account.access, and CSRF validation.

## CodeCanyon deployment note

The five-step installer collects mail delivery settings inside Step 4 Website and requires a successful test email before browser installation can complete.

SMTP credentials are persisted only in protected runtime configuration after installation. The installer session stores only a fingerprint of the tested mail configuration, not the SMTP password.

The customer still receives the simple CLI command below for scheduled notification delivery:

php tools/notifications.php run --limit=100

Real provider delivery and hosting cron configuration are validated during final live-server acceptance.
