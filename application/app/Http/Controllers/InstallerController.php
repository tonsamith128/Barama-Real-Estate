<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Csrf;
use Barama\Core\Installation;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\Session;
use Barama\Core\View;
use Barama\Services\InstallerService;
use Barama\Services\LicenseManagerClient;
use RuntimeException;
use Throwable;

final class InstallerController
{
    private const STEP_KEY = 'installer.step';
    private const TERMS_KEY = 'installer.terms_accepted';
    private const LICENSE_KEY = 'installer.license';
    private const DATABASE_KEY = 'installer.database';
    private const MAIL_TEST_KEY = 'installer.mail_test';
    private const FINISHED_KEY = 'installer.finished';

    public function handle(): void
    {
        $request = Request::capture();
        $service = new InstallerService();

        if (Installation::isInstalled()) {
            if (Session::get(self::FINISHED_KEY, false) === true) {
                $this->render($service, 5);
                return;
            }

            $this->redirect($service->basePath() . '/');
            return;
        }

        if ($request->method() === 'POST') {
            $this->handlePost($request, $service);
            return;
        }

        $requested = filter_var(
            $request->input('step', Session::get(self::STEP_KEY, 1)),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 5]]
        );

        $step = $requested === false ? 1 : (int) $requested;
        $allowed = max(1, min(4, (int) Session::get(self::STEP_KEY, 1)));

        if ($step > $allowed) {
            $step = $allowed;
        }

        if ($step === 1) {
            Session::forget(self::TERMS_KEY);
        }

        $this->render($service, $step);
    }

    private function handlePost(
        Request $request,
        InstallerService $service
    ): void {
        if (!Csrf::validate((string) $request->input('_token', ''))) {
            $this->respondError(
                $request,
                'Your installation session expired. Refresh the page and try again.',
                419
            );
            return;
        }

        $action = trim((string) $request->input('action', ''));

        try {
            match ($action) {
                'accept_terms' => $this->acceptTerms($request),
                'validate_license' => $this->validateLicense(
                    $request,
                    $service
                ),
                'continue_license' => $this->continueLicense(
                    $request,
                    $service
                ),
                'continue_requirements' => $this->continueRequirements(
                    $request,
                    $service
                ),
                'test_database' => $this->testDatabase(
                    $request,
                    $service
                ),
                'test_mail' => $this->testMail(
                    $request,
                    $service
                ),
                'install' => $this->install($request, $service),
                default => throw new RuntimeException(
                    'The installer action is invalid.'
                ),
            };
        } catch (RuntimeException $exception) {
            $this->respondError(
                $request,
                $exception->getMessage(),
                422
            );
        } catch (Throwable) {
            $this->respondError(
                $request,
                'The installer could not complete this request. Check the server requirements and try again.',
                500
            );
        }
    }

    private function acceptTerms(Request $request): never
    {
        Session::put(self::TERMS_KEY, [
            'accepted' => true,
            'accepted_at' => gmdate('c'),
        ]);

        $this->json([
            'ok' => true,
            'data' => [
                'accepted' => true,
                'message' => 'Terms accepted. You may continue after the license is validated.',
            ],
        ]);
    }

    private function validateLicense(
        Request $request,
        InstallerService $service
    ): never {
        $license = (new LicenseManagerClient())->activate(
            (string) $request->input('purchase_code', ''),
            $service->detectedDomain(),
            Installation::installationId(),
            'auto'
        );

        Session::put(self::LICENSE_KEY, $license);

        $this->json([
            'ok' => true,
            'data' => [
                'validated' => true,
                'message' => 'License validated successfully.',
            ],
        ]);
    }

    private function continueLicense(
        Request $request,
        InstallerService $service
    ): never {
        $terms = Session::get(self::TERMS_KEY, []);
        $license = Session::get(self::LICENSE_KEY, []);

        if (
            !is_array($terms)
            || ($terms['accepted'] ?? false) !== true
            || (string) $request->input('agree_terms', '') !== '1'
        ) {
            throw new RuntimeException(
                'Review and accept the terms before continuing.'
            );
        }

        if (
            !is_array($license)
            || trim((string) ($license['activation_token'] ?? '')) === ''
        ) {
            throw new RuntimeException(
                'Validate the license before continuing.'
            );
        }

        Session::put(self::STEP_KEY, 2);
        $this->redirect($service->installerUrl('step=2'));
    }

    private function continueRequirements(
        Request $request,
        InstallerService $service
    ): never {
        $this->requireReachedStep(2);
        $this->requireLicense();

        if (!$service->requirementsReady()) {
            throw new RuntimeException(
                'Resolve the Fix Required checks before continuing.'
            );
        }

        Session::put(self::STEP_KEY, 3);
        $this->redirect($service->installerUrl('step=3'));
    }

    private function testDatabase(
        Request $request,
        InstallerService $service
    ): never {
        $this->requireReachedStep(3);
        $this->requireLicense();

        $mode = strtolower(trim(
            (string) $request->input('db_mode', 'existing')
        ));

        Session::put('installer.database_old', [
            'db_mode' => in_array($mode, ['existing', 'create'], true)
                ? $mode
                : 'existing',
            'db_host' => trim((string) $request->input(
                'db_host',
                '127.0.0.1'
            )),
            'db_port' => (string) $request->input('db_port', 3306),
            'db_database' => trim((string) $request->input(
                'db_database',
                ''
            )),
            'db_username' => trim((string) $request->input(
                'db_username',
                ''
            )),
            'db_admin_host' => trim((string) $request->input(
                'db_admin_host',
                '127.0.0.1'
            )),
            'db_admin_port' => (string) $request->input(
                'db_admin_port',
                3306
            ),
            'db_admin_username' => trim((string) $request->input(
                'db_admin_username',
                ''
            )),
            'db_new_database' => trim((string) $request->input(
                'db_new_database',
                ''
            )),
            'db_new_username' => trim((string) $request->input(
                'db_new_username',
                ''
            )),
        ]);

        $database = $service->testDatabase([
            'db_mode' => $mode,
            'db_host' => $request->input('db_host', ''),
            'db_port' => $request->input('db_port', 3306),
            'db_database' => $request->input('db_database', ''),
            'db_username' => $request->input('db_username', ''),
            'db_password' => $request->input('db_password', ''),
            'db_admin_host' => $request->input(
                'db_admin_host',
                '127.0.0.1'
            ),
            'db_admin_port' => $request->input('db_admin_port', 3306),
            'db_admin_username' => $request->input(
                'db_admin_username',
                ''
            ),
            'db_admin_password' => $request->input(
                'db_admin_password',
                ''
            ),
            'db_new_database' => $request->input(
                'db_new_database',
                ''
            ),
            'db_new_username' => $request->input(
                'db_new_username',
                ''
            ),
            'db_new_password' => $request->input(
                'db_new_password',
                ''
            ),
            'db_new_password_confirmation' => $request->input(
                'db_new_password_confirmation',
                ''
            ),
        ]);

        Session::put(self::DATABASE_KEY, $database);
        Session::put(self::STEP_KEY, 4);

        $this->redirect($service->installerUrl('step=4'));
    }

    private function testMail(
        Request $request,
        InstallerService $service
    ): never {
        $this->requireReachedStep(4);
        $this->requireLicense();

        $database = Session::get(self::DATABASE_KEY, []);

        if (!is_array($database) || $database === []) {
            throw new RuntimeException(
                'Test the database connection before testing email delivery.'
            );
        }

        Session::forget(self::MAIL_TEST_KEY);

        $result = $service->testMail(
            $this->mailInput($request),
            (string) $request->input('admin_email', ''),
            (string) $request->input(
                'site_name',
                'Barama Real Estate'
            )
        );

        Session::put(self::MAIL_TEST_KEY, [
            'fingerprint' => (string) $result['fingerprint'],
            'recipient' => strtolower(trim(
                (string) $request->input('admin_email', '')
            )),
            'tested_at' => gmdate('c'),
        ]);

        $this->json([
            'ok' => true,
            'data' => [
                'tested' => true,
                'message' => 'Test email sent successfully.',
            ],
        ]);
    }

    private function install(
        Request $request,
        InstallerService $service
    ): never {
        $this->requireReachedStep(4);
        $license = $this->requireLicense();
        $license = $this->revalidateLicenseForInstall($license, $service);
        $database = Session::get(self::DATABASE_KEY, []);

        if (!is_array($database) || $database === []) {
            throw new RuntimeException(
                'Test the database connection before installing.'
            );
        }

        $websiteInput = [
            'site_name' => $request->input(
                'site_name',
                'Barama Real Estate'
            ),
            'site_url' => $request->input(
                'site_url',
                $service->detectedSiteUrl()
            ),
            'timezone' => $request->input(
                'timezone',
                'Asia/Phnom_Penh'
            ),
            'default_language' => $request->input(
                'default_language',
                'en'
            ),
            'admin_name' => $request->input('admin_name', ''),
            'admin_email' => $request->input('admin_email', ''),
            'admin_password' => $request->input(
                'admin_password',
                ''
            ),
            'admin_password_confirmation' => $request->input(
                'admin_password_confirmation',
                ''
            ),
            ...$this->mailInput($request),
        ];

        Session::put('installer.website_old', [
            'site_name' => trim((string) $websiteInput['site_name']),
            'site_url' => trim((string) $websiteInput['site_url']),
            'timezone' => trim((string) $websiteInput['timezone']),
            'default_language' => trim(
                (string) $websiteInput['default_language']
            ),
            'admin_name' => trim((string) $websiteInput['admin_name']),
            'admin_email' => trim(
                (string) $websiteInput['admin_email']
            ),
            'mail_driver' => trim(
                (string) $websiteInput['mail_driver']
            ),
            'mail_from_address' => trim(
                (string) $websiteInput['mail_from_address']
            ),
            'mail_from_name' => trim(
                (string) $websiteInput['mail_from_name']
            ),
            'mail_host' => trim(
                (string) $websiteInput['mail_host']
            ),
            'mail_port' => trim(
                (string) $websiteInput['mail_port']
            ),
            'mail_encryption' => trim(
                (string) $websiteInput['mail_encryption']
            ),
            'mail_username' => trim(
                (string) $websiteInput['mail_username']
            ),
        ]);

        $mail = $service->validateMail(
            $websiteInput,
            (string) $websiteInput['admin_email'],
            (string) $websiteInput['site_name']
        );
        $mailTest = Session::get(self::MAIL_TEST_KEY, []);
        $fingerprint = $service->mailFingerprint($mail);

        if (
            !is_array($mailTest)
            || !isset($mailTest['fingerprint'], $mailTest['recipient'])
            || !hash_equals(
                (string) $mailTest['fingerprint'],
                $fingerprint
            )
            || !hash_equals(
                strtolower(trim((string) $mailTest['recipient'])),
                strtolower(trim((string) $websiteInput['admin_email']))
            )
        ) {
            throw new RuntimeException(
                'Send a successful test email after entering the email delivery settings.'
            );
        }

        Session::forget(self::MAIL_TEST_KEY);

        $service->install($database, $websiteInput, $license);

        Session::forget(self::DATABASE_KEY);
        Session::forget(self::LICENSE_KEY);
        Session::forget(self::TERMS_KEY);
        Session::forget(self::MAIL_TEST_KEY);
        Session::forget('installer.database_old');
        Session::forget('installer.website_old');
        Session::put(self::STEP_KEY, 5);
        Session::put(self::FINISHED_KEY, true);
        Session::regenerate();

        $this->redirect($service->installerUrl('step=5'));
    }

    private function render(
        InstallerService $service,
        int $step
    ): void {
        $oldDatabase = Session::get('installer.database_old', []);
        $oldWebsite = Session::get('installer.website_old', []);
        $error = Session::pull('installer.error');
        $terms = Session::get(self::TERMS_KEY, []);
        $license = Session::get(self::LICENSE_KEY, []);

        $html = View::render(
            'installer/index',
            [
                'pageTitle' => 'Install Barama Real Estate',
                'step' => $step,
                'reachedStep' => max(
                    $step,
                    (int) Session::get(self::STEP_KEY, 1)
                ),
                'termsReviewed' => is_array($terms)
                    && ($terms['accepted'] ?? false) === true,
                'licenseValidated' => is_array($license)
                    && trim(
                        (string) ($license['activation_token'] ?? '')
                    ) !== '',
                'requirements' => $service->requirements(),
                'requirementsReady' => $service->requirementsReady(),
                'oldDatabase' => is_array($oldDatabase)
                    ? $oldDatabase
                    : [],
                'oldWebsite' => is_array($oldWebsite)
                    ? $oldWebsite
                    : [],
                'siteUrl' => $service->detectedSiteUrl(),
                'languages' => $service->supportedLanguages(),
                'timezones' => $service->commonTimezones(),
                'health' => $step === 5
                    ? $service->health()
                    : null,
                'error' => is_string($error) ? $error : null,
                'installerUrl' => $service->installerUrl(),
                'basePath' => $service->basePath(),
            ],
            null
        );

        (new Response(
            $html,
            200,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]
        ))->send();
    }

    /** @return array<string,mixed> */
    private function mailInput(Request $request): array
    {
        return [
            'mail_driver' => $request->input('mail_driver', 'mail'),
            'mail_from_address' => $request->input(
                'mail_from_address',
                ''
            ),
            'mail_from_name' => $request->input(
                'mail_from_name',
                'Barama Real Estate'
            ),
            'mail_host' => $request->input('mail_host', ''),
            'mail_port' => $request->input('mail_port', 587),
            'mail_encryption' => $request->input(
                'mail_encryption',
                'tls'
            ),
            'mail_username' => $request->input('mail_username', ''),
            'mail_password' => $request->input('mail_password', ''),
        ];
    }

    /**
     * Revalidate the Step 1 activation immediately before installation mutates
     * the database or writes production runtime state.
     *
     * @param array<string,mixed> $license
     * @return array<string,mixed>
     */
    private function revalidateLicenseForInstall(
        array $license,
        InstallerService $service
    ): array {
        $installationId = Installation::installationId();
        $currentDomain = $service->detectedDomain();
        $validatedDomain = LicenseManagerClient::canonicalDomain(
            (string) ($license['domain'] ?? '')
        );

        if (
            $currentDomain === ''
            || $validatedDomain === ''
            || !hash_equals($validatedDomain, $currentDomain)
        ) {
            throw new RuntimeException(
                'The installation domain changed after license validation. Validate the license again on the current domain.'
            );
        }

        $validated = (new LicenseManagerClient())->validateActive(
            (string) ($license['activation_token'] ?? ''),
            $currentDomain,
            $installationId
        );

        return array_merge(
            $license,
            $validated,
            [
                'status' => 'active',
                'domain' => $currentDomain,
                'installation_id' => $installationId,
                'validated_at' => gmdate('c'),
            ]
        );
    }

    /** @return array<string,mixed> */
    private function requireLicense(): array
    {
        $license = Session::get(self::LICENSE_KEY, []);

        if (
            !is_array($license)
            || trim((string) ($license['activation_token'] ?? '')) === ''
        ) {
            throw new RuntimeException(
                'Validate the license before continuing.'
            );
        }

        return $license;
    }

    private function requireReachedStep(int $step): void
    {
        if ((int) Session::get(self::STEP_KEY, 1) < $step) {
            throw new RuntimeException(
                'Complete the previous installation step first.'
            );
        }
    }

    private function respondError(
        Request $request,
        string $message,
        int $status
    ): never {
        if ($this->wantsJson($request)) {
            $this->json(
                [
                    'ok' => false,
                    'message' => $message,
                ],
                $status
            );
        }

        Session::put('installer.error', $message);
        $service = new InstallerService();
        $step = max(
            1,
            min(4, (int) Session::get(self::STEP_KEY, 1))
        );

        $this->redirect(
            $service->installerUrl('step=' . $step)
        );
    }

    /** @param array<string,mixed> $payload */
    private function json(
        array $payload,
        int $status = 200
    ): never {
        (new Response(
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            ),
            $status,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        ))->send();

        exit;
    }

    private function redirect(string $location): never
    {
        (new Response(
            '',
            303,
            [
                'Location' => $location,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        ))->send();

        exit;
    }

    private function wantsJson(Request $request): bool
    {
        return strtolower(
            (string) $request->header('X-Requested-With', '')
        ) === 'xmlhttprequest'
            || str_contains(
                strtolower(
                    (string) $request->header('Accept', '')
                ),
                'application/json'
            );
    }
}
