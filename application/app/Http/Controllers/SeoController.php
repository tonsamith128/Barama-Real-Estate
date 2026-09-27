<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\SeoRepository;
use DateTimeImmutable;
use Throwable;

final class SeoController
{
    public function robots(Request $request): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /account',
            'Disallow: /dashboard',
            'Disallow: /developer',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /verify-email',
            'Disallow: /installer',
            'Disallow: /updater',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
            '',
        ];

        return Response::binary(
            implode("\n", $lines),
            'text/plain; charset=UTF-8',
            ['Cache-Control' => 'public, max-age=3600']
        );
    }

    public function sitemap(Request $request): Response
    {
        $entries = [
            ['path' => '/', 'last_modified' => null],
            ['path' => '/properties', 'last_modified' => null],
            ['path' => '/projects', 'last_modified' => null],
            ['path' => '/company', 'last_modified' => null],
        ];

        foreach ((new SeoRepository())->sitemapEntries() as $entry) {
            $entries[] = $entry;
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($entries as $entry) {
            $location = htmlspecialchars(
                url((string) $entry['path']),
                ENT_QUOTES | ENT_XML1,
                'UTF-8'
            );

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . $location . '</loc>';

            if (!empty($entry['last_modified'])) {
                try {
                    $lastModified = (new DateTimeImmutable((string) $entry['last_modified']))
                        ->format('Y-m-d');
                    $xml[] = '    <lastmod>' . $lastModified . '</lastmod>';
                } catch (Throwable) {
                    // Skip malformed legacy timestamps rather than breaking the sitemap.
                }
            }

            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return Response::binary(
            implode("\n", $xml) . "\n",
            'application/xml; charset=UTF-8',
            ['Cache-Control' => 'public, max-age=1800']
        );
    }
}
