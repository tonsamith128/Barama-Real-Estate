<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\NotificationRepository;

/** Server-side, recipient-only notification acknowledgement and safe navigation. */
final class NotificationActionController
{
    public function open(Request $request, string $id): Response
    {
        $user = auth_user();
        if (!is_array($user) || (int)($user['id'] ?? 0) < 1) {
            return Response::redirect(url('/login'));
        }
        if (!ctype_digit($id) || (int)$id < 1) {
            return Response::html('Notification not found.', 404);
        }
        $userId = (int)$user['id'];
        $repository = new NotificationRepository();
        $notification = $repository->findOwned($userId, (int)$id);
        if ($notification === null) {
            return Response::html('Notification not found.', 404);
        }

        $source = (string)$request->input('source', 'account');
        $fallback = match ($source) {
            'dashboard' => '/dashboard/notifications',
            'developer' => '/developer/notifications',
            default => '/account/notifications',
        };
        $target = self::safeDestination((string)($notification['action_url'] ?? '')) ?? $fallback;
        // The user-specific read write happens only after verifying recipient ownership.
        $repository->markRead($userId, (int)$id);
        return Response::redirect(url($target), 303);
    }

    /** Only application-relative GET destinations; no external/open redirects. */
    public static function safeDestination(string $raw): ?string
    {
        $raw = trim($raw);
        // Some legacy events stored an absolute app URL; normalize same-origin HTTPS only.
        if (str_starts_with(strtolower($raw), 'https://')) {
            $absolute = parse_url($raw);
            $host = parse_url((string)config('app.url', ''), PHP_URL_HOST);
            if ($absolute === false || !is_string($host) || $host === ''
                || strcasecmp((string)($absolute['host'] ?? ''), $host) !== 0
                || isset($absolute['user']) || isset($absolute['pass']) || isset($absolute['port'])) {
                return null;
            }
            $raw = (string)($absolute['path'] ?? '/')
                . (isset($absolute['query']) ? '?' . $absolute['query'] : '')
                . (isset($absolute['fragment']) ? '#' . $absolute['fragment'] : '');
        }
        if ($raw === '' || strlen($raw) > 1800 || $raw[0] !== '/' || str_starts_with($raw, '//')
            || str_contains($raw, chr(92)) || preg_match('/[\x00-\x1F\x7F]/', $raw)) {
            return null;
        }
        $parts = parse_url($raw);
        if ($parts === false || isset($parts['scheme'], $parts['host'])
            || isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return null;
        }
        $path = (string)($parts['path'] ?? '');
        // Avoid encoded path separators, dot segments, auth/logout or mutation paths.
        if ($path === '' || str_contains($path, '//') || preg_match('~%(?:2f|5c|00|0d|0a)~i', $path)
            || preg_match('~(^|/)\.{1,2}(/|$)~', $path)) {
            return null;
        }
        if (preg_match('~^/(?:dashboard/(?:audit|security|notifications|inquiries|properties|projects|companies|users)(?:/|$)|developer/(?:notifications|company|projects|properties|listings|inquiries|viewings|leads)(?:/|$)|account/(?:notifications|alerts|viewings|inquiries|saved|recent|profile)(?:/|$)|properties(?:/|$)|projects(?:/|$)|company(?:/|$))~', $path) !== 1) {
            return null;
        }
        return $raw;
    }
}
