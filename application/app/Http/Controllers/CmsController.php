<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\PageRepository;

final class CmsController
{
    public function show(Request $request, string $slug): Response
    {
        $normalizedSlug = strtolower(trim($slug));

        if (in_array($normalizedSlug, ['about', 'about-us'], true)) {
            return Response::redirect(url('/about'), 301);
        }

        if (in_array($normalizedSlug, ['contact', 'contact-us'], true)) {
            return Response::redirect(url('/contact'), 301);
        }

        $page = (new PageRepository())->publishedBySlug($slug);

        if ($page === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Page not found']),
                404
            );
        }

        $description = trim((string) ($page['seo_description'] ?? ''));

        if ($description === '') {
            $description = trim((string) ($page['excerpt'] ?? ''));
        }

        if ($description === '') {
            $description = mb_substr(trim((string) $page['content_text']), 0, 280);
        }

        return Response::html(View::render('cms/page', [
            'pageTitle' => trim((string) ($page['seo_title'] ?? '')) !== ''
                ? (string) $page['seo_title']
                : (string) $page['title'],
            'metaDescription' => $description,
            'canonicalUrl' => url('/pages/' . rawurlencode((string) $page['slug'])),
            'metaRobots' => (string) $page['robots_directive'],
            'activeArea' => 'cms',
            'page' => $page,
        ]));
    }
}
