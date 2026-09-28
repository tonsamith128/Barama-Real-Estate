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

        // BARAMA_CUSTOMER_PROPERTY_ACTIVITY_V150
        $activitySearch = mb_substr(trim((string) $request->input('q', '')), 0, 80);
        $activityOffer = trim((string) $request->input('offer', ''));
        $activityType = trim((string) $request->input('type', ''));
        $activityStatus = trim((string) $request->input('status', ''));
        $activitySort = trim((string) $request->input('sort', ''));

        if (!in_array($activityOffer, ['', 'sale', 'rent'], true)) {
            $activityOffer = '';
        }

        if ($activityType !== '' && !in_array($activityType, DomainRules::PROPERTY_TYPES, true)) {
            $activityType = '';
        }

        $activityStatusOptions = match ($section) {
            'inquiries' => DomainRules::INQUIRY_STATUSES,
            'viewings' => ['pending', 'confirmed', 'rescheduled', 'completed', 'declined', 'cancelled'],
            default => [],
        };

        if ($activityStatus !== '' && !in_array($activityStatus, $activityStatusOptions, true)) {
            $activityStatus = '';
        }

        $activitySortOptions = match ($section) {
            'saved' => [
                'saved_desc' => 'Recently saved',
                'price_asc' => 'Price low to high',
                'price_desc' => 'Price high to low',
                'title_asc' => 'Property A-Z',
            ],
            'inquiries' => [
                'newest' => 'Newest first',
                'oldest' => 'Oldest first',
                'property_asc' => 'Property A-Z',
            ],
            'viewings' => [
                'newest' => 'Newest request',
                'oldest' => 'Oldest request',
                'scheduled_asc' => 'Next scheduled',
                'property_asc' => 'Property A-Z',
            ],
            default => [],
        };

        if ($activitySortOptions !== [] && !array_key_exists($activitySort, $activitySortOptions)) {
            $activitySort = array_key_first($activitySortOptions);
        }

        $savedListings = match ($section) {
            'overview' => $engagement->savedListings($userId, 3),
            'saved' => $engagement->savedListingsForAccount(
                $userId,
                $activitySearch,
                $activityOffer,
                $activityType,
                $activitySort,
                100
            ),
            default => [],
        };

        $inquiries = $section === 'inquiries'
            ? $engagement->inquiriesForAccount(
                $userId,
                $activitySearch,
                $activityStatus,
                $activitySort,
                200
            )
            : [];

        $viewingRequests = match ($section) {
            'overview' => $engagement->viewingRequests($userId),
            'viewings' => $engagement->viewingRequestsForAccount(
                $userId,
                $activitySearch,
                $activityStatus,
                $activitySort,
                100
            ),
            default => [],
        };

        // BARAMA_CUSTOMER_PERSONALIZATION_V160
        // Real-estate listing scope only: property searches, listing/viewing/inquiry
        // notifications, recent listings, and company/vendor listing access.
        $personalizationSearch = mb_substr(trim((string) $request->input('q', '')), 0, 80);
        $personalizationState = trim((string) $request->input('state', ''));
        $personalizationType = trim((string) $request->input('category', ''));
        $personalizationFrequency = trim((string) $request->input('frequency', ''));
        $personalizationSort = trim((string) $request->input('sort', ''));

        $savedSearches = [];
        $recentViews = $section === 'overview'
            ? $engagement->recentViews($userId, 3)
            : [];
        $notificationItems = [];
        $ownedCompanies = [];
        $companyInvitations = [];

        if ($section === 'alerts') {
            if (!in_array($personalizationState, ['', 'enabled', 'paused'], true)) {
                $personalizationState = '';
            }
            if (!in_array($personalizationFrequency, ['', 'daily', 'weekly'], true)) {
                $personalizationFrequency = '';
            }
            if (!in_array($personalizationSort, ['updated_desc', 'name_asc'], true)) {
                $personalizationSort = 'updated_desc';
            }

            $savedSearches = $engagement->savedSearchesForAccount(
                $userId,
                $personalizationSearch,
                $personalizationState,
                $personalizationFrequency,
                $personalizationSort,
                100
            );
        } elseif ($section === 'notifications') {
            if (!in_array($personalizationState, ['', 'unread', 'read'], true)) {
                $personalizationState = '';
            }
            if (!in_array($personalizationType, ['', 'saved_search_alerts', 'viewing_updates', 'inquiry_updates', 'announcements'], true)) {
                $personalizationType = '';
            }

            $notificationItems = $notifications->notificationsForAccount(
                $userId,
                $personalizationSearch,
                $personalizationState,
                $personalizationType,
                150
            );
        } elseif ($section === 'recent') {
            if (!in_array($personalizationSort, ['recent_desc', 'most_viewed', 'title_asc'], true)) {
                $personalizationSort = 'recent_desc';
            }

            $recentViews = $engagement->recentViewsForAccount(
                $userId,
                $personalizationSearch,
                $personalizationSort,
                100
            );
        } elseif ($section === 'company') {
            $ownedCompanies = $accountSettings->ownedCompaniesForAccess($userId);
            $companyInvitations = $accountSettings->invitationsForEmail((string) $user['email']);
        }

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
            'ownedCompanies' => $ownedCompanies,
            'companyInvitations' => $companyInvitations,
            'summary' => $summary,
            'savedListings' => $savedListings,
            'comparisonItems' => $section === 'compare'
                ? $engagement->comparisonItems($userId)
                : [],
            'inquiries' => $inquiries,
            'viewingRequests' => $viewingRequests,
            'savedSearches' => $savedSearches,
            'recentViews' => $recentViews,
            'notifications' => $notificationItems,
            'notificationPreferences' => $section === 'notifications'
                ? $notifications->preferences($userId)
                : [],
            'personalizationSearch' => $personalizationSearch,
            'personalizationFilters' => [
                'state' => $personalizationState,
                'category' => $personalizationType,
                'frequency' => $personalizationFrequency,
            ],
            'personalizationSort' => $personalizationSort,
            'locations' => in_array($section, ['alerts', 'company'], true)
                ? $marketplace->activeLocations()
                : [],
            'propertyTypes' => DomainRules::PROPERTY_TYPES,
            'activitySearch' => $activitySearch,
            'activityFilters' => [
                'offer' => $activityOffer,
                'type' => $activityType,
                'status' => $activityStatus,
            ],
            'activitySort' => $activitySort,
            'activitySortOptions' => $activitySortOptions,
            'activityStatusOptions' => $activityStatusOptions,
            'companyTypes' => DomainRules::COMPANY_TYPES,
            'timezones' => $section === 'profile' ? timezone_identifiers_list() : [],
        ]));
    }

    /** @return array<int,array{label:string,path:string,icon:string}> */
    private function navigation(): array
    {
        return [
            ['heading' => 'Account'],
            ['label' => 'Overview', 'path' => '/account', 'icon' => 'grid'],
            ['heading' => 'Property Activity'],
            ['label' => 'Saved', 'path' => '/account/saved', 'icon' => 'heart'],
            ['label' => 'Compare', 'path' => '/account/compare', 'icon' => 'compare'],
            ['label' => 'Inquiries', 'path' => '/account/inquiries', 'icon' => 'message'],
            ['label' => 'Viewings', 'path' => '/account/viewings', 'icon' => 'calendar'],
            ['heading' => 'Personalization'],
            ['label' => 'Alerts', 'path' => '/account/alerts', 'icon' => 'bell'],
            ['label' => 'Notifications', 'path' => '/account/notifications', 'icon' => 'bell'],
            ['label' => 'Recent', 'path' => '/account/recent', 'icon' => 'clock'],
            ['heading' => 'Business Access'],
            ['label' => 'Company Access', 'path' => '/account/company', 'icon' => 'building'],
            ['heading' => 'Settings'],
            ['label' => 'Basic', 'path' => '/account/settings', 'icon' => 'settings'],
            ['label' => 'Profile', 'path' => '/account/settings/profile', 'icon' => 'user'],
            ['label' => 'Privacy', 'path' => '/account/settings/privacy', 'icon' => 'shield'],
            ['label' => 'Security', 'path' => '/account/settings/security', 'icon' => 'lock'],
        ];
    }
}
