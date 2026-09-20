<?php

declare(strict_types=1);

namespace Barama\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $content = '',
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): self
    {
        $content = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return new self($content, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            $securityHeaders = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'camera=(), microphone=(), payment=()',
                'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data: https:; style-src 'self' https://cdn.jsdelivr.net; script-src 'self'; font-src 'self' data: https:; connect-src 'self'",
            ];

            if (is_https()) {
                $securityHeaders['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
            }

            foreach (array_merge($securityHeaders, $this->headers) as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->content;
    }
}
