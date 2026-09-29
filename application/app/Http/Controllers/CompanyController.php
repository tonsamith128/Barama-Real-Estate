<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\MarketplaceRepository;
use Barama\Repositories\SiteContentRepository;

final class CompanyController
{
    public function index(Request $request): Response
    {
        $repository = new MarketplaceRepository();
        $siteContent = (new SiteContentRepository())->get();
        $pageConfig = is_array($siteContent['marketplace_pages']['companies'] ?? null)
            ? $siteContent['marketplace_pages']['companies']
            : SiteContentRepository::defaults()['marketplace_pages']['companies'];
        $pageSize = max(6, min(48, (int) ($pageConfig['grid']['page_size'] ?? 16)));
        $filters = [
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 100),
            'type' => trim((string) $request->input('type', '')),
            'location' => trim((string) $request->input('location', '')),
        ];

        $page = filter_var(
            $request->input('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $currentPage = $page === false ? 1 : (int) $page;
        $isFiltered = $currentPage > 1
            || (string) ($filters['q'] ?? '') !== ''
            || (string) ($filters['type'] ?? '') !== ''
            || (string) ($filters['location'] ?? '') !== '';

        return Response::html(View::render('company/index', [
            'pageTitle' => 'Real Estate Developers',
            'metaDescription' => 'Browse approved real-estate developers, agencies, property companies, and marketplace vendors on Barama Real Estate.',
            'canonicalUrl' => url('/company'),
            'metaRobots' => $isFiltered ? 'noindex,follow' : 'index,follow,max-image-preview:large',
            'activeArea' => 'company',
            'inventory' => $repository->companies(
                $filters,
                $currentPage,
                $pageSize
            ),
            'pageConfig' => $pageConfig,
            'filters' => $filters,
            'locations' => $repository->activeLocations(),
            'companyTypes' => DomainRules::COMPANY_TYPES,
        ]));
    }

    public function show(Request $request, string $slug): Response
    {
        $repository = new MarketplaceRepository();
        $company = $repository->companyBySlug($slug);

        if ($company === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Company not found']),
                404
            );
        }

        $gallery = $repository->gallery('company', (int) $company['id']);
        $metaImage = '';

        foreach ($gallery as $media) {
            if (
                str_starts_with((string) ($media['mime_type'] ?? ''), 'image/')
                && !empty($media['uuid'])
            ) {
                $metaImage = url('/media/' . rawurlencode((string) $media['uuid']));
                break;
            }
        }

        $metaDescription = trim((string) ($company['description'] ?? ''));

        if ($metaDescription === '') {
            $metaDescription = (string) $company['name']
                . (!empty($company['location_name']) ? ' in ' . (string) $company['location_name'] : '')
                . ' · approved real-estate company on Barama Real Estate.';
        }

        return Response::html(View::render('company/show', [
            'pageTitle' => (string) $company['name'],
            'metaDescription' => mb_substr($metaDescription, 0, 280),
            'canonicalUrl' => url('/company/' . rawurlencode((string) $company['slug'])),
            'metaImage' => $metaImage,
            'metaType' => 'profile',
            'activeArea' => 'company',
            'company' => $company,
            'gallery' => $gallery,
            'projects' => $repository->companyProjects((int) $company['id'], 6),
            'listings' => $repository->companyListings((int) $company['id'], 6),
            'viewer' => auth_user(),
        ]));
    }
}
