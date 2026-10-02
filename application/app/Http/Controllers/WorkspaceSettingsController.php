<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\WorkspaceSettingsRepository;
use Barama\Services\AccountSettingsService;
use Barama\Services\AdminSettingsService;
use Barama\Services\SecurityAuditService;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class WorkspaceSettingsController
{
    private const SECTIONS = ['basic', 'profile', 'privacy', 'security', 'storage'];

    public function accountIndex(Request $request): Response { return $this->show($request, 'account', 'basic'); }
    public function account(Request $request, string $section): Response { return $this->show($request, 'account', $section); }
    public function developerIndex(Request $request): Response { return $this->show($request, 'developer', 'basic'); }
    public function developer(Request $request, string $section): Response { return $this->show($request, 'developer', $section); }
    public function dashboardIndex(Request $request): Response { return $this->show($request, 'dashboard', 'basic'); }
    public function dashboard(Request $request, string $section): Response { return $this->show($request, 'dashboard', $section); }

    public function saveAccount(Request $request, string $section): Response { return $this->save($request, 'account', $section); }
    public function saveDeveloper(Request $request, string $section): Response { return $this->save($request, 'developer', $section); }
    public function saveDashboard(Request $request, string $section): Response { return $this->save($request, 'dashboard', $section); }

    private function show(Request $request, string $workspace, string $section): Response
    {
        $section = strtolower(trim($section));
        if (
            !in_array($section, self::SECTIONS, true)
            || ($section === 'storage' && $workspace !== 'dashboard')
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

        return Response::html(View::render('workspace/settings', [
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
            'timezones' => timezone_identifiers_list(),
            'saved' => (string) $request->input('saved', '') === '1',
            'savedMessage' => trim((string) $request->input('message', '')),
            'error' => trim((string) $request->input('error', '')),
        ]));
    }

    private function save(Request $request, string $workspace, string $section): Response
    {
        $section = strtolower(trim($section));
        if (
            !in_array($section, self::SECTIONS, true)
            || ($section === 'storage' && $workspace !== 'dashboard')
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

        try {
            $profileRepo = new AccountSettingsRepository();
            $profile = $profileRepo->profile($userId);
            $settingsRepo = new WorkspaceSettingsRepository();
            $preferences = $settingsRepo->get($userId, $workspace);
            $service = new AccountSettingsService();
            $input = $request->all();
            $message = 'Settings saved.';

            if ($section === 'basic') {
                if ($workspace === 'account') {
                    $service->updateProfile($userId, [
                        'name' => (string) ($profile['name'] ?? $user['name'] ?? ''),
                        'phone' => (string) ($profile['phone'] ?? ''),
                        'timezone' => (string) ($input['timezone'] ?? $profile['timezone'] ?? 'UTC'),
                        'preferred_language' => (string) ($input['preferred_language'] ?? $profile['preferred_language'] ?? 'en'),
                    ]);
                    $message = 'Customer account defaults updated.';
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
                }
            } elseif ($section === 'profile') {
                $service->updateProfile($userId, [
                    'name' => (string) ($input['name'] ?? $profile['name'] ?? ''),
                    'phone' => (string) ($input['phone'] ?? $profile['phone'] ?? ''),
                    'timezone' => (string) ($profile['timezone'] ?? 'UTC'),
                    'preferred_language' => (string) ($profile['preferred_language'] ?? 'en'),
                ]);

                if ($workspace !== 'account') {
                    $preferences['role_title'] = mb_substr(trim((string) ($input['role_title'] ?? '')), 0, 100);
                    $settingsRepo->save($userId, $workspace, $preferences);
                }

                $message = match ($workspace) {
                    'dashboard' => 'Administrator profile updated.',
                    'developer' => 'Vendor / Developer operator profile updated.',
                    default => 'Customer profile updated.',
                };
            } elseif ($section === 'privacy') {
                $privacyAction = (string) ($input['privacy_action'] ?? 'save');

                if ($workspace === 'account') {
                    if ($privacyAction === 'clear_history') {
                        $service->clearRecentViews($userId);
                        $message = 'Recently viewed property history cleared.';
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
            } elseif ($section === 'security') {
                $action = (string) ($input['security_action'] ?? '');

                if ($action === 'password') {
                    $service->changePassword($userId, $input);
                    $message = 'Password updated and other authenticated sessions invalidated.';
                } elseif ($action === 'email') {
                    $service->requestEmailChange($userId, $input);
                    $message = 'Email confirmation sent to the new address.';
                } elseif ($action === 'deactivate' && $workspace === 'account') {
                    if (trim((string) ($input['confirm_phrase'] ?? '')) !== 'DEACTIVATE') {
                        throw new InvalidArgumentException('Type DEACTIVATE to confirm account deactivation.');
                    }
                    $service->deactivateAccount($userId, $input);
                    return Response::redirect(url('/'));
                } else {
                    throw new InvalidArgumentException('Select a valid security action.');
                }

                // Remove the unused v1.0.1 security-alert placeholder.
                if (array_key_exists('security_alerts', $preferences)) {
                    unset($preferences['security_alerts']);
                    $settingsRepo->save($userId, $workspace, $preferences);
                }
            }

            return Response::redirect(url(
                $target . '?saved=1&message=' . rawurlencode($message)
            ));
        } catch (InvalidArgumentException|DomainException|RuntimeException $exception) {
            return Response::redirect(url(
                $target . '?error=' . rawurlencode($exception->getMessage())
            ));
        }
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
                'basic' => 'Control Admin Dashboard record density and default operational sorting.',
                'profile' => 'Maintain the administrator identity used for operational work.',
                'privacy' => 'Choose whether Admin Dashboard filters persist between visits and clear them on demand.',
                'security' => 'Manage administrator sign-in credentials and review account security state.',
                'storage' => 'Inspect application storage health, control Admin-managed Hero and site-media upload limits, and clean temporary files safely.',
            ],
        ];
        return [
            'title' => ucfirst($section) . ' Settings',
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