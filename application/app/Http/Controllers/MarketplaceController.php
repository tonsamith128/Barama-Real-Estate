<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\CustomerEngagementRepository;
use Barama\Repositories\MarketplaceRepository;
use Barama\Services\CustomerEngagementService;

final class MarketplaceController
{
    public function properties(Request $request): Response
    {
        $repository = new MarketplaceRepository();
        $filters = $this->listingFilters($request);

        return Response::html(View::render('marketplace/properties', [
            'pageTitle' => 'Properties for Sale & Rent',
            'activeArea' => 'properties',
            'inventory' => $repository->listings($filters, $this->page($request)),
            'filters' => $filters,
            'locations' => $repository->activeLocations(),
            'propertyTypes' => DomainRules::PROPERTY_TYPES,
        ]));
    }

    public function property(Request $request, string $slug): Response
    {
        $repository = new MarketplaceRepository();
        $listing = $repository->listingBySlug($slug);

        if ($listing === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Property not found']),
                404
            );
        }

        $user = auth_user();
        $engagementState = [
            'signed_in' => $user !== null,
            'saved' => false,
            'compared' => false,
        ];

        if ($user !== null) {
            $userId = (int) $user['id'];
            $listingId = (int) $listing['id'];
            (new CustomerEngagementService())->recordView($userId, $listingId);
            $engagement = new CustomerEngagementRepository();
            $engagementState['saved'] = $engagement->isSaved($userId, $listingId);
            $engagementState['compared'] = $engagement->isCompared($userId, $listingId);
        }

        return Response::html(View::render('marketplace/property', [
            'pageTitle' => (string) $listing['title'],
            'activeArea' => 'properties',
            'listing' => $listing,
            'gallery' => $repository->gallery('listing', (int) $listing['id']),
            'viewer' => $user,
            'engagementState' => $engagementState,
        ]));
    }

    public function projects(Request $request): Response
    {
        $repository = new MarketplaceRepository();
        $filters = [
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 100),
            'type' => trim((string) $request->input('type', '')),
            'completion' => trim((string) $request->input('completion', '')),
            'location' => trim((string) $request->input('location', '')),
        ];

        return Response::html(View::render('marketplace/projects', [
            'pageTitle' => 'New Developments',
            'activeArea' => 'projects',
            'inventory' => $repository->projects($filters, $this->page($request)),
            'filters' => $filters,
            'locations' => $repository->activeLocations(),
            'projectTypes' => DomainRules::PROJECT_TYPES,
            'completionStatuses' => DomainRules::PROJECT_COMPLETION_STATUSES,
        ]));
    }

    public function project(Request $request, string $slug): Response
    {
        $repository = new MarketplaceRepository();
        $project = $repository->projectBySlug($slug);

        if ($project === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Project not found']),
                404
            );
        }

        return Response::html(View::render('marketplace/project', [
            'pageTitle' => (string) $project['name'],
            'activeArea' => 'projects',
            'project' => $project,
            'gallery' => $repository->gallery('project', (int) $project['id']),
            'listings' => $repository->projectListings((int) $project['id'], 6),
        ]));
    }

    /** @return array<string,mixed> */
    private function listingFilters(Request $request): array
    {
        return [
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 100),
            'offer' => trim((string) $request->input('offer', '')),
            'type' => trim((string) $request->input('type', '')),
            'location' => trim((string) $request->input('location', '')),
            'bedrooms' => trim((string) $request->input('bedrooms', '')),
            'min_price' => trim((string) $request->input('min_price', '')),
            'max_price' => trim((string) $request->input('max_price', '')),
            'sort' => trim((string) $request->input('sort', 'newest')),
        ];
    }

    private function page(Request $request): int
    {
        $page = filter_var(
            $request->input('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $page === false ? 1 : (int) $page;
    }
}
