<?php

declare(strict_types=1);

namespace Barama\Services;

final class CmsRoutePolicy
{
    private const RESERVED_SLUGS = [
        'about',
        'account',
        'appearance.css',
        'company',
        'contact',
        'dashboard',
        'developer',
        'forgot-password',
        'health',
        'inquiries',
        'locale',
        'login',
        'logout',
        'media',
        'pages',
        'payments',
        'projects',
        'properties',
        'register',
        'reset-password',
        'robots.txt',
        'sitemap.xml',
        'subscribe',
        'verify-email',
    ];

    public static function isReservedSlug(string $slug): bool
    {
        return in_array(
            strtolower(trim($slug)),
            self::RESERVED_SLUGS,
            true
        );
    }

    /** @return array<int,string> */
    public static function reservedSlugs(): array
    {
        return self::RESERVED_SLUGS;
    }
}
