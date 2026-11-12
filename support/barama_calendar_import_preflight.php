<?php

declare(strict_types=1);

/**
 * Read-only calendar import preflight for a private QA .ics download.
 * No network access, app bootstrap, customer data output or file writes.
 * Provider and physical device imports still require manual acceptance.
 */
final class BaramaCalendarImportPreflight
{
    private const DESCRIPTION = 'DESCRIPTION:Confirmed property viewing. Check your Barama account for details. Duration and meeting location are not specified.';

    /** @return array{int, string} */
    public static function verify(string $content, string $mode, int $expected): array
    {
        self::ensure(in_array($mode, ['customer', 'company'], true), 'MODE_INVALID');
        self::ensure($expected >= 0 && $expected <= 250, 'EXPECTED_COUNT_INVALID');
        self::ensure($mode !== 'customer' || $expected === 1, 'CUSTOMER_COUNT_INVALID');
        self::ensure($content !== '' && strlen($content) <= 2097152, 'FILE_SIZE_INVALID');
        self::ensure(mb_check_encoding($content, 'UTF-8'), 'UTF8_INVALID');
        self::ensure(str_ends_with($content, "\r\n"), 'CRLF_REQUIRED');
        self::ensure(preg_match('/(?<!\r)\n|\r(?!\n)/', $content) === 0, 'CRLF_INVALID');

        $physical = explode("\r\n", substr($content, 0, -2));
        $logical = [];
        foreach ($physical as $line) {
            self::ensure($line !== '' && strlen($line) <= 75, 'LINE_FOLDING_INVALID');
            self::ensure(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line) === 0, 'CONTROL_CHARACTER_INVALID');
            if ($line[0] === ' ' || $line[0] === "\t") {
                self::ensure(count($logical) > 0, 'FOLD_WITHOUT_PARENT');
                $logical[count($logical) - 1] .= substr($line, 1);
            } else {
                $logical[] = $line;
            }
        }

        $header = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Barama Real Estate//Viewing Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Barama confirmed viewings',
        ];
        self::ensure(array_slice($logical, 0, count($header)) === $header, 'CALENDAR_HEADER_INVALID');

        $cursor = count($header);
        $count = 0;
        $uids = [];
        while (($logical[$cursor] ?? '') === 'BEGIN:VEVENT') {
            self::ensure($count < 250, 'EVENT_LIMIT_EXCEEDED');
            $event = array_slice($logical, $cursor, 8);
            self::ensure(count($event) === 8, 'EVENT_INCOMPLETE');
            self::ensure($event[0] === 'BEGIN:VEVENT', 'EVENT_START_INVALID');
            self::ensure(preg_match('/\AUID:[a-f0-9]{64}@calendar\.invalid\z/D', $event[1]) === 1, 'UID_INVALID');
            self::ensure(!isset($uids[$event[1]]), 'UID_DUPLICATE');
            $uids[$event[1]] = true;
            self::ensure(self::utc($event[2], 'DTSTAMP:'), 'DTSTAMP_INVALID');
            self::ensure(self::utc($event[3], 'DTSTART:'), 'DTSTART_INVALID');
            self::ensure($event[4] === 'STATUS:CONFIRMED', 'STATUS_INVALID');
            self::ensure(str_starts_with($event[5], 'SUMMARY:Viewing - ')
                && strlen($event[5]) > strlen('SUMMARY:Viewing - '), 'SUMMARY_INVALID');
            self::ensure($event[6] === self::DESCRIPTION, 'DESCRIPTION_OR_PRIVACY_INVALID');
            self::ensure($event[7] === 'END:VEVENT', 'EVENT_FIELDS_INVALID');
            $count++;
            $cursor += 8;
        }

        self::ensure(array_slice($logical, $cursor) === ['END:VCALENDAR'], 'EXTRA_OR_UNSAFE_FIELDS');
        self::ensure($count === $expected, 'EXPECTED_EVENT_COUNT_MISMATCH');

        return [$count, $mode];
    }

    private static function utc(string $property, string $name): bool
    {
        if (!str_starts_with($property, $name)) {
            return false;
        }
        $date = substr($property, strlen($name));
        if (preg_match('/\A[0-9]{8}T[0-9]{6}Z\z/D', $date) !== 1) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat(
            '!Ymd\THis\Z', $date, new DateTimeZone('UTC')
        );

        return $parsed instanceof DateTimeImmutable
            && $parsed->format('Ymd\THis\Z') === $date;
    }

    private static function ensure(bool $condition, string $reason): void
    {
        if (!$condition) {
            throw new RuntimeException($reason);
        }
    }
}

$options = getopt('', ['file:', 'mode:', 'expected-events:']);
$file = $options['file'] ?? null;
$mode = $options['mode'] ?? null;
$expected = $options['expected-events'] ?? null;

try {
    if (!is_string($file) || !str_ends_with(strtolower($file), '.ics')
        || is_link($file) || !is_file($file) || !is_readable($file)) {
        throw new RuntimeException('INPUT_FILE_INVALID');
    }
    if (!is_string($mode) || !is_string($expected)
        || preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', $expected) !== 1) {
        throw new RuntimeException('ARGUMENTS_INVALID');
    }
    $size = filesize($file);
    if ($size === false || $size < 1 || $size > 2097152) {
        throw new RuntimeException('FILE_SIZE_INVALID');
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        throw new RuntimeException('INPUT_READ_FAILED');
    }
    [$count, $kind] = BaramaCalendarImportPreflight::verify(
        $content, $mode, (int) $expected
    );
    echo "CALENDAR_IMPORT_PREFLIGHT=PASS\n";
    echo "MODE=".strtoupper($kind)."\n";
    echo "VERIFIED_EVENT_COUNT=".$count."\n";
    echo "ACTUAL_PROVIDER_OR_DEVICE_IMPORT=NOT_TESTED\n";
    exit(0);
} catch (Throwable $e) {
    echo "CALENDAR_IMPORT_PREFLIGHT=FAIL\n";
    echo 'REASON='.($e instanceof RuntimeException
        ? $e->getMessage() : 'INTERNAL_VALIDATION_ERROR')."\n";
    exit(2);
}
