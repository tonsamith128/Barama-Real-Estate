<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

final class MailService
{
    private ?string $lastError = null;

    /** @param array<string,mixed> $overrides */
    public function __construct(
        private readonly array $overrides = []
    ) {
    }

    public function send(string $to, string $subject, string $html, string $text): bool
    {
        $this->lastError = null;

        $fromAddress = trim((string) $this->setting('from_address', ''));
        $fromName = trim((string) $this->setting('from_name', 'Barama Real Estate'));

        if (
            !filter_var($to, FILTER_VALIDATE_EMAIL)
            || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
        ) {
            return $this->fail('Invalid mail address configuration.', $to);
        }

        $driver = strtolower(trim((string) $this->setting('driver', 'mail')));

        return match ($driver) {
            'smtp' => $this->sendSmtp($to, $subject, $html, $text, $fromAddress, $fromName),
            'log' => $this->sendLog($to, $subject, $html, $text, $fromAddress, $fromName),
            'mail' => $this->sendPhpMail($to, $subject, $html, $text, $fromAddress, $fromName),
            default => $this->fail('Unsupported mail driver [' . $driver . '].', $to),
        };
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function sendPhpMail(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $fromAddress,
        string $fromName
    ): bool {
        [$headers, $body] = $this->multipart($to, $subject, $html, $text, $fromAddress, $fromName);

        $sent = @mail(
            $to,
            $this->safeHeader($subject),
            $body,
            implode("\r\n", array_filter(
                $headers,
                static fn (string $header): bool => !str_starts_with($header, 'To:')
                    && !str_starts_with($header, 'Subject:')
            ))
        );

        return $sent
            ? true
            : $this->fail('PHP mail() returned false.', $to);
    }

    private function sendLog(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $fromAddress,
        string $fromName
    ): bool {
        $directory = APP_PATH . '/storage/logs';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return $this->fail('Mail log directory could not be created.', $to);
        }

        $entry = sprintf(
            "[%s] DRIVER=log FROM=%s <%s> TO=%s SUBJECT=%s\nTEXT:\n%s\nHTML:\n%s\n---\n",
            date('c'),
            $fromName,
            $fromAddress,
            $to,
            $this->safeHeader($subject),
            $text,
            $html
        );

        return @file_put_contents(
            $directory . '/mail-delivery.log',
            $entry,
            FILE_APPEND | LOCK_EX
        ) !== false
            ? true
            : $this->fail('Mail log write failed.', $to);
    }

    private function sendSmtp(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $fromAddress,
        string $fromName
    ): bool {
        $host = trim((string) $this->setting('host', ''));
        $port = (int) $this->setting('port', 587);
        $encryption = strtolower(trim((string) $this->setting('encryption', 'tls')));
        $username = (string) $this->setting('username', '');
        $password = (string) $this->setting('password', '');
        $timeout = max(3, (int) $this->setting('timeout', 15));

        if ($host === '' || $port < 1 || $port > 65535) {
            return $this->fail('SMTP host or port is invalid.', $to);
        }

        if (!in_array($encryption, ['', 'none', 'tls', 'ssl'], true)) {
            return $this->fail('SMTP encryption mode is invalid.', $to);
        }

        $scheme = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $errorNumber = 0;
        $errorMessage = '';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
            ],
        ]);

        $stream = @stream_socket_client(
            $scheme . $host . ':' . $port,
            $errorNumber,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($stream)) {
            return $this->fail(
                'SMTP connection failed: ' . ($errorMessage !== '' ? $errorMessage : (string) $errorNumber),
                $to
            );
        }

        stream_set_timeout($stream, $timeout);

        try {
            $this->expect($stream, [220]);
            $hostname = gethostname() ?: 'localhost';
            $this->command($stream, 'EHLO ' . $hostname, [250]);

            if ($encryption === 'tls') {
                $this->command($stream, 'STARTTLS', [220]);

                $cryptoMethod = defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')
                    ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                    : STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

                if (!@stream_socket_enable_crypto($stream, true, $cryptoMethod)) {
                    throw new RuntimeException('SMTP TLS negotiation failed.');
                }

                $this->command($stream, 'EHLO ' . $hostname, [250]);
            }

            if ($username !== '') {
                $this->command($stream, 'AUTH LOGIN', [334]);
                $this->command($stream, base64_encode($username), [334]);
                $this->command($stream, base64_encode($password), [235]);
            }

            $this->command($stream, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->command($stream, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($stream, 'DATA', [354]);

            [$headers, $body] = $this->multipart(
                $to,
                $subject,
                $html,
                $text,
                $fromAddress,
                $fromName
            );
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;

            if (@fwrite($stream, $message . "\r\n.\r\n") === false) {
                throw new RuntimeException('SMTP DATA write failed.');
            }

            $this->expect($stream, [250]);

            @fwrite($stream, "QUIT\r\n");
            fclose($stream);

            return true;
        } catch (\Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return $this->fail($exception->getMessage(), $to);
        }
    }

    /** @return array{0:array<int,string>,1:string} */
    private function multipart(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $fromAddress,
        string $fromName
    ): array {
        $safeName = $this->safeHeader($fromName);
        $safeSubject = $this->safeHeader($subject);
        $boundary = 'barama_' . bin2hex(random_bytes(12));

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $safeName . ' <' . $fromAddress . '>',
            'To: <' . $to . '>',
            'Subject: ' . $safeSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $this->normalizeBody($text) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $this->normalizeBody($html) . "\r\n"
            . '--' . $boundary . "--\r\n";

        return [$headers, $body];
    }

    private function command($stream, string $command, array $expectedCodes): string
    {
        if (@fwrite($stream, $command . "\r\n") === false) {
            throw new RuntimeException('SMTP command write failed.');
        }

        return $this->expect($stream, $expectedCodes);
    }

    private function expect($stream, array $expectedCodes): string
    {
        $response = '';

        while (($line = fgets($stream, 515)) !== false) {
            $response .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('SMTP server closed the connection unexpectedly.');
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException(
                'SMTP server returned ' . $code . ': '
                . trim(preg_replace('/^\d{3}[- ]?/m', '', $response) ?? $response)
            );
        }

        return $response;
    }

    private function safeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function normalizeBody(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace("\n", "\r\n", $normalized);
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->overrides)
            ? $this->overrides[$key]
            : config('mail.' . $key, $default);
    }

    private function fail(string $reason, string $to): bool
    {
        $this->lastError = $reason;
        $this->logFailure($reason, $to);

        return false;
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
