<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Installation;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\Licensing\CurlLicenseTransport;
use Barama\Services\Licensing\CustomerLicenseManager;
use Barama\Services\Licensing\InstallationLicenseStateStore;
use Barama\Services\Licensing\LicenseClientException;
use Barama\Services\Licensing\LicenseSettingsAction;
use Barama\Services\Licensing\UnifiedLicenseService;

/**
 * Customer-only license dashboard. Author-side issuance and license records
 * remain exclusively in the Publisher. No DB writes or remote requests on GET.
 */
final class LicenseSettingsController
{
    private const FRESH_VERIFICATION_SECONDS = 900;
    private const METADATA_KEY = 'publisher_v2_verification';

    public function index(Request $request): Response
    {
        if (!auth()->hasRole('administrator')) {
            return $this->forbidden();
        }
        try {
            $status = $this->localStatus();
        } catch (LicenseClientException $exception) {
            $status = ['configured' => false, 'error' => 'PRIVATE_LICENSE_STATE_UNAVAILABLE'];
        }

        return new Response(View::render('dashboard/license-settings', [
            'pageTitle' => 'License & Activation · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->licensingNavigation(),
            'activeSection' => 'license',
            'licenseStatus' => $status,
            'licenseContext' => $this->displayContext(),
            'licenseActionUrl' => url('/dashboard/license/actions'),
            'csrfToken' => csrf_token(),
        ]), 200, $this->noStoreHeaders('text/html; charset=UTF-8'));
    }

