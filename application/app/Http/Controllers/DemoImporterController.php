<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Installation;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\AuthService;
use Barama\Services\DemoImportPackageV1;
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
    private const AUTO_URL = 'https://tonsamith.com/license/v2/demo/package';
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
            'demoPasscodeEnabled' => $passcodeEnabled,
            'demoReview' => $review,
            'demoFlash' => is_array($flash) ? $flash : null,
            'demoSource' => 'https://baramarealestate.tonsamith.com',
            'demoPublisher' => 'https://tonsamith.com/dashboard/demo-content',
            'demoCommercialConfigured' => $this->commercialEntitlementReady(),
        ]));
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

    /** Publisher-only HTTPS POST; the publisher must explicitly entitlement-gate this endpoint. */
    public function automatic(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $license = $this->commercialLicense();
            if (!extension_loaded('curl')) throw new RuntimeException('DEMO_HTTPS_UNAVAILABLE');
            $runtime = Installation::runtimeConfig();
            $payload = json_encode([
                'activation_token' => $license['activation_token'],
                'installation_id' => $license['installation_id'],
                'domain' => $license['domain'],
                'product_slug' => 'barama-real-estate',
                'package' => 'barama-live-demo',
                'mode' => 'download',
            ], JSON_THROW_ON_ERROR);
            $ch = curl_init(self::AUTO_URL);
            if ($ch === false) throw new RuntimeException('DEMO_PUBLISHER_UNAVAILABLE');
            $buffer = '';
            try {
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/zip'],
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_TIMEOUT => 45,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_HEADER => false,
                    CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer): int {
                        if (strlen($buffer) + strlen($chunk) > 67108864) return 0;
                        $buffer .= $chunk;
                        return strlen($chunk);
                    },
                ]);
                $ok = curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            } finally { curl_close($ch); }
            if ($ok !== true || $status !== 200 ||
                !str_starts_with(strtolower($type), 'application/zip') || strlen($buffer) < 500) {
                throw new RuntimeException('DEMO_PUBLISHER_PACKAGE_NOT_AVAILABLE');
            }
            $dir = $this->vault();
            $candidate = tempnam($dir, 'demo-auto-');
            if ($candidate === false) throw new RuntimeException('DEMO_PRIVATE_STAGE_UNAVAILABLE');
            try {
                chmod($candidate, 0600);
                if (file_put_contents($candidate, $buffer, LOCK_EX) !== strlen($buffer)) {
                    throw new RuntimeException('DEMO_PRIVATE_STAGE_WRITE_FAILED');
                }
                $this->storeVerified($candidate, 'auto');
            } finally { @unlink($candidate); }
            return $this->flash('auto', 'success', 'Publisher-entitled signed demo downloaded and verified. Nothing installed yet.');
        } catch (Throwable $e) { return $this->failure('auto', $e); }
    }

    /** Database mutation: fresh install only, backup and explicit owner approval. */
    public function install(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
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
