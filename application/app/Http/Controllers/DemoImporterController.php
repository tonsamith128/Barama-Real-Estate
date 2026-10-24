<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Installation;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\AuthService;
use Barama\Services\DemoPublisherNativeArchiveV1;
use Barama\Services\DemoPublisherDownloadV1;
use Barama\Services\DemoPublisherApiV1;
use Barama\Services\LicenseManagerClient;
use RuntimeException;
use Throwable;

/**
 * BARAMA_DEMO_IMPORT_CONTROLLER_V100
 * Two modes, same signed-package verifier. No package ever bypasses
 * publisher-signed trust or commercial entitlement checks.
 */
final class DemoImporterController
{
    private const URL = '/dashboard/system/demo-importer';
    // Production remains preview-only until actual publisher and staging acceptance.
    public const RELEASE_GATE = 'download_only_staging_pending';
    private const SESSION = '_barama_demo_import_v100';

    public function index(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        $mode = (string)$request->input('mode', 'manual');
        if (!in_array($mode, ['manual', 'auto'], true)) $mode = 'manual';
        $flash = $_SESSION['_barama_demo_import_flash'] ?? null;
        unset($_SESSION['_barama_demo_import_flash']);
        $stage = $_SESSION[self::SESSION] ?? null;
        $review = null;
        if (is_array($stage) && is_string($stage['sha256'] ?? null)) {
            try {
                $file = $this->stagedFile();
                if (!hash_equals($stage['sha256'], hash_file('sha256', $file))) {
                    throw new RuntimeException('DEMO_STAGED_SHA_MISMATCH');
                }
                if (($stage['source'] ?? '') !== 'publisher_signed_v1') {
                    throw new RuntimeException('DEMO_OLD_UNTRUSTED_STAGE_REJECTED');
                }
                $proof = (new DemoPublisherNativeArchiveV1())->inspectSigned(
                    $file, (array)($stage['publisher_metadata'] ?? []),
                    (array)($stage['publisher_identity'] ?? []),
                    DemoPublisherApiV1::PINNED_PUBLIC_KEY_SHA256
                );
                $review = [
                    'package_id' => (string)($stage['publisher_metadata']['id'] ?? ''),
                    'version' => (string)$proof['package_version'],
                    'records' => (int)$proof['record_count'],
                    'assets' => (int)$proof['media_images'],
                    'settings' => (int)$proof['settings_count'],
                    'expires_at' => 'Publisher signed',
                    'components' => array_keys(array_filter($proof['table_counts'])),
                ];
            } catch (Throwable) {
                $review = null;
            }
        }
        $passcodeEnabled = true;
        try {
            $user = (new AuthService())->user();
            if (is_array($user) && isset($user['id'])) {
                $passcodeEnabled = (new \Barama\Services\SecurityFactorService())->passcodeEnabled((int)$user['id']);
            }
        } catch (Throwable) {
            $passcodeEnabled = true;
        }
        return Response::html(View::render('workspace/demo-importer', [
            'pageTitle' => 'Live Demo Importer · Admin Control Center',
            'metaRobots' => 'noindex,nofollow,noarchive',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new WorkspaceSettingsController())->systemAdminNavigation(),
            'activeSection' => 'system-demo-importer',
            'demoMode' => $mode,
            'demoReleaseAccepted' => self::RELEASE_GATE === 'accepted',
            'demoDownloadEnabled' => self::RELEASE_GATE === 'download_only_staging_pending',
            'demoPasscodeEnabled' => $passcodeEnabled,
            'demoReview' => $review,
            'demoFlash' => is_array($flash) ? $flash : null,
            'demoSource' => 'https://baramarealestate.tonsamith.com',
            'demoPublisher' => 'https://tonsamith.com/dashboard/demo-content',
            'demoCommercialConfigured' => $this->commercialEntitlementReady(),
            'demoApiIdentity' => is_array($_SESSION['_barama_demo_api_identity'] ?? null)
                ? $_SESSION['_barama_demo_api_identity'] : null,
            'demoApiCatalog' => is_array($_SESSION['_barama_demo_api_catalog'] ?? null)
                ? $_SESSION['_barama_demo_api_catalog'] : null,
        ]));
    }

    /**
     * Read-only TLS + public signing identity probe.
     * The observed key is NOT automatically pinned for ZIP installation.
     */
    public function connection(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $identity = (new DemoPublisherApiV1())->pinnedSigningIdentity();
            $_SESSION['_barama_demo_api_identity'] = [
                'key_id' => $identity['key_id'],
                'fingerprint' => $identity['fingerprint'],
                'checked_at' => time(),
            ];
            return $this->flash('auto', 'success',
                'Publisher API reachable over HTTPS. Signing key ID: '
                . $identity['key_id'] . '. Independently pinned signing fingerprint matches. Installation remains disabled.');
        } catch (Throwable $error) {
            return $this->failure('auto', $error);
        }
    }

    /**
     * Real publisher discovery lists only published demos.
     * Draft package #2 is intentionally not exposed.
     */
    public function catalog(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $license = $this->licenseBindingForPublisher();
            $api = new DemoPublisherApiV1();
            $identity = $api->pinnedSigningIdentity();
            $result = $api->discover(
                $license['token'], $license['domain'], $license['installation_id']
            );
            if (isset($result['signing']['key_id']) &&
                !hash_equals($identity['key_id'], (string)$result['signing']['key_id'])) {
                throw new RuntimeException('DEMO_CATALOG_SIGNING_KEY_MISMATCH');
            }
            $_SESSION['_barama_demo_api_catalog'] = [
                'packages' => array_slice($result['packages'], 0, 20),
                'checked_at' => time(),
            ];
            return $this->flash('auto', 'success',
                count($result['packages']) . ' published demo package(s) found for the licensed installation. '
                . 'Draft packages are intentionally hidden.');
        } catch (Throwable $error) {
            unset($_SESSION['_barama_demo_api_catalog']);
            return $this->failure('auto', $error);
        }
    }

    /** @return array{token:string,domain:string,installation_id:string} */
    private function licenseBindingForPublisher(): array
    {
        $state = Installation::licenseState();
        $token = trim((string)($state['activation_token'] ?? ''));
        $domain = LicenseManagerClient::canonicalDomain(
            (string)(Installation::runtimeConfig()['APP_URL'] ?? '')
        );
        $registered = LicenseManagerClient::canonicalDomain((string)($state['domain'] ?? ''));
        $id = Installation::installationId();
        if ($token === '' || $domain === '' || $domain !== $registered ||
            ($state['installation_id'] ?? null) !== $id ||
            strtolower((string)($state['status'] ?? '')) !== 'active') {
            throw new RuntimeException('DEMO_ACTIVE_BOUND_LICENSE_REQUIRED');
        }
        return ['token' => $token, 'domain' => $domain, 'installation_id' => $id];
    }

    /** Manual upload requires exact membership in licensed published catalog. */
    public function manual(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $file = $request->files()['package'] ?? null;
            if (!is_array($file) || (int)($file['error'] ?? -1) !== UPLOAD_ERR_OK ||
                !is_string($file['tmp_name'] ?? null) ||
                !is_uploaded_file($file['tmp_name']) ||
                (int)($file['size'] ?? 0) < 500 ||
                (int)($file['size'] ?? 0) > 67108864) {
                throw new RuntimeException('DEMO_UPLOAD_INVALID_MAX_64MB');
            }
            $candidate = (string)$file['tmp_name'];
            $source = $this->publishedPackageForHash(hash_file('sha256', $candidate));
            $this->stageSigned($candidate, $source['metadata'], $source['identity'], 'manual');
            return $this->flash('manual', 'success',
                'Publisher-signed ZIP verified and privately staged. Nothing imported.');
        } catch (Throwable $e) { return $this->failure('manual', $e); }
    }

    /** Authorized publisher download -> signature -> native ZIP -> private stage. */
    public function automatic(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        if (self::RELEASE_GATE !== 'download_only_staging_pending') {
            return $this->flash('auto', 'error', 'Publisher download is not enabled.');
        }
        try {
            $binding = $this->licenseBindingForPublisher();
            $api = new DemoPublisherApiV1();
            $identity = $api->pinnedSigningIdentity();
            $catalog = $api->discover(
                $binding['token'], $binding['domain'], $binding['installation_id']
            );
            $selected = (string)$request->input('package_id', '');
            $available = [];
            foreach ($catalog['packages'] as $package) {
                if (($package['demo_slug'] ?? '') === 'live') {
                    $available[(string)$package['id']] = $package;
                }
            }
            if (!$available) throw new RuntimeException('DEMO_NO_PUBLISHED_LIVE_PACKAGE');
            if ($selected === '') $selected = (string)array_key_first($available);
            if (!isset($available[$selected])) {
                throw new RuntimeException('DEMO_SELECTED_PACKAGE_NOT_PUBLISHED');
            }
            $ticket = $api->authorize(
                $selected, $binding['token'], $binding['domain'], $binding['installation_id']
            );
            $metadata = $ticket['metadata'];
            if (!hash_equals((string)$available[$selected]['sha256'], (string)$metadata['sha256']) ||
                !hash_equals((string)$available[$selected]['key_id'], (string)$metadata['key_id'])) {
                throw new RuntimeException('DEMO_CATALOG_AUTH_MISMATCH');
            }
            $download = (new DemoPublisherDownloadV1())->receive(
                $ticket, $identity, DemoPublisherApiV1::PINNED_PUBLIC_KEY_SHA256,
                $this->vault()
            );
            $path = $download['path'];
            try {
                $this->stageSigned($path, $metadata, $identity, 'auto');
            } finally {
                if (is_file($path) && !is_link($path)) @unlink($path);
            }
            return $this->flash('auto', 'success',
                'Official signed demo downloaded and staged. Installation remains disabled pending staging.');
        } catch (Throwable $e) { return $this->failure('auto', $e); }
    }

    /** @return array{metadata:array,identity:array} */
    private function publishedPackageForHash(string $hash): array
    {
        $binding = $this->licenseBindingForPublisher();
        $api = new DemoPublisherApiV1();
        $identity = $api->pinnedSigningIdentity();
        $catalog = $api->discover(
            $binding['token'], $binding['domain'], $binding['installation_id']
        );
        foreach ($catalog['packages'] as $metadata) {
            if (($metadata['demo_slug'] ?? '') === 'live' &&
                is_string($metadata['sha256'] ?? null) &&
                hash_equals($metadata['sha256'], $hash)) {
                return ['metadata' => $metadata, 'identity' => $identity];
            }
        }
        throw new RuntimeException('DEMO_ZIP_NOT_IN_LICENSED_PUBLISHED_CATALOG');
    }

    /** This handler can never modify production content until staging acceptance. */
    public function install(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        return $this->flash('manual', 'error',
            'Database and media installation is disabled until fresh staging and rollback acceptance pass.');
    }

    private function stageSigned(string $candidate, array $metadata, array $identity, string $mode): void
    {
        $proof = (new DemoPublisherNativeArchiveV1())->inspectSigned(
            $candidate, $metadata, $identity,
            DemoPublisherApiV1::PINNED_PUBLIC_KEY_SHA256
        );
        if ($proof['package_version'] !== ($metadata['version'] ?? null)) {
            throw new RuntimeException('DEMO_PUBLISHER_VERSION_MISMATCH');
        }
        $dir = $this->vault();
        $name = 'staged-' . bin2hex(random_bytes(16)) . '.zip';
        $destination = $dir . '/' . $name;
        if (!copy($candidate, $destination)) throw new RuntimeException('DEMO_STAGE_COPY_FAILED');
        chmod($destination, 0600);
        if (!hash_equals((string)$proof['zip_sha256'], hash_file('sha256', $destination))) {
            @unlink($destination);
            throw new RuntimeException('DEMO_STAGE_DIGEST_FAILED');
        }
        $old = $_SESSION[self::SESSION]['file'] ?? null;
        if (is_string($old) && preg_match('/^staged-[a-f0-9]{32}\.zip$/D', $old)) {
            $prior = $dir . '/' . $old;
            if (is_file($prior) && !is_link($prior)) @unlink($prior);
        }
        $_SESSION[self::SESSION] = [
            'file' => $name,
            'sha256' => $proof['zip_sha256'],
            'source' => 'publisher_signed_v1',
            'publisher_metadata' => $metadata,
            'publisher_identity' => $identity,
            'mode' => $mode,
            'created_at' => time(),
        ];
    }

    private function stagedFile(): string
    {
        $stage = $_SESSION[self::SESSION] ?? null;
        if (!is_array($stage) || !is_string($stage['file'] ?? null) ||
            !preg_match('/^staged-[a-f0-9]{32}\.zip$/D', $stage['file']) ||
            (int)($stage['created_at'] ?? 0) < time() - 3600) {
            throw new RuntimeException('DEMO_STAGE_NOT_AVAILABLE_OR_EXPIRED');
        }
        $path = $this->vault() . '/' . $stage['file'];
        if (!is_file($path) || is_link($path)) throw new RuntimeException('DEMO_STAGED_PACKAGE_UNSAFE');
        return $path;
    }

    private function vault(): string
    {
        // Private sibling of website root; no ZIP within served application.
        $site = dirname(APP_PATH);
        $dir = dirname($site) . '/.barama-private-demo-' .
            substr(hash('sha256', $site), 0, 18);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('DEMO_STORAGE_UNAVAILABLE');
        }
        if (is_link($dir) || (fileperms($dir) & 0077) !== 0) {
            throw new RuntimeException('DEMO_STORAGE_UNSAFE');
        }
        return $dir;
    }

    private function commercialLicense(): array
    {
        $license = Installation::licenseState();
        if (!is_array($license) || strtolower((string)($license['status'] ?? '')) !== 'active' ||
            (string)($license['product_slug'] ?? '') !== 'barama-real-estate' ||
            !is_string($license['activation_token'] ?? null)) {
            throw new RuntimeException('DEMO_COMMERCIAL_LICENSE_REQUIRED');
        }
        $domain = LicenseManagerClient::canonicalDomain(
            (string)(Installation::runtimeConfig()['APP_URL'] ?? ''));
        $registered = LicenseManagerClient::canonicalDomain((string)($license['domain'] ?? ''));
        if ($domain === '' || $domain !== $registered ||
            (string)($license['installation_id'] ?? '') !== Installation::installationId()) {
            throw new RuntimeException('DEMO_LICENSE_BINDING_MISMATCH');
        }
        $remote = (new LicenseManagerClient())->validateActive(
            (string)$license['activation_token'], $domain, Installation::installationId()
        );
        // Only the publisher may assert demo entitlement; tester licenses
        // cannot get it by choosing a UI option or uploading an unsigned ZIP.
        if (($remote['demo_content_entitled'] ?? null) !== true) {
            throw new RuntimeException('DEMO_PUBLISHER_ENTITLEMENT_NOT_CONFIGURED');
        }
        return $license;
    }

    private function commercialEntitlementReady(): bool
    {
        try {
            $license = Installation::licenseState();
            return is_array($license) &&
                strtolower((string)($license['status'] ?? '')) === 'active' &&
                (string)($license['product_slug'] ?? '') === 'barama-real-estate';
        } catch (Throwable) { return false; }
    }

    private function admin(): bool
    {
        $auth = new AuthService();
        return $auth->hasRole('administrator') || $auth->hasRole('super-administrator');
    }

    private function failure(string $mode, Throwable $error): Response
    {
        $code = $error instanceof RuntimeException ? $error->getMessage() : 'DEMO_ACTION_REJECTED';
        if (!preg_match('/^[A-Z0-9_]{4,90}$/D', $code)) $code = 'DEMO_ACTION_REJECTED';
        return $this->flash($mode, 'error', 'Action safely stopped: ' . $code . '. No installation is claimed.');
    }

    private function flash(string $mode, string $tone, string $message): Response
    {
        $_SESSION['_barama_demo_import_flash'] = ['tone' => $tone, 'message' => $message];
        return Response::redirect(self::URL . '?mode=' . $mode, 303);
    }
}
