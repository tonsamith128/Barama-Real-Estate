<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\SiteContentRepository;

final class BuiltInPageController
{
    public function about(Request $request): Response
    {
        return $this->render('about');
    }

    public function contact(Request $request): Response
    {
        return $this->render('contact');
    }

    private function render(string $page): Response
    {
        $config = (new SiteContentRepository())->get();
        $pageConfig = is_array($config[$page] ?? null) ? $config[$page] : [];

        if (empty($pageConfig['enabled'])) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Page not found']),
                404
            );
        }

        $seo = is_array($pageConfig['seo'] ?? null) ? $pageConfig['seo'] : [];
        $hero = is_array($pageConfig['hero'] ?? null) ? $pageConfig['hero'] : [];
        $fallbackTitle = $page === 'about' ? 'About Us' : 'Contact Us';
        $view = $page === 'about' ? 'public/about' : 'public/contact';

        return Response::html(View::render($view, [
            'pageTitle' => trim((string) ($seo['title'] ?? '')) !== ''
                ? (string) $seo['title']
                : (trim((string) ($hero['title'] ?? '')) !== '' ? (string) $hero['title'] : $fallbackTitle),
            'metaDescription' => trim((string) ($seo['description'] ?? '')) !== ''
                ? (string) $seo['description']
                : (string) ($hero['description'] ?? ''),
            'canonicalUrl' => url('/' . $page),
            'metaRobots' => (string) ($seo['robots'] ?? 'index,follow'),
            'metaImage' => (string) ($hero['image_url'] ?? ''),
            'activeArea' => $page,
            $page . 'Page' => $pageConfig,
        ]));
    }
}
