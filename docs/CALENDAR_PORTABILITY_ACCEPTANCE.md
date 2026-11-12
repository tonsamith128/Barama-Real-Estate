# Barama calendar portability and physical QA acceptance

DRAFT. No external calendar import, physical device test, real customer database restore or host deployment has been performed by this milestone.

## Offline, read-only import preflight

Export an .ics file from an authorized, isolated Laravel QA installation. Keep it in a private directory with restrictive permissions. Never share real calendar bytes, customer details, session cookies or device screenshots containing personal data with GitHub, CI or ChatGPT.

Customer calendar command:

    php support/barama_calendar_import_preflight.php --file=/private/qa/viewing.ics --mode=customer --expected-events=1

Company calendar command for a verified QA company with two eligible appointments:

    php support/barama_calendar_import_preflight.php --file=/private/qa/company-viewings.ics --mode=company --expected-events=2

Independently confirm expected count against the QA workspace. Company exports cap at 250; zero is valid for an authorized company with no eligible future appointments. Customer export must contain exactly one event.

The offline verifier checks UTF-8, CRLF, 75-octet line folding, canonical calendar/event structure, unique identifiers, UTC timestamps, confirmed-only status, fixed privacy-safe descriptions, no unsolicited fields such as attendees and a bounded count. It prints only a sanitized result, count and an explicit provider-import NOT_TESTED result. It has no network or database operations and does not write files.

Synthetic offline regression tests:

    bash scripts/acceptance/calendar-import-preflight-smoke.sh

This accepts fictional Khmer content and 0/1/2 event examples; rejects injected attendees, malformed dates, duplicate IDs, damaged UTF-8, bad line endings, overlong lines and count mismatches.

## Provider / physical device review (all NOT_TESTED)

| Journey | Real iPhone with Apple Calendar | Android with Google Calendar | Desktop calendar import |
| --- | --- | --- | --- |
| Authorized customer imports one confirmed appointment | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Company owner/manager imports authorized appointments | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Time zone, UTF-8 Khmer title, punctuation render correctly | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Pending, cancelled, held and legacy records excluded | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Signed-out, unverified and revoked users denied export | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Customer private info, messages, invite recipients absent | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Changed/cancelled event needs fresh one-time download | NOT_TESTED | NOT_TESTED | NOT_TESTED |

A UTC DTSTART does not specify a meeting duration. Without DTEND/DURATION, calendar applications may display a zero-duration event or choose their own behavior. Test in actual apps; do not infer compatibility from syntax validation.

Host verification: Execute the existing read-only host acceptance probe on separate, explicitly marked, disposable aaPanel and cPanel QA installations. Capture sanitized build SHA, version, outcome and evidence hash in a private operations vault. No production database, media or webroot may be reused.

## Release decision

Synthetic CI passes do not satisfy real-device/provider imports, physical host QA, two authorized independent restored legacy database/media copies, historic financial/security governance, or stacked PR reconciliation. Maintain draft PR state and production NO-GO until those gates are independently witnessed.
