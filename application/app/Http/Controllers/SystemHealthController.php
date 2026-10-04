<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\PublicSystemHealthService;

final class SystemHealthController
{
    public function index(Request $request): Response
    {
        $snapshot = (new PublicSystemHealthService())->snapshot();

        $html = View::render(
            'public/system-health',
            [
                'pageTitle' => 'System Status',
                'metaDescription' => 'Current customer-facing service status for Barama Real Estate.',
                'metaRobots' => 'noindex,follow',
                'canonicalUrl' => url('/health'),
                'systemHealth' => $snapshot,
            ],
            'layouts/status'
        );

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, follow',
        ]);
    }

    public function machine(Request $request): Response
    {
        $snapshot = (new PublicSystemHealthService())->snapshot();
        $healthy = ($snapshot['overall'] ?? '') === 'operational';

        return new Response(
            json_encode(
                ['status' => $healthy ? 'ok' : 'degraded'],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            $healthy ? 200 : 503,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]
        );
    }
}
