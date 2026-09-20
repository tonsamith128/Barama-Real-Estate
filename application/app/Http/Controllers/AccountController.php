<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\CustomerEngagementRepository;
use Barama\Repositories\MarketplaceRepository;

final class AccountController
{
    private const SECTIONS = [
        'overview' => ['title' => 'Account Overview', 'description' => 'Your saved properties, inquiries, viewings, alerts, and recent marketplace activity.'],
        'saved' => ['title' => 'Saved Properties', 'description' => 'Keep a personal shortlist of published properties you want to revisit.'],
        'compare' => ['title' => 'Property Comparison', 'description' => 'Compare up to four published properties side by side.'],
        'inquiries' => ['title' => 'Inquiry History', 'description' => 'Review inquiries submitted from your signed-in Barama account.'],
        'viewings' => ['title' => 'Viewing Requests', 'description' => 'Track property viewing requests and their scheduling status.'],
        'alerts' => ['title' => 'Saved Searches & Alerts', 'description' => 'Save property criteria and control alert preferences for matching inventory.'],
        'recent' => ['title' => 'Recently Viewed', 'description' => 'Return to published properties you recently opened while signed in.'],
    ];

    public function index(Request $request): Response
    {
        return $this->renderSection($request, 'overview');
    }

    public function section(Request $request, string $section): Response
    {
        return $this->renderSection($request, strtolower(trim($section)));
    }

    private function renderSection(Request $request, string $section): Response
    {
        if (!isset(self::SECTIONS[$section])) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Page not found']),
                404
            );
        }

        $user = auth_user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        $userId = (int) $user['id'];
        $engagement = new CustomerEngagementRepository();
        $marketplace = new MarketplaceRepository();

        return Response::html(View::render('account/index', [
            'pageTitle' => self::SECTIONS[$section]['title'] . ' · Account',
            'activeArea' => 'account',
            'workspaceLabel' => 'Customer Account',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $section,
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'sectionDescription' => self::SECTIONS[$section]['description'],
            'accountUser' => $user,
            'summary' => $engagement->summary($userId),
            'savedListings' => in_array($section, ['overview', 'saved'], true)
                ? $engagement->savedListings($userId, $section === 'overview' ? 3 : 50)
                : [],
            'comparisonItems' => $section === 'compare'
                ? $engagement->comparisonItems($userId)
                : [],
            'inquiries' => $section === 'inquiries'
                ? $engagement->inquiries($userId)
                : [],
            'viewingRequests' => in_array($section, ['overview', 'viewings'], true)
                ? $engagement->viewingRequests($userId)
                : [],
            'savedSearches' => $section === 'alerts'
                ? $engagement->savedSearches($userId)
                : [],
            'recentViews' => in_array($section, ['overview', 'recent'], true)
                ? $engagement->recentViews($userId, $section === 'overview' ? 3 : 24)
                : [],
            'locations' => $section === 'alerts' ? $marketplace->activeLocations() : [],
            'propertyTypes' => DomainRules::PROPERTY_TYPES,
        ]));
    }

    /** @return array<int,array{label:string,path:string,icon:string}> */
    private function navigation(): array
    {
        return [
            ['label' => 'Overview', 'path' => '/account', 'icon' => 'grid'],
            ['label' => 'Saved', 'path' => '/account/saved', 'icon' => 'heart'],
            ['label' => 'Compare', 'path' => '/account/compare', 'icon' => 'compare'],
            ['label' => 'Inquiries', 'path' => '/account/inquiries', 'icon' => 'message'],
            ['label' => 'Viewings', 'path' => '/account/viewings', 'icon' => 'calendar'],
            ['label' => 'Alerts', 'path' => '/account/alerts', 'icon' => 'bell'],
            ['label' => 'Recent', 'path' => '/account/recent', 'icon' => 'clock'],
        ];
    }
}
