<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Installation;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\PublicSystemHealthService;
use Throwable;

/**
 * Admin-only System Center pages. Remote license verification occurs only on
 * an explicitly submitted, protected POST. No update installation or secrets exposed.
 */
final class SystemCenterController
{
    public function about(Request $request): Response
    {
        return $this->page('about');
    }

    public function updates(Request $request): Response
    {
        return $this->page('updates');
    }

    public function autoUpdater(Request $request): Response
    {
        return $this->page('auto-updater');
    }

    public function health(Request $request): Response
    {
        return $this->page('health');
    }

    /** Administrator-initiated, authenticated and CSRF-protected publisher check. */
    public function validatePublisherLicense(Request $request): Response
    {
        $now = time();
        $last = (int) ($_SESSION['_barama_publisher_license_last_attempt_v111'] ?? 0);
        if ($last > 0 && ($now - $last) < 90) {
            return $this->finishPublisherCheck('Wait before retrying', 90 - ($now - $last));
        }
        $_SESSION['_barama_publisher_license_last_attempt_v111'] = $now;
        $state = 'Unable to verify';
        // A license validation is not a remote update/support entitlement check.
        $issuer = (string) config('license.server_url', '');
        $endpoint = (string) config('license.validate_path', '');
        if ($issuer !== 'https://tonsamith.com' || $endpoint !== '/license/v1/validate'
            || (string) config('license.product_slug', '') !== 'barama-real-estate') {
            $state = 'Publisher settings not trusted';
        } else {
            try {
                $result = \Barama\Services\PublisherLicenseStatus::probe(
                    Installation::licenseState(),
                    Installation::runtimeConfig(),
                    static function (string $token, string $domain, string $installationId): array {
                        return (new \Barama\Services\LicenseManagerClient())
                            ->validate($token, $domain, $installationId);
                    }
                );
                $state = (string) ($result['state'] ?? 'Unable to verify');
            } catch (\Throwable) {
                // Never expose upstream errors, token, response body, or PHP trace.
                $state = 'Unable to verify';
            }
        }
        return $this->finishPublisherCheck($state);
    }

    /** Explicit POST, admin+CSRF middleware applied by the route. */
    public function inspectStagedRelease(Request $request): Response
    {
        $now=time();
        $last=(int)($_SESSION['_barama_signed_release_check_last_v150']??0);
        $result=['status'=>'Not inspected','checked_at'=>gmdate('Y-m-d H:i').' UTC'];
        if($last>0 && $now-$last<30) {
            $result['status']='Please wait before checking again';
        } else {
            $_SESSION['_barama_signed_release_check_last_v150']=$now;
            try {
                $verified=\Barama\Services\Updates\SignedReleaseInspector::inspect((string)APP_VERSION);
                $result['status']='Signed package verified (not installed)';
                $result['version']=$verified['version'];
                $result['files']=$verified['files'];
            } catch (\RuntimeException $exception) {
                // Only allowlisted, non-sensitive codes are shown to administrators.
                $result['status']='Inspection unavailable or rejected';
            } catch (\Throwable) {
                $result['status']='Inspection unavailable or rejected';
            }
        }
        $_SESSION['_barama_signed_release_result_v150']=$result;
        return Response::redirect(url('/dashboard/system/auto-updater'),303);
    }

