<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\Session;
use Barama\Core\View;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\GlobalThemeRepository;
use Barama\Repositories\WorkspaceSettingsRepository;
use Barama\Repositories\GlobalLocationRepository;
use Barama\Services\AccountSettingsService;
use Barama\Services\AdminSettingsService;
use Barama\Services\GlobalLocationManagementService;
use Barama\Services\CloudStorageService;
use Barama\Services\SecurityAuditService;
use Barama\Services\SecurityFactorService;
use Barama\Services\SecurityProtectionService;
use Barama\Services\ProfileAvatarService;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class WorkspaceSettingsController
{
    /* BARAMA_GLOBAL_LOCATIONS_V101 */
    private const SECTIONS = ['basic', 'profile', 'privacy', 'security', 'storage', 'cloud-storage',
        'locations',
    ];

    public function accountIndex(Request $request): Response { return $this->show($request, 'account', 'basic'); }
    public function account(Request $request, string $section): Response { return $this->show($request, 'account', $section); }
    public function developerIndex(Request $request): Response { return $this->show($request, 'developer', 'basic'); }
    public function developer(Request $request, string $section): Response { return $this->show($request, 'developer', $section); }
    public function dashboardIndex(Request $request): Response { return $this->show($request, 'dashboard', 'basic'); }
    public function dashboardLocations(Request $request): Response { return $this->show($request, 'dashboard', 'locations'); }
    public function saveDashboardLocations(Request $request): Response { return $this->save($request, 'dashboard', 'locations'); }
    public function dashboard(Request $request, string $section): Response { return $this->show($request, 'dashboard', $section); }

    public function saveAccount(Request $request, string $section): Response { return $this->save($request, 'account', $section); }
    public function saveDeveloper(Request $request, string $section): Response { return $this->save($request, 'developer', $section); }
    public function saveDashboard(Request $request, string $section): Response { return $this->save($request, 'dashboard', $section); }

    public function authorizeChange(Request $request): Response
    {
        $user = auth_user();
        if ($user === null) {
            return Response::json(['ok' => false, 'message' => 'Authentication required.'], 401);
        }

        try {
            $grant = (new SecurityFactorService())->issueChangeGrant(
                (int) $user['id'],
                trim((string) $request->input('passcode', ''))
            );

            return Response::json(['ok' => true, 'grant' => $grant]);
        } catch (DomainException|InvalidArgumentException|RuntimeException $exception) {
            return Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function show(Request $request, string $workspace, string $section): Response
    {
        $section = strtolower(trim($section));
        if (
            !in_array($section, self::SECTIONS, true)
            || (in_array($section, ['storage', 'cloud-storage'], true) && $workspace !== 'dashboard')
        ) {
            return Response::html(View::render('errors/404', ['pageTitle' => 'Page not found']), 404);
        }

        $user = auth_user();
        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        $userId = (int) $user['id'];
        $profile = (new AccountSettingsRepository())->profile($userId);
        $preferences = (new WorkspaceSettingsRepository())->get($userId, $workspace);
        $meta = $this->meta($workspace, $section);

        $locationRepository = $workspace === 'dashboard' && $section === 'locations'
            ? new GlobalLocationRepository()
            : null;
        $locationCountries = $locationRepository instanceof GlobalLocationRepository
            ? $locationRepository->countries()
            : [];
        $locationSummary = $locationRepository instanceof GlobalLocationRepository
            ? $locationRepository->summary()
            : [];
        $locationMode = $locationRepository instanceof GlobalLocationRepository
            ? $locationRepository->mode()
            : GlobalLocationRepository::MODE_SELECTED;
        $data = [
            'pageTitle' => $meta['title'] . ' · ' . $meta['workspace_label'],
            'activeArea' => $workspace === 'dashboard' ? 'dashboard' : $workspace,
            'workspaceType' => $workspace === 'dashboard' ? 'admin' : $workspace,
            'workspaceLabel' => $meta['workspace_label'],
            'workspaceNavigation' => $this->navigation($workspace),
            'activeSection' => $section,
            'settingsSection' => $section,
            'settingsTitle' => $meta['title'],
            'settingsDescription' => $meta['description'],
            'settingsWorkspace' => $workspace,
            'settingsBasePath' => $meta['base'],
            'profile' => $profile,
            'preferences' => $preferences,
            'storageDiagnostics' => $workspace === 'dashboard' && $section === 'storage'
                ? (new AdminSettingsService())->storageDiagnostics($preferences)
                : [],
            'locationCountries' => $locationCountries,
            'locationSummary' => $locationSummary,
            'locationMode' => $locationMode,
            'timezones' => timezone_identifiers_list(),
            'saved' => (string) $request->input('saved', '') === '1',
            'savedMessage' => trim((string) $request->input('message', '')),
            'error' => trim((string) $request->input('error', '')),
            'securityAutoOpen' => $section === 'security'
                ? trim((string) $request->input('security_modal', ''))
                : '',
        ];

        if ($section === 'security') {
            try {
                $data['securityFactors'] = (new SecurityFactorService())->viewModel($userId);
            } catch (RuntimeException $exception) {
                $data['securityFactors'] = [];
                if ((string) ($data['error'] ?? '') === '') {
                    $data['error'] = $exception->getMessage();
                }
            }
            $recoveryCodes = Session::pull('security.mfa_recovery_codes', []);
            $data['mfaRecoveryCodes'] = is_array($recoveryCodes)
                ? array_values(array_map('strval', $recoveryCodes))
                : [];

            if ($workspace === 'dashboard') {
                try {
                    $data['securityProtection'] = (new SecurityProtectionService())->viewModel();
                } catch (RuntimeException $exception) {
                    $data['securityProtection'] = [];
                    if ((string) ($data['error'] ?? '') === '') {
                        $data['error'] = $exception->getMessage();
                    }
                }
            }
        }

        if ($section === 'cloud-storage') {
            try {
                $data['cloudStorage'] = (new CloudStorageService())->viewModel();
            } catch (RuntimeException $exception) {
                $data['cloudStorage'] = [];
                if ((string) ($data['error'] ?? '') === '') {
                    $data['error'] = $exception->getMessage();
                }
            }

            return Response::html(View::render('workspace/cloud-storage', $data));
        }

        return Response::html(View::render('workspace/settings', $data));
    }

    private function save(Request $request, string $workspace, string $section): Response
    {
        $section = strtolower(trim($section));
        if (
            !in_array($section, self::SECTIONS, true)
            || (in_array($section, ['storage', 'cloud-storage'], true) && $workspace !== 'dashboard')
        ) {
            return Response::html(View::render('errors/404', ['pageTitle' => 'Page not found']), 404);
        }

        $user = auth_user();
        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        $userId = (int) $user['id'];
        $base = $this->meta($workspace, $section)['base'];
        $target = $base . ($section === 'basic' ? '' : '/' . $section);
        $securityModal = '';

        try {
            if ($section === 'cloud-storage') {
                $cloudService = new CloudStorageService();
                $input = $request->all();
                $action = strtolower(trim((string) ($input['cloud_action'] ?? '')));
                $message = match ($action) {
                    'save_provider' => $this->saveCloudProvider($cloudService, $input),
                    'test' => $this->testCloudProvider($cloudService, $input),
                    'activate' => $this->activateCloudStorage($cloudService, $input),
                    default => throw new InvalidArgumentException('Select a valid cloud storage action.'),
                };

                return Response::redirect(url(
                    $target . '?saved=1&message=' . rawurlencode($message)
                ));
            }

            $profileRepo = new AccountSettingsRepository();
            $profile = $profileRepo->profile($userId);
            $settingsRepo = new WorkspaceSettingsRepository();
            $preferences = $settingsRepo->get($userId, $workspace);
            $service = new AccountSettingsService();
            $input = $request->all();
            $message = 'Settings saved.';

            if ($section === 'basic') {
                if ($workspace === 'account') {
                    $basicAction = (string) ($input['basic_action'] ?? 'save');
                    $isReset = $basicAction === 'reset';

                    $service->updateProfile($userId, [
                        'name' => (string) ($profile['name'] ?? $user['name'] ?? ''),
                        'phone' => (string) ($profile['phone'] ?? ''),
                        'timezone' => $isReset
                            ? 'UTC'
                            : (string) ($input['timezone'] ?? $profile['timezone'] ?? 'UTC'),
                        'preferred_language' => $isReset
                            ? 'en'
                            : (string) ($input['preferred_language'] ?? $profile['preferred_language'] ?? 'en'),
                    ]);
                    $message = $isReset
                        ? 'Customer account defaults reset to English and UTC.'
                        : 'Customer account defaults updated.';
                } else {
                    $pageSize = (int) ($input['page_size'] ?? 20);
                    if (!in_array($pageSize, [10, 20, 50], true)) {
                        throw new InvalidArgumentException('Select a valid records-per-page value.');
                    }

                    $sort = (string) ($input['default_sort'] ?? 'updated_desc');
                    if (!in_array($sort, ['updated_desc', 'updated_asc', 'name_asc'], true)) {
                        throw new InvalidArgumentException('Select a valid default sort order.');
                    }

                    $preferences['page_size'] = $pageSize;
                    $preferences['default_sort'] = $sort;

                    if ($workspace === 'dashboard') {
                        $defaultTheme = strtolower(trim((string) ($input['default_theme'] ?? 'light')));
                        if (!in_array($defaultTheme, ['light', 'dark'], true)) {
                            throw new InvalidArgumentException('Select Light or Dark as the default appearance.');
                        }
                        $preferences['default_theme'] = $defaultTheme;
                    }

                    if ($workspace === 'developer') {
                        $currency = strtoupper(trim((string) ($input['default_currency'] ?? 'USD')));
                        if (!in_array($currency, ['USD', 'KHR', 'THB'], true)) {
                            throw new InvalidArgumentException('Select a supported default currency.');
                        }
                        $preferences['default_currency'] = $currency;
                        $message = 'Vendor / Developer workspace defaults updated.';
                    } else {
                        $message = 'Admin Dashboard defaults updated.';
                    }

                    $settingsRepo->save($userId, $workspace, $preferences);

                    if ($workspace === 'dashboard') {
                        (new GlobalThemeRepository())->save(
                            (string) ($preferences['default_theme'] ?? 'light')
                        );
                    }
                }
            } elseif ($section === 'profile') {
                $profileAction = (string) ($input['profile_action'] ?? 'details');

                if ($workspace === 'account' && $profileAction === 'avatar_upload') {
                    $files = $request->files();
                    $avatar = $files['avatar'] ?? null;

                    if (!is_array($avatar)) {
                        throw new InvalidArgumentException('Choose a profile avatar to upload.');
                    }

                    (new ProfileAvatarService())->replace($userId, $avatar);
                    (new SecurityAuditService())->record(
                        'account.profile_avatar_updated',
                        $userId,
                        $userId
                    );
                    $message = 'Profile avatar updated.';
                } elseif ($workspace === 'account' && $profileAction === 'avatar_remove') {
                    (new ProfileAvatarService())->remove($userId);
                    (new SecurityAuditService())->record(
                        'account.profile_avatar_removed',
                        $userId,
                        $userId
                    );
                    $message = 'Profile avatar removed.';
                } else {
                    $service->updateProfile($userId, [
                        'name' => (string) ($input['name'] ?? $profile['name'] ?? ''),
                        'phone' => (string) ($input['phone'] ?? $profile['phone'] ?? ''),
                        'date_of_birth' => $workspace === 'account'
                            ? (string) ($input['date_of_birth'] ?? $profile['date_of_birth'] ?? '')
                            : (string) ($profile['date_of_birth'] ?? ''),
                        'address' => $workspace === 'account'
                            ? (string) ($input['address'] ?? $profile['address'] ?? '')
                            : (string) ($profile['address'] ?? ''),
                        'timezone' => (string) ($profile['timezone'] ?? 'UTC'),
                        'preferred_language' => (string) ($profile['preferred_language'] ?? 'en'),
                    ]);

                    if ($workspace !== 'account') {
                        $preferences['role_title'] = mb_substr(
                            trim((string) ($input['role_title'] ?? '')),
                            0,
                            100
                        );
                        $settingsRepo->save($userId, $workspace, $preferences);
                    }

                    $message = match ($workspace) {
                        'dashboard' => 'Administrator profile updated.',
                        'developer' => 'Vendor / Developer operator profile updated.',
                        default => 'Customer profile updated.',
                    };
                }
            } elseif ($section === 'privacy') {
                $privacyAction = (string) ($input['privacy_action'] ?? 'save');

                if ($workspace === 'account') {
                    if ($privacyAction === 'clear_history') {
                        $service->clearRecentViews($userId);
                        $message = 'Recently viewed property history cleared.';
                    } elseif ($privacyAction === 'clear_compare') {

                        $profileRepo->clearComparisonItems($userId);

                        $message = 'Property comparison list cleared.';

                    } else {
                        $service->updatePrivacy($userId, $input);
                        $message = 'Customer privacy preference updated.';
                    }
                } else {
                    if ($privacyAction === 'clear_filters') {
                        unset($preferences['last_filters']);
                        $settingsRepo->save($userId, $workspace, $preferences);
                        $message = 'Remembered workspace filters cleared.';
                    } else {
                        $preferences['remember_filters'] = isset($input['remember_filters'])
                            && (string) $input['remember_filters'] === '1';

                        if (!$preferences['remember_filters']) {
                            unset($preferences['last_filters']);
                        }

                        // Remove unused v1.0.1 placeholders so every saved setting has real behavior.
                        unset($preferences['team_contact_visibility'], $preferences['mask_audit_origin']);
                        $settingsRepo->save($userId, $workspace, $preferences);
                        $message = $workspace === 'developer'
                            ? 'Vendor / Developer filter privacy updated.'
                            : 'Admin Dashboard filter privacy updated.';
                    }
                }
            } elseif ($section === 'storage') {
                if ($workspace !== 'dashboard') {
                    throw new InvalidArgumentException(
                        'Storage settings are available only in Admin Dashboard.'
                    );
                }

                $storageService = new AdminSettingsService();
                $action = (string) ($input['storage_action'] ?? 'save');

                if ($action === 'save') {
                    $storageConfig = $storageService->saveStorageConfig($input);

                    (new SecurityAuditService())->record(
                        'admin.storage_settings_updated',
                        $userId,
                        $userId,
                        [
                            'site_media_upload_limit_mb' =>
                                (int) $storageConfig['site_media_upload_limit_mb'],
                            'temp_retention_hours' =>
                                (int) $storageConfig['temp_retention_hours'],
                        ]
                    );

                    $message = 'Admin storage policy updated.';
                } elseif ($action === 'test_write') {
                    $storageService->testWrite();

                    (new SecurityAuditService())->record(
                        'admin.storage_write_test',
                        $userId,
                        $userId,
                        ['result' => 'pass']
                    );

                    $message = 'Storage write test passed.';
                } elseif ($action === 'clear_temp') {
                    $storageConfig = $storageService->storageConfig();
                    $retention = (int) $storageConfig['temp_retention_hours'];
                    $result = $storageService->cleanupTemp($retention);

                    (new SecurityAuditService())->record(
                        'admin.storage_temp_cleanup',
                        $userId,
                        $userId,
                        [
                            'retention_hours' => $retention,
                            'files_deleted' => (int) $result['files'],
                            'bytes_deleted' => (int) $result['bytes'],
                        ]
                    );

                    $message = 'Temporary storage cleanup completed: '
                        . (string) $result['files']
                        . ' file(s) removed.';
                } else {
                    throw new InvalidArgumentException(
                        'Select a valid storage action.'
                    );
                }
            } elseif ($section === 'locations') {
                if ($workspace !== 'dashboard') {
                    throw new InvalidArgumentException(
                        'Location governance is available only in Admin Dashboard.'
                    );
                }

                $locationService = new GlobalLocationManagementService();
                $action = (string) ($input['location_action'] ?? '');

                if ($action === 'mode') {
                    $locationService->setMode((string) ($input['location_mode'] ?? 'selected'));
                    $message = 'Marketplace location scope updated.';
                } elseif ($action === 'country_status') {
                    $locationService->setCountryStatus(
                        (string) ($input['country_code'] ?? ''),
                        (string) ($input['enabled'] ?? '0') === '1'
                    );
                    $message = 'Country availability updated.';
                } elseif ($action === 'default_country') {
                    $locationService->setDefaultCountry((string) ($input['country_code'] ?? ''));
                    $message = 'Default marketplace country updated.';
                } else {
                    throw new InvalidArgumentException('Select a valid location-management action.');
                }
            } elseif ($section === 'security') {
                $action = strtolower(trim((string) ($input['security_action'] ?? '')));
                $factorService = new SecurityFactorService();

                if ($action === 'password') {
                    $service->changePassword($userId, $input);
                    $message = 'Password updated and other authenticated sessions invalidated.';
                } elseif ($action === 'email') {
                    $service->requestEmailChange($userId, $input);
                    $message = 'Email confirmation sent to the new address.';
                } elseif ($action === 'passcode_set') {
                    $factorService->setPasscode($userId, $input);
                    $message = '6-digit change passcode is enabled for this account.';
                } elseif ($action === 'passcode_disable') {
                    $factorService->disablePasscode($userId, $input);
                    $message = 'Change passcode requirement is disabled.';
                } elseif ($action === 'mfa_begin') {
                    $securityModal = 'mfa';
                    $factorService->beginMfaSetup(
                        $userId,
                        (string) ($input['current_password'] ?? '')
                    );
                    $message = 'Identity confirmed. Scan the QR code and verify one authenticator code to finish MFA setup.';
                } elseif ($action === 'mfa_enable') {
                    $securityModal = 'mfa';
                    $codes = $factorService->enableMfa(
                        $userId,
                        (string) ($input['mfa_code'] ?? '')
                    );
                    Session::put('security.mfa_recovery_codes', $codes);
                    $message = 'Multi-factor authentication is enabled. Save the recovery codes shown below.';
                } elseif ($action === 'mfa_cancel') {
                    $securityModal = 'mfa';
                    $factorService->cancelMfaSetup(
                        $userId,
                        (string) ($input['current_password'] ?? '')
                    );
                    $message = 'Pending MFA setup was cancelled.';
                } elseif ($action === 'mfa_recovery_regenerate') {
                    $securityModal = 'mfa';
                    $codes = $factorService->regenerateRecoveryCodes($userId, $input);
                    Session::put('security.mfa_recovery_codes', $codes);
                    $message = 'New MFA recovery codes generated. Previous recovery codes are no longer valid.';
                } elseif ($action === 'mfa_disable') {
                    $securityModal = 'mfa';
                    $factorService->disableMfa($userId, $input);
                    $message = 'Multi-factor authentication is disabled.';
                } elseif ($workspace === 'dashboard' && $action === 'spam_protection_save') {
                    $securityModal = 'spam';
                    (new SecurityProtectionService())->saveSpam($input);
                    $securityModal = '';
                    $message = 'Spam Protection settings are active.';
                } elseif ($workspace === 'dashboard' && $action === 'spam_counters_clear') {
                    $securityModal = 'spam';
                    (new SecurityProtectionService())->clearSpamCounters();
                    $message = 'Spam Protection counters were cleared.';
                } elseif ($workspace === 'dashboard' && $action === 'hack_protection_save') {
                    $securityModal = 'hack';
                    (new SecurityProtectionService())->saveHack($input);
                    $securityModal = '';
                    $message = 'Hack Protection settings are active.';
                } elseif ($workspace === 'dashboard' && $action === 'hack_alert_test') {
                    $securityModal = 'hack';
                    (new SecurityProtectionService())->sendTestHackAlert();
                    $message = 'A hacking-alert test was queued for delivery.';
                } elseif ($workspace === 'dashboard' && $action === 'right_click_protection_save') {
                    $securityModal = 'right-click';
                    (new SecurityProtectionService())->saveRightClick($input);
                    $securityModal = '';
                    $message = 'Right Click Protection settings are active.';
                } elseif ($action === 'deactivate' && $workspace === 'account') {
                    if (trim((string) ($input['confirm_phrase'] ?? '')) !== 'DEACTIVATE') {
                        throw new InvalidArgumentException('Type DEACTIVATE to confirm account deactivation.');
                    }
                    $service->deactivateAccount($userId, $input);
                    return Response::redirect(url('/'));
                } else {
                    throw new InvalidArgumentException('Select a valid security action.');
                }

                if (array_key_exists('security_alerts', $preferences)) {
                    unset($preferences['security_alerts']);
                    $settingsRepo->save($userId, $workspace, $preferences);
                }
            }

            $query = '?saved=1&message=' . rawurlencode($message);
            if ($securityModal !== '') {
                $query .= '&security_modal=' . rawurlencode($securityModal);
            }

            return Response::redirect(url($target . $query));
        } catch (InvalidArgumentException|DomainException|RuntimeException $exception) {
            $query = '?error=' . rawurlencode($exception->getMessage());
            if ($securityModal !== '') {
                $query .= '&security_modal=' . rawurlencode($securityModal);
            }

            return Response::redirect(url($target . $query));
        }
    }

    /** @param array<string,mixed> $input */
    private function saveCloudProvider(CloudStorageService $service, array $input): string
    {
        $provider = strtolower(trim((string) ($input['provider'] ?? '')));
        $service->saveProvider($provider, $input);
        return strtoupper($provider) . ' configuration saved. Run the connection test before activation.';
    }

    /** @param array<string,mixed> $input */
    private function testCloudProvider(CloudStorageService $service, array $input): string
    {
        $provider = strtolower(trim((string) ($input['provider'] ?? '')));
        $service->testProvider($provider);
        return strtoupper($provider) . ' write/read/delete test passed.';
    }

    /** @param array<string,mixed> $input */
    private function activateCloudStorage(CloudStorageService $service, array $input): string
    {
        $disk = strtolower(trim((string) ($input['disk'] ?? 'private')));
        $service->activate($disk);
        return $disk === 'private'
            ? 'Local private storage is active for new uploads.'
            : strtoupper($disk) . ' is active for new uploads.';
    }

    /** @return array{title:string,description:string,workspace_label:string,base:string} */
    private function meta(string $workspace, string $section): array
    {
        $workspaceLabel = match ($workspace) {
            'dashboard' => 'Admin Dashboard',
            'developer' => 'Vendor / Developer',
            default => 'Customer Account',
        };
        $base = match ($workspace) {
            'dashboard' => '/dashboard/settings',
            'developer' => '/developer/settings',
            default => '/account/settings',
        };
        $descriptions = [
            'account' => [
                'basic' => 'Set the language and timezone used by your customer account.',
                'profile' => 'Maintain your customer identity and contact details.',
                'privacy' => 'Control recently viewed-property history and clear stored activity whenever you choose.',
                'security' => 'Manage sign-in credentials, verified email, and account lifecycle securely.',
            ],
            'developer' => [
                'basic' => 'Control Vendor / Developer record density, sorting, and the default listing currency.',
                'profile' => 'Maintain the operator identity used in your company workspace.',
                'privacy' => 'Choose whether workspace filters persist between visits and clear them on demand.',
                'security' => 'Manage sign-in credentials and review the security state of your operator account.',
            ],
            'dashboard' => [
                'basic' => 'Control Admin Dashboard record density, default sorting, and the default Light or Dark appearance.',
                'profile' => 'Maintain the administrator identity used for operational work.',
                'privacy' => 'Choose whether Admin Dashboard filters persist between visits and clear them on demand.',
                'security' => 'Manage administrator sign-in credentials and review account security state.',
                'storage' => 'Inspect application storage health, control Admin-managed Hero and site-media upload limits, and clean temporary files safely.',
                'locations' => 'Control which countries are available and govern the global real-estate location hierarchy.',
                'cloud-storage' => 'Configure encrypted Amazon S3 and Cloudflare R2 storage for Barama media.',
            ],
        ];
        return [
            'title' => $section === 'cloud-storage' ? 'Cloud Storage' : ucfirst($section) . ' Settings',
            'description' => $descriptions[$workspace][$section],
            'workspace_label' => $workspaceLabel,
            'base' => $base,
        ];
    }

    /** @return array<int,array<string,string>> */
    private function navigation(string $workspace): array
    {
        if ($workspace === 'dashboard') {
            return [
                ['heading' => 'Control Center'],
                ['label' => 'Overview', 'path' => '/dashboard', 'icon' => 'grid'],
                ['label' => 'Approval Queue', 'path' => '/dashboard/approvals', 'icon' => 'shield'],

                ['heading' => 'Marketplace'],
                ['label' => 'Listings', 'path' => '/dashboard/listings', 'icon' => 'tag'],

                ['heading' => 'CRM'],
                ['label' => 'Inquiries', 'path' => '/dashboard/inquiries', 'icon' => 'message'],
                ['label' => 'Property Leads', 'path' => '/dashboard/leads', 'icon' => 'pipeline'],
                ['label' => 'Viewing Appointments', 'path' => '/dashboard/viewings', 'icon' => 'calendar'],

                ['heading' => 'Platform Data'],
                ['label' => 'Locations & Amenities', 'path' => '/dashboard/catalog', 'icon' => 'map'],

                ['heading' => 'Content & Discovery'],
                ['label' => 'Appearance', 'path' => '/dashboard/appearance', 'icon' => 'settings'],
                ['label' => 'Homepage', 'path' => '/dashboard/homepage', 'icon' => 'home'],
                ['label' => 'Properties', 'path' => '/dashboard/properties', 'icon' => 'home'],
                ['label' => 'Projects', 'path' => '/dashboard/projects', 'icon' => 'layers'],
                ['label' => 'Companies', 'path' => '/dashboard/companies', 'icon' => 'building'],
                ['label' => 'Navigation', 'path' => '/dashboard/navigation', 'icon' => 'menu'],
                ['label' => 'About', 'path' => '/dashboard/about', 'icon' => 'users'],
                ['label' => 'Contact', 'path' => '/dashboard/contact', 'icon' => 'mail'],
                ['label' => 'Page Manager', 'path' => '/dashboard/pages', 'icon' => 'edit'],
                ['label' => 'Media Library', 'path' => '/dashboard/media', 'icon' => 'image'],
                ['label' => 'SEO Center', 'path' => '/dashboard/seo', 'icon' => 'search'],

                ['heading' => 'Finance'],
                ['label' => 'Plans & Packages', 'path' => '/dashboard/plans', 'icon' => 'layers'],
                ['label' => 'Subscriptions', 'path' => '/dashboard/subscriptions', 'icon' => 'building'],
                ['label' => 'Coupons', 'path' => '/dashboard/coupons', 'icon' => 'edit'],
                ['label' => 'Tax Rules', 'path' => '/dashboard/taxes', 'icon' => 'shield'],
                ['label' => 'Payment Providers', 'path' => '/dashboard/payment-providers', 'icon' => 'settings'],
                ['label' => 'Billing Operations', 'path' => '/dashboard/billing-operations', 'icon' => 'pipeline'],
                ['label' => 'Invoices', 'path' => '/dashboard/invoices', 'icon' => 'edit'],
                ['label' => 'Transactions', 'path' => '/dashboard/transactions', 'icon' => 'pipeline'],

                ['heading' => 'Communications'],
                ['label' => 'Notification Center', 'path' => '/dashboard/notifications', 'icon' => 'message'],
                ['label' => 'Email Templates', 'path' => '/dashboard/email-templates', 'icon' => 'mail'],
                ['label' => 'Mail Queue & Logs', 'path' => '/dashboard/mail-queue', 'icon' => 'pipeline'],

                ['heading' => 'Users & Security'],
                ['label' => 'Users', 'path' => '/dashboard/users', 'icon' => 'users'],
                ['label' => 'Verification Center', 'path' => '/dashboard/verification', 'icon' => 'check'],
                ['label' => 'Security Audit', 'path' => '/dashboard/audit', 'icon' => 'shield'],

                ['heading' => 'Settings'],
                ['label' => 'Basic', 'path' => '/dashboard/settings/basic', 'icon' => 'settings'],
                ['label' => 'Profile', 'path' => '/dashboard/settings/profile', 'icon' => 'user'],
                ['label' => 'Privacy', 'path' => '/dashboard/settings/privacy', 'icon' => 'shield'],
                ['label' => 'Security', 'path' => '/dashboard/settings/security', 'icon' => 'lock'],
                ['label' => 'Storage', 'path' => '/dashboard/settings/storage', 'icon' => 'image'],
                ['label' => 'Locations', 'path' => '/dashboard/settings/locations', 'icon' => 'map'],
                ['label' => 'Cloud Storage', 'path' => '/dashboard/settings/cloud-storage', 'icon' => 'layers'],
            ];
        }

        $main = $workspace === 'developer'
            ? [
                ['label'=>'Overview','path'=>'/developer','icon'=>'grid'],
                ['label'=>'Company','path'=>'/developer/company','icon'=>'building'],
                ['label'=>'Projects','path'=>'/developer/projects','icon'=>'layers'],
                ['label'=>'Properties','path'=>'/developer/properties','icon'=>'home'],
                ['label'=>'Listings','path'=>'/developer/listings','icon'=>'tag'],
                ['label'=>'Inquiries','path'=>'/developer/inquiries','icon'=>'message'],
                ['label'=>'Viewings','path'=>'/developer/viewings','icon'=>'calendar'],
                ['label'=>'Leads','path'=>'/developer/leads','icon'=>'pipeline'],
                ['label'=>'Team','path'=>'/developer/team','icon'=>'users'],
                ['label'=>'Media','path'=>'/developer/media','icon'=>'image'],
            ]
            : [
                ['label'=>'Overview','path'=>'/account','icon'=>'grid'],
                ['label'=>'Saved','path'=>'/account/saved','icon'=>'heart'],
                ['label'=>'Compare','path'=>'/account/compare','icon'=>'compare'],
                ['label'=>'Inquiries','path'=>'/account/inquiries','icon'=>'message'],
                ['label'=>'Viewings','path'=>'/account/viewings','icon'=>'calendar'],
                ['label'=>'Alerts','path'=>'/account/alerts','icon'=>'bell'],
                ['label'=>'Notifications','path'=>'/account/notifications','icon'=>'bell'],
                ['label'=>'Recent','path'=>'/account/recent','icon'=>'clock'],
                ['label'=>'Company Access','path'=>'/account/company','icon'=>'building'],
            ];

        $base = $workspace === 'developer'
            ? '/developer/settings'
            : '/account/settings';

        $main[] = ['heading' => 'Settings'];
        $main[] = ['label'=>'Basic','path'=>$base.'/basic','icon'=>'settings'];
        $main[] = ['label'=>'Profile','path'=>$base.'/profile','icon'=>'user'];
        $main[] = ['label'=>'Privacy','path'=>$base.'/privacy','icon'=>'shield'];
        $main[] = ['label'=>'Security','path'=>$base.'/security','icon'=>'lock'];

        return $main;
    }
}