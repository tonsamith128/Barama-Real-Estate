# Barama viewing calendar downloads — draft feature

This milestone builds on the confirmed/rescheduled viewing workflow in PR #192.

- A verified customer may download one **current confirmed** appointment under their account at /account/viewings/{viewing}/calendar.
- An active company owner/manager may download up to 250 upcoming **confirmed** viewings from their authorized companies at /developer/viewings/calendar.
- At read time, both exports require a published, currently visible listing, an approved parent property and visible company, no administrator hold, no historical legacy UUID, and a scheduled date within six months.
- Pending appointments and proposed alternate times are not confirmed appointments. They are never exported.
- The exported calendar deliberately has no location/duration, customer contact details, notes, private links or calendar invite recipients. The property title is UTF-8-safe, RFC 5545-escaped and folded. Downloads are private/no-store and require signed-in, email-verified accounts.
- Export does not make a standing subscription or public ICS URL. Updating or cancelling an appointment requires re-downloading the calendar item; existing calendar app events do not synchronize automatically.
- No new migration, notifications, cron, queue, payment, media or server changes. Frontend page layouts are retained; only small download links are added to existing workspace pages.

## Acceptance

Run the new test:

    php artisan test --filter=ViewingCalendarExportTest

Then run existing viewing lifecycle, quality and isolated-hosting CI. The PR remains a **draft**, stacked on PR #193. All real database/private-media rehearsal, physical hosting/device checks, reconciliation of stacked branches and finance/security release gates remain outstanding.

No live site, production database or original Pure PHP files were changed.

## Calendar download regression coverage

- Tests protect customer ownership, approval-state revocation, held or withdrawn events, historical imported records and cross-company authorization.
- Company exports are deterministically ordered by viewing time and numeric viewing ID, with a maximum of 250 records. Any additional appointments are not included in the one-time file.
- Calendar event text must never include customer PII, private messages or embedded attendee invitations.
- Download actions explicitly say **Download calendar (.ics)**. Neither endpoint automatically writes to Google Calendar, Apple Calendar, Outlook or other external calendars; the user imports the file and must import a fresh file if the viewing changes.

## Portability preflight and manual-device gates

A local, read-only file verifier is available in support/barama_calendar_import_preflight.php. Synthetic acceptance cases live in scripts/acceptance/calendar-import-preflight-smoke.sh. See docs/CALENDAR_PORTABILITY_ACCEPTANCE.md for the private operator test commands and manual Apple/Google Calendar import evidence matrix. A preflight pass is not a provider or physical device import.
