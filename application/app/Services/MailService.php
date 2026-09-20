<?php

declare(strict_types=1);

namespace Barama\Services;

final class MailService
{
    public function send(string $to, string $subject, string $html, string $text): bool
    {
        $fromAddress = trim((string) config('mail.from_address', ''));
        $fromName = trim((string) config('mail.from_name', 'Barama Real Estate'));

        if (
            !filter_var($to, FILTER_VALIDATE_EMAIL)
            || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
        ) {
            $this->logFailure('Invalid mail address configuration.', $to);
            return false;
        }

        $safeName = str_replace(["\r", "\n"], '', $fromName);
        $safeSubject = str_replace(["\r", "\n"], '', $subject);
        $boundary = 'barama_' . bin2hex(random_bytes(12));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . $safeName . ' <' . $fromAddress . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $text . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n"
            . "--{$boundary}--\r\n";

        $sent = @mail(
            $to,
            $safeSubject,
            $body,
            implode("\r\n", $headers)
        );

        if (!$sent) {
            $this->logFailure('PHP mail() returned false.', $to);
        }

        return $sent;
    }

    private function logFailure(string $reason, string $to): void
    {
        $directory = APP_PATH . '/storage/logs';

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @error_log(
            sprintf("[%s] Mail failure for %s: %s\n", date('c'), $to, $reason),
            3,
            $directory . '/mail.log'
        );
    }
}
