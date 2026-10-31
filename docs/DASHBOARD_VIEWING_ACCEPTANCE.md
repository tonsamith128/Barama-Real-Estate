# Laravel Admin Dashboard recovery and viewing management

**Status:** Development QA, staging verification required before any release.

## Incident

After the frontend and account upgrades, the homepage at `codecanyon.salabandet.com` works but `/dashboard` returned a generic Laravel HTTP 500. The dashboard immediately queries new tables introduced by the account upgrade: `contact_messages` and `viewing_requests`. A cPanel upload without running `php artisan migrate --force` will cause an SQL missing-table exception. This is the most likely code-based explanation; **the actual cPanel exception has not been inspected**.

The new dashboard checks for required tables individually before querying them. Missing modules are shown as an administrator-only **Database update required** notice with safe migration instructions. It does not catch or suppress any unrelated SQL exception.

## Safe cPanel verification

1. Confirm website files and MySQL database have verified backups.
2. From existing Laravel application root, run `/opt/alt/php85/usr/bin/php artisan migrate:status`.
3. If pending account migrations exist, run `/opt/alt/php85/usr/bin/php artisan migrate --force`.
4. Run `/opt/alt/php85/usr/bin/php artisan optimize:clear` and refresh `/dashboard`.
5. Confirm dashboard statistics are nonzero only where real data exists. `/dashboard/viewings` may correctly be empty with a new database.
6. If HTTP 500 persists, inspect `storage/logs/laravel.log` privately, redact customer addresses, secrets and tokens, and diagnose the first exception. Never expose the log via a public URL.
7. Never reset the database, use `migrate:fresh`, delete the installation lock, or overwrite `.env` when applying the QA overlay.

## Viewing workflow

- Customers request viewings only for publicly visible listings with a future date and receive an in-app acknowledgement. Pending requests are **not booked appointments**.
- System administrators can review all customer viewing requests in `/dashboard/viewings`.
- Approved company **owners and managers**, with active membership, can review and decide viewing requests only for their own company at `/developer/viewings`.
- Confirm/Decline decisions atomically update status, create an account notification for the customer, and write an audit event.
- Already decided or cancelled requests cannot be confirmed again. Past requests cannot be confirmed. Unrelated company members and ordinary users cannot inspect another company's requests or mutate them.
- In-app notifications are not email or SMS delivery and do not guarantee a physical viewing appointment.

## Test coverage

Feature tests exercise the missing-table recovery screen, confirmation and declines, immutability, cross-company isolation, administrator authorization, notifications, audit events and past/cancelled requests.

## Pending full-platform milestones

CodeCanyon commercial licensing and signed updater, original demo imports in production, advanced Appearance builder, billing/receipts, permissions/teams, property editing and moderation history, customer search alerts, multilingual/Khmer localization, full end-to-end cPanel browser acceptance. These are not claimed as complete.
