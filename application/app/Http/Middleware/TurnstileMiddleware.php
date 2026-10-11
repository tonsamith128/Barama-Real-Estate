<?php

declare(strict_types=1);

namespace Barama\Http\Middleware;

use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Security\TurnstileSettingsStore;
use Barama\Security\TurnstileVerifier;
use Closure;
use Throwable;

/**
 * Form-scoped Turnstile enforcement.
 *
 * This middleware is intentionally not added to live routes until the
 * corresponding widgets, CSP policy, and admin configuration UI are complete.
 */
final class TurnstileMiddleware implements MiddlewareInterface
{
    private const JSON_PATHS = ['/contact/messages', '/inquiries'];

    /**
     * @param Closure(string,string,string,string,?string):bool|null $testVerifier
     */
    public function __construct(
        private readonly string $form,
        private readonly ?TurnstileSettingsStore $store = null,
        private readonly ?Closure $testVerifier = null,
        private readonly ?string $siteUrl = null
    ) {
        if (!in_array($form, TurnstileSettingsStore::FORMS, true)) {
            throw new \InvalidArgumentException('Unknown Turnstile form.');
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            $settings = ($this->store ?? new TurnstileSettingsStore())->read();
        } catch (Throwable) {
            // A corrupted/undecryptable enabled configuration must not silently
            // bypass verification. Never reveal encryption or Siteverify errors.
            return $this->reject($request, 503);
        }

        if (!$settings['enabled'] || !in_array($this->form, $settings['forms'], true)) {
            return $next($request);
        }

        $siteUrl = $this->siteUrl ?? (string) config('app.url', '');
        $hostname = parse_url($siteUrl, PHP_URL_HOST);
        $hostname = is_string($hostname) ? strtolower(rtrim($hostname, '.')) : '';

        $token = $request->input('cf-turnstile-response', '');
        if (!is_string($token) || $token === '' || strlen($token) > 2048 ||
            ($settings['site_key'] ?? '') === '' ||
            ($settings['secret'] ?? '') === '' ||
            $hostname === '' ||
            filter_var($hostname, FILTER_VALIDATE_IP)) {
            return $this->reject($request, 422);
        }

        try {
            $accepted = $this->testVerifier !== null
                ? ($this->testVerifier)(
                    $settings['secret'],
                    $token,
                    $hostname,
                    $this->form,
                    $request->ip()
                )
                : (new TurnstileVerifier())->verify(
                    $settings['secret'],
                    $token,
                    $hostname,
                    $this->form,
                    $request->ip()
                );
        } catch (Throwable) {
            $accepted = false;
        }

        return $accepted === true
            ? $next($request)
            : $this->reject($request, 422);
    }

    private function reject(Request $request, int $status): Response
    {
        $message = $status === 503
            ? 'Security verification is temporarily unavailable.'
            : 'Security verification failed. Please refresh and try again.';

        if (in_array($request->path(), self::JSON_PATHS, true)) {
            return Response::json(['ok' => false, 'message' => $message], $status);
        }

        return Response::html(
            '<!doctype html><html lang="en"><meta charset="utf-8">'
            . '<title>Security verification</title>'
            . '<main><h1>Security verification</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p></main></html>',
            $status
        );
    }
}
