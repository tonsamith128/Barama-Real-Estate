<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Seo;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Only publicly visible records enter the sitemap.
 * Private workspaces, unpublished items, and expired listings never appear.
 */
final class SitemapController
{
    public function index(): StreamedResponse
    {
        return response()->stream(function (): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach (['home', 'properties.index', 'projects.index', 'companies.index'] as $name) {
                $this->writeUrl(route($name));
            }

            Company::query()->publiclyVisible()->select(['id', 'slug'])
                ->chunkById(250, function ($companies): void {
                    foreach ($companies as $company) {
                        $this->writeUrl(route('companies.show', $company->slug));
                    }
                });

            Project::query()->publiclyVisible()->select(['id', 'slug'])
                ->chunkById(250, function ($projects): void {
                    foreach ($projects as $project) {
                        $this->writeUrl(route('projects.show', $project->slug));
                    }
                });

            Listing::query()->visibleToPublic()->select(['id', 'slug'])
                ->chunkById(250, function ($listings): void {
                    foreach ($listings as $listing) {
                        $this->writeUrl(route('properties.show', $listing->slug));
                    }
                });
            echo "</urlset>\n";
        }, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\n"
            ."Disallow: /dashboard\n"
            ."Disallow: /developer\n"
            ."Disallow: /account\n"
            ."Disallow: /installer\n"
            ."Sitemap: ".route('seo.sitemap')."\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function writeUrl(string $url): void
    {
        echo '  <url><loc>'
            .htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8')
            ."</loc></url>\n";
    }
}
