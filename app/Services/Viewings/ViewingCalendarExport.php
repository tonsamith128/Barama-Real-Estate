<?php

declare(strict_types=1);

namespace App\Services\Viewings;

use App\Models\ViewingRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Generates a one-time download, never a background subscription, public feed
 * or invitation to a third-party calendar provider.
 */
final class ViewingCalendarExport
{
    public function eligible(): Builder
    {
        return ViewingRequest::query()
            ->where('status', 'confirmed')
            ->where('review_status', 'clear')
            ->whereNull('legacy_uuid')
            ->where('requested_at', '>=', now())
            ->where('requested_at', '<=', now()->addMonths(6))
            ->whereHas('listing', fn (Builder $query) => $query->visibleToPublic());
    }

    /** @param iterable<ViewingRequest> $viewings */
    public function render(iterable $viewings): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Barama Real Estate//Viewing Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Barama confirmed viewings',
        ];

        // One DTSTAMP for this export, even when it contains many events.
        $exportedAt = now()->utc()->format('Ymd\THis\Z');
        foreach ($viewings as $viewing) {
            // No customer name, notes, email, telephone, address or agency
            // contact details are distributed in this downloadable file.
            $title = (string) ($viewing->listing?->property?->title ?: 'Property');
            $uid = hash('sha256', (string) config('app.url').'|viewing|'.$viewing->getKey());
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:'.$uid.'@calendar.invalid',
                'DTSTAMP:'.$exportedAt,
                'DTSTART:'.$viewing->requested_at->copy()->utc()->format('Ymd\THis\Z'),
                'STATUS:CONFIRMED',
                'SUMMARY:'.$this->escape('Viewing - '.$title),
                'DESCRIPTION:Confirmed property viewing. Check your Barama account for details. Duration and meeting location are not specified.',
                'END:VEVENT',
            );
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * RFC 5545 text escaping, including protection against calendar property
     * injection through a property title containing CRLF or punctuation.
     */
    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\\;', '\\,', '\\n'],
            $value
        );
    }

    /** Fold lines at the RFC 5545 75-octet boundary, not at UTF-8 bytes. */
    private function fold(string $line): string
    {
        $folded = [];
        while (strlen($line) > 75) {
            $piece = mb_strcut($line, 0, 75, 'UTF-8');
            $folded[] = $piece;
            $line = ' '.substr($line, strlen($piece));
        }
        $folded[] = $line;

        return implode("\r\n", $folded);
    }
}
