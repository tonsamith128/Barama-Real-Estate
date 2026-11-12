#!/usr/bin/env bash
# Offline calendar acceptance. Fake generated fixtures only, no real bookings.
set -Eeuo pipefail
set +x
umask 077
ROOT="$(cd -- "$(dirname -- "$0")/../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT

python3 - "$TMP" <<'PY'
from pathlib import Path
import sys

root = Path(sys.argv[1])
description = ("DESCRIPTION:Confirmed property viewing. Check your Barama account "
               "for details. Duration and meeting location are not specified.")

def fold(line):
    out = []
    while len(line.encode('utf-8')) > 75:
        encoded = line.encode('utf-8')
        cut = 75
        while True:
            try:
                part = encoded[:cut].decode('utf-8')
                break
            except UnicodeDecodeError:
                cut -= 1
        out.append(part)
        line = ' ' + encoded[cut:].decode('utf-8')
    out.append(line)
    return out

def make(count=1):
    lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0',
        'PRODID:-//Barama Real Estate//Viewing Calendar//EN',
        'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'X-WR-CALNAME:Barama confirmed viewings',
    ]
    for i in range(count):
        lines.extend([
            'BEGIN:VEVENT', 'UID:' + f'{i+1:064x}' + '@calendar.invalid',
            'DTSTAMP:20310101T120000Z', 'DTSTART:20310201T130000Z',
            'STATUS:CONFIRMED',
            'SUMMARY:Viewing - ' + ('Khmer ផ្ទះស្អាត\, test\; ' * 8),
            description, 'END:VEVENT',
        ])
    lines.append('END:VCALENDAR')
    return ('\r\n'.join(y for x in lines for y in fold(x)) + '\r\n').encode('utf-8')

valid = make()
(root / 'valid.ics').write_bytes(valid)
(root / 'company.ics').write_bytes(make(2))
(root / 'empty-company.ics').write_bytes(make(0))
(root / 'injected.ics').write_bytes(valid.replace(
    b'END:VEVENT\r\n', b'ATTENDEE:mailto:example@example.invalid\r\nEND:VEVENT\r\n'
))
(root / 'lf-only.ics').write_bytes(valid.replace(b'\r\n', b'\n'))
(root / 'duplicate.ics').write_bytes(make(2).replace(
    ('UID:' + f'{2:064x}').encode(), ('UID:' + f'{1:064x}').encode()
))
(root / 'bad-date.ics').write_bytes(valid.replace(
    b'20310201T130000Z', b'20310231T130000Z'
))
(root / 'oversize-line.ics').write_bytes(valid.replace(
    b'SUMMARY:Viewing -', b'SUMMARY:' + b'X' * 76
))
(root / 'invalid-utf8.ics').write_bytes(valid.replace(
    b'BEGIN:VCALENDAR', b'BEGIN:VCALENDAR\xff'
))
PY

php "$ROOT/support/barama_calendar_import_preflight.php" --file="$TMP/valid.ics" \
    --mode=customer --expected-events=1 | grep -Fx 'CALENDAR_IMPORT_PREFLIGHT=PASS'
php "$ROOT/support/barama_calendar_import_preflight.php" --file="$TMP/company.ics" \
    --mode=company --expected-events=2 | grep -Fx 'VERIFIED_EVENT_COUNT=2'
php "$ROOT/support/barama_calendar_import_preflight.php" --file="$TMP/empty-company.ics" \
    --mode=company --expected-events=0 | grep -Fx 'VERIFIED_EVENT_COUNT=0'

for case in injected lf-only duplicate bad-date oversize-line invalid-utf8; do
    if php "$ROOT/support/barama_calendar_import_preflight.php" \
        --file="$TMP/$case.ics" --mode=customer --expected-events=1 \
        > "$TMP/result.txt"; then
        echo 'FAILED=UNSAFE_CALENDAR_ACCEPTED'; exit 1
    fi
    grep -Fx 'CALENDAR_IMPORT_PREFLIGHT=FAIL' "$TMP/result.txt" >/dev/null
done

if php "$ROOT/support/barama_calendar_import_preflight.php" \
    --file="$TMP/valid.ics" --mode=company --expected-events=2 \
    > "$TMP/result.txt"; then
    echo 'FAILED=INCORRECT_COUNT_ACCEPTED'; exit 1
fi
grep -Fx 'REASON=EXPECTED_EVENT_COUNT_MISMATCH' "$TMP/result.txt" >/dev/null

if php "$ROOT/support/barama_calendar_import_preflight.php" \
    --file="$TMP/valid.ics" --mode=customer --expected-events=0 \
    > "$TMP/result.txt"; then
    echo 'FAILED=INVALID_CUSTOMER_COUNT_ACCEPTED'; exit 1
fi

echo 'CALENDAR_IMPORT_PREFLIGHT_SMOKE=PASS'