    /** Owner-approved user interaction: remote request only on admin+CSRF POST. */
    public function checkUpdateEntitlement(Request $request): Response
    {
        $now = time();
        $last = (int)($_SESSION['_barama_update_feed_last_attempt_v151'] ?? 0);
        if ($last > 0 && $now - $last < 90) {
            $_SESSION['_barama_update_feed_flash_v151'] = 'Please wait 90 seconds between checks.';
            return Response::redirect(url('/dashboard/system/auto-updater'), 303);
        }
        $_SESSION['_barama_update_feed_last_attempt_v151'] = $now;
        try {
            $result = \Barama\Services\Updates\TrustedReleaseFeed::check(
                (string)APP_VERSION, Installation::licenseState(), Installation::runtimeConfig()
            );
            $result['at'] = $now;
            $result['license_binding'] = self::feedBindingFingerprint(
                Installation::licenseState(), Installation::runtimeConfig()
            );
            $_SESSION['_barama_update_feed_result_v151'] = $result;
            // Reconcile this *fresh* authenticated publisher response against
            // the signed ZIP on the structure-only staging site. Read-only.
            // Production always returns STAGING_ONLY; no install is authorized.
            $candidate = \Barama\Services\Updates\StagingPublisherCandidateV1::inspect(
                defined('BASE_PATH') ? (string) BASE_PATH : '',
                (string) APP_VERSION,
                $result
            );
            $_SESSION['_barama_publisher_candidate_v1'] = $candidate;
            $_SESSION['_barama_update_feed_flash_v151'] = match ($result['status']) {
                'available' => 'A newer publisher-signed release was found. No package was downloaded.',
                'eligible' => 'Update entitlement confirmed. No newer signed release was published.',
                'ineligible' => ($result['reason'] ?? '') === 'not_configured'
                    ? 'Update entitlement has not been configured by the publisher. Contact License assistance; no update was downloaded.'
                    : 'Publisher denied update entitlement for this installation. Contact License assistance for a review; the active license is unchanged.',
                default => 'Update check completed.',
            };
            if (($candidate['publisher_release_matched_to_staged_zip'] ?? false) === true) {
                $_SESSION['_barama_update_feed_flash_v151'] =
                    'Publisher release and staged signed ZIP match exactly. Verification only: database recovery, rollback and Update Now remain blocked.';
            }
        } catch (\Throwable) {
            // Do not retain a stale candidate result after a failed recheck.
            unset($_SESSION['_barama_publisher_candidate_v1']);
            // Preserve the canonical license and suppress upstream network errors/secrets.
            $_SESSION['_barama_update_feed_result_v151'] = [
                'status' => 'unavailable', 'entitlement' => 'Not checked',
                'feed' => 'Unavailable', 'version' => '',
                'checked_at' => gmdate('Y-m-d H:i').' UTC', 'at' => $now,
            ];
            $_SESSION['_barama_update_feed_flash_v151'] = 'Unable to confirm update entitlement or a trusted release. The installed license was not changed.';
        }
        return Response::redirect(url('/dashboard/system/auto-updater'), 303);
    }

    /**
     * Admin-initiated request only; an independently trusted root worker
     * verifies all security and recovery gates before any installation.
     */
    public function requestOneClickUpdate(Request $request): Response
    {
        $auth = new \Barama\Services\AuthService();
        if (!$auth->hasRole('administrator') && !$auth->hasRole('super-administrator')) {
            return Response::html('Forbidden', 403);
        }
        $last = (int)($_SESSION['_barama_oneclick_request_at'] ?? 0);
        if ($last > 0 && time() - $last < 90) {
            $_SESSION['_barama_oneclick_request_flash'] = 'Wait before submitting another update request.';
            return Response::redirect(url('/dashboard/system/auto-updater'), 303);
        }
        $_SESSION['_barama_oneclick_request_at'] = time();
        try {
            $site = defined('BASE_PATH') ? (string) BASE_PATH : '';
            $feed = \Barama\Services\Updates\TrustedReleaseFeed::check(
                (string) APP_VERSION, Installation::licenseState(), Installation::runtimeConfig()
            );
            $queued = \Barama\Services\Updates\OneClickUpdateQueueV1::enqueue(
                $site, (string) APP_VERSION, $feed
            );
            $_SESSION['_barama_oneclick_request_flash'] =
                'Update request queued for worker verification. Installation is not complete.';
            $_SESSION['_barama_oneclick_job_id'] = $queued['id'];
        } catch (\Throwable) {
            $_SESSION['_barama_oneclick_request_flash'] =
                'Update request blocked. Check publisher publication, entitlement, signed package and worker readiness.';
        }
        return Response::redirect(url('/dashboard/system/auto-updater'), 303);
    }

