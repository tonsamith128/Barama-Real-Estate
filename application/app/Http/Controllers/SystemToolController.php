<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Services\AuthService;
use Barama\Services\SystemToolService;
use RuntimeException;
use Throwable;

/** BARAMA_SYSTEM_TOOL_CONTROLLER_V100 — no public or developer access. */
final class SystemToolController
{
    private const BASE = '/dashboard/system/tool';

    public function index(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        $service = new SystemToolService();
        $tab = (string) $request->input('tab', 'importer');
        if (!in_array($tab, ['importer', 'exporter', 'backup', 'restore'], true)) $tab = 'importer';
        $flash = $_SESSION['_barama_system_tool_flash'] ?? null;
        unset($_SESSION['_barama_system_tool_flash']);
        $datasets = [];
        $backups = [];
        $error = '';
        try {
            $datasets = $service->datasets();
            $backups = $service->backups();
        } catch (Throwable) {
            $error = 'Database metadata is temporarily unavailable. No operations have been performed.';
        }
        $passcodeEnabled = true;
        try {
            $user = auth_user();
            if (is_array($user) && isset($user['id'])) {
                $passcodeEnabled = (new \Barama\Services\SecurityFactorService())->passcodeEnabled((int) $user['id']);
            }
        } catch (Throwable) {
            $passcodeEnabled = true; // Fail closed: never suppress a required confirmation.
        }
        return Response::html(View::render('workspace/system-tool', [
            'pageTitle' => 'Tool · System · Admin Control Center',
            'metaRobots' => 'noindex,nofollow,noarchive',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new WorkspaceSettingsController())->systemAdminNavigation(),
            'activeSection' => 'system-tool',
            'toolTab' => $tab,
            'toolFlash' => is_array($flash) ? $flash : null,
            'toolDatasets' => $datasets,
            'toolBackups' => $backups,
            'toolStorageReady' => $service->storageReady(),
            'toolPasscodeEnabled' => $passcodeEnabled,
            'toolError' => $error,
        ]));
    }

    public function export(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $table = (string) $request->input('dataset', '');
            $csv = (new SystemToolService())->exportCsv($table);
            return Response::binary($csv, 'text/csv; charset=UTF-8', [
                'Content-Disposition' => 'attachment; filename="barama-' . $table . '-' . gmdate('Ymd-His') . '.csv"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $error) {
            return $this->failed('exporter', $error);
        }
    }

    public function import(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $table = (string) $request->input('dataset', '');
            $mode = (string) $request->input('mode', '');
            if (!in_array($mode, ['preview', 'import'], true)) {
                throw new RuntimeException('IMPORT_MODE_INVALID');
            }
            $file = $request->files()['file'] ?? null;
            if (!is_array($file)) throw new RuntimeException('CSV_FILE_REQUIRED');
            $result = (new SystemToolService())->importCsv($table, $file, $mode === 'preview');
            $verb = $mode === 'preview' ? 'Validated (not imported)' : 'Imported';
            return $this->succeeded('importer', $verb . ': ' . $result['rows'] .
                ' rows and ' . $result['columns'] . ' columns. ' .
                ($mode === 'preview' ? 'All preview inserts were rolled back.' : 'Changes committed.'));
        } catch (Throwable $error) {
            return $this->failed('importer', $error);
        }
    }

    public function backup(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $result = (new SystemToolService())->backup();
            return $this->succeeded('backup', 'Database snapshot created: ' . $result['id'] .
                '. ' . $result['tables'] . ' tables and ' . $result['rows'] .
                ' rows. Stored privately and ready for download.');
        } catch (Throwable $error) {
            return $this->failed('backup', $error);
        }
    }

    public function download(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            $id = (string) $request->input('id', '');
            $path = (new SystemToolService())->verifiedBackup($id);
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) throw new RuntimeException('BACKUP_READ_FAILED');
            return Response::binary($bytes, 'application/zip', [
                'Content-Disposition' => 'attachment; filename="barama-database-' . $id . '.zip"',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $error) {
            return $this->failed('backup', $error);
        }
    }

    public function restore(Request $request): Response
    {
        if (!$this->admin()) return Response::html('Forbidden', 403);
        try {
            if ((string) $request->input('confirmation', '') !== 'RESTORE DATABASE') {
                throw new RuntimeException('RESTORE_TYPED_APPROVAL_REQUIRED');
            }
            $id = (string) $request->input('backup_id', '');
            $result = (new SystemToolService())->restore($id);
            return $this->succeeded('restore', 'Database restored from snapshot ' . $id .
                '. Restored ' . $result['tables'] . ' tables and ' . $result['rows'] .
                ' rows. Pre-restore safety backup: ' . $result['safety_backup'] . '.');
        } catch (Throwable $error) {
            return $this->failed('restore', $error);
        }
    }

    private function admin(): bool
    {
        $auth = new AuthService();
        return $auth->hasRole('administrator') || $auth->hasRole('super-administrator');
    }

    private function succeeded(string $tab, string $message): Response
    {
        $_SESSION['_barama_system_tool_flash'] = ['tone' => 'success', 'message' => $message];
        return Response::redirect(self::BASE . '?tab=' . $tab, 303);
    }

    private function failed(string $tab, Throwable $error): Response
    {
        $code = $error instanceof RuntimeException ? $error->getMessage() : 'OPERATION_REJECTED';
        if (preg_match('/^[A-Z0-9_]{4,60}$/D', $code) !== 1) $code = 'OPERATION_REJECTED';
        $_SESSION['_barama_system_tool_flash'] = [
            'tone' => 'error',
            'message' => 'Operation safely stopped: ' . $code . '. No success is claimed.',
        ];
        return Response::redirect(self::BASE . '?tab=' . $tab, 303);
    }
}
