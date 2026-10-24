<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Installation;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\AuthService;
use Barama\Services\DemoImportPackageV1;
use Barama\Services\DemoPublisherApiV1;
use Barama\Services\LicenseManagerClient;
use Barama\Services\Updates\PublisherTrustPaths;
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
    public const RELEASE_GATE = 'publisher_pending';
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
                $review = (new DemoImportPackageV1())->inspect($file);
                unset($review['content']);
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
            $identity = (new DemoPublisherApiV1())->signingIdentity();
            $_SESSION['_barama_demo_api_identity'] = [
                'key_id' => $identity['key_id'],
                'fingerprint' => $identity['fingerprint'],
                'checked_at' => time(),
            ];
            return $this->flash('auto', 'success',
                'Publisher API reachable over HTTPS. Signing key ID: '
                . $identity['key_id'] . '. Identity observed; not yet pinned for installation.');
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
            $result = (new DemoPublisherApiV1())->discover(
                $license['token'], $license['domain'], $license['installation_id']
            );
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

    /** Read-only preview; no commercial entitlement or DB modification required. */
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
            $this->storeVerified((string)$file['tmp_name'], 'manual');
            return $this->flash('manual', 'success', 'Publisher-signed demo package verified and staged. Nothing imported yet.');
        } catch (Throwable $e) { return $this->failure('manual', $e); }
    }

    /**
     * Auto ZIP acquisition remains disabled until the real publisher download
     * ticket + digest + pinned-signature + staging importer gates pass.
     * This removes the obsolete, unsupported download endpoint.
     */
    public function automatic(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        return $this->flash('auto', 'error',
            'Automatic download is still disabled. Use the read-only catalog check '
            . 'until the real publisher package is published and staging acceptance passes.');
    }

    /** Database mutation: fresh install only, backup and explicit owner approval. */
    public function install(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        if (self::RELEASE_GATE !== 'accepted') {
            return $this->flash('manual', 'error', 'Import is disabled until publisher package and staging restore acceptance pass.');
        }
        try {
            if ((string)$request->input('confirmation', '') !== 'IMPORT LIVE DEMO') {
                throw new RuntimeException('DEMO_TYPED_CONFIRMATION_REQUIRED');
            }
            $this->commercialLicense();
            $file = $this->stagedFile();
            $stage = $_SESSION[self::SESSION] ?? [];
            if (!is_array($stage) || !hash_equals((string)($stage['sha256'] ?? ''), hash_file('sha256', $file))) {
                throw new RuntimeException('DEMO_STAGED_PACKAGE_CHANGED');
            }
            $user = (new AuthService())->user();
            $result = (new DemoImportPackageV1())->install($file, (int)($user['id'] ?? 0));
            unset($_SESSION[self::SESSION]);
            return $this->flash('manual', 'success',
                'Live Demo import completed: ' . $result['records'] . ' records, ' .
                $result['assets'] . ' images, ' . $result['settings'] .
                ' configuration groups. Pre-import database backup: ' .
                $result['pre_import_backup'] . '.');
        } catch (Throwable $e) { return $this->failure('manual', $e); }
    }

    private function storeVerified(string $candidate, string $mode): void
    {
        $proof = (new DemoImportPackageV1())->inspect($candidate);
        $dir = $this->vault();
        $name = 'staged-' . bin2hex(random_bytes(16)) . '.zip';
        $destination = $dir . '/' . $name;
        if (!copy($candidate, $destination)) throw new RuntimeException('DEMO_PRIVATE_STAGE_FAILED');
        chmod($destination, 0600);
        if (!hash_equals($proof['digest'], hash_file('sha256', $destination))) {
            @unlink($destination);
            throw new RuntimeException('DEMO_STAGE_DIGEST_MISMATCH');
        }
        $previous = $_SESSION[self::SESSION]['file'] ?? null;
        if (is_string($previous) && preg_match('/^staged-[a-f0-9]{32}\.zip$/D', $previous)) {
            @unlink($dir . '/' . $previous);
        }
        $_SESSION[self::SESSION] = ['file' => $name,
            'sha256' => $proof['digest'], 'mode' => $mode, 'created_at' => time()];
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
        $dir = APP_PATH . '/storage/app/demo-imports';
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