    /** Server-session fingerprint: never store or render an activation token. */
    private static function feedBindingFingerprint(array $license, array $runtime): string
    {
        return hash('sha256', implode("\0", [
            (string)($license['activation_token'] ?? ''),
            (string)($license['installation_id'] ?? ''),
            (string)($license['domain'] ?? ''),
            (string)($license['product_slug'] ?? ''),
            (string)($license['status'] ?? ''),
            (string)($runtime['APP_URL'] ?? ''),
        ]));
    }

    private function finishPublisherCheck(string $state, int $retryAfter = 0): Response
    {
        $allowed = [
            'Active license verified', 'Unable to verify', 'License not configured',
            'License binding mismatch', 'Publisher settings not trusted', 'Wait before retrying',
            'Publisher temporarily unavailable', 'Publisher confirmed revoked',
            'Publisher confirmed suspended', 'Publisher confirmed expired',
        ];
        if (!in_array($state, $allowed, true)) {
            $state = 'Unable to verify';
        }
        // Only a publisher-confirmed result is persisted. Temporary failures
        // never erase previous trusted verification history.
        if (in_array($state, [
            'Active license verified',
            'Publisher confirmed revoked',
            'Publisher confirmed suspended',
            'Publisher confirmed expired',
        ], true)) {
            try {
                if (!\Barama\Services\PublisherVerificationRecord::remember($state)) {
                    $state = 'License binding mismatch';
                }
            } catch (Throwable) {
                error_log('[Barama Auto Updater] Private verification history could not be stored.');
                $state = 'Unable to verify';
            }
        }
        $checkedAt = gmdate('Y-m-d H:i') . ' UTC';
        $_SESSION['_barama_publisher_license_result_v111'] = [
            'state' => $state, 'checked_at' => $checkedAt,
        ];
        $messages = [
            'Active license verified' => 'The publisher confirmed an active Barama license bound to this installation. Update entitlement is not yet checked.',
            'Unable to verify' => 'The publisher did not confirm a matching active license. Check the publisher connection and license record; no credentials were changed.',
            'License not configured' => 'No usable activation token or installation ID is recorded on this installation.',
            'License binding mismatch' => 'The local license does not match the expected product, domain, or installation.',
            'Publisher settings not trusted' => 'The configured publisher endpoint or product identifier does not match the trusted Barama settings.',
            'Wait before retrying' => 'Please wait before another publisher request. Repeated checks are limited to one every 90 seconds.',
            'Publisher temporarily unavailable' => 'The license publisher could not be reached or returned a temporary service failure. Your license record is unchanged.',
            'Publisher confirmed revoked' => 'The publisher returned a revoked status for this product, domain and installation. Contact the publisher before attempting reactivation.',
            'Publisher confirmed suspended' => 'The publisher returned a suspended status for this installation. Contact publisher support.',
            'Publisher confirmed expired' => 'The publisher returned an expired status for this installation. Contact publisher support.',
        ];
        $feedback = [
            'state' => $state,
            'message' => $messages[$state],
            'checked_at' => $checkedAt,
            'success' => $state === 'Active license verified',
            'retry_after_seconds' => max(0, min(90, $retryAfter)),
        ];
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $requestedWith = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        if ($requestedWith === 'XMLHttpRequest' && str_contains($accept, 'application/json')) {
            // Response is a fixed, allowlisted, sanitized status. No license data.
            return new Response(json_encode($feedback, JSON_THROW_ON_ERROR), 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        // Progressive enhancement: non-JS form continues using safe PRG redirect.
        $_SESSION['_barama_publisher_license_flash_v112'] = true;
        return Response::redirect(url('/dashboard/system/auto-updater'), 303);
    }

    private function page(string $section): Response
    {
        $titles = [
            'about' => 'About Barama',
            'updates' => 'System Updates',
            'health' => 'System Health',
            'auto-updater' => 'Auto Updater',
        ];
        if (!isset($titles[$section])) {
            return Response::html('Not found', 404);
        }

        $publisherFlash = !empty($_SESSION['_barama_publisher_license_flash_v112']);
        unset($_SESSION['_barama_publisher_license_flash_v112']);
        $publisherResult = $_SESSION['_barama_publisher_license_result_v111'] ?? null;
        $savedPublisherResult = null;
        $installedActivation = null;
        if ($section === 'auto-updater') {
            try {
                $savedPublisherResult = \Barama\Services\PublisherVerificationRecord::read();
                $local = (new \Barama\Services\Licensing\UnifiedLicenseService())->localStatus();
                if (!empty($local['installer_recognized'])
                    && in_array($local['state'] ?? null, ['verified', 'pending'], true)) {
                    $receipt = Installation::licenseState();
                    $v2 = $receipt['publisher_v2_verification'] ?? null;
                    $blocked = is_array($v2)
                        && in_array($v2['blocked_reason'] ?? null,
                            ['LICENSE_REVOKED', 'LICENSE_SUSPENDED', 'LICENSE_EXPIRED'], true)
                        && \Barama\Services\LicenseManagerClient::canonicalDomain((string) ($v2['domain'] ?? ''))
                            === \Barama\Services\LicenseManagerClient::canonicalDomain((string) ($receipt['domain'] ?? ''));
                    $expiry = $receipt['expires_at'] ?? null;
                    $expired = is_string($expiry) && trim($expiry) !== ''
                        && ($expiresAt = strtotime($expiry)) !== false
                        && $expiresAt <= time();
                    if (!$blocked && !$expired
                        && ($receipt['publisher_v2_activation'] ?? null) === null) {
                        $installedActivation = [
                            'state' => 'Installed license recognized',
                            'checked_at' => is_string($local['last_verified_at'] ?? null)
                                ? $local['last_verified_at'] : 'Activation recorded',
                        ];
                    }
                }
            } catch (Throwable) {
                // A missing/invalid canonical receipt never creates a verified status.
            }
        }
        if (!is_array($publisherResult) || !isset($publisherResult['state'], $publisherResult['checked_at'])
            || !is_string($publisherResult['state']) || !is_string($publisherResult['checked_at'])
            || !preg_match('/^(Active license verified|Unable to verify|License not configured|License binding mismatch|Publisher settings not trusted|Wait before retrying|Publisher temporarily unavailable|Publisher confirmed revoked|Publisher confirmed suspended|Publisher confirmed expired)$/D', $publisherResult['state'])
        ) {
            $publisherResult = $savedPublisherResult ?? $installedActivation
                ?? ['state' => 'Not checked', 'checked_at' => 'Never'];
        }
        if (!$publisherFlash && ($savedPublisherResult !== null || $installedActivation !== null)) {
            // A persisted publisher decision takes priority over installed
            // activation. A revoked result must never become locally "active".
            $publisherResult = $savedPublisherResult ?? $installedActivation;
        } elseif ($savedPublisherResult === null
            && in_array($publisherResult['state'], [
                'Active license verified',
                'Publisher confirmed revoked',
                'Publisher confirmed suspended',
                'Publisher confirmed expired',
            ], true)) {
            // A session is not evidence: never resurrect it after the license
            // token, domain, installation ID or expiry changes.
            $publisherResult = $installedActivation
                ?? ['state' => 'Not checked', 'checked_at' => 'Never'];
        }
        $feedResult = $_SESSION['_barama_update_feed_result_v151'] ?? null;
        if (!is_array($feedResult) || !in_array(($feedResult['status'] ?? ''),
            ['available','eligible','ineligible','unavailable'], true)
            || !is_int($feedResult['at'] ?? null)
            || time() - $feedResult['at'] > 600 || $feedResult['at'] > time() + 30
            || !in_array(($feedResult['entitlement'] ?? ''), ['Eligible','Not entitled','Not configured','Not checked'], true)
            || !in_array(($feedResult['feed'] ?? ''), ['Signed release verified','No release published','Not available','Unavailable'], true)
            || !is_string($feedResult['version'] ?? null)
            || !preg_match('/^(?:|\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?)$/D', $feedResult['version'])) {
            $feedResult = ['status'=>'not_checked','entitlement'=>'Not checked',
                'feed'=>'Not connected', 'version'=>'', 'checked_at'=>'Never'];
        }
        $feedFlash = $_SESSION['_barama_update_feed_flash_v151'] ?? '';
        unset($_SESSION['_barama_update_feed_flash_v151']);
        $allowedFeedFlashes = [
            'Please wait 90 seconds between checks.',
            'A newer publisher-signed release was found. No package was downloaded.',
            'Update entitlement confirmed. No newer signed release was published.',
            'Publisher reports that this installation is not entitled to updates.',
            'Update entitlement has not been configured by the publisher. Contact License assistance; no update was downloaded.',
            'Publisher denied update entitlement for this installation. Contact License assistance for a review; the active license is unchanged.',
            'Update check completed.',
            'Unable to confirm update entitlement or a trusted release. The installed license was not changed.',
        ];
        if (!is_string($feedFlash) || !in_array($feedFlash, $allowedFeedFlashes, true)) $feedFlash = '';
        $feedReady = ['can_check'=>false,'configured'=>false,'publisher_key'=>false];
        if ($section === 'auto-updater') {
            try { $feedReady = \Barama\Services\Updates\TrustedReleaseFeed::readiness(); }
            catch (\Throwable) { /* Rendering is safe when the publisher is unavailable. */ }
        }
        $metadata = Installation::metadata();
        $installedVersion = trim((string) ($metadata['version'] ?? ''));
        $snapshot = $section === 'health'
            ? (new PublicSystemHealthService())->snapshot()
            : null;

        $license = [];
        try {
            $license = Installation::licenseState();
        } catch (Throwable) {
            // Missing/malformed license state is reported as "not recorded",
            // not guessed or treated as a remotely verified entitlement.
        }
        $license = is_array($license) ? $license : [];
        if ($section === 'auto-updater' && in_array($feedResult['status'] ?? '', ['available','eligible','ineligible'], true)) {
            $currentBinding = self::feedBindingFingerprint($license, Installation::runtimeConfig());
            if (!is_string($feedResult['license_binding'] ?? null)
                || !hash_equals($currentBinding, $feedResult['license_binding'])
                || empty($feedReady['can_check'])) {
                $feedResult = ['status'=>'not_checked','entitlement'=>'Not checked',
                    'feed'=>'Not connected', 'version'=>'', 'checked_at'=>'Never'];
                $feedFlash = '';
            }
        }
        $recordedStatus = strtolower(trim((string) ($license['status'] ?? '')));
        $safeStatuses = [
            'active' => 'Recorded active',
            'pending' => 'Pending',
            'expired' => 'Recorded expired',
            'revoked' => 'Recorded revoked',
            'suspended' => 'Recorded suspended',
            'inactive' => 'Recorded inactive',
            'invalid' => 'Recorded invalid',
        ];
        $safeValue = static function (mixed $raw, int $max = 96): string {
            if (!is_string($raw) || !preg_match('/^[A-Za-z0-9 ._\/-]{1,96}$/D', $raw)) {
                return 'Not recorded';
            }
            return substr(trim($raw), 0, min(96, $max)) ?: 'Not recorded';
        };
        $safeDate = static function (mixed $raw): string {
            if (!is_string($raw) || strlen($raw) > 64 ||
                !preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ][0-9:.+\-Z]*)?$/D', $raw)) {
                return 'Not recorded';
            }
            try {
                return (new \DateTimeImmutable($raw))->format('Y-m-d');
            } catch (Throwable) {
                return 'Not recorded';
            }
        };
        $supportRecord = $license['support_expires_at']
            ?? $license['support_until']
            ?? $license['support_ends_at']
            ?? null;
        $requirements = [[
            'name' => 'PHP',
            'requirement' => 'PHP 8.2+ runtime baseline',
            'detected' => PHP_VERSION,
            'state' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'Available' : 'Unavailable',
        ]];
        foreach ([
            'pdo_mysql' => 'MySQL database connectivity',
            'mbstring' => 'Unicode text processing',
            'openssl' => 'Cryptography / TLS',
            'curl' => 'HTTPS integrations',
            'fileinfo' => 'Uploaded file identification',
            'json' => 'JSON processing',
            'zip' => 'ZIP archive handling',
            'intl' => 'Localization',
            'gd' => 'Image processing',
            'dom' => 'Document parsing',
            'xml' => 'XML parsing',
        ] as $ext => $purpose) {
            $loaded = extension_loaded($ext);
            $requirements[] = [
                'name' => $ext,
                'requirement' => $purpose . ' (runtime capability)',
                'detected' => $loaded ? 'Loaded' : 'Not loaded',
                'state' => $loaded ? 'Available' : 'Unavailable',
            ];
        }
        $requirements[] = [
            'name' => 'Database engine',
            'requirement' => 'MySQL / MariaDB with application schema',
            'detected' => 'Engine/version not inspected',
            'state' => 'Not checked',
        ];
        $requirements[] = [
            'name' => 'Web server',
            'requirement' => 'Supported web-server rewrites and HTTPS',
            'detected' => 'Configuration not inspected',
            'state' => 'Not checked',
        ];
        $storageWritable = is_dir(APP_PATH . '/storage') && is_writable(APP_PATH . '/storage');
        $requirements[] = [
            'name' => 'Application storage',
            'requirement' => 'Private writable application storage',
            'detected' => $storageWritable ? 'Writable' : 'Not writable',
            'state' => $storageWritable ? 'Available' : 'Unavailable',
        ];
        $updaterPresent = is_file(BASE_PATH . '/updater/index.php');
        $versionMatches = APP_VERSION === $installedVersion;

        $releaseReady=['trusted_key'=>false,'staged_release'=>false,'inspect_enabled'=>false,'zip_available'=>false];
        if($section==='auto-updater') {
            try { $releaseReady=\Barama\Services\Updates\SignedReleaseInspector::readiness(); }
            catch (\Throwable) { /* Never block the dashboard on readiness. */ }
        }
        $releaseCheck=$_SESSION['_barama_signed_release_result_v150']??null;
        if(!is_array($releaseCheck) || !in_array(($releaseCheck['status']??''),[
            'Not inspected','Signed package verified (not installed)',
            'Inspection unavailable or rejected','Please wait before checking again'
        ],true)) $releaseCheck=['status'=>'Not inspected','checked_at'=>'Never'];

        $html = View::render('workspace/system-center', [
            'pageTitle' => $titles[$section] . ' · Admin Control Center',
            'metaRobots' => 'noindex,nofollow,noarchive',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new WorkspaceSettingsController())->systemAdminNavigation(),
            'activeSection' => 'system-' . $section,
            'systemSection' => $section,
            'systemTitle' => $titles[$section],
            'systemVersion' => (string) APP_VERSION,
            'systemInstalledVersion' => $installedVersion,
            'systemSnapshot' => $snapshot,
            'systemUpdaterPresent' => $updaterPresent,
            'systemRequirements' => $requirements,
            'systemCheckedAt' => gmdate('Y-m-d H:i') . ' UTC',
            'systemLicense' => [
                'status' => $safeStatuses[$recordedStatus] ?? 'Not recorded',
                'type' => $safeValue($license['license_type'] ?? $license['type'] ?? null),
                'source' => $safeValue($license['purchase_source'] ?? $license['source'] ?? null),
                'product' => $safeValue($license['product_slug'] ?? null),
                'validated_at' => $safeDate($license['last_validated_at'] ?? $license['validated_at'] ?? null),
                'support_until' => $safeDate($supportRecord),
            ],
            'systemEnvironment' => [
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'storage' => $storageWritable ? 'Writable' : 'Not writable',
                'release_channel' => str_contains(strtolower((string) APP_VERSION), '-dev')
                    ? 'Development' : 'Not specified',
            ],
            'systemPublisherCheck' => $publisherResult,
            'systemPublisherFlash' => $publisherFlash,
            'systemReleaseReadiness' => $releaseReady,
            'systemFeedReadiness' => $feedReady,
            'systemFeedResult' => $feedResult,
            'systemFeedFlash' => $feedFlash,
            'systemReleaseInspection' => $releaseCheck,
            'systemOneClickFlash' => $_SESSION['_barama_oneclick_request_flash'] ?? '',
            'systemOneClickPending' => (static function (): bool {
                try {
                    return \Barama\Services\Updates\OneClickUpdateQueueV1::pending((string) BASE_PATH);
                } catch (\Throwable) {
                    return false;
                }
            })(),
            'systemLicenseBinding' => [
                'current_domain' => 'baramarealestate.tonsamith.com',
                'recorded_domain' => (static function (mixed $value): string {
                    if (!is_string($value) || strlen($value) > 255) return 'Not recorded';
                    $domain = \Barama\Services\LicenseManagerClient::canonicalDomain($value);
                    return preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,24}$/D', $domain) ? $domain : 'Not recorded';
                })($license['domain'] ?? null),
                'recorded_status' => $safeStatuses[$recordedStatus] ?? 'Not recorded',
            ],
            'systemAutoUpdater' => [
                'license_record' => $safeStatuses[$recordedStatus] ?? 'Not recorded',
                'license_local' => $license !== [],
                'version_match' => $versionMatches,
                'updater_exists' => $updaterPresent,
                'storage_writable' => $storageWritable,
                'product' => 'barama-real-estate',
                'release_feed' => (string)($feedResult['feed'] ?? 'Not connected'),
                'signed_releases' => 'Not checked',
                'remote_entitlement' => (string)($feedResult['entitlement'] ?? 'Not checked'),
                'demo_importer' => 'Not connected',
                'auto_install' => 'Disabled',
                'rollback_tested' => 'Not checked',
            ],
            'systemUpdateSummary' => [
                [
                    'name' => 'Version record consistency',
                    'description' => $versionMatches ? 'Installed and application versions match.' : 'Version records differ.',
                    'status' => $versionMatches ? 'Available' : 'Attention',
                ],
                [
                    'name' => 'Guarded updater entry point',
                    'description' => $updaterPresent ? 'Existing /updater/ entry point is present.' : 'Updater entry point is absent.',
                    'status' => $updaterPresent ? 'Available' : 'Attention',
                ],
                [
                    'name' => 'Application storage',
                    'description' => $storageWritable ? 'Storage directory is writable by this PHP process.' : 'Storage writability check failed.',
                    'status' => $storageWritable ? 'Available' : 'Attention',
                ],
                [
                    'name' => 'Signed release availability',
                    'description' => 'No release-feed or signature check performed.',
                    'status' => 'Not checked',
                ],
                [
                    'name' => 'Pre-update backup/rollback',
                    'description' => 'No backup or restore drill performed by this page.',
                    'status' => 'Not checked',
                ],
                [
                    'name' => 'Database migrations',
                    'description' => 'No migration readiness check performed.',
                    'status' => 'Not checked',
                ],
                [
                    'name' => 'License update entitlement',
                    'description' => 'No remote entitlement check performed.',
                    'status' => 'Not checked',
                ],
            ],
        ]);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