    public function action(Request $request): Response
    {
        if (!auth()->hasRole('administrator')) {
            return $this->forbidden();
        }
        $action = $request->input('action');
        try {
            if (!is_string($action) || !in_array($action, ['activate', 'verify', 'deactivate'], true)) {
                throw new LicenseClientException('LICENSE_ACTION_NOT_FOUND', 404);
            }
            $legacy = $this->unified();
            $v2 = (new InstallationLicenseStateStore())->load();
            $receipt = Installation::licenseState();
            if ($v2 !== null && $legacy->hasReceipt($receipt)) {
                throw new LicenseClientException('CONFLICTING_LICENSE_RECORDS', 409);
            }
            if ($v2 === null) {
                // The installer, updater and License Center share one receipt.
                // Every mutation is routed to the original trusted License Manager.
                return $this->json(['ok' => true,
                    'result' => $legacy->run($action, $request->all())], 200);
            }
            $result = $this->actionService()->run($action, $request->all());
            // Verification timestamps are display-only metadata. A failure to save
            // them must never turn a successful remote activation into a false 503.
            try {
                if ($action === 'activate' || $action === 'verify') {
                    $this->recordVerified($result);
                } elseif ($action === 'deactivate') {
                    $this->clearVerification();
                }
            } catch (\Throwable) {
                // The main encrypted activation token is managed by the existing store.
            }
            return $this->json(['ok' => true, 'result' => $result], 200);
        } catch (LicenseClientException $error) {
            if ($action === 'verify' && in_array($error->reason, ['LICENSE_REVOKED', 'LICENSE_SUSPENDED', 'LICENSE_EXPIRED'], true)) {
                $this->recordBlocked($error->reason);
            }
            return $this->json(['ok' => false, 'error' => $error->reason],
                $error->getCode() >= 400 && $error->getCode() <= 599 ? $error->getCode() : 422);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'LICENSE_SERVICE_UNAVAILABLE'], 503);
        }
    }

    private function actionService(): LicenseSettingsAction
    {
        $appUrl = (string) config('app.url', '');
        $parts = parse_url($appUrl);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || !is_string($parts['host'] ?? null)
            || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            throw new LicenseClientException('CANONICAL_HTTPS_URL_REQUIRED', 503);
        }
        return new LicenseSettingsAction($this->manager(), strtolower($parts['host']),
            Installation::installationId());
    }

    /** Display-safe local status; no remote request and no token exposure. */
    private function localStatus(): array
    {
        $state = (new InstallationLicenseStateStore())->load();
        $service = $this->unified();
        $receipt = Installation::licenseState();
        if ($state !== null && $service->hasReceipt($receipt)) {
            return [
                'configured' => true, 'source' => 'conflict', 'state' => 'error',
                'status_title' => 'License records need review',
                'status_hint' => 'Multiple activation records found. Contact product support.',
            ];
        }
        if ($state === null) {
            return $service->localStatus();
        }
        $metadata = Installation::licenseState()[self::METADATA_KEY] ?? [];
        $last = is_array($metadata) ? ($metadata['checked_at'] ?? null) : null;
        $sameDomain = is_array($metadata)
            && ($metadata['domain'] ?? null) === $state['domain'];
        $last = $sameDomain && is_int($last) && $last > 0 && $last <= time()
            ? $last : null;
        $blocked = $sameDomain && in_array($metadata['blocked_reason'] ?? null,
            ['LICENSE_REVOKED', 'LICENSE_SUSPENDED', 'LICENSE_EXPIRED'], true);
        $fresh = $last !== null && time() - $last <= self::FRESH_VERIFICATION_SECONDS && !$blocked;
        $expiry = is_array($metadata) && is_string($metadata['expires_at'] ?? null)
            ? $metadata['expires_at'] : null;
        $expired = $sameDomain && $expiry !== null && ($expiryTimestamp = strtotime($expiry)) !== false
            && $expiryTimestamp <= time();
        return [
            'configured' => true,
            'state' => ($expired || $blocked) ? 'error' : ($fresh ? 'verified' : 'pending'),
            'status_title' => $expired ? 'License expired' : ($blocked ? 'License blocked' : null),
            'domain' => $state['domain'],
            'product_code' => $state['product'],
            'last_verified_at' => $last === null ? null : gmdate('Y-m-d H:i:s', $last) . ' UTC',
            'expires_at' => $expiry,
        ];
    }

    /** No connectivity claim: configured origin is not a verified API connection. */
    private function displayContext(): array
    {
        // Existing installed licenses use the original signed server contract.
        // A configured endpoint alone does not mean it is reachable.
        $installer = $this->isInstallerReceiptSelected();
        $url = $installer
            ? trim((string) config('license.server_url', ''))
            : trim((string) env('BARAMA_LICENSE_PUBLISHER_ORIGIN', ''));
        $parts = $url !== '' ? parse_url($url) : false;
        $valid = is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && is_string($parts['host'] ?? null) && $parts['host'] !== ''
            && !filter_var($parts['host'], FILTER_VALIDATE_IP)
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['port']) && !isset($parts['query'])
            && !isset($parts['fragment'])
            && in_array($parts['path'] ?? '/', ['', '/'], true);
        $id = Installation::installationId();
        $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        return [
            'product_name' => 'Barama Real Estate',
            'version' => defined('APP_VERSION') ? APP_VERSION : '—',
            'installation_id_masked' => strlen($id) > 12 ? substr($id, 0, 7) . '••••' . substr($id, -5) : 'Protected',
            'domain' => is_string($appHost) ? strtolower($appHost) : 'Not configured',
            'publisher_host' => $valid ? strtolower($parts['host']) : null,
            'publisher_configured' => $valid,
            'publisher_contract' => $installer ? 'installer_v1' : 'publisher_v2',
        ];
    }

    /** Shared installed-license service, not an independent activation record. */
    private function unified(): UnifiedLicenseService
    {
        return new UnifiedLicenseService();
    }

    private function isInstallerReceiptSelected(): bool
    {
        return (new InstallationLicenseStateStore())->load() === null;
    }

    private function recordVerified(array $result): void
    {
        $domain = $result['domain'] ?? null;
        if (!is_string($domain) || $domain === '') {
            return;
        }
        $expiry = $result['expires_at'] ?? null;
        Installation::refreshLicenseState([self::METADATA_KEY => [
            'checked_at' => time(),
            'domain' => $domain,
            'expires_at' => is_string($expiry) ? substr($expiry, 0, 64) : null,
        ]]);
    }

    private function recordBlocked(string $reason): void
    {
        try {
            $state = (new InstallationLicenseStateStore())->load();
            if ($state !== null && is_string($state['domain'] ?? null)) {
                Installation::refreshLicenseState([self::METADATA_KEY => [
                    'blocked_reason' => $reason, 'domain' => $state['domain'],
                    'checked_at' => null, 'expires_at' => null,
                ]]);
            }
        } catch (\Throwable) {
            // Do not mask the original denial or expose secrets to the response.
        }
    }

    private function clearVerification(): void
    {
        Installation::refreshLicenseState([self::METADATA_KEY => null]);
    }

    private function manager(): CustomerLicenseManager
    {
        return new CustomerLicenseManager(
            new CurlLicenseTransport((string) env('BARAMA_LICENSE_PUBLISHER_ORIGIN', '')),
            new InstallationLicenseStateStore()
        );
    }

    private function forbidden(): Response
    {
        return $this->json(['ok' => false, 'error' => 'ACCESS_DENIED'], 403);
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status): Response
    {
        return new Response(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status, $this->noStoreHeaders('application/json; charset=UTF-8'));
    }

    /** @return array<string,string> */
    private function noStoreHeaders(string $type): array
    {
        return [
            'Content-Type' => $type,
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}
