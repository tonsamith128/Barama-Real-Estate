<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\CustomerEngagementRepository;
use Barama\Repositories\MarketplaceRepository;
use Barama\Repositories\NotificationRepository;
use Barama\Services\AccountSettingsService;

final class AccountController
{
    private const SECTIONS = [
        'overview' => ['title' => 'Account Overview', 'description' => 'Your saved properties, inquiries, viewings, alerts, and recent marketplace activity.'],
        'saved' => ['title' => 'Saved Properties', 'description' => 'Keep a personal shortlist of published properties you want to revisit.'],
        'compare' => ['title' => 'Property Comparison', 'description' => 'Compare up to four published properties side by side.'],
        'inquiries' => ['title' => 'Inquiry History', 'description' => 'Review inquiries submitted from your signed-in Barama account.'],
        'viewings' => ['title' => 'Viewing Requests', 'description' => 'Track property viewing requests and their scheduling status.'],
        'alerts' => ['title' => 'Saved Searches & Alerts', 'description' => 'Save property criteria and control alert preferences for matching inventory.'],
        'notifications' => ['title' => 'Notifications', 'description' => 'Review marketplace updates and control in-app and email notification preferences.'],
        'recent' => ['title' => 'Recently Viewed', 'description' => 'Return to published properties you recently opened while signed in.'],
        'company' => ['title' => 'Company Access', 'description' => 'Request your first company workspace or accept invitations from approved companies.'],
        'profile' => ['title' => 'Profile Settings', 'description' => 'Maintain your personal account details, timezone, and preferred language.'],
        'privacy' => ['title' => 'Privacy Settings', 'description' => 'Control marketplace activity history and clear personal browsing activity.'],
        'security' => ['title' => 'Security Settings', 'description' => 'Manage your password, verified email address, and account status securely.'],
    ];

    public function index(Request $request): Response
    {
        return $this->renderSection($request, 'overview');
    }

    public function section(Request $request, string $section): Response
    {
        return $this->renderSection($request, strtolower(trim($section)));
    }

    public function emailChange(Request $request, string $token): Response
    {
        $user = auth_user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        $newEmail = (new AccountSettingsService())->emailChangePreview((int) $user['id'], $token);

        return Response::html(View::render('account/email-change', [
            'pageTitle' => 'Confirm Email Change · Account',
            'activeArea' => 'account',
            'token' => $token,
            'newEmail' => $newEmail,
        ]), $newEmail === null ? 404 : 200);
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
        $notifications = new NotificationRepository();
        $accountSettings = new AccountSettingsRepository();
        $profile = $accountSettings->profile($userId);
        $summary = $engagement->summary($userId);
        $summary['notifications'] = $notifications->unreadCount($userId);

        return Response::html(View::render('account/index', [
            'pageTitle' => self::SECTIONS[$section]['title'] . ' · Account',
            'activeArea' => 'account',
            'workspaceLabel' => 'Customer Account',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $section,
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'sectionDescription' => self::SECTIONS[$section]['description'],
            'accountUser' => $user,
            'profile' => $profile,
            'ownedCompanies' => $section === 'company'
                ? $accountSettings->ownedCompanies($userId)
                : [],
            'companyInvitations' => $section === 'company'
                ? $accountSettings->invitationsForEmail((string) $user['email'])
                : [],
            'summary' => $summary,
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
            'notifications' => $section === 'notifications'
                ? $notifications->notifications($userId)
                : [],
            'notificationPreferences' => $section === 'notifications'
                ? $notifications->preferences($userId)
                : [],
            'locations' => in_array($section, ['alerts', 'company'], true)
                ? $marketplace->activeLocations()
                : [],
            'propertyTypes' => DomainRules::PROPERTY_TYPES,
            'companyTypes' => DomainRules::COMPANY_TYPES,
            'timezones' => $section === 'profile' ? timezone_identifiers_list() : [],
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
            ['label' => 'Notifications', 'path' => '/account/notifications', 'icon' => 'bell'],
            ['label' => 'Recent', 'path' => '/account/recent', 'icon' => 'clock'],
            ['label' => 'Company Access', 'path' => '/account/company', 'icon' => 'building'],
            ['label' => 'Profile', 'path' => '/account/profile', 'icon' => 'user'],
            ['label' => 'Privacy', 'path' => '/account/privacy', 'icon' => 'shield'],
            ['label' => 'Security', 'path' => '/account/security', 'icon' => 'lock'],
        ];
    }
}
