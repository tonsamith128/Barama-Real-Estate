<?php

declare(strict_types=1);

namespace Barama\Http\Middleware;

use Barama\Core\Csrf;
use Barama\Core\MiddlewareInterface;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->input('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            // BARAMA_MEDIA_UPLOAD_SIZE_GUARD_V102: when PHP rejects an
            // oversized multipart body, POST fields including CSRF are empty.
            // Reject with 413; never treat the request as CSRF-authorized.
            if ($request->method() === 'POST'
                && $request->path() === '/dashboard/media/upload'
                && $_POST === [] && $_FILES === []
                && isset($_SERVER['CONTENT_LENGTH'])
                && ctype_digit((string) $_SERVER['CONTENT_LENGTH'])
            ) {
                $configured = trim((string) ini_get('post_max_size'));
                $maxPost = 0;
                if (preg_match('/^([0-9]+)([KMG])?$/i', $configured, $parts) === 1) {
                    $n = (int) $parts[1];
                    $factor = match (strtoupper($parts[2] ?? '')) {
                        'K' => 1024, 'M' => 1048576, 'G' => 1073741824, default => 1,
                    };
                    $maxPost = $n > intdiv(PHP_INT_MAX, $factor) ? PHP_INT_MAX : $n * $factor;
                }
                if ($maxPost > 0 && (float) $_SERVER['CONTENT_LENGTH'] > $maxPost) {
                    return Response::html(
                        '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                        . '<title>Upload too large · Barama</title>'
                        . '<style>body{font:16px system-ui,sans-serif;background:#f4f7fc;color:#172238;'
                        . 'display:grid;place-items:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box}'
                        . 'main{background:#fff;max-width:560px;padding:35px;border-radius:18px;'
                        . 'border:1px solid #dbe5f4}h1{margin:0 0 12px}a{color:#165af5}</style>'
                        . '</head><body><main><h1>File upload too large</h1>'
                        . '<p>Your upload exceeds this server’s maximum request size. '
                        . 'No files were saved. Choose smaller files and reopen the uploader.</p>'
                        . '<p><a href="/dashboard/media/upload">Return to Media Library uploader</a></p>'
                        . '</main></body></html>',
                        413
                    );
                }
            }
            return Response::html(
                View::render('errors/419', [
                    'pageTitle' => 'Session expired',
                    'activeArea' => '',
                ]),
                419
            );
        }

        return $next($request);
    }
}
